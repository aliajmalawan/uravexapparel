<?php
use App\Auth\Auth;

$types = ['sale' => 'Sale', 'refund' => 'Refund', 'withdrawal' => 'Withdrawal', 'withdrawal_reversal' => 'Withdrawal returned', 'adjustment' => 'Adjustment'];
$actions = match ($s['status']) {
    'pending' => ['approve' => 'Approve shop', 'reject' => 'Reject'],
    'approved' => ['suspend' => 'Suspend shop'],
    'suspended' => ['reinstate' => 'Reinstate shop'],
    default => [],
};
$c = $balances['currency'];
?>
<p><a href="<?= e(url('/admin/sellers')) ?>">← All sellers</a></p>
<div class="grid-2">
  <section class="card">
    <h2><?= e($s['shop_name']) ?> <span class="status-pill s-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span></h2>
    <dl class="kv mt-2">
      <dt>Owner</dt><dd><?= e($s['owner_name']) ?> · <?= e($s['email']) ?><?= $s['contact_phone'] ? ' · ' . e($s['contact_phone']) : '' ?></dd>
      <dt>Applied</dt><dd><?= e(dt($s['applied_at'])) ?></dd>
      <dt>Shop page</dt><dd><?= $s['status'] === 'approved' ? '<a href="' . e(url('/sellers/' . $s['slug'])) . '" target="_blank" rel="noopener">/sellers/' . e($s['slug']) . ' ↗</a>' : '—' ?></dd>
      <dt>Payout address</dt><dd><code class="wallet"><?= e((string) ($s['payout_address'] ?: 'not set')) ?></code></dd>
      <?php if ($s['status_note']): ?><dt>Note</dt><dd><?= e($s['status_note']) ?></dd><?php endif; ?>
    </dl>
    <h3 class="small mt-4">About</h3>
    <p class="small"><?= nl2br(e($s['description'])) ?></p>
    <?php if ($actions): ?>
      <form method="post" action="<?= e(url('/admin/sellers/' . $s['id'] . '/decide')) ?>" class="mt-4">
        <?= csrf_field() ?>
        <div class="field"><label for="note">Note to the seller <span class="muted">(required to reject or suspend)</span></label><input class="input" id="note" name="note" maxlength="500"<?= error_attrs('note') ?>><?= error_text('note') ?></div>
        <div class="flex">
          <?php foreach ($actions as $a => $label): ?><button class="btn btn-sm <?= in_array($a, ['approve', 'reinstate'], true) ? 'btn-primary' : 'btn-danger' ?>" type="submit" name="action" value="<?= e($a) ?>"<?= $a === 'suspend' ? ' data-confirm="Suspend this shop? Its products are hidden immediately."' : '' ?>><?= e($label) ?></button><?php endforeach; ?>
        </div>
        <?= error_text('action') ?>
      </form>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Earnings</h2>
    <dl class="kv mt-2">
      <dt>Available</dt><dd><strong><?= e(money($balances['available'], $c)) ?></strong></dd>
      <dt>Pending</dt><dd><?= e(money($balances['pending'], $c)) ?></dd>
      <dt>In withdrawal</dt><dd><?= e(money($balances['in_withdrawal'], $c)) ?></dd>
      <dt>Withdrawn</dt><dd><?= e(money($balances['withdrawn'], $c)) ?></dd>
      <dt>Lifetime</dt><dd><?= e(money($balances['lifetime'], $c)) ?></dd>
    </dl>
    <?php if (Auth::can('settings.manage')): ?>
      <form method="post" action="<?= e(url('/admin/sellers/' . $s['id'] . '/commission')) ?>" class="mt-4">
        <?= csrf_field() ?>
        <div class="field"><label for="commission_percent">Commission for this seller (%) — now <?= e($commission) ?>%</label>
          <div class="flex"><input class="input grow" id="commission_percent" name="commission_percent" inputmode="decimal" placeholder="Store default" value="<?= e((string) ($s['commission_percent'] ?? '')) ?>"<?= error_attrs('commission_percent') ?>><button class="btn btn-dark btn-sm" type="submit">Save</button></div>
          <?= error_text('commission_percent') ?><p class="hint">Leave blank to use the store default. Applies to new orders only.</p></div>
      </form>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <h2>Products</h2>
  <?php if ($products): ?>
    <table class="admin-table stack-sm"><thead><tr><th scope="col">Product</th><th scope="col">Store status</th><th scope="col">Review</th><th scope="col" class="num">Price</th></tr></thead><tbody>
    <?php foreach ($products as $p): ?>
      <tr><td data-label="Product"><a href="<?= e(url('/admin/products/' . $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a></td><td data-label="Store status"><?= e(ucfirst($p['status'])) ?></td><td data-label="Review"><?= e(ucfirst($p['review_status'])) ?></td><td data-label="Price" class="num"><?= e(money((int) $p['price_minor'], $p['currency'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php else: ?><p class="muted small">No products yet.</p><?php endif; ?>
</section>

<section class="card">
  <h2>Recent wallet activity</h2>
  <?php if ($ledger): ?>
    <table class="admin-table stack-sm"><thead><tr><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Details</th><th scope="col" class="num">Amount</th><th scope="col">Available from</th></tr></thead><tbody>
    <?php foreach ($ledger as $l): ?>
      <tr><td data-label="Date"><?= e(dt($l['created_at'])) ?></td><td data-label="Type"><?= e($types[$l['type']] ?? $l['type']) ?></td><td data-label="Details" class="small"><?= $l['order_id'] ? '<a href="' . e(url('/admin/orders/' . $l['order_id'])) . '">' . e((string) $l['note']) . '</a>' : e((string) $l['note']) ?></td>
        <td data-label="Amount" class="num"><?= e(money((int) $l['amount_minor'], $l['currency'])) ?></td><td data-label="Available from"><?= $l['available_at'] ? e(date_only($l['available_at'])) : 'After delivery' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php else: ?><p class="muted small">No activity yet.</p><?php endif; ?>
</section>

<?php if ($withdrawals): ?>
<section class="card">
  <h2>Withdrawals</h2>
  <table class="admin-table stack-sm"><thead><tr><th scope="col">Requested</th><th scope="col" class="num">Amount</th><th scope="col">Status</th><th scope="col">Transaction</th></tr></thead><tbody>
  <?php foreach ($withdrawals as $w): ?>
    <tr><td data-label="Requested"><?= e(dt($w['requested_at'])) ?></td><td data-label="Amount" class="num"><?= e(money((int) $w['amount_minor'], $w['currency'])) ?></td><td data-label="Status"><?= e(ucfirst($w['status'])) ?></td>
      <td data-label="Transaction"><?= $w['txid'] ? '<a href="https://tronscan.org/#/transaction/' . e($w['txid']) . '" target="_blank" rel="noopener noreferrer"><code class="wallet xsmall">' . e($w['txid']) . '</code></a>' : '—' ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</section>
<?php endif; ?>
