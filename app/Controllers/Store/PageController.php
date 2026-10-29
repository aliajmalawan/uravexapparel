<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\Content;
use App\Services\Settings;

final class PageController extends Controller
{
    public function about(Request $r): Response
    {
        $page = Content::page('about');
        return $this->view('store/page', ['page' => $page ?? ['title' => 'About URAVEX', 'body' => '<p>' . e(Settings::get('home.story_body')) . '</p>'], 'isAbout' => true], [
            'title' => $page['seo_title'] ?? 'About URAVEX',
            'description' => $page['seo_description'] ?? Settings::get('home.story_body'),
            'canonical' => url('/about'),
        ]);
    }

    public function show(Request $r): Response
    {
        $page = Content::page($r->params['slug']);
        if (!$page) {
            throw new HttpException(404);
        }
        return $this->view('store/page', ['page' => $page, 'isAbout' => false], [
            'title' => $page['seo_title'] ?: $page['title'],
            'description' => $page['seo_description'] ?: strip_tags($page['body']),
            'canonical' => url('/pages/' . $page['slug']),
        ]);
    }

    public function faq(Request $r): Response
    {
        $faqs = Content::faqs();
        $ld = $faqs ? [[
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn ($f) => [
                '@type' => 'Question', 'name' => $f['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['answer'])],
            ], $faqs),
        ]] : [];
        return $this->view('store/faq', ['faqs' => $faqs], ['title' => 'FAQs', 'canonical' => url('/faq'), 'jsonld' => $ld,
            'description' => 'Answers about delivery, payments, exchanges and sizing at URAVEX Apparel.']);
    }

    public function contact(Request $r): Response
    {
        return $this->view('store/contact', ['store' => Settings::get('store')], ['title' => 'Contact us', 'canonical' => url('/contact'),
            'description' => 'Get in touch with URAVEX Apparel customer care.']);
    }

    public function sendContact(Request $r): Response
    {
        RateLimiter::hit('contact:' . $r->ip(), 5, 3600);
        if ($r->str('website') !== '') {
            return $this->redirect('/contact', 'Thanks — your message has been sent.'); // honeypot
        }
        $d = Validator::make($r->post, [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email',
            'phone' => 'phone|max:32',
            'subject' => 'required|string|min:2|max:191',
            'message' => 'required|string|min:10|max:5000',
        ])->validated();
        Database::get()->insert(
            'INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)',
            [$d['name'], mb_strtolower($d['email']), $d['phone'], $d['subject'], $d['message']]
        );
        $notify = (string) Config::get('mail.admin_notify');
        if ($notify !== '') {
            \App\Mail\Mailer::queue($notify, 'contact_message', ['m' => $d]);
            \App\Mail\Mailer::flush();
        }
        return $this->redirect('/contact', 'Thanks — your message has been sent. We usually reply within one working day.');
    }

    public function subscribe(Request $r): Response
    {
        RateLimiter::hit('newsletter:' . $r->ip(), 10, 3600);
        if ($r->str('website') !== '') {
            return $this->back();
        }
        $d = Validator::make($r->post, ['email' => 'required|email'])->validated();
        $email = mb_strtolower($d['email']);
        Database::get()->exec(
            "INSERT INTO newsletter_subscribers (email, source, unsubscribe_token) VALUES (?, 'footer', ?)
             ON DUPLICATE KEY UPDATE status = 'subscribed', unsubscribed_at = NULL",
            [$email, bin2hex(random_bytes(32))]
        );
        // Same response whether or not the address was already subscribed.
        \App\Core\Session::flash('success', 'Thank you for subscribing to the URAVEX list.');
        return $this->back();
    }

    /** Customer currency switcher: remembers the choice in a cookie and returns to the previous page. */
    public function currency(Request $r): Response
    {
        $code = strtoupper(mb_substr($r->str('code'), 0, 3));
        if (in_array($code, \App\Services\Currency::choices(), true) && PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(\App\Services\Currency::COOKIE, $code, [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => (Config::get('base_path') ?: '') . '/',
                'secure' => (bool) Config::get('https'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return $this->back('/');
    }

    public function unsubscribe(Request $r): Response
    {
        $n = Database::get()->exec(
            "UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = UTC_TIMESTAMP() WHERE unsubscribe_token = ?",
            [$r->params['token']]
        );
        return $this->view('store/message', [
            'heading' => $n ? 'You have been unsubscribed' : 'Link not recognised',
            'body' => $n ? 'You will no longer receive marketing emails from URAVEX.' : 'This unsubscribe link is invalid or was already used.',
        ], ['title' => 'Newsletter', 'noindex' => true]);
    }
}
