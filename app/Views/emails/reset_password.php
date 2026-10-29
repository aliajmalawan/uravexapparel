<?php $subject = 'Reset your URAVEX password'; ?>
<h1 style="font-size:22px;margin:0 0 12px;">Reset your password</h1>
<p>Hi <?= e($name) ?>, we received a request to reset your password.</p>
<?= App\Core\View::partial('emails/_button', ['href' => $url, 'label' => 'Choose a new password']) ?>
<p style="font-size:13px;color:#666666;">This link expires in <?= (int) $minutes ?> minutes and works once. If you did not ask for this, ignore this email — your password will not change.</p>
