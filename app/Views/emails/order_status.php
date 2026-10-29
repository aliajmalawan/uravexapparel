<?php
$label = App\Services\OrderState::label($status);
$subject = "Order {$order['order_number']}: {$label}";
$copy = match ($status) {
    'confirmed' => 'Your order is confirmed and will be prepared shortly.',
    'processing' => 'Our team has started preparing your order.',
    'delivered' => 'Your order has been delivered. We hope you love it.',
    'cancelled' => 'Your order has been cancelled. If you paid online, any refund will follow separately.',
    default => 'Your order status has been updated.',
};
?>
<h1 style="font-size:22px;margin:0 0 12px;">Order <?= e(strtolower($label)) ?></h1>
<p>Hi <?= e($order['customer_name']) ?>, <?= e($copy) ?></p>
<?php if (!empty($note)): ?><p style="background:#F5F3EE;padding:12px;"><?= e($note) ?></p><?php endif; ?>
<?= App\Core\View::partial('emails/_order', compact('order', 'items', 'shipping')) ?>
<?= App\Core\View::partial('emails/_button', ['href' => $orderUrl, 'label' => 'View order']) ?>
