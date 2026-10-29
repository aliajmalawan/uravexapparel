<?php
/** @var string $portal customer|seller|admin @var array $p */
$portal ??= 'customer';
$p ??= App\Controllers\Auth\AuthController::PORTALS['customer'];
$intro = [
    'customer' => null,
    'seller' => 'Sign in to manage your shop, orders and earnings.',
    'admin' => 'Staff only. Sign in to the URAVEX dashboard.',
][$portal];
?>
<div class="auth-wrap<?= $portal === 'admin' ? ' auth-admin' : '' ?>">
  <div class="auth-card">
    <img class="auth-logo" src="<?= e(brand_logo()) ?>" alt="URAVEX Apparel" width="200" height="100">
    <?php if ($portal !== 'customer'): ?><span class="auth-badge"><?= $portal === 'admin' ? 'Admin' : 'Seller centre' ?></span><?php endif; ?>
    <h1><?= e($p['title']) ?></h1>
    <?php if ($intro): ?><p class="small muted center"><?= e($intro) ?></p><?php endif; ?>
    <?php if ($portal === 'admin' && ($warn = App\Core\Session::getFlash('warning'))): ?><div class="alert alert-warning" role="status"><p><?= e((string) $warn) ?></p></div><?php endif; ?>
    <?php if ($msg = field_error('email')): ?>
      <?php /* The store layout already shows the message as a flash; the admin (minimal) layout does not. */ ?>
      <?php if ($portal === 'admin'): ?><div class="alert alert-error" role="alert"><p><?= e($msg) ?></p></div><?php endif; ?>
      <?php if ($portal === 'customer' && str_contains($msg, 'admin login')): ?><p class="small center"><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/login')) ?>">Go to admin login</a></p><?php endif; ?>
      <?php if ($portal === 'seller' && str_contains($msg, 'No seller shop')): ?><p class="small center"><a class="btn btn-outline btn-sm" href="<?= e(url('/sell')) ?>">Open a shop</a></p><?php endif; ?>
    <?php endif; ?>
    <form method="post" action="<?= e(url($p['path'])) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email</label>
        <input class="input" id="email" name="email" type="email" autocomplete="<?= $portal === 'admin' ? 'username' : 'email' ?>" required autofocus value="<?= e(old('email')) ?>"<?= error_attrs('email') ?>>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input class="input" id="password" name="password" type="password" autocomplete="current-password" required maxlength="128"<?= error_attrs('password') ?>>
          <button type="button" data-toggle-password aria-controls="password">Show</button>
        </div>
        <?= error_text('password') ?>
      </div>
      <p class="small"><a href="<?= e(url('/forgot-password')) ?>">Forgot your password?</a></p>
      <button class="btn <?= $portal === 'admin' ? 'btn-primary' : 'btn-dark' ?> btn-block" type="submit">Sign in</button>
    </form>
    <hr>
    <?php if ($portal === 'customer'): ?>
      <p class="center small">New to URAVEX? <a href="<?= e(url('/register')) ?>">Create an account</a></p>
      <p class="center xsmall muted">Selling on URAVEX? <a href="<?= e(url('/seller/login')) ?>">Seller sign in</a> · <a href="<?= e(url('/sell')) ?>">Open a shop</a></p>
    <?php elseif ($portal === 'seller'): ?>
      <p class="center small">New seller? <a href="<?= e(url('/seller/register')) ?>">Create a seller account</a></p>
      <p class="center xsmall muted">Shopping instead? <a href="<?= e(url('/login')) ?>">Customer sign in</a></p>
    <?php else: ?>
      <p class="center xsmall muted"><a href="<?= e(url('/')) ?>">← Back to the store</a></p>
    <?php endif; ?>
  </div>
</div>
