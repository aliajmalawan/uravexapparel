<?php /** @var array $o */ $ship = $o['shipping']; $mine = $o['my_shipment']; $canTrack = in_array($o['status'], ['confirmed', 'processing', 'packed', 'shipped'], true); ?>
<header class="container page-head"><nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="<?= e(url('/seller/orders')) ?>">Orders</a></li><li aria-current="page"><?= e($o['order_number']) ?></li></ol></nav><h1>Order <?= e($o['order_number']) ?></h1></header>
<div class="container account-layout seller-panel">
  <?= App\Core\View::partial('seller/_nav', ['seller' => $seller]) ?>
  <div>
    <section class="panel">
      <p><span class="status-pill s-<?= e($o['status']) ?>"><?= e(App\Services\OrderState::label($o['status'])) ?></span> <span class="small muted">placed <?= e(dt($o['placed_at'])) ?></span></p>
      <h2>Your items</h2>
      <table class="admin-table stack-sm">
        <thead><tr><th scope="col">Item</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Line total</th><th scope="col" class="num">Commission</th><th scope="col" class="num">Your earnings</th></tr></thead>
        <tbody>
        <?php foreach ($o['items'] as $it): ?>
          <tr><td data-label="Item"><?= e($it['product_title']) ?><br><span class="xsmall muted"><?= e($it['variant_label']) ?> · <?= e($it['sku']) ?><?= $it['refunded_quantity'] ? ' · ' . (int) $it['refunded_quantity'] . ' refunded' : '' ?></span></td>
            <td data-label="Qty" class="num"><?= (int) $it['quantity'] ?></td>
            <td data-label="Line total" class="num"><?= e(money((int) $it['line_total_minor'], $o['currency'])) ?></td>
            <td data-label="Commission" class="num">−<?= e(money((int) $it['commission_minor'], $o['currency'])) ?></td>
            <td data-label="Your earnings" class="num"><strong><?= e(money((int) $it['seller_amount_minor'], $o['currency'])) ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <div class="cards-2">
      <section class="panel"><h2>Ship to</h2><p class="small"><?= e($ship['full_name'] ?? '') ?><br><?= e($ship['line1'] ?? '') ?><?= !empty($ship['line2']) ? '<br>' . e($ship['line2']) : '' ?><br><?= e(App\Services\Region::locality($ship)) ?><br><?= e(App\Services\Region::COUNTRY_NAME) ?><br><?= e($ship['phone'] ?? '') ?></p><p class="xsmall muted">Use this address only to ship this order.</p></section>
      <section class="panel" id="tracking">
        <h2>Tracking</h2>
        <?php if ($mine): ?><p class="small">Shipped with <strong><?= e($mine['courier']) ?></strong> — <?= $mine['tracking_url'] ? '<a href="' . e($mine['tracking_url']) . '" target="_blank" rel="noopener noreferrer">' . e($mine['tracking_number']) . '</a>' : e($mine['tracking_number']) ?><br><span class="xsmall muted"><?= e(dt($mine['shipped_at'])) ?></span></p><?php endif; ?>
        <?php if ($canTrack): ?>
          <form method="post" action="<?= e(url('/seller/orders/' . $o['order_number'] . '/tracking')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field"><label for="courier">Carrier</label><select class="select" id="courier" name="courier" required><?php foreach ($couriers as $c): ?><option<?= ($mine['courier'] ?? '') === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select><?= error_text('courier') ?></div>
            <div class="field"><label for="tracking_number">Tracking number</label><input class="input" id="tracking_number" name="tracking_number" required maxlength="80" value="<?= e(old('tracking_number', $mine['tracking_number'] ?? '')) ?>"<?= error_attrs('tracking_number') ?>><?= error_text('tracking_number') ?></div>
            <button class="btn btn-dark btn-sm" type="submit"><?= $mine ? 'Update tracking' : 'Save tracking' ?></button>
          </form>
        <?php elseif (!$mine): ?><p class="small muted">—</p><?php endif; ?>
      </section>
    </div>
    <p class="small muted">Earnings for this order become available <?= (int) App\Services\Marketplace::setting('hold_days') ?> days after it is marked delivered.</p>
  </div>
</div>
