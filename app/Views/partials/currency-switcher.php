<?php
use App\Core\Money;
use App\Services\Currency;
use App\Services\Settings;

$choices = Currency::choices();
if (count($choices) < 2 || !Settings::get('currency.show_switcher', true)) {
    return;
}
$id = 'cur-' . ($place ?? 'h');
?>
<form class="currency-switch <?= e($class ?? '') ?>" method="get" action="<?= e(url('/currency')) ?>" data-auto-submit>
  <label class="sr-only" for="<?= e($id) ?>">Currency</label>
  <select id="<?= e($id) ?>" name="code" class="currency-select">
    <?php foreach ($choices as $code): ?>
      <option value="<?= e($code) ?>"<?= Currency::current() === $code ? ' selected' : '' ?>><?= e($code) ?> — <?= e(trim(Money::symbol($code))) ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn btn-sm btn-outline-light" type="submit">Go</button></noscript>
</form>
<?php if (($place ?? '') === 'f' && Settings::get('currency.auto_rates', true) && (App\Services\ExchangeRates::cached()['source'] ?? 'primary') === 'primary'): ?>
  <a class="rates-credit" href="<?= e(App\Services\ExchangeRates::SOURCE_URL) ?>" rel="noopener" target="_blank">Rates by Exchange Rate API</a>
<?php endif; ?>
