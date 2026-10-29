<?php
/** @var array $v @var array $errors @var array $s */
$t = $s['totals'];
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="field-error" id="' . e($k) . '-err">' . e($errors[$k]) . '</p>' : '';
$inv = static fn (string $k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="' . e($k) . '-err"' : '';
$methodIcons = ['cod' => 'cash', 'bank_transfer' => 'bank', 'sandbox' => 'card', 'usdt_trc20' => 'coin', 'usdt_erc20' => 'coin', 'btc' => 'coin', 'eth' => 'coin'];
?>
<header class="container page-head">
  <nav class="breadcrumbs" aria-label="Checkout steps"><ol><li><a href="<?= e(url('/cart')) ?>">Bag</a></li><li aria-current="step">Checkout</li><li>Confirmation</li></ol></nav>
  <h1>Checkout</h1>
  <?php if (!$user): ?><p class="small muted">Have an account? <a href="<?= e(url('/login')) ?>">Sign in</a> for faster checkout and order tracking. You can also continue as a guest.</p><?php endif; ?>
</header>

<?php if (isset($errors['total_changed'])): $was = (int) $errors['total_changed']; ?>
  <div class="container"><div class="alert alert-warning" role="alert"><p><strong>Your order total was updated<?= $was > 0 && $was !== (int) $t['total'] ? ' from ' . e(money($was)) . ' to ' . e(money((int) $t['total'])) : '' ?>.</strong> Your bag, delivery option or a price changed after you opened checkout (for example, an item was added in another tab). Please check your order below and press <strong>Place order</strong> again — your details are kept.</p></div></div>
<?php endif; ?>
<?php if (!empty($errors['checkout']) || !empty($errors['coupon'])): ?>
  <div class="container"><div class="alert alert-error" role="alert"><p><?= e($errors['checkout'] ?? $errors['coupon']) ?></p></div></div>
<?php endif; ?>

<form class="container checkout-layout" method="post" action="<?= e(url('/checkout')) ?>" data-checkout-form novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">
  <input type="hidden" name="fingerprint" value="<?= e($fingerprint) ?>">
  <input type="hidden" name="expected_total" value="<?= (int) $t['total'] ?>">
  <!-- Default button for Enter-key submission (keeps "Use address" from firing). -->
  <button class="sr-only" type="submit" name="intent" value="place" tabindex="-1" aria-hidden="true"<?= !$rate || !$methods ? ' disabled' : '' ?>>Place order</button>

  <div>
    <section class="checkout-step" aria-labelledby="step-contact">
      <h2 id="step-contact"><span class="step-num">1</span> Contact</h2>
      <div class="form-grid cols-2">
        <div class="field">
          <label for="email">Email</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email" required maxlength="191" value="<?= e($v['email']) ?>"<?= $inv('email') ?>>
          <?= $err('email') ?>
        </div>
        <div class="field">
          <label for="phone">Phone number</label>
          <input class="input" id="phone" name="phone" type="tel" autocomplete="tel-national" inputmode="tel" required maxlength="32" placeholder="(555) 123-4567" value="<?= e($v['phone']) ?>"<?= $inv('phone') ?>>
          <p class="hint">US number, in case the carrier needs to reach you about delivery.</p>
          <?= $err('phone') ?>
        </div>
      </div>
    </section>

    <section class="checkout-step" aria-labelledby="step-address">
      <h2 id="step-address"><span class="step-num">2</span> Shipping address</h2>
      <?php if ($addresses): ?>
        <div class="field">
          <label for="use_address">Saved addresses</label>
          <div class="flex">
            <select class="select grow" id="use_address" name="use_address" data-saved-address>
              <option value="">Choose a saved address…</option>
              <?php foreach ($addresses as $a): ?>
                <option value="<?= (int) $a['id'] ?>"><?= e(($a['label'] ? $a['label'] . ' — ' : '') . $a['line1'] . ', ' . $a['city']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline btn-sm" type="submit" name="intent" value="address" formnovalidate>Use</button>
          </div>
        </div>
      <?php endif; ?>
      <div class="form-grid cols-2">
        <div class="field span-2">
          <label for="full_name">Full name</label>
          <input class="input" id="full_name" name="full_name" autocomplete="name" required maxlength="120" value="<?= e($v['full_name']) ?>"<?= $inv('full_name') ?>>
          <?= $err('full_name') ?>
        </div>
        <div class="field span-2">
          <label for="country">Country / region</label>
          <input class="input" id="country" value="<?= e(App\Services\Region::COUNTRY_NAME) ?>" autocomplete="country-name" readonly aria-describedby="country-hint">
          <p class="hint" id="country-hint">We currently ship within the United States only.</p>
        </div>
        <div class="field span-2">
          <label for="line1">Street address</label>
          <input class="input" id="line1" name="line1" autocomplete="address-line1" required maxlength="191" placeholder="123 Main St" value="<?= e($v['line1']) ?>"<?= $inv('line1') ?>>
          <?= $err('line1') ?>
        </div>
        <div class="field span-2">
          <label for="line2">Apartment, suite, unit, etc. <span class="muted">(optional)</span></label>
          <input class="input" id="line2" name="line2" autocomplete="address-line2" maxlength="191" value="<?= e($v['line2']) ?>">
        </div>
      </div>
      <div class="form-grid cols-3">
        <div class="field">
          <label for="city">City</label>
          <input class="input" id="city" name="city" autocomplete="address-level2" required maxlength="100" value="<?= e($v['city']) ?>"<?= $inv('city') ?>>
          <?= $err('city') ?>
        </div>
        <div class="field">
          <label for="province">State</label>
          <select class="select" id="province" name="province" autocomplete="address-level1" required data-refresh-on-change<?= $inv('province') ?>>
            <option value="">Select state</option>
            <?php foreach ($states as $code => $name): ?><option value="<?= e($code) ?>"<?= strtoupper($v['province']) === $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
          </select>
          <?= $err('province') ?>
        </div>
        <div class="field">
          <label for="postal_code">ZIP code</label>
          <input class="input" id="postal_code" name="postal_code" autocomplete="postal-code" inputmode="numeric" required maxlength="10" pattern="\d{5}(-\d{4})?" placeholder="10001" value="<?= e($v['postal_code']) ?>"<?= $inv('postal_code') ?>>
          <?= $err('postal_code') ?>
        </div>
      </div>
      <?php if ($user): ?>
        <label class="check"><input type="checkbox" name="save_address" value="1"<?= $v['save_address'] ? ' checked' : '' ?>> Save this address to my account</label>
      <?php endif; ?>
      <p class="small mt-4"><button class="btn btn-outline btn-sm" type="submit" name="intent" value="update" formnovalidate data-refresh-btn>Update shipping options</button></p>
    </section>

    <section class="checkout-step" aria-labelledby="step-delivery">
      <h2 id="step-delivery"><span class="step-num">3</span> Shipping method</h2>
      <?php if ($v['province'] === '' && $v['city'] === ''): ?>
        <p class="muted small">Select your state to see shipping options.</p>
      <?php elseif (!$rates): ?>
        <div class="alert alert-warning"><p>We don't ship to this address yet. Please <a href="<?= e(url('/contact')) ?>">contact us</a>.</p></div>
      <?php else: ?>
        <?php foreach ($rates as $rt): $free = $t['free_shipping'] && $rate && $rt['id'] === $rate['id']; ?>
          <label class="option-card">
            <input type="radio" name="shipping_rate_id" value="<?= (int) $rt['id'] ?>"<?= $rate && $rt['id'] === $rate['id'] ? ' checked' : '' ?> data-refresh-on-change>
            <span class="grow"><strong class="small"><?= e($rt['name']) ?></strong><br><span class="xsmall muted"><?= (int) $rt['min_days'] === (int) $rt['max_days'] ? (int) $rt['min_days'] : (int) $rt['min_days'] . '–' . (int) $rt['max_days'] ?> business days<?= $rt['cod_available'] && isset($methods['cod']) ? ' · Cash on delivery available' : '' ?></span></span>
            <span class="opt-price"><?= $free ? 'Free' : e(money((int) $rt['price_minor'])) ?></span>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
      <?= $err('shipping_rate_id') ?>
    </section>

    <section class="checkout-step" aria-labelledby="step-payment">
      <h2 id="step-payment"><span class="step-num">4</span> Payment</h2>
      <?php if (!$methods): ?>
        <div class="alert alert-warning"><p>No payment method is available for this order right now. Please contact us to complete your purchase.</p></div>
      <?php else: ?>
        <?php foreach ($methods as $code => $m): ?>
          <label class="option-card">
            <input type="radio" name="payment_method" value="<?= e($code) ?>"<?= $v['payment_method'] === $code ? ' checked' : '' ?>>
            <?= icon($methodIcons[$code] ?? 'card') ?>
            <span class="grow"><strong class="small"><?= e($m->label()) ?></strong><br><span class="xsmall muted"><?= e($m->description()) ?></span></span>
          </label>
        <?php endforeach; ?>
        <?php if (array_filter($methods, static fn ($m) => $m instanceof App\Payments\CryptoTransferProvider)): ?>
          <p class="xsmall muted">After you place the order you will see the exact amount and our wallet address, plus a form to submit your transaction hash. BTC/ETH amounts use the live market price.</p>
        <?php elseif (isset($methods['bank_transfer'])): ?>
          <p class="xsmall muted">Bank details are shown after you place the order, along with a form to submit your transaction ID.</p>
        <?php endif; ?>
      <?php endif; ?>
      <?= $err('payment_method') ?>
      <div class="field mt-4">
        <label for="note">Order note <span class="muted">(optional)</span></label>
        <textarea class="textarea" id="note" name="note" maxlength="1000" rows="3"><?= e($v['note']) ?></textarea>
      </div>
    </section>
  </div>

  <aside class="summary" aria-labelledby="co-summary">
    <h2 id="co-summary">Your order</h2>
    <ul class="mini-lines">
      <?php foreach ($s['lines'] as $l): ?>
        <li>
          <span class="mini-thumb"><img src="<?= e(media($l['image'], 400)) ?>" alt="" width="56" height="75" loading="lazy"><b><?= (int) $l['quantity'] ?></b></span>
          <span><?= e($l['title']) ?><br><span class="xsmall muted"><?= e($l['variant_label']) ?></span></span>
          <span class="small"><?= e(money($l['unit'] * (int) $l['quantity'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <dl>
      <div class="row"><dt>Subtotal</dt><dd><?= e(money($t['subtotal'])) ?></dd></div>
      <?php if ($t['discount'] > 0): ?><div class="row"><dt>Discount (<?= e($s['coupon']['code']) ?>)</dt><dd>−<?= e(money($t['discount'])) ?></dd></div><?php endif; ?>
      <div class="row"><dt>Shipping</dt><dd><?= $rate ? ($t['shipping'] === 0 ? 'Free' : e(money($t['shipping']))) : '—' ?></dd></div>
      <?php if ($t['tax'] > 0): ?><div class="row"><dt>Tax</dt><dd><?= e(money($t['tax'])) ?></dd></div><?php endif; ?>
      <div class="row total"><dt>Total</dt><dd><?= e(money($t['total'])) ?></dd></div>
    </dl>
    <?= App\Core\View::partial('partials/currency-note', ['amount' => $t['total']]) ?>
    <?php if (!$s['coupon']): ?><p class="xsmall muted mt-2">Have a code? <a href="<?= e(url('/cart')) ?>">Apply it in your bag</a>.</p><?php endif; ?>
    <button class="btn btn-primary btn-block mt-4" type="submit" name="intent" value="place" data-place-order<?= !$rate || !$methods ? ' disabled' : '' ?>>Place order · <?= e(money($t['total'])) ?></button>
    <p class="xsmall muted center mt-2">By placing your order you agree to our <a href="<?= e(url('/pages/terms')) ?>">terms</a> and <a href="<?= e(url('/pages/privacy-policy')) ?>">privacy policy</a>.</p>
  </aside>
</form>
