<?php use App\Auth\Auth; ?>
<div class="kpis">
  <a class="kpi<?= $pendingReviews ? ' warn-kpi' : '' ?>" href="<?= e(url('/admin/sellers/reviews')) ?>"><p class="label">Products to review</p><p class="value"><?= (int) $pendingReviews ?></p></a>
  <?php if (Auth::can('payouts.manage')): ?><a class="kpi<?= $pendingWithdrawals ? ' warn-kpi' : '' ?>" href="<?= e(url('/admin/withdrawals')) ?>"><p class="label">Withdrawals to process</p><p class="value"><?= (int) $pendingWithdrawals ?></p></a><?php endif; ?>
  <div class="kpi"><p class="label">Commission</p><p class="value"><?= e(rtrim(rtrim(bcadd((string) $m['commission_percent'], '0', 2), '0'), '.')) ?>%</p><p class="sub">Hold <?= (int) $m['hold_days'] ?> days after delivery</p></div>
</div>

<div class="page-actions">
  <nav class="flex" aria-label="Filter sellers">
    <?php foreach (['' => 'All', 'pending' => 'Applications', 'approved' => 'Approved', 'suspended' => 'Suspended', 'rejected' => 'Rejected'] as $k => $label): ?>
      <a class="btn btn-sm <?= $status === $k ? 'btn-dark' : 'btn-outline' ?>" href="<?= e(url('/admin/sellers', $k !== '' ? ['status' => $k] : [])) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <a class="btn btn-outline btn-sm" href="<?= e(url('/sell')) ?>" target="_blank" rel="noopener">Seller sign-up page ↗</a>
</div>

<section class="card">
<?php if ($rows): ?>
  <table class="admin-table stack-sm">
    <thead><tr><th scope="col">Shop</th><th scope="col">Owner</th><th scope="col">Status</th><th scope="col" class="num">Live products</th><th scope="col" class="num">Paid sales</th><th scope="col">Applied</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr>
        <td data-label="Shop"><a href="<?= e(url('/admin/sellers/' . $s['id'])) ?>"><strong><?= e($s['shop_name']) ?></strong></a></td>
        <td data-label="Owner"><?= e($s['owner_name']) ?><br><span class="xsmall muted"><?= e($s['email']) ?></span></td>
        <td data-label="Status"><span class="status-pill s-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span></td>
        <td data-label="Live products" class="num"><?= (int) $s['live_products'] ?></td>
        <td data-label="Paid sales" class="num"><?= e(money((int) $s['gross_sales'])) ?></td>
        <td data-label="Applied"><?= e(date_only($s['applied_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?><p class="muted">No sellers yet. Share the sign-up page: <a href="<?= e(url('/sell')) ?>"><?= e(url('/sell')) ?></a></p><?php endif; ?>
</section>

<?php if (Auth::can('settings.manage')): ?>
<form class="card" method="post" action="<?= e(url('/admin/settings/marketplace')) ?>" novalidate>
  <?= csrf_field() ?>
  <h2>Marketplace settings</h2>
  <label class="check mt-2"><input type="checkbox" name="applications_open" value="1"<?= !empty($m['applications_open']) ? ' checked' : '' ?>> Accept new seller applications</label>
  <div class="form-row c3">
    <div class="field"><label for="commission_percent">Commission (%)</label><input class="input" id="commission_percent" name="commission_percent" inputmode="decimal" required value="<?= e((string) $m['commission_percent']) ?>"<?= error_attrs('commission_percent') ?>><?= error_text('commission_percent') ?></div>
    <div class="field"><label for="hold_days">Hold after delivery (days)</label><input class="input" id="hold_days" name="hold_days" type="number" min="0" max="90" required value="<?= (int) $m['hold_days'] ?>"></div>
    <div class="field"><label for="min_withdrawal">Minimum withdrawal (<?= e(App\Core\Money::defaultCurrency()) ?>)</label><input class="input" id="min_withdrawal" name="min_withdrawal" inputmode="decimal" required value="<?= e(App\Core\Money::decimal((int) $m['min_withdrawal_minor'])) ?>"></div>
  </div>
  <p class="hint">Commission is taken from each seller line total (after discounts) and fixed on the order when it is placed — changes apply to new orders only. The hold period covers your return window before sellers can withdraw.</p>
  <button class="btn btn-primary btn-sm" type="submit">Save marketplace settings</button>
</form>
<?php endif; ?>
