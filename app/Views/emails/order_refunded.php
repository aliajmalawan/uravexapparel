<?php $subject = ($full ? 'Refund issued — ' : 'Partial refund issued — ') . $order['order_number']; ?>
<h1 style="font-size:22px;margin:0 0 12px;">Refund issued</h1>
<p>Hi <?= e($order['customer_name']) ?>, we have issued a <?= $full ? '' : 'partial ' ?>refund of <strong><?= e(money((int) $amount, $order['currency'])) ?></strong> for order <strong><?= e($order['order_number']) ?></strong>.</p>
<p>Depending on your bank or payment method it may take a few working days to appear.</p>
<?= App\Core\View::partial('emails/_button', ['href' => $orderUrl, 'label' => 'View order']) ?>
