<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Auth\Tokens;
use App\Core\Database;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\ValidationException;
use App\Payments\SandboxProvider;
use App\Services\Cart;
use App\Services\Coupons;
use App\Services\CsvImport;
use App\Services\Inventory;
use App\Services\Orders;
use App\Services\Payments;

$db = Database::get();
$stock = static fn (int $vid) => (int) $db->value('SELECT stock FROM product_variants WHERE id = ?', [$vid]);

// ───────────────────────────────── Checkout & stock
T::$current = 'Checkout';
reset_db();
$rate = Fx::shipping(25000);
$p = Fx::product(200000, null, 5);
Fx::newGuest();
Cart::add($p['variant_id'], 2);
$r = Fx::checkout($rate);
$o = $r['order'];
T::eq('confirmed', $o['status'], 'COD order is confirmed');
T::eq('unpaid', $o['payment_status'], 'COD order unpaid until collected');
T::eq(400000, (int) $o['subtotal_minor'], 'server-side subtotal');
T::eq(425000, (int) $o['total_minor'], 'total includes shipping below the free-delivery threshold');
T::eq(3, $stock($p['variant_id']), 'stock decremented by quantity');
T::eq(1, (int) $db->value("SELECT COUNT(*) FROM inventory_movements WHERE order_id = ? AND reason = 'sale' AND delta = -2", [$o['id']]), 'sale movement recorded');
T::eq(0, Cart::count(), 'cart cleared after order');
T::ok((bool) preg_match('/^UVX-\d{6}$/', (string) $o['order_number']), 'human order number');
$item = $db->one('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
T::eq(200000, (int) $item['unit_price_minor'], 'item price snapshot');
$db->exec('UPDATE products SET price_minor = 999900, title = ? WHERE id = ?', ['Renamed', $p['product_id']]);
$item2 = $db->one('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
T::eq(200000, (int) $item2['unit_price_minor'], 'snapshot unchanged after product edit');
T::ok($item2['product_title'] !== 'Renamed', 'title snapshot unchanged');
T::ok(count($db->all("SELECT id FROM email_outbox WHERE template = 'order_confirmation'")) === 1, 'confirmation email queued');

T::$current = 'Idempotency';
reset_db();
$rate = Fx::shipping();
$p = Fx::product(100000, null, 5);
Fx::newGuest();
Cart::add($p['variant_id'], 1);
$summary = Cart::summary(25000, null, 'buyer@example.test');
$key = bin2hex(random_bytes(32));
$input = ['email' => 'buyer@example.test', 'phone' => '03001234567', 'full_name' => 'B', 'line1' => 'House 1', 'line2' => null, 'city' => 'Lahore', 'province' => 'Punjab', 'postal_code' => null,
    'shipping_rate_id' => $rate, 'payment_method' => 'cod', 'note' => null, 'idempotency_key' => $key, 'fingerprint' => App\Services\Pricing::fingerprint($summary['totals'], $summary['pricing_lines']), 'billing_same' => true];
$a = App\Services\Checkout::place($input);
$b = App\Services\Checkout::place($input);
T::eq($a['order']['id'], $b['order']['id'], 'same idempotency key returns the same order');
T::eq(1, (int) $db->value('SELECT COUNT(*) FROM orders'), 'only one order created');
T::eq(4, $stock($p['variant_id']), 'stock decremented once');

T::$current = 'Stock races';
reset_db();
$rate = Fx::shipping();
$p = Fx::product(100000, null, 1);
Fx::newGuest();
Cart::add($p['variant_id'], 1);
$summary = Cart::summary(25000, null, 'x@example.test');
Inventory::apply($p['variant_id'], -1, 'adjustment', 'another buyer took it');
T::throws(ValidationException::class, fn () => App\Services\Checkout::place(['email' => 'x@example.test', 'phone' => '03001234567', 'full_name' => 'B', 'line1' => 'House 1', 'line2' => null, 'city' => 'Lahore', 'province' => 'Punjab', 'postal_code' => null, 'shipping_rate_id' => $rate, 'payment_method' => 'cod', 'note' => null, 'idempotency_key' => bin2hex(random_bytes(32)), 'fingerprint' => App\Services\Pricing::fingerprint($summary['totals'], $summary['pricing_lines']), 'billing_same' => true]), 'sold-out item blocks checkout', 'sold out');
T::eq(0, $stock($p['variant_id']), 'stock never negative');
T::eq(0, (int) $db->value('SELECT COUNT(*) FROM orders'), 'no order created');
T::throws(\mysqli_sql_exception::class, fn () => $db->exec('UPDATE product_variants SET stock = -1 WHERE id = ?', [$p['variant_id']]), 'database CHECK forbids negative stock');
T::throws(ValidationException::class, fn () => Inventory::apply($p['variant_id'], -1, 'adjustment'), 'inventory refuses to go negative');
T::throws(ValidationException::class, fn () => Cart::add($p['variant_id'], 1), 'cannot add sold-out variant');

T::$current = 'Price manipulation';
reset_db();
$rate = Fx::shipping();
$p = Fx::product(100000, null, 5);
Fx::newGuest();
Cart::add($p['variant_id'], 1);
$summary = Cart::summary(25000, null, 'x@example.test');
$fp = App\Services\Pricing::fingerprint($summary['totals'], $summary['pricing_lines']);
$db->exec('UPDATE products SET price_minor = 200000 WHERE id = ?', [$p['product_id']]);
T::throws(ValidationException::class, fn () => App\Services\Checkout::place(['email' => 'x@example.test', 'phone' => '03001234567', 'full_name' => 'B', 'line1' => 'House 1', 'line2' => null, 'city' => 'Lahore', 'province' => 'Punjab', 'postal_code' => null, 'shipping_rate_id' => $rate, 'payment_method' => 'cod', 'note' => null, 'idempotency_key' => bin2hex(random_bytes(32)), 'fingerprint' => $fp, 'billing_same' => true]), 'changed price detected at submit', 'changed');
T::throws(ValidationException::class, fn () => App\Services\Checkout::place(['email' => 'x@example.test', 'phone' => '03001234567', 'full_name' => 'B', 'line1' => 'House 1', 'line2' => null, 'city' => 'Lahore', 'province' => 'Punjab', 'postal_code' => null, 'shipping_rate_id' => $rate, 'payment_method' => 'stripe_fake', 'note' => null, 'idempotency_key' => bin2hex(random_bytes(32)), 'fingerprint' => $fp, 'billing_same' => true]), 'unknown payment method rejected');
T::throws(ValidationException::class, fn () => App\Services\Checkout::place(['email' => 'x@example.test', 'phone' => '03001234567', 'full_name' => 'B', 'line1' => 'House 1', 'line2' => null, 'city' => 'Lahore', 'province' => 'Punjab', 'postal_code' => null, 'shipping_rate_id' => $rate, 'payment_method' => 'safepay', 'note' => null, 'idempotency_key' => bin2hex(random_bytes(32)), 'fingerprint' => $fp, 'billing_same' => true]), 'unimplemented provider rejected');

// ───────────────────────────────── Coupons
T::$current = 'Coupons';
reset_db();
$rate = Fx::shipping();
$p = Fx::product(200000, null, 20);
$c = Fx::coupon(['code' => 'ONCE', 'value' => 10, 'usage_limit' => 1]);
Fx::newGuest();
Cart::add($p['variant_id'], 1);
Cart::setCoupon('ONCE');
$r1 = Fx::checkout($rate, 'cod', ['email' => 'a@example.test']);
T::eq(20000, (int) $r1['order']['discount_minor'], '10% coupon applied server-side');
T::eq(1, (int) $db->value('SELECT usage_count FROM coupons WHERE id = ?', [$c['id']]), 'usage counted');
Fx::newGuest();
Cart::add($p['variant_id'], 1);
Cart::setCoupon('ONCE');
$s = Cart::summary(25000, null, 'b@example.test');
T::ok($s['coupon'] === null && $s['coupon_error'] !== null, 'used-up coupon dropped from cart with message');
T::throws(ValidationException::class, fn () => Coupons::evaluate(Coupons::find('ONCE'), [['key' => 1, 'unit' => 100, 'qty' => 1, 'product_id' => 1, 'category_id' => null, 'collection_ids' => []]], null, 'c@example.test'), 'usage limit enforced', 'limit');

$per = Fx::coupon(['code' => 'PERCUST', 'value' => 5, 'per_customer_limit' => 1]);
Fx::newGuest();
Cart::add($p['variant_id'], 1);
Cart::setCoupon('PERCUST');
Fx::checkout($rate, 'cod', ['email' => 'repeat@example.test']);
T::throws(ValidationException::class, fn () => Coupons::evaluate(Coupons::find('PERCUST'), [['key' => 1, 'unit' => 100000, 'qty' => 1, 'product_id' => 1, 'category_id' => null, 'collection_ids' => []]], null, 'REPEAT@example.test'), 'per-customer limit by email (case-insensitive)', 'maximum');

$min = Fx::coupon(['code' => 'BIGSPEND', 'value' => 10, 'min_subtotal_minor' => 1000000]);
T::throws(ValidationException::class, fn () => Coupons::evaluate($min, [['key' => 1, 'unit' => 100000, 'qty' => 1, 'product_id' => 1, 'category_id' => null, 'collection_ids' => []]], null, null), 'minimum basket enforced', 'Spend at least');
$db->exec("UPDATE coupons SET ends_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE code = 'BIGSPEND'");
T::throws(ValidationException::class, fn () => Coupons::evaluate(Coupons::find('BIGSPEND'), [], null, null), 'expired coupon rejected', 'expired');
$scoped = Fx::coupon(['code' => 'ONLYONE', 'scope' => 'products']);
$db->exec("INSERT INTO coupon_targets (coupon_id, target_type, target_id) VALUES (?, 'product', 999999)", [$scoped['id']]);
T::throws(ValidationException::class, fn () => Coupons::evaluate($scoped, [['key' => 1, 'unit' => 100000, 'qty' => 1, 'product_id' => $p['product_id'], 'category_id' => null, 'collection_ids' => []]], null, null), 'scoped coupon requires eligible items', 'does not apply');

// cancellation releases usage
$db->exec('UPDATE coupons SET usage_limit = 2 WHERE id = ?', [$c['id']]);
Orders::transition((int) $r1['order']['id'], 'cancelled', 'test', null);
T::eq(0, (int) $db->value('SELECT usage_count FROM coupons WHERE id = ?', [$c['id']]), 'cancellation releases coupon use');
T::eq(1, (int) $db->value('SELECT COUNT(*) FROM coupon_redemptions WHERE order_id = ? AND voided_at IS NOT NULL', [$r1['order']['id']]), 'redemption voided');

// ───────────────────────────────── Order lifecycle
T::$current = 'Order transitions';
reset_db();
$rate = Fx::shipping();
$p = Fx::product(100000, null, 10);
$staff = Fx::user('manager');
Auth::actingAs($staff);
Cart::setGuestToken(null);
Fx::newGuest();
Cart::add($p['variant_id'], 3);
$o = Fx::checkout($rate)['order'];
Auth::actingAs($staff);
T::throws(ValidationException::class, fn () => Orders::transition((int) $o['id'], 'delivered', null, (int) $staff['id']), 'cannot jump to delivered');
T::throws(ValidationException::class, fn () => Orders::transition((int) $o['id'], 'shipped', null, (int) $staff['id']), 'shipping needs tracking');
Orders::transition((int) $o['id'], 'processing', null, (int) $staff['id']);
Orders::transition((int) $o['id'], 'packed', null, (int) $staff['id']);
Orders::ship((int) $o['id'], 'TCS', 'TRK123', null, null, (int) $staff['id']);
T::eq('shipped', Orders::find((int) $o['id'])['status'], 'shipped with tracking');
T::eq(1, (int) $db->value('SELECT COUNT(*) FROM shipments WHERE order_id = ?', [$o['id']]), 'shipment recorded');
T::throws(ValidationException::class, fn () => Orders::ship((int) $o['id'], 'TCS', 'TRK999', null, null, (int) $staff['id']), 'cannot ship twice');
T::throws(ValidationException::class, fn () => Orders::transition((int) $o['id'], 'cancelled', null, (int) $staff['id']), 'cannot cancel after shipping');
Orders::transition((int) $o['id'], 'delivered', null, (int) $staff['id']);
Orders::recordCodCollected((int) $o['id'], 'cash', (int) $staff['id']);
T::eq('paid', Orders::find((int) $o['id'])['payment_status'], 'COD collection marks paid');
T::throws(ValidationException::class, fn () => Orders::recordCodCollected((int) $o['id'], 'again', (int) $staff['id']), 'COD collection cannot be recorded twice');
T::ok((int) $db->value("SELECT COUNT(*) FROM admin_audit_logs WHERE action = 'payment.cod_collected'") === 1, 'COD collection audited');

T::$current = 'Cancellation restock';
Fx::newGuest();
Cart::add($p['variant_id'], 2);
$before = $stock($p['variant_id']);
$o2 = Fx::checkout($rate)['order'];
T::eq($before - 2, $stock($p['variant_id']), 'stock reserved');
Auth::actingAs($staff);
Orders::transition((int) $o2['id'], 'cancelled', 'customer request', (int) $staff['id']);
T::eq($before, $stock($p['variant_id']), 'cancellation returns stock');
T::throws(ValidationException::class, fn () => Orders::transition((int) $o2['id'], 'cancelled', null, (int) $staff['id']), 'cannot cancel twice');
T::eq($before, $stock($p['variant_id']), 'no double restock');

T::$current = 'Refunds';
$oid = (int) $o['id'];
$itemId = (int) $db->value('SELECT id FROM order_items WHERE order_id = ?', [$oid]);
$s0 = $stock($p['variant_id']);
$key = bin2hex(random_bytes(32));
Orders::refund($oid, 100000, 'one returned', [['order_item_id' => $itemId, 'quantity' => 1, 'restock' => true]], 'manual', 'BANK1', $key, (int) $staff['id']);
Orders::refund($oid, 100000, 'one returned', [['order_item_id' => $itemId, 'quantity' => 1, 'restock' => true]], 'manual', 'BANK1', $key, (int) $staff['id']);
T::eq(1, (int) $db->value('SELECT COUNT(*) FROM refunds WHERE order_id = ?', [$oid]), 'refund idempotent');
T::eq('partially_refunded', Orders::find($oid)['payment_status'], 'partial refund status');
T::eq($s0 + 1, $stock($p['variant_id']), 'refunded item restocked');
T::throws(ValidationException::class, fn () => Orders::refund($oid, 99999999, 'too much', [], 'manual', null, bin2hex(random_bytes(32)), (int) $staff['id']), 'cannot refund more than paid', 'exceeds');
T::throws(ValidationException::class, fn () => Orders::refund($oid, 100, 'x', [['order_item_id' => $itemId, 'quantity' => 5, 'restock' => false]], 'manual', null, bin2hex(random_bytes(32)), (int) $staff['id']), 'cannot refund more units than bought');
$left = (int) Orders::find($oid)['total_minor'] - (int) Orders::find($oid)['refunded_minor'];
Orders::refund($oid, $left, 'rest', [], 'manual', null, bin2hex(random_bytes(32)), (int) $staff['id']);
T::eq('refunded', Orders::find($oid)['status'], 'full refund moves order to Refunded');
T::throws(ValidationException::class, fn () => Orders::refund((int) $o2['id'], 100, 'unpaid', [], 'manual', null, bin2hex(random_bytes(32)), (int) $staff['id']), 'unpaid orders cannot be refunded');

// ───────────────────────────────── Bank transfer
T::$current = 'Bank transfer';
reset_db();
App\Services\Settings::put('payments', array_replace_recursive(App\Services\Settings::DEFAULTS['payments'], ['bank_transfer' => ['enabled' => true, 'bank_name' => 'Test Bank', 'account_title' => 'URAVEX', 'account_number' => '0123']]), null);
$rate = Fx::shipping();
$p = Fx::product(100000, null, 5);
Fx::newGuest();
Cart::add($p['variant_id'], 1);
$o = Fx::checkout($rate, 'bank_transfer')['order'];
T::eq('awaiting_payment', $o['status'], 'bank transfer awaits payment');
Orders::submitBankReference((int) $o['id'], 'TXN-555', null);
T::eq('pending_verification', Orders::find((int) $o['id'])['payment_status'], 'reference submitted');
$staff = Fx::user('order_staff');
Auth::actingAs($staff);
Orders::verifyBankTransfer((int) $o['id'], true, 'matched', (int) $staff['id']);
$o = Orders::find((int) $o['id']);
T::eq('confirmed', $o['status'], 'verified transfer confirms order');
T::eq('paid', $o['payment_status'], 'verified transfer marks paid');
T::throws(ValidationException::class, fn () => Orders::verifyBankTransfer((int) $o['id'], true, 'again', (int) $staff['id']), 'cannot verify twice');

// ───────────────────────────────── Sandbox webhooks
T::$current = 'Webhooks';
reset_db();
$rate = Fx::shipping();
$p = Fx::product(100000, null, 5);
Fx::newGuest();
Cart::add($p['variant_id'], 1);
$res = Fx::checkout($rate, 'sandbox');
$o = $res['order'];
T::eq('redirect', $res['next']['type'], 'sandbox redirects to hosted page');
T::eq(1, (int) $o['is_test'], 'sandbox orders flagged as test');
T::ok($o['reservation_expires_at'] !== null, 'online payment reserves stock with expiry');
$ref = (string) $db->value('SELECT provider_reference FROM payments WHERE order_id = ?', [$o['id']]);
$provider = new SandboxProvider();
$req = static fn (string $body, string $sig) => new Request('POST', '/webhooks/sandbox', [], [], [], ['HTTP_X_SANDBOX_SIGNATURE' => $sig, 'REMOTE_ADDR' => '127.0.0.1'], [], $body);

$bad = Payments::handleWebhook('sandbox', $req('{"id":"evt_x","type":"payment.paid","data":{"reference":"' . $ref . '","status":"paid","amount_minor":125000,"currency":"PKR"}}', 't=' . time() . ',v1=' . str_repeat('0', 64)));
T::eq(400, $bad['status'], 'invalid signature rejected');
T::eq('awaiting_payment', Orders::find((int) $o['id'])['status'], 'invalid webhook changes nothing');

$forgedBody = json_encode(['id' => 'evt_forged', 'type' => 'payment.paid', 'data' => ['reference' => $ref, 'status' => 'paid', 'amount_minor' => (int) $o['total_minor'], 'currency' => 'PKR']]);
$forged = Payments::handleWebhook('sandbox', $req($forgedBody, SandboxProvider::sign($forgedBody, time())));
T::eq('status_mismatch', $forged['outcome'], 'signed event not confirmed by provider API is not applied');
T::eq('awaiting_payment', Orders::find((int) $o['id'])['status'], 'browser/webhook alone never marks paid');

$old = SandboxProvider::sign($forgedBody, time() - 3600);
T::eq(400, Payments::handleWebhook('sandbox', $req($forgedBody, $old))['status'], 'stale timestamp rejected (replay protection)');

$sim = $provider->simulate($ref, 'pay');
$first = Payments::handleWebhook('sandbox', $req($sim['body'], $sim['signature']));
T::eq('paid', $first['outcome'], 'valid signed webhook confirmed by provider marks paid');
$o2 = Orders::find((int) $o['id']);
T::eq('confirmed', $o2['status'], 'order confirmed after payment');
T::eq('paid', $o2['payment_status'], 'payment status paid');
$replay = Payments::handleWebhook('sandbox', $req($sim['body'], $sim['signature']));
T::eq('duplicate', $replay['outcome'], 'replayed event ignored (idempotent)');
T::eq(1, (int) $db->value("SELECT COUNT(*) FROM order_status_history WHERE order_id = ? AND to_status = 'confirmed'", [$o['id']]), 'confirmed exactly once');

$tamperBody = json_encode(['id' => 'evt_amt', 'type' => 'payment.paid', 'data' => ['reference' => $ref, 'status' => 'paid', 'amount_minor' => 1, 'currency' => 'PKR']]);
T::eq('amount_mismatch', Payments::handleWebhook('sandbox', $req($tamperBody, SandboxProvider::sign($tamperBody, time())))['outcome'], 'amount mismatch flagged');

T::$current = 'Reservation expiry';
Fx::newGuest();
Cart::add($p['variant_id'], 2);
$before = $stock($p['variant_id']);
$o3 = Fx::checkout($rate, 'sandbox')['order'];
T::eq($before - 2, $stock($p['variant_id']), 'reserved');
$db->exec('UPDATE orders SET reservation_expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = ?', [$o3['id']]);
T::eq(1, Orders::expireStale(), 'expired reservation cancelled by cron');
T::eq('cancelled', Orders::find((int) $o3['id'])['status'], 'order cancelled');
T::eq($before, $stock($p['variant_id']), 'stock released');
T::eq('expired', (string) $db->value('SELECT status FROM payments WHERE order_id = ?', [$o3['id']]), 'payment marked expired');

// ───────────────────────────────── Auth tokens & rate limits
T::$current = 'Auth tokens';
reset_db();
$u = Fx::user();
$t = Tokens::issue((int) $u['id'], 'reset_password');
T::ok($db->value('SELECT COUNT(*) FROM user_tokens WHERE token_hash = ?', [$t]) == 0, 'raw token is not stored');
T::eq((int) $u['id'], Tokens::consume($t, 'reset_password'), 'token consumed once');
T::eq(null, Tokens::consume($t, 'reset_password'), 'token cannot be reused');
$t2 = Tokens::issue((int) $u['id'], 'reset_password');
$db->exec('UPDATE user_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)');
T::eq(null, Tokens::consume($t2, 'reset_password'), 'expired token rejected');
$t3 = Tokens::issue((int) $u['id'], 'verify_email');
T::eq(null, Tokens::consume($t3, 'reset_password'), 'token type enforced');
$t4 = Tokens::issue((int) $u['id'], 'verify_email');
T::eq(null, Tokens::consume($t3, 'verify_email'), 'new token supersedes old');
T::eq((int) $u['id'], Tokens::consume($t4, 'verify_email'), 'latest token works');
T::ok(password_verify('Password123', $u['password_hash']) && !str_contains($u['password_hash'], 'Password123'), 'passwords stored as one-way hashes');
T::ok(str_starts_with($u['password_hash'], '$argon2id$') || str_starts_with($u['password_hash'], '$2y$'), 'argon2id/bcrypt hash');

T::$current = 'Rate limiting';
$ok = 0;
for ($i = 0; $i < 7; $i++) {
    $ok += App\Core\RateLimiter::attempt('test-bucket', 5, 60) ? 1 : 0;
}
T::eq(5, $ok, 'only 5 attempts allowed in window');
T::throws(App\Core\HttpException::class, fn () => App\Core\RateLimiter::hit('test-bucket', 5, 60), 'hit() throws 429 when exceeded');

// ───────────────────────────────── Cart merge
T::$current = 'Cart merge';
reset_db();
$p = Fx::product(100000, null, 3);
$u = Fx::user();
Auth::actingAs($u);
Cart::add($p['variant_id'], 2);
Fx::newGuest();
$guestToken = bin2hex(random_bytes(32));
Cart::setGuestToken($guestToken);
Cart::add($p['variant_id'], 2);
Auth::actingAs($u);
Cart::setGuestToken($guestToken);
$notices = Cart::mergeGuestInto((int) $u['id']);
T::eq(3, Cart::count(), 'merged quantity capped at stock');
T::ok(count($notices) === 1, 'customer told about the adjustment');
T::eq(1, (int) $db->value('SELECT COUNT(*) FROM carts'), 'guest cart removed after merge');

// ───────────────────────────────── Uploads
T::$current = 'Uploads';
$tmpDir = BASE_PATH . '/tests/.tmp';
@mkdir($tmpDir, 0700, true);
$php = $tmpDir . '/evil.jpg';
file_put_contents($php, "<?php echo 'pwned'; ?>");
T::throws(ValidationException::class, fn () => Uploads::storeImage(['tmp_name' => $php, 'error' => UPLOAD_ERR_OK, 'size' => filesize($php), 'name' => 'evil.jpg']), 'PHP disguised as JPG rejected', 'Only JPG');
$big = $tmpDir . '/big.png';
$im = imagecreatetruecolor(100, 100);
imagepng($im, $big);
T::throws(ValidationException::class, fn () => Uploads::storeImage(['tmp_name' => $big, 'error' => UPLOAD_ERR_OK, 'size' => filesize($big), 'name' => 'tiny.png']), 'too-small image rejected', 'at least');
$good = $tmpDir . '/good.png';
$im = imagecreatetruecolor(600, 800);
imagepng($im, $good);
$stored = Uploads::storeImage(['tmp_name' => $good, 'error' => UPLOAD_ERR_OK, 'size' => filesize($good), 'name' => '../../etc/passwd.png']);
T::ok((bool) preg_match('#^products/\d{4}/\d{2}/[a-f0-9]{24}$#', $stored['path']), 'random safe filename (original name ignored)');
T::ok(is_file(Uploads::root() . '/' . $stored['path'] . '-800.webp'), 'resized WebP written');
Uploads::deleteImage($stored['path']);
T::ok(!is_file(Uploads::root() . '/' . $stored['path'] . '-800.webp'), 'image deleted');
$pdf = $tmpDir . '/fake.pdf';
file_put_contents($pdf, "<html><script>alert(1)</script></html>");
T::throws(ValidationException::class, fn () => Uploads::storeReceipt(['tmp_name' => $pdf, 'error' => UPLOAD_ERR_OK, 'size' => filesize($pdf), 'name' => 'r.pdf']), 'HTML disguised as receipt rejected');
T::eq(null, Uploads::receiptPath('../../.env'), 'receipt path traversal blocked');
array_map('unlink', glob($tmpDir . '/*') ?: []);

// ───────────────────────────────── CSV import
T::$current = 'CSV import';
reset_db();
$db->insert("INSERT INTO categories (name, slug) VALUES ('Shirts', 'shirts')");
$csv = $tmpDir . '/import.csv';
file_put_contents($csv, "product_sku,title,category,status,price,variant_sku,size,color,stock\n"
    . "SKU1,Oxford Shirt,shirts,active,4990,SKU1-M,M,White,5\n"
    . "SKU1,Oxford Shirt,shirts,active,4990,SKU1-L,L,White,3\n"
    . "SKU2,Bad Price,shirts,active,abc,SKU2-M,M,Black,1\n"
    . "SKU3,Dupe,shirts,draft,100,SKU1-M,M,Black,1\n"
    . "SKU4,Unknown Cat,hats,draft,100,SKU4-M,M,Black,1\n");
$plan = CsvImport::plan(CsvImport::parse($csv));
T::eq(3, count($plan['errors']), 'row-level errors reported');
T::eq([4, 5, 6], array_column($plan['errors'], 'line'), 'errors reference CSV line numbers');
T::throws(ValidationException::class, fn () => CsvImport::commit($plan, 1), 'commit refused while errors exist');
file_put_contents($csv, "product_sku,title,category,status,price,variant_sku,size,color,stock\nSKU1,Oxford Shirt,shirts,active,4990,SKU1-M,M,White,5\nSKU1,Oxford Shirt,shirts,active,4990,SKU1-L,L,White,3\n");
$admin = Fx::user('owner');
$res = CsvImport::commit(CsvImport::plan(CsvImport::parse($csv)), (int) $admin['id']);
T::eq(1, $res['created'], 'product created from CSV');
T::eq(8, (int) $db->value("SELECT SUM(stock) FROM product_variants"), 'variant stock imported');
T::eq(2, (int) $db->value("SELECT COUNT(*) FROM inventory_movements WHERE reason = 'import'"), 'import movements logged');
$res2 = CsvImport::commit(CsvImport::plan(CsvImport::parse($csv)), (int) $admin['id']);
T::eq(1, $res2['updated'], 're-import updates instead of duplicating');
T::eq(1, (int) $db->value('SELECT COUNT(*) FROM products'), 'no duplicate product');
@unlink($csv);

// ───────────────────────────────── Currency
T::$current = 'Currency';
reset_db();
use App\Core\Money;
use App\Services\Currency;
T::eq('PKR', Currency::base(), 'default base currency is PKR');
T::eq(1796, Money::convert(499000, 'PKR', 'USD', '0.0036'), 'PKR→USD conversion rounds half-up to cents');
T::eq(2695, Money::convert(499000, 'PKR', 'JPY', '0.54'), '0-decimal currency conversion');
T::eq(3071, Money::convert(1000, 'USD', 'KWD', '0.3071'), '3-decimal currency conversion');
T::eq('¥2,695', Money::format(2695, 'JPY'), 'JPY formatted without decimals');
T::eq(null, Money::parse('10.5', 'JPY'), 'JPY rejects decimals');
T::ok(!Money::validRate('0') && !Money::validRate('-1') && !Money::validRate('abc') && Money::validRate('0.0036'), 'rate validation');
App\Services\Settings::put('currency', ['base' => '', 'display' => ['USD' => '0.0036', 'XYZ' => '1'], 'show_switcher' => true], null);
T::eq(['PKR', 'USD'], Currency::choices(), 'only supported display currencies offered');
$_COOKIE[Currency::COOKIE] = 'USD';
Currency::setCurrent(null);
T::eq('$17.96', price(499000), 'storefront price shown in visitor currency');
T::eq('Rs 4,990', money(499000), 'money() stays in base currency');
$_COOKIE[Currency::COOKIE] = 'EUR';
Currency::setCurrent(null);
T::eq('PKR', Currency::current(), 'disabled currency in cookie ignored');
unset($_COOKIE[Currency::COOKIE]);
Currency::setCurrent(null);

$rate = Fx::shipping(25000);
$p = Fx::product(499000, 399000, 5);
$db->exec('UPDATE products SET cost_minor = 200000 WHERE id = ?', [$p['product_id']]);
$cp = Fx::coupon(['code' => 'FIX500', 'type' => 'fixed', 'value' => 50000, 'min_subtotal_minor' => 300000]);
Fx::newGuest();
Cart::add($p['variant_id'], 1);
$pkrOrder = Fx::checkout($rate)['order'];
$counts = Currency::changeBase('USD', '0.0036', null);
T::eq('USD', Currency::base(), 'base currency changed');
$prod = $db->one('SELECT price_minor, sale_price_minor, cost_minor, currency FROM products WHERE id = ?', [$p['product_id']]);
T::eq([1796, 1436, 720, 'USD'], [(int) $prod['price_minor'], (int) $prod['sale_price_minor'], (int) $prod['cost_minor'], $prod['currency']], 'product prices converted');
T::eq(90, (int) $db->value('SELECT price_minor FROM shipping_rates WHERE id = ?', [$rate]), 'delivery rate converted');
T::eq([180, 1080], array_map('intval', array_values($db->one('SELECT value, min_subtotal_minor FROM coupons WHERE id = ?', [$cp['id']]))), 'fixed coupon amounts converted');
T::eq(1800, (int) App\Services\Settings::get('shipping.free_threshold_minor'), 'free-delivery threshold converted');
T::eq('PKR', (string) $db->value('SELECT currency FROM orders WHERE id = ?', [$pkrOrder['id']]), 'existing orders keep their currency');
T::eq([], Currency::displayRates(), 'display rates reset after base change');
T::eq('$17.96', money(1796), 'money() uses new base currency');
Fx::newGuest();
Cart::add($p['variant_id'], 1);
$usdOrder = Fx::checkout($rate)['order'];
T::eq('USD', $usdOrder['currency'], 'new orders charged in new base currency');
T::eq(1436 + 90, (int) $usdOrder['total_minor'], 'checkout total in converted prices');
T::eq(1, App\Services\Reports::summary(App\Services\Reports::range(null, null))['orders'], 'reports only total orders in current base currency');
T::throws(ValidationException::class, fn () => Currency::changeBase('USD', '1', null), 'cannot change to the same currency');
T::throws(ValidationException::class, fn () => Currency::changeBase('EUR', '0', null), 'zero rate rejected');

// ───────────────────────────────── Live exchange rates
T::$current = 'Live exchange rates';
reset_db();
use App\Services\ExchangeRates;
T::ok(ExchangeRates::refresh(), 'live rates downloaded (fixture)');
T::eq('0.003611', ExchangeRates::rate('USD'), 'rate parsed as exact decimal string');
App\Services\Settings::put('currency', ['base' => '', 'display' => ['USD' => ''], 'default_display' => 'USD', 'show_switcher' => true, 'auto_rates' => true], null);
Currency::setCurrent(null);
T::eq(['USD' => '0.003611'], Currency::displayRates(), 'enabled currency uses live rate without manual entry');
T::eq('$18.02', price(499000), 'storefront price uses live rate');
App\Services\Settings::put('currency', ['base' => '', 'display' => ['USD' => '0.0036'], 'default_display' => 'USD', 'show_switcher' => true, 'auto_rates' => false], null);
T::eq(['USD' => '0.0036'], Currency::displayRates(), 'manual rate used when automatic rates are off');
App\Services\Settings::put('currency', ['base' => '', 'display' => ['GBP' => '0.0029'], 'default_display' => '', 'show_switcher' => true, 'auto_rates' => true], null);
$db->exec("DELETE FROM site_settings WHERE `key` = 'currency_rates'");
ExchangeRates::forget();
$saved = ExchangeRates::$fetcher;
ExchangeRates::$fetcher = static fn (string $b): ?string => null; // service down
T::ok(!ExchangeRates::refresh(), 'refresh reports failure when the service is down');
T::eq(['GBP' => '0.0029'], Currency::displayRates(), 'manual fallback used while live rates unavailable');
ExchangeRates::$fetcher = static fn (string $b): ?string => '{"result":"error"}';
T::ok(!ExchangeRates::refresh(), 'error responses are rejected');
ExchangeRates::$fetcher = static fn (string $b): ?string => '{"result":"success","base_code":"USD","rates":{"PKR":277,"EUR":0.9,"GBP":0.8,"AED":3.67}}';
T::ok(!ExchangeRates::refresh(), 'rates for a different base currency are rejected');
ExchangeRates::$fetcher = $saved;
T::eq(0, $db->value("SELECT COUNT(*) FROM site_settings WHERE `key` = 'currency_rates' AND value LIKE '%\"rates\"%'"), 'failed refreshes never overwrite rates');

// ───────────────────────────────── Crypto payments
T::$current = 'Crypto payments';
reset_db();
App\Payments\Registry::$legacy = false;
App\Payments\Registry::reset();
T::eq(['usdt_trc20', 'usdt_erc20', 'btc', 'eth'], array_keys(App\Payments\Registry::all()), 'store offers only TRC20, ERC20, BTC and ETH');
T::eq([], array_keys(App\Payments\Registry::available(100000)), 'no method is offered until a wallet address is saved');
T::eq('Cash on Delivery', App\Payments\Registry::label('cod'), 'retired methods keep a label on old orders');
$trc = 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE';
App\Services\Settings::put('payments', array_replace_recursive(App\Services\Settings::DEFAULTS['payments'], [
    'usdt_trc20' => ['address' => $trc], 'btc' => ['address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'],
    'eth' => ['enabled' => false, 'address' => '0x52908400098527886E0F7030069857D2E4169EE7'],
]), null);
App\Payments\Registry::reset();
T::eq(['usdt_trc20', 'btc'], array_keys(App\Payments\Registry::available(100000)), 'methods with an address and switched on are offered');

$rate = Fx::shipping();
$p = Fx::product(100000, null, 5);
Fx::newGuest();
Cart::add($p['variant_id'], 1);
$o = Fx::checkout($rate, 'usdt_trc20')['order'];
T::eq(125000, (int) $o['total_minor'], 'order total (Rs 1,250)');
T::eq('awaiting_payment', $o['status'], 'crypto order awaits payment');
T::ok($o['reservation_expires_at'] !== null, 'stock reserved with an expiry');
$pay = $db->one('SELECT * FROM payments WHERE order_id = ?', [$o['id']]);
T::eq('4.52', $pay['pay_amount'], 'USDT amount converted at the live rate and rounded up (1250 × 0.003611 = 4.51375)');
T::eq(['USDT', 'TRC20', $trc], [$pay['pay_asset'], $pay['pay_network'], $pay['pay_address']], 'asset, network and wallet snapshotted on the payment');
T::throws(ValidationException::class, fn () => Orders::submitBankReference((int) $o['id'], 'TXN-555', null), 'free-text reference rejected', 'transaction hash');
$txid = str_repeat('9f', 32);
Orders::submitBankReference((int) $o['id'], $txid, null);
T::eq('pending_verification', Orders::find((int) $o['id'])['payment_status'], 'TXID submitted for verification');
$db->exec('UPDATE orders SET reservation_expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = ?', [$o['id']]);
T::eq(0, Orders::expireStale(), 'orders with a submitted TXID are never auto-cancelled');

Fx::newGuest();
Cart::add($p['variant_id'], 1);
$o2 = Fx::checkout($rate, 'usdt_trc20')['order'];
T::throws(ValidationException::class, fn () => Orders::submitBankReference((int) $o2['id'], $txid, null), 'a TXID cannot be reused for another order', 'already been submitted');

$staff = Fx::user('order_staff');
Auth::actingAs($staff);
Orders::verifyBankTransfer((int) $o2['id'], false, 'Not found on explorer', (int) $staff['id']);
T::eq('failed', Orders::find((int) $o2['id'])['payment_status'], 'rejected payment marked failed');
Orders::submitBankReference((int) $o2['id'], str_repeat('7e', 32), null);
T::eq('pending_verification', Orders::find((int) $o2['id'])['payment_status'], 'customer can resubmit after a rejection');
Orders::verifyBankTransfer((int) $o['id'], true, 'Confirmed on Tronscan', (int) $staff['id']);
$o = Orders::find((int) $o['id']);
T::eq(['confirmed', 'paid'], [$o['status'], $o['payment_status']], 'verified crypto payment confirms the order');

T::$current = 'Crypto prices';
Fx::newGuest();
Cart::add($p['variant_id'], 1);
$o3 = Fx::checkout($rate, 'btc')['order'];
$pay = $db->one('SELECT * FROM payments WHERE order_id = ?', [$o3['id']]);
T::eq(['BTC', '0.00007523', '60000'], [$pay['pay_asset'], $pay['pay_amount'], $pay['pay_rate']], 'BTC amount at the live price, rounded up to 8 decimals');
$eth = (new App\Payments\CryptoTransferProvider('eth'))->quote(125000, 'PKR');
T::eq('0.00180514', $eth['amount'], 'ETH quote rounded up (4.51375 / 2500.5)');
$db->exec("DELETE FROM site_settings WHERE `key` = 'crypto_prices'");
App\Services\CryptoPrices::forget();
$savedFetcher = App\Services\CryptoPrices::$fetcher;
App\Services\CryptoPrices::$fetcher = static fn (string $s): ?string => $s === 'coingecko' ? '{"bitcoin":{"usd":61000},"ethereum":{"usd":2600.25}}' : null;
T::eq('61000', App\Services\CryptoPrices::price('BTC'), 'falls back to CoinGecko when Binance is down');
$db->exec("DELETE FROM site_settings WHERE `key` = 'crypto_prices'");
App\Services\CryptoPrices::forget();
App\Services\CryptoPrices::$fetcher = static fn (string $s): ?string => null;
App\Payments\Registry::reset();
T::eq(['usdt_trc20'], array_keys(App\Payments\Registry::available(100000)), 'BTC hidden at checkout while no live price is available');
Fx::newGuest();
Cart::add($p['variant_id'], 1);
T::throws(ValidationException::class, fn () => Fx::checkout($rate, 'btc'), 'BTC order refused without a live price');
$db->exec("UPDATE site_settings SET value = JSON_SET(value, '$.fetched_at', '2020-01-01 00:00:00') WHERE `key` = 'crypto_prices'");
App\Services\CryptoPrices::$fetcher = $savedFetcher;
App\Payments\Registry::$legacy = true;
App\Payments\Registry::reset();

T::$current = 'US shipping zones';
reset_db();
$remote = $db->insert("INSERT INTO shipping_zones (name, cities, provinces, sort_order) VALUES ('Alaska & Hawaii', '', 'ak\nhawaii', 1)");
$lower48 = $db->insert("INSERT INTO shipping_zones (name, cities, provinces, is_fallback, sort_order) VALUES ('Lower 48', '', '', 1, 2)");
T::eq($remote, (int) App\Services\Shipping::zoneFor('Anchorage', 'AK')['id'], 'state matched by code');
T::eq($remote, (int) App\Services\Shipping::zoneFor('Honolulu', 'HI')['id'], 'state code matches a zone listing the state name');
T::eq($lower48, (int) App\Services\Shipping::zoneFor('Austin', 'TX')['id'], 'other states use the fallback zone');
T::eq($lower48, (int) App\Services\Shipping::zoneFor('', 'TX')['id'], 'zone found from the state alone (city not needed yet)');
T::eq('US', (string) $db->value('SELECT country FROM shipping_zones WHERE id = ?', [$lower48]), 'new zones default to the US');


// ───────────────────────────────── Marketplace
T::$current = 'Marketplace earnings';
reset_db();
$mpOwner = Fx::user('customer', 'seller1@example.test');
$sid = App\Services\Marketplace::apply((int) $mpOwner['id'], ['shop_name' => 'Brooklyn Knits', 'description' => 'Hand-finished knitwear made in New York.', 'contact_phone' => null, 'payout_address' => '']);
T::throws(ValidationException::class, fn () => App\Services\Marketplace::apply((int) $mpOwner['id'], ['shop_name' => 'Other', 'description' => str_repeat('x', 30), 'contact_phone' => null, 'payout_address' => '']), 'cannot apply twice');
T::throws(ValidationException::class, fn () => App\Services\Marketplace::apply((int) Fx::user()['id'], ['shop_name' => 'Brooklyn Knits', 'description' => str_repeat('x', 30), 'contact_phone' => null, 'payout_address' => '']), 'shop names are unique');
$staffMp = Fx::user('manager');
T::throws(ValidationException::class, fn () => App\Services\Marketplace::decide($sid, 'reject', '', (int) $staffMp['id']), 'rejecting needs a reason');
App\Services\Marketplace::decide($sid, 'approve', null, (int) $staffMp['id']);
T::eq('approved', App\Services\Marketplace::seller($sid)['status'], 'seller approved');

$rate = Fx::shipping(0);
$sp = Fx::product(10000, null, 10); // 100.00
$db->exec('UPDATE products SET seller_id = ? WHERE id = ?', [$sid, $sp['product_id']]);
$house = Fx::product(5000, null, 10);
Fx::newGuest();
Cart::add($sp['variant_id'], 2);
Cart::add($house['variant_id'], 1);
$mo = Fx::checkout($rate, 'cod')['order'];
$lines = $db->all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$mo['id']]);
$sellerLine = array_values(array_filter($lines, static fn ($l) => (int) $l['product_id'] === $sp['product_id']))[0];
$houseLine = array_values(array_filter($lines, static fn ($l) => (int) $l['product_id'] === $house['product_id']))[0];
T::eq([$sid, 2000, 18000], [(int) $sellerLine['seller_id'], (int) $sellerLine['commission_minor'], (int) $sellerLine['seller_amount_minor']], 'seller line snapshot: 10% commission on 200.00');
T::eq(null, $houseLine['seller_id'], 'house product has no seller');
T::eq(0, App\Services\Marketplace::balances($sid)['pending'], 'nothing credited before payment');

