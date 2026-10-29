<?php
/**
 * Pay-by-transfer instructions + transaction-hash form, shown while an order
 * paid by crypto (or legacy bank transfer) is awaiting payment.
 *
 * @var array $o        order detail
 * @var ?array $payment latest payment row
 * @var App\Payments\ManualPayment $provider
 * @var array $bank     bank details (legacy bank transfer only)
 */
use App\Payments\CryptoTransferProvider;

$crypto = $provider instanceof CryptoTransferProvider;
$asset = $payment['pay_asset'] ?? ($crypto ? $provider->asset() : null);
$amount = $payment['pay_amount'] ?? null;
$amount = $amount !== null && str_contains((string) $amount, '.') ? rtrim(rtrim((string) $amount, '0'), '.') : $amount;
$address = $payment['pay_address'] ?? ($crypto ? $provider->address() : '');
$submitted = $payment && $payment['status'] === 'awaiting_verification';
?>
<section class="panel" aria-labelledby="pay-title">
  <h2 id="pay-title"><?= $crypto ? 'Send ' . e($asset) . ' to complete your order' : 'Bank transfer details' ?></h2>
  <?php if ($crypto): ?>
    <div class="alert alert-info"><p>Send <strong>exactly the amount below</strong> on the <strong><?= e($provider->network()) ?></strong> network only. Funds sent on another network cannot be recovered.<?php if (!$submitted && !empty($o['reservation_expires_at'])): ?> Your items are reserved until <?= e(dt($o['reservation_expires_at'])) ?> — submit the transaction hash before then.<?php endif; ?></p></div>
    <div class="bank-box crypto-box">
      <dl>
        <dt>Network</dt><dd><?= e($provider->network()) ?></dd>
        <dt>Amount</dt><dd><span class="pay-amount"><?= $amount !== null ? e($amount . ' ' . $asset) : '—' ?></span><?php if ($amount !== null): ?> <button class="copy-btn" type="button" data-copy="<?= e((string) $amount) ?>">Copy</button><?php endif; ?></dd>
        <?php if ($asset !== 'USDT'): ?><dt>Order total</dt><dd><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?><?= !empty($payment['pay_rate']) ? ' <span class="muted">(1 ' . e($asset) . ' = ' . e(number_format((float) $payment['pay_rate'], 2)) . ' USDT)</span>' : '' ?></dd><?php endif; ?>
        <dt>Wallet address</dt><dd><code class="wallet"><?= e($address) ?></code> <button class="copy-btn" type="button" data-copy="<?= e($address) ?>">Copy</button></dd>
        <dt>Order</dt><dd><?= e($o['order_number']) ?></dd>
      </dl>
    </div>
  <?php else: ?>
    <p class="small"><?= e($bank['instructions'] ?? '') ?></p>
    <div class="bank-box">
      <dl>
        <dt>Amount</dt><dd><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></dd>
        <dt>Bank</dt><dd><?= e($bank['bank_name'] ?? '') ?></dd>
        <dt>Account title</dt><dd><?= e($bank['account_title'] ?? '') ?></dd>
        <?php if (!empty($bank['account_number'])): ?><dt>Account no.</dt><dd><?= e($bank['account_number']) ?></dd><?php endif; ?>
        <?php if (!empty($bank['iban'])): ?><dt>IBAN</dt><dd><?= e($bank['iban']) ?></dd><?php endif; ?>
        <dt>Reference</dt><dd><?= e($o['order_number']) ?></dd>
      </dl>
    </div>
  <?php endif; ?>

  <?php if ($submitted): ?>
    <div class="alert alert-success mt-4"><p><?= e($provider->referenceLabel()) ?> <strong class="wallet"><?= e($payment['customer_reference']) ?></strong> received — we are verifying your payment and will confirm your order shortly.</p></div>
  <?php else: ?>
    <?php if ($payment && $payment['status'] === 'failed'): ?><div class="alert alert-error mt-4"><p>We could not verify your previous payment<?= $payment['verification_note'] ? ': ' . e($payment['verification_note']) : '' ?>. Please check and resubmit.</p></div><?php endif; ?>
    <form method="post" action="<?= e(url('/orders/' . $o['order_number'] . '/bank-reference')) ?>"<?= $crypto ? '' : ' enctype="multipart/form-data"' ?> class="mt-4">
      <?= csrf_field() ?>
      <div class="field">
        <label for="reference"><?= e($provider->referenceLabel()) ?></label>
        <input class="input" id="reference" name="reference" required maxlength="120" autocomplete="off" spellcheck="false" value="<?= e(old('reference')) ?>"<?= error_attrs('reference') ?>>
        <?php if ($crypto): ?><p class="hint"><?= $asset === 'BTC' ? 'The 64-character transaction ID from your wallet.' : ($provider->code() === 'usdt_trc20' ? 'The 64-character transaction hash shown in your wallet or on Tronscan.' : 'The transaction hash starting with 0x (66 characters).') ?></p><?php endif; ?>
        <?= error_text('reference') ?>
      </div>
      <?php if (!$crypto): ?>
        <div class="field">
          <label for="receipt">Receipt screenshot <span class="muted">(optional, JPG/PNG/PDF, max 5 MB)</span></label>
          <input class="input" id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp,application/pdf"<?= error_attrs('file') ?>>
          <?= error_text('file') ?><?= error_text('receipt') ?>
        </div>
      <?php endif; ?>
      <button class="btn btn-dark" type="submit">I have paid — submit</button>
    </form>
  <?php endif; ?>
</section>
