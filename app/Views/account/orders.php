<header class="container page-head"><span class="eyebrow">My account</span><h1>Orders</h1></header>
<div class="container account-layout">
  <?= App\Core\View::partial('account/_nav') ?>
  <div>
    <?php if ($orders): ?>
      <ul class="order-list">
        <?php foreach ($orders as $o): ?>
          <li class="order-row">
            <a href="<?= e(url('/account/orders/' . $o['order_number'])) ?>"><strong><?= e($o['order_number']) ?></strong><br><span class="xsmall muted"><?= pluralize((int) $o['item_count'], 'item') ?></span></a>
            <span class="small muted"><?= e(date_only($o['placed_at'])) ?></span>
            <span><span class="status-pill s-<?= e($o['status']) ?>"><?= e(App\Services\OrderState::label($o['status'])) ?></span></span>
            <span class="small"><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></span>
            <a class="btn btn-sm btn-outline" href="<?= e(url('/account/orders/' . $o['order_number'])) ?>">Details<span class="sr-only"> for <?= e($o['order_number']) ?></span></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
          <?php for ($n = 1; $n <= $pages; $n++): ?><?= $n === $page ? '<span aria-current="page">' . $n . '</span>' : '<a href="' . e(url('/account/orders', ['page' => $n])) . '">' . $n . '</a>' ?><?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state"><?= icon('box') ?><h2>No orders yet</h2><p class="muted">When you place an order it will appear here with live tracking.</p><a class="btn btn-dark" href="<?= e(url('/shop')) ?>">Start shopping</a></div>
    <?php endif; ?>
  </div>
</div>
