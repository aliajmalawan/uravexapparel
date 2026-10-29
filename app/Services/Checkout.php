<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Payments\CryptoTransferProvider;
use App\Payments\Registry;

/**
 * Order placement. Everything the customer sees is recalculated here from the
 * database inside one transaction:
 *
 *  1. idempotency key — a repeated submit returns the original order
 *  2. lock variants (FOR UPDATE, ordered by id to avoid deadlocks)
 *  3. re-validate product status, variant, stock, prices
 *  4. re-validate coupon (coupon row locked) and shipping rate for the address
 *  5. re-validate payment method availability
 *  6. compare with the fingerprint the customer confirmed — if anything
 *     changed, abort and show the new totals instead of charging silently
 *  7. insert order + item snapshots, decrement stock (conditional UPDATE),
 *     write inventory movements, redeem coupon, create payment row
 */
final class Checkout
{
    /**
     * @param array{email:string, phone:string, full_name:string, line1:string, line2:?string, city:string, province:?string, postal_code:?string,
     *              shipping_rate_id:int, payment_method:string, note:?string, idempotency_key:string, fingerprint:string,
     *              billing_same:bool, billing?:array, save_address?:bool} $in
     * @return array{order:array, payment:array, next:array}
     */
    public static function place(array $in): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $in['idempotency_key'])) {
            throw ValidationException::one('checkout', 'Your checkout session expired. Please review your order and try again.');
        }
        $db = Database::get();
        $existing = $db->one('SELECT * FROM orders WHERE idempotency_key = ?', [$in['idempotency_key']]);
        if ($existing) {
            return ['order' => $existing, 'payment' => $db->one('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$existing['id']]) ?? [], 'next' => ['type' => 'duplicate']];
        }

        $cart = Cart::current();
        if (!$cart) {
            throw ValidationException::one('checkout', 'Your bag is empty.');
        }
        $userId = Auth::id();
        if ($userId === null && !Settings::get('checkout.guest_checkout', true)) {
            throw ValidationException::one('checkout', 'Please sign in to check out.');
        }
        $provider = Registry::get($in['payment_method']);
        if (!$provider || !$provider->isImplemented() || !$provider->isConfigured() || !$provider->isEnabled()) {
            throw ValidationException::one('payment_method', 'That payment method is not available. Please choose another.');
        }

        $result = $db->transaction(static function (Database $db) use ($in, $cart, $userId, $provider): array {
            // Re-check idempotency inside the transaction (unique index is the final guard).
            $dupe = $db->one('SELECT * FROM orders WHERE idempotency_key = ? FOR UPDATE', [$in['idempotency_key']]);
            if ($dupe) {
                return ['order' => $dupe, 'payment' => [], 'next' => ['type' => 'duplicate']];
            }

            $items = $db->all('SELECT variant_id, quantity FROM cart_items WHERE cart_id = ? ORDER BY variant_id', [$cart['id']]);
            if (!$items) {
                throw ValidationException::one('checkout', 'Your bag is empty.');
            }
            $variantIds = array_map(static fn ($i) => (int) $i['variant_id'], $items);
            $variants = [];
            foreach ($db->all(
                'SELECT v.*, p.title, p.status AS product_status, p.published_at, p.price_minor AS p_price, p.sale_price_minor AS p_sale, p.category_id, p.currency
                 FROM product_variants v JOIN products p ON p.id = v.product_id
                 WHERE v.id IN ' . Database::in($variantIds) . ' ORDER BY v.id FOR UPDATE',
                $variantIds
            ) as $v) {
                $variants[(int) $v['id']] = $v;
            }
            $collections = [];
            $pids = array_values(array_unique(array_map(static fn ($v) => (int) $v['product_id'], $variants)));
            foreach ($db->all('SELECT product_id, collection_id FROM product_collections WHERE product_id IN ' . Database::in($pids), $pids) as $pc) {
                $collections[$pc['product_id']][] = (int) $pc['collection_id'];
            }

            $problems = [];
            $lines = [];
            $currency = Currency::base();
            foreach ($items as $it) {
                $v = $variants[(int) $it['variant_id']] ?? null;
                $qty = (int) $it['quantity'];
                if (!$v || !$v['is_active'] || $v['product_status'] !== 'active' || $v['published_at'] === null || $v['published_at'] > now_utc()) {
                    $problems[] = ($v['title'] ?? 'An item') . ' is no longer available.';
                    continue;
                }
                if ((int) $v['stock'] < $qty) {
                    $problems[] = (int) $v['stock'] > 0
                        ? "Only {$v['stock']} left of {$v['title']} (" . Cart::variantLabel($v['size'], $v['color']) . ').'
                        : "{$v['title']} (" . Cart::variantLabel($v['size'], $v['color']) . ') just sold out.';
                    continue;
                }
                if ($v['currency'] !== $currency) {
                    $problems[] = "{$v['title']} is priced in a different currency.";
                    continue;
                }
                $price = Pricing::unitPrice(
                    ['price_minor' => $v['p_price'], 'sale_price_minor' => $v['p_sale']],
                    ['price_minor' => $v['price_minor'], 'sale_price_minor' => $v['sale_price_minor']]
                );
                $lines[] = [
                    'key' => (int) $v['id'],
                    'unit' => $price['price'],
                    'qty' => $qty,
                    'product_id' => (int) $v['product_id'],
                    'category_id' => $v['category_id'] !== null ? (int) $v['category_id'] : null,
                    'collection_ids' => $collections[$v['product_id']] ?? [],
                    'variant' => $v,
                ];
            }
            if ($problems) {
                throw new ValidationException(['checkout' => implode(' ', $problems)], 'Some items in your bag changed. Please review your bag.');
            }

            // Shipping rate must belong to the zone for this address.
            $rate = $db->one('SELECT r.*, z.id AS zone_id FROM shipping_rates r JOIN shipping_zones z ON z.id = r.zone_id WHERE r.id = ? AND r.is_active = 1 AND z.is_active = 1', [$in['shipping_rate_id']]);
            $zone = Shipping::zoneFor($in['city'], $in['province']);
            if (!$rate || !$zone || (int) $rate['zone_id'] !== (int) $zone['id']) {
                throw ValidationException::one('shipping_rate_id', 'Choose a delivery option available for your city.');
            }

            // Coupon (lock the row so per-customer and usage limits serialize).
            $coupon = null;
            if ($cart['coupon_code']) {
                $c = Coupons::find($cart['coupon_code'], true);
                if (!$c) {
                    throw ValidationException::one('coupon', 'Your discount code is no longer valid.');
                }
                $coupon = Coupons::evaluate($c, $lines, $userId, $in['email']);
                foreach ($lines as &$l) {
                    $l['eligible'] = in_array($l['key'], $coupon['eligible_keys'], true);
                }
                unset($l);
            }

            $taxPercent = (int) Settings::get('checkout.tax_percent', 0);
            $totals = Pricing::totals($lines, $coupon, (int) $rate['price_minor'], Shipping::freeOver($rate), $taxPercent);

            if (!$provider->availableFor($totals['total'], (bool) $rate['cod_available'])) {
                throw ValidationException::one('payment_method', $provider->label() . ' is not available for this order.');
            }
            if (!hash_equals(Pricing::fingerprint($totals, $lines), $in['fingerprint'])) {
                throw new ValidationException(['checkout' => 'Prices, discounts or delivery charges changed since you started checkout. Please review the updated total before placing your order.'], 'Your order total changed. Please review it.');
            }

            $shippingAddress = [
                'full_name' => $in['full_name'], 'phone' => $in['phone'], 'line1' => $in['line1'], 'line2' => $in['line2'],
                'city' => $in['city'], 'province' => $in['province'], 'postal_code' => $in['postal_code'], 'country' => Region::COUNTRY,
            ];
            $billing = $in['billing_same'] ? $shippingAddress : ($in['billing'] ?? $shippingAddress);
            if ($provider instanceof CryptoTransferProvider && $provider->quote($totals['total'], $currency) === null) {
                throw ValidationException::one('payment_method', 'Live ' . $provider->asset() . ' price is unavailable right now. Please choose USDT or try again in a minute.');
            }
            $isTest = $provider->code() === 'sandbox';
            $expires = $provider->reservationExpires()
                ? gmdate('Y-m-d H:i:s', time() + 60 * max(5, (int) Settings::get('checkout.reservation_minutes', 30)))
                : null;

            $orderId = $db->insert(
                'INSERT INTO orders (user_id, email, phone, customer_name, status, payment_status, payment_method, currency,
                    subtotal_minor, discount_minor, shipping_minor, tax_minor, total_minor, coupon_code,
                    shipping_address, billing_address, shipping_method, customer_note, idempotency_key, reservation_expires_at, is_test)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId, mb_strtolower($in['email']), $in['phone'], $in['full_name'],
                    $provider->initialOrderStatus(), 'unpaid', $provider->code(), $currency,
                    $totals['subtotal'], $totals['discount'], $totals['shipping'], $totals['tax'], $totals['total'],
                    $coupon['code'] ?? null,
                    json_encode($shippingAddress, JSON_UNESCAPED_UNICODE), json_encode($billing, JSON_UNESCAPED_UNICODE),
                    $rate['name'] . ' (' . $rate['min_days'] . '–' . $rate['max_days'] . ' days)',
                    $in['note'], $in['idempotency_key'], $expires, $isTest,
                ]
            );
            $orderNumber = 'UVX-' . (100000 + $orderId);
            $db->exec('UPDATE orders SET order_number = ? WHERE id = ?', [$orderNumber, $orderId]);

            foreach ($lines as $l) {
                $v = $l['variant'];
                $lineDiscount = $totals['line_discounts'][$l['key']] ?? 0;
                $image = $db->value('SELECT path FROM product_images WHERE product_id = ? ORDER BY (color <=> ?) DESC, position, id LIMIT 1', [$v['product_id'], $v['color']]);
                $db->insert(
                    'INSERT INTO order_items (order_id, product_id, variant_id, product_title, variant_label, sku, image_path, unit_price_minor, quantity, discount_minor, line_total_minor)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$orderId, $v['product_id'], $v['id'], $v['title'], Cart::variantLabel($v['size'], $v['color']), $v['sku'], $image,
                     $l['unit'], $l['qty'], $lineDiscount, $l['unit'] * $l['qty'] - $lineDiscount]
                );
                // Conditional decrement: the authoritative oversell guard.
                $n = $db->exec('UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?', [$l['qty'], $v['id'], $l['qty']]);
                if ($n !== 1) {
                    throw ValidationException::one('checkout', "{$v['title']} just sold out. Please review your bag.");
                }
                $after = (int) $db->value('SELECT stock FROM product_variants WHERE id = ?', [$v['id']]);
                $db->insert(
                    "INSERT INTO inventory_movements (variant_id, delta, stock_after, reason, note, order_id) VALUES (?, ?, ?, 'sale', ?, ?)",
                    [$v['id'], -$l['qty'], $after, 'Order ' . $orderNumber, $orderId]
                );
            }

            // Marketplace lines: snapshot seller, commission and seller share (refuses suspended sellers).
            Marketplace::snapshotOrder($db, $orderId);

            if ($coupon) {
                if ($coupon['per_customer_limit'] !== null && Coupons::customerUses((int) $coupon['id'], $userId, $in['email']) >= (int) $coupon['per_customer_limit']) {
                    throw ValidationException::one('coupon', 'You have already used this code the maximum number of times.');
                }
                Coupons::redeem($coupon, $orderId, $userId, $in['email'], $totals['discount']);
            }

            $reference = $provider->code() === 'sandbox' ? Payments::newReference('sandbox') : null;
            $paymentId = $db->insert(
                'INSERT INTO payments (order_id, provider, mode, status, amount_minor, currency, provider_reference) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$orderId, $provider->code(), $isTest ? 'sandbox' : 'live', $provider->initialPaymentStatus(), $totals['total'], $currency, $reference]
            );

            Orders::history($orderId, null, $provider->initialOrderStatus(), 'Order placed', $userId);
            Cart::clear((int) $cart['id']);

            if ($userId !== null && !empty($in['save_address'])) {
                $hasAddress = (int) $db->value('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$userId]);
                $db->insert(
                    'INSERT INTO addresses (user_id, label, full_name, phone, line1, line2, city, province, postal_code, country, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$userId, 'Home', $in['full_name'], $in['phone'], $in['line1'], $in['line2'], $in['city'], $in['province'], $in['postal_code'], Region::COUNTRY, $hasAddress === 0]
                );
            }

            $order = $db->one('SELECT * FROM orders WHERE id = ?', [$orderId]);
            Notifications::orderPlaced($order);
            $payment = $db->one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
            return ['order' => $order, 'payment' => $payment, 'next' => []];
        });

        if (($result['next']['type'] ?? '') !== 'duplicate') {
            // Provider hand-off after commit (never hold DB locks during network calls).
            $result['next'] = $provider->start($result['order'], $result['payment']);
        }
        return $result;
    }
}
