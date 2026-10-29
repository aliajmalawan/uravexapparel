<header class="container page-head"><span class="eyebrow">My account</span><h1>Profile &amp; security</h1></header>
<div class="container account-layout">
  <?= App\Core\View::partial('account/_nav') ?>
  <div>
    <?= App\Core\View::partial('account/_verify', ['user' => $user]) ?>
    <form class="panel" method="post" action="<?= e(url('/account/profile')) ?>" novalidate>
      <?= csrf_field() ?>
      <h2>Personal details</h2>
      <div class="form-grid cols-2">
        <div class="field"><label for="name">Full name</label><input class="input" id="name" name="name" required autocomplete="name" maxlength="120" value="<?= e(old('name', $user['name'])) ?>"<?= error_attrs('name') ?>><?= error_text('name') ?></div>
        <div class="field"><label for="phone">Mobile number</label><input class="input" id="phone" name="phone" type="tel" required autocomplete="tel" value="<?= e(old('phone', $user['phone'])) ?>"<?= error_attrs('phone') ?>><?= error_text('phone') ?></div>
        <div class="field"><label for="email">Email</label><input class="input" id="email" name="email" type="email" required autocomplete="email" value="<?= e(old('email', $user['email'])) ?>"<?= error_attrs('email') ?>><?= error_text('email') ?><p class="hint">Changing your email requires your current password and re-verification.</p></div>
        <div class="field"><label for="cp1">Current password <span class="muted">(only to change email)</span></label><input class="input" id="cp1" name="current_password" type="password" autocomplete="current-password"<?= error_attrs('current_password') ?>><?= error_text('current_password') ?></div>
      </div>
      <button class="btn btn-dark" type="submit">Save details</button>
    </form>
    <form class="panel" method="post" action="<?= e(url('/account/password')) ?>" novalidate>
      <?= csrf_field() ?>
      <h2>Change password</h2>
      <div class="form-grid cols-2">
        <div class="field span-2"><label for="cp2">Current password</label><input class="input" id="cp2" name="current_password" type="password" required autocomplete="current-password"></div>
        <div class="field"><label for="np">New password</label><input class="input" id="np" name="password" type="password" required minlength="8" autocomplete="new-password"<?= error_attrs('password') ?>><?= error_text('password') ?></div>
        <div class="field"><label for="npc">Confirm new password</label><input class="input" id="npc" name="password_confirmation" type="password" required autocomplete="new-password"></div>
      </div>
      <p class="hint">Changing your password signs out all other devices.</p>
      <button class="btn btn-dark" type="submit">Update password</button>
    </form>
  </div>
</div>
