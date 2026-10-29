<header class="container page-head"><span class="eyebrow">My account</span><h1>Wishlist</h1></header>
<div class="container account-layout">
  <?= App\Core\View::partial('account/_nav') ?>
  <div>
    <?php if ($items): ?>
      <ul class="product-grid">
        <?php foreach ($items as $p): ?><?= App\Core\View::partial('partials/product-card', ['p' => $p, 'wishlistIds' => $wishlistIds]) ?><?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty-state"><?= icon('heart') ?><h2>Your wishlist is empty</h2><p class="muted">Tap the heart on any product to save it here.</p><a class="btn btn-dark" href="<?= e(url('/shop')) ?>">Browse products</a></div>
    <?php endif; ?>
  </div>
</div>
