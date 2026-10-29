<?php $v = static fn ($k, $d = '') => old($k, $c[$k] ?? $d); ?>
<form class="card admin-form" method="post" action="<?= e(url('/admin/collections')) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <h2><?= $c ? 'Collection details' : 'New collection' ?></h2>
  <?php if ($c): ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><?php endif; ?>
  <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" required value="<?= e($v('name')) ?>" data-slug-source<?= error_attrs('name') ?>><?= error_text('name') ?></div>
  <div class="field"><label for="slug">Slug</label><input class="input" id="slug" name="slug" value="<?= e($v('slug')) ?>" data-slug-target<?= error_attrs('slug') ?>><?= error_text('slug') ?></div>
  <div class="field"><label for="description">Description</label><textarea class="textarea" id="description" name="description" rows="3"><?= e($v('description')) ?></textarea></div>
  <div class="form-row c2">
    <div class="field"><label for="sort_order">Sort order</label><input class="input" id="sort_order" name="sort_order" type="number" min="0" value="<?= e($v('sort_order', 0)) ?>"></div>
    <div class="field"><label for="home_order">Homepage order</label><input class="input" id="home_order" name="home_order" type="number" min="0" value="<?= e($v('home_order', 0)) ?>"></div>
  </div>
  <label class="check"><input type="checkbox" name="featured_on_home" value="1"<?= $v('featured_on_home') ? ' checked' : '' ?>> Feature on homepage</label>
  <div class="field mt-4"><label for="status">Status</label><select class="select" id="status" name="status"><?php foreach (['active', 'archived'] as $s): ?><option value="<?= $s ?>"<?= $v('status', 'active') === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="image">Cover image</label><?php if (!empty($c['image_url'])): ?><img class="thumb mt-2" src="<?= e(media($c['image_url'], 400)) ?>" alt="" width="80" height="100"><?php endif; ?><input class="input" id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"><?= error_text('file') ?></div>
  <div class="field"><label for="seo_title">SEO title</label><input class="input" id="seo_title" name="seo_title" value="<?= e($v('seo_title')) ?>"></div>
  <div class="field"><label for="seo_description">Meta description</label><textarea class="textarea" id="seo_description" name="seo_description" rows="2"><?= e($v('seo_description')) ?></textarea></div>
  <button class="btn btn-primary" type="submit">Save collection</button>
</form>
