<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Auth\Auth;
use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploads;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Mail\Mailer;
use App\Payments\Registry;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\Orders;
use App\Services\Payments;
use App\Services\Pricing;
use App\Services\Region;
use App\Services\Settings;
use App\Services\Shipping;

final class CheckoutController extends Controller
{
    private const FIELDS = ['email', 'phone', 'full_name', 'line1', 'line2', 'city', 'province', 'postal_code', 'note', 'shipping_rate_id', 'payment_method', 'save_address'];

    public function show(Request $r): Response
    {
        if (!Auth::check() && !Settings::get('checkout.guest_checkout', true)) {
            Session::put('intended', '/checkout');
            return $this->redirect('/login', null, 'Please sign in to check out.');
        }
        $notices = Cart::normalize();
        if ($notices) {
            Session::flashNow('notices', $notices);
        }
        return $this->render($this->prefill([]), []);
    }

    /** Builds initial form values from the signed-in customer's profile/default address. */
    private function prefill(array $input): array
    {
        $u = Auth::user();
        $values = array_fill_keys(self::FIELDS, '');
        $values['save_address'] = '1';
        if ($u) {
            $values['email'] = $u['email'];
            $values['phone'] = (string) $u['phone'];
            $values['full_name'] = $u['name'];
            $addr = Database::get()->one('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1', [$u['id']]);
            if ($addr) {
                $values = array_merge($values, [
                    'full_name' => $addr['full_name'], 'phone' => $addr['phone'], 'line1' => $addr['line1'], 'line2' => (string) $addr['line2'],
                    'city' => $addr['city'], 'province' => (string) $addr['province'], 'postal_code' => (string) $addr['postal_code'], 'save_address' => '',
                ]);
            }
        }
        foreach (self::FIELDS as $f) {
            if (array_key_exists($f, $input)) {
                $values[$f] = is_scalar($input[$f]) ? trim((string) $input[$f]) : '';
            }
        }
        return $values;
    }

    private function render(array $values, array $errors, int $status = 200): Response
    {
        // Delivery options appear once the state is known (zones match by state, then city, then fallback).
        $rates = $values['province'] !== '' || $values['city'] !== '' ? Shipping::ratesFor($values['city'], $values['province'] ?: null) : [];
        $rate = null;
        foreach ($rates as $candidate) {
            if ((string) $candidate['id'] === (string) $values['shipping_rate_id']) {
                $rate = $candidate;
            }
        }
        $rate ??= $rates[0] ?? null;
        $summary = Cart::summary($rate ? (int) $rate['price_minor'] : null, $rate ? Shipping::freeOver($rate) : null, $values['email'] ?: null);
        if (!$summary['lines']) {
            return $this->redirect('/cart');
        }
        if ($summary['coupon_error']) {
            Session::flashNow('warning', $summary['coupon_error']);
        }
        $methods = Registry::available($summary['totals']['total'], $rate ? (bool) $rate['cod_available'] : true);
        if ($values['payment_method'] === '' || !isset($methods[$values['payment_method']])) {
            $values['payment_method'] = (string) array_key_first($methods);
        }
        $addresses = Auth::check() ? Database::get()->all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [Auth::id()]) : [];

