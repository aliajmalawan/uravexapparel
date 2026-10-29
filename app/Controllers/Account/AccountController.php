<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Auth\Auth;
use App\Auth\Tokens;
use App\Controllers\Controller;
use App\Controllers\Store\CheckoutController;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Mail\Mailer;
use App\Payments\Registry;
use App\Services\Orders;
use App\Services\Settings;
use App\Services\Wishlist;

/**
 * Customer account. Every query is scoped to Auth::id() — a customer can never
 * read or modify another customer's data by changing an id or order number.
 */
final class AccountController extends Controller
{
    private function uid(): int
    {
        return (int) Auth::id();
    }

    public function dashboard(Request $r): Response
    {
        $db = Database::get();
        return $this->view('account/dashboard', [
            'recent' => $db->all('SELECT * FROM orders WHERE user_id = ? ORDER BY placed_at DESC LIMIT 3', [$this->uid()]),
            'orderCount' => (int) $db->value('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$this->uid()]),
            'wishCount' => count(Wishlist::ids()),
            'address' => $db->one('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1', [$this->uid()]),
        ], ['title' => 'My account', 'noindex' => true]);
    }

    public function profile(Request $r): Response
    {
        return $this->view('account/profile', [], ['title' => 'Profile', 'noindex' => true]);
    }

    public function updateProfile(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:191',
            'phone' => 'required|phone|max:32',
            'current_password' => 'max:128',
        ])->validated();
        $db = Database::get();
        $u = $db->one('SELECT * FROM users WHERE id = ?', [$this->uid()]);
        $email = mb_strtolower($d['email']);
        $emailChanged = $email !== $u['email'];
        if ($emailChanged) {
            if (!$d['current_password'] || !Auth::verifyPassword($d['current_password'], $u['password_hash'])) {
                throw ValidationException::one('current_password', 'Enter your current password to change your email.');
            }
            if ($db->value('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) {
                throw ValidationException::one('email', 'That email is already used by another account.');
            }
        }
        $db->exec(
            'UPDATE users SET name = ?, phone = ?, email = ?, email_verified_at = IF(?, NULL, email_verified_at) WHERE id = ?',
            [$d['name'], $d['phone'], $email, $emailChanged, $u['id']]
        );
        if ($emailChanged) {
            $token = Tokens::issue((int) $u['id'], 'verify_email');
            Mailer::sendNow($email, 'verify_email', ['name' => $d['name'], 'url' => url('/verify-email', ['token' => $token])]);
            Mailer::sendNow($u['email'], 'email_changed', ['name' => $d['name'], 'newEmail' => $email]);
        }
        Auth::reset();
        return $this->redirect('/account/profile', $emailChanged ? 'Profile saved. Please verify your new email address.' : 'Profile saved.');
    }

