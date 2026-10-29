<div class="auth-wrap">
  <div class="auth-card">
    <img class="auth-logo" src="<?= e(brand_logo()) ?>" alt="URAVEX Apparel" width="200" height="100">
    <h1>Choose a new password</h1>
    <?php if (!$valid): ?>
      <div class="alert alert-error" role="alert"><p>This reset link is invalid, expired or has already been used.</p></div>
      <a class="btn btn-dark btn-block" href="<?= e(url('/forgot-password')) ?>">Request a new link</a>
    <?php else: ?>
      <form method="post" action="<?= e(url('/reset-password')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
          <label for="password">New password</label>
          <div class="password-wrap">
            <input class="input" id="password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="128" aria-describedby="pw-hint"<?= error_attrs('password') ?>>
            <button type="button" data-toggle-password aria-controls="password">Show</button>
          </div>
          <p class="hint" id="pw-hint">At least 8 characters, including a letter and a number.</p>
          <?= error_text('password') ?>
        </div>
        <div class="field">
          <label for="password_confirmation">Confirm new password</label>
          <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="128">
        </div>
        <button class="btn btn-dark btn-block" type="submit">Update password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
