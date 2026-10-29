<?php $subject = 'Your order ' . $order['order_number'] . ' has shipped'; ?>
<h1 style="font-size:22px;margin:0 0 12px;">Your order is on its way</h1>
<p>Hi <?= e($order['customer_name']) ?>, order <strong><?= e($order['order_number']) ?></strong> has been handed to <strong><?= e($courier) ?></strong>.</p>
<p style="background:#F5F3EE;padding:12px;">Tracking number: <strong><?= e($tracking) ?></strong></p>
<?php if (!empty($trackingUrl)): ?><?= App\Core\View::partial('emails/_button', ['href' => $trackingUrl, 'label' => 'Track shipment']) ?><?php endif; ?>
<?php if ($order['payment_method'] === 'cod'): ?><p>Please keep <strong><?= e(money((int) $order['total_minor'], $order['currency'])) ?></strong> ready in cash.</p><?php endif; ?>
<?= App\Core\View::partial('emails/_order', compact('order', 'items', 'shipping')) ?>
