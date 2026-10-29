<?= App\Core\View::partial('admin/settings/_tabs') ?>
<?php use App\Core\Money; ?>
<div class="grid-2">
  <div>
  <form class="card" method="post" action="<?= e(url('/admin/settings/currency_display')) ?>" novalidate>
    <?= csrf_field() ?>
    <h2>Customer display currencies</h2>
    <p class="hint mt-2">Let shoppers browse prices in other currencies using a switcher in the header and footer. These are <strong>approximate</strong> conversions — checkout always charges <strong><?= e($base) ?></strong>, and cart and checkout say so.</p>
    <?= error_text('display') ?>
    <label class="check mt-2"><input type="checkbox" name="auto_rates" value="1"<?= $autoRates ? ' checked' : '' ?>> <span><strong>Update exchange rates automatically</strong> — live rates from <?= e(App\Services\ExchangeRates::SOURCE_NAME) ?>, refreshed every few hours. No manual rates needed.</span></label>
    <p class="xsmall muted">
      <?php if (!empty($live['fetched_at']) && ($live['base'] ?? '') === $base): ?>
        Live rates last checked <?= e(dt($live['fetched_at'])) ?><?= !empty($live['source_updated_at']) ? ' · published ' . e(dt($live['source_updated_at'])) : '' ?>.
      <?php else: ?>No live rates downloaded yet for <?= e($base) ?> — click “Refresh rates now”.<?php endif; ?>
    </p>
    <label class="check"><input type="checkbox" name="show_switcher" value="1"<?= $showSwitcher ? ' checked' : '' ?>> Show the currency switcher on the store</label>
    <div class="field mt-2"><label for="default_display">Default currency for new visitors</label>
      <select class="select" id="default_display" name="default_display">
        <option value=""><?= e($base) ?> (store currency)</option>
        <?php foreach ($rates as $code => $r0): ?><option value="<?= e($code) ?>"<?= $defaultDisplay === $code ? ' selected' : '' ?>><?= e($code) ?></option><?php endforeach; ?>
      </select>
      <p class="hint">Prices are shown in this currency until a visitor picks another one. It must be ticked below.</p></div>
    <div class="table-scroll mt-2">
      <table class="admin-table">
        <thead><tr><th>Show</th><th>Currency</th><th>Live rate (1 <?= e($base) ?> =)</th><th>Manual rate<br><span class="xsmall muted">optional fallback</span></th><th>Example</th></tr></thead>
        <tbody>
        <?php foreach (Money::CURRENCIES as $code => [$name, $symbol]): if ($code === $base) continue;
            $enabled = array_key_exists($code, $rates);
            $manual = $enabled ? (string) $rates[$code] : '';
            $liveRate = App\Services\ExchangeRates::rate($code);
            $use = $effective[$code] ?? ($liveRate ?? ($manual !== '' ? $manual : null)); ?>
          <tr>
            <td><input type="checkbox" name="display[<?= e($code) ?>][enabled]" value="1"<?= $enabled ? ' checked' : '' ?> aria-label="Show prices in <?= e($name) ?>"></td>
            <td><strong><?= e($code) ?></strong> <span class="xsmall muted"><?= e($name) ?></span></td>
            <td class="small"><?= $liveRate !== null ? e($liveRate) : '<span class="muted">—</span>' ?></td>
            <td><input class="input rate-input" name="display[<?= e($code) ?>][rate]" value="<?= e($manual) ?>" inputmode="decimal" placeholder="<?= $autoRates ? 'auto' : 'e.g. 0.0036' ?>" aria-label="Manual exchange rate for <?= e($code) ?>"></td>
            <td class="small muted"><?= $use !== null && Money::validRate($use) ? e(money(1000000, $base)) . ' ≈ ' . e(Money::format(Money::convert(1000000, $base, $code, $use), $code)) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint">With automatic rates on, leave “Manual rate” empty — it is only used if the live service is unreachable. With automatic rates off, enter a rate for each ticked currency.</p>
    <button class="btn btn-primary btn-sm mt-2" type="submit">Save display currencies</button>
  </form>
  <form class="card" method="post" action="<?= e(url('/admin/settings/currency_refresh')) ?>">
    <?= csrf_field() ?>
    <div class="flex between"><span class="small">Rates update automatically. Need them right now?</span><button class="btn btn-outline btn-sm" type="submit"><?= icon('refresh', 'icon icon-sm') ?> Refresh rates now</button></div>
  </form>
  </div>
  <?php if ($preview): ?>
  <form class="card currency-preview" method="post" action="<?= e(url('/admin/settings/currency_base')) ?>" id="currency-preview">
    <?= csrf_field() ?>
    <input type="hidden" name="base" value="<?= e($preview['new']) ?>">
    <input type="hidden" name="rate" value="<?= e($preview['quoted']) ?>">
    <input type="hidden" name="confirm" value="1">
    <h2>Check before converting</h2>
    <p class="mt-2">Rate: <strong>1 <?= e($preview['new']) ?> = <?= e($preview['quoted']) ?> <?= e($base) ?></strong>. Your prices will change like this:</p>
    <table class="admin-table mt-2">
      <thead><tr><th>Item</th><th class="num">Now (<?= e($base) ?>)</th><th class="num">After (<?= e($preview['new']) ?>)</th></tr></thead>
      <tbody><?php foreach ($preview['samples'] as [$label, $before, $after]): ?><tr><td><?= e($label) ?></td><td class="num"><?= e($before) ?></td><td class="num"><strong><?= e($after) ?></strong></td></tr><?php endforeach; ?></tbody>
    </table>
    <div class="alert alert-warning mt-4"><p>If these numbers look wrong (far too high or too low), the rate is probably reversed — go back and correct it. Nothing has been changed yet.</p></div>
    <div class="flex">
      <button class="btn btn-danger btn-sm" type="submit">Yes, convert all prices to <?= e($preview['new']) ?></button>
      <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/settings/currency')) ?>">Cancel</a>
    </div>
  </form>
  <?php endif; ?>

  <form class="card" method="post" action="<?= e(url('/admin/settings/currency_base')) ?>" novalidate>
    <?= csrf_field() ?>
    <h2>Store currency</h2>
    <p class="mt-2">Current: <strong><?= e($base) ?> — <?= e(Money::CURRENCIES[$base][0]) ?></strong></p>
    <p class="hint">This is the currency products are priced in and customers are charged in. Changing it converts every product and variant price, cost, delivery rate, coupon amount, free-delivery threshold and COD limit using the rate you enter (rounded to the new currency's smallest unit). <?= (int) $productCount ?> products will be converted.</p>
    <div class="alert alert-warning"><p>Only change this if you will actually charge customers in the new currency (crypto amounts are calculated from it). Existing orders keep the currency they were placed in<?= $otherCurrencyOrders ? ' (' . (int) $otherCurrencyOrders . ' orders are already in other currencies)' : '' ?>; reports only total orders in the current store currency. Display currencies are reset and must be re-entered.</p></div>
    <div class="form-row c2">
      <div class="field"><label for="base">New store currency</label>
        <select class="select" id="base" name="base" data-currency-select<?= error_attrs('base') ?>>
          <?php foreach (Money::CURRENCIES as $code => [$name]): if ($code === $base) continue; ?><option value="<?= e($code) ?>"<?= old('base') === $code ? ' selected' : '' ?>><?= e($code . ' — ' . $name) ?></option><?php endforeach; ?>
        </select><?= error_text('base') ?></div>
      <div class="field"><label for="rate">Exchange rate: 1 <span data-currency-code><?= e(array_key_first(array_diff_key(Money::CURRENCIES, [$base => 1]))) ?></span> = how many <?= e($base) ?>?</label><input class="input" id="rate" name="rate" inputmode="decimal" placeholder="e.g. 276.85" required value="<?= e(old('rate')) ?>" data-live-rates="<?= e(json_encode(array_map(static fn ($r) => rtrim(rtrim(bcdiv('1', $r, 6), '0'), '.'), array_filter((array) ($live['rates'] ?? []), static fn ($r) => is_string($r) && App\Core\Money::validRate($r))))) ?>"<?= error_attrs('rate') ?>><?= error_text('rate') ?><p class="hint">Enter the rate the way banks quote it. Example: 1 USD = 276.85 PKR → type <strong>276.85</strong>. You will see a preview before anything changes.</p></div>
    </div>
    <button class="btn btn-dark btn-sm mt-4" type="submit">Preview conversion</button>
    <p class="hint mt-2">Tip: take a backup first (<code>php bin/console.php backup</code>).</p>
  </form>
</div>
