<?php
use App\Payments\CryptoTransferProvider;

$legacy = array_filter($rows, static fn ($row) => !$row['p'] instanceof CryptoTransferProvider);
?>
<?= App\Core\View::partial('admin/settings/_tabs') ?>
<form class="card" method="post" action="<?= e(url('/admin/settings/payments_methods')) ?>" novalidate>
  <?= csrf_field() ?>
  <h2>Payment methods</h2>
  <p class="hint mt-2">Customers pay in crypto straight to your own wallets: USDT on TRON (TRC20), USDT on Ethereum (ERC20), Bitcoin and Ether. Paste the receiving address for each method you accept. A method appears at checkout only when it is switched on <em>and</em> has a valid address.</p>
  <?= error_text('methods') ?>
  <div class="table-scroll mt-2">
    <table class="admin-table">
      <thead><tr><th>On</th><th>Method</th><th>Your wallet address</th><th>Name at checkout</th><th>Description at checkout</th><th>Order</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $code => $row): $p = $row['p']; $m = $s['methods'][$code] ?? []; $live = $row['implemented'] && $row['configured'] && $row['enabled'];
        $crypto = $p instanceof CryptoTransferProvider;
        if ($code === 'sandbox' && $production) continue; ?>
        <tr>
          <td><input type="checkbox" name="methods[<?= e($code) ?>][enabled]" value="1"<?= $row['enabled'] ? ' checked' : '' ?><?= $row['implemented'] ? '' : ' disabled' ?> aria-label="Enable <?= e($p->defaultLabel()) ?>"></td>
          <td><strong><?= e($p->defaultLabel()) ?></strong><?php if ($crypto): ?><br><span class="xsmall muted"><?= e($p->network()) ?> network</span><?php elseif (!$row['implemented']): ?><br><span class="xsmall muted"><?= e($p->notes()) ?></span><?php endif; ?></td>
          <td><?php if ($crypto): ?><input class="input wallet" name="methods[<?= e($code) ?>][address]" value="<?= e($p->address()) ?>" placeholder="<?= e(['usdt_trc20' => 'T…', 'usdt_erc20' => '0x…', 'eth' => '0x…', 'btc' => 'bc1… / 1… / 3…'][$code] ?? '') ?>" maxlength="100" autocomplete="off" spellcheck="false" aria-label="<?= e($p->defaultLabel()) ?> wallet address"><?php else: ?><span class="xsmall muted">—</span><?php endif; ?></td>
          <td><input class="input" name="methods[<?= e($code) ?>][label]" value="<?= e($m['label'] ?? '') ?>" placeholder="<?= e($p->defaultLabel()) ?>" maxlength="80" aria-label="Checkout name for <?= e($p->defaultLabel()) ?>"></td>
          <td><input class="input" name="methods[<?= e($code) ?>][description]" value="<?= e($m['description'] ?? '') ?>" placeholder="<?= e($p->defaultDescription()) ?>" maxlength="200" aria-label="Checkout description for <?= e($p->defaultLabel()) ?>"></td>
          <td><input class="input order-input" type="number" min="0" max="999" name="methods[<?= e($code) ?>][sort]" value="<?= (int) ($m['sort'] ?? 100) ?>" aria-label="Order for <?= e($p->defaultLabel()) ?>"></td>
          <td>
            <?php if ($live && $crypto && $p->asset() !== 'USDT' && !$pricesFresh): ?><span class="status-pill s-pending">Waiting for price</span>
            <?php elseif ($live): ?><span class="status-pill s-delivered">Live</span>
            <?php elseif (!$row['implemented']): ?><span class="status-pill s-pending">Needs integration</span>
            <?php elseif (!$row['configured']): ?><span class="status-pill s-pending"><?= $crypto ? 'Add address' : 'Setup required' ?></span>
            <?php else: ?><span class="status-pill s-cancelled">Off</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="hint">Double-check every address — payments sent to a wrong address cannot be recovered. Leave name/description blank to use the default.</p>
  <button class="btn btn-primary btn-sm" type="submit">Save payment methods</button>
</form>

<div class="grid-2">
  <section class="card">
    <h2>Live BTC / ETH prices</h2>
    <p class="hint mt-2">Bitcoin and Ether amounts are calculated at checkout from the live market price (Binance, with CoinGecko as backup), rounded up to 8 decimals, and fixed on the order. USDT is charged 1:1 with the order total.</p>
    <?php if (!empty($prices['prices'])): ?>
      <dl class="kv mt-2">
        <?php foreach ($prices['prices'] as $asset => $price): ?><dt>1 <?= e($asset) ?></dt><dd><?= e(number_format((float) $price, 2)) ?> USDT</dd><?php endforeach; ?>
        <dt>Updated</dt><dd><?= e(dt($prices['fetched_at'])) ?> · <?= e(ucfirst((string) ($prices['source'] ?? ''))) ?><?= $pricesFresh ? '' : ' <span class="text-error">(stale — BTC/ETH hidden at checkout)</span>' ?></dd>
      </dl>
    <?php else: ?>
      <p class="small text-error mt-2">No prices yet — Bitcoin and Ether stay hidden at checkout until a price is fetched.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/admin/settings/payments_prices_refresh')) ?>" class="mt-4">
      <?= csrf_field() ?><button class="btn btn-dark btn-sm" type="submit">Refresh prices now</button>
    </form>
  </section>
  <section class="card">
    <h2>How payments are confirmed</h2>
    <ol class="small mt-2">
      <li>The customer places the order and sees the exact amount, network and your wallet address.</li>
      <li>They send the funds and submit the transaction hash (TXID). Items are reserved for <?= (int) $reservationMinutes ?> minutes until the TXID is submitted (change this under <a href="<?= e(url('/admin/settings')) ?>">Store settings → Checkout</a>).</li>
      <li>You open the order, follow the explorer link, check the amount, network and receiving address, then click <strong>Approve payment</strong>. The order is confirmed and the customer is emailed.</li>
    </ol>
    <p class="hint">The site never holds private keys — funds go straight to your wallets.</p>
  </section>
