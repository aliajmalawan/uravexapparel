<?php
use App\Services\OrderState;
use App\Services\Reports;

$q = ['from' => $range['from'], 'to' => $range['to']];
$exp = static fn (string $t) => App\Auth\Auth::can('reports.export') ? '<a class="btn btn-outline btn-sm" href="' . e(url('/admin/reports/export/' . $t, $q)) . '">' . icon('download', 'icon icon-sm') . ' CSV</a>' : '';
?>
<form class="toolbar" method="get" action="<?= e(url('/admin/reports')) ?>">
  <div class="field"><label for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($range['from']) ?>"></div>
  <div class="field"><label for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($range['to']) ?>"></div>
  <button class="btn btn-dark btn-sm" type="submit">Apply</button>
</form>

<section class="kpis">
  <div class="kpi"><p class="label">Gross sales</p><p class="value"><?= e(money($summary['gross'])) ?></p></div>
  <div class="kpi"><p class="label">Discounts</p><p class="value"><?= e(money($summary['discounts'])) ?></p></div>
  <div class="kpi"><p class="label">Refunds</p><p class="value"><?= e(money($summary['refunds'])) ?></p></div>
  <div class="kpi"><p class="label">Net sales</p><p class="value"><?= e(money($summary['net'])) ?></p></div>
  <div class="kpi"><p class="label">Revenue collected</p><p class="value"><?= e(money($summary['revenue'])) ?></p></div>
  <div class="kpi"><p class="label">Orders</p><p class="value"><?= (int) $summary['orders'] ?></p><p class="sub"><?= (int) $summary['placed'] ?> placed in total</p></div>
</section>
<section class="card"><h2>Definitions</h2><dl class="kv mt-2"><?php foreach (Reports::DEFINITIONS as $k => $def): ?><dt><?= e(ucfirst($k)) ?></dt><dd class="small"><?= e($def) ?></dd><?php endforeach; ?><dt>Excluded</dt><dd class="small">Sandbox test orders<?= App\Core\Config::isProduction() ? ' and development demo data' : ' (demo data is included only in development)' ?>. Dates use Pakistan time.</dd></dl></section>

<section class="card">
  <div class="card-head"><h2>Sales by date</h2><?= $exp('sales') ?></div>
  <?php
    $points = array_map(static fn ($d) => ['label' => date('D j M Y', strtotime($d['date'])), 'short' => date('j M', strtotime($d['date'])), 'value' => $d['sales'], 'display' => money($d['sales']) . ' · ' . $d['orders'] . ' orders'], $daily);
    $peak = $daily ? array_reduce($daily, static fn ($c, $d) => $c === null || $d['sales'] > $c['sales'] ? $d : $c) : null;
  ?>
  <?= App\Core\View::partial('admin/_bar-chart', ['points' => $points, 'title' => 'Net sales per day', 'peak' => $peak && $peak['sales'] ? money($peak['sales']) . ' on ' . date('j M', strtotime($peak['date'])) : 'no sales']) ?>
</section>

