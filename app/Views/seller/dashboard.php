<?php /** @var array $seller */ ?>
<header class="container page-head"><span class="eyebrow">Seller panel</span><h1><?= e($seller['shop_name']) ?></h1></header>
<div class="container account-layout seller-panel">
  <?= App\Core\View::partial('seller/_nav', ['seller' => $seller]) ?>
  <div>
    <?php if ($seller['status'] === 'pending'): ?>
      <section class="panel"><h2>Application received</h2><p>Thanks for applying. We are reviewing <strong><?= e($seller['shop_name']) ?></strong> and will email you when it is approved.</p><span class="status-pill s-pending">Pending review</span></section>
    <?php elseif ($seller['status'] !== 'approved'): ?>
      <section class="panel"><h2>Account <?= e($seller['status']) ?></h2><?php if ($seller['status_note']): ?><p><?= e($seller['status_note']) ?></p><?php endif; ?>
        <?php if ($seller['status'] === 'rejected'): ?><a class="btn btn-dark" href="<?= e(url('/sell')) ?>">Update and re-apply</a><?php else: ?><p class="small muted">Your products are hidden from the store. <a href="<?= e(url('/contact')) ?>">Contact us</a> if you have questions.</p><?php endif; ?></section>
    <?php else: ?>
      <?= App\Core\View::partial('seller/_balances', ['balances' => $balances]) ?>
      <div class="cards-3">
        <section class="panel"><h2 class="small">Live products</h2><p class="display stat"><?= (int) ($productCounts['live'] ?? 0) ?></p><p class="xsmall muted"><?= (int) ($productCounts['in_review'] ?? 0) ?> in review<?= (int) ($productCounts['rejected'] ?? 0) ? ' · ' . (int) $productCounts['rejected'] . ' need changes' : '' ?></p><a class="link-arrow" href="<?= e(url('/seller/products')) ?>">Manage products</a></section>
        <section class="panel"><h2 class="small">To ship</h2><p class="display stat"><?= (int) $toShip ?></p><a class="link-arrow" href="<?= e(url('/seller/orders')) ?>">View orders</a></section>
        <section class="panel"><h2 class="small">Payout address</h2><p class="small wallet"><?= $seller['payout_address'] ? e($seller['payout_address']) : '<span class="text-error">Not set</span>' ?></p><a class="link-arrow" href="<?= e(url('/seller/settings')) ?>">Shop settings</a></section>
      </div>
      <section class="panel">
        <h2>Recent orders</h2>
        <?= $recent ? App\Core\View::partial('seller/_order-table', ['rows' => $recent]) : '<p class="muted">No orders yet. Once your products are live, paid orders appear here.</p>' ?>
      </section>
    <?php endif; ?>
  </div>
</div>
