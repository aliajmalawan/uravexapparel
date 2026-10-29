<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Orders;

/** Guest order lookup by order number + email (rate limited, no enumeration). */
final class TrackController extends Controller
{
    public function form(Request $r): Response
    {
        return $this->view('store/track', ['order' => null], ['title' => 'Track your order', 'canonical' => url('/track-order')]);
    }

    public function lookup(Request $r): Response
    {
        RateLimiter::hit('track:' . $r->ip(), 10, 600);
        $data = Validator::make($r->post, [
            'order_number' => 'required|string|max:20|regex:/^UVX-\d{6,}$/i',
            'email' => 'required|email',
        ])->validated();
        $o = Database::get()->one('SELECT id FROM orders WHERE order_number = ? AND email = ?', [strtoupper($data['order_number']), mb_strtolower($data['email'])]);
        if (!$o) {
            Session::flashNow('error', 'We could not find an order with those details.');
            return $this->view('store/track', ['order' => null], ['title' => 'Track your order', 'noindex' => true]);
        }
        $detail = Orders::detail((int) $o['id']);
        return $this->view('store/track', ['order' => $detail], ['title' => 'Track your order', 'noindex' => true]);
    }
}
