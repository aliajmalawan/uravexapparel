<?php

declare(strict_types=1);

use App\Auth\Permissions;
use App\Controllers\Admin\AdminController;
use App\Core\Logger;
use App\Core\Money;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Payments\CryptoTransferProvider;
use App\Services\OrderState;
use App\Services\Pricing;

T::$current = 'Money';
T::eq(249900, Money::parse('2499'), 'parses whole rupees');
T::eq(249950, Money::parse('2,499.5'), 'parses one decimal with thousands separator');
T::eq(249905, Money::parse('2499.05'), 'parses two decimals');
T::eq(null, Money::parse('2499.555'), 'rejects too many decimals');
T::eq(null, Money::parse('-10'), 'rejects negatives');
T::eq(null, Money::parse('1e5'), 'rejects scientific notation');
T::eq('Rs 2,499', Money::format(249900), 'formats without zero decimals');
T::eq('Rs 2,499.50', Money::format(249950), 'formats decimals');
T::eq('2499.50', Money::decimal(249950), 'decimal string');
T::eq(25, Money::percentOf(250, 10), 'percent half-up 25.0');
T::eq(13, Money::percentOf(125, 10), 'percent half-up 12.5 → 13');
T::eq(12, Money::percentOf(124, 10), 'percent 12.4 → 12');
$parts = Money::allocate(1000, [333, 333, 334]);
T::eq(1000, array_sum($parts), 'allocation sums exactly');
T::eq([333, 333, 334], $parts, 'allocation proportional');
T::eq(100, array_sum(Money::allocate(100, [1, 1, 1])), 'allocation of indivisible amount sums exactly');

T::$current = 'Pricing';
$p = Pricing::unitPrice(['price_minor' => 500000, 'sale_price_minor' => 400000]);
T::eq(400000, $p['price'], 'product sale price applies');
T::ok($p['on_sale'], 'on_sale flag');
$p = Pricing::unitPrice(['price_minor' => 500000, 'sale_price_minor' => 600000]);
T::eq(500000, $p['price'], 'sale above regular is ignored');
$p = Pricing::unitPrice(['price_minor' => 500000, 'sale_price_minor' => 400000], ['price_minor' => 550000, 'sale_price_minor' => null]);
T::eq(550000, $p['price'], 'variant price override ignores product sale');
$p = Pricing::unitPrice(['price_minor' => 500000, 'sale_price_minor' => null], ['price_minor' => null, 'sale_price_minor' => 450000]);
T::eq(450000, $p['price'], 'variant sale price on product price');

$lines = [['key' => 1, 'unit' => 100000, 'qty' => 2], ['key' => 2, 'unit' => 50000, 'qty' => 1]];
$t = Pricing::totals($lines, null, 25000, null);
T::eq(250000, $t['subtotal'], 'subtotal');
T::eq(275000, $t['total'], 'total with shipping');
$t = Pricing::totals($lines, ['type' => 'percentage', 'value' => 10], 25000, 200000);
T::eq(25000, $t['discount'], '10% discount');
T::eq(0, $t['shipping'], 'free shipping over threshold after discount');
T::eq(225000, $t['total'], 'total after discount and free shipping');
T::eq(25000, array_sum($t['line_discounts']), 'line discounts sum to order discount');
$t = Pricing::totals($lines, ['type' => 'fixed', 'value' => 999999999], 25000, null);
T::eq(250000, $t['discount'], 'fixed discount capped at subtotal');
T::eq(25000, $t['total'], 'never negative');
$t = Pricing::totals($lines, ['type' => 'percentage', 'value' => 50, 'max_discount_minor' => 30000], null, null);
T::eq(30000, $t['discount'], 'max discount cap');
$t = Pricing::totals([['key' => 1, 'unit' => 100000, 'qty' => 1, 'eligible' => true], ['key' => 2, 'unit' => 100000, 'qty' => 1, 'eligible' => false]], ['type' => 'percentage', 'value' => 10], null, null);
T::eq(10000, $t['discount'], 'percentage applies only to eligible lines');
T::eq(0, $t['line_discounts'][2], 'ineligible line gets no discount');
$t = Pricing::totals($lines, ['type' => 'free_shipping', 'value' => 0, 'free_shipping' => 1], 25000, null);
T::eq(0, $t['shipping'], 'free-shipping coupon');
$t = Pricing::totals($lines, null, 25000, null, 5);
T::eq(12500, $t['tax'], 'tax percent on discounted subtotal');
T::ok(Pricing::fingerprint($t, $lines) !== Pricing::fingerprint(Pricing::totals($lines, null, 30000, null, 5), $lines), 'fingerprint changes with shipping');

