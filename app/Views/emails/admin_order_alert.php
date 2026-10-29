<?php $subject = '[URAVEX] ' . $headline . ' — ' . $order['order_number'] . ' (' . money((int) $order['total_minor'], $order['currency']) . ')'; ?>
<h1 style="font-size:20px;margin:0 0 12px;"><?= e($headline) ?></h1>
<p>Order <strong><?= e($order['order_number']) ?></strong> · <?= e($order['customer_name']) ?> · payment: <?= e($order['payment_method']) ?> · status: <?= e(App\Services\OrderState::label($order['status'])) ?></p>
<?= App\Core\View::partial('emails/_order', compact('order', 'items', 'shipping')) ?>
<?= App\Core\View::partial('emails/_button', ['href' => $adminUrl, 'label' => 'Open in admin']) ?>
