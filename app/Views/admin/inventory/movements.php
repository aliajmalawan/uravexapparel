<div class="page-actions">
  <a class="link-arrow" href="<?= e(url('/admin/inventory')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?> Inventory</a>
  <form class="toolbar" method="get" action="<?= e(url('/admin/inventory/movements')) ?>" data-auto-submit>
    <?php if (!empty($query['variant'])): ?><input type="hidden" name="variant" value="<?= (int) $query['variant'] ?>"><?php endif; ?>
    <label class="sr-only" for="reason">Reason</label>
    <select class="select" id="reason" name="reason"><option value="">All reasons</option><?php foreach (App\Services\Inventory::REASONS as $rs): ?><option value="<?= $rs ?>"<?= ($query['reason'] ?? '') === $rs ? ' selected' : '' ?>><?= ucfirst($rs) ?></option><?php endforeach; ?></select>
    <noscript><button class="btn btn-sm btn-dark">Filter</button></noscript>
  </form>
</div>
<?php if ($rows): ?>
<table class="admin-table stack-sm">
  <thead><tr><th scope="col">When</th><th scope="col">Variant</th><th scope="col" class="num">Change</th><th scope="col" class="num">After</th><th scope="col">Reason</th><th scope="col">By / order</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $m): ?>
    <tr>
      <td data-label="When"><?= e(dt($m['created_at'])) ?></td>
      <td data-label="Variant"><?= e($m['title']) ?><br><span class="xsmall muted"><?= e($m['sku']) ?></span></td>
      <td data-label="Change" class="num <?= $m['delta'] < 0 ? 'text-error' : 'text-success' ?>"><?= $m['delta'] > 0 ? '+' : '' ?><?= (int) $m['delta'] ?></td>
      <td data-label="After" class="num"><?= (int) $m['stock_after'] ?></td>
      <td data-label="Reason"><?= e(ucfirst($m['reason'])) ?><?= $m['note'] ? '<br><span class="xsmall muted">' . e($m['note']) . '</span>' : '' ?></td>
      <td data-label="By / order"><?= e($m['actor'] ?? 'System') ?><?php if ($m['order_number']): ?><br><a class="xsmall" href="<?= e(url('/admin/orders/' . $m['order_id'])) ?>"><?= e($m['order_number']) ?></a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?= App\Core\View::partial('admin/_pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/inventory/movements', 'query' => $query]) ?>
<?php else: ?><div class="card empty-state"><h2>No movements</h2></div><?php endif; ?>
