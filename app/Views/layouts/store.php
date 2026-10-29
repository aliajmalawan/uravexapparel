<?php
/** @var array $meta */
/** @var string $content */
$settings = $settings ?? App\Services\Settings::all();
$user = $user ?? null;
$cartCount = $cartCount ?? 0;
$announcement = $settings['announcement'] ?? [];
$nav = [];
foreach ((array) ($settings['navigation']['header'] ?? []) as $link) {
    $nav[$link['href']] = $link['label'];
}
$meta = $meta ?? ['full_title' => 'URAVEX Apparel', 'description' => '', 'noindex' => true, 'jsonld' => [], 'type' => 'website', 'canonical' => null, 'image' => null];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta['full_title']) ?></title>
<meta name="description" content="<?= e($meta['description']) ?>">
<?php if (!empty($meta['noindex'])): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if (!empty($meta['canonical'])): ?><link rel="canonical" href="<?= e($meta['canonical']) ?>"><?php endif; ?>
<meta property="og:site_name" content="URAVEX Apparel">
<meta property="og:title" content="<?= e($meta['full_title']) ?>">
<meta property="og:description" content="<?= e($meta['description']) ?>">
<meta property="og:type" content="<?= e($meta['type']) ?>">
<?php if (!empty($meta['canonical'])): ?><meta property="og:url" content="<?= e($meta['canonical']) ?>"><?php endif; ?>
<?php if (!empty($meta['image'])): ?><meta property="og:image" content="<?= e($meta['image']) ?>"><meta name="twitter:image" content="<?= e($meta['image']) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0B0B0B">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(path_url('favicon.ico')) ?>" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(asset('brand/uravex-emblem-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('brand/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(path_url('site.webmanifest')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<?php foreach ($meta['jsonld'] as $ld): ?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
</head>
<body>
<span id="top" tabindex="-1"></span>
<a class="skip-link" href="#main">Skip to content</a>
<?php if (!empty($announcement['enabled']) && trim((string) ($announcement['text'] ?? '')) !== ''): ?>
<div class="announcement">
  <?php if (!empty($announcement['link'])): ?><a href="<?= e(url($announcement['link'])) ?>"><?= e($announcement['text']) ?></a><?php else: ?><span><?= e($announcement['text']) ?></span><?php endif; ?>
