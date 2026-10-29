<?php
/** @var array $o */
$awaitingBank = $provider instanceof App\Payments\ManualPayment && $o['status'] === 'awaiting_payment';
$awaitingOnline = $o['status'] === 'awaiting_payment' && !$awaitingBank;
$ship = $o['shipping'];
?>
<div class="container container-narrow section">
  <div class="center">
    <span class="eyebrow"><?= $o['status'] === 'cancelled' ? 'Order cancelled' : 'Thank you' ?></span>
    <h1><?= match (true) {
        $o['status'] === 'cancelled' => 'This order was cancelled',
        $awaitingBank => 'Order received — complete your payment',
        $awaitingOnline => 'Waiting for payment confirmation',
        default => 'Your order is confirmed',
    } ?></h1>
    <p class="muted">Order <strong><?= e($o['order_number']) ?></strong> · placed <?= e(dt($o['placed_at'])) ?>. A confirmation has been sent to <?= e($o['email']) ?>.</p>
    <hr class="gold-rule">
  </div>

  <?php if ($awaitingOnline): ?>
    <div class="alert alert-info" role="status"><p>We have not received confirmation from the payment provider yet. This page updates when you refresh. Your items are reserved until <?= e(dt($o['reservation_expires_at'])) ?>.</p></div>
    <?php if ($payment && $o['payment_method'] === 'sandbox' && $payment['status'] === 'pending'): ?>
      <p class="center"><a class="btn btn-primary" href="<?= e(url('/payments/sandbox/' . $payment['provider_reference'])) ?>">Return to payment page</a></p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($awaitingBank): ?>
    <?= App\Core\View::partial('partials/manual-payment', ['o' => $o, 'payment' => $payment, 'provider' => $provider, 'bank' => $bank]) ?>
  <?php endif; ?>

  <section class="panel" aria-labelledby="sum-title">
    <h2 id="sum-title">Order summary</h2>
    <ul class="mini-lines">
      <?php foreach ($o['items'] as $it): ?>
        <li><span class="mini-thumb"><img src="<?= e(media($it['image_path'], 400)) ?>" alt="" width="56" height="75" loading="lazy"><b><?= (int) $it['quantity'] ?></b></span>
          <span><?= e($it['product_title']) ?><br><span class="xsmall muted"><?= e($it['variant_label']) ?></span></span>
          <span class="small"><?= e(money((int) $it['unit_price_minor'] * (int) $it['quantity'], $o['currency'] ?? null)) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <dl class="summary-plain">
      <div class="row"><dt>Subtotal</dt><dd><?= e(money((int) $o['subtotal_minor'], $o['currency'] ?? null)) ?></dd></div>
      <?php if ($o['discount_minor'] > 0): ?><div class="row"><dt>Discount<?= $o['coupon_code'] ? ' (' . e($o['coupon_code']) . ')' : '' ?></dt><dd>−<?= e(money((int) $o['discount_minor'], $o['currency'] ?? null)) ?></dd></div><?php endif; ?>
      <div class="row"><dt>Delivery</dt><dd><?= (int) $o['shipping_minor'] === 0 ? 'Free' : e(money((int) $o['shipping_minor'], $o['currency'] ?? null)) ?></dd></div>
      <?php if ($o['tax_minor'] > 0): ?><div class="row"><dt>Tax</dt><dd><?= e(money((int) $o['tax_minor'], $o['currency'] ?? null)) ?></dd></div><?php endif; ?>
      <div class="row total"><dt>Total</dt><dd><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></dd></div>
    </dl>
    <div class="cards-2 mt-4">
      <div><h3 class="small">Delivering to</h3><p class="small"><?= e($ship['full_name'] ?? '') ?><br><?= e($ship['line1'] ?? '') ?><?= !empty($ship['line2']) ? '<br>' . e($ship['line2']) : '' ?><br><?= e(App\Services\Region::locality($ship)) ?><br><?= e($ship['phone'] ?? '') ?></p></div>
      <div><h3 class="small">Payment &amp; delivery</h3><p class="small"><?= e(App\Payments\Registry::label($o['payment_method'])) ?><br><?= e($o['shipping_method']) ?></p></div>
    </div>
  </section>

  <div class="flex flex-center">
    <?php if ($user && (int) $o['user_id'] === (int) $user['id']): ?><a class="btn btn-dark" href="<?= e(url('/account/orders/' . $o['order_number'])) ?>">View order in my account</a><?php endif; ?>
    <a class="btn btn-outline" href="<?= e(url('/shop')) ?>">Continue shopping</a>
  </div>
</div>
