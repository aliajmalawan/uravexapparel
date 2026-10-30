<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;

/**
 * In-process HTTP tests: requests go through the real router, middleware
 * (auth, permissions, CSRF) and controllers.
 */
function http(string $method, string $path, array $post = [], ?array $user = null, bool $withCsrf = true, array $query = []): array
{
    Auth::actingAs($user);
    if ($user === null) {
        App\Core\Session::forget('uid');
    }
    if ($method === 'POST' && $withCsrf) {
        $post['_token'] = Csrf::token();
    }
    $req = new Request($method, $path, $query, $post, [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'text/html'], []);
    View::share('currentPath', $path);
    View::share('request', $req);
    $router = new Router();
    require APP_PATH . '/routes.php';
    try {
        $res = $router->dispatch($req);
    } catch (\Throwable $e) {
        $res = ErrorHandler::toResponse($e, $req);
    }
    return [$res->status, $res->headers['Location'] ?? null, $res->body];
}

reset_db();
$db = Database::get();
$customer = Fx::user('customer');
$other = Fx::user('customer');
$owner = Fx::user('owner');
$manager = Fx::user('manager');
$staff = Fx::user('order_staff');
$editor = Fx::user('content_editor');
$rate = Fx::shipping();
$p = Fx::product(100000, null, 5);

T::$current = 'HTTP storefront';
foreach (['/', '/shop', '/collections', '/new-arrivals', '/sale', '/cart', '/login', '/register', '/faq', '/contact', '/track-order', '/sitemap.xml', '/robots.txt'] as $path) {
    [$s] = http('GET', $path);
    T::eq(200, $s, "GET {$path}");
}
[$s] = http('GET', '/product/does-not-exist');
T::eq(404, $s, 'unknown product is 404');
[$s, , $body] = http('GET', '/product/' . $db->value('SELECT slug FROM products LIMIT 1'));
T::eq(200, $s, 'product page renders');
T::ok(str_contains($body, '"@type":"Product"'), 'product structured data present');
$db->exec("UPDATE products SET status = 'draft'");
[$s] = http('GET', '/product/' . $db->value('SELECT slug FROM products LIMIT 1'));
T::eq(404, $s, 'draft products are hidden from customers');
$db->exec("UPDATE products SET status = 'active'");

T::$current = 'HTTP auth & CSRF';
[$s, $loc] = http('GET', '/account');
T::ok($s === 303 && str_ends_with((string) $loc, '/login'), 'account requires login');
[$s, $loc] = http('GET', '/admin');
T::ok($s === 303 && str_ends_with((string) $loc, '/login'), 'admin requires login');
[$s, $loc, $body] = http('GET', '/admin', [], $customer);
T::ok($s === 303 && str_ends_with((string) $loc, '/admin/login') && !str_contains((string) $body, 'admin-shell'), 'customers cannot open admin (sent to the admin login)');
[$s] = http('POST', '/cart/add', ['variant_id' => $p['variant_id'], 'quantity' => 1], null, false);
T::eq(419, $s, 'POST without CSRF token rejected');
[$s] = http('POST', '/cart/add', ['variant_id' => $p['variant_id'], 'quantity' => 1, '_token' => str_repeat('a', 64)], null, false);
T::eq(419, $s, 'POST with wrong CSRF token rejected');
[$s] = http('POST', '/cart/add', ['variant_id' => $p['variant_id'], 'quantity' => 1]);
T::eq(303, $s, 'POST with CSRF token accepted');

T::$current = 'HTTP login';
[$s, $loc] = http('POST', '/login', ['email' => $customer['email'], 'password' => 'wrong-password']);
T::ok($s === 303 && Auth::user() === null, 'wrong password does not sign in');
[$s, $loc] = http('POST', '/login', ['email' => $customer['email'], 'password' => 'Password123']);
T::ok($s === 303 && Auth::id() === (int) $customer['id'], 'correct password signs in');
for ($i = 0; $i < 6; $i++) {
    http('POST', '/login', ['email' => $other['email'], 'password' => 'nope-' . $i]);
}
[$s] = http('POST', '/login', ['email' => $other['email'], 'password' => 'Password123']);
T::ok(Auth::user() === null || (int) (Auth::user()['id'] ?? 0) !== (int) $other['id'], 'login locked after repeated failures');
[$s, $loc] = http('POST', '/forgot-password', ['email' => 'nobody@example.test']);
[$s2, $loc2] = http('POST', '/forgot-password', ['email' => $customer['email']]);
T::ok($s === $s2 && $loc === $loc2, 'forgot-password response identical for unknown and known emails');
[$s] = http('POST', '/register', ['name' => 'Dup', 'email' => strtoupper($customer['email']), 'phone' => '03001234567', 'password' => 'Password123', 'password_confirmation' => 'Password123']);
T::eq(1, (int) $db->value('SELECT COUNT(*) FROM users WHERE email = ?', [$customer['email']]), 'duplicate email cannot register (case-insensitive)');

