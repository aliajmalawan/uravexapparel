<div class="grid-2 grid-2-1">
  <section class="card">
    <div class="card-head"><h2>All collections</h2></div>
    <?php if ($rows): ?>
    <table class="admin-table stack-sm">
      <thead><tr><th scope="col">Collection</th><th scope="col" class="num">Products</th><th scope="col">Homepage</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $c): ?>
        <tr>
          <td data-label="Collection"><a href="<?= e(url('/admin/collections/' . $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a><br><span class="xsmall muted">/collections/<?= e($c['slug']) ?></span></td>
          <td data-label="Products" class="num"><?= (int) $c['products'] ?></td>
          <td data-label="Homepage"><?= $c['featured_on_home'] ? 'Featured (#' . ((int) $c['home_order'] + 1) . ')' : '—' ?></td>
          <td data-label="Status"><?= e(ucfirst($c['status'])) ?></td>
          <td data-label="Actions" class="flex">
            <a class="btn btn-sm btn-outline" href="<?= e(url('/admin/collections/' . $c['id'])) ?>">Manage</a>
            <form method="post" action="<?= e(url('/admin/collections/' . $c['id'] . '/archive')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link" type="submit"><?= $c['status'] === 'active' ? 'Archive' : 'Restore' ?></button></form>
            <form method="post" action="<?= e(url('/admin/collections/' . $c['id'] . '/delete')) ?>" data-confirm="Delete this collection? Its products are not deleted."><?= csrf_field() ?><button class="btn btn-sm btn-link" type="submit">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="muted">No collections yet.</p><?php endif; ?>
  </section>
  <?= App\Core\View::partial('admin/catalog/_collection-form', ['c' => []]) ?>
</div>
