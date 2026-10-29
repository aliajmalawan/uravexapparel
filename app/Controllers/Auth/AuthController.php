<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Auth\Auth;
use App\Auth\Tokens;
use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Mail\Mailer;
use App\Services\Cart;
use App\Services\Wishlist;

final class AuthController extends Controller
{
    private const LOGIN_MAX_PER_EMAIL = 5;   // per 15 minutes
    private const LOGIN_MAX_PER_IP = 20;     // per 15 minutes
    private const WINDOW = 900;

    /**
     * Separate sign-in pages: customers (/login), sellers (/seller/login) and
     * staff (/admin/login). Each only admits the accounts it is meant for.
     */
    public const PORTALS = [
        'customer' => ['path' => '/login', 'home' => '/account', 'title' => 'Sign in'],
        'seller' => ['path' => '/seller/login', 'home' => '/seller', 'title' => 'Seller sign in'],
        'admin' => ['path' => '/admin/login', 'home' => '/admin', 'title' => 'Admin sign in'],
    ];

    /** Whether a user may use a portal ('' = yes, otherwise the reason). */
    private static function portalRefusal(string $portal, array $user): string
    {
        $isStaff = \App\Auth\Permissions::isStaff((string) $user['role']);
        return match ($portal) {
            'admin' => $isStaff ? '' : 'This account does not have admin access.',
            'seller' => \App\Services\Marketplace::sellerForUser((int) $user['id']) ? '' : 'No seller shop is linked to this account yet.',
            default => $isStaff ? 'Staff accounts sign in on the admin login page.' : '',
        };
    }

    private function portalForm(string $portal): Response
    {
        $u = Auth::user();
        if ($u && self::portalRefusal($portal, $u) === '') {
            return $this->redirect(self::PORTALS[$portal]['home']);
        }
        $p = self::PORTALS[$portal];
        return $this->view('auth/login', ['portal' => $portal, 'p' => $p, 'title' => $p['title']], ['title' => $p['title'], 'noindex' => true], $portal === 'admin' ? 'layouts/minimal' : 'layouts/store');
    }

    public function loginForm(Request $r): Response
    {
        if (Auth::check()) {
            return $this->redirect(Auth::isStaff() ? '/admin' : '/account');
        }
        return $this->portalForm('customer');
    }

    public function sellerLoginForm(Request $r): Response
    {
        return $this->portalForm('seller');
    }

    public function adminLoginForm(Request $r): Response
    {
        return $this->portalForm('admin');
    }

    public function sellerLogin(Request $r): Response
    {
        return $this->attemptLogin($r, 'seller');
    }

    public function adminLogin(Request $r): Response
    {
        return $this->attemptLogin($r, 'admin');
    }

    public function login(Request $r): Response
    {
        return $this->attemptLogin($r, 'customer');
    }

    private function attemptLogin(Request $r, string $portal): Response
    {
        $email = mb_strtolower($r->str('email'));
        $ipKey = 'login-ip:' . $r->ip();
        $emailKey = 'login-email:' . $email;
        if (RateLimiter::tooMany($emailKey, self::LOGIN_MAX_PER_EMAIL, self::WINDOW) || RateLimiter::tooMany($ipKey, self::LOGIN_MAX_PER_IP, self::WINDOW)) {
            throw ValidationException::one('email', 'Too many sign-in attempts. Please wait 15 minutes or reset your password.');
        }
        $data = Validator::make($r->post, ['email' => 'required|email', 'password' => 'required|max:128'])->validated();

        $user = Database::get()->one('SELECT * FROM users WHERE email = ?', [mb_strtolower($data['email'])]);
        $ok = $user && Auth::verifyPassword($data['password'], $user['password_hash']);
        if (!$user) {
            Auth::dummyVerify($data['password']);
        }
        if (!$ok || $user['disabled']) {
            RateLimiter::attempt($emailKey, PHP_INT_MAX, self::WINDOW);
            RateLimiter::attempt($ipKey, PHP_INT_MAX, self::WINDOW);
            Logger::info('Failed login', ['email' => $data['email'], 'ip' => $r->ip()]);
            throw ValidationException::one('email', 'The email or password is incorrect.');
        }
        RateLimiter::clear($emailKey, self::WINDOW);
        if ($refusal = self::portalRefusal($portal, $user)) {
            Logger::info('Login refused for portal', ['portal' => $portal, 'user' => (int) $user['id']]);
            throw ValidationException::one('email', $refusal);
        }

        // Opportunistic rehash when the algorithm/cost changes.
        if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            Database::get()->exec('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hashPassword($data['password']), $user['id']]);
        }
        Auth::login($user);
        $notices = Cart::mergeGuestInto((int) $user['id']);
        if ($notices) {
            Session::flash('notices', $notices);
        }
        $this->applyPendingWishlist((int) $user['id']);

        // Return to the page that asked for sign-in, but only within this portal's area.
        $home = self::PORTALS[$portal]['home'];
        $intended = (string) Session::pull('intended');
        $inArea = match ($portal) {
            'admin' => str_starts_with($intended, '/admin'),
            'seller' => str_starts_with($intended, '/seller'),
            default => $intended !== '' && !str_starts_with($intended, '/admin') && !str_starts_with($intended, '/seller/'),
        };
        $next = $this->safeNext($inArea ? $intended : null, $home);
        return $this->redirect($next, 'Welcome back, ' . explode(' ', $user['name'])[0] . '.');
    }