T::$current = 'HTTP permissions';
$matrix = [
    ['GET', '/admin/orders', $editor, 403], ['GET', '/admin/content', $editor, 200], ['GET', '/admin/products', $editor, 200],
    ['GET', '/admin/products/new', $editor, 403], ['GET', '/admin/orders', $staff, 200], ['GET', '/admin/reports', $staff, 403],
    ['GET', '/admin/orders/export', $staff, 403], ['GET', '/admin/customers/export', $staff, 403], ['GET', '/admin/settings', $manager, 403],
    ['GET', '/admin/settings/payments', $manager, 403], ['GET', '/admin/staff', $manager, 403], ['GET', '/admin/reports', $manager, 200],
    ['GET', '/admin/staff', $owner, 200], ['GET', '/admin/settings/payments', $owner, 200], ['GET', '/admin/audit', $staff, 403],
    ['POST', '/admin/products', $staff, 403], ['POST', '/admin/inventory/adjust', $staff, 403], ['POST', '/admin/staff', $manager, 403],
    ['POST', '/admin/settings/payments_cod', $manager, 403], ['POST', '/admin/content/settings/announcement', $staff, 403],
];
foreach ($matrix as [$m, $path, $u, $expected]) {
    [$s] = http($m, $path, [], $u);
    T::eq($expected, $s, "{$m} {$path} as {$u['role']}");
}

// An order to test fulfilment permissions & ownership.
Fx::newGuest();
App\Services\Cart::add($p['variant_id'], 1);
$order = Fx::checkout($rate, 'cod', ['email' => $customer['email']])['order'];
$db->exec('UPDATE orders SET user_id = ? WHERE id = ?', [$customer['id'], $order['id']]);
[$s] = http('POST', '/admin/orders/' . $order['id'] . '/refund', ['amount' => '100', 'reason' => 'x', 'method' => 'manual', 'idempotency_key' => str_repeat('a', 64)], $staff);
T::eq(403, $s, 'order staff cannot refund');
[$s] = http('POST', '/admin/orders/' . $order['id'] . '/cancel', ['note' => 'test'], $staff);
T::eq(403, $s, 'order staff cannot cancel');
[$s] = http('POST', '/admin/orders/' . $order['id'] . '/status', ['to' => 'cancelled'], $staff);
T::eq(403, $s, 'order staff cannot cancel via status route');
[$s] = http('POST', '/admin/orders/' . $order['id'] . '/status', ['to' => 'processing'], $staff);
T::eq(303, $s, 'order staff can fulfil');
T::eq('processing', App\Services\Orders::find((int) $order['id'])['status'], 'status updated');

T::$current = 'HTTP data isolation';
[$s] = http('GET', '/account/orders/' . $order['order_number'], [], $customer);
T::eq(200, $s, 'owner sees own order');
[$s] = http('GET', '/account/orders/' . $order['order_number'], [], $other);
T::eq(404, $s, 'another customer cannot see the order');
[$s] = http('GET', '/checkout/complete/' . $order['order_number'], [], $other);
T::eq(404, $s, 'confirmation page protected');
$addr = $db->insert("INSERT INTO addresses (user_id, full_name, phone, line1, city, province) VALUES (?, 'A', '0300', 'L1', 'Lahore', 'Punjab')", [$customer['id']]);
http('POST', '/account/addresses/' . $addr . '/delete', [], $other);
T::eq(1, (int) $db->value('SELECT COUNT(*) FROM addresses WHERE id = ?', [$addr]), "cannot delete another customer's address");

T::$current = 'HTTP sandbox & newsletter routes';
Fx::newGuest();
App\Services\Cart::add($p['variant_id'], 1);
$sbx = Fx::checkout($rate, 'sandbox');
$ref = (string) $db->value('SELECT provider_reference FROM payments WHERE order_id = ?', [$sbx['order']['id']]);
[$s, , $body] = http('GET', '/payments/sandbox/' . $ref);
T::eq(200, $s, 'sandbox hosted page route resolves');
T::ok(str_contains($body, 'Simulate successful payment'), 'sandbox page offers payment');
[$s] = http('GET', '/payments/sandbox/sbx_abc');
T::eq(404, $s, 'malformed sandbox reference is 404');
App\Core\Session::put('placed_orders', [(int) $sbx['order']['id']]);
[$s, $loc] = http('POST', '/payments/sandbox/' . $ref, ['outcome' => 'pay']);
T::ok($s === 303 && str_contains((string) $loc, '/checkout/complete/'), 'sandbox payment returns to confirmation');
T::eq('confirmed', App\Services\Orders::find((int) $sbx['order']['id'])['status'], 'sandbox payment confirmed via signed webhook');
$tok = bin2hex(random_bytes(32));
$db->insert("INSERT INTO newsletter_subscribers (email, unsubscribe_token) VALUES ('n@example.test', ?)", [$tok]);
[$s] = http('GET', '/newsletter/unsubscribe/' . $tok);
T::eq(200, $s, 'unsubscribe route resolves');
T::eq('unsubscribed', (string) $db->value("SELECT status FROM newsletter_subscribers WHERE email = 'n@example.test'"), 'unsubscribed');

T::$current = 'HTTP webhook & cron';
[$s] = http('POST', '/webhooks/sandbox', [], null, false);
T::eq(400, $s, 'webhook without signature rejected (no CSRF needed)');
[$s] = http('POST', '/webhooks/safepay', [], null, false);
T::eq(404, $s, 'unimplemented provider webhook is 404');
[$s] = http('POST', '/cron/run', [], null, false);
T::eq(404, $s, 'cron requires secret');

T::$current = 'HTTP security headers';
$res = new App\Core\Response('ok');
App\Core\Security::headers($res, new Request('GET', '/', [], [], [], [], []));
T::ok(str_contains($res->headers['Content-Security-Policy'] ?? '', "frame-ancestors 'none'"), 'CSP set');
T::eq('nosniff', $res->headers['X-Content-Type-Options'] ?? null, 'nosniff set');
$res2 = new App\Core\Response('ok');
App\Core\Security::headers($res2, new Request('GET', '/admin/orders', [], [], [], [], []));
T::eq('no-store, private', $res2->headers['Cache-Control'] ?? null, 'admin pages not cached');