        return $this->view('store/checkout', [
            'v' => $values,
            'errors' => $errors,
            's' => $summary,
            'rates' => $rates,
            'rate' => $rate,
            'methods' => $methods,
            'states' => Region::STATES,
            'addresses' => $addresses,
            'fingerprint' => $rate ? Pricing::fingerprint($summary['totals'], $summary['pricing_lines']) : '',
            'idempotencyKey' => bin2hex(random_bytes(32)),
            'bank' => Settings::get('payments.bank_transfer'),
        ], ['title' => 'Checkout', 'noindex' => true], 'layouts/store', $status);
    }

    public function place(Request $r): Response
    {
        $values = $this->prefill($r->post);
        $values['save_address'] = $r->bool('save_address') ? '1' : '';

        // Use a saved address (owned by this user only).
        if ($r->str('intent') === 'address' && $r->str('use_address') !== '' && Auth::check()) {
            $addr = Database::get()->one('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [$r->int('use_address'), Auth::id()]);
            if ($addr) {
                $values = array_merge($values, ['full_name' => $addr['full_name'], 'phone' => $addr['phone'], 'line1' => $addr['line1'], 'line2' => (string) $addr['line2'], 'city' => $addr['city'], 'province' => (string) $addr['province'], 'postal_code' => (string) $addr['postal_code']]);
            }
            return $this->render($values, []);
        }
        if ($r->str('intent') === 'update') {
            return $this->render($values, []);
        }

        RateLimiter::hit('checkout:' . $r->ip(), 20, 600);
        $v = Validator::make($r->post, [
            'email' => 'required|email|max:191',
        ] + Region::addressRules() + [
            'note' => 'string|max:1000',
            'shipping_rate_id' => 'required|int|min:1',
            'payment_method' => 'required|string|max:40|regex:/^[a-z0-9_]+$/',
            'idempotency_key' => 'required|regex:/^[a-f0-9]{64}$/',
            'fingerprint' => 'required|regex:/^[a-f0-9]{64}$/',
        ]);
        if ($v->fails()) {
            Session::flashNow('error', 'Please correct the highlighted fields.');
            return $this->render($values, $v->errors(), 422);
        }
        $data = $v->validated();
        try {
            $result = Checkout::place($data + ['billing_same' => true, 'save_address' => $r->bool('save_address')]);
        } catch (ValidationException $e) {
            $errors = $e->errors;
            if (isset($errors['checkout']) && str_contains((string) $errors['checkout'], 'changed since you started checkout')) {
                // Shown with the old and new totals by the view; the customer just reviews and presses Place order again.
                $errors['total_changed'] = max(0, $r->int('expected_total'));
                unset($errors['checkout']);
            } elseif (!isset($errors['checkout'])) {
                Session::flashNow('error', $e->getMessage()); // field errors: point to the highlighted fields
            }
            return $this->render($values, $errors, 409);
        }

        $order = $result['order'];
        $placed = (array) Session::get('placed_orders', []);
        $placed[] = (int) $order['id'];
        Session::put('placed_orders', array_slice(array_unique($placed), -20));
        Mailer::flush();

        if (($result['next']['type'] ?? '') === 'redirect') {
            return Response::redirect($result['next']['url']);
        }
        return Response::redirect('/checkout/complete/' . $order['order_number']);
    }

    /** Order access: the owner, or the browser session that placed it. */
    private function authorizedOrder(string $number): array
    {
        $o = Orders::findByNumber($number);
        if (!$o) {
            throw new HttpException(404);
        }
        $mine = $o['user_id'] !== null && (int) $o['user_id'] === Auth::id();
        $session = in_array((int) $o['id'], array_map('intval', (array) Session::get('placed_orders', [])), true);
        if (!$mine && !$session) {
            throw new HttpException(404);
        }
        return $o;
    }

    public function complete(Request $r): Response
    {
        $o = $this->authorizedOrder($r->params['number']);
        // Reconcile online payments with the provider when the customer returns.
        $payment = Database::get()->one('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$o['id']]);
        if ($payment && $payment['status'] === 'pending' && ($p = Registry::get($payment['provider'])) && $p->reservationExpires()) {
            Payments::confirmFromProvider($payment, 'return');
            Mailer::flush();
        }
        $detail = Orders::detail((int) $o['id']);
        return $this->view('store/complete', [
            'o' => $detail,
            'payment' => end($detail['payments']) ?: null,
            'bank' => Settings::get('payments.bank_transfer'),
            'provider' => Registry::get($o['payment_method']),
        ], ['title' => 'Order ' . $o['order_number'], 'noindex' => true]);
    }

    public function bankReference(Request $r): Response
    {
        $o = $this->authorizedOrder($r->params['number']);
        RateLimiter::hit('bankref:' . $o['id'], 10, 3600);
        $data = Validator::make($r->post, ['reference' => 'required|string|min:4|max:120|regex:/^[A-Za-z0-9 \-\/#.]+$/'])->validated();
        $receipt = null;
        if ($file = $r->file('receipt')) {
            $receipt = Uploads::storeReceipt($file);
        }
        Orders::submitBankReference((int) $o['id'], $data['reference'], $receipt);
        Mailer::flush();
        $back = $o['user_id'] && (int) $o['user_id'] === Auth::id() ? '/account/orders/' . $o['order_number'] : '/checkout/complete/' . $o['order_number'];
        return $this->redirect($back, 'Thank you — we will verify your payment on the blockchain and confirm your order shortly.');
    }
}
