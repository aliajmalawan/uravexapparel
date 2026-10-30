<?php
use App\Auth\Auth;
use App\Auth\Permissions;

$nav = [
    ['/admin', 'Dashboard', 'grid', 'dashboard.view'],
    ['/admin/orders', 'Orders', 'clipboard', 'orders.view'],
    ['/admin/products', 'Products', 'tag', 'products.view'],
    ['/admin/categories', 'Categories', 'layers', 'catalog.manage'],
    ['/admin/collections', 'Collections', 'image', 'catalog.manage'],
    ['/admin/inventory', 'Inventory', 'box', 'inventory.view'],
    ['/admin/customers', 'Customers', 'users', 'customers.view'],
    ['/admin/sellers', 'Sellers', 'home', 'sellers.manage'],
    ['/admin/withdrawals', 'Withdrawals', 'cash', 'payouts.manage'],
    ['/admin/coupons', 'Coupons', 'tag', 'coupons.manage'],
    ['/admin/content', 'Content', 'file', 'content.manage'],
    ['/admin/reports', 'Reports', 'chart', 'reports.view'],
    ['/admin/messages', 'Messages', 'mail', 'messages.view'],
    ['/admin/audit', 'Audit log', 'history', 'audit.view'],
    ['/admin/settings', 'Settings', 'settings', 'settings.manage'],
    ['/admin/staff', 'Staff & roles', 'shield', 'staff.manage'],
];
$current = $currentPath ?? '/admin';
$isActive = static fn (string $href) => $href === '/admin' ? $current === '/admin' : str_starts_with($current, $href);
$u = Auth::user();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> · URAVEX Admin</title>
<link rel="icon" href="<?= e(path_url('favicon.ico')) ?>" sizes="any">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Roboto:wght@300;400;500;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<a class="skip-link" href="#main">Skip to content</a>
<div class="admin-shell">
  <aside class="admin-sidebar on-dark" id="admin-nav" aria-label="Admin navigation">
    <div class="admin-brand">
      <a href="<?= e(url('/admin')) ?>" class="brand"><img src="<?= e(brand_emblem(96)) ?>" width="36" height="36" alt=""><span class="brand-word">URAVEX<small>ADMIN</small></span></a>
      <button class="icon-btn admin-nav-close" type="button" aria-label="Close navigation" data-admin-nav-close><?= icon('close') ?></button>
    </div>
    <nav>
      <ul>
        <?php foreach ($nav as [$href, $label, $ic, $perm]): if (!Auth::can($perm)) continue; ?>
          <li><a href="<?= e(url($href)) ?>"<?= $isActive($href) ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= e($label) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="admin-sidebar-foot">
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('external') ?><span>View store</span></a>
    </div>
  </aside>
  <div class="admin-backdrop" data-admin-nav-close></div>
  <div class="admin-main">
    <header class="admin-topbar">
      <button class="icon-btn admin-nav-open" type="button" aria-label="Open navigation" aria-controls="admin-nav" aria-expanded="false" data-admin-nav-open><?= icon('menu') ?></button>
      <h1 class="admin-title"><?= e($title) ?></h1>
      <div class="admin-user">
        <span class="small hide-sm"><?= e($u['name']) ?> · <span class="tag tag-gold"><?= e(Permissions::ROLES[$u['role']] ?? $u['role']) ?></span></span>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><input type="hidden" name="portal" value="admin"><button class="icon-btn" type="submit" aria-label="Sign out"><?= icon('logout') ?></button></form>
      </div>
    </header>
    <?php if (!App\Core\Config::isProduction()): ?><div class="sandbox-banner" role="note">Development environment — demo data and sandbox payments may be present. Analytics exclude sandbox orders<?= App\Core\Config::isProduction() ? ' and demo data' : '' ?>.</div><?php endif; ?>
    <div class="admin-content" id="main" tabindex="-1">
      <?php foreach (['success', 'error', 'warning', 'info'] as $type): ?>
        <?php if ($msg = flash($type)): ?><div class="alert alert-<?= $type ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><p><?= e($msg) ?></p></div><?php endif; ?>
      <?php endforeach; ?>
      <?php if ($errs = flash('errors')): if (count($errs) > 1): ?>
        <div class="alert alert-error"><ul><?php foreach ($errs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
      <?php endif; endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
</body>
</html>
