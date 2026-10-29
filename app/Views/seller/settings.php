<header class="container page-head"><span class="eyebrow">Seller panel</span><h1>Shop settings</h1></header>
<div class="container account-layout seller-panel">
  <?= App\Core\View::partial('seller/_nav', ['seller' => $seller]) ?>
  <div>
    <form class="panel" method="post" action="<?= e(url('/seller/settings')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label for="shop_name">Shop name</label><input class="input" id="shop_name" name="shop_name" required maxlength="80" value="<?= e(old('shop_name', $seller['shop_name'])) ?>"<?= error_attrs('shop_name') ?>><?= error_text('shop_name') ?><p class="hint">Shown on your products ("Sold by …") and your shop page. Changing it also changes your shop link.</p></div>
      <div class="field"><label for="description">About your shop</label><textarea class="textarea" id="description" name="description" rows="5" required maxlength="2000"<?= error_attrs('description') ?>><?= e(old('description', $seller['description'])) ?></textarea><?= error_text('description') ?></div>
      <div class="field"><label for="contact_phone">Contact phone</label><input class="input" id="contact_phone" name="contact_phone" type="tel" maxlength="32" value="<?= e(old('contact_phone', (string) $seller['contact_phone'])) ?>"></div>
      <fieldset class="panel">
        <legend>Payouts</legend>
        <div class="field"><label for="payout_address">USDT TRC20 (TRON) payout address</label><input class="input wallet" id="payout_address" name="payout_address" maxlength="60" autocomplete="off" spellcheck="false" placeholder="T…" value="<?= e(old('payout_address', (string) $seller['payout_address'])) ?>"<?= error_attrs('payout_address') ?>><?= error_text('payout_address') ?>
          <p class="hint">Double-check it — payouts sent to a wrong address cannot be recovered. Use a TRON address only.</p></div>
        <div class="field"><label for="password">Account password <span class="muted">(required to change the payout address)</span></label><input class="input" id="password" name="password" type="password" autocomplete="current-password"<?= error_attrs('password') ?>><?= error_text('password') ?></div>
      </fieldset>
      <button class="btn btn-dark" type="submit">Save settings</button>
    </form>
  </div>
</div>