T::$current = 'HTTP admin editing';
[$s] = http('POST', '/admin/content/settings/navigation', ['header' => [['label' => 'Evil', 'href' => 'javascript:alert(1)']]], $owner);
T::ok(!str_contains(json_encode(App\Services\Settings::get('navigation')), 'javascript:'), 'javascript: menu links rejected');
http('POST', '/admin/content/settings/navigation', ['header' => [['label' => 'Shop', 'href' => '/shop'], ['label' => 'Lookbook', 'href' => 'https://example.com/look']], 'footer_pages_title' => 'Info'], $owner);
T::eq('Lookbook', App\Services\Settings::get('navigation.header')[1]['label'] ?? null, 'header menu saved');
[, , $home] = http('GET', '/');
T::ok(str_contains($home, '>Lookbook</a>'), 'menu change live on storefront');
http('POST', '/admin/content/settings/sections', ['new_count' => '4', 'bestsellers_count' => '4', 'new_title' => 'Fresh drops', 'show_new' => '1'], $owner);
[, , $home] = http('GET', '/');
T::ok(str_contains($home, 'Fresh drops') && !str_contains($home, 'id="nl-title"'), 'section heading edited and unchecked sections hidden');
[$s] = http('POST', '/admin/customers/' . $other['id'], ['name' => 'Renamed', 'email' => $other['email'], 'disabled' => '1'], $staff);
T::eq(403, $s, 'order staff cannot edit customers');
http('POST', '/admin/customers/' . $other['id'], ['name' => 'Renamed', 'email' => $other['email'], 'disabled' => '1'], $manager);
$row = $db->one('SELECT name, disabled, session_version FROM users WHERE id = ?', [$other['id']]);
T::ok($row['name'] === 'Renamed' && (int) $row['disabled'] === 1 && (int) $row['session_version'] > (int) $other['session_version'], 'manager edits/disables customer and sessions are revoked');
[$s] = http('POST', '/admin/customers/' . $owner['id'], ['name' => 'X', 'email' => $owner['email']], $manager);
T::eq(404, $s, 'staff accounts cannot be edited from Customers');
http('POST', '/admin/orders/' . $order['id'] . '/address', ['full_name' => 'New Name', 'phone' => '03009998877', 'email' => 'new@example.test', 'line1' => 'New street 5', 'city' => 'Lahore', 'province' => 'Punjab'], $staff);
T::eq('New Name', json_decode((string) $db->value('SELECT shipping_address FROM orders WHERE id = ?', [$order['id']]), true)['full_name'] ?? null, 'address corrected before shipping');
T::eq(1, (int) $db->value("SELECT COUNT(*) FROM admin_audit_logs WHERE action = 'order.address'"), 'address change audited');
$db->exec("UPDATE orders SET status = 'shipped' WHERE id = ?", [$order['id']]);
http('POST', '/admin/orders/' . $order['id'] . '/address', ['full_name' => 'Too Late', 'phone' => '03009998877', 'email' => 'new@example.test', 'line1' => 'New street 5', 'city' => 'Lahore', 'province' => 'Punjab'], $staff);
T::ok(!str_contains((string) $db->value('SELECT shipping_address FROM orders WHERE id = ?', [$order['id']]), 'Too Late'), 'address locked after shipping');
$cp = Fx::coupon(['code' => 'DELME']);
http('POST', '/admin/coupons/' . $cp['id'] . '/delete', [], $manager);
T::eq(0, (int) $db->value('SELECT COUNT(*) FROM coupons WHERE id = ?', [$cp['id']]), 'unused coupon deleted');

T::$current = 'HTTP payment method editing';
http('POST', '/admin/settings/payments_methods', ['methods' => ['cod' => ['enabled' => '1', 'label' => 'Pay on delivery', 'sort' => '2'], 'sandbox' => ['enabled' => '1', 'sort' => '1'], 'safepay' => ['enabled' => '1']]], $owner);
App\Payments\Registry::reset();
T::eq('Pay on delivery', App\Payments\Registry::get('cod')->label(), 'method renamed for checkout');
T::eq(['sandbox', 'cod'], array_keys(App\Payments\Registry::available(100000)), 'checkout order follows admin sort');
T::ok(!App\Payments\Registry::get('safepay')->isEnabled(), 'unimplemented gateway cannot be switched on');
[$s] = http('POST', '/admin/settings/payments_methods', ['methods' => ['cod' => ['enabled' => '1']]], $manager);
T::eq(403, $s, 'managers cannot change payment methods');
http('POST', '/admin/settings/payments_credentials', ['provider' => 'jazzcash', 'cred' => ['JAZZCASH_MERCHANT_ID' => 'MC12345']], $owner);
T::eq('MC12345', App\Payments\Credentials::get('JAZZCASH_MERCHANT_ID'), 'gateway credential saved');
T::ok(!str_contains((string) $db->value("SELECT value FROM site_settings WHERE `key` = 'payment_credentials'"), 'MC12345'), 'credential encrypted at rest');
T::ok(!str_contains(json_encode($db->all("SELECT after_data FROM admin_audit_logs WHERE action = 'payments.credentials'")), 'MC12345'), 'credential value never written to audit log');
[, , $page] = http('GET', '/admin/settings/payments', [], $owner);
T::ok(!str_contains($page, 'MC12345') && str_contains($page, '✓ saved'), 'saved credential never echoed back to the browser');
http('POST', '/admin/settings/payments_credentials', ['provider' => 'jazzcash', 'clear' => ['JAZZCASH_MERCHANT_ID']], $owner);
T::eq(null, App\Payments\Credentials::get('JAZZCASH_MERCHANT_ID'), 'credential can be removed');

