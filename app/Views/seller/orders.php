<header class="container page-head"><span class="eyebrow">Seller panel</span><h1>Orders</h1></header>
<div class="container account-layout seller-panel">
  <?= App\Core\View::partial('seller/_nav', ['seller' => $seller]) ?>
  <div>
    <section class="panel">
      <p class="small muted">Paid orders that include your products. Ship your items and add the tracking number on each order.</p>
      <?= $rows ? App\Core\View::partial('seller/_order-table', ['rows' => $rows]) : '<p class="muted">No orders yet.</p>' ?>
      <?php if ($pages > 1): ?><nav class="flex mt-4" aria-label="Pages"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="btn btn-sm <?= $i === $page ? 'btn-dark' : 'btn-outline' ?>" href="<?= e(url('/seller/orders', ['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
    </section>
  </div>
</div>