T::$current = 'OrderState';
T::ok(OrderState::canTransition('confirmed', 'processing'), 'confirmed → processing');
T::ok(OrderState::canTransition('packed', 'shipped'), 'packed → shipped');
T::ok(!OrderState::canTransition('pending', 'shipped'), 'cannot skip to shipped');
T::ok(!OrderState::canTransition('shipped', 'cancelled'), 'cannot cancel shipped');
T::ok(!OrderState::canTransition('delivered', 'processing'), 'cannot go backwards');
T::ok(!OrderState::canTransition('cancelled', 'confirmed'), 'cancelled is terminal');
T::ok(OrderState::isTerminal('refunded'), 'refunded is terminal');
T::ok(!in_array('cancelled', OrderState::manualNext(['status' => 'confirmed', 'payment_status' => 'paid']), true), 'paid orders cannot be cancelled manually');
T::ok(!in_array('refunded', OrderState::manualNext(['status' => 'delivered', 'payment_status' => 'paid']), true), 'refunded only via refund flow');
T::ok(!in_array('confirmed', OrderState::manualNext(['status' => 'awaiting_payment', 'payment_status' => 'unpaid']), true), 'awaiting payment cannot be confirmed manually');

T::$current = 'Permissions';
T::ok(Permissions::allows('owner', 'settings.manage'), 'owner has everything');
T::ok(Permissions::allows('manager', 'orders.refund'), 'manager can refund');
T::ok(!Permissions::allows('manager', 'staff.manage'), 'manager cannot manage staff');
T::ok(!Permissions::allows('manager', 'payments.configure'), 'manager cannot configure payments');
T::ok(Permissions::allows('order_staff', 'orders.payments'), 'order staff verify payments');
T::ok(!Permissions::allows('order_staff', 'orders.refund'), 'order staff cannot refund');
T::ok(!Permissions::allows('order_staff', 'reports.export'), 'order staff cannot export');
T::ok(!Permissions::allows('content_editor', 'orders.view'), 'content editor cannot see orders');
T::ok(!Permissions::allows('customer', 'dashboard.view'), 'customer has no admin access');
T::ok(!Permissions::isStaff('customer'), 'customer is not staff');

T::$current = 'Validator';
$v = Validator::make(['email' => 'bad', 'qty' => '5', 'price' => '12.50'], ['email' => 'required|email', 'qty' => 'required|int|min:1|max:3', 'price' => 'money', 'name' => 'required']);
T::ok(isset($v->errors()['email'], $v->errors()['qty'], $v->errors()['name']), 'reports each invalid field');
$d = Validator::make(['price' => '12.50', 'flag' => '1', 'n' => ' 7 '], ['price' => 'money', 'flag' => 'bool', 'n' => 'int'])->validated();
T::eq(1250, $d['price'], 'money cast to minor units');
T::eq(true, $d['flag'], 'bool cast');
T::eq(7, $d['n'], 'int cast with trim');
T::throws(ValidationException::class, fn () => Validator::make(['p' => 'abc', 'p_confirmation' => 'abd'], ['p' => 'confirmed'])->validated(), 'confirmed rule');
T::throws(ValidationException::class, fn () => Validator::make(['l' => 'javascript:alert(1)'], ['l' => 'link'])->validated(), 'link rule rejects javascript:');
T::throws(ValidationException::class, fn () => Validator::make(['l' => '//evil.example'], ['l' => 'link'])->validated(), 'link rule rejects protocol-relative');

T::$current = 'Sanitizer';
$html = Sanitizer::html('<p onclick="x()">Hi <script>alert(1)</script><a href="javascript:alert(1)">x</a><img src=x onerror=alert(1)><strong>ok</strong><iframe src="//x"></iframe></p>');
T::ok(!str_contains($html, 'script') && !str_contains($html, 'onclick') && !str_contains($html, 'javascript:') && !str_contains($html, 'onerror') && !str_contains($html, 'iframe'), 'removes scripts, handlers, js URLs, iframes');
T::ok(str_contains($html, '<strong>ok</strong>'), 'keeps allowed markup');
T::ok(str_contains(Sanitizer::html('<a href="https://x.example">x</a>'), 'rel="noopener noreferrer nofollow"'), 'external links get rel');

T::$current = 'Logger';
$r = Logger::redact(['password' => 'secret', 'token' => 'abc', 'nested' => ['api_key' => 'k', 'note' => 'card 4111 1111 1111 1111'], 'email' => 'person@example.com']);
T::eq('[redacted]', $r['password'], 'password redacted');
T::eq('[redacted]', $r['token'], 'token redacted');
T::eq('[redacted]', $r['nested']['api_key'], 'nested key redacted');
T::ok(!str_contains($r['nested']['note'], '4111'), 'card-like number redacted');
T::eq('p*****@example.com', $r['email'], 'email masked');

T::$current = 'CSV safety';
T::eq("'=HYPERLINK(\"x\")", AdminController::cell('=HYPERLINK("x")'), 'formula cells are neutralised');
T::eq('-5', AdminController::cell('-5'), 'negative numbers untouched');
T::eq('plain', AdminController::cell('plain'), 'plain text untouched');

