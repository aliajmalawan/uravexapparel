<?php $tabs = ['/admin/settings' => 'Checkout & inventory', '/admin/settings/payments' => 'Payments', '/admin/settings/currency' => 'Currency', '/admin/settings/shipping' => 'Shipping & couriers']; ?>
<nav class="category-pills mb-4" aria-label="Settings sections">
  <?php foreach ($tabs as $href => $label): ?><a href="<?= e(url($href)) ?>"<?= $currentPath === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
