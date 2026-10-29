<?php
/** @var array $p */
$variants = $p['variants'];
$sizes = [];
$colors = [];
foreach ($variants as $v) {
    if ($v['size'] !== '') { $sizes[$v['size']] = true; }
    if ($v['color'] !== '') { $colors[$v['color']] = $v['color_hex']; }
}
$variantData = array_map(static fn ($v) => [
    'id' => (int) $v['id'], 'size' => $v['size'], 'color' => $v['color'], 'stock' => (int) $v['stock'],
    'low' => (int) $v['low_stock_threshold'], 'price' => price($v['pricing']['price']),
    'regular' => price($v['pricing']['regular']), 'sale' => $v['pricing']['on_sale'], 'sku' => $v['sku'],
], $variants);
$first = null;
foreach ($variants as $v) { if ((int) $v['stock'] > 0) { $first = $v; break; } }
$first ??= $variants[0] ?? null;
$wished = in_array((int) $p['id'], $wishlistIds, true);
$pricing = $first['pricing'] ?? $p['pricing'];
$singleVariant = count($variants) === 1 && $variants[0]['size'] === '' && $variants[0]['color'] === '';
?>
<?php if ($preview): ?><div class="sandbox-banner" role="note">Preview — status: <?= e(ucfirst($p['status'])) ?>. Customers <?= $p['status'] === 'active' ? 'can' : 'cannot' ?> see this product.</div><?php endif; ?>
<nav class="container breadcrumbs" aria-label="Breadcrumb">
  <ol>
    <li><a href="<?= e(url('/')) ?>">Home</a></li>
    <?php if ($p['category_slug']): ?><li><a href="<?= e(url('/category/' . $p['category_slug'])) ?>"><?= e($p['category_name']) ?></a></li><?php endif; ?>
    <li aria-current="page"><?= e($p['title']) ?></li>
  </ol>
</nav>

