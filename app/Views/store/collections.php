<nav class="container breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="<?= e(url('/')) ?>">Home</a></li><li aria-current="page">Collections</li></ol></nav>
<header class="container page-head"><span class="eyebrow">Curated edits</span><h1>Collections</h1></header>
<div class="container pb-section">
  <?php if ($collections): ?>
    <div class="tiles">
      <?php foreach ($collections as $c): ?>
        <a class="tile<?= $c['image_url'] ? '' : ' tile-plain' ?>" href="<?= e(url('/collections/' . $c['slug'])) ?>">
          <?php if ($c['image_url']): ?><img src="<?= e(media($c['image_url'], 800)) ?>" alt="" loading="lazy" width="800" height="1000"><?php endif; ?>
          <span class="tile-copy">
            <h2 class="display"><?= e($c['name']) ?></h2>
            <span class="small"><?= pluralize((int) $c['product_count'], 'piece') ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state"><?= icon('layers') ?><h2>Collections are coming soon</h2><p class="muted">In the meantime, browse everything in the shop.</p><a class="btn btn-dark" href="<?= e(url('/shop')) ?>">Shop all</a></div>
  <?php endif; ?>
</div>
