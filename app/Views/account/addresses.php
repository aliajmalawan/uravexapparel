<?php $a = $edit ?? []; ?>
<header class="container page-head"><span class="eyebrow">My account</span><h1>Addresses</h1></header>
<div class="container account-layout">
  <?= App\Core\View::partial('account/_nav') ?>
  <div>
    <?php if ($addresses): ?>
      <div class="cards-2">
        <?php foreach ($addresses as $ad): ?>
          <div class="address-card<?= $ad['is_default'] ? ' is-default' : '' ?>">
            <?php if ($ad['is_default']): ?><span class="tag tag-dark">Default</span><?php endif; ?>
            <p class="mt-2"><strong><?= e($ad['label'] ?: 'Address') ?></strong><br><?= e($ad['full_name']) ?><br><?= e($ad['line1']) ?><?= $ad['line2'] ? '<br>' . e($ad['line2']) : '' ?><br><?= e(App\Services\Region::locality($ad)) ?><br><?= e($ad['phone']) ?></p>
            <div class="flex">
              <a class="btn btn-sm btn-outline" href="<?= e(url('/account/addresses', ['edit' => $ad['id']])) ?>#address-form">Edit</a>
              <?php if (!$ad['is_default']): ?>
                <form method="post" action="<?= e(url('/account/addresses/' . $ad['id'] . '/default')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link" type="submit">Make default</button></form>
              <?php endif; ?>
              <form method="post" action="<?= e(url('/account/addresses/' . $ad['id'] . '/delete')) ?>" data-confirm="Delete this address?"><?= csrf_field() ?><button class="btn btn-sm btn-link" type="submit">Delete</button></form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted">No saved addresses yet. Addresses you save here are offered at checkout.</p>
    <?php endif; ?>

    <form class="panel mt-4" id="address-form" method="post" action="<?= e(url('/account/addresses')) ?>" novalidate>
      <?= csrf_field() ?>
      <h2><?= $a ? 'Edit address' : 'Add an address' ?></h2>
      <?php if ($a): ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><?php endif; ?>
      <div class="form-grid cols-2">
        <div class="field"><label for="label">Label <span class="muted">(e.g. Home, Office)</span></label><input class="input" id="label" name="label" maxlength="60" value="<?= e(old('label', $a['label'] ?? '')) ?>"></div>
        <div class="field"><label for="full_name">Full name</label><input class="input" id="full_name" name="full_name" required autocomplete="name" value="<?= e(old('full_name', $a['full_name'] ?? $user['name'])) ?>"<?= error_attrs('full_name') ?>><?= error_text('full_name') ?></div>
        <div class="field"><label for="phone">Phone number</label><input class="input" id="phone" name="phone" type="tel" required autocomplete="tel-national" placeholder="(555) 123-4567" value="<?= e(old('phone', $a['phone'] ?? $user['phone'])) ?>"<?= error_attrs('phone') ?>><?= error_text('phone') ?></div>
        <div class="field"><label for="line1">Street address</label><input class="input" id="line1" name="line1" required autocomplete="address-line1" placeholder="123 Main St" value="<?= e(old('line1', $a['line1'] ?? '')) ?>"<?= error_attrs('line1') ?>><?= error_text('line1') ?></div>
        <div class="field"><label for="line2">Apartment, suite, unit <span class="muted">(optional)</span></label><input class="input" id="line2" name="line2" autocomplete="address-line2" value="<?= e(old('line2', $a['line2'] ?? '')) ?>"></div>
        <div class="field"><label for="city">City</label><input class="input" id="city" name="city" required autocomplete="address-level2" value="<?= e(old('city', $a['city'] ?? '')) ?>"<?= error_attrs('city') ?>><?= error_text('city') ?></div>
        <div class="field"><label for="province">State</label>
          <select class="select" id="province" name="province" required autocomplete="address-level1"<?= error_attrs('province') ?>><option value="">Select state</option>
            <?php foreach ($states as $code => $name): ?><option value="<?= e($code) ?>"<?= strtoupper((string) old('province', $a['province'] ?? '')) === $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
          </select><?= error_text('province') ?></div>
        <div class="field"><label for="postal_code">ZIP code</label><input class="input" id="postal_code" name="postal_code" required autocomplete="postal-code" inputmode="numeric" maxlength="10" placeholder="10001" value="<?= e(old('postal_code', $a['postal_code'] ?? '')) ?>"<?= error_attrs('postal_code') ?>><?= error_text('postal_code') ?></div>
        <div class="field"><label for="country">Country</label><input class="input" id="country" value="<?= e(App\Services\Region::COUNTRY_NAME) ?>" readonly></div>
      </div>
      <label class="check"><input type="checkbox" name="is_default" value="1"<?= !empty($a['is_default']) ? ' checked' : '' ?>> Use as default address</label>
      <div class="flex mt-4"><button class="btn btn-dark" type="submit">Save address</button><?php if ($a): ?><a class="btn btn-link" href="<?= e(url('/account/addresses')) ?>">Cancel</a><?php endif; ?></div>
    </form>
  </div>
</div>
