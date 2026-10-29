<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\Cart;
use App\Services\Coupons;
use App\Services\Shipping;

final class CartController extends Controller
{
    public function show(Request $r): Response
    {
        $estimate = Shipping::estimate();
        $freeOver = $estimate ? Shipping::freeOver($estimate) : null;
        $summary = Cart::summary($estimate ? (int) $estimate['price_minor'] : null, $freeOver);
        if ($summary['coupon_error']) {
            Session::flashNow('warning', $summary['coupon_error']);
        }
        return $this->view('store/cart', [
            's' => $summary,
            'estimate' => $estimate,
            'freeOver' => $freeOver,
        ], ['title' => 'Your bag', 'noindex' => true]);
    }

    public function add(Request $r): Response
    {
        RateLimiter::hit('cart:' . $r->ip(), 120, 60);
        $result = Cart::add($r->int('variant_id'), max(1, $r->int('quantity', 1)));
        $msg = $result['limited']
            ? "Only {$result['stock']} available — your bag now has {$result['quantity']}."
            : "{$result['title']} added to your bag.";
        if ($r->wantsJson()) {
            return Response::json(['ok' => true, 'message' => $msg, 'count' => Cart::count()]);
        }
        return $this->redirect('/cart', $msg);
    }

    public function update(Request $r): Response
    {
        $msg = Cart::update($r->int('item_id'), $r->int('quantity'));
        return $this->redirect('/cart', $msg);
    }

    public function remove(Request $r): Response
    {
        Cart::remove($r->int('item_id'));
        return $this->redirect('/cart', 'Item removed from your bag.');
    }

    public function applyCoupon(Request $r): Response
    {
        RateLimiter::hit('coupon:' . $r->ip(), 15, 600);
        $code = Coupons::normalize($r->str('code'));
        $coupon = Coupons::find($code);
        if (!$coupon) {
            throw ValidationException::one('coupon', 'That code is not valid.');
        }
        $summary = Cart::summary();
        if (!$summary['pricing_lines']) {
            throw ValidationException::one('coupon', 'Add items to your bag before applying a code.');
        }
        Coupons::evaluate($coupon, $summary['pricing_lines'], \App\Auth\Auth::id(), \App\Auth\Auth::user()['email'] ?? null);
        Cart::setCoupon($coupon['code']);
        return $this->redirect('/cart', "Code {$coupon['code']} applied.");
    }

    public function removeCoupon(Request $r): Response
    {
        Cart::setCoupon(null);
        return $this->redirect('/cart', 'Discount code removed.');
    }

    public function count(Request $r): Response
    {
        return Response::json(['count' => Cart::count()]);
    }
}