</div>
<?php endif; ?>
<header class="site-header">
  <div class="container header-inner">
    <div class="header-left">
      <button class="icon-btn menu-toggle" type="button" aria-label="Open menu" aria-controls="mobile-menu" aria-expanded="false" data-drawer-open="mobile-menu"><?= icon('menu') ?></button>
      <a class="brand" href="<?= e(url('/')) ?>" aria-label="URAVEX Apparel — home">
        <img src="<?= e(brand_emblem(96)) ?>" width="40" height="40" alt="">
        <?php if (!empty($settings['branding']['show_wordmark'])): ?><span class="brand-word">URAVEX<small>APPAREL</small></span><?php endif; ?>
      </a>
    </div>
    <nav class="main-nav" aria-label="Main">
      <ul>
        <?php foreach ($nav as $href => $label): ?>
          <li><a href="<?= e(url($href)) ?>"<?= is_active_path($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="header-right">
      <?= App\Core\View::partial('partials/currency-switcher', ['place' => 'h', 'class' => 'hide-mobile']) ?>
      <button class="icon-btn" type="button" aria-label="Search" aria-expanded="false" aria-controls="search-panel" data-toggle="search-panel"><?= icon('search') ?></button>
      <?php if ($user): ?>
        <a class="icon-btn hide-mobile" href="<?= e(url(App\Auth\Permissions::isStaff($user['role']) ? '/admin' : '/account')) ?>" aria-label="My account"><?= icon('user') ?></a>
      <?php else: ?>
        <a class="btn-login hide-mobile" href="<?= e(url('/login')) ?>">Login</a>
      <?php endif; ?>
      <a class="icon-btn hide-mobile" href="<?= e(url('/account/wishlist')) ?>" aria-label="Wishlist"><?= icon('heart') ?></a>
      <a class="icon-btn" href="<?= e(url('/cart')) ?>" aria-label="Shopping bag, <?= (int) $cartCount ?> items">
        <?= icon('bag') ?><span class="badge-count" data-cart-count<?= $cartCount ? '' : ' hidden' ?>><?= (int) $cartCount ?></span>
      </a>
    </div>
  </div>
  <div class="search-panel" id="search-panel" hidden>
    <div class="container">
      <form action="<?= e(url('/search')) ?>" method="get" role="search">
        <label class="sr-only" for="site-search">Search products</label>
        <input class="input" id="site-search" type="search" name="q" placeholder="Search shirts, denim, jackets…" value="<?= e($request->query['q'] ?? '') ?>" autocomplete="off" maxlength="80">
        <button class="btn btn-primary" type="submit">Search</button>
      </form>
    </div>
  </div>
</header>

<?php if (!empty($settings['sections']['show_category_rail'])): $railCats = array_values(array_filter(App\Services\Catalog::categories(), static fn ($c) => (int) $c['product_count'] > 0)); ?>
<?php if ($railCats): ?>
<nav class="cat-rail" aria-label="Shop by category">
  <div class="container">
    <ul>
      <?php foreach ($railCats as $c): $here = ($currentPath ?? '') === '/category/' . $c['slug']; ?>
        <li><a href="<?= e(url('/category/' . $c['slug'])) ?>"<?= $here ? ' aria-current="page"' : '' ?>><?= icon($c['icon'] ?: 'hanger') ?><span><?= e($c['name']) ?></span></a></li>
      <?php endforeach; ?>
      <li><a href="<?= e(url('/new-arrivals')) ?>"><?= icon('sparkle') ?><span>New in</span></a></li>
      <li><a href="<?= e(url('/sale')) ?>"><?= icon('tag') ?><span>Sale</span></a></li>
      <li><a href="<?= e(url('/collections')) ?>"><?= icon('dots') ?><span>View all</span></a></li>
    </ul>
  </div>
</nav>
<?php endif; endif; ?>

<div class="drawer" id="mobile-menu" role="dialog" aria-modal="true" aria-label="Menu">
  <div class="drawer-backdrop" data-drawer-close></div>
  <div class="drawer-panel on-dark">
    <div class="drawer-head">
      <img src="<?= e(brand_emblem(96)) ?>" width="36" height="36" alt="URAVEX">
      <button class="icon-btn" type="button" aria-label="Close menu" data-drawer-close><?= icon('close') ?></button>
    </div>
    <div class="drawer-body">
      <ul class="mobile-nav">
        <?php foreach ($nav as $href => $label): ?>
          <li><a href="<?= e(url($href)) ?>"<?= is_active_path($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?><?= icon('chevron-right', 'icon icon-sm') ?></a></li>
        <?php endforeach; ?>
        <li><a href="<?= e(url('/sale')) ?>">Sale<?= icon('chevron-right', 'icon icon-sm') ?></a></li>
        <li><a href="<?= e(url('/account/wishlist')) ?>">Wishlist<?= icon('heart', 'icon icon-sm') ?></a></li>
        <li><a href="<?= e(url($user ? '/account' : '/login')) ?>"><?= $user ? 'My account' : 'Sign in / Register' ?><?= icon('user', 'icon icon-sm') ?></a></li>
        <li><a href="<?= e(url('/track-order')) ?>">Track order<?= icon('truck', 'icon icon-sm') ?></a></li>
      </ul>
      <?= App\Core\View::partial('partials/currency-switcher', ['place' => 'm', 'class' => 'mt-4']) ?>
    </div>
  </div>
</div>

<div class="container flash-region" aria-live="polite">
  <?php foreach (['success', 'error', 'warning', 'info'] as $type): ?>
    <?php if ($msg = flash($type)): ?>
      <div class="alert alert-<?= $type ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><p><?= e($msg) ?></p></div>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php foreach ((array) flash('notices') as $notice): ?>
    <div class="alert alert-info" role="status"><p><?= e($notice) ?></p></div>
  <?php endforeach; ?>
</div>

<main id="main" tabindex="-1">
<?= $content ?>
</main>

<?= App\Core\View::partial('partials/footer', ['settings' => $settings]) ?>

<nav class="tabbar" aria-label="Quick links">
  <a href="<?= e(url('/')) ?>"<?= ($currentPath ?? '') === '/' ? ' aria-current="page"' : '' ?>><?= icon('home') ?><span>Home</span></a>
  <a href="<?= e(url('/collections')) ?>"<?= str_starts_with($currentPath ?? '', '/collections') || str_starts_with($currentPath ?? '', '/category') ? ' aria-current="page"' : '' ?>><?= icon('grid') ?><span>Categories</span></a>
  <a class="tabbar-cart" href="<?= e(url('/cart')) ?>" aria-label="Bag, <?= (int) $cartCount ?> items"><span class="tabbar-cart-btn"><?= icon('bag') ?><span class="badge-count" data-cart-count<?= $cartCount ? '' : ' hidden' ?>><?= (int) $cartCount ?></span></span><span>Bag</span></a>
  <a href="<?= e(url('/account/wishlist')) ?>"<?= ($currentPath ?? '') === '/account/wishlist' ? ' aria-current="page"' : '' ?>><?= icon('heart') ?><span>Wishlist</span></a>
  <a href="<?= e(url($user ? '/account' : '/login')) ?>"<?= in_array($currentPath ?? '', ['/account', '/login'], true) ? ' aria-current="page"' : '' ?>><?= icon('user') ?><span><?= $user ? 'Account' : 'Login' ?></span></a>
</nav>
<noscript><div class="alert alert-info container">Some conveniences need JavaScript, but you can browse, add to bag and check out without it.</div></noscript>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
