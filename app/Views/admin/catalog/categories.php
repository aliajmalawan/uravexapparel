<?php
/**
 * Categories: tree of main categories and their subcategories (left) and the
 * create / edit form (right, sticky on desktop; below the list on phones).
 */
$c = $edit ?? [];
$v = static fn ($k, $d = '') => old($k, $c[$k] ?? $d);
$parentValue = (string) $v('parent_id', $newParent['id'] ?? '');
$formTitle = $c ? 'Edit “' . $c['name'] . '”' : ($newParent ? 'New subcategory in ' . $newParent['name'] : 'New category');
$tabs = ['all' => ['All', $counts['all']], 'active' => ['Active', $counts['active']], 'archived' => ['Archived', $counts['archived']]];
$thumb = static fn (array $row): string => $row['image_url']
    ? '<img src="' . e(media($row['image_url'], 400)) . '" alt="" width="44" height="44" loading="lazy">'
    : icon($row['icon'] ?: 'hanger');
$searchText = static fn (array $row): string => mb_strtolower($row['name'] . ' ' . $row['slug']);
$mover = static function (array $row, bool $isChild, bool $hasKids) use ($places): string {
    $place = $places[(int) $row['id']] ?? ['first' => true, 'last' => true];
    $name = $row['name'];
    $buttons = [
        'up' => ['arrow-up', 'Move up', $place['first'] ? "Already at the top" : "Move {$name} up"],
        'down' => ['arrow-down', 'Move down', $place['last'] ? "Already at the bottom" : "Move {$name} down"],
        'left' => ['outdent', 'Make main category', $isChild ? "Make {$name} a main category" : 'Already a main category'],
        'right' => ['indent', 'Make subcategory', $isChild ? 'Already a subcategory' : ($place['first'] ? 'No main category above to move into' : ($hasKids ? 'Has its own subcategories — move them out first' : "Make {$name} a subcategory of the category above"))],
    ];
    $disabled = ['up' => $place['first'], 'down' => $place['last'], 'left' => !$isChild, 'right' => $isChild || $place['first'] || $hasKids];
    $html = '<form class="cat-mover" method="post" action="' . e(url('/admin/categories/' . $row['id'] . '/move')) . '">' . csrf_field();
    foreach ($buttons as $dir => [$ic, $label, $title]) {
        $html .= '<button class="cat-move-btn" type="submit" name="direction" value="' . $dir . '" title="' . e($title) . '" aria-label="' . e($label . ': ' . $name) . '"' . ($disabled[$dir] ? ' disabled' : '') . '>' . icon($ic, 'icon icon-sm') . '</button>';
    }
    return $html . '</form>';
};
?>
<div class="cat-page">
  <ul class="kpis cat-kpis" aria-label="Category overview">
    <li class="kpi"><p class="label"><?= icon('grid', 'icon icon-sm') ?> Main categories</p><p class="value"><?= (int) $counts['main'] ?></p></li>
    <li class="kpi"><p class="label"><?= icon('layers', 'icon icon-sm') ?> Subcategories</p><p class="value"><?= (int) $counts['sub'] ?></p></li>
    <li class="kpi"><p class="label"><?= icon('check', 'icon icon-sm') ?> Active</p><p class="value"><?= (int) $counts['active'] ?></p><?php if ($counts['archived']): ?><p class="sub"><?= (int) $counts['archived'] ?> archived</p><?php endif; ?></li>
    <li class="kpi"><p class="label"><?= icon('tag', 'icon icon-sm') ?> Products categorised</p><p class="value"><?= (int) $counts['products'] ?></p></li>
  </ul>

  <div class="cat-layout">
    <section class="card cat-list" aria-labelledby="cat-tree-title">
      <div class="cat-list-head">
        <div>
          <h2 id="cat-tree-title">Category tree</h2>
          <p class="small muted">Main categories show in the store’s Shop menu once they have products; subcategories appear under them. Use <span class="cat-legend"><?= icon('arrow-up', 'icon icon-sm') ?><?= icon('arrow-down', 'icon icon-sm') ?></span> to reorder, <span class="cat-legend"><?= icon('indent', 'icon icon-sm') ?></span> to put a category under the one above it, and <span class="cat-legend"><?= icon('outdent', 'icon icon-sm') ?></span> to make it a main category again.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/admin/categories')) ?>#category-form"><?= icon('plus', 'icon icon-sm') ?> New category</a>
      </div>

      <div class="cat-toolbar">
        <label class="cat-search"><?= icon('search', 'icon icon-sm') ?><span class="sr-only">Search categories</span>
          <input class="input" type="search" placeholder="Search categories…" autocomplete="off" data-cat-search>
        </label>
        <nav class="cat-tabs" aria-label="Filter by status">
          <?php foreach ($tabs as $key => [$label, $n]): ?>
            <a href="<?= e(url('/admin/categories', $key === 'all' ? [] : ['status' => $key])) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span><?= (int) $n ?></span></a>
          <?php endforeach; ?>
        </nav>
      </div>

      <?php if ($tree): ?>
        <ul class="cat-tree" data-cat-tree>
          <?php foreach ($tree as $node):
            $kids = $node['children'];
            // Collapsed overview; open the group being edited or receiving a new subcategory.
            $open = ($c && ((int) ($c['parent_id'] ?? 0) === (int) $node['id'] || (int) $c['id'] === (int) $node['id'])) || (int) ($newParent['id'] ?? 0) === (int) $node['id']
                || ($focus && ((int) $node['id'] === $focus || in_array($focus, array_map(static fn ($k) => (int) $k['id'], $kids), true)));
            $haystack = $searchText($node) . ' ' . implode(' ', array_map($searchText, $kids)); ?>
            <li class="cat-node<?= $node['status'] === 'archived' ? ' is-archived' : '' ?>" id="cat-<?= (int) $node['id'] ?>" data-search="<?= e($haystack) ?>">
              <?php ob_start(); ?>
                <span class="cat-thumb" aria-hidden="true"><?= $thumb($node) ?></span>
                <span class="cat-main">
                  <span class="cat-name"><?= e($node['name']) ?><?php if ($node['status'] === 'archived'): ?> <span class="status-pill">Archived</span><?php endif; ?></span>
                  <span class="cat-meta">/category/<?= e($node['slug']) ?><?= $kids ? ' · ' . e(pluralize(count($kids), 'subcategory', 'subcategories')) : '' ?></span>
                </span>
                <span class="cat-count" title="Products in this category and its subcategories"><?= (int) $node['total'] ?><small>products</small></span>
              <?php $rowInner = ob_get_clean(); ?>
              <?php $actions = '<span class="cat-actions">' . $mover($node, false, (bool) $kids)
                  . '<a class="btn btn-sm btn-outline" href="' . e(url('/admin/categories', ['edit' => $node['id']])) . '#category-form">Edit</a>'
                  . '<a class="btn btn-sm btn-link" href="' . e(url('/admin/categories', ['parent' => $node['id']])) . '#category-form" title="Add a subcategory to ' . e($node['name']) . '">' . icon('plus', 'icon icon-sm') . '<span class="hide-sm"> Subcategory</span></a>'
                  . '<a class="btn btn-sm btn-link" href="' . e(url('/category/' . $node['slug'])) . '" target="_blank" rel="noopener" title="View on store">' . icon('external', 'icon icon-sm') . '<span class="sr-only">View ' . e($node['name']) . ' on the store</span></a>'
                  . '</span>'; ?>
              <?php if ($kids): ?>
                <details class="cat-group"<?= $open ? ' open' : '' ?>>
                  <summary class="cat-row is-parent<?= (int) ($c['id'] ?? 0) === (int) $node['id'] ? ' is-editing' : '' ?><?= $focus === (int) $node['id'] ? ' is-focused' : '' ?>"><span class="cat-caret" aria-hidden="true"><?= icon('chevron-right', 'icon icon-sm') ?></span><?= $rowInner ?><?= $actions ?></summary>
                  <ul class="cat-children">
                    <?php foreach ($kids as $kid): ?>
                      <li class="cat-row is-child<?= $kid['status'] === 'archived' ? ' is-archived' : '' ?><?= (int) ($c['id'] ?? 0) === (int) $kid['id'] ? ' is-editing' : '' ?><?= $focus === (int) $kid['id'] ? ' is-focused' : '' ?>" id="cat-<?= (int) $kid['id'] ?>" data-search="<?= e($searchText($kid)) ?>">
                        <span class="cat-thumb sm" aria-hidden="true"><?= $thumb($kid) ?></span>
                        <span class="cat-main">
                          <span class="cat-name"><?= e($kid['name']) ?><?php if ($kid['status'] === 'archived'): ?> <span class="status-pill">Archived</span><?php endif; ?></span>
                          <span class="cat-meta">/category/<?= e($kid['slug']) ?></span>
                        </span>
                        <span class="cat-count"><?= (int) $kid['products'] ?><small>products</small></span>
                        <span class="cat-actions">
                          <?= $mover($kid, true, false) ?>
                          <a class="btn btn-sm btn-outline" href="<?= e(url('/admin/categories', ['edit' => $kid['id']])) ?>#category-form">Edit</a>
                          <a class="btn btn-sm btn-link" href="<?= e(url('/category/' . $kid['slug'])) ?>" target="_blank" rel="noopener" title="View on store"><?= icon('external', 'icon icon-sm') ?><span class="sr-only">View <?= e($kid['name']) ?> on the store</span></a>
                        </span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php else: ?>
                <div class="cat-row is-parent is-leaf<?= (int) ($c['id'] ?? 0) === (int) $node['id'] ? ' is-editing' : '' ?><?= $focus === (int) $node['id'] ? ' is-focused' : '' ?>"><span class="cat-caret" aria-hidden="true"></span><?= $rowInner ?><?= $actions ?></div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="cat-empty" data-cat-empty hidden><?= icon('search') ?> No categories match your search.</p>
      <?php else: ?>
        <div class="cat-empty">
          <?= icon('grid') ?>
          <p><strong><?= $status === 'all' ? 'No categories yet' : 'Nothing ' . e($status) ?></strong><br><span class="muted small"><?= $status === 'all' ? 'Create your first category with the form.' : 'Switch to “All” to see every category.' ?></span></p>
        </div>
      <?php endif; ?>
    </section>

    <aside class="cat-side" id="category-form">
      <form class="card admin-form cat-form" method="post" action="<?= e(url('/admin/categories')) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <?php if ($c): ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><?php endif; ?>
        <div class="cat-form-head">
          <h2><?= e($formTitle) ?></h2>
          <?php if ($c || $newParent): ?><a class="btn btn-sm btn-link" href="<?= e(url('/admin/categories')) ?>">Cancel</a><?php endif; ?>
        </div>

        <fieldset class="cat-fieldset">
          <legend>Basics</legend>
          <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" required maxlength="120" value="<?= e($v('name')) ?>" placeholder="e.g. Dresses" data-slug-source data-seo-name<?= error_attrs('name') ?>><?= error_text('name') ?></div>
          <div class="field">
            <label for="slug">Link</label>
            <div class="input-prefix"><span>/category/</span><input class="input" id="slug" name="slug" maxlength="140" value="<?= e($v('slug')) ?>" placeholder="created from the name" data-slug-target data-seo-slug<?= error_attrs('slug') ?>></div>
            <?= error_text('slug') ?>
            <p class="hint">Lowercase letters, numbers and dashes. Changing it breaks old links to this category.</p>
          </div>
          <div class="field"><label for="description">Description <span class="char-count" data-count-for="description"></span></label><textarea class="textarea" id="description" name="description" rows="3" maxlength="2000" placeholder="Shown at the top of the category page."><?= e($v('description')) ?></textarea></div>
        </fieldset>

        <fieldset class="cat-fieldset">
          <legend>Placement</legend>
          <div class="form-row c2">
            <div class="field"><label for="parent_id">Main category</label>
              <select class="select" id="parent_id" name="parent_id"<?= $editChildren ? ' disabled' : '' ?>>
                <option value="">None — main category</option>
                <?php foreach ($parents as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $parentValue === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
              </select>
              <?php if ($editChildren): ?><p class="hint">Has <?= e(pluralize($editChildren, 'subcategory', 'subcategories')) ?>, so it stays a main category.</p><?php endif; ?>
              <?= error_text('parent_id') ?>
            </div>
            <div class="field"><label for="sort_order">Position</label><input class="input" id="sort_order" name="sort_order" type="number" min="0" max="9999" value="<?= e($v('sort_order', 0)) ?>"><p class="hint">Lower numbers show first.</p></div>
          </div>
          <div class="field"><span class="label-text" id="status-label">Status</span>
            <div class="segmented" role="radiogroup" aria-labelledby="status-label">
              <?php foreach (['active' => 'Active — visible', 'archived' => 'Archived — hidden'] as $s => $label): ?>
                <label><input type="radio" name="status" value="<?= $s ?>"<?= $v('status', 'active') === $s ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        </fieldset>

        <fieldset class="cat-fieldset">
          <legend>Appearance</legend>
          <div class="field"><span class="label-text" id="icon-label">Icon <span class="hint">(menus and category tiles)</span></span>
            <div class="icon-pick" role="radiogroup" aria-labelledby="icon-label">
              <?php foreach (App\Controllers\Admin\CategoryController::ICONS as $ic): ?>
                <label class="icon-opt"><input type="radio" name="icon" value="<?= e($ic) ?>"<?= $v('icon', 'hanger') === $ic ? ' checked' : '' ?>><span title="<?= e($ic) ?>"><?= icon($ic) ?><span class="sr-only"><?= e($ic) ?></span></span></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="field">
            <label for="image">Image <span class="hint">(optional, used on homepage tiles)</span></label>
            <?php if (!empty($c['image_url'])): ?><div class="cat-current-img"><img src="<?= e(media($c['image_url'], 400)) ?>" alt="Current image for <?= e($c['name']) ?>" width="72" height="72"><span class="small muted">Current image — choose a file to replace it.</span></div><?php endif; ?>
            <input class="input" id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"><?= error_text('file') ?>
          </div>
        </fieldset>

        <fieldset class="cat-fieldset">
          <legend>Search engines</legend>
          <div class="field"><label for="seo_title">SEO title <span class="char-count" data-count-for="seo_title" data-ideal="60"></span></label><input class="input" id="seo_title" name="seo_title" maxlength="191" value="<?= e($v('seo_title')) ?>" placeholder="Defaults to the name" data-seo-title></div>
          <div class="field"><label for="seo_description">Meta description <span class="char-count" data-count-for="seo_description" data-ideal="160"></span></label><textarea class="textarea" id="seo_description" name="seo_description" rows="3" maxlength="320" placeholder="Defaults to the description" data-seo-desc><?= e($v('seo_description')) ?></textarea></div>
          <div class="seo-preview" aria-label="Google preview" data-seo-preview data-site="<?= e(rtrim(url('/'), '/')) ?>">
            <span class="seo-url"></span>
            <span class="seo-title"></span>
            <span class="seo-desc"></span>
          </div>
        </fieldset>

        <div class="cat-form-actions">
          <button class="btn btn-primary" type="submit"><?= $c ? 'Save changes' : 'Create category' ?></button>
          <?php if ($c): ?><a class="btn btn-outline" href="<?= e(url('/category/' . $c['slug'])) ?>" target="_blank" rel="noopener">View on store <?= icon('external', 'icon icon-sm') ?></a><?php endif; ?>
        </div>
      </form>

      <?php if ($c): ?>
        <div class="card cat-danger">
          <h3>Visibility &amp; removal</h3>
          <form method="post" action="<?= e(url('/admin/categories/' . $c['id'] . '/archive')) ?>">
            <?= csrf_field() ?>
            <p class="small muted"><?= $c['status'] === 'active' ? 'Archiving hides this category from the store but keeps its products and settings.' : 'Restoring makes this category visible in the store again.' ?></p>
            <button class="btn btn-sm btn-outline" type="submit"><?= $c['status'] === 'active' ? 'Archive category' : 'Restore category' ?></button>
          </form>
          <?php if ($editProducts === 0 && $editChildren === 0): ?>
            <form method="post" action="<?= e(url('/admin/categories/' . $c['id'] . '/delete')) ?>" data-confirm="Delete “<?= e($c['name']) ?>” permanently?">
              <?= csrf_field() ?>
              <p class="small muted">Deleting removes the category permanently.</p>
              <button class="btn btn-sm btn-danger" type="submit">Delete category</button>
            </form>
          <?php else: ?>
            <p class="small muted">Can’t be deleted while it has <?= e(implode(' and ', array_filter([$editProducts ? pluralize($editProducts, 'product') : '', $editChildren ? pluralize($editChildren, 'subcategory', 'subcategories') : '']))) ?>. Archive it instead, or move those first.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</div>