T::$current = 'HTTP currency';
[$s] = http('POST', '/admin/settings/currency_display', ['display' => ['USD' => ['enabled' => '1', 'rate' => '0.0036']], 'show_switcher' => '1'], $manager);
T::eq(403, $s, 'managers cannot change currencies');
http('POST', '/admin/settings/currency_display', ['display' => ['USD' => ['enabled' => '1', 'rate' => '0.0036'], 'GBP' => ['enabled' => '1', 'rate' => 'abc']], 'show_switcher' => '1'], $owner);
T::eq([], App\Services\Currency::displayRates(), 'invalid rate rejects the whole save');
http('POST', '/admin/settings/currency_display', ['display' => ['USD' => ['enabled' => '1', 'rate' => '0.0036']], 'show_switcher' => '1'], $owner);
T::eq(['USD' => '0.0036'], App\Services\Currency::displayRates(), 'display currency saved');
[, , $home] = http('GET', '/');
T::ok(str_contains($home, 'name="code"') && str_contains($home, 'USD'), 'currency switcher shown on store');
[$s] = http('POST', '/admin/settings/currency_base', ['base' => 'USD', 'rate' => '0.0036'], $owner);
T::eq('PKR', App\Services\Currency::base(), 'base change requires confirmation');

T::$current = 'HTTP default display currency';
http('POST', '/admin/settings/currency_display', ['display' => ['USD' => ['enabled' => '1', 'rate' => '0.0036120642']], 'default_display' => 'USD', 'show_switcher' => '1'], $owner);
unset($_COOKIE[App\Services\Currency::COOKIE]);
App\Services\Currency::setCurrent(null);
T::eq('USD', App\Services\Currency::current(), 'new visitors see USD by default');
$_COOKIE[App\Services\Currency::COOKIE] = 'PKR';
App\Services\Currency::setCurrent(null);
T::eq('PKR', App\Services\Currency::current(), 'visitor choice overrides default');
unset($_COOKIE[App\Services\Currency::COOKIE]);
App\Services\Currency::setCurrent(null);
http('POST', '/admin/settings/currency_display', ['display' => [], 'default_display' => 'USD', 'show_switcher' => '1'], $owner);
App\Services\Currency::setCurrent(null);
T::eq('', (string) App\Services\Settings::get('currency.default_display'), 'default must be an enabled display currency');

T::$current = 'HTTP store currency change (preview + correct direction)';
$probe = Fx::product(499000, null, 1);
[$s, , $page] = http('POST', '/admin/settings/currency_base', ['base' => 'USD', 'rate' => '276.85'], $owner);
T::ok($s === 200 && str_contains($page, 'Check before converting') && str_contains($page, '$18.02'), 'preview shows Rs 4,990 → $18.02 before converting');
T::eq('PKR', App\Services\Currency::base(), 'preview does not change anything');
http('POST', '/admin/settings/currency_base', ['base' => 'USD', 'rate' => '276.85', 'confirm' => '1'], $owner);
T::eq('USD', App\Services\Currency::base(), 'confirmed change applied');
T::eq(1802, (int) $db->value('SELECT price_minor FROM products WHERE id = ?', [$probe['product_id']]), 'rate entered as "1 USD = 276.85 PKR" converts correctly');

T::$current = 'HTTP live rates';
$db->exec("UPDATE site_settings SET value = JSON_SET(value, '$.base', '') WHERE `key` = 'currency'");
App\Services\Settings::put('currency', ['base' => 'PKR', 'display' => [], 'default_display' => '', 'show_switcher' => true, 'auto_rates' => true], null);
http('POST', '/admin/settings/currency_refresh', [], $owner);
T::eq('0.003611', App\Services\ExchangeRates::rate('USD'), 'Refresh now downloads live rates');
http('POST', '/admin/settings/currency_display', ['auto_rates' => '1', 'display' => ['USD' => ['enabled' => '1', 'rate' => '']], 'default_display' => 'USD', 'show_switcher' => '1'], $owner);
App\Services\Currency::setCurrent(null);
T::eq(['USD' => '0.003611'], App\Services\Currency::displayRates(), 'currency enabled without typing a rate');
[, , $footer] = http('GET', '/');
T::ok(str_contains($footer, 'Rates by Exchange Rate API'), 'rate source attributed in footer');

T::$current = 'HTTP crypto payments';
App\Services\Settings::put('currency', ['base' => 'USDT', 'display' => [], 'default_display' => '', 'show_switcher' => false, 'auto_rates' => true], null);
App\Payments\Registry::$legacy = false;
App\Payments\Registry::reset();
[$s] = http('POST', '/admin/settings/payments_methods', ['methods' => ['usdt_trc20' => ['enabled' => '1', 'address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE']]], $manager);
T::eq(403, $s, 'managers cannot set wallet addresses');
http('POST', '/admin/settings/payments_methods', ['methods' => ['usdt_trc20' => ['enabled' => '1', 'address' => '0x52908400098527886E0F7030069857D2E4169EE7']]], $owner);
App\Services\Settings::flush();
T::eq('', App\Payments\Registry::get('usdt_trc20')->address(), 'address for the wrong network is rejected');
http('POST', '/admin/settings/payments_methods', ['methods' => [
    'usdt_trc20' => ['enabled' => '1', 'address' => ' TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE ', 'sort' => '1'],
    'usdt_erc20' => ['enabled' => '1', 'address' => '0x52908400098527886E0F7030069857D2E4169EE7', 'sort' => '2'],
    'btc' => ['address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'],
]], $owner);
App\Services\Settings::flush();
App\Payments\Registry::reset();
T::eq('TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', App\Payments\Registry::get('usdt_trc20')->address(), 'wallet address saved (whitespace trimmed)');
T::eq(['usdt_trc20', 'usdt_erc20'], array_keys(App\Payments\Registry::available(1000)), 'only switched-on methods with addresses are offered');
T::ok((bool) $db->value("SELECT COUNT(*) FROM admin_audit_logs WHERE action = 'settings.payments_methods'"), 'payment settings change audited');
[$s, , $page] = http('GET', '/admin/settings/payments', [], $owner);
T::eq(200, $s, 'payments settings page loads');
T::ok(str_contains($page, 'USDT (TRC20)') && str_contains($page, 'Bitcoin (BTC)') && str_contains($page, 'Ether (ETH)'), 'crypto methods listed');
T::ok(!str_contains($page, 'Cash on Delivery') && !str_contains($page, 'Bank transfer') && !str_contains($page, 'JazzCash'), 'other methods removed from the admin page');
[, , $home] = http('GET', '/');
T::ok(str_contains($home, 'USDT TRC20') && !str_contains($home, 'Cash on Delivery'), 'footer shows only crypto badges');

