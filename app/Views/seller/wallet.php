<?php
/** @var array $balances @var array $ledger @var array $withdrawals */
$types = ['sale' => 'Sale', 'refund' => 'Refund', 'withdrawal' => 'Withdrawal', 'withdrawal_reversal' => 'Withdrawal returned', 'adjustment' => 'Adjustment'];
$c = $balances['currency'];
$hasPending = (bool) array_filter($withdrawals, static fn ($w) => $w['status'] === 'pending');
?>
<header class="container page-head"><span class="eyebrow">Seller panel</span><h1>Wallet</h1></header>
<div class="container account-layout seller-panel">
  <?= App\Core\View::partial('seller/_nav', ['seller' => $seller]) ?>
  <div>
    <?= App\Core\View::partial('seller/_balances', ['balances' => $balances]) ?>

    <section class="panel" id="withdraw">
      <h2>Withdraw</h2>
      <p class="small muted">Payouts are sent in USDT on the TRON (TRC20) network to your saved address. Minimum <?= e(money($minWithdrawal, $c)) ?>. Earnings unlock <?= (int) $holdDays ?> days after an order is delivered.</p>
      <?php if (!$seller['payout_address']): ?>
        <div class="alert alert-warning"><p>Add your USDT TRC20 payout address in <a href="<?= e(url('/seller/settings')) ?>">Shop settings</a> first.</p></div>
      <?php elseif ($hasPending): ?>
        <div class="alert alert-info"><p>A withdrawal is being processed. You can request another once it is sent.</p></div>
      <?php elseif ($balances['available'] < max(1, $minWithdrawal)): ?>
        <p class="small">Nothing to withdraw yet.</p>
      <?php else: ?>
        <form method="post" action="<?= e(url('/seller/wallet/withdraw')) ?>" novalidate>
          <?= csrf_field() ?>
          <p class="small">To: <code class="wallet"><?= e($seller['payout_address']) ?></code></p>
          <div class="form-grid cols-2">
            <div class="field"><label for="amount">Amount (<?= e($c) ?>)</label><input class="input" id="amount" name="amount" inputmode="decimal" required value="<?= e(old('amount', App\Core\Money::decimal($balances['available'], $c))) ?>"<?= error_attrs('amount') ?>><?= error_text('amount') ?></div>
            <div class="field"><label for="password">Your password</label><input class="input" id="password" name="password" type="password" autocomplete="current-password" required<?= error_attrs('password') ?>><?= error_text('password') ?></div>
          </div>
          <button class="btn btn-primary" type="submit">Request withdrawal</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="panel">
      <h2>Withdrawal requests</h2>
      <?php if ($withdrawals): ?>
        <table class="admin-table stack-sm">
          <thead><tr><th scope="col">Date</th><th scope="col" class="num">Amount</th><th scope="col">Status</th><th scope="col">Address / transaction</th></tr></thead>
          <tbody>
          <?php foreach ($withdrawals as $w): ?>
            <tr>
              <td data-label="Date"><?= e(date_only($w['requested_at'])) ?></td>
              <td data-label="Amount" class="num"><?= e(money((int) $w['amount_minor'], $w['currency'])) ?></td>
              <td data-label="Status"><span class="status-pill s-<?= e($w['status'] === 'paid' ? 'paid' : ($w['status'] === 'rejected' ? 'failed' : 'pending')) ?>"><?= e(ucfirst($w['status'])) ?></span><?= $w['admin_note'] ? '<br><span class="xsmall muted">' . e($w['admin_note']) . '</span>' : '' ?></td>
              <td data-label="Address / transaction"><code class="wallet xsmall"><?= e($w['address']) ?></code><?php if ($w['txid']): ?><br><a class="xsmall" href="https://tronscan.org/#/transaction/<?= e($w['txid']) ?>" target="_blank" rel="noopener noreferrer">View transaction ↗</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?><p class="muted small">No withdrawals yet.</p><?php endif; ?>
    </section>

    <section class="panel">
      <h2>Wallet history</h2>
      <?php if ($ledger): ?>
        <table class="admin-table stack-sm">
          <thead><tr><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Details</th><th scope="col" class="num">Amount</th><th scope="col">Available</th></tr></thead>
          <tbody>
          <?php foreach ($ledger as $l): $pending = $l['available_at'] === null || strtotime($l['available_at'] . ' UTC') > time(); ?>
            <tr>
              <td data-label="Date"><?= e(date_only($l['created_at'])) ?></td>
              <td data-label="Type"><?= e($types[$l['type']] ?? $l['type']) ?></td>
              <td data-label="Details" class="small"><?= e((string) $l['note']) ?></td>
              <td data-label="Amount" class="num <?= (int) $l['amount_minor'] < 0 ? 'text-error' : 'text-success' ?>"><?= (int) $l['amount_minor'] < 0 ? '−' : '+' ?><?= e(money(abs((int) $l['amount_minor']), $l['currency'])) ?></td>
              <td data-label="Available" class="small"><?= $pending ? ($l['available_at'] ? 'From ' . e(date_only($l['available_at'])) : 'After delivery') : 'Yes' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?><p class="muted small">No activity yet.</p><?php endif; ?>
    </section>
  </div>
</div>
