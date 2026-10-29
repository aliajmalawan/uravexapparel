<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Uploads;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\Audit;
use App\Services\Settings;
use App\Services\SettingsSchema;

final class ContentController extends AdminController
{
    public function index(Request $r): Response
    {
        return $this->admin('content/index', ['s' => Settings::all()], 'Site content');
    }

    public function saveSettings(Request $r): Response
    {
        $group = $r->params['group'];
        if (!in_array($group, SettingsSchema::CONTENT_GROUPS, true)) {
            throw new HttpException(404);
        }
        [$key, $value] = SettingsSchema::build($group, $r->post);
        if ($group === 'home' && ($f = $r->file('story_image'))) {
            $value['story_image'] = Uploads::storeImage($f, 'content')['path'];
        }
        if ($group === 'seo' && ($f = $r->file('og_image'))) {
            $value['og_image'] = Uploads::storeImage($f, 'content')['path'];
        }
        if ($group === 'branding') {
            foreach (['emblem', 'logo'] as $k) {
                if ($r->bool('reset_' . $k)) {
                    $value[$k] = '';
                } elseif ($f = $r->file($k)) {
                    $value[$k] = Uploads::storeImage($f, 'branding')['path'];
                }
            }
        }
        $before = Settings::get($key, []);
        Settings::put($key, $value, $this->actorId());
        [$b, $a] = Audit::diff(is_array($before) ? $before : [], $value);
        Audit::log('settings.' . $group, 'settings', $key, $b, $a);
        $back = match ($group) {
            'home', 'sections' => '/admin/content/home',
            'navigation' => '/admin/content/navigation',
            'branding' => '/admin/content/branding',
            default => '/admin/content',
        };
        return $this->redirect($back, 'Saved — changes are live on the storefront.');
    }

    public function navigation(Request $r): Response
    {
        return $this->admin('content/navigation', ['nav' => Settings::get('navigation')], 'Navigation menus');
    }

    public function branding(Request $r): Response
    {
        return $this->admin('content/branding', ['b' => Settings::get('branding')], 'Logo & branding');
    }

    public function home(Request $r): Response
    {
        $db = Database::get();
        return $this->admin('content/home', [
            'home' => Settings::get('home'),
            'sec' => Settings::get('sections'),
            'featured' => $db->all("SELECT id, title, featured_order, status FROM products WHERE featured = 1 ORDER BY featured_order, title"),
            'candidates' => $db->all("SELECT id, title FROM products WHERE featured = 0 AND status = 'active' ORDER BY title"),
            'collections' => $db->all('SELECT id, name, featured_on_home, home_order, status FROM collections ORDER BY featured_on_home DESC, home_order, name'),
        ], 'Homepage');
    }

    public function saveFeatured(Request $r): Response
    {
        $db = Database::get();
        $db->transaction(static function (Database $db) use ($r): void {
            foreach ($r->arr('product_order') as $id => $pos) {
                $db->exec('UPDATE products SET featured_order = ? WHERE id = ?', [(int) $pos, (int) $id]);
            }
            foreach ($r->arr('unfeature') as $id) {
                $db->exec('UPDATE products SET featured = 0 WHERE id = ?', [(int) $id]);
            }
            foreach ($r->arr('feature') as $id) {
                $db->exec('UPDATE products SET featured = 1, featured_order = 99 WHERE id = ?', [(int) $id]);
            }
            $home = array_map('intval', $r->arr('collection_home'));
            foreach ($r->arr('collection_order') as $id => $pos) {
                $db->exec('UPDATE collections SET home_order = ?, featured_on_home = ? WHERE id = ?', [(int) $pos, in_array((int) $id, $home, true), (int) $id]);
            }
        });
        Audit::log('content.homepage_features', 'settings', 'home');
        return $this->redirect('/admin/content/home', 'Homepage features updated.');
    }

    // ── Banners ──
    public function banners(Request $r): Response
    {
        $db = Database::get();
        return $this->admin('content/banners', [
            'rows' => $db->all('SELECT * FROM banners ORDER BY placement, sort_order, id'),
            'edit' => isset($r->query['edit']) ? $db->one('SELECT * FROM banners WHERE id = ?', [(int) $r->query['edit']]) : null,
        ], 'Banners');
    }

