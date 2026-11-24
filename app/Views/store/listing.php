<?php
/**
 * Product listing (shop, category, collection, sale, new arrivals, search, seller shop):
 * dark header band with category strip → sticky toolbar → collapsible filter sidebar
 * (bottom-sheet drawer on phones/tablets) → product grid → pagination.
 *
 * @var array $filters @var array $result @var array $facets
 */
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
$activeCount = count($filters['sizes']) + count($filters['colors']) + ($filters['min'] !== null || $filters['max'] !== null ? 1 : 0) + ($filters['in_stock'] ? 1 : 0);
$clearUrl = url($basePath, array_filter(['q' => $filters['q'] ?: null, 'sort' => $filters['sort'] !== 'featured' ? $filters['sort'] : null]));

// Category tree for the strip and the sidebar.
$catById = array_column($categories, null, 'id');
$activeCat = null;
foreach ($categories as $cat) {
    if ($cat['slug'] === $activeCategory) {
        $activeCat = $cat;
    }
}
$activeTop = $activeCat && $activeCat['parent_id'] !== null && isset($catById[$activeCat['parent_id']]) ? $catById[$activeCat['parent_id']] : $activeCat;
$mainCats = array_values(array_filter($categories, static fn ($c) => $c['parent_id'] === null && ((int) $c['product_count'] > 0 || ($activeTop && $activeTop['id'] === $c['id']))));
$childrenOf = static fn (array $parent): array => array_values(array_filter($categories, static fn ($c) => (string) $c['parent_id'] === (string) $parent['id'] && (int) $c['product_count'] > 0));
$subs = $activeTop ? array_values(array_filter($categories, static fn ($c) => (string) $c['parent_id'] === (string) $activeTop['id'] && ((int) $c['product_count'] > 0 || $c['slug'] === $activeCategory))) : [];
$showCategories = $categories && $basePath !== '/search' && !str_starts_with($basePath, '/sellers/');

// Sizes grouped so long lists stay scannable.
$sizeGroups = [];
foreach ($facets['sizes'] as $s) {
    $u = strtoupper((string) $s);
    $group = match (true) {
        (bool) preg_match('/^(XXS|XS|S|M|L|XL|XXL|[2-5]XL|ONE SIZE|OS)$/', $u) => 'Clothing',
        (bool) preg_match('/^\d+(-\d+)?\s*(M|Y|MO|YRS?|T)$/', $u) => 'Baby & kids',
        str_starts_with($u, 'EU') => 'EU shoe',
        str_starts_with($u, 'UK') => 'UK shoe',
        str_starts_with($u, 'US') => 'US shoe',
        (bool) preg_match('/^\d+(\.\d+)?[RLS]?$/', $u) => 'Waist & numeric',
        default => 'Other',
    };
    $sizeGroups[$group][] = $s;
}
$groupOrder = ['Clothing', 'Baby & kids', 'Waist & numeric', 'US shoe', 'EU shoe', 'UK shoe', 'Other'];
uksort($sizeGroups, static fn ($a, $b) => array_search($a, $groupOrder, true) <=> array_search($b, $groupOrder, true));

// Quick price ranges from the catalogue's price spread (rounded to friendly numbers).
$priceBuckets = [];
$pMin = (int) ($facets['price']['min'] ?? 0);
$pMax = (int) ($facets['price']['max'] ?? 0);
if ($pMax > $pMin && $pMax > 0) {
    $nice = static function (int $minor): int {
        $major = $minor / 100;
        $step = $major >= 1000 ? 500 : ($major >= 200 ? 50 : ($major >= 50 ? 10 : 5));
        return (int) (max($step, round($major / $step) * $step) * 100);
    };
    $edges = array_values(array_unique([$nice((int) ($pMin + ($pMax - $pMin) * .15)), $nice((int) ($pMin + ($pMax - $pMin) * .35)), $nice((int) ($pMin + ($pMax - $pMin) * .6))]));
    $prev = null;
    foreach ($edges as $edge) {
        $priceBuckets[] = [$prev, $edge];
        $prev = $edge;
    }
    $priceBuckets[] = [$prev, null];
}
$bucketLabel = static fn (?int $lo, ?int $hi): string => $lo === null ? 'Under ' . money($hi) : ($hi === null ? money($lo) . ' & up' : money($lo) . ' – ' . money($hi));
$bucketUrl = static fn (?int $lo, ?int $hi): string => url($basePath, array_merge($q, [
    'min_price' => $lo !== null ? App\Core\Money::decimal($lo) : null,
    'max_price' => $hi !== null ? App\Core\Money::decimal($hi) : null,
]));

