<?= App\Core\View::partial('admin/settings/_tabs') ?>
<div class="grid-2">
  <form class="card" method="post" action="<?= e(url('/admin/settings/checkout')) ?>">
    <?= csrf_field() ?><h2>Checkout</h2>
    <label class="check mt-4"><input type="checkbox" name="guest_checkout" value="1"<?= !empty($s['checkout']['guest_checkout']) ? ' checked' : '' ?>> Allow guest checkout</label>
    <label class="check"><input type="checkbox" name="require_phone" value="1"<?= !empty($s['checkout']['require_phone']) ? ' checked' : '' ?>> Require phone number</label>
    <div class="form-row c2 mt-4">
      <div class="field"><label for="tax_percent">Tax added at checkout (%)</label><input class="input" id="tax_percent" name="tax_percent" type="number" min="0" max="50" value="<?= (int) ($s['checkout']['tax_percent'] ?? 0) ?>"><p class="hint">0 if prices already include tax.</p></div>
      <div class="field"><label for="reservation_minutes">Online payment reservation (minutes)</label><input class="input" id="reservation_minutes" name="reservation_minutes" type="number" min="5" max="1440" value="<?= (int) ($s['checkout']['reservation_minutes'] ?? 30) ?>"><p class="hint">Unpaid online orders are cancelled and restocked after this.</p></div>
    </div>
    <button class="btn btn-dark btn-sm" type="submit">Save checkout settings</button>
  </form>
  <form class="card" method="post" action="<?= e(url('/admin/settings/inventory')) ?>">
    <?= csrf_field() ?><h2>Inventory</h2>
    <div class="field mt-4"><label for="default_low_stock">Default low-stock threshold</label><input class="input" id="default_low_stock" name="default_low_stock" type="number" min="0" value="<?= (int) ($s['inventory']['default_low_stock'] ?? 5) ?>"></div>
    <button class="btn btn-dark btn-sm" type="submit">Save</button>
  </form>
  <section class="card">
    <h2>Environment</h2>
    <dl class="kv mt-4">
      <dt>Environment</dt><dd><?= e((string) App\Core\Config::get('env')) ?></dd>
      <dt>Store URL</dt><dd><?= e((string) App\Core\Config::get('url')) ?></dd>
      <dt>Timezone</dt><dd><?= e((string) App\Core\Config::get('timezone')) ?></dd>
      <dt>Currency</dt><dd><?= e(App\Core\Money::defaultCurrency()) ?> — change in <a href="<?= e(url('/admin/settings/currency')) ?>">Currency</a></dd>
      <dt>Email driver</dt><dd><?= e((string) App\Core\Config::get('mail.driver')) ?><?= App\Core\Config::get('mail.driver') === 'outbox' ? ' — development only; emails are saved to storage/mail' : '' ?></dd>
    </dl>
    <p class="hint mt-2">These values come from the server's .env file and cannot be changed here.</p>
  </section>
</div>
