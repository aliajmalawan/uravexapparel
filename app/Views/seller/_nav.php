<?php
/** @var array $seller */
$approved = ($seller['status'] ?? '') === 'approved';
$links = $approved
    ? ['/seller' => 'Dashboard', '/seller/products' => 'My products', '/seller/orders' => 'Orders', '/seller/wallet' => 'Wallet', '/seller/settings' => 'Shop settings']
    : ['/seller' => 'Application status', '/seller/settings' => 'Shop settings'];
?>
<nav class="account-nav" aria-label="Seller panel">
  <p class="eyebrow small"><?= e($seller['shop_name']) ?></p>
  <ul>
    <?php foreach ($links as $href => $label): ?>
      <li><a href="<?= e(url($href)) ?>"<?= ($currentPath === $href || ($href !== '/seller' && str_starts_with($currentPath, $href))) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
    <?php endforeach; ?>
    <?php if ($approved): ?><li><a href="<?= e(url('/sellers/' . $seller['slug'])) ?>">View my shop</a></li><?php endif; ?>
    <li><a href="<?= e(url('/account')) ?>">My account</a></li>
    <li><form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><input type="hidden" name="portal" value="seller"><button class="btn btn-link small account-logout" type="submit"><?= icon('logout', 'icon icon-sm') ?> Sign out</button></form></li>
  </ul>
</nav>