$cp = Fx::product(2000, null, 5);
$db->exec("UPDATE products SET currency = 'USDT' WHERE id = ?", [$cp['product_id']]);
Fx::newGuest();
App\Services\Cart::add($cp['variant_id'], 1);
$zone = App\Services\Shipping::zoneFor('Lahore', 'Punjab');
$crate = $db->insert("INSERT INTO shipping_rates (zone_id, name, price_minor, min_days, max_days, cod_available) VALUES (?, 'Crypto test', 500, 2, 3, 0)", [$zone['id']]);
$co = Fx::checkout($crate, 'usdt_erc20')['order'];
App\Core\Session::put('placed_orders', [(int) $co['id']]);
[$s, , $page] = http('GET', '/checkout/complete/' . $co['order_number']);
T::eq(200, $s, 'order complete page loads');
T::ok(str_contains($page, '0x52908400098527886E0F7030069857D2E4169EE7') && str_contains($page, 'Ethereum (ERC20)') && str_contains($page, 'data-copy'), 'customer sees network, wallet and copy buttons');
$want = rtrim(rtrim((string) $db->value('SELECT pay_amount FROM payments WHERE order_id = ?', [$co['id']]), '0'), '.');
T::eq(App\Core\Money::decimal((int) $co['total_minor'], 'USDT'), number_format((float) $want, 2, '.', ''), 'USDT amount equals the order total 1:1');
T::ok(str_contains($page, $want . ' USDT'), 'customer sees the exact USDT amount');
[$s] = http('POST', '/orders/' . $co['order_number'] . '/bank-reference', ['reference' => '0x' . str_repeat('c4', 32)]);
T::eq(303, $s, 'TXID form submits');
T::eq('pending_verification', App\Services\Orders::find((int) $co['id'])['payment_status'], 'TXID recorded for verification');
[, , $admin] = http('GET', '/admin/orders/' . $co['id'], [], $owner);
T::ok(str_contains($admin, 'https://etherscan.io/tx/0x' . str_repeat('c4', 32)) && str_contains($admin, 'Approve payment'), 'staff see explorer link and approve button');
App\Payments\Registry::$legacy = true;
App\Payments\Registry::reset();

T::$current = 'HTTP US checkout';
App\Payments\Registry::$legacy = false;
App\Payments\Registry::reset();
Fx::newGuest();
App\Core\Session::forget('uid');
$up = Fx::product(3000, null, 5);
$db->exec("UPDATE products SET currency = 'USDT' WHERE id = ?", [$up['product_id']]);
App\Services\Cart::add($up['variant_id'], 1);
$usZone = $db->insert("INSERT INTO shipping_zones (name, cities, provinces, sort_order) VALUES ('Texas test', '', 'tx', 0)");
$usRate = $db->insert("INSERT INTO shipping_rates (zone_id, name, price_minor, min_days, max_days, cod_available) VALUES (?, 'Standard shipping', 699, 5, 7, 0)", [$usZone]);
[$s, , $page] = http('GET', '/checkout');
T::eq(200, $s, 'checkout page loads');
T::ok(str_contains($page, '>State</label>') && str_contains($page, '>ZIP code</label>') && str_contains($page, 'United States') && str_contains($page, '<option value="TX">Texas</option>'), 'checkout asks for State, ZIP and shows United States');
T::ok(!str_contains($page, 'Province') && !str_contains($page, '03XX') && !str_contains($page, 'Punjab'), 'no Pakistan-specific fields left');
T::ok(str_contains($page, 'Select your state to see shipping options'), 'shipping options wait for the state');
[, , $page] = http('POST', '/checkout', ['intent' => 'update', 'province' => 'TX', 'city' => 'Austin']);
T::ok(str_contains($page, 'Standard shipping') && str_contains($page, '5–7 business days'), 'state selects the shipping zone and rates');
$usPost = ['email' => 'jane@example.test', 'phone' => '03001234567', 'full_name' => 'Jane Doe', 'line1' => '100 Congress Ave', 'city' => 'Austin', 'province' => 'Punjab', 'postal_code' => '7870',
    'shipping_rate_id' => (string) $usRate, 'payment_method' => 'usdt_trc20', 'idempotency_key' => bin2hex(random_bytes(32)), 'fingerprint' => str_repeat('a', 64), 'intent' => 'place'];
