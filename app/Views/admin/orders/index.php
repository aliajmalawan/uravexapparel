<?php use App\Services\OrderState; ?>
<form class="toolbar" method="get" action="<?= e(url('/admin/orders')) ?>" role="search">
  <div class="field grow"><label class="sr-only" for="q">Search</label><input class="input" id="q" type="search" name="q" placeholder="Order no., name, email or phone" value="<?= e($query['q'] ?? '') ?>"></div>
  <div class="field"><label class="sr-only" for="status">Status</label><select class="select" id="status" name="status"><option value="">All statuses</option><?php foreach (OrderState::STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= ($query['status'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="sr-only" for="payment">Payment</label><select class="select" id="payment" name="payment"><option value="">All payments</option><?php foreach (['unpaid', 'pending_verification', 'paid', 'partially_refunded', 'refunded', 'failed', 'voided'] as $k): ?><option value="<?= $k ?>"<?= ($query['payment'] ?? '') === $k ? ' selected' : '' ?>><?= e(status_label($k)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="sr-only" for="method">Method</label><select class="select" id="method" name="method"><option value="">All methods</option><?php foreach ($methods as $code => $m): if (!$m->isImplemented()) continue; ?><option value="<?= e($code) ?>"<?= ($query['method'] ?? '') === $code ? ' selected' : '' ?>><?= e($m->label()) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="label xsmall" for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($query['from'] ?? '') ?>"></div>
  <div class="field"><label class="label xsmall" for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($query['to'] ?? '') ?>"></div>
  <label class="check"><input type="checkbox" name="test" value="1"<?= ($query['test'] ?? '') === '1' ? ' checked' : '' ?>> Include sandbox tests</label>
  <button class="btn btn-dark btn-sm" type="submit">Filter</button>
  <?php if (App\Auth\Auth::can('reports.export')): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/orders/export', $query)) ?>"><?= icon('download', 'icon icon-sm') ?> Export CSV</a><?php endif; ?>
</form>
<p class="small muted"><?= pluralize($total, 'order') ?></p>
<?php if ($rows): ?>
<table class="admin-table stack-sm">
  <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col" class="num">Items</th><th scope="col" class="num">Total</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $o): ?>
    <tr>
      <td data-label="Order"><a href="<?= e(url('/admin/orders/' . $o['id'])) ?>"><strong><?= e($o['order_number']) ?></strong></a><?= $o['is_test'] ? ' <span class="tag tag-muted">test</span>' : '' ?><?= $o['is_demo'] ? ' <span class="tag tag-muted">demo</span>' : '' ?><br><span class="xsmall muted"><?= e(dt($o['placed_at'])) ?></span></td>
      <td data-label="Customer"><?= e($o['customer_name']) ?><br><span class="xsmall muted"><?= e($o['phone']) ?> · <?= e(json_decode((string) $o['shipping_address'], true)['city'] ?? '') ?></span></td>
      <td data-label="Status"><span class="status-pill s-<?= e($o['status']) ?>"><?= e(OrderState::label($o['status'])) ?></span></td>
      <td data-label="Payment"><span class="status-pill s-<?= e($o['payment_status']) ?>"><?= e(status_label($o['payment_status'])) ?></span><br><span class="xsmall muted"><?= e(App\Payments\Registry::label($o['payment_method'])) ?></span></td>
      <td data-label="Items" class="num"><?= (int) $o['items'] ?></td>
      <td data-label="Total" class="num"><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?= App\Core\View::partial('admin/_pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/orders', 'query' => $query]) ?>
<?php else: ?><div class="card empty-state"><?= icon('clipboard') ?><h2>No orders match these filters</h2></div><?php endif; ?>
