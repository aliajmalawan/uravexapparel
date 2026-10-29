<?php $links = ['/account' => 'Overview', '/account/orders' => 'Orders', '/account/wishlist' => 'Wishlist', '/account/addresses' => 'Addresses', '/account/profile' => 'Profile & security']; ?>
<nav class="account-nav" aria-label="Account">
  <ul>
    <?php foreach ($links as $href => $label): ?>
      <li><a href="<?= e(url($href)) ?>"<?= ($currentPath === $href || ($href !== '/account' && str_starts_with($currentPath, $href))) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
    <?php endforeach; ?>
    <?php $mySeller = App\Services\Marketplace::sellerForUser(App\Auth\Auth::id()); ?>
    <li><a href="<?= e(url($mySeller ? '/seller' : '/sell')) ?>"><?= $mySeller ? 'Seller panel' : 'Sell on URAVEX' ?></a></li>
    <?php if (App\Auth\Auth::isStaff()): ?><li><a href="<?= e(url('/admin')) ?>">Admin dashboard</a></li><?php endif; ?>
    <li><form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-link small account-logout" type="submit"><?= icon('logout', 'icon icon-sm') ?> Sign out</button></form></li>
  </ul>
</nav>