[$s, , $page] = http('POST', '/checkout', $usPost);
T::eq(422, $s, 'non-US details rejected');
T::ok(str_contains($page, 'id="province-err"') && str_contains($page, 'id="postal_code-err"') && str_contains($page, 'id="phone-err"'), 'state, ZIP and phone errors shown');
$rateRow = $db->one('SELECT * FROM shipping_rates WHERE id = ?', [$usRate]);
$summary = App\Services\Cart::summary(699, App\Services\Shipping::freeOver($rateRow), 'jane@example.test');
[$s, $loc, $body] = http('POST', '/checkout', ['phone' => '(512) 555-0100', 'province' => 'TX', 'postal_code' => '78701', 'fingerprint' => App\Services\Pricing::fingerprint($summary['totals'], $summary['pricing_lines'])] + $usPost);
T::eq(303, $s, 'US order placed' . ($s === 303 ? '' : ' ' . implode('; ', array_map('strip_tags', preg_match_all('/<p class="field-error"[^>]*>(.*?)<\/p>/', (string) $body, $mm) ? $mm[1] : [])) . ' ' . (preg_match('/role="alert"[^>]*>(.*?)<\/div>/s', (string) $body, $am) ? strip_tags($am[1]) : '')));
$usOrder = $db->one('SELECT * FROM orders ORDER BY id DESC LIMIT 1');
$ship = json_decode((string) $usOrder['shipping_address'], true);
T::eq(['TX', '78701', 'US'], [$ship['province'], $ship['postal_code'], $ship['country']], 'order stores state code, ZIP and country US');
App\Payments\Registry::$legacy = true;
App\Payments\Registry::reset();


T::$current = 'HTTP marketplace';
$sellerUser = Fx::user('customer', 'shop.owner@example.test');
$otherUser = Fx::user('customer', 'other.shop@example.test');
[$s, $loc] = http('GET', '/seller', [], $sellerUser);
T::ok($loc !== null && str_ends_with((string) parse_url($loc, PHP_URL_PATH), '/sell'), 'non-sellers are sent to the application page');
[$s, , $page] = http('GET', '/sell');
T::ok($s === 200 && str_contains($page, 'Create your seller account') && str_contains($page, 'action="' . url('/seller/register') . '"'), 'guests see the one-step seller sign-up');
http('POST', '/sell', ['shop_name' => 'Harbor Denim', 'description' => 'Selvedge denim cut and sewn in Los Angeles.', 'agree' => '1'], $sellerUser);
$hsid = (int) $db->value('SELECT id FROM sellers WHERE user_id = ?', [$sellerUser['id']]);
T::eq('pending', (string) $db->value('SELECT status FROM sellers WHERE id = ?', [$hsid]), 'application stored as pending');
[$s, $loc] = http('GET', '/seller/products', [], $sellerUser);
T::ok($s === 303 && str_ends_with((string) parse_url((string) $loc, PHP_URL_PATH), '/seller'), 'pending sellers cannot use the product pages');
[$s] = http('POST', '/admin/sellers/' . $hsid . '/decide', ['action' => 'approve'], $staff);
T::eq(403, $s, 'order staff cannot approve sellers');
App\Mail\Mailer::$sent = [];
http('POST', '/admin/sellers/' . $hsid . '/decide', ['action' => 'approve'], $manager);
T::eq('approved', (string) $db->value('SELECT status FROM sellers WHERE id = ?', [$hsid]), 'manager approves the seller');
T::ok((bool) array_filter(App\Mail\Mailer::$sent, static fn ($m) => $m['to'] === $sellerUser['email'] && str_contains($m['subject'], 'approved')), 'approval email is sent to the seller straight away');

$cat = (int) $db->insert("INSERT INTO categories (name, slug, status) VALUES ('Denim test', 'denim-test-mp', 'active')");
[$s, $loc] = http('POST', '/seller/products', ['title' => 'Raw Selvedge Jean', 'description' => '14oz raw selvedge denim, button fly, slim taper.', 'category_id' => (string) $cat, 'price' => '120.00'], $sellerUser);
$spid = (int) $db->value('SELECT id FROM products WHERE seller_id = ? ORDER BY id DESC LIMIT 1', [$hsid]);
T::ok($spid > 0 && $db->value('SELECT status FROM products WHERE id = ?', [$spid]) === 'draft', 'seller product created as a draft');
T::ok(str_contains((string) $db->value('SELECT description FROM products WHERE id = ?', [$spid]), '<p>14oz'), 'description stored as safe paragraphs');
http('POST', '/seller/products/' . $spid . '/variants', ['variants' => ['new0' => ['size' => '32', 'color' => 'Indigo', 'stock' => '5']]], $sellerUser);
T::eq(5, (int) $db->value('SELECT SUM(stock) FROM product_variants WHERE product_id = ?', [$spid]), 'seller adds sizes with stock');
http('POST', '/seller/products/' . $spid . '/submit', [], $sellerUser);
T::eq('none', (string) $db->value('SELECT review_status FROM products WHERE id = ?', [$spid]), 'cannot submit without a photo');
$db->insert("INSERT INTO product_images (product_id, path, alt, width, height, position) VALUES (?, 'products/test', 'x', 800, 1000, 0)", [$spid]);
http('POST', '/seller/products/' . $spid . '/submit', [], $sellerUser);
T::eq('pending', (string) $db->value('SELECT review_status FROM products WHERE id = ?', [$spid]), 'submitted for review');
$pslug = (string) $db->value('SELECT slug FROM products WHERE id = ?', [$spid]);
[$s] = http('GET', '/product/' . $pslug);
T::eq(404, $s, 'not visible before approval');
[$s, , $page] = http('GET', '/admin/sellers/reviews', [], $manager);
T::ok(str_contains($page, 'Raw Selvedge Jean'), 'product appears in the review queue');
http('POST', '/admin/sellers/products/' . $spid . '/review', ['decision' => 'approve'], $manager);
[$s, , $page] = http('GET', '/product/' . $pslug);
T::ok($s === 200 && str_contains($page, 'Sold by') && str_contains($page, 'Harbor Denim'), 'approved product is live with "Sold by"');
[$s, , $page] = http('GET', '/sellers/' . $db->value('SELECT slug FROM sellers WHERE id = ?', [$hsid]));
T::ok($s === 200 && str_contains($page, 'Raw Selvedge Jean'), 'public seller shop lists the product');
http('POST', '/seller/products/' . $spid, ['title' => 'Raw Selvedge Jean', 'description' => '14oz raw selvedge denim, button fly, slim taper.', 'category_id' => (string) $cat, 'price' => '99.00'], $sellerUser);
T::eq(['draft', 'pending'], array_values($db->one('SELECT status, review_status FROM products WHERE id = ?', [$spid])), 'changing the price of a live product sends it back to review');
http('POST', '/admin/sellers/products/' . $spid . '/review', ['decision' => 'approve'], $manager);

