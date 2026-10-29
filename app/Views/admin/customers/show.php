<div class="page-actions"><a class="link-arrow" href="<?= e(url('/admin/customers')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?> All customers</a></div>
<section class="kpis">
  <div class="kpi"><p class="label">Orders</p><p class="value"><?= (int) $u['orders'] ?></p></div>
  <div class="kpi"><p class="label">Lifetime spend</p><p class="value"><?= e(money((int) $u['spend'])) ?></p><p class="sub">collected, net of refunds</p></div>
  <div class="kpi"><p class="label">Joined</p><p class="value"><?= e(date_only($u['created_at'])) ?></p></div>
  <div class="kpi"><p class="label">Last sign-in</p><p class="value"><?= e($u['last_login_at'] ? date_only($u['last_login_at']) : '—') ?></p></div>
</section>
<div class="grid-2 grid-1-2">
  <div>
    <section class="card"><h2>Contact</h2>
      <dl class="kv mt-2"><dt>Name</dt><dd><?= e($u['name']) ?></dd><dt>Email</dt><dd><?= e($u['email']) ?> <?= $u['email_verified_at'] ? '<span class="text-success xsmall">verified</span>' : '<span class="text-warning xsmall">unverified</span>' ?></dd><dt>Phone</dt><dd><?= e((string) $u['phone']) ?></dd><dt>Status</dt><dd><?= $u['disabled'] ? 'Disabled' : 'Active' ?></dd></dl>
    </section>
    <?php if (App\Auth\Auth::can('customers.manage') && $u['role'] === 'customer'): ?>
    <form class="card" method="post" action="<?= e(url('/admin/customers/' . $u['id'])) ?>" novalidate>
      <?= csrf_field() ?><h2>Edit customer</h2>
      <div class="field mt-4"><label for="cu-name">Name</label><input class="input" id="cu-name" name="name" required value="<?= e(old('name', $u['name'])) ?>"<?= error_attrs('name') ?>><?= error_text('name') ?></div>
      <div class="field"><label for="cu-email">Email</label><input class="input" id="cu-email" name="email" type="email" required value="<?= e(old('email', $u['email'])) ?>"<?= error_attrs('email') ?>><?= error_text('email') ?></div>
      <div class="field"><label for="cu-phone">Phone</label><input class="input" id="cu-phone" name="phone" type="tel" value="<?= e(old('phone', (string) $u['phone'])) ?>"<?= error_attrs('phone') ?>><?= error_text('phone') ?></div>
      <label class="check"><input type="checkbox" name="verified" value="1"<?= $u['email_verified_at'] ? ' checked' : '' ?>> Email verified</label>
      <label class="check"><input type="checkbox" name="disabled" value="1"<?= $u['disabled'] ? ' checked' : '' ?>> Account disabled (cannot sign in)</label>
      <p class="hint">Changing the email or disabling the account signs the customer out on all devices. Passwords are never shown or set here — the customer uses “Forgot password”.</p>
      <button class="btn btn-dark btn-sm" type="submit">Save customer</button>
    </form>
    <?php endif; ?>
    <section class="card"><h2>Addresses</h2>
      <?php foreach ($addresses as $a): ?><p class="small mt-2"><?= $a['is_default'] ? '<strong>Default</strong><br>' : '' ?><?= e($a['full_name']) ?>, <?= e($a['line1']) ?>, <?= e($a['city']) ?> · <?= e($a['phone']) ?></p><?php endforeach; ?>
      <?php if (!$addresses): ?><p class="small muted mt-2">None saved.</p><?php endif; ?>
    </section>
  </div>
  <section class="card"><h2>Order history</h2>
    <?php if ($orders): ?>
    <table class="admin-table stack-sm mt-2"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="num">Total</th></tr></thead><tbody>
      <?php foreach ($orders as $o): ?><tr><td data-label="Order"><a href="<?= e(url('/admin/orders/' . $o['id'])) ?>"><?= e($o['order_number']) ?></a></td><td data-label="Date"><?= e(date_only($o['placed_at'])) ?></td><td data-label="Status"><span class="status-pill s-<?= e($o['status']) ?>"><?= e(App\Services\OrderState::label($o['status'])) ?></span></td><td data-label="Total" class="num"><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p class="small muted mt-2">No orders yet.</p><?php endif; ?>
  </section>
</div>
