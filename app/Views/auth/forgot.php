<div class="auth-wrap">
  <div class="auth-card">
    <img class="auth-logo" src="<?= e(brand_logo()) ?>" alt="URAVEX Apparel" width="200" height="100">
    <h1>Reset password</h1>
    <p class="center small muted">Enter your account email and we'll send a secure link to choose a new password.</p>
    <form method="post" action="<?= e(url('/forgot-password')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email</label>
        <input class="input" id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email')) ?>"<?= error_attrs('email') ?>>
        <?= error_text('email') ?>
      </div>
      <button class="btn btn-dark btn-block" type="submit">Send reset link</button>
    </form>
    <hr>
    <p class="center small"><a href="<?= e(url('/login')) ?>">Back to sign in</a></p>
  </div>
</div>
