<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Audit;

final class MessageController extends AdminController
{
    public function index(Request $r): Response
    {
        $db = Database::get();
        $pg = $this->page($r->query, 30);
        $showAll = ($r->query['all'] ?? '') === '1';
        $where = $showAll ? '1=1' : 'handled = 0';
        $total = (int) $db->value("SELECT COUNT(*) FROM contact_messages WHERE {$where}");
        return $this->admin('messages/index', [
            'rows' => $db->all("SELECT * FROM contact_messages WHERE {$where} ORDER BY created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}"),
            'page' => $pg['page'], 'pages' => max(1, (int) ceil($total / $pg['per'])), 'showAll' => $showAll,
            'subscribers' => (int) $db->value("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'subscribed'"),
        ], 'Messages');
    }

    public function handled(Request $r): Response
    {
        Database::get()->exec('UPDATE contact_messages SET handled = 1 - handled WHERE id = ?', [(int) $r->params['id']]);
        return $this->back('/admin/messages');
    }

    public function exportNewsletter(Request $r): Response
    {
        $rows = Database::get()->all("SELECT email, source, consent_at FROM newsletter_subscribers WHERE status = 'subscribed' ORDER BY consent_at DESC");
        Audit::log('export.newsletter', 'export', null, null, ['rows' => count($rows)]);
        return $this->csv('newsletter-' . gmdate('Ymd') . '.csv', ['email', 'source', 'consented_at_utc'], $rows);
    }
}
