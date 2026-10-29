<?php
$earn = array_sum(array_map(static fn ($l) => (int) $l['seller_amount_minor'], $lines));
$subject = 'New paid order ' . $order['order_number'] . ' — ' . money($earn, $order['currency']) . ' earnings';
?>
<h1 style="font-size:22px;margin:0 0 12px;">You have a new order</h1>
<p>Hi <?= e($seller['shop_name']) ?>, order <strong><?= e($order['order_number']) ?></strong> has been paid and includes:</p>
<ul>
  <?php foreach ($lines as $l): ?><li><?= (int) $l['quantity'] ?> × <?= e($l['product_title']) ?> (<?= e($l['variant_label']) ?>) — your share <?= e(money((int) $l['seller_amount_minor'], $order['currency'])) ?></li><?php endforeach; ?>
</ul>
<p>Please ship these items and add the tracking number in your seller panel. Your earnings (<?= e(money($earn, $order['currency'])) ?>) become available to withdraw after the order is delivered and the hold period ends.</p>
<?= App\Core\View::partial('emails/_button', ['href' => $orderUrl, 'label' => 'View order']) ?>
