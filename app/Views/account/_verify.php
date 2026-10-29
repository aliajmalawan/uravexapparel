<?php if ($user && !$user['email_verified_at']): ?>
  <div class="alert alert-warning">
    <p>Please verify <strong><?= e($user['email']) ?></strong>. Check your inbox for the link.</p>
    <form method="post" action="<?= e(url('/verify-email/resend')) ?>"><?= csrf_field() ?><button class="btn btn-link small" type="submit">Resend verification email</button></form>
  </div>
<?php endif; ?>
