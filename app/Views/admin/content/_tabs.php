<?php $tabs = ['/admin/content' => 'General', '/admin/content/home' => 'Homepage', '/admin/content/navigation' => 'Menus', '/admin/content/branding' => 'Logo', '/admin/content/banners' => 'Banners', '/admin/content/pages' => 'Pages & policies', '/admin/content/faqs' => 'FAQs']; ?>
<nav class="category-pills mb-4" aria-label="Content sections">
  <?php foreach ($tabs as $href => $label): ?><a href="<?= e(url($href)) ?>"<?= ($currentPath === $href || ($href !== '/admin/content' && str_starts_with($currentPath, $href))) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View store ↗</a>
</nav>