// Isolation: another seller cannot touch this product.
http('POST', '/sell', ['shop_name' => 'Other Shop', 'description' => 'Another shop for isolation testing.', 'agree' => '1'], $otherUser);
$osid = (int) $db->value('SELECT id FROM sellers WHERE user_id = ?', [$otherUser['id']]);
http('POST', '/admin/sellers/' . $osid . '/decide', ['action' => 'approve'], $manager);
[$s] = http('GET', '/seller/products/' . $spid, [], $otherUser);
T::eq(404, $s, "another seller cannot open someone else's product");
[$s] = http('POST', '/seller/products/' . $spid, ['title' => 'Hacked', 'description' => str_repeat('x', 30), 'category_id' => (string) $cat, 'price' => '1.00'], $otherUser);
T::eq(404, $s, "another seller cannot edit someone else's product");
T::eq('Raw Selvedge Jean', (string) $db->value('SELECT title FROM products WHERE id = ?', [$spid]), 'product unchanged');
$foreignVariant = (int) $db->value('SELECT id FROM product_variants WHERE product_id = ?', [$spid]);
$ownPid = (int) $db->insert("INSERT INTO products (seller_id, title, slug, sku, description, price_minor, status, review_status) VALUES (?, 'Mine', 'mine-iso', 'S-ISO-1', '', 1000, 'draft', 'none')", [$osid]);
[$s] = http('POST', '/seller/products/' . $ownPid . '/variants', ['variants' => [$foreignVariant => ['id' => (string) $foreignVariant, 'size' => 'XL', 'color' => 'Red', 'stock' => '0']]], $otherUser);
T::eq(404, $s, "cannot edit another seller's variant through own product");
T::eq(5, (int) $db->value('SELECT stock FROM product_variants WHERE id = ?', [$foreignVariant]), 'foreign stock unchanged');

// Payout address change needs the password.
http('POST', '/seller/settings', ['shop_name' => 'Harbor Denim', 'description' => 'Selvedge denim cut and sewn in Los Angeles.', 'payout_address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', 'password' => 'wrong'], $sellerUser);
T::eq(null, $db->value('SELECT payout_address FROM sellers WHERE id = ?', [$hsid]), 'payout address not changed without the password');
http('POST', '/seller/settings', ['shop_name' => 'Harbor Denim', 'description' => 'Selvedge denim cut and sewn in Los Angeles.', 'payout_address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', 'password' => 'Password123'], $sellerUser);
T::eq('TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', (string) $db->value('SELECT payout_address FROM sellers WHERE id = ?', [$hsid]), 'payout address saved with the password');

[$s] = http('GET', '/admin/withdrawals', [], $manager);
T::eq(403, $s, 'only owners process payouts');
[$s, , $page] = http('GET', '/admin/withdrawals', [], $owner);
T::eq(200, $s, 'owner opens withdrawals');
[$s, , $page] = http('GET', '/seller/wallet', [], $sellerUser);
T::ok($s === 200 && str_contains($page, 'Available to withdraw') && !str_contains(strtolower($page), 'recharge'), 'seller wallet shows balances and no deposit option');
[$s, , $page] = http('GET', '/admin/sellers', [], $manager);
T::ok($s === 200 && str_contains($page, 'Harbor Denim'), 'sellers list');


