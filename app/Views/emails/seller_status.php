<?php
$titles = ['approved' => 'Your URAVEX shop is approved', 'rejected' => 'Your seller application was not approved', 'suspended' => 'Your URAVEX shop has been suspended'];
$subject = ($titles[$seller['status']] ?? 'Seller account update') . ' — ' . $seller['shop_name'];
?>
<h1 style="font-size:22px;margin:0 0 12px;"><?= e($titles[$seller['status']] ?? 'Seller account update') ?></h1>
<?php if ($seller['status'] === 'approved'): ?>
  <p>Welcome! <strong><?= e($seller['shop_name']) ?></strong> can now sell on URAVEX. Add your first products from your seller panel — each product is reviewed before it goes live.</p>
<?php elseif ($seller['status'] === 'rejected'): ?>
  <p>Thank you for applying with <strong><?= e($seller['shop_name']) ?></strong>. We are unable to approve the application at this time.</p>
<?php else: ?>
  <p>Your shop <strong><?= e($seller['shop_name']) ?></strong> is suspended and its products are hidden from the store.</p>
<?php endif; ?>
<?php if (!empty($seller['status_note'])): ?><p style="background:#F5F3EE;padding:12px;"><?= e($seller['status_note']) ?></p><?php endif; ?>
<?= App\Core\View::partial('emails/_button', ['href' => $panelUrl, 'label' => 'Open seller panel']) ?>
