<?php
$titles = ['approved' => 'Congratulations — your shop is approved!', 'rejected' => 'Your seller application was not approved', 'suspended' => 'Your URAVEX shop has been suspended'];
$subject = ($seller['status'] === 'approved' ? 'Your shop "' . $seller['shop_name'] . '" is approved on URAVEX' : ($titles[$seller['status']] ?? 'Seller account update') . ' — ' . $seller['shop_name']);
?>
<h1 style="font-size:22px;margin:0 0 12px;"><?= e($titles[$seller['status']] ?? 'Seller account update') ?></h1>
<?php if ($seller['status'] === 'approved'): ?>
  <p>Good news! Your shop <strong><?= e($seller['shop_name']) ?></strong> has been reviewed and <strong>approved</strong>. You can now sell on URAVEX.</p>
  <p style="margin:16px 0 6px;"><strong>What you can do now:</strong></p>
  <ul style="margin:0 0 16px;padding-left:20px;line-height:1.7;">
    <li>Sign in to your seller panel<?= !empty($email) ? ' with <strong>' . e($email) . '</strong>' : '' ?> and the password you chose when you registered.</li>
    <li>Add your first products — each product is checked by our team before it goes live.</li>
    <li>Add your USDT wallet address in Shop settings so we can pay out your earnings.</li>
  </ul>
  <?php if (!empty($shopUrl)): ?><p>Your public shop page: <a href="<?= e($shopUrl) ?>" style="color:#8F5F17;"><?= e($shopUrl) ?></a></p><?php endif; ?>
<?php elseif ($seller['status'] === 'rejected'): ?>
  <p>Thank you for applying with <strong><?= e($seller['shop_name']) ?></strong>. We are unable to approve the application at this time.</p>
<?php else: ?>
  <p>Your shop <strong><?= e($seller['shop_name']) ?></strong> is suspended and its products are hidden from the store.</p>
<?php endif; ?>
<?php if (!empty($seller['status_note'])): ?><p style="background:#F5F3EE;padding:12px;"><?= e($seller['status_note']) ?></p><?php endif; ?>
<?= App\Core\View::partial('emails/_button', ['href' => $seller['status'] === 'approved' && !empty($loginUrl) ? $loginUrl : $panelUrl, 'label' => $seller['status'] === 'approved' ? 'Sign in to your seller panel' : 'Open seller panel']) ?>