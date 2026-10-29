<?php
/** @var array $p product card row from Catalog::hydrateCards */
$wished = in_array((int) $p['id'], $wishlistIds ?? [], true);
$eager = $eager ?? false;
?>
<li class="card<?= $p['in_stock'] ? '' : ' is-soldout' ?>">
  <div class="card-media">
    <img src="<?= e(media($p['image'], 800)) ?>" <?php if ($p['image']): ?>srcset="<?= e(media_srcset($p['image'])) ?>" sizes="(min-width:1024px) 25vw, (min-width:768px) 33vw, 50vw"<?php endif; ?> alt="<?= e($p['image_alt']) ?>" width="600" height="800" <?= $eager ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
    <?php if ($p['image_hover']): ?>
      <img class="img-hover" src="<?= e(media($p['image_hover'], 800)) ?>" alt="" width="600" height="800" loading="lazy" decoding="async">
    <?php endif; ?>
    <div class="card-badges">
      <?php if (!$p['in_stock']): ?><span class="tag tag-muted">Sold out</span>
      <?php elseif ($p['on_sale']): ?><span class="tag tag-gold">Sale</span>
      <?php elseif ($p['is_new']): ?><span class="tag tag-dark">New</span><?php endif; ?>
    </div>
  </div>
  <form class="card-wish" method="post" action="<?= e(url('/wishlist/toggle')) ?>" data-wishlist-form>
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
    <button class="icon-btn" type="submit" aria-pressed="<?= $wished ? 'true' : 'false' ?>" aria-label="<?= $wished ? 'Remove ' . e($p['title']) . ' from wishlist' : 'Save ' . e($p['title']) . ' to wishlist' ?>"><?= icon('heart') ?></button>
  </form>
  <div class="card-body">
    <?php if (!empty($p['category_name'])): ?><p class="card-cat"><?= e($p['category_name']) ?></p><?php endif; ?>
    <h3 class="card-title"><a href="<?= e(url('/product/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
    <p class="price">
      <?php if ($p['on_sale']): ?>
        <span class="now-sale"><span class="sr-only">Sale price </span><?= e(price($p['price'])) ?></span>
        <span class="was"><span class="sr-only">Regular price </span><?= e(price($p['regular'])) ?></span>
      <?php else: ?>
        <span><?= e(price($p['price'])) ?></span>
      <?php endif; ?>
    </p>
    <?php if (count($p['swatches']) > 1): ?>
      <div class="card-swatches" aria-label="<?= count($p['swatches']) ?> colours">
        <?php foreach (array_slice($p['swatches'], 0, 5, true) as $name => $hex): ?>
          <svg class="swatch-dot" viewBox="0 0 12 12" role="img" aria-label="<?= e($name) ?>"><circle cx="6" cy="6" r="5.5" fill="<?= e($hex ?: '#cccccc') ?>"/></svg>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</li>