<div class="grid-2">
  <section class="card"><div class="card-head"><h2>Orders by status</h2><?= $exp('status') ?></div>
    <table class="admin-table"><thead><tr><th>Status</th><th class="num">Orders</th><th class="num">Value</th></tr></thead><tbody>
      <?php foreach ($statuses as $s): ?><tr><td><?= e(OrderState::label($s['status'])) ?></td><td class="num"><?= (int) $s['n'] ?></td><td class="num"><?= e(money((int) $s['total'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$statuses): ?><tr><td colspan="3" class="muted">No orders.</td></tr><?php endif; ?>
    </tbody></table></section>
  <section class="card"><div class="card-head"><h2>Payment method &amp; status</h2><?= $exp('payments') ?></div>
    <table class="admin-table"><thead><tr><th>Method</th><th>Status</th><th class="num">Orders</th><th class="num">Value</th></tr></thead><tbody>
      <?php foreach ($payments as $p): ?><tr><td><?= e(App\Payments\Registry::label($p['payment_method'])) ?></td><td><?= e(status_label($p['payment_status'])) ?></td><td class="num"><?= (int) $p['n'] ?></td><td class="num"><?= e(money((int) $p['total'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="4" class="muted">No orders.</td></tr><?php endif; ?>
    </tbody></table></section>
</div>

<section class="card"><div class="card-head"><h2>Product performance</h2><?= $exp('products') ?></div>
  <div class="table-scroll"><table class="admin-table"><thead><tr><th>Product</th><th class="num">Units</th><th class="num">Sales</th><th class="num">Refunded units</th></tr></thead><tbody>
    <?php foreach ($products as $p): ?><tr><td><?= e($p['product_title']) ?></td><td class="num"><?= (int) $p['units'] ?></td><td class="num"><?= e(money((int) $p['sales'])) ?></td><td class="num"><?= (int) $p['refunded_units'] ?></td></tr><?php endforeach; ?>
    <?php if (!$products): ?><tr><td colspan="4" class="muted">No sales.</td></tr><?php endif; ?>
  </tbody></table></div></section>

<div class="grid-2">
  <section class="card"><div class="card-head"><h2>Top customers</h2><?= $exp('customers') ?></div>
    <table class="admin-table"><thead><tr><th>Customer</th><th class="num">Orders</th><th class="num">Spend</th></tr></thead><tbody>
      <?php foreach ($customers as $c): ?><tr><td><?= e($c['name']) ?></td><td class="num"><?= (int) $c['orders'] ?></td><td class="num"><?= e(money((int) $c['spend'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$customers): ?><tr><td colspan="3" class="muted">None.</td></tr><?php endif; ?>
    </tbody></table></section>
  <section class="card"><div class="card-head"><h2>Coupons</h2><?= $exp('coupons') ?></div>
    <table class="admin-table"><thead><tr><th>Code</th><th class="num">Uses</th><th class="num">Discount</th><th class="num">Order value</th></tr></thead><tbody>
      <?php foreach ($coupons as $c): ?><tr><td><?= e($c['code']) ?></td><td class="num"><?= (int) $c['uses'] ?></td><td class="num"><?= e(money((int) $c['discount'])) ?></td><td class="num"><?= e(money((int) $c['order_value'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$coupons): ?><tr><td colspan="4" class="muted">No coupon use.</td></tr><?php endif; ?>
    </tbody></table></section>
</div>

<div class="grid-2">
  <section class="card"><div class="card-head"><h2>Refunds</h2><?= $exp('refunds') ?></div>
    <table class="admin-table"><thead><tr><th>Date</th><th>Order</th><th class="num">Amount</th><th>Reason</th></tr></thead><tbody>
      <?php foreach ($refunds as $rf): ?><tr><td><?= e(date_only($rf['created_at'])) ?></td><td><a href="<?= e(url('/admin/orders/' . $rf['order_id'])) ?>"><?= e($rf['order_number']) ?></a></td><td class="num"><?= e(money((int) $rf['amount_minor'])) ?></td><td class="small"><?= e($rf['reason']) ?></td></tr><?php endforeach; ?>
      <?php if (!$refunds): ?><tr><td colspan="4" class="muted">No refunds.</td></tr><?php endif; ?>
    </tbody></table></section>
  <section class="card"><div class="card-head"><h2>Inventory (lowest first)</h2><?= $exp('inventory') ?></div>
    <table class="admin-table"><thead><tr><th>Variant</th><th class="num">Stock</th><th>Level</th></tr></thead><tbody>
      <?php foreach ($inventory as $i): ?><tr><td><?= e($i['title']) ?> <span class="xsmall muted"><?= e($i['sku']) ?></span></td><td class="num"><?= (int) $i['stock'] ?></td><td class="<?= $i['level'] === 'out' ? 'text-error' : ($i['level'] === 'low' ? 'text-warning' : 'text-success') ?>"><?= e(ucfirst($i['level'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></section>
</div>
