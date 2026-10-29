<?php
$review = [
    'none' => ['Draft — not submitted', 's-cancelled'], 'pending' => ['In review', 's-pending'],
    'approved' => ['Approved', 's-delivered'], 'rejected' => ['Changes needed', 's-failed'],
];
?>
<header class="container page-head"><span class="eyebrow">Seller panel</span><h1>My products</h1></header>
<div class="container account-layout seller-panel">
  <?= App\Core\View::partial('seller/_nav', ['seller' => $seller]) ?>
  <div>
    <p><a class="btn btn-primary" href="<?= e(url('/seller/products/new')) ?>"><?= icon('plus', 'icon icon-sm') ?> Add product</a></p>
    <section class="panel">
      <?php if ($rows): ?>
        <table class="admin-table stack-sm">
          <thead><tr><th scope="col">Product</th><th scope="col" class="num">Price</th><th scope="col" class="num">Stock</th><th scope="col">Status</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $p): [$label, $cls] = $review[$p['review_status']] ?? ['—', '']; ?>
            <tr>
              <td data-label="Product"><a class="flex" href="<?= e(url('/seller/products/' . $p['id'])) ?>"><?php if ($p['image']): ?><img src="<?= e(media($p['image'], 400)) ?>" alt="" width="40" height="53" loading="lazy"><?php endif; ?><strong><?= e($p['title']) ?></strong></a></td>
              <td data-label="Price" class="num"><?= e(money((int) ($p['sale_price_minor'] ?? $p['price_minor']), $p['currency'])) ?></td>
              <td data-label="Stock" class="num"><?= (int) $p['stock'] ?></td>
              <td data-label="Status"><span class="status-pill <?= e($cls) ?>"><?= e($label) ?></span><?= $p['status'] === 'active' ? ' <span class="status-pill s-paid">Live</span>' : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?><p class="muted">No products yet. Add your first product — it goes live after a quick review.</p><?php endif; ?>
    </section>
  </div>
</div>
