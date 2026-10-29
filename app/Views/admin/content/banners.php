<?= App\Core\View::partial('admin/content/_tabs') ?>
<?php $b = $edit ?? []; $v = static fn ($k, $d = '') => old($k, $b[$k] ?? $d); ?>
<div class="grid-2 grid-2-1">
  <section class="card">
    <h2>Banners</h2>
    <p class="hint mt-2">The first active hero banner is shown at the top of the homepage. Promo banners appear as dark bands. Schedules use Pakistan time.</p>
    <?php if ($rows): ?>
    <table class="admin-table stack-sm mt-2"><thead><tr><th>Banner</th><th>Placement</th><th>Schedule</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td data-label="Banner"><?php if ($row['image_path']): ?><img class="thumb" src="<?= e(media($row['image_path'], 400)) ?>" alt="" width="44" height="58"> <?php endif; ?><strong><?= e($row['title']) ?></strong></td>
          <td data-label="Placement"><?= e(ucfirst($row['placement'])) ?> #<?= (int) $row['sort_order'] ?></td>
          <td data-label="Schedule" class="xsmall"><?= $row['starts_at'] ? 'from ' . e(dt($row['starts_at'])) : 'always' ?><?= $row['ends_at'] ? '<br>until ' . e(dt($row['ends_at'])) : '' ?></td>
          <td data-label="Status"><?= $row['is_active'] ? 'Active' : 'Hidden' ?></td>
          <td data-label="Actions" class="flex"><a class="btn btn-sm btn-outline" href="<?= e(url('/admin/content/banners', ['edit' => $row['id']])) ?>">Edit</a>
            <form method="post" action="<?= e(url('/admin/content/banners/' . $row['id'] . '/delete')) ?>" data-confirm="Delete this banner?"><?= csrf_field() ?><button class="btn btn-sm btn-link" type="submit">Delete</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p class="muted small mt-2">No banners yet — the homepage shows a default hero.</p><?php endif; ?>
  </section>
  <form class="card" method="post" action="<?= e(url('/admin/content/banners')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?><h2><?= $b ? 'Edit banner' : 'New banner' ?></h2>
    <?php if ($b): ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><?php endif; ?>
    <div class="form-row c2 mt-4">
      <div class="field"><label for="placement">Placement</label><select class="select" id="placement" name="placement"><option value="hero"<?= $v('placement') === 'hero' ? ' selected' : '' ?>>Hero</option><option value="promo"<?= $v('placement') === 'promo' ? ' selected' : '' ?>>Promo band</option></select></div>
      <div class="field"><label for="sort_order">Order</label><input class="input" id="sort_order" name="sort_order" type="number" min="0" value="<?= e($v('sort_order', 0)) ?>"></div>
    </div>
    <div class="field"><label for="eyebrow">Eyebrow</label><input class="input" id="eyebrow" name="eyebrow" maxlength="80" value="<?= e($v('eyebrow')) ?>"></div>
    <div class="field"><label for="title">Heading</label><input class="input" id="title" name="title" required maxlength="160" value="<?= e($v('title')) ?>"<?= error_attrs('title') ?>><?= error_text('title') ?></div>
    <div class="field"><label for="subtitle">Text</label><textarea class="textarea" id="subtitle" name="subtitle" rows="2" maxlength="320"><?= e($v('subtitle')) ?></textarea></div>
    <div class="field"><label for="image">Image (hero: 1920×1080)</label><input class="input" id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"><?= error_text('file') ?></div>
    <div class="field"><label for="image_alt">Image description (alt text)</label><input class="input" id="image_alt" name="image_alt" maxlength="191" value="<?= e($v('image_alt')) ?>"></div>
    <div class="form-row c2">
      <div class="field"><label for="cta_label">Button</label><input class="input" id="cta_label" name="cta_label" value="<?= e($v('cta_label', 'Shop now')) ?>"></div>
      <div class="field"><label for="cta_href">Button link</label><input class="input" id="cta_href" name="cta_href" value="<?= e($v('cta_href', '/shop')) ?>"<?= error_attrs('cta_href') ?>><?= error_text('cta_href') ?></div>
      <div class="field"><label for="secondary_cta_label">Second button</label><input class="input" id="secondary_cta_label" name="secondary_cta_label" value="<?= e($v('secondary_cta_label')) ?>"></div>
      <div class="field"><label for="secondary_cta_href">Second link</label><input class="input" id="secondary_cta_href" name="secondary_cta_href" value="<?= e($v('secondary_cta_href')) ?>"<?= error_attrs('secondary_cta_href') ?>><?= error_text('secondary_cta_href') ?></div>
      <div class="field"><label for="starts_at">Show from</label><input class="input" id="starts_at" name="starts_at" type="datetime-local" value="<?= e(old('starts_at', utc_to_local_input($b['starts_at'] ?? null))) ?>"></div>
      <div class="field"><label for="ends_at">Show until</label><input class="input" id="ends_at" name="ends_at" type="datetime-local" value="<?= e(old('ends_at', utc_to_local_input($b['ends_at'] ?? null))) ?>"></div>
    </div>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= $v('is_active', 1) ? ' checked' : '' ?>> Active</label>
    <div class="flex mt-4"><button class="btn btn-primary btn-sm" type="submit">Save banner</button><?php if ($b): ?><a class="btn btn-link" href="<?= e(url('/admin/content/banners')) ?>">Cancel</a><?php endif; ?></div>
  </form>
</div>
