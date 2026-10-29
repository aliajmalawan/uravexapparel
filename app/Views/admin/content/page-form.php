<?= App\Core\View::partial('admin/content/_tabs') ?>
<?php $v = static fn ($k, $d = '') => old($k, $page[$k] ?? $d); ?>
<form class="admin-form" method="post" action="<?= e(url('/admin/content/pages')) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($page): ?><input type="hidden" name="id" value="<?= (int) $page['id'] ?>"><?php endif; ?>
  <div class="grid-2 grid-2-1">
    <section class="card">
      <div class="field"><label for="title">Title</label><input class="input" id="title" name="title" required value="<?= e($v('title')) ?>" data-slug-source<?= error_attrs('title') ?>><?= error_text('title') ?></div>
      <div class="field"><label for="slug">Slug</label><input class="input" id="slug" name="slug" required value="<?= e($v('slug')) ?>" data-slug-target<?= error_attrs('slug') ?>><p class="hint">The slug “about” is used for /about.</p><?= error_text('slug') ?></div>
      <div class="field"><label for="body">Content</label><textarea class="textarea" id="body" name="body" rows="18"<?= error_attrs('body') ?>><?= e($v('body')) ?></textarea><p class="hint">HTML allowed: headings (h2–h4), paragraphs, lists, bold/italic, links, tables, blockquotes. Everything else is removed when saved.</p><?= error_text('body') ?></div>
    </section>
    <section class="card">
      <div class="field"><label for="status">Status</label><select class="select" id="status" name="status"><option value="active"<?= $v('status', 'active') === 'active' ? ' selected' : '' ?>>Published</option><option value="draft"<?= $v('status') === 'draft' ? ' selected' : '' ?>>Draft</option></select></div>
      <div class="field"><label for="seo_title">SEO title</label><input class="input" id="seo_title" name="seo_title" value="<?= e($v('seo_title')) ?>"></div>
      <div class="field"><label for="seo_description">Meta description</label><textarea class="textarea" id="seo_description" name="seo_description" rows="3"><?= e($v('seo_description')) ?></textarea></div>
      <?php if ($page): ?><p class="small"><a href="<?= e(url($page['slug'] === 'about' ? '/about' : '/pages/' . $page['slug'])) ?>" target="_blank" rel="noopener">View page ↗</a></p><?php endif; ?>
      <button class="btn btn-primary" type="submit">Save page</button>
    </section>
  </div>
</form>
<?php if ($page): ?>
<form class="mt-4" method="post" action="<?= e(url('/admin/content/pages/' . $page['id'] . '/delete')) ?>" data-confirm="Delete this page permanently?"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete page</button></form>
<?php endif; ?>
