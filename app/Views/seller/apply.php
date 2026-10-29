<?php /** @var ?array $existing @var bool $open */ ?>
<header class="container page-head center"><span class="eyebrow">Marketplace</span><h1>Sell on URAVEX</h1>
  <p class="muted">Open your own shop on URAVEX and reach customers across the United States.</p></header>
<div class="container container-narrow">
  <section class="cards-3 sell-steps" aria-label="How selling works">
    <div class="panel"><h2 class="small">1. Apply</h2><p class="small muted">Tell us about your brand. We review every shop.</p></div>
    <div class="panel"><h2 class="small">2. List &amp; ship</h2><p class="small muted">Add products (reviewed before they go live). Ship orders and add tracking.</p></div>
    <div class="panel"><h2 class="small">3. Get paid</h2><p class="small muted">Earn <?= e(rtrim(rtrim(bcsub('100', $commission, 2), '0'), '.')) ?>% of each sale. Withdraw in USDT (TRC20) <?= (int) $holdDays ?> days after delivery.</p></div>
  </section>

  <?php if ($existing && $existing['status'] === 'rejected'): ?>
    <div class="alert alert-error"><p>Your previous application was not approved<?= $existing['status_note'] ? ': ' . e($existing['status_note']) : '' ?>. You can update it and apply again.</p></div>
  <?php endif; ?>

  <?php if (!$open): ?>
    <div class="alert alert-info"><p>Seller applications are closed at the moment. Please check back soon.</p></div>
  <?php elseif (!$user): ?>
    <form class="panel" id="seller-signup" method="post" action="<?= e(url('/seller/register')) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="text" name="website" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">
      <h2>Create your seller account</h2>
      <p class="small muted">One form: your login details and your shop. You can sign in at <a href="<?= e(url('/seller/login')) ?>">Seller sign in</a> any time with this email and password.</p>

      <fieldset class="signup-group">
        <legend>1. Your account</legend>
        <div class="form-grid cols-2">
          <div class="field"><label for="name">Your full name</label><input class="input" id="name" name="name" autocomplete="name" required maxlength="120" value="<?= e(old('name')) ?>"<?= error_attrs('name') ?>><?= error_text('name') ?></div>
          <div class="field"><label for="phone">Phone number</label><input class="input" id="phone" name="phone" type="tel" autocomplete="tel" required maxlength="32" placeholder="(555) 123-4567" value="<?= e(old('phone')) ?>"<?= error_attrs('phone') ?>><?= error_text('phone') ?></div>
          <div class="field span-2"><label for="email">Email</label><input class="input" id="email" name="email" type="email" autocomplete="email" required maxlength="191" value="<?= e(old('email')) ?>"<?= error_attrs('email') ?>><?= error_text('email') ?></div>
          <div class="field"><label for="password">Password</label><div class="password-wrap"><input class="input" id="password" name="password" type="password" autocomplete="new-password" required maxlength="128"<?= error_attrs('password') ?>><button type="button" data-toggle-password aria-controls="password">Show</button></div><p class="hint">At least 8 characters with a letter and a number.</p><?= error_text('password') ?></div>
          <div class="field"><label for="password_confirmation">Confirm password</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="128"></div>
        </div>
      </fieldset>

      <fieldset class="signup-group">
        <legend>2. Your shop</legend>
        <div class="field"><label for="shop_name">Shop name</label><input class="input" id="shop_name" name="shop_name" required maxlength="80" value="<?= e(old('shop_name')) ?>"<?= error_attrs('shop_name') ?>><?= error_text('shop_name') ?></div>
        <div class="field"><label for="description">About your shop</label><textarea class="textarea" id="description" name="description" rows="4" required maxlength="2000" placeholder="What do you make or sell? Where do you ship from?"<?= error_attrs('description') ?>><?= e(old('description')) ?></textarea><?= error_text('description') ?></div>
        <div class="field"><label for="payout_address">USDT TRC20 payout address <span class="muted">(optional — you can add it later)</span></label><input class="input wallet" id="payout_address" name="payout_address" maxlength="60" autocomplete="off" spellcheck="false" placeholder="T…" value="<?= e(old('payout_address')) ?>"<?= error_attrs('payout_address') ?>><?= error_text('payout_address') ?></div>
      </fieldset>

      <label class="check"><input type="checkbox" name="agree" value="1" required> I agree that URAVEX keeps <?= e(rtrim(rtrim($commission, '0'), '.')) ?>% commission on each sale, that I ship my own orders and add tracking, that customer shipping fees stay with URAVEX, and that refunds are deducted from my earnings.</label>
      <?= error_text('agree') ?>
      <button class="btn btn-primary btn-block mt-4" type="submit">Create seller account</button>
      <p class="xsmall muted mt-2 center">You never pay to sell — no deposits or upfront fees. Your shop goes live after a quick review.</p>
      <hr>
      <p class="small center">Already have a URAVEX account? <a href="<?= e(url('/login')) ?>">Sign in</a> and come back to this page to apply. Already a seller? <a href="<?= e(url('/seller/login')) ?>">Seller sign in</a></p>
    </form>
  <?php else: ?>
    <form class="panel" method="post" action="<?= e(url('/sell')) ?>" novalidate>
      <?= csrf_field() ?>
      <h2>Shop application</h2>
      <div class="field"><label for="shop_name">Shop name</label><input class="input" id="shop_name" name="shop_name" required maxlength="80" value="<?= e(old('shop_name', $existing['shop_name'] ?? '')) ?>"<?= error_attrs('shop_name') ?>><?= error_text('shop_name') ?></div>
      <div class="field"><label for="description">About your shop</label><textarea class="textarea" id="description" name="description" rows="5" required maxlength="2000" placeholder="What do you make or sell? Where do you ship from?"<?= error_attrs('description') ?>><?= e(old('description', $existing['description'] ?? '')) ?></textarea><?= error_text('description') ?></div>
      <div class="form-grid cols-2">
        <div class="field"><label for="contact_phone">Contact phone <span class="muted">(optional)</span></label><input class="input" id="contact_phone" name="contact_phone" type="tel" maxlength="32" value="<?= e(old('contact_phone', $existing['contact_phone'] ?? '')) ?>"></div>
        <div class="field"><label for="payout_address">USDT TRC20 payout address <span class="muted">(can add later)</span></label><input class="input wallet" id="payout_address" name="payout_address" maxlength="60" autocomplete="off" spellcheck="false" placeholder="T…" value="<?= e(old('payout_address', $existing['payout_address'] ?? '')) ?>"<?= error_attrs('payout_address') ?>><?= error_text('payout_address') ?></div>
      </div>
      <label class="check"><input type="checkbox" name="agree" value="1" required> I agree that URAVEX keeps <?= e(rtrim(rtrim($commission, '0'), '.')) ?>% commission on each sale, that I ship my own orders and add tracking, that customer shipping fees stay with URAVEX, and that refunds are deducted from my earnings.</label>
      <?= error_text('agree') ?>
      <button class="btn btn-primary mt-4" type="submit">Submit application</button>
      <p class="xsmall muted mt-2">You never pay to sell: there are no deposits or fees upfront. URAVEX only keeps its commission from completed sales.</p>
    </form>
  <?php endif; ?>
</div>