T::$current = 'Crypto payments';
T::ok(CryptoTransferProvider::validAddress('usdt_trc20', 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE'), 'valid TRC20 address accepted');
T::ok(!CryptoTransferProvider::validAddress('usdt_trc20', '0x52908400098527886E0F7030069857D2E4169EE7'), 'Ethereum address rejected for TRC20');
T::ok(CryptoTransferProvider::validAddress('usdt_erc20', '0x52908400098527886E0F7030069857D2E4169EE7'), 'valid ERC20 address accepted');
T::ok(!CryptoTransferProvider::validAddress('eth', '0x5290840009852788'), 'short ETH address rejected');
T::ok(CryptoTransferProvider::validAddress('btc', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'), 'bech32 BTC address accepted');
T::ok(CryptoTransferProvider::validAddress('btc', '1BoatSLRHtKNngkdXEeobR76b53LETtpyT'), 'legacy BTC address accepted');
T::ok(!CryptoTransferProvider::validAddress('btc', 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE'), 'TRON address rejected for BTC');
T::ok(!CryptoTransferProvider::validAddress('btc', 'bc1q<script>'), 'markup rejected');
$hex = str_repeat('ab12', 16);
T::ok((new CryptoTransferProvider('usdt_trc20'))->validReference($hex), 'TRON TXID: 64 hex');
T::ok(!(new CryptoTransferProvider('usdt_trc20'))->validReference('0x' . $hex), 'TRON TXID has no 0x prefix');
T::ok((new CryptoTransferProvider('usdt_erc20'))->validReference('0x' . $hex), 'Ethereum TXID: 0x + 64 hex');
T::ok(!(new CryptoTransferProvider('eth'))->validReference($hex), 'Ethereum TXID needs 0x');
T::ok((new CryptoTransferProvider('btc'))->validReference($hex), 'Bitcoin TXID: 64 hex');
T::ok(!(new CryptoTransferProvider('btc'))->validReference('TXN-555'), 'free text is not a TXID');
T::eq('https://tronscan.org/#/transaction/' . $hex, (new CryptoTransferProvider('usdt_trc20'))->explorerUrl($hex), 'explorer link for TRON');
T::eq(null, (new CryptoTransferProvider('eth'))->explorerUrl('javascript:alert(1)'), 'no explorer link for invalid hashes');
T::eq('12.34000000', CryptoTransferProvider::toUsdt(1234, 'USDT'), 'USDT store total is 1:1');
T::eq('12.34000000', CryptoTransferProvider::toUsdt(1234, 'USD'), 'USD store total is 1:1');
T::throws(\InvalidArgumentException::class, fn () => new CryptoTransferProvider('paypal'), 'unknown crypto method rejected');

T::$current = 'US addresses';
$ok = ['full_name' => 'Jane Doe', 'phone' => '(212) 555-0147', 'line1' => '350 Fifth Ave', 'line2' => '', 'city' => 'New York', 'province' => 'NY', 'postal_code' => '10118'];
T::ok(!Validator::make($ok, App\Services\Region::addressRules())->fails(), 'valid US address accepted');
foreach (['+1 212 555 0147', '212-555-0147', '2125550147'] as $ph) {
    T::ok(!Validator::make(['phone' => $ph] + $ok, App\Services\Region::addressRules())->fails(), "US phone format {$ph} accepted");
}
T::ok(Validator::make(['phone' => '03001234567'] + $ok, App\Services\Region::addressRules())->fails(), 'Pakistani mobile number rejected');
T::ok(!Validator::make(['postal_code' => '10118-0110'] + $ok, App\Services\Region::addressRules())->fails(), 'ZIP+4 accepted');
T::ok(Validator::make(['postal_code' => '1011'] + $ok, App\Services\Region::addressRules())->fails(), 'short ZIP rejected');
T::ok(Validator::make(['postal_code' => ''] + $ok, App\Services\Region::addressRules())->fails(), 'ZIP required');
T::ok(Validator::make(['province' => 'Punjab'] + $ok, App\Services\Region::addressRules())->fails(), 'non-US state rejected');
T::eq('State is required.', Validator::make(['province' => ''] + $ok, App\Services\Region::addressRules())->errors()['province'] ?? null, 'error message says "State"');
T::eq('New York, NY 10118', App\Services\Region::locality($ok), 'address shown as City, ST ZIP');
T::eq('California', App\Services\Region::stateName('ca'), 'state name from code');


T::$current = 'Marketplace commission';
T::eq(100, App\Services\Marketplace::commissionOn(999, '10.00'), 'commission rounds half-up (99.9 → 100)');
T::eq(126, App\Services\Marketplace::commissionOn(1005, '12.50'), 'fractional commission rate');
T::eq(0, App\Services\Marketplace::commissionOn(5000, '0.00'), 'zero commission');
T::eq('7.50', App\Services\Marketplace::commissionPercent(['commission_percent' => '7.5']), 'seller override used');

T::$current = 'Phone numbers';
foreach (['(646) 555-0199', '+1 646 555 0199', '646.555.0199', '6465550199'] as $ph) {
    T::ok(!Validator::make(['p' => $ph], ['p' => 'phone'])->fails(), "phone {$ph} accepted");
}
T::ok(Validator::make(['p' => '(12)'], ['p' => 'phone'])->fails(), 'too-short phone rejected');