Orders::recordCodCollected((int) $mo['id'], 'cash', (int) $staffMp['id']);
$b = App\Services\Marketplace::balances($sid);
T::eq([18000, 0], [$b['pending'], $b['available']], 'payment credits the seller share as pending');
App\Services\Marketplace::creditOrder($db, Orders::find((int) $mo['id']));
T::eq(1, (int) $db->value("SELECT COUNT(*) FROM seller_ledger WHERE order_id = ? AND type = 'sale'", [$mo['id']]), 'crediting is idempotent');
T::eq(1, (int) $db->value("SELECT COUNT(*) FROM email_outbox WHERE to_email = 'seller1@example.test' AND template = 'seller_new_order'"), 'seller emailed once about the new order');

Orders::transition((int) $mo['id'], 'processing', null, (int) $staffMp['id']);
Orders::transition((int) $mo['id'], 'packed', null, (int) $staffMp['id']);
Orders::ship((int) $mo['id'], 'USPS', '9400100000000000000000', null, null, (int) $staffMp['id']);
Orders::transition((int) $mo['id'], 'delivered', null, (int) $staffMp['id']);
$avail = (string) $db->value("SELECT available_at FROM seller_ledger WHERE order_id = ? AND type = 'sale'", [$mo['id']]);
T::ok(abs(strtotime($avail . ' UTC') - (time() + 7 * 86400)) < 120, 'earnings unlock 7 days after delivery');
T::eq(0, App\Services\Marketplace::balances($sid)['available'], 'still pending during the hold');
$db->exec('UPDATE seller_ledger SET available_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE order_id = ?', [$mo['id']]);
T::eq([0, 18000], [App\Services\Marketplace::balances($sid)['pending'], App\Services\Marketplace::balances($sid)['available']], 'available after the hold');

