<?php
/** @var array $s */
$t = $s['totals'];
$lines = $s['lines'];
$afterDiscount = $t['subtotal'] - $t['discount'];
$issueText = [
    'unavailable' => 'No longer available — please remove it.',
    'out_of_stock' => 'Sold out — please remove it.',
    'insufficient' => 'Only limited stock left — quantity will be reduced.',
];
?>
<header class="container page-head"><h1>Your bag</h1></header>
<div class="container">
<?php if (!$lines): ?>
  <div class="empty-state">
    <?= icon('bag') ?>
    <h2>Your bag is empty</h2>
    <p class="muted">Discover new arrivals and signature pieces.</p>
    <div class="flex flex-center"><a class="btn btn-primary" href="<?= e(url('/new-arrivals')) ?>">Shop new arrivals</a><a class="btn btn-outline" href="<?= e(url('/account/wishlist')) ?>">View wishlist</a></div>
  </div>
<?php else: ?>
  <div class="cart-layout">
    <section aria-label="Items in your bag">
      <ul class="cart-lines">
        <?php foreach ($lines as $l): ?>
          <li class="cart-line">
            <a href="<?= e(url('/product/' . $l['slug'])) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(media($l['image'], 400)) ?>" alt="" width="110" height="146" loading="lazy"></a>
            <div>
              <h3><a href="<?= e(url('/product/' . $l['slug'])) ?>"><?= e($l['title']) ?></a></h3>
              <p class="small muted"><?= e($l['variant_label']) ?> · <?= e($l['sku']) ?></p>
              <p class="price"><?= e(money($l['unit'])) ?><?php if ($l['on_sale']): ?> <span class="was"><?= e(money($l['regular'])) ?></span><?php endif; ?></p>
              <?php if ($l['issue']): ?><p class="line-issue" role="alert"><?= e($issueText[$l['issue']]) ?></p><?php endif; ?>
              <div class="cart-line-actions">
                <?php if ($l['issue'] !== 'unavailable' && $l['issue'] !== 'out_of_stock'): ?>
                <form method="post" action="<?= e(url('/cart/update')) ?>" class="flex" data-qty-form>
                  <?= csrf_field() ?>
                  <input type="hidden" name="item_id" value="<?= (int) $l['item_id'] ?>">
                  <div class="qty" data-qty>
                    <button type="button" data-qty-dec aria-label="Decrease quantity of <?= e($l['title']) ?>">−</button>
                    <label class="sr-only" for="q<?= (int) $l['item_id'] ?>">Quantity of <?= e($l['title']) ?></label>
                    <input id="q<?= (int) $l['item_id'] ?>" type="number" name="quantity" value="<?= (int) min($l['quantity'], max(1, $l['stock'])) ?>" min="0" max="<?= (int) min(99, max(1, $l['stock'])) ?>" inputmode="numeric">
                    <button type="button" data-qty-inc aria-label="Increase quantity of <?= e($l['title']) ?>">+</button>
                  </div>
                  <button class="btn btn-sm btn-outline" type="submit" data-update-btn>Update</button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/cart/remove')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="item_id" value="<?= (int) $l['item_id'] ?>">
                  <button class="btn btn-link small" type="submit">Remove<span class="sr-only"> <?= e($l['title']) ?></span></button>
                </form>
              </div>
            </div>
            <p class="cart-line-total"><?= e(money($l['unit'] * min((int) $l['quantity'], max(0, (int) $l['stock'])))) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="mt-4"><a class="link-arrow" href="<?= e(url('/shop')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?> Continue shopping</a></p>
    </section>

    <aside class="summary" aria-labelledby="summary-title">
      <h2 id="summary-title">Order summary</h2>
      <?php if ($freeOver && !$t['free_shipping']): ?>
        <div class="free-ship-meter">
          <?php $gap = max(0, $freeOver - $afterDiscount); ?>
          <?= $gap > 0 ? 'Spend <strong>' . e(money($gap)) . '</strong> more for free delivery' : 'You qualify for free delivery' ?>
          <progress class="meter" max="<?= (int) $freeOver ?>" value="<?= (int) min($freeOver, $afterDiscount) ?>" aria-label="Progress towards free delivery"></progress>
        </div>
      <?php endif; ?>
      <dl>
        <div class="row"><dt>Subtotal</dt><dd><?= e(money($t['subtotal'])) ?></dd></div>
        <?php if ($t['discount'] > 0): ?><div class="row"><dt>Discount (<?= e($s['coupon']['code']) ?>)</dt><dd>−<?= e(money($t['discount'])) ?></dd></div><?php endif; ?>
        <div class="row"><dt>Estimated delivery</dt><dd><?= $estimate ? ($t['free_shipping'] ? 'Free' : e(money($t['shipping'])) . '*') : 'Calculated at checkout' ?></dd></div>
        <?php if ($t['tax'] > 0): ?><div class="row"><dt>Tax</dt><dd><?= e(money($t['tax'])) ?></dd></div><?php endif; ?>
        <div class="row total"><dt>Estimated total</dt><dd><?= e(money($t['total'])) ?></dd></div>
      </dl>
      <?= App\Core\View::partial('partials/currency-note', ['amount' => $t['total']]) ?>
      <?php if ($estimate && !$t['free_shipping']): ?><p class="xsmall muted">*Final delivery charge is confirmed at checkout for your city.</p><?php endif; ?>

      <?php if ($s['coupon']): ?>
        <form method="post" action="<?= e(url('/cart/coupon/remove')) ?>" class="flex between mt-4">
          <?= csrf_field() ?>
          <span class="small"><?= icon('tag', 'icon icon-sm') ?> <strong><?= e($s['coupon']['code']) ?></strong> applied</span>
          <button class="btn btn-link small" type="submit">Remove</button>
        </form>
      <?php else: ?>
        <form method="post" action="<?= e(url('/cart/coupon')) ?>" class="coupon-form" novalidate>
          <?= csrf_field() ?>
          <label class="sr-only" for="code">Discount code</label>
          <input class="input" id="code" name="code" placeholder="Discount code" value="<?= e(old('code')) ?>" maxlength="40" autocomplete="off"<?= error_attrs('coupon') ?>>
          <button class="btn btn-outline" type="submit">Apply</button>
        </form>
        <?= error_text('coupon') ?>
      <?php endif; ?>

      <?php if ($s['issues']): ?>
        <div class="alert alert-warning mt-4"><p>Some items need attention before checkout.</p></div>
      <?php endif; ?>
      <a class="btn btn-primary btn-block mt-4" href="<?= e(url('/checkout')) ?>"<?= $s['issues'] && !array_filter($lines, fn ($l) => $l['issue'] === null || $l['issue'] === 'insufficient') ? ' aria-disabled="true"' : '' ?>>Proceed to checkout</a>
      <p class="xsmall muted center mt-2"><?= icon('shield', 'icon icon-sm') ?> Secure checkout · <?= isset(App\Payments\Registry::all()['cod']) ? 'Cash on delivery available' : 'Pay with USDT, BTC or ETH' ?></p>
    </aside>
  </div>
<?php endif; ?>
</div>
