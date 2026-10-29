<div class="page-actions"><a class="link-arrow" href="<?= e(url('/admin/collections')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?> All collections</a><a class="btn btn-outline btn-sm" href="<?= e(url('/collections/' . $c['slug'])) ?>" target="_blank" rel="noopener">View on store</a></div>
<div class="grid-2 grid-2-1">
  <form class="card" method="post" action="<?= e(url('/admin/collections/' . $c['id'] . '/products')) ?>">
    <?= csrf_field() ?>
    <div class="card-head"><h2>Products in this collection</h2><span class="definition">Lower numbers appear first.</span></div>
    <?php if ($members): ?>
      <table class="admin-table stack-sm">
        <thead><tr><th scope="col">Product</th><th scope="col">Order</th><th scope="col">Remove</th></tr></thead>
        <tbody>
        <?php foreach ($members as $m): ?>
          <tr>
            <td data-label="Product"><?= e($m['title']) ?> <span class="xsmall muted"><?= e($m['sku']) ?> · <?= e($m['status']) ?></span></td>
            <td data-label="Order"><input class="input" type="number" min="0" name="order[<?= (int) $m['id'] ?>]" value="<?= (int) $m['sort_order'] ?>" aria-label="Order for <?= e($m['title']) ?>"></td>
            <td data-label="Remove"><input type="checkbox" name="remove[]" value="<?= (int) $m['id'] ?>" aria-label="Remove <?= e($m['title']) ?>"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?><p class="muted small">No products yet.</p><?php endif; ?>
    <?php if ($all): ?>
      <div class="field mt-4"><label for="add">Add products</label>
        <select class="select" id="add" name="add[]" multiple size="8"><?php foreach ($all as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['title'] . ' — ' . $p['sku']) ?></option><?php endforeach; ?></select>
        <p class="hint">Hold Ctrl / ⌘ to select several.</p></div>
    <?php endif; ?>
    <button class="btn btn-dark" type="submit">Save products</button>
  </form>
  <?= App\Core\View::partial('admin/catalog/_collection-form', ['c' => $c]) ?>
</div>
