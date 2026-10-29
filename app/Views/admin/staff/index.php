<?php use App\Auth\Permissions; ?>
<div class="grid-2 grid-2-1">
  <section class="card">
    <h2>Staff accounts</h2>
    <table class="admin-table stack-sm mt-2">
      <thead><tr><th>Person</th><th>Role</th><th>Last sign-in</th><th>Change</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td data-label="Person"><strong><?= e($u['name']) ?></strong><?= $u['disabled'] ? ' <span class="tag tag-muted">disabled</span>' : '' ?><br><span class="xsmall muted"><?= e($u['email']) ?></span></td>
          <td data-label="Role"><?= e($roles[$u['role']]) ?></td>
          <td data-label="Last sign-in"><?= e($u['last_login_at'] ? dt($u['last_login_at']) : 'Never') ?></td>
          <td data-label="Change">
            <form class="flex" method="post" action="<?= e(url('/admin/staff')) ?>" data-confirm="Change access for <?= e($u['email']) ?>? Their current sessions will be signed out.">
              <?= csrf_field() ?><input type="hidden" name="email" value="<?= e($u['email']) ?>">
              <label class="sr-only" for="role<?= (int) $u['id'] ?>">Role</label>
              <select class="select" id="role<?= (int) $u['id'] ?>" name="role"><?php foreach ($roles as $k => $l): ?><option value="<?= $k ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= e($l) ?><?= $k === 'customer' ? ' (remove staff access)' : '' ?></option><?php endforeach; ?></select>
              <label class="check"><input type="checkbox" name="disabled" value="1"<?= $u['disabled'] ? ' checked' : '' ?>> Disabled</label>
              <button class="btn btn-sm btn-dark" type="submit">Apply</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <div>
    <form class="card" method="post" action="<?= e(url('/admin/staff')) ?>" novalidate>
      <?= csrf_field() ?><h2>Invite or promote</h2>
      <p class="hint mt-2">Existing customers keep their password and gain the role. New emails receive a link to set a password.</p>
      <div class="field"><label for="email">Email</label><input class="input" id="email" name="email" type="email" required<?= error_attrs('email') ?>><?= error_text('email') ?></div>
      <div class="field"><label for="name">Name (new accounts)</label><input class="input" id="name" name="name"<?= error_attrs('name') ?>><?= error_text('name') ?></div>
      <div class="field"><label for="role">Role</label><select class="select" id="role" name="role"><?php foreach ($roles as $k => $l): if ($k === 'customer') continue; ?><option value="<?= $k ?>"<?= $k === 'order_staff' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= error_text('role') ?></div>
      <button class="btn btn-primary btn-sm" type="submit">Save access</button>
    </form>
    <section class="card">
      <h2>What each role can do</h2>
      <dl class="kv mt-2">
        <dt>Owner</dt><dd>Everything, including payment settings, store settings, staff roles and data purge.</dd>
        <dt>Manager</dt><dd>Products, imports, inventory, orders, refunds, cancellations, coupons, content, reports, exports and audit log.</dd>
        <dt>Order Staff</dt><dd>View and fulfil orders, verify crypto payments, view customers and stock. No refunds or exports.</dd>
        <dt>Content Editor</dt><dd>Banners, pages, FAQs, homepage and featured products.</dd>
      </dl>
    </section>
  </div>
</div>