$first = $result['total'] ? ($result['page'] - 1) * App\Services\Catalog::PER_PAGE + 1 : 0;
$last = min($result['total'], $result['page'] * App\Services\Catalog::PER_PAGE);

$filterForm = static function (string $idPrefix, bool $auto = false) use ($filters, $facets, $basePath, $sizeGroups, $priceBuckets, $bucketLabel, $bucketUrl, $mainCats, $childrenOf, $activeTop, $activeCategory, $showCategories, $clearUrl): string {
    ob_start(); ?>
    <?php if ($showCategories && $mainCats): ?>
      <details class="sp-filter"<?= $idPrefix === 'd' || $activeTop ? ' open' : '' ?>>
        <summary>Category</summary>
        <ul class="sp-cat-tree">
          <li><a href="<?= e(url('/shop')) ?>"<?= $basePath === '/shop' ? ' aria-current="page"' : '' ?>>All products</a></li>
          <?php foreach ($mainCats as $mc): $kids = $childrenOf($mc); $isActive = $activeTop && $activeTop['id'] === $mc['id']; ?>
            <li<?= $isActive ? ' class="is-open"' : '' ?>>
              <a href="<?= e(url('/category/' . $mc['slug'])) ?>"<?= $activeCategory === $mc['slug'] ? ' aria-current="page"' : '' ?>><span><?= e($mc['name']) ?></span><small><?= (int) $mc['product_count'] ?></small></a>
              <?php if ($isActive && $kids): ?>
                <ul>
                  <?php foreach ($kids as $k): ?><li><a href="<?= e(url('/category/' . $k['slug'])) ?>"<?= $activeCategory === $k['slug'] ? ' aria-current="page"' : '' ?>><span><?= e($k['name']) ?></span><small><?= (int) $k['product_count'] ?></small></a></li><?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
    <form method="get" action="<?= e(url($basePath)) ?>" class="filter-form sp-form"<?= $auto ? ' data-auto-submit' : '' ?>>
      <?php if ($filters['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><?php endif; ?>
      <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
      <?php if ($sizeGroups): ?>
      <details class="sp-filter"<?= $filters['sizes'] || $idPrefix === 'd' ? ' open' : '' ?>>
        <summary>Size<?php if ($filters['sizes']): ?> <span class="sp-badge"><?= count($filters['sizes']) ?></span><?php endif; ?></summary>
        <?php foreach ($sizeGroups as $group => $sizes): ?>
          <fieldset class="sp-size-group">
            <?php if (count($sizeGroups) > 1): ?><legend><?= e($group) ?></legend><?php else: ?><legend class="sr-only">Size</legend><?php endif; ?>
            <div class="chip-list">
              <?php foreach ($sizes as $s): ?>
                <label class="chip"><input type="checkbox" name="size[]" value="<?= e($s) ?>"<?= in_array($s, $filters['sizes'], true) ? ' checked' : '' ?>><span><?= e($s) ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>
      </details>
      <?php endif; ?>
      <?php if ($facets['colors']): ?>
      <details class="sp-filter"<?= $filters['colors'] || $idPrefix === 'd' ? ' open' : '' ?>>
        <summary>Colour<?php if ($filters['colors']): ?> <span class="sp-badge"><?= count($filters['colors']) ?></span><?php endif; ?></summary>
        <fieldset><legend class="sr-only">Colour</legend>
          <div class="sp-swatches">
            <?php foreach ($facets['colors'] as $c): ?>
              <label class="sp-swatch" title="<?= e($c['color']) ?>"><input type="checkbox" name="color[]" value="<?= e($c['color']) ?>"<?= in_array($c['color'], $filters['colors'], true) ? ' checked' : '' ?>><svg class="sp-dot" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="<?= e($c['hex'] ?: '#cccccc') ?>"/></svg><span class="sp-swatch-name"><?= e($c['color']) ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      </details>
      <?php endif; ?>
      <details class="sp-filter"<?= $filters['min'] !== null || $filters['max'] !== null || $idPrefix === 'd' ? ' open' : '' ?>>
        <summary>Price</summary>
        <?php if ($priceBuckets): ?>
          <ul class="sp-price-quick">
            <?php foreach ($priceBuckets as [$lo, $hi]): $on = $filters['min'] === $lo && $filters['max'] === $hi; ?>
              <li><a href="<?= e($bucketUrl($lo, $hi)) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= e($bucketLabel($lo, $hi)) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <div class="price-range sp-price-range">
          <div><label for="<?= $idPrefix ?>-min">Min</label><input class="input" id="<?= $idPrefix ?>-min" type="number" inputmode="numeric" min="0" step="1" name="min_price" placeholder="0" value="<?= $filters['min'] !== null ? e(App\Core\Money::decimal($filters['min'])) : '' ?>"></div>
          <span aria-hidden="true">–</span>
          <div><label for="<?= $idPrefix ?>-max">Max</label><input class="input" id="<?= $idPrefix ?>-max" type="number" inputmode="numeric" min="0" step="1" name="max_price" placeholder="Any" value="<?= $filters['max'] !== null ? e(App\Core\Money::decimal($filters['max'])) : '' ?>"></div>
        </div>
      </details>
      <details class="sp-filter" open>
        <summary>Availability</summary>
        <label class="sp-toggle"><input type="checkbox" name="availability" value="in_stock"<?= $filters['in_stock'] ? ' checked' : '' ?>><span class="sp-toggle-ui" aria-hidden="true"></span> In stock only</label>
      </details>
      <div class="sp-form-actions">
        <button class="btn btn-dark" type="submit">Show results</button>
        <a class="btn btn-link" href="<?= e($clearUrl) ?>">Clear all</a>
      </div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<section class="sp-hero" aria-labelledby="listing-title">
  <div class="container">
    <nav class="breadcrumbs sp-crumbs" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?= e(url('/')) ?>">Home</a></li>
        <?php foreach ($breadcrumbs as [$name, $path]): ?><li><a href="<?= e(url($path)) ?>"><?= e($name) ?></a></li><?php endforeach; ?>
        <li aria-current="page"><?= e($heading) ?></li>
      </ol>
    </nav>
    <div class="sp-hero-row">
      <div>
        <?php if ($eyebrow): ?><span class="sp-eyebrow"><?= e($eyebrow) ?></span><?php endif; ?>
        <h1 id="listing-title"><?= e($heading) ?></h1>
        <?php if ($intro): ?><p class="sp-intro"><?= e($intro) ?></p><?php endif; ?>
      </div>
      <p class="sp-total"><strong><?= number_format((int) $result['total']) ?></strong> <?= (int) $result['total'] === 1 ? 'product' : 'products' ?></p>
    </div>
  </div>
  <?php if ($showCategories && $mainCats): ?>
    <div class="container">
      <nav class="sp-cats" aria-label="Categories">
        <a class="sp-cat<?= $basePath === '/shop' ? ' is-active' : '' ?>" href="<?= e(url('/shop')) ?>"<?= $basePath === '/shop' ? ' aria-current="page"' : '' ?>><span class="sp-cat-ico"><?= icon('grid', 'icon icon-sm') ?></span>All</a>
        <?php foreach ($mainCats as $mc): $on = $activeTop && $activeTop['id'] === $mc['id']; ?>
          <a class="sp-cat<?= $on ? ' is-active' : '' ?>" href="<?= e(url('/category/' . $mc['slug'])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><span class="sp-cat-ico"><?= icon($mc['icon'] ?: 'hanger', 'icon icon-sm') ?></span><?= e($mc['name']) ?><small><?= (int) $mc['product_count'] ?></small></a>
        <?php endforeach; ?>
      </nav>
    </div>
  <?php endif; ?>
</section>

<?php if ($subs): ?>
<div class="container">
  <nav class="sp-subs" aria-label="<?= e($activeTop['name']) ?> subcategories">
    <a href="<?= e(url('/category/' . $activeTop['slug'])) ?>"<?= $activeCategory === $activeTop['slug'] ? ' aria-current="page"' : '' ?>>All <?= e($activeTop['name']) ?></a>
    <?php foreach ($subs as $sub): ?>
      <a href="<?= e(url('/category/' . $sub['slug'])) ?>"<?= $activeCategory === $sub['slug'] ? ' aria-current="page"' : '' ?>><?= e($sub['name']) ?> <small><?= (int) $sub['product_count'] ?></small></a>
    <?php endforeach; ?>
  </nav>
</div>
<?php endif; ?>

<div class="container sp-body">
  <div class="sp-layout pb-section">
    <aside class="sp-sidebar filters-desktop" aria-label="Filters">
      <div class="sp-sidebar-head"><strong>Filters</strong><?php if ($hasFilters): ?><a href="<?= e($clearUrl) ?>">Clear all</a><?php endif; ?></div>
      <?= $filterForm('d', true) ?>
    </aside>

    <div class="sp-main">
      <div class="sp-toolbar">
        <button class="btn sp-filter-btn" type="button" data-drawer-open="filter-drawer" aria-controls="filter-drawer"><?= icon('filter', 'icon icon-sm') ?> Filters<?php if ($activeCount): ?> <span class="sp-badge"><?= $activeCount ?></span><?php endif; ?></button>
        <p class="sp-showing" role="status"><?= $result['total'] ? 'Showing <strong>' . number_format($first) . '–' . number_format($last) . '</strong> of ' . number_format((int) $result['total']) : 'No products' ?></p>
        <form class="sp-sort" method="get" action="<?= e(url($basePath)) ?>" data-auto-submit>
          <?php foreach ($q as $k => $v): if ($k === 'sort') continue; foreach ((array) $v as $vv): ?>
            <input type="hidden" name="<?= e($k) . (is_array($v) ? '[]' : '') ?>" value="<?= e($vv) ?>">
          <?php endforeach; endforeach; ?>
          <label for="sort"><span class="hide-sm">Sort by</span><span class="sr-only"> sort order</span></label>
          <select class="select" id="sort" name="sort">
            <?php foreach (App\Services\Catalog::SORTS as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-sm btn-outline" type="submit">Sort</button></noscript>
        </form>
      </div>

      <?php if ($hasFilters): ?>
        <div class="sp-active" aria-label="Active filters">
          <?php foreach ($filters['sizes'] as $s): ?><a href="<?= e($without('size', $s)) ?>">Size: <?= e($s) ?> <?= icon('close', 'icon icon-sm') ?><span class="sr-only">Remove</span></a><?php endforeach; ?>
          <?php foreach ($filters['colors'] as $c): ?><a href="<?= e($without('color', $c)) ?>">Colour: <?= e($c) ?> <?= icon('close', 'icon icon-sm') ?><span class="sr-only">Remove</span></a><?php endforeach; ?>
          <?php if ($filters['min'] !== null): ?><a href="<?= e($without('min_price')) ?>">From <?= e(money($filters['min'])) ?> <?= icon('close', 'icon icon-sm') ?><span class="sr-only">Remove</span></a><?php endif; ?>
          <?php if ($filters['max'] !== null): ?><a href="<?= e($without('max_price')) ?>">Up to <?= e(money($filters['max'])) ?> <?= icon('close', 'icon icon-sm') ?><span class="sr-only">Remove</span></a><?php endif; ?>
          <?php if ($filters['in_stock']): ?><a href="<?= e($without('availability')) ?>">In stock <?= icon('close', 'icon icon-sm') ?><span class="sr-only">Remove</span></a><?php endif; ?>
          <a class="sp-clear" href="<?= e($clearUrl) ?>">Clear all</a>
        </div>
      <?php endif; ?>

      <?php if ($result['items']): ?>
        <div class="hp-products sp-grid">
          <ul class="product-grid">
            <?php foreach ($result['items'] as $i => $p): ?><?= App\Core\View::partial('partials/product-card', ['p' => $p, 'wishlistIds' => $wishlistIds, 'eager' => $i < 2]) ?><?php endforeach; ?>
          </ul>
        </div>
        <?php if ($result['pages'] > 1):
          $cur = $result['page'];
          $pages = array_values(array_unique(array_filter(array_merge([1, $result['pages']], range(max(1, $cur - 1), min($result['pages'], $cur + 1))), static fn ($n) => $n >= 1)));
          sort($pages); ?>
          <nav class="sp-pager" aria-label="Pagination">
            <?php if ($cur > 1): ?><a class="sp-pager-step" href="<?= e($pageUrl($cur - 1)) ?>" rel="prev"><?= icon('chevron-left', 'icon icon-sm') ?><span>Previous</span></a><?php else: ?><span class="sp-pager-step is-disabled" aria-hidden="true"><?= icon('chevron-left', 'icon icon-sm') ?><span>Previous</span></span><?php endif; ?>
            <ol>
              <?php $prevN = 0; foreach ($pages as $n): ?>
                <?php if ($n - $prevN > 1): ?><li class="sp-gap" aria-hidden="true">…</li><?php endif; ?>
                <li><?php if ($n === $cur): ?><span aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e($pageUrl($n)) ?>" aria-label="Page <?= $n ?>"><?= $n ?></a><?php endif; ?></li>
              <?php $prevN = $n; endforeach; ?>
            </ol>
            <?php if ($cur < $result['pages']): ?><a class="sp-pager-step" href="<?= e($pageUrl($cur + 1)) ?>" rel="next"><span>Next</span><?= icon('chevron-right', 'icon icon-sm') ?></a><?php else: ?><span class="sp-pager-step is-disabled" aria-hidden="true"><span>Next</span><?= icon('chevron-right', 'icon icon-sm') ?></span><?php endif; ?>
          </nav>
          <p class="sp-pager-note">Page <?= (int) $cur ?> of <?= (int) $result['pages'] ?></p>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty-state sp-empty">
          <span class="sp-empty-ico"><?= icon('search') ?></span>
          <h2><?= $filters['q'] !== '' ? 'No matches for “' . e($filters['q']) . '”' : 'No products match these filters' ?></h2>
          <p class="muted">Try removing a filter, checking the spelling, or browse everything we have.</p>
          <div class="flex flex-center"><a class="btn btn-dark" href="<?= e(url('/shop')) ?>">Browse all products</a><?php if ($hasFilters): ?><a class="btn btn-outline" href="<?= e($clearUrl) ?>">Clear filters</a><?php endif; ?></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="drawer" id="filter-drawer" role="dialog" aria-modal="true" aria-label="Filters">
  <div class="drawer-backdrop" data-drawer-close></div>
  <div class="drawer-panel bottom sp-drawer">
    <div class="drawer-head"><strong>Filters<?php if ($activeCount): ?> <span class="sp-badge"><?= $activeCount ?></span><?php endif; ?></strong><button class="icon-btn" type="button" aria-label="Close filters" data-drawer-close><?= icon('close') ?></button></div>
    <div class="drawer-body"><?= $filterForm('m') ?></div>
  </div>
</div>
