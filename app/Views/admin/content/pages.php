<?= App\Core\View::partial('admin/content/_tabs') ?>
<div class="page-actions"><p class="small muted">About, policies and other informational pages. Active pages appear in the footer.</p><a class="btn btn-primary btn-sm" href="<?= e(url('/admin/content/pages/new')) ?>"><?= icon('plus', 'icon icon-sm') ?> New page</a></div>
<table class="admin-table stack-sm">
  <thead><tr><th>Page</th><th>URL</th><th>Status</th><th>Updated</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): ?>
    <tr><td data-label="Page"><a href="<?= e(url('/admin/content/pages/' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a></td><td data-label="URL" class="small"><?= $p['slug'] === 'about' ? '/about' : '/pages/' . e($p['slug']) ?></td><td data-label="Status"><?= e(ucfirst($p['status'])) ?></td><td data-label="Updated"><?= e(dt($p['updated_at'])) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
