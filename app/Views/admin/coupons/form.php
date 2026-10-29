<?php
$v = static fn ($k, $d = '') => old($k, $c[$k] ?? $d);
$m = static fn ($minor) => $minor === null || $minor === '' ? '' : rtrim(rtrim(App\Core\Money::decimal((int) $minor), '0'), '.');
?>
<div class="page-actions"><a class="link-arrow" href="<?= e(url('/admin/coupons')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?> All coupons</a></div>
<form class="admin-form" method="post" action="<?= e(url('/admin/coupons')) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($c): ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><?php endif; ?>
  <div class="grid-2">
    <section class="card">
      <h2>Discount</h2>
      <div class="field mt-4"><label for="code">Code</label><input class="input" id="code" name="code" required maxlength="40" value="<?= e($v('code')) ?>" placeholder="WELCOME10"<?= error_attrs('code') ?>><p class="hint">Letters, numbers, - and _. Codes are case-insensitive.</p><?= error_text('code') ?></div>
      <div class="field"><label for="description">Internal description</label><input class="input" id="description" name="description" maxlength="255" value="<?= e($v('description')) ?>"></div>
      <div class="field"><label for="type">Type</label><select class="select" id="type" name="type"><?php foreach (['percentage' => 'Percentage off', 'fixed' => 'Fixed amount off', 'free_shipping' => 'Free delivery only'] as $k => $l): ?><option value="<?= $k ?>"<?= $v('type', 'percentage') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="form-row c2">
        <div class="field"><label for="percent">Percent (1–100)</label><input class="input" id="percent" name="percent" type="number" min="1" max="100" value="<?= e(old('percent', ($c['type'] ?? '') === 'percentage' ? $c['value'] : '')) ?>"<?= error_attrs('percent') ?>><?= error_text('percent') ?></div>
        <div class="field"><label for="amount">Fixed amount (<?= e(App\Core\Money::defaultCurrency()) ?>)</label><input class="input" id="amount" name="amount" inputmode="decimal" value="<?= e(old('amount', ($c['type'] ?? '') === 'fixed' ? $m($c['value']) : '')) ?>"<?= error_attrs('amount') ?>><?= error_text('amount') ?></div>
      </div>
      <div class="form-row c2">
        <div class="field"><label for="max_discount">Max discount (<?= e(App\Core\Money::defaultCurrency()) ?>)</label><input class="input" id="max_discount" name="max_discount" inputmode="decimal" value="<?= e(old('max_discount', $m($c['max_discount_minor'] ?? null))) ?>"><p class="hint">Caps percentage discounts.</p></div>
        <div class="field"><label for="min_subtotal">Minimum basket (<?= e(App\Core\Money::defaultCurrency()) ?>)</label><input class="input" id="min_subtotal" name="min_subtotal" inputmode="decimal" value="<?= e(old('min_subtotal', $m($c['min_subtotal_minor'] ?? null))) ?>"></div>
      </div>
      <label class="check"><input type="checkbox" name="free_shipping" value="1"<?= $v('free_shipping') ? ' checked' : '' ?>> Also give free delivery</label>
      <label class="check"><input type="checkbox" name="is_active" value="1"<?= $v('is_active', 1) ? ' checked' : '' ?>> Active</label>
    </section>
    <section class="card">
      <h2>Limits &amp; eligibility</h2>
      <div class="form-row c2 mt-4">
        <div class="field"><label for="starts_at">Starts (PKT)</label><input class="input" id="starts_at" name="starts_at" type="datetime-local" value="<?= e(old('starts_at', utc_to_local_input($c['starts_at'] ?? null))) ?>"<?= error_attrs('starts_at') ?>><?= error_text('starts_at') ?></div>
        <div class="field"><label for="ends_at">Ends (PKT)</label><input class="input" id="ends_at" name="ends_at" type="datetime-local" value="<?= e(old('ends_at', utc_to_local_input($c['ends_at'] ?? null))) ?>"<?= error_attrs('ends_at') ?>><?= error_text('ends_at') ?></div>
        <div class="field"><label for="usage_limit">Total uses</label><input class="input" id="usage_limit" name="usage_limit" type="number" min="1" value="<?= e($v('usage_limit')) ?>" placeholder="Unlimited"<?= error_attrs('usage_limit') ?>><?= error_text('usage_limit') ?><?php if ($c): ?><p class="hint">Used <?= (int) $c['usage_count'] ?> times.</p><?php endif; ?></div>
        <div class="field"><label for="per_customer_limit">Uses per customer</label><input class="input" id="per_customer_limit" name="per_customer_limit" type="number" min="1" value="<?= e($v('per_customer_limit')) ?>" placeholder="Unlimited"><p class="hint">Counted by account and by email for guests.</p></div>
      </div>
      <div class="field"><label for="scope">Applies to</label><select class="select" id="scope" name="scope"<?= error_attrs('scope') ?>><?php foreach (['all' => 'Entire order', 'products' => 'Selected products', 'collections' => 'Selected collections', 'categories' => 'Selected categories'] as $k => $l): ?><option value="<?= $k ?>"<?= $v('scope', 'all') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><?= error_text('scope') ?></div>
      <div class="form-row c3">
        <div class="field"><label for="product_ids">Products</label><select class="select" id="product_ids" name="product_ids[]" multiple size="6"><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"<?= in_array((int) $p['id'], $targets['product'], true) ? ' selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="collection_ids">Collections</label><select class="select" id="collection_ids" name="collection_ids[]" multiple size="6"><?php foreach ($collections as $p): ?><option value="<?= (int) $p['id'] ?>"<?= in_array((int) $p['id'], $targets['collection'], true) ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="category_ids">Categories</label><select class="select" id="category_ids" name="category_ids[]" multiple size="6"><?php foreach ($categories as $p): ?><option value="<?= (int) $p['id'] ?>"<?= in_array((int) $p['id'], $targets['category'], true) ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <p class="hint">Only the list matching “Applies to” is used. All rules are re-checked on the server at checkout.</p>
    </section>
  </div>
  <div class="sticky-actions"><button class="btn btn-primary" type="submit">Save coupon</button></div>
</form>
<?php if ($c): ?>
<form class="mt-4" method="post" action="<?= e(url('/admin/coupons/' . $c['id'] . '/delete')) ?>" data-confirm="Delete coupon <?= e($c['code']) ?>? Used coupons are deactivated instead."><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete coupon</button></form>
<?php endif; ?>
