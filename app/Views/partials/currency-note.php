<?php
/** Shown when a visitor browses in a display currency. @var int|null $amount base-currency amount */
use App\Core\Money;
use App\Services\Currency;

if (!Currency::isConverted()) {
    return;
}
$base = Currency::base();
?>
<p class="currency-note xsmall" role="note">
  <?php if (isset($amount)): ?>
    ≈ <?= e(price((int) $amount)) ?> (<?= e(Currency::current()) ?>). You will be charged <strong><?= e(money((int) $amount, $base)) ?></strong> in <?= e($base) ?>.
  <?php else: ?>
    Prices in <?= e(Currency::current()) ?> are approximate. Orders are charged in <?= e($base) ?> (<?= e(Money::CURRENCIES[$base][0]) ?>).
  <?php endif; ?>
</p>
