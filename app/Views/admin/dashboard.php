<?php
use App\Services\OrderState;
use App\Services\Reports;

$presets = [
    'Today' => [date_only(now_utc()), null],
    '7 days' => [-6, null],
    '30 days' => [-29, null],
    '90 days' => [-89, null],
];
$tz = new DateTimeZone((string) App\Core\Config::get('timezone'));
$today = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
?>
<form class="toolbar" method="get" action="<?= e(url('/admin')) ?>" aria-label="Date range">
  <div class="field"><label for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($range['from']) ?>" max="<?= e($today) ?>"></div>
  <div class="field"><label for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($range['to']) ?>" max="<?= e($today) ?>"></div>
  <button class="btn btn-dark btn-sm" type="submit">Apply</button>
  <?php foreach ([7, 30, 90] as $d): $f = (new DateTimeImmutable('today', $tz))->modify('-' . ($d - 1) . ' days')->format('Y-m-d'); ?>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin', ['from' => $f, 'to' => $today])) ?>"<?= $range['from'] === $f && $range['to'] === $today ? ' aria-current="true"' : '' ?>>Last <?= $d ?> days</a>
  <?php endforeach; ?>
</form>

<?php if ($canAnalytics && $summary): ?>
<section class="kpis" aria-label="Key metrics">
  <div class="kpi"><p class="label">Gross sales</p><p class="value"><?= e(money($summary['gross'])) ?></p><p class="sub"><?= (int) $summary['orders'] ?> orders</p></div>
  <div class="kpi"><p class="label">Net sales</p><p class="value"><?= e(money($summary['net'])) ?></p><p class="sub">after <?= e(money($summary['discounts'])) ?> discounts</p></div>
  <div class="kpi"><p class="label">Revenue collected</p><p class="value"><?= e(money($summary['revenue'])) ?></p><p class="sub">paid orders, incl. delivery</p></div>
  <div class="kpi"><p class="label">Refunds</p><p class="value"><?= e(money($summary['refunds'])) ?></p><p class="sub">issued in period</p></div>
  <div class="kpi"><p class="label">Avg. order</p><p class="value"><?= e(money($summary['aov'])) ?></p><p class="sub"><?= (int) $summary['completed'] ?> delivered</p></div>
  <div class="kpi"><p class="label">Open orders</p><p class="value"><?= (int) $summary['open'] ?></p><p class="sub"><?= (int) $summary['cancelled'] ?> cancelled</p></div>
</section>
<?php endif; ?>

<section class="kpis" aria-label="Store health">
  <a class="kpi<?= $counts['to_fulfil'] ? ' warn-kpi' : '' ?>" href="<?= e(url('/admin/orders', ['status' => 'confirmed'])) ?>"><p class="label">To fulfil</p><p class="value"><?= (int) $counts['to_fulfil'] ?></p><p class="sub">confirmed → packed</p></a>
  <a class="kpi<?= $counts['awaiting_verification'] ? ' warn-kpi' : '' ?>" href="<?= e(url('/admin/orders', ['payment' => 'pending_verification'])) ?>"><p class="label">Payments to verify</p><p class="value"><?= (int) $counts['awaiting_verification'] ?></p><p class="sub">bank transfers</p></a>
  <a class="kpi<?= $counts['low'] ? ' warn-kpi' : '' ?>" href="<?= e(url('/admin/inventory', ['level' => 'low'])) ?>"><p class="label">Low stock</p><p class="value"><?= (int) $counts['low'] ?></p><p class="sub">variants</p></a>
  <a class="kpi<?= $counts['out'] ? ' alert-kpi' : '' ?>" href="<?= e(url('/admin/inventory', ['level' => 'out'])) ?>"><p class="label">Out of stock</p><p class="value"><?= (int) $counts['out'] ?></p><p class="sub">variants</p></a>
  <a class="kpi" href="<?= e(url('/admin/customers')) ?>"><p class="label">Customers</p><p class="value"><?= (int) $counts['customers'] ?></p><p class="sub">registered</p></a>
  <a class="kpi" href="<?= e(url('/admin/products')) ?>"><p class="label">Products</p><p class="value"><?= (int) $counts['products'] ?></p><p class="sub"><?= (int) $counts['drafts'] ?> drafts</p></a>
</section>