</div>

<?php if (array_filter($legacy, static fn ($row) => $row['env'])): ?>
<section class="card">
  <h2>Gateway credentials</h2>
  <p class="hint mt-2">Keys are encrypted before they are stored and are never shown again — only whether each one is set. Leave a field blank to keep its saved value. A value set in the server's <code>.env</code> file takes priority.</p>
  <div class="grid-2 mt-4">
    <?php foreach ($legacy as $code => $row): if (!$row['env']) continue; $p = $row['p']; ?>
      <form id="gw-<?= e($code) ?>" class="card" method="post" action="<?= e(url('/admin/settings/payments_credentials')) ?>" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($code) ?>">
        <h3 class="small"><?= e($p->defaultLabel()) ?></h3>
        <?php foreach ($row['env'] as $env): $k = $env['key']; ?>
          <div class="field">
            <label for="c-<?= e($k) ?>"><?= e(ucwords(strtolower(str_replace('_', ' ', preg_replace('/^[A-Z]+_/', '', $k))))) ?> <span class="xsmall <?= $env['source'] ? 'text-success' : 'muted' ?>"><?= $env['source'] === 'env' ? '✓ set in .env' : ($env['source'] === 'admin' ? '✓ saved' : 'not set') ?></span></label>
            <input class="input" id="c-<?= e($k) ?>" name="cred[<?= e($k) ?>]" type="password" autocomplete="new-password" placeholder="<?= $env['source'] ? '•••••••• (leave blank to keep)' : 'Enter value' ?>"<?= $env['source'] === 'env' ? ' disabled' : '' ?>>
            <?php if ($env['source'] === 'admin'): ?><label class="check xsmall"><input type="checkbox" name="clear[]" value="<?= e($k) ?>"> Remove saved value</label><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <button class="btn btn-dark btn-sm" type="submit">Save <?= e($p->defaultLabel()) ?> keys</button>
      </form>
    <?php endforeach; ?>
  </div>
  <?= error_text('credentials') ?>
</section>
<?php endif; ?>

<?php if (isset($legacy['cod']) || isset($legacy['bank_transfer'])): ?>
<div class="grid-2">
  <?php if (isset($legacy['cod'])): ?>
  <form class="card" method="post" action="<?= e(url('/admin/settings/payments_cod')) ?>">
    <?= csrf_field() ?><h2>Cash on Delivery</h2>
    <label class="check mt-4"><input type="checkbox" name="enabled" value="1"<?= !empty($s['cod']['enabled']) ? ' checked' : '' ?>> Enable COD</label>
    <div class="field"><label for="max_order">Maximum order value for COD (<?= e(App\Core\Money::defaultCurrency()) ?>, blank = no limit)</label><input class="input" id="max_order" name="max_order" inputmode="decimal" value="<?= !empty($s['cod']['max_order_minor']) ? e(App\Core\Money::decimal((int) $s['cod']['max_order_minor'])) : '' ?>"></div>
    <div class="field"><label for="cod_note">Checkout note</label><input class="input" id="cod_note" name="note" value="<?= e($s['cod']['note'] ?? '') ?>"></div>
    <button class="btn btn-dark btn-sm" type="submit">Save COD</button>
  </form>
  <?php endif; ?>
  <?php if (isset($legacy['bank_transfer'])): ?>
  <form class="card" method="post" action="<?= e(url('/admin/settings/payments_bank_transfer')) ?>">
    <?= csrf_field() ?><h2>Bank transfer</h2>
    <label class="check mt-4"><input type="checkbox" name="enabled" value="1"<?= !empty($s['bank_transfer']['enabled']) ? ' checked' : '' ?>> Enable bank transfer</label>
    <div class="form-row c2">
      <div class="field"><label for="bank_name">Bank name</label><input class="input" id="bank_name" name="bank_name" value="<?= e($s['bank_transfer']['bank_name'] ?? '') ?>"<?= error_attrs('bank_name') ?>><?= error_text('bank_name') ?></div>
      <div class="field"><label for="account_title">Account title</label><input class="input" id="account_title" name="account_title" value="<?= e($s['bank_transfer']['account_title'] ?? '') ?>"></div>
      <div class="field"><label for="account_number">Account number</label><input class="input" id="account_number" name="account_number" value="<?= e($s['bank_transfer']['account_number'] ?? '') ?>"></div>
      <div class="field"><label for="iban">IBAN</label><input class="input" id="iban" name="iban" value="<?= e($s['bank_transfer']['iban'] ?? '') ?>"<?= error_attrs('iban') ?>><?= error_text('iban') ?></div>
      <div class="field"><label for="branch">Branch</label><input class="input" id="branch" name="branch" value="<?= e($s['bank_transfer']['branch'] ?? '') ?>"></div>
      <div class="field"><label for="auto_cancel_days">Auto-cancel unpaid after (days, 0 = never)</label><input class="input" id="auto_cancel_days" name="auto_cancel_days" type="number" min="0" max="30" value="<?= (int) ($s['bank_transfer']['auto_cancel_days'] ?? 3) ?>"></div>
    </div>
    <div class="field"><label for="instructions">Instructions shown to customers</label><textarea class="textarea" id="instructions" name="instructions" rows="3"><?= e($s['bank_transfer']['instructions'] ?? '') ?></textarea></div>
    <button class="btn btn-dark btn-sm" type="submit">Save bank transfer</button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
