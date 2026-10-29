<?php /** @var array $balances */ $c = $balances['currency']; ?>
<section class="wallet-cards" aria-label="Earnings">
  <div class="wallet-card is-available"><p class="label">Available to withdraw</p><p class="value"><?= e(money(max(0, $balances['available']), $c)) ?></p><a class="xsmall" href="<?= e(url('/seller/wallet')) ?>">Withdraw</a></div>
  <div class="wallet-card"><p class="label">Pending</p><p class="value"><?= e(money($balances['pending'], $c)) ?></p><p class="xsmall muted">Unlocks after delivery + hold period</p></div>
  <div class="wallet-card"><p class="label">Withdrawn</p><p class="value"><?= e(money($balances['withdrawn'], $c)) ?></p><?php if ($balances['in_withdrawal']): ?><p class="xsmall muted"><?= e(money($balances['in_withdrawal'], $c)) ?> being sent</p><?php endif; ?></div>
  <div class="wallet-card"><p class="label">Lifetime earnings</p><p class="value"><?= e(money($balances['lifetime'], $c)) ?></p><p class="xsmall muted">After commission &amp; refunds</p></div>
</section>
<?php if ($balances['available'] < 0): ?><div class="alert alert-warning"><p>Your balance is negative because of a refund on an order whose earnings you had already withdrawn. It is offset against future sales.</p></div><?php endif; ?>
