<?php $mySeller = App\Services\Marketplace::sellerForUser((int) $user['id']); ?>
<header class="container page-head"><span class="eyebrow">My account</span><h1>Hello, <?= e(trim((string) $user['name'])) ?></h1><?php if ($mySeller): ?><p class="muted">Seller · <a href="<?= e(url('/seller')) ?>"><?= e($mySeller['shop_name']) ?></a></p><?php endif; ?></header>
<div class="container account-layout">
  <?= App\Core\View::partial('account/_nav') ?>
  <div>
    <?= App\Core\View::partial('account/_verify', ['user' => $user]) ?>
    <div class="cards-2">
      <section class="panel"><h2>Orders</h2><p class="display stat"><?= (int) $orderCount ?></p><a class="link-arrow" href="<?= e(url('/account/orders')) ?>">View orders</a></section>
      <section class="panel"><h2>Wishlist</h2><p class="display stat"><?= (int) $wishCount ?></p><a class="link-arrow" href="<?= e(url('/account/wishlist')) ?>">View wishlist</a></section>
    </div>
    <section class="panel">
      <h2>Recent orders</h2>
      <?php if ($recent): ?>
        <ul class="order-list">
          <?php foreach ($recent as $o): ?>
            <li class="order-row">
              <a href="<?= e(url('/account/orders/' . $o['order_number'])) ?>"><strong><?= e($o['order_number']) ?></strong></a>
              <span class="small muted"><?= e(date_only($o['placed_at'])) ?></span>
              <span class="hide-sm"><span class="status-pill s-<?= e($o['status']) ?>"><?= e(App\Services\OrderState::label($o['status'])) ?></span></span>
              <span class="small"><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></span>
              <a class="btn btn-sm btn-outline" href="<?= e(url('/account/orders/' . $o['order_number'])) ?>">Details</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted">You have not placed an order yet.</p><a class="btn btn-dark" href="<?= e(url('/shop')) ?>">Start shopping</a>
      <?php endif; ?>
    </section>
    <section class="panel">
      <h2>Default address</h2>
      <?php if ($address): ?>
        <p class="small"><?= e($address['full_name']) ?><br><?= e($address['line1']) ?><br><?= e(App\Services\Region::locality($address)) ?><br><?= e($address['phone']) ?></p>
      <?php else: ?><p class="small muted">No saved address yet.</p><?php endif; ?>
      <a class="link-arrow" href="<?= e(url('/account/addresses')) ?>">Manage addresses</a>
    </section>
  </div>
</div>
