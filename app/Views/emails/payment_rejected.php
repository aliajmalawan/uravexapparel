<?php $subject = 'Action needed: payment not verified — ' . $order['order_number']; ?>
<h1 style="font-size:22px;margin:0 0 12px;">We couldn't verify your payment</h1>
<p>Hi <?= e($order['customer_name']) ?>, we could not verify the payment for order <strong><?= e($order['order_number']) ?></strong>.</p>
<?php if (!empty($note)): ?><p style="background:#F5F3EE;padding:12px;"><?= e($note) ?></p><?php endif; ?>
<p>Please check the transaction (amount, network and wallet address) and resubmit the transaction hash from your order page, or reply to this email.</p>
<?= App\Core\View::partial('emails/_button', ['href' => $orderUrl, 'label' => 'Update payment details']) ?>
