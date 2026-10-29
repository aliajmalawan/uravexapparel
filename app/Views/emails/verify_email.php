<?php $subject = 'Verify your URAVEX email address'; ?>
<h1 style="font-size:22px;margin:0 0 12px;">Confirm your email</h1>
<p>Hi <?= e($name) ?>, please confirm this email address for your URAVEX account.</p>
<?= App\Core\View::partial('emails/_button', ['href' => $url, 'label' => 'Verify email']) ?>
<p style="font-size:13px;color:#666666;">This link expires in 24 hours and can be used once. If you did not create an account, you can ignore this email.</p>
