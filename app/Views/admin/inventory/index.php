<div class="page-actions">
  <form class="toolbar" method="get" action="<?= e(url('/admin/inventory')) ?>" role="search">
    <div class="field grow"><label class="sr-only" for="q">Search</label><input class="input" id="q" type="search" name="q" placeholder="Product or SKU" value="<?= e($q) ?>"></div>
    <div class="field"><label class="sr-only" for="level">Stock level</label>
      <select class="select" id="level" name="level"><option value="">All stock levels</option><option value="low"<?= $level === 'low' ? ' selected' : '' ?>>Low stock (<?= (int) $counts['low'] ?>)</option><option value="out"<?= $level === 'out' ? ' selected' : '' ?>>Out of stock (<?= (int) $counts['out'] ?>)</option></select></div>
    <button class="btn btn-dark btn-sm" type="submit">Filter</button>
  </form>
  <div class="flex">
    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/inventory/movements')) ?>"><?= icon('history', 'icon icon-sm') ?> Movement log</a>
    <?php if (App\Auth\Auth::can('reports.export')): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/inventory', array_filter(['q' => $q, 'level' => $level, 'export' => '1']))) ?>"><?= icon('download', 'icon icon-sm') ?> Export CSV</a><?php endif; ?>
  </div>
</div>
<p class="small muted"><?= pluralize($total, 'variant') ?></p>
<?php if ($rows): ?>
<table class="admin-table stack-sm">
  <thead><tr><th scope="col">Variant</th><th scope="col" class="num">On hand</th><th scope="col">Level</th><?php if ($canAdjust): ?><th scope="col">Adjust</th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($rows as $v): $lvl = (int) $v['stock'] === 0 ? 'out' : ((int) $v['stock'] <= (int) $v['low_stock_threshold'] ? 'low' : 'ok'); ?>
    <tr>
      <td data-label="Variant"><a href="<?= e(url('/admin/products/' . $v['product_id'] . '/edit#variants')) ?>"><strong><?= e($v['title']) ?></strong></a><br><span class="xsmall muted"><?= e($v['sku']) ?> · <?= e(App\Services\Cart::variantLabel($v['size'], $v['color'])) ?> · <a href="<?= e(url('/admin/inventory/movements', ['variant' => $v['id']])) ?>">history</a></span></td>
      <td data-label="On hand" class="num"><strong><?= (int) $v['stock'] ?></strong></td>
      <td data-label="Level"><span class="badge-dot <?= $lvl === 'out' ? 'text-error' : ($lvl === 'low' ? 'text-warning' : 'text-success') ?>"><?= $lvl === 'out' ? 'Out of stock' : ($lvl === 'low' ? 'Low (≤' . (int) $v['low_stock_threshold'] . ')' : 'OK') ?></span></td>
      <?php if ($canAdjust): ?>
      <td data-label="Adjust">
        <form class="flex" method="post" action="<?= e(url('/admin/inventory/adjust')) ?>">
          <?= csrf_field() ?><input type="hidden" name="variant_id" value="<?= (int) $v['id'] ?>">
          <label class="sr-only" for="m<?= (int) $v['id'] ?>">Mode</label>
          <select class="select btn-sm" id="m<?= (int) $v['id'] ?>" name="mode"><option value="add">Add</option><option value="remove">Remove</option><option value="set">Set to</option></select>
          <label class="sr-only" for="qy<?= (int) $v['id'] ?>">Quantity</label>
          <input class="input" id="qy<?= (int) $v['id'] ?>" name="quantity" type="number" min="0" max="100000" required size="4">
          <label class="sr-only" for="r<?= (int) $v['id'] ?>">Reason</label>
          <select class="select" id="r<?= (int) $v['id'] ?>" name="reason"><option value="restock">Restock</option><option value="adjustment">Count adjustment</option><option value="damage">Damaged</option><option value="return">Customer return</option></select>
          <label class="sr-only" for="n<?= (int) $v['id'] ?>">Note</label>
          <input class="input" id="n<?= (int) $v['id'] ?>" name="note" placeholder="Note" maxlength="500" size="10">
          <button class="btn btn-sm btn-dark" type="submit">Save</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?= App\Core\View::partial('admin/_pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/inventory', 'query' => array_filter(['q' => $q, 'level' => $level])]) ?>
<?php else: ?><div class="card empty-state"><?= icon('box') ?><h2>No variants match</h2></div><?php endif; ?>
