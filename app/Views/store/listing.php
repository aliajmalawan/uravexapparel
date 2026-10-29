<?php
/** @var array $filters @var array $result @var array $facets */
$q = $query;
unset($q['page']);
$pageUrl = static fn (int $n) => url($basePath, array_merge($q, ['page' => $n > 1 ? $n : null]));
$without = static function (string $key, ?string $value = null) use ($q, $basePath) {
    $next = $q;
    if ($value === null) {
        unset($next[$key]);
    } else {
        $vals = is_array($next[$key] ?? null) ? $next[$key] : [];
        $next[$key] = array_values(array_diff($vals, [$value]));
    }
    return url($basePath, $next);
};
$hasFilters = $filters['sizes'] || $filters['colors'] || $filters['min'] !== null || $filters['max'] !== null || $filters['in_stock'];
$filterForm = static function (string $idPrefix, bool $auto = false) use ($filters, $facets, $basePath, $q): string {
    ob_start(); ?>
    <form method="get" action="<?= e(url($basePath)) ?>" class="filter-form"<?= $auto ? " data-auto-submit" : "" ?>>
      <?php if ($filters['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><?php endif; ?>
      <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
      <?php if ($facets['sizes']): ?>
      <fieldset class="filter-group">
        <legend>Size</legend>
        <div class="chip-list">
          <?php foreach ($facets['sizes'] as $s): ?>
            <label class="chip"><input type="checkbox" name="size[]" value="<?= e($s) ?>"<?= in_array($s, $filters['sizes'], true) ? ' checked' : '' ?>><span><?= e($s) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <?php endif; ?>
      <?php if ($facets['colors']): ?>
      <fieldset class="filter-group">
        <legend>Colour</legend>
        <div class="chip-list">
          <?php foreach ($facets['colors'] as $c): ?>
            <label class="chip color-chip"><input type="checkbox" name="color[]" value="<?= e($c['color']) ?>"<?= in_array($c['color'], $filters['colors'], true) ? ' checked' : '' ?>><span><svg class="swatch-dot" viewBox="0 0 12 12" aria-hidden="true"><circle cx="6" cy="6" r="5.5" fill="<?= e($c['hex'] ?: '#cccccc') ?>"/></svg><?= e($c['color']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <?php endif; ?>
      <fieldset class="filter-group">
        <legend>Price (<?= e(App\Core\Money::defaultCurrency()) ?>)</legend>
        <div class="price-range">
          <div><label class="sr-only" for="<?= $idPrefix ?>-min">Minimum price</label><input class="input" id="<?= $idPrefix ?>-min" type="number" inputmode="numeric" min="0" step="1" name="min_price" placeholder="Min" value="<?= $filters['min'] !== null ? e(App\Core\Money::decimal($filters['min'])) : '' ?>"></div>
          <div><label class="sr-only" for="<?= $idPrefix ?>-max">Maximum price</label><input class="input" id="<?= $idPrefix ?>-max" type="number" inputmode="numeric" min="0" step="1" name="max_price" placeholder="Max" value="<?= $filters['max'] !== null ? e(App\Core\Money::decimal($filters['max'])) : '' ?>"></div>
        </div>
      </fieldset>
      <fieldset class="filter-group">
        <legend>Availability</legend>
        <label class="check"><input type="checkbox" name="availability" value="in_stock"<?= $filters['in_stock'] ? ' checked' : '' ?>> In stock only</label>
      </fieldset>
      <div class="flex">
        <button class="btn btn-dark btn-block" type="submit">Apply filters</button>
        <a class="btn btn-link" href="<?= e(url($basePath, array_filter(['q' => $filters['q'] ?: null, 'sort' => $filters['sort'] !== 'featured' ? $filters['sort'] : null]))) ?>">Clear all</a>
      </div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<nav class="container breadcrumbs" aria-label="Breadcrumb">
  <ol>
    <li><a href="<?= e(url('/')) ?>">Home</a></li>
    <?php foreach ($breadcrumbs as [$name, $path]): ?><li><a href="<?= e(url($path)) ?>"><?= e($name) ?></a></li><?php endforeach; ?>
    <li aria-current="page"><?= e($heading) ?></li>
  </ol>
</nav>
<header class="container page-head">
  <?php if ($eyebrow): ?><span class="eyebrow"><?= e($eyebrow) ?></span><?php endif; ?>
  <h1><?= e($heading) ?></h1>
  <?php if ($intro): ?><p class="muted"><?= e($intro) ?></p><?php endif; ?>
  <?php if ($categories && $basePath !== '/search'): ?>
    <nav class="category-pills" aria-label="Categories">
      <a href="<?= e(url('/shop')) ?>"<?= $basePath === '/shop' ? ' aria-current="page"' : '' ?>>All</a>
      <?php foreach ($categories as $cat): ?>
        <?php if ($cat['product_count'] > 0 || $activeCategory === $cat['slug']): ?>
          <a href="<?= e(url('/category/' . $cat['slug'])) ?>"<?= $activeCategory === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
</header>

<div class="container">
  <div class="shop-toolbar">
    <button class="btn btn-outline btn-sm filter-open-btn" type="button" data-drawer-open="filter-drawer" aria-controls="filter-drawer"><?= icon('filter', 'icon icon-sm') ?> Filters<?= $hasFilters ? ' (on)' : '' ?></button>
    <p class="small muted" role="status"><?= pluralize($result['total'], 'product') ?></p>
    <form method="get" action="<?= e(url($basePath)) ?>" data-auto-submit>
      <?php foreach ($q as $k => $v): if ($k === 'sort') continue; foreach ((array) $v as $vv): ?>
        <input type="hidden" name="<?= e($k) . (is_array($v) ? '[]' : '') ?>" value="<?= e($vv) ?>">
      <?php endforeach; endforeach; ?>
      <label class="sr-only" for="sort">Sort by</label>
      <select class="select" id="sort" name="sort">
        <?php foreach (App\Services\Catalog::SORTS as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm btn-outline" type="submit">Sort</button></noscript>
    </form>
  </div>

  <?php if ($hasFilters): ?>
    <div class="active-filters" aria-label="Active filters">
      <?php foreach ($filters['sizes'] as $s): ?><a href="<?= e($without('size', $s)) ?>">Size: <?= e($s) ?> <?= icon('close', 'icon icon-sm') ?><span class="sr-only">Remove</span></a><?php endforeach; ?>
      <?php foreach ($filters['colors'] as $c): ?><a href="<?= e($without('color', $c)) ?>">Colour: <?= e($c) ?> <?= icon('close', 'icon icon-sm') ?><span class="sr-only">Remove</span></a><?php endforeach; ?>
      <?php if ($filters['min'] !== null): ?><a href="<?= e($without('min_price')) ?>">From <?= e(money($filters['min'])) ?> <?= icon('close', 'icon icon-sm') ?></a><?php endif; ?>
      <?php if ($filters['max'] !== null): ?><a href="<?= e($without('max_price')) ?>">Up to <?= e(money($filters['max'])) ?> <?= icon('close', 'icon icon-sm') ?></a><?php endif; ?>
      <?php if ($filters['in_stock']): ?><a href="<?= e($without('availability')) ?>">In stock <?= icon('close', 'icon icon-sm') ?></a><?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="shop-layout pb-section">
    <aside class="filters-desktop" aria-label="Filters"><?= $filterForm("d", true) ?></aside>
    <div>
      <?php if ($result['items']): ?>
        <ul class="product-grid">
          <?php foreach ($result['items'] as $i => $p): ?><?= App\Core\View::partial('partials/product-card', ['p' => $p, 'wishlistIds' => $wishlistIds, 'eager' => $i < 2]) ?><?php endforeach; ?>
        </ul>
        <?php if ($result['pages'] > 1): ?>
          <nav class="pagination" aria-label="Pagination">
            <?php if ($result['page'] > 1): ?><a href="<?= e($pageUrl($result['page'] - 1)) ?>" rel="prev" aria-label="Previous page"><?= icon('chevron-left', 'icon icon-sm') ?></a><?php endif; ?>
            <?php for ($n = max(1, $result['page'] - 2); $n <= min($result['pages'], $result['page'] + 2); $n++): ?>
              <?php if ($n === $result['page']): ?><span aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e($pageUrl($n)) ?>"><?= $n ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($result['page'] < $result['pages']): ?><a href="<?= e($pageUrl($result['page'] + 1)) ?>" rel="next" aria-label="Next page"><?= icon('chevron-right', 'icon icon-sm') ?></a><?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty-state">
          <?= icon('search') ?>
          <h2><?= $filters['q'] !== '' ? 'No matches for “' . e($filters['q']) . '”' : 'No products match these filters' ?></h2>
          <p class="muted">Try removing a filter, checking the spelling, or browse everything we have.</p>
          <div class="flex flex-center"><a class="btn btn-dark" href="<?= e(url('/shop')) ?>">Browse all products</a><?php if ($hasFilters): ?><a class="btn btn-outline" href="<?= e(url($basePath, array_filter(['q' => $filters['q'] ?: null]))) ?>">Clear filters</a><?php endif; ?></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="drawer" id="filter-drawer" role="dialog" aria-modal="true" aria-label="Filters">
  <div class="drawer-backdrop" data-drawer-close></div>
  <div class="drawer-panel bottom">
    <div class="drawer-head"><strong>Filters</strong><button class="icon-btn" type="button" aria-label="Close filters" data-drawer-close><?= icon('close') ?></button></div>
    <div class="drawer-body"><?= $filterForm('m') ?></div>
  </div>
</div>
