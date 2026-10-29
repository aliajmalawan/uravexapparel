<div class="container container-narrow section">
  <span class="eyebrow">Order status</span>
  <h1>Track your order</h1>
  <?php if ($order): ?>
    <section class="panel" aria-label="Order status">
      <?= App\Core\View::partial('partials/order-tracking', ['o' => $order]) ?>
    </section>
    <p><a class="link-arrow" href="<?= e(url('/track-order')) ?>">Track another order</a></p>
  <?php else: ?>
    <p class="muted">Enter your order number (e.g. UVX-100245) and the email used at checkout.</p>
    <form class="panel" method="post" action="<?= e(url('/track-order')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid cols-2">
        <div class="field"><label for="order_number">Order number</label><input class="input" id="order_number" name="order_number" required placeholder="UVX-100000" value="<?= e(old('order_number')) ?>"<?= error_attrs('order_number') ?>><?= error_text('order_number') ?></div>
        <div class="field"><label for="email">Email</label><input class="input" id="email" name="email" type="email" required autocomplete="email" value="<?= e(old('email')) ?>"<?= error_attrs('email') ?>><?= error_text('email') ?></div>
      </div>
      <button class="btn btn-dark" type="submit">Find my order</button>
    </form>
    <p class="small muted">Have an account? <a href="<?= e(url('/account/orders')) ?>">See all your orders</a>.</p>
  <?php endif; ?>
</div>
