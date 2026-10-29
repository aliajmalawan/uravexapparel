<?php $subject = ($w['status'] === 'paid' ? 'Withdrawal sent' : 'Withdrawal not processed') . ' — ' . money((int) $w['amount_minor'], $w['currency']); ?>
<h1 style="font-size:22px;margin:0 0 12px;"><?= $w['status'] === 'paid' ? 'Your withdrawal has been sent' : 'Your withdrawal was not processed' ?></h1>
<?php if ($w['status'] === 'paid'): ?>
  <p>We sent <strong><?= e(money((int) $w['amount_minor'], $w['currency'])) ?></strong> (USDT, <?= e($w['network']) ?>) to <code><?= e($w['address']) ?></code>.</p>
  <p>Transaction hash: <code style="word-break:break-all;"><?= e((string) $w['txid']) ?></code><br><a href="https://tronscan.org/#/transaction/<?= e((string) $w['txid']) ?>">View on Tronscan</a></p>
<?php else: ?>
  <p>Your withdrawal of <strong><?= e(money((int) $w['amount_minor'], $w['currency'])) ?></strong> was not processed and the amount is back in your available balance.</p>
<?php endif; ?>
<?php if (!empty($w['admin_note'])): ?><p style="background:#F5F3EE;padding:12px;"><?= e($w['admin_note']) ?></p><?php endif; ?>
<?= App\Core\View::partial('emails/_button', ['href' => $walletUrl, 'label' => 'Open wallet']) ?>