<div class="grid-2 grid-2-1">
  <?php if ($canAnalytics): ?>
  <section class="card" aria-labelledby="sales-title">
    <div class="card-head"><h2 id="sales-title">Net sales by day</h2><span class="definition"><?= e(date_only($range['from_utc'])) ?> – <?= e(date_only($range['to_utc'])) ?></span></div>
    <?php
      $points = array_map(static fn ($d) => ['label' => date('D j M', strtotime($d['date'])), 'short' => date('j M', strtotime($d['date'])), 'value' => $d['sales'], 'display' => money($d['sales']) . ' · ' . $d['orders'] . ' orders'], $daily);
      $peak = $daily ? array_reduce($daily, static fn ($c, $d) => $c === null || $d['sales'] > $c['sales'] ? $d : $c) : null;
    ?>
    <?= App\Core\View::partial('admin/_bar-chart', ['points' => $points, 'title' => 'Net sales per day', 'peak' => $peak && $peak['sales'] ? money($peak['sales']) . ' on ' . date('j M', strtotime($peak['date'])) : 'no sales in range']) ?>
    <p class="definition mt-2"><strong>Definitions.</strong> Gross: <?= e(Reports::DEFINITIONS['gross']) ?> Net: <?= e(Reports::DEFINITIONS['net']) ?> Revenue: <?= e(Reports::DEFINITIONS['revenue']) ?> Sandbox test orders are always excluded<?= App\Core\Config::isProduction() ? ', as is demo data' : '; demo data is included in development only' ?>.</p>
  </section>
  <?php endif; ?>
  <section class="card" aria-labelledby="status-title">
    <div class="card-head"><h2 id="status-title">Orders by status</h2></div>
    <?php $maxN = max(1, ...array_map(static fn ($s) => (int) $s['n'], $statuses ?: [['n' => 1]])); ?>
    <?php if ($statuses): ?>
      <div class="hbar">
        <?php foreach ($statuses as $s): ?>
          <a href="<?= e(url('/admin/orders', ['status' => $s['status']])) ?>"><?= e(OrderState::label($s['status'])) ?></a>
          <progress max="<?= $maxN ?>" value="<?= (int) $s['n'] ?>" aria-label="<?= e(OrderState::label($s['status'])) ?>: <?= (int) $s['n'] ?> orders"></progress>
          <span class="num"><?= (int) $s['n'] ?></span>
        <?php endforeach; ?>
      </div>
    <?php else: ?><p class="muted small">No orders in this period.</p><?php endif; ?>
  </section>
</div>

<div class="grid-2 grid-2-1">
  <section class="card" aria-labelledby="recent-title">
    <div class="card-head"><h2 id="recent-title">Recent orders</h2><a class="link-arrow" href="<?= e(url('/admin/orders')) ?>">All orders</a></div>
    <?php if ($recent): ?>
    <table class="admin-table stack-sm">
      <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col" class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr>
          <td data-label="Order"><a href="<?= e(url('/admin/orders/' . $o['id'])) ?>"><?= e($o['order_number']) ?></a><?= $o['is_test'] ? ' <span class="tag tag-muted">test</span>' : '' ?><?= $o['is_demo'] ? ' <span class="tag tag-muted">demo</span>' : '' ?><br><span class="xsmall muted"><?= e(dt($o['placed_at'])) ?></span></td>
          <td data-label="Customer"><?= e($o['customer_name']) ?></td>
          <td data-label="Status"><span class="status-pill s-<?= e($o['status']) ?>"><?= e(OrderState::label($o['status'])) ?></span></td>
          <td data-label="Payment"><span class="status-pill s-<?= e($o['payment_status']) ?>"><?= e(status_label($o['payment_status'])) ?></span></td>
          <td data-label="Total" class="num"><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="muted small">No orders yet.</p><?php endif; ?>
  </section>
  <?php if ($canAnalytics): ?>
  <section class="card" aria-labelledby="best-title">
    <div class="card-head"><h2 id="best-title">Best sellers</h2><a class="link-arrow" href="<?= e(url('/admin/reports', ['from' => $range['from'], 'to' => $range['to']])) ?>">Reports</a></div>
    <?php if ($top): $maxU = max(1, ...array_map(static fn ($t) => (int) $t['units'], $top)); ?>
      <div class="hbar">
        <?php foreach ($top as $t): ?>
          <span class="small"><?= e($t['product_title']) ?></span>
          <progress max="<?= $maxU ?>" value="<?= (int) $t['units'] ?>" aria-label="<?= e($t['product_title']) ?>: <?= (int) $t['units'] ?> units"></progress>
          <span class="num small"><?= (int) $t['units'] ?></span>
        <?php endforeach; ?>
      </div>
    <?php else: ?><p class="muted small">No sales in this period.</p><?php endif; ?>
  </section>
  <?php endif; ?>
</div>
