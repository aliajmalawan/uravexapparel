<?php $subject = 'Payment received — ' . $order['order_number']; ?>
<h1 style="font-size:22px;margin:0 0 12px;">Payment received</h1>
<p>Hi <?= e($order['customer_name']) ?>, we have received your payment for order <strong><?= e($order['order_number']) ?></strong>. Your order is confirmed and being prepared.</p>
<?= App\Core\View::partial('emails/_order', compact('order', 'items', 'shipping')) ?>
<?= App\Core\View::partial('emails/_button', ['href' => $orderUrl, 'label' => 'Track your order']) ?>