T::$current = 'HTTP seller sign-in flow';
App\Core\Session::forget('intended');
$path = static fn (?string $loc): string => (string) parse_url((string) $loc, PHP_URL_PATH);
[$s, $loc] = http('POST', '/seller/login', ['email' => $sellerUser['email'], 'password' => 'Password123']);
T::ok($s === 303 && str_ends_with($path($loc), '/seller'), 'seller login opens the Seller panel');
App\Core\Session::forget('uid');
[$s, $loc] = http('POST', '/login', ['email' => $sellerUser['email'], 'password' => 'Password123']);
T::ok($s === 303 && str_ends_with($path($loc), '/account'), 'customer login of a seller opens My account');
App\Core\Session::forget('uid');
http('POST', '/seller/login', ['email' => $customer['email'], 'password' => 'Password123']);
T::ok(Auth::user() === null, 'seller login refuses accounts without a shop');
App\Core\Session::forget('uid');
http('POST', '/admin/login', ['email' => $customer['email'], 'password' => 'Password123']);
T::ok(Auth::user() === null, 'admin login refuses customers');
App\Core\Session::forget('uid');
http('POST', '/login', ['email' => $owner['email'], 'password' => 'Password123']);
T::ok(Auth::user() === null, 'staff cannot sign in on the customer login');
App\Core\Session::forget('uid');
[$s, $loc] = http('POST', '/admin/login', ['email' => $owner['email'], 'password' => 'Password123']);
T::ok($s === 303 && str_ends_with($path($loc), '/admin') && (int) Auth::id() === (int) $owner['id'], 'admin login signs staff into the dashboard');
[$s, $loc] = http('POST', '/logout', ['portal' => 'admin'], $owner);
T::ok(str_ends_with($path($loc), '/admin/login'), 'admin sign-out returns to the admin login');
App\Core\Session::forget('uid');
[$s, $loc] = http('GET', '/admin/orders');
T::ok($s === 303 && str_ends_with($path($loc), '/admin/login'), 'signed-out admin pages go to the admin login');
[$s, $loc] = http('GET', '/seller/wallet');
T::ok($s === 303 && str_ends_with($path($loc), '/seller/login'), 'signed-out seller pages go to the seller login');
foreach (['/admin/login', '/seller/login'] as $lp) {
    [$s, , $page] = http('GET', $lp);
    T::ok($s === 200 && str_contains($page, 'action="' . url($lp) . '"'), "{$lp} page renders its own form");
}
App\Core\Session::forget('uid');
http('GET', '/sell');
T::eq('/sell', App\Core\Session::get('intended'), 'guests on the Sell page return there after signing up or in');
App\Core\Session::forget('intended');


T::$current = 'HTTP seller sign-up';
App\Core\Session::forget('uid');
$base = ['name' => 'Maya Lee', 'phone' => '(646) 555-0199', 'password' => 'Seller12345', 'password_confirmation' => 'Seller12345',
    'description' => 'Small-batch linen shirts sewn in Brooklyn.', 'agree' => '1'];
[$s] = http('POST', '/seller/register', $base + ['email' => 'dup.shop@example.test', 'shop_name' => 'Harbor Denim']);
T::eq(null, $db->value("SELECT id FROM users WHERE email = 'dup.shop@example.test'"), 'taken shop name leaves no half-made account');
[$s] = http('POST', '/seller/register', $base + ['email' => $customer['email'], 'shop_name' => 'Brand New Shop']);
T::eq(null, $db->value("SELECT id FROM sellers WHERE shop_name = 'Brand New Shop'"), 'existing email cannot sign up again');
[$s, $loc] = http('POST', '/seller/register', $base + ['email' => 'maya@example.test', 'shop_name' => 'Maya Linen']);
$maya = $db->one("SELECT u.id, s.status, s.shop_name FROM users u JOIN sellers s ON s.user_id = u.id WHERE u.email = 'maya@example.test'");
T::ok($maya !== null && $maya['status'] === 'pending', 'one form creates the account and a pending shop');
T::ok($s === 303 && str_ends_with((string) parse_url((string) $loc, PHP_URL_PATH), '/seller') && (int) Auth::id() === (int) $maya['id'], 'new seller is signed in and taken to the Seller panel');
App\Core\Session::forget('uid');
[$s, $loc] = http('POST', '/seller/login', ['email' => 'maya@example.test', 'password' => 'Seller12345']);
T::ok($s === 303 && str_ends_with((string) parse_url((string) $loc, PHP_URL_PATH), '/seller'), 'the new seller can sign in at the seller login');
[$s, , $page] = http('GET', '/seller/register');
T::eq(200, $s, '/seller/register page opens');

T::$current = 'HTTP admin entry';
App\Core\Session::forget('uid');
[$s, $loc] = http('GET', '/admin');
T::ok($s === 303 && str_ends_with((string) parse_url((string) $loc, PHP_URL_PATH), '/admin/login'), 'typing /admin while signed out opens the admin login');
[$s, $loc] = http('GET', '/admin', [], $customer);
T::ok($s === 303 && str_ends_with((string) parse_url((string) $loc, PHP_URL_PATH), '/admin/login'), 'signed in as a customer, /admin opens the admin login (not a 403)');
[$s] = http('POST', '/admin/settings/checkout', [], $customer);
T::eq(403, $s, 'non-staff POSTs to admin are still forbidden');
[$s, $loc] = http('GET', '/ADMIN');
T::ok($s === 303 && str_ends_with((string) parse_url((string) $loc, PHP_URL_PATH), '/admin'), 'uppercase /ADMIN redirects to /admin');


T::$current = 'HTTP shop rename';
$hd = $db->one("SELECT id FROM sellers WHERE shop_name = 'Harbor Denim'");
$hdUser = $db->one('SELECT u.* FROM users u JOIN sellers s ON s.user_id = u.id WHERE s.id = ?', [$hd['id']]);
http('POST', '/seller/settings', ['shop_name' => 'Other Shop', 'description' => 'Selvedge denim cut and sewn in Los Angeles.', 'payout_address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE'], $hdUser);
T::eq('Harbor Denim', (string) $db->value('SELECT shop_name FROM sellers WHERE id = ?', [$hd['id']]), 'cannot take another seller name');
http('POST', '/seller/settings', ['shop_name' => 'Harbor Denim Co', 'description' => 'Selvedge denim cut and sewn in Los Angeles.', 'payout_address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE'], $hdUser);
T::eq(['Harbor Denim Co', 'harbor-denim-co'], array_values($db->one('SELECT shop_name, slug FROM sellers WHERE id = ?', [$hd['id']])), 'seller renames the shop (link follows)');