<div class="container pdp">
  <section class="gallery" aria-label="Product images" data-gallery>
    <div class="gallery-main">
      <?php if ($p['images']): ?>
        <?php foreach ($p['images'] as $i => $img): ?>
          <img src="<?= e(media($img['path'], 1600)) ?>" srcset="<?= e(media_srcset($img['path'])) ?>" sizes="(min-width:1024px) 55vw, 100vw"
               alt="<?= e($img['alt'] ?: $p['title'] . ($img['color'] ? ' in ' . $img['color'] : '')) ?>" width="<?= (int) $img['width'] ?>" height="<?= (int) $img['height'] ?>"
               data-slide="<?= $i ?>" data-color="<?= e((string) $img['color']) ?>"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy" hidden' ?>>
        <?php endforeach; ?>
        <?php if (count($p['images']) > 1): ?>
          <button class="icon-btn gallery-nav prev" type="button" data-gallery-prev aria-label="Previous image"><?= icon('chevron-left') ?></button>
          <button class="icon-btn gallery-nav next" type="button" data-gallery-next aria-label="Next image"><?= icon('chevron-right') ?></button>
        <?php endif; ?>
      <?php else: ?>
        <img src="<?= e(asset('assets/img/placeholder.svg')) ?>" alt="Image coming soon" width="600" height="800">
      <?php endif; ?>
    </div>
    <?php if (count($p['images']) > 1): ?>
      <ul class="gallery-thumbs">
        <?php foreach ($p['images'] as $i => $img): ?>
          <li><button type="button" data-thumb="<?= $i ?>" aria-label="Show image <?= $i + 1 ?> of <?= count($p['images']) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><img src="<?= e(media($img['path'], 400)) ?>" alt="" loading="lazy" width="72" height="96"></button></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="pdp-info" aria-labelledby="product-title">
    <?php if ($p['category_name']): ?><span class="eyebrow"><?= e($p['category_name']) ?></span><?php endif; ?>
    <h1 id="product-title"><?= e($p['title']) ?></h1>
    <?php if (!empty($p['seller_slug'])): ?><p class="sold-by">Sold by <a href="<?= e(url('/sellers/' . $p['seller_slug'])) ?>"><?= e($p['seller_name']) ?></a></p><?php endif; ?>
    <p class="price pdp-price" data-price>
      <?php if ($pricing['on_sale']): ?>
        <span class="now-sale"><span class="sr-only">Sale price </span><?= e(price($pricing['price'])) ?></span>
        <span class="was"><span class="sr-only">Regular price </span><?= e(price($pricing['regular'])) ?></span>
      <?php else: ?><span><?= e(price($pricing['price'])) ?></span><?php endif; ?>
    </p>
    <?= App\Core\View::partial('partials/currency-note') ?>
    <p class="pdp-meta">SKU: <span data-sku><?= e($first['sku'] ?? $p['sku']) ?></span> · Prices include all taxes</p>

    <?php if (!$variants): ?>
      <div class="alert alert-warning"><p>This product is currently unavailable.</p></div>
    <?php else: ?>
    <form method="post" action="<?= e(url('/cart/add')) ?>" data-add-to-cart data-variants="<?= e(json_encode($variantData, JSON_UNESCAPED_UNICODE)) ?>">
      <?= csrf_field() ?>
      <?php if ($singleVariant): ?>
        <input type="hidden" name="variant_id" value="<?= (int) $variants[0]['id'] ?>">
      <?php else: ?>
        <div class="field" data-variant-fallback>
          <label for="variant_id">Choose an option</label>
          <select class="select" id="variant_id" name="variant_id" required>
            <?php foreach ($variants as $v): ?>
              <option value="<?= (int) $v['id'] ?>"<?= (int) $v['stock'] <= 0 ? ' disabled' : '' ?><?= $first && $v['id'] === $first['id'] ? ' selected' : '' ?>>
                <?= e(App\Services\Cart::variantLabel($v['size'], $v['color'])) ?> — <?= e(price($v['pricing']['price'])) ?><?= (int) $v['stock'] <= 0 ? ' (sold out)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($colors): ?>
          <fieldset class="option-group" data-option="color" hidden>
            <legend>Colour: <span data-selected-color><?= e($first['color'] ?? '') ?></span></legend>
            <div class="chip-list">
              <?php foreach ($colors as $name => $hex): $name = (string) $name; ?>
                <label class="chip color-chip"><input type="radio" name="_color" value="<?= e($name) ?>"<?= (string) ($first['color'] ?? '') === $name ? ' checked' : '' ?>><span><svg class="swatch-dot" viewBox="0 0 12 12" aria-hidden="true"><circle cx="6" cy="6" r="5.5" fill="<?= e($hex ?: '#cccccc') ?>"/></svg><?= e($name) ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endif; ?>
        <?php if ($sizes): ?>
          <fieldset class="option-group" data-option="size" hidden>
            <legend>Size: <span data-selected-size><?= e($first['size'] ?? '') ?></span></legend>
            <div class="chip-list">
              <?php foreach (array_keys($sizes) as $size): $size = (string) $size; ?>
                <label class="chip size-chip"><input type="radio" name="_size" value="<?= e($size) ?>"<?= (string) ($first['size'] ?? '') === $size ? ' checked' : '' ?>><span><?= e($size) ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endif; ?>
      <?php endif; ?>

      <?php $stock = (int) ($first['stock'] ?? 0); $low = (int) ($first['low_stock_threshold'] ?? 5); ?>
      <p class="stock-note <?= $stock <= 0 ? 'out' : ($stock <= $low ? 'low' : 'in') ?>" data-stock-note aria-live="polite">
        <?= $stock <= 0 ? 'Out of stock' : ($stock <= $low ? "Only {$stock} left" : 'In stock — ready to ship') ?>
      </p>

      <div class="buy-row">
        <div class="qty" data-qty>
          <button type="button" data-qty-dec aria-label="Decrease quantity">−</button>
          <label class="sr-only" for="qty">Quantity</label>
          <input id="qty" type="number" name="quantity" value="1" min="1" max="<?= max(1, min(99, $stock)) ?>" inputmode="numeric">
          <button type="button" data-qty-inc aria-label="Increase quantity">+</button>
        </div>
        <button class="btn btn-primary" type="submit" data-add-btn<?= $inStock ? '' : ' disabled' ?>><?= $inStock ? 'Add to bag' : 'Sold out' ?></button>
      </div>
    </form>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/wishlist/toggle')) ?>" data-wishlist-form class="mt-2">
      <?= csrf_field() ?>
      <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
      <button class="btn btn-link" type="submit" aria-pressed="<?= $wished ? 'true' : 'false' ?>"><?= icon('heart', 'icon icon-sm') ?> <span data-wish-label><?= $wished ? 'Saved to wishlist' : 'Add to wishlist' ?></span></button>
    </form>

    <div class="accordion">
      <details open>
        <summary>Description</summary>
        <div class="acc-body prose"><?= rich($p["description"]) /* sanitized on save */ ?></div>
      </details>
      <?php if ($p['care_details']): ?>
        <details><summary>Fabric &amp; care</summary><div class="acc-body prose"><?= App\Core\Sanitizer::paragraphs($p['care_details']) ?></div></details>
      <?php endif; ?>
      <details>
        <summary>Shipping &amp; returns</summary>
        <div class="acc-body prose">
          <?php if ($p['shipping_returns']): ?><?= App\Core\Sanitizer::paragraphs($p['shipping_returns']) ?>
          <?php else: ?>
            <p>Ships across the United States in 2–7 business days.<?php if (!empty($defaultShipping['free_threshold_minor'])): ?> Free shipping on orders over <?= e(money((int) $defaultShipping['free_threshold_minor'])) ?>.<?php endif; ?></p>
            <p>Unworn items with tags can be exchanged within 7 days. <a href="<?= e(url('/pages/returns-policy')) ?>">Read our returns policy</a>.</p>
          <?php endif; ?>
        </div>
      </details>
    </div>
  </section>
</div>

<?php if ($related): ?>
<section class="section section-ivory" aria-labelledby="related-title">
  <div class="container">
    <div class="section-head"><h2 id="related-title">You may also like</h2></div>
    <ul class="product-grid">
      <?php foreach ($related as $rp): ?><?= App\Core\View::partial('partials/product-card', ['p' => $rp, 'wishlistIds' => $wishlistIds]) ?><?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($variants && $inStock): ?>
<div class="sticky-buy" data-sticky-buy aria-hidden="true">
  <div class="grow"><strong class="small"><?= e($p['title']) ?></strong><div class="small" data-price-mini><?= e(price($pricing['price'])) ?></div></div>
  <a class="btn btn-primary btn-sm" href="#product-title" tabindex="-1">Choose size</a>
</div>
<?php endif; ?>
