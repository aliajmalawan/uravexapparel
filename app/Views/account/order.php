<?php $ship = $o['shipping']; ?>
<header class="container page-head"><nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="<?= e(url('/account/orders')) ?>">Orders</a></li><li aria-current="page"><?= e($o['order_number']) ?></li></ol></nav><h1>Order <?= e($o['order_number']) ?></h1></header>
<div class="container account-layout">
  <?= App\Core\View::partial('account/_nav') ?>
  <div>
    <section class="panel" aria-label="Order status"><?= App\Core\View::partial('partials/order-tracking', ['o' => $o]) ?></section>

    <?php if ($provider instanceof App\Payments\ManualPayment && $o['status'] === 'awaiting_payment'): ?>
      <?= App\Core\View::partial('partials/manual-payment', ['o' => $o, 'payment' => $payment, 'provider' => $provider, 'bank' => $bank]) ?>
    <?php endif; ?>

    <section class="panel">
      <h2>Items</h2>
      <ul class="mini-lines">
        <?php foreach ($o['items'] as $it): ?>
          <li><span class="mini-thumb"><img src="<?= e(media($it['image_path'], 400)) ?>" alt="" width="56" height="75" loading="lazy"><b><?= (int) $it['quantity'] ?></b></span>
            <span><?= e($it['product_title']) ?><br><span class="xsmall muted"><?= e($it['variant_label']) ?> · <?= e($it['sku']) ?><?= $it['refunded_quantity'] ? ' · ' . (int) $it['refunded_quantity'] . ' refunded' : '' ?></span></span>
            <span class="small"><?= e(money((int) $it['line_total_minor'], $o['currency'] ?? null)) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <dl class="summary-plain">
        <div class="row"><dt>Subtotal</dt><dd><?= e(money((int) $o['subtotal_minor'], $o['currency'] ?? null)) ?></dd></div>
        <?php if ($o['discount_minor'] > 0): ?><div class="row"><dt>Discount</dt><dd>−<?= e(money((int) $o['discount_minor'], $o['currency'] ?? null)) ?></dd></div><?php endif; ?>
        <div class="row"><dt>Delivery</dt><dd><?= (int) $o['shipping_minor'] ? e(money((int) $o['shipping_minor'], $o['currency'] ?? null)) : 'Free' ?></dd></div>
        <div class="row total"><dt>Total</dt><dd><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></dd></div>
        <?php if ($o['refunded_minor'] > 0): ?><div class="row"><dt>Refunded</dt><dd><?= e(money((int) $o['refunded_minor'], $o['currency'] ?? null)) ?></dd></div><?php endif; ?>
      </dl>
    </section>
    <div class="cards-2">
      <section class="panel"><h2>Delivery address</h2><p class="small"><?= e($ship['full_name'] ?? '') ?><br><?= e($ship['line1'] ?? '') ?><?= !empty($ship['line2']) ? '<br>' . e($ship['line2']) : '' ?><br><?= e(App\Services\Region::locality($ship)) ?><br><?= e($ship['phone'] ?? '') ?></p></section>
      <section class="panel"><h2>Payment</h2><p class="small"><?= e(App\Payments\Registry::label($o['payment_method'])) ?><br><span class="status-pill s-<?= e($o['payment_status']) ?>"><?= e(status_label($o['payment_status'])) ?></span></p><p class="small"><?= e($o['shipping_method']) ?></p></section>
    </div>
  </div>
</div>