T::$current = 'Marketplace withdrawals';
$uid = (int) $mpOwner['id'];
T::throws(ValidationException::class, fn () => App\Services\Marketplace::requestWithdrawal($sid, 5000, $uid), 'payout address required', 'payout address');
$db->exec("UPDATE sellers SET payout_address = 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE' WHERE id = ?", [$sid]);
T::throws(ValidationException::class, fn () => App\Services\Marketplace::requestWithdrawal($sid, 1000, $uid), 'below the minimum rejected', 'minimum');
T::throws(ValidationException::class, fn () => App\Services\Marketplace::requestWithdrawal($sid, 18001, $uid), 'cannot withdraw more than available', 'up to');
$w1 = App\Services\Marketplace::requestWithdrawal($sid, 10000, $uid);
T::eq(8000, App\Services\Marketplace::balances($sid)['available'], 'requested amount leaves the available balance');
T::throws(ValidationException::class, fn () => App\Services\Marketplace::requestWithdrawal($sid, 5000, $uid), 'one withdrawal at a time', 'already');
T::throws(ValidationException::class, fn () => App\Services\Marketplace::processWithdrawal($w1, false, null, '', (int) $staffMp['id']), 'rejection needs a reason');
App\Services\Marketplace::processWithdrawal($w1, false, null, 'Address check failed', (int) $staffMp['id']);
T::eq(18000, App\Services\Marketplace::balances($sid)['available'], 'rejected withdrawal returns to the balance');
T::throws(ValidationException::class, fn () => App\Services\Marketplace::processWithdrawal($w1, true, str_repeat('a', 64), null, (int) $staffMp['id']), 'a processed withdrawal cannot be processed again');
$w2 = App\Services\Marketplace::requestWithdrawal($sid, 18000, $uid);
T::throws(ValidationException::class, fn () => App\Services\Marketplace::processWithdrawal($w2, true, 'not-a-hash', null, (int) $staffMp['id']), 'payout TXID format checked');
App\Services\Marketplace::processWithdrawal($w2, true, str_repeat('5b', 32), null, (int) $staffMp['id']);
$b = App\Services\Marketplace::balances($sid);
T::eq([0, 18000, 0], [$b['available'], $b['withdrawn'], $b['in_withdrawal']], 'paid withdrawal recorded');
T::eq((int) $db->value('SELECT COALESCE(SUM(amount_minor),0) FROM seller_ledger WHERE seller_id = ?', [$sid]), $b['available'] + $b['pending'], 'ledger sum equals pending + available');

