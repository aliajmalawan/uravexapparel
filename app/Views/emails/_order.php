<?php /** @var array $order @var array $items @var array $shipping */ ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #E5E5E5;margin:16px 0;font-size:14px;">
  <?php foreach ($items as $it): ?>
    <tr>
      <td style="padding:10px 0;border-bottom:1px solid #E5E5E5;"><?= e($it['product_title']) ?><br><span style="color:#666666;font-size:12px;"><?= e($it['variant_label']) ?> × <?= (int) $it['quantity'] ?></span></td>
      <td align="right" style="padding:10px 0;border-bottom:1px solid #E5E5E5;white-space:nowrap;"><?= e(money((int) $it['line_total_minor'], $order['currency'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <tr><td style="padding:6px 0;color:#666666;">Subtotal</td><td align="right"><?= e(money((int) $order['subtotal_minor'], $order['currency'])) ?></td></tr>
  <?php if ($order['discount_minor'] > 0): ?><tr><td style="padding:6px 0;color:#666666;">Discount</td><td align="right">−<?= e(money((int) $order['discount_minor'], $order['currency'])) ?></td></tr><?php endif; ?>
  <tr><td style="padding:6px 0;color:#666666;">Delivery</td><td align="right"><?= (int) $order['shipping_minor'] ? e(money((int) $order['shipping_minor'], $order['currency'])) : 'Free' ?></td></tr>
  <tr><td style="padding:10px 0;font-weight:bold;border-top:1px solid #111111;">Total</td><td align="right" style="padding:10px 0;font-weight:bold;border-top:1px solid #111111;"><?= e(money((int) $order['total_minor'], $order['currency'])) ?></td></tr>
</table>
<?php if ($shipping): ?>
<p style="font-size:13px;color:#444444;margin:0 0 12px;"><strong>Delivering to</strong><br><?= e($shipping['full_name'] ?? '') ?>, <?= e($shipping['line1'] ?? '') ?>, <?= e($shipping['city'] ?? '') ?><br><?= e($shipping['phone'] ?? '') ?></p>
<?php endif; ?>
