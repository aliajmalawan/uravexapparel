<?php
$ship = $o['shipping'];
$bill = $o['billing'];
$isInvoice = $type === 'invoice';
?>
<article class="print-doc">
  <header class="doc-head">
    <img src="<?= e(brand_logo()) ?>" alt="URAVEX Apparel" width="180" height="90">
    <div class="small">
      <h1 class="display"><?= $isInvoice ? 'Invoice' : 'Packing slip' ?></h1>
      <strong><?= e($o['order_number']) ?></strong><br>
      Date: <?= e(date_only($o['placed_at'])) ?><br>
      <?php if ($isInvoice): ?>Payment: <?= e(App\Payments\Registry::label($o['payment_method'])) ?> — <?= e(status_label($o['payment_status'])) ?><br><?php endif; ?>
      <?= e($store['name'] ?? 'URAVEX Apparel') ?><?= !empty($store['support_email']) ? ' · ' . e($store['support_email']) : '' ?>
    </div>
  </header>
  <div class="cards-2">
    <div class="small"><strong>Ship to</strong><br><?= e($ship['full_name'] ?? '') ?><br><?= e($ship['line1'] ?? '') ?><?= !empty($ship['line2']) ? '<br>' . e($ship['line2']) : '' ?><br><?= e(App\Services\Region::locality($ship)) ?><br><?= e($ship['phone'] ?? '') ?></div>
    <?php if ($isInvoice): ?><div class="small"><strong>Bill to</strong><br><?= e($bill['full_name'] ?? $o['customer_name']) ?><br><?= e($o['email']) ?><br><?= e(App\Services\Region::locality($bill)) ?></div><?php endif; ?>
  </div>
  <table class="mt-4">
    <thead><tr><th>Item</th><th>SKU</th><th class="num">Qty</th><?php if ($isInvoice): ?><th class="num">Unit</th><th class="num">Total</th><?php else: ?><th>Packed ✓</th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($o['items'] as $it): ?>
        <tr><td><?= e($it['product_title']) ?><br><span class="xsmall"><?= e($it['variant_label']) ?></span></td><td><?= e($it['sku']) ?></td><td class="num"><?= (int) $it['quantity'] ?></td>
          <?php if ($isInvoice): ?><td class="num"><?= e(money((int) $it['unit_price_minor'], $o['currency'] ?? null)) ?></td><td class="num"><?= e(money((int) $it['line_total_minor'], $o['currency'] ?? null)) ?></td><?php else: ?><td>☐</td><?php endif; ?></tr>
      <?php endforeach; ?>
    </tbody>
    <?php if ($isInvoice): ?>
    <tfoot>
      <tr><td colspan="4" class="num">Subtotal</td><td class="num"><?= e(money((int) $o['subtotal_minor'], $o['currency'] ?? null)) ?></td></tr>
      <?php if ($o['discount_minor'] > 0): ?><tr><td colspan="4" class="num">Discount</td><td class="num">−<?= e(money((int) $o['discount_minor'], $o['currency'] ?? null)) ?></td></tr><?php endif; ?>
      <tr><td colspan="4" class="num">Delivery</td><td class="num"><?= e(money((int) $o['shipping_minor'], $o['currency'] ?? null)) ?></td></tr>
      <?php if ($o['tax_minor'] > 0): ?><tr><td colspan="4" class="num">Tax</td><td class="num"><?= e(money((int) $o['tax_minor'], $o['currency'] ?? null)) ?></td></tr><?php endif; ?>
      <tr><th colspan="4" class="num">Total</th><th class="num"><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></th></tr>
      <?php if ($o['refunded_minor'] > 0): ?><tr><td colspan="4" class="num">Refunded</td><td class="num">−<?= e(money((int) $o['refunded_minor'], $o['currency'] ?? null)) ?></td></tr><?php endif; ?>
      <?php if ($o['payment_method'] === 'cod' && $o['payment_status'] !== 'paid'): ?><tr><th colspan="4" class="num">Cash to collect</th><th class="num"><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></th></tr><?php endif; ?>
    </tfoot>
    <?php endif; ?>
  </table>
  <?php if ($o['customer_note']): ?><p class="small mt-4"><strong>Customer note:</strong> <?= e($o['customer_note']) ?></p><?php endif; ?>
  <p class="xsmall muted mt-4">Thank you for shopping with URAVEX Apparel.</p>
</article>