T::$current = 'Marketplace refunds';
Orders::refund((int) $mo['id'], 5000, 'One sweater returned', [['order_item_id' => (int) $sellerLine['id'], 'quantity' => 1, 'restock' => true]], 'manual', 'TX', bin2hex(random_bytes(16)), (int) $staffMp['id']);
T::eq(-9000, App\Services\Marketplace::balances($sid)['available'], 'refund after payout makes the balance negative (offset against future sales)');

Fx::newGuest();
Cart::add($sp['variant_id'], 1);
$mo2 = Fx::checkout($rate, 'cod')['order'];
Orders::recordCodCollected((int) $mo2['id'], null, (int) $staffMp['id']);
T::eq(9000, App\Services\Marketplace::balances($sid)['pending'], 'second order pending');
Orders::refund((int) $mo2['id'], (int) $mo2['total_minor'], 'Cancelled by customer', [], 'manual', 'TX2', bin2hex(random_bytes(16)), (int) $staffMp['id']);
T::eq(0, App\Services\Marketplace::balances($sid)['pending'], 'full refund before delivery removes the pending earnings');

T::$current = 'Marketplace suspension';
App\Services\Marketplace::decide($sid, 'suspend', 'Policy review', (int) $staffMp['id']);
$slug = (string) $db->value('SELECT slug FROM products WHERE id = ?', [$sp['product_id']]);
T::eq(null, App\Services\Catalog::product($slug), 'suspended seller products are hidden');
T::eq(0, count(array_filter(App\Services\Catalog::search([])['items'], static fn ($i) => (int) $i['id'] === $sp['product_id'])), 'and removed from listings');
Fx::newGuest();
Cart::add($house['variant_id'], 1);
$db->exec('INSERT INTO cart_items (cart_id, variant_id, quantity) SELECT cart_id, ?, 1 FROM cart_items ORDER BY id DESC LIMIT 1', [$sp['variant_id']]);
T::throws(ValidationException::class, fn () => Fx::checkout($rate, 'cod'), 'cannot buy from a suspended seller');
T::throws(ValidationException::class, fn () => App\Services\Marketplace::requestWithdrawal($sid, 100, $uid), 'suspended sellers cannot withdraw');

T::$current = 'Checkout with several items';
reset_db();
$rate = Fx::shipping();
$older = Fx::product(100000, null, 5);
$newer = Fx::product(150000, null, 5);
Fx::newGuest();
// Added newest variant first: the bag lists items in the order added, checkout locks them by variant id.
Cart::add($newer['variant_id'], 1);
Cart::add($older['variant_id'], 2);
$r = Fx::checkout($rate, 'cod');
T::ok(!empty($r['order']['id']), 'order with items added out of id order places without a "total changed" error');
T::eq(2, (int) $db->value('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$r['order']['id']]), 'both items on the order');
