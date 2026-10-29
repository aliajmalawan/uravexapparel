<nav class="flex mb-4" aria-label="Filter withdrawals">
  <?php foreach (['pending' => 'To process', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $label): ?>
    <a class="btn btn-sm <?= $status === $k ? 'btn-dark' : 'btn-outline' ?>" href="<?= e(url('/admin/withdrawals', ['status' => $k])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<?php if ($status === 'pending'): ?>
  <p class="small muted">To pay: send the exact amount in <strong>USDT on TRON (TRC20)</strong> from your wallet to the seller's address, then paste the transaction hash here. Rejecting returns the amount to the seller's available balance.</p>
<?php endif; ?>
<?php if (!$rows): ?><section class="card"><p class="muted">No withdrawals here.</p></section><?php endif; ?>
<?php foreach ($rows as $w): ?>
  <section class="card">
    <div class="card-head"><h2 class="small"><a href="<?= e(url('/admin/sellers/' . $w['sid'])) ?>"><?= e($w['shop_name']) ?></a> · <?= e(money((int) $w['amount_minor'], $w['currency'])) ?></h2><span class="status-pill s-<?= e($w['status'] === 'paid' ? 'paid' : ($w['status'] === 'rejected' ? 'failed' : 'pending')) ?>"><?= e(ucfirst($w['status'])) ?></span></div>
    <dl class="kv mt-2">
      <dt>Requested</dt><dd><?= e(dt($w['requested_at'])) ?></dd>
      <dt>Send to (<?= e($w['network']) ?>)</dt><dd><code class="wallet"><?= e($w['address']) ?></code> <button class="copy-btn" type="button" data-copy="<?= e($w['address']) ?>">Copy</button></dd>
      <dt>Amount</dt><dd><strong><?= e(App\Core\Money::decimal((int) $w['amount_minor'], $w['currency'])) ?> USDT</strong> <button class="copy-btn" type="button" data-copy="<?= e(App\Core\Money::decimal((int) $w['amount_minor'], $w['currency'])) ?>">Copy</button></dd>
      <dt>Seller balance now</dt><dd><?= e(money($w['balances']['available'], $w['currency'])) ?> available · <?= e(money($w['balances']['pending'], $w['currency'])) ?> pending</dd>
      <?php if ($w['txid']): ?><dt>Transaction</dt><dd><a href="https://tronscan.org/#/transaction/<?= e($w['txid']) ?>" target="_blank" rel="noopener noreferrer"><code class="wallet xsmall"><?= e($w['txid']) ?></code></a></dd><?php endif; ?>
      <?php if ($w['admin_note']): ?><dt>Note</dt><dd><?= e($w['admin_note']) ?></dd><?php endif; ?>
    </dl>
    <?php if ($w['status'] === 'pending'): ?>
      <form method="post" action="<?= e(url('/admin/withdrawals/' . $w['id'])) ?>" class="mt-4" novalidate>
        <?= csrf_field() ?>
        <div class="form-row c2">
          <div class="field"><label for="txid-<?= (int) $w['id'] ?>">Payout transaction hash (TXID)</label><input class="input wallet" id="txid-<?= (int) $w['id'] ?>" name="txid" maxlength="100" autocomplete="off" spellcheck="false"></div>
          <div class="field"><label for="wnote-<?= (int) $w['id'] ?>">Note <span class="muted">(required to reject)</span></label><input class="input" id="wnote-<?= (int) $w['id'] ?>" name="note" maxlength="500"></div>
        </div>
        <div class="flex">
          <button class="btn btn-primary btn-sm" type="submit" name="decision" value="paid" data-confirm="Confirm you sent <?= e(App\Core\Money::decimal((int) $w['amount_minor'], $w['currency'])) ?> USDT (TRC20) to <?= e($w['address']) ?>?">Mark as paid</button>
          <button class="btn btn-danger btn-sm" type="submit" name="decision" value="rejected">Reject</button>
        </div>
      </form>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?= error_text('txid') ?><?= error_text('note') ?>