    public function saveBanner(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'id' => 'int', 'placement' => 'required|in:hero,promo', 'eyebrow' => 'string|max:80', 'title' => 'required|string|max:160',
            'subtitle' => 'string|max:320', 'image_alt' => 'string|max:191', 'cta_label' => 'string|max:60', 'cta_href' => 'link|max:255',
            'secondary_cta_label' => 'string|max:60', 'secondary_cta_href' => 'link|max:255', 'sort_order' => 'int|min:0|max:999',
            'is_active' => 'bool', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
        ])->validated();
        $image = ($f = $r->file('image')) ? Uploads::storeImage($f, 'banners')['path'] : null;
        $vals = [$d['placement'], $d['eyebrow'], $d['title'], $d['subtitle'], $d['image_alt'] ?? '', $d['cta_label'], $d['cta_href'], $d['secondary_cta_label'], $d['secondary_cta_href'], $d['sort_order'] ?? 0, $d['is_active'], local_to_utc($d['starts_at']), local_to_utc($d['ends_at'])];
        $db = Database::get();
        if ($d['id']) {
            $db->exec('UPDATE banners SET placement=?, eyebrow=?, title=?, subtitle=?, image_alt=?, cta_label=?, cta_href=?, secondary_cta_label=?, secondary_cta_href=?, sort_order=?, is_active=?, starts_at=?, ends_at=?, image_path = COALESCE(?, image_path) WHERE id = ?', [...$vals, $image, $d['id']]);
            $id = $d['id'];
        } else {
            $id = $db->insert('INSERT INTO banners (placement, eyebrow, title, subtitle, image_alt, cta_label, cta_href, secondary_cta_label, secondary_cta_href, sort_order, is_active, starts_at, ends_at, image_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$vals, $image]);
        }
        Audit::log($d['id'] ? 'banner.update' : 'banner.create', 'banner', $id, null, ['title' => $d['title'], 'active' => $d['is_active']]);
        return $this->redirect('/admin/content/banners', 'Banner saved.');
    }

    public function deleteBanner(Request $r): Response
    {
        $db = Database::get();
        $b = $db->one('SELECT * FROM banners WHERE id = ?', [(int) $r->params['id']]);
        if (!$b) {
            throw new HttpException(404);
        }
        $db->exec('DELETE FROM banners WHERE id = ?', [$b['id']]);
        if ($b['image_path']) {
            Uploads::deleteImage($b['image_path']);
        }
        Audit::log('banner.delete', 'banner', $b['id'], ['title' => $b['title']], null);
        return $this->redirect('/admin/content/banners', 'Banner deleted.');
    }

    // ── Pages ──
    public function pages(Request $r): Response
    {
        return $this->admin('content/pages', ['rows' => Database::get()->all('SELECT id, slug, title, status, updated_at FROM content_pages ORDER BY title')], 'Pages & policies');
    }

    public function editPage(Request $r): Response
    {
        $page = isset($r->params['id']) ? Database::get()->one('SELECT * FROM content_pages WHERE id = ?', [(int) $r->params['id']]) : null;
        if (isset($r->params['id']) && !$page) {
            throw new HttpException(404);
        }
        return $this->admin('content/page-form', ['page' => $page], $page ? 'Edit: ' . $page['title'] : 'New page');
    }

    public function savePage(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'id' => 'int', 'title' => 'required|string|max:191', 'slug' => 'required|slug|max:120', 'body' => 'required|string|max:100000',
            'seo_title' => 'string|max:191', 'seo_description' => 'string|max:320', 'status' => 'required|in:draft,active',
        ])->validated();
        $db = Database::get();
        if ($db->value('SELECT id FROM content_pages WHERE slug = ? AND id <> ?', [$d['slug'], $d['id'] ?? 0])) {
            throw ValidationException::one('slug', 'Another page uses this slug.');
        }
        $body = Sanitizer::html($d['body']);
        $vals = [$d['title'], $d['slug'], $body, $d['seo_title'], $d['seo_description'], $d['status'], $this->actorId()];
        if ($d['id']) {
            $db->exec('UPDATE content_pages SET title=?, slug=?, body=?, seo_title=?, seo_description=?, status=?, updated_by=? WHERE id = ?', [...$vals, $d['id']]);
            $id = $d['id'];
        } else {
            $id = $db->insert('INSERT INTO content_pages (title, slug, body, seo_title, seo_description, status, updated_by) VALUES (?,?,?,?,?,?,?)', $vals);
        }
        Audit::log('page.save', 'page', $id, null, ['slug' => $d['slug'], 'status' => $d['status']]);
        return $this->redirect('/admin/content/pages/' . $id, 'Page saved' . ($d['status'] === 'active' ? ' and published.' : ' as draft.'));
    }

    public function deletePage(Request $r): Response
    {
        $db = Database::get();
        $page = $db->one('SELECT id, slug, title FROM content_pages WHERE id = ?', [(int) $r->params['id']]);
        if (!$page) {
            throw new HttpException(404);
        }
        $db->exec('DELETE FROM content_pages WHERE id = ?', [$page['id']]);
        Audit::log('page.delete', 'page', $page['id'], $page, null);
        return $this->redirect('/admin/content/pages', 'Page “' . $page['title'] . '” deleted.');
    }

    // ── FAQs ──
    public function faqs(Request $r): Response
    {
        return $this->admin('content/faqs', ['rows' => Database::get()->all('SELECT * FROM faqs ORDER BY sort_order, id')], 'FAQs');
    }

    public function saveFaq(Request $r): Response
    {
        $d = Validator::make($r->post, ['id' => 'int', 'question' => 'required|string|max:255', 'answer' => 'required|string|max:3000', 'sort_order' => 'int|min:0|max:999', 'is_active' => 'bool'])->validated();
        $db = Database::get();
        if ($d['id']) {
            $db->exec('UPDATE faqs SET question=?, answer=?, sort_order=?, is_active=? WHERE id = ?', [$d['question'], $d['answer'], $d['sort_order'] ?? 0, $d['is_active'], $d['id']]);
        } else {
            $d['id'] = $db->insert('INSERT INTO faqs (question, answer, sort_order, is_active) VALUES (?,?,?,?)', [$d['question'], $d['answer'], $d['sort_order'] ?? 0, $d['is_active']]);
        }
        Audit::log('faq.save', 'faq', $d['id']);
        return $this->redirect('/admin/content/faqs', 'FAQ saved.');
    }

    public function deleteFaq(Request $r): Response
    {
        Database::get()->exec('DELETE FROM faqs WHERE id = ?', [(int) $r->params['id']]);
        Audit::log('faq.delete', 'faq', (int) $r->params['id']);
        return $this->redirect('/admin/content/faqs', 'FAQ deleted.');
    }
}
