<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Auth\Permissions;
use App\Auth\Tokens;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Mail\Mailer;
use App\Services\Audit;

/** Owner-only: grant/revoke staff roles, disable accounts, invite staff. */
final class StaffController extends AdminController
{
    public function index(Request $r): Response
    {
        return $this->admin('staff/index', [
            'rows' => Database::get()->all("SELECT id, name, email, role, disabled, last_login_at, created_at FROM users WHERE role <> 'customer' ORDER BY FIELD(role, 'owner','manager','order_staff','content_editor'), name"),
            'roles' => Permissions::ROLES,
        ], 'Staff & roles');
    }

    public function save(Request $r): Response
    {
        Auth::authorize('staff.manage');
        $d = Validator::make($r->post, [
            'email' => 'required|email',
            'name' => 'string|max:120',
            'role' => 'required|in:' . implode(',', array_keys(Permissions::ROLES)),
            'disabled' => 'bool',
        ])->validated();
        $db = Database::get();
        $email = mb_strtolower($d['email']);
        $user = $db->one('SELECT * FROM users WHERE email = ?', [$email]);

        if ($user && (int) $user['id'] === Auth::id() && ($d['role'] !== 'owner' || $d['disabled'])) {
            throw ValidationException::one('role', 'You cannot remove your own owner access or disable yourself.');
        }
        if ($user && $user['role'] === 'owner' && $d['role'] !== 'owner') {
            $owners = (int) $db->value("SELECT COUNT(*) FROM users WHERE role = 'owner' AND disabled = 0");
            if ($owners <= 1) {
                throw ValidationException::one('role', 'The store must keep at least one active owner.');
            }
        }

        if (!$user) {
            if (!$d['name']) {
                throw ValidationException::one('name', 'Enter a name for the new staff account.');
            }
            // Random unusable password; the invite email lets them set their own.
            $id = $db->insert(
                'INSERT INTO users (name, email, password_hash, role, email_verified_at) VALUES (?, ?, ?, ?, NULL)',
                [$d['name'], $email, Auth::hashPassword(bin2hex(random_bytes(32))), $d['role']]
            );
            $token = Tokens::issue($id, 'reset_password');
            Mailer::sendNow($email, 'reset_password', ['name' => $d['name'], 'url' => url('/reset-password', ['token' => $token]), 'minutes' => intdiv(Tokens::RESET_TTL, 60)]);
            Audit::log('staff.invite', 'user', $id, null, ['email' => $email, 'role' => $d['role']]);
            return $this->redirect('/admin/staff', "Invitation sent to {$email}. The link to set a password expires in 60 minutes.");
        }

        // Role or status change invalidates the user's existing sessions.
        $db->exec('UPDATE users SET role = ?, disabled = ?, session_version = session_version + 1 WHERE id = ?', [$d['role'], $d['disabled'], $user['id']]);
        Audit::log('staff.role_change', 'user', $user['id'], ['role' => $user['role'], 'disabled' => (bool) $user['disabled']], ['role' => $d['role'], 'disabled' => $d['disabled']]);
        return $this->redirect('/admin/staff', "Updated {$email}: " . Permissions::ROLES[$d['role']] . ($d['disabled'] ? ' (disabled)' : '') . '.');
    }
}