    public function updatePassword(Request $r): Response
    {
        RateLimiter::hit('pwchange:' . $this->uid(), 5, 900);
        $d = Validator::make($r->post, [
            'current_password' => 'required|max:128',
            'password' => 'required|min:8|max:128|password|confirmed',
        ])->validated();
        $db = Database::get();
        $u = $db->one('SELECT * FROM users WHERE id = ?', [$this->uid()]);
        if (!Auth::verifyPassword($d['current_password'], $u['password_hash'])) {
            throw ValidationException::one('current_password', 'Your current password is incorrect.');
        }
        $db->exec('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [Auth::hashPassword($d['password']), $u['id']]);
        // Other sessions are now invalid; keep this one signed in with the new version.
        Auth::login($db->one('SELECT * FROM users WHERE id = ?', [$u['id']]));
        Mailer::sendNow($u['email'], 'password_changed', ['name' => $u['name']]);
        return $this->redirect('/account/profile', 'Password updated. Other devices have been signed out.');
    }

    public function addresses(Request $r): Response
    {
        $edit = null;
        if (isset($r->query['edit'])) {
            $edit = Database::get()->one('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [(int) $r->query['edit'], $this->uid()]);
        }
        return $this->view('account/addresses', [
            'addresses' => Database::get()->all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [$this->uid()]),
            'edit' => $edit,
            'states' => \App\Services\Region::STATES,
        ], ['title' => 'Addresses', 'noindex' => true]);
    }

    public function saveAddress(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'id' => 'int',
            'label' => 'string|max:60',
        ] + \App\Services\Region::addressRules() + [
            'is_default' => 'bool',
        ])->validated();
        $db = Database::get();
        $uid = $this->uid();
        $db->transaction(static function (Database $db) use ($d, $uid): void {
            $count = (int) $db->value('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$uid]);
            if (!$d['id'] && $count >= 10) {
                throw ValidationException::one('line1', 'You can save up to 10 addresses.');
            }
            $default = $d['is_default'] || $count === 0;
            if ($default) {
                $db->exec('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$uid]);
            }
            $vals = [$d['label'], $d['full_name'], $d['phone'], $d['line1'], $d['line2'], $d['city'], $d['province'], $d['postal_code']];
            if ($d['id']) {
                $n = $db->exec(
                    'UPDATE addresses SET label = ?, full_name = ?, phone = ?, line1 = ?, line2 = ?, city = ?, province = ?, postal_code = ?, is_default = IF(?, 1, is_default) WHERE id = ? AND user_id = ?',
                    [...$vals, $default, $d['id'], $uid]
                );
                if ($n === 0 && !$db->value('SELECT id FROM addresses WHERE id = ? AND user_id = ?', [$d['id'], $uid])) {
                    throw new HttpException(404);
                }
            } else {
                $db->insert(
                    'INSERT INTO addresses (label, full_name, phone, line1, line2, city, province, postal_code, is_default, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [...$vals, $default, $uid]
                );
            }
        });
        return $this->redirect('/account/addresses', 'Address saved.');
    }

    public function deleteAddress(Request $r): Response
    {
        Database::get()->exec('DELETE FROM addresses WHERE id = ? AND user_id = ?', [(int) $r->params['id'], $this->uid()]);
        return $this->redirect('/account/addresses', 'Address removed.');
    }

    public function defaultAddress(Request $r): Response
    {
        $uid = $this->uid();
        $id = (int) $r->params['id'];
        Database::get()->transaction(static function (Database $db) use ($uid, $id): void {
            if (!$db->value('SELECT id FROM addresses WHERE id = ? AND user_id = ?', [$id, $uid])) {
                throw new HttpException(404);
            }
            $db->exec('UPDATE addresses SET is_default = (id = ?) WHERE user_id = ?', [$id, $uid]);
        });
        return $this->redirect('/account/addresses', 'Default address updated.');
    }

    public function orders(Request $r): Response
    {
        $page = max(1, (int) ($r->query['page'] ?? 1));
        $per = 10;
        $db = Database::get();
        $total = (int) $db->value('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$this->uid()]);
        $orders = $db->all(
            'SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS item_count FROM orders o WHERE o.user_id = ? ORDER BY o.placed_at DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
            [$this->uid()]
        );
        return $this->view('account/orders', ['orders' => $orders, 'page' => $page, 'pages' => max(1, (int) ceil($total / $per))], ['title' => 'Orders', 'noindex' => true]);
    }

    public function order(Request $r): Response
    {
        $o = Database::get()->one('SELECT id FROM orders WHERE order_number = ? AND user_id = ?', [$r->params['number'], $this->uid()]);
        if (!$o) {
            throw new HttpException(404);
        }
        $detail = Orders::detail((int) $o['id']);
        return $this->view('account/order', [
            'o' => $detail,
            'payment' => end($detail['payments']) ?: null,
            'provider' => Registry::get($detail['payment_method']),
            'bank' => Settings::get('payments.bank_transfer'),
        ], ['title' => 'Order ' . $detail['order_number'], 'noindex' => true]);
    }

    public function wishlist(Request $r): Response
    {
        return $this->view('account/wishlist', ['items' => Wishlist::products($this->uid()), 'wishlistIds' => Wishlist::ids()], ['title' => 'Wishlist', 'noindex' => true]);
    }
}