    private function applyPendingWishlist(int $userId): void
    {
        $pid = Session::pull('pending_wishlist');
        if (is_int($pid) && $pid > 0) {
            try {
                if (!in_array($pid, Wishlist::ids(), true)) {
                    Wishlist::toggle($userId, $pid);
                }
            } catch (\Throwable) {
            }
        }
    }

    public function registerForm(Request $r): Response
    {
        return $this->view('auth/register', [], ['title' => 'Create account', 'noindex' => true]);
    }

    public function register(Request $r): Response
    {
        RateLimiter::hit('register:' . $r->ip(), 5, 3600);
        if ($r->str('website') !== '') {
            throw ValidationException::one('email', 'Registration failed. Please try again.');
        }
        $data = Validator::make($r->post, [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:191',
            'phone' => 'required|phone|max:32',
            'password' => 'required|min:8|max:128|password|confirmed',
        ])->validated();
        $email = mb_strtolower($data['email']);
        $db = Database::get();
        if ($db->value('SELECT id FROM users WHERE email = ?', [$email])) {
            throw ValidationException::one('email', 'An account with this email already exists. Sign in or reset your password.');
        }
        try {
            $id = $db->insert(
                'INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)',
                [$data['name'], $email, $data['phone'], Auth::hashPassword($data['password'])]
            );
        } catch (\mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) { // unique constraint: concurrent duplicate
                throw ValidationException::one('email', 'An account with this email already exists. Sign in or reset your password.');
            }
            throw $e;
        }
        $user = $db->one('SELECT * FROM users WHERE id = ?', [$id]);
        $this->sendVerification($user);
        Auth::login($user);
        $notices = Cart::mergeGuestInto($id);
        if ($notices) {
            Session::flash('notices', $notices);
        }
        $this->applyPendingWishlist($id);
        $next = $this->safeNext(Session::pull('intended'), '/account');
        return $this->redirect($next, 'Welcome to URAVEX! We sent a verification link to ' . $email . '.');
    }

    /**
     * One-step seller sign-up: creates the account and the shop application
     * together (one transaction — a taken shop name leaves no half-made account),
     * signs the new seller in and opens the Seller panel.
     */
    public function sellerRegister(Request $r): Response
    {
        if (Auth::check()) {
            return $this->redirect('/sell');
        }
        RateLimiter::hit('register:' . $r->ip(), 5, 3600);
        if ($r->str('website') !== '') {
            throw ValidationException::one('email', 'Registration failed. Please try again.');
        }
        $data = Validator::make($r->post, [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:191',
            'phone' => 'required|phone|max:32',
            'password' => 'required|min:8|max:128|password|confirmed',
            'shop_name' => 'required|string|min:3|max:80',
            'description' => 'required|string|min:20|max:2000',
            'payout_address' => 'string|max:60',
            'agree' => 'bool',
        ])->validated();
        if (!$data['agree']) {
            throw ValidationException::one('agree', 'Please accept the seller terms.');
        }
        $email = mb_strtolower($data['email']);
        $db = Database::get();
        if ($db->value('SELECT id FROM users WHERE email = ?', [$email])) {
            throw ValidationException::one('email', 'An account with this email already exists. Sign in first, then apply from the Sell page.');
        }
        $id = $db->transaction(static function (Database $db) use ($data, $email): int {
            $id = $db->insert(
                'INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)',
                [$data['name'], $email, $data['phone'], Auth::hashPassword($data['password'])]
            );
            \App\Services\Marketplace::apply($id, [
                'shop_name' => $data['shop_name'], 'description' => $data['description'],
                'contact_phone' => $data['phone'], 'payout_address' => (string) $data['payout_address'],
            ]);
            return $id;
        });
        $user = $db->one('SELECT * FROM users WHERE id = ?', [$id]);
        $this->sendVerification($user);
        Auth::login($user);
        Session::forget('intended');
        return $this->redirect('/seller', 'Welcome! Your seller account is created and your shop is waiting for approval. We sent a verification link to ' . $email . '.');
    }

    private function sendVerification(array $user): void
    {
        $token = Tokens::issue((int) $user['id'], 'verify_email');
        Mailer::sendNow($user['email'], 'verify_email', ['name' => $user['name'], 'url' => url('/verify-email', ['token' => $token])]);
    }

    public function verify(Request $r): Response
    {
        $token = (string) ($r->query['token'] ?? '');
        $uid = Tokens::consume($token, 'verify_email');
        if ($uid === null) {
            return $this->view('store/message', [
                'heading' => 'This link has expired',
                'body' => 'Verification links are valid for 24 hours and can be used once. Sign in to request a new one.',
                'cta_href' => Auth::check() ? '/account' : '/login', 'cta_label' => Auth::check() ? 'Go to my account' : 'Sign in',
            ], ['title' => 'Verify email', 'noindex' => true], 'layouts/store', 410);
        }
        Database::get()->exec('UPDATE users SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?', [$uid]);
        return $this->redirect(Auth::check() ? '/account' : '/login', 'Your email address is verified.');
    }

    public function resendVerification(Request $r): Response
    {
        RateLimiter::hit('verify-resend:' . Auth::id(), 3, 3600);
        $u = Auth::user();
        if ($u['email_verified_at']) {
            return $this->redirect('/account', 'Your email is already verified.');
        }
        $this->sendVerification(Database::get()->one('SELECT * FROM users WHERE id = ?', [$u['id']]));
        return $this->redirect('/account', 'A new verification link is on its way.');
    }

    public function forgotForm(Request $r): Response
    {
        return $this->view('auth/forgot', [], ['title' => 'Reset password', 'noindex' => true]);
    }

    public function forgot(Request $r): Response
    {
        RateLimiter::hit('forgot-ip:' . $r->ip(), 5, 900);
        $data = Validator::make($r->post, ['email' => 'required|email'])->validated();
        $email = mb_strtolower($data['email']);
        // Per-email throttle, silently enforced so responses stay identical.
        if (RateLimiter::attempt('forgot-email:' . $email, 3, 3600)) {
            $user = Database::get()->one('SELECT * FROM users WHERE email = ? AND disabled = 0', [$email]);
            if ($user) {
                $token = Tokens::issue((int) $user['id'], 'reset_password');
                Mailer::sendNow($user['email'], 'reset_password', ['name' => $user['name'], 'url' => url('/reset-password', ['token' => $token]), 'minutes' => intdiv(Tokens::RESET_TTL, 60)]);
            }
        }
        // Identical response for existing and unknown emails (no account enumeration).
        return $this->redirect('/forgot-password', 'If an account exists for that email, a reset link has been sent. It expires in 60 minutes.');
    }

    public function resetForm(Request $r): Response
    {
        $token = (string) ($r->query['token'] ?? '');
        $valid = Tokens::peek($token, 'reset_password') !== null;
        return $this->view('auth/reset', ['token' => $valid ? $token : '', 'valid' => $valid], ['title' => 'Choose a new password', 'noindex' => true]);
    }

    public function reset(Request $r): Response
    {
        RateLimiter::hit('reset:' . $r->ip(), 10, 900);
        $data = Validator::make($r->post, [
            'token' => 'required|regex:/^[A-Za-z0-9_-]{40,64}$/',
            'password' => 'required|min:8|max:128|password|confirmed',
        ])->validated();
        $uid = Tokens::consume($data['token'], 'reset_password');
        if ($uid === null) {
            throw ValidationException::one('password', 'This reset link has expired or was already used. Request a new one.');
        }
        $db = Database::get();
        // New hash + session_version bump signs out every existing session.
        $db->exec(
            'UPDATE users SET password_hash = ?, session_version = session_version + 1, email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?',
            [Auth::hashPassword($data['password']), $uid]
        );
        $user = $db->one('SELECT * FROM users WHERE id = ?', [$uid]);
        Mailer::sendNow($user['email'], 'password_changed', ['name' => $user['name']]);
        Auth::logout();
        return $this->redirect('/login', 'Your password has been changed. Please sign in.');
    }

    public function logout(Request $r): Response
    {
        Auth::logout();
        // Staff and sellers go back to their own sign-in page.
        $to = match ($r->str('portal')) { 'admin' => '/admin/login', 'seller' => '/seller/login', default => '/' };
        return $this->redirect($to, 'You have been signed out.');
    }
}
