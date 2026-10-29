<?php $subject = 'Your URAVEX password was changed'; ?>
<h1 style="font-size:22px;margin:0 0 12px;">Password changed</h1>
<p>Hi <?= e($name) ?>, the password for your URAVEX account was just changed and other devices were signed out.</p>
<p>If this was not you, reset your password immediately and contact us.</p>
<?= App\Core\View::partial('emails/_button', ['href' => url('/forgot-password'), 'label' => 'Reset password']) ?>
