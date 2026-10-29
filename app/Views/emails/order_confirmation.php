<?php
$awaiting = $order['status'] === 'awaiting_payment';
$subject = ($awaiting ? 'Order received — ' : 'Order confirmed — ') . $order['order_number'];
?>
<h1 style="font-size:22px;margin:0 0 12px;"><?= $awaiting ? 'We received your order' : 'Thank you for your order' ?></h1>
<p>Hi <?= e($order['customer_name']) ?>, your order <strong><?= e($order['order_number']) ?></strong> has been placed.</p>
<?php if ($order['payment_method'] === 'cod'): ?>
  <p>Please keep <strong><?= e(money((int) $order['total_minor'], $order['currency'])) ?></strong> ready in cash for the courier. We'll email you when it ships.</p>
<?php elseif ($order['payment_method'] === 'bank_transfer' && !empty($bank)): ?>
  <p>Please transfer <strong><?= e(money((int) $order['total_minor'], $order['currency'])) ?></strong> to:</p>
  <p style="background:#F5F3EE;padding:12px;font-size:14px;">
    Bank: <?= e($bank['bank_name'] ?? '') ?><br>Account title: <?= e($bank['account_title'] ?? '') ?><br>
    <?php if (!empty($bank['account_number'])): ?>Account no.: <?= e($bank['account_number']) ?><br><?php endif; ?>
    <?php if (!empty($bank['iban'])): ?>IBAN: <?= e($bank['iban']) ?><br><?php endif; ?>
    Reference: <?= e($order['order_number']) ?>
  </p>
  <p>Then submit your transaction ID from your order page. We confirm your order once the payment is verified.</p>
<?php elseif (!empty($crypto)): ?>
  <p>To complete your order, send <strong><?= e(rtrim(rtrim((string) $crypto['amount'], '0'), '.') . ' ' . $crypto['asset']) ?></strong> on the <strong><?= e($crypto['network']) ?></strong> network to:</p>
  <p style="background:#F5F3EE;padding:12px;font-size:14px;font-family:Consolas,monospace;word-break:break-all;"><?= e($crypto['address']) ?></p>
  <p>Use only the <?= e($crypto['network']) ?> network — funds sent on another network cannot be recovered. Then submit your transaction hash (TXID) from your order page. We confirm your order once the payment is verified on the blockchain.</p>
<?php elseif ($awaiting): ?>
  <p>We are waiting for confirmation from the payment provider.</p>
<?php endif; ?>
<?= App\Core\View::partial('emails/_order', compact('order', 'items', 'shipping')) ?>
<?= App\Core\View::partial('emails/_button', ['href' => $orderUrl, 'label' => 'View your order']) ?>
