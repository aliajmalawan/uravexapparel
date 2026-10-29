<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Auth\Auth;
use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Wishlist;

final class WishlistController extends Controller
{
    public function toggle(Request $r): Response
    {
        $productId = $r->int('product_id');
        if (!Auth::check()) {
            // Remember the intent and add it right after sign-in.
            Session::put('pending_wishlist', $productId);
            Session::put('intended', '/account/wishlist');
            if ($r->wantsJson()) {
                return Response::json(['ok' => false, 'login' => url('/login'), 'message' => 'Sign in to save items to your wishlist.'], 401);
            }
            return $this->redirect('/login', null, 'Sign in to save items to your wishlist.');
        }
        $saved = Wishlist::toggle((int) Auth::id(), $productId);
        $msg = $saved ? 'Saved to your wishlist.' : 'Removed from your wishlist.';
        if ($r->wantsJson()) {
            return Response::json(['ok' => true, 'saved' => $saved, 'message' => $msg]);
        }
        Session::flash('success', $msg);
        return $this->back('/account/wishlist');
    }

    public function redirect(Request $r): Response
    {
        return Response::redirect('/account/wishlist', 301);
    }
}
