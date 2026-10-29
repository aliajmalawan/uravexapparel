<?php $c = $edit ?? []; $v = static fn ($k, $d = '') => old($k, $c[$k] ?? $d); ?>
<div class="grid-2 grid-2-1">
  <section class="card">
    <div class="card-head"><h2>All categories</h2></div>
    <?php if ($rows): ?>
    <table class="admin-table stack-sm">
      <thead><tr><th scope="col">Name</th><th scope="col">Parent</th><th scope="col" class="num">Products</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td data-label="Name"><strong><?= e($row['name']) ?></strong><br><span class="xsmall muted">/category/<?= e($row['slug']) ?> · order <?= (int) $row['sort_order'] ?></span></td>
          <td data-label="Parent"><?= e($row['parent_name'] ?? '—') ?></td>
          <td data-label="Products" class="num"><?= (int) $row['products'] ?></td>
          <td data-label="Status"><?= e(ucfirst($row['status'])) ?></td>
          <td data-label="Actions" class="flex">
            <a class="btn btn-sm btn-outline" href="<?= e(url('/admin/categories', ['edit' => $row['id']])) ?>">Edit</a>
            <form method="post" action="<?= e(url('/admin/categories/' . $row['id'] . '/archive')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link" type="submit"><?= $row['status'] === 'active' ? 'Archive' : 'Restore' ?></button></form>
            <?php if ((int) $row['products'] === 0): ?><form method="post" action="<?= e(url('/admin/categories/' . $row['id'] . '/delete')) ?>" data-confirm="Delete this category?"><?= csrf_field() ?><button class="btn btn-sm btn-link" type="submit">Delete</button></form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="muted">No categories yet.</p><?php endif; ?>
  </section>
  <form class="card admin-form" method="post" action="<?= e(url('/admin/categories')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <h2><?= $c ? 'Edit category' : 'New category' ?></h2>
    <?php if ($c): ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><?php endif; ?>
    <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" required value="<?= e($v('name')) ?>" data-slug-source<?= error_attrs('name') ?>><?= error_text('name') ?></div>
    <div class="field"><label for="slug">Slug</label><input class="input" id="slug" name="slug" value="<?= e($v('slug')) ?>" data-slug-target<?= error_attrs('slug') ?>><?= error_text('slug') ?></div>
    <div class="field"><label for="description">Description</label><textarea class="textarea" id="description" name="description" rows="3"><?= e($v('description')) ?></textarea></div>
    <div class="form-row c2">
      <div class="field"><label for="parent_id">Parent</label><select class="select" id="parent_id" name="parent_id"><option value="">— None —</option>
        <?php foreach ($rows as $row): if (($c['id'] ?? 0) === $row['id']) continue; ?><option value="<?= (int) $row['id'] ?>"<?= (string) $v('parent_id') === (string) $row['id'] ? ' selected' : '' ?>><?= e($row['name']) ?></option><?php endforeach; ?>
      </select><?= error_text('parent_id') ?></div>
      <div class="field"><label for="sort_order">Sort order</label><input class="input" id="sort_order" name="sort_order" type="number" min="0" value="<?= e($v('sort_order', 0)) ?>"></div>
    </div>
    <div class="field"><label for="icon">Icon (category bar on the store)</label>
      <div class="icon-pick">
        <?php foreach (App\Controllers\Admin\CategoryController::ICONS as $ic): ?>
          <label class="icon-opt"><input type="radio" name="icon" value="<?= e($ic) ?>"<?= $v('icon', 'hanger') === $ic ? ' checked' : '' ?>><span title="<?= e($ic) ?>"><?= icon($ic) ?><span class="sr-only"><?= e($ic) ?></span></span></label>
        <?php endforeach; ?>
      </div></div>
    <div class="field"><label for="status">Status</label><select class="select" id="status" name="status"><?php foreach (['active', 'archived'] as $s): ?><option<?= $v('status', 'active') === $s ? ' selected' : '' ?> value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="image">Image</label><input class="input" id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"><?= error_text('file') ?></div>
    <div class="field"><label for="seo_title">SEO title</label><input class="input" id="seo_title" name="seo_title" value="<?= e($v('seo_title')) ?>"></div>
    <div class="field"><label for="seo_description">Meta description</label><textarea class="textarea" id="seo_description" name="seo_description" rows="2"><?= e($v('seo_description')) ?></textarea></div>
    <div class="flex"><button class="btn btn-primary" type="submit">Save category</button><?php if ($c): ?><a class="btn btn-link" href="<?= e(url('/admin/categories')) ?>">Cancel</a><?php endif; ?></div>
  </form>
</div>
