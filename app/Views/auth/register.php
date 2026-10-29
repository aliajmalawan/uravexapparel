<div class="auth-wrap">
  <div class="auth-card">
    <img class="auth-logo" src="<?= e(brand_logo()) ?>" alt="URAVEX Apparel" width="200" height="100">
    <h1>Create account</h1>
    <p class="center small muted">Track orders, save addresses and keep a wishlist.</p>
    <form method="post" action="<?= e(url('/register')) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="text" name="website" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="field">
        <label for="name">Full name</label>
        <input class="input" id="name" name="name" autocomplete="name" required maxlength="120" value="<?= e(old('name')) ?>"<?= error_attrs('name') ?>>
        <?= error_text('name') ?>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input class="input" id="email" name="email" type="email" autocomplete="email" required maxlength="191" value="<?= e(old('email')) ?>"<?= error_attrs('email') ?>>
        <?= error_text('email') ?>
      </div>
      <div class="field">
        <label for="phone">Mobile number</label>
        <input class="input" id="phone" name="phone" type="tel" autocomplete="tel" required maxlength="32" placeholder="(555) 123-4567" value="<?= e(old('phone')) ?>"<?= error_attrs('phone') ?>>
        <?= error_text('phone') ?>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input class="input" id="password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="128" aria-describedby="pw-hint"<?= error_attrs('password') ?>>
          <button type="button" data-toggle-password aria-controls="password">Show</button>
        </div>
        <p class="hint" id="pw-hint">At least 8 characters, including a letter and a number.</p>
        <?= error_text('password') ?>
      </div>
      <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="128">
      </div>
      <button class="btn btn-dark btn-block" type="submit">Create account</button>
      <p class="xsmall muted center mt-2">By creating an account you agree to our <a href="<?= e(url('/pages/terms')) ?>">terms</a> and <a href="<?= e(url('/pages/privacy-policy')) ?>">privacy policy</a>.</p>
    </form>
    <hr>
    <p class="center small">Already have an account? <a href="<?= e(url('/login')) ?>">Sign in</a></p>
  </div>
</div>
