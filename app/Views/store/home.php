<?php
/**
 * Homepage: hero → trust strip → featured edits → shop by category → new
 * arrivals → promo band → best sellers → category rails → story → sell on
 * URAVEX → sign-up. Every block is controlled from Admin → Content → Homepage.
 */
$hero = $heroes[0] ?? null;
$assurances = $home['assurances'] ?? [];
$edits = array_values(array_filter($tiles, static fn ($t) => str_starts_with($t['href'], '/collections/')));
$cats = array_values(array_filter($tiles, static fn ($t) => str_starts_with($t['href'], '/category/') && $t['image']));
// Hero collage: distinct product photos from the newest / best-selling items.
$collage = [];
$seenCat = [];
foreach (array_merge($newArrivals, $bestSellers) as $p) {
    $cat = (string) ($p['category_id'] ?? ('p' . $p['id']));
    if ($p['image'] && !isset($collage[$p['image']]) && !isset($seenCat[$cat])) {
        $collage[$p['image']] = $p;
        $seenCat[$cat] = true; // one per category for variety
    }
}
$collage = array_slice(array_values($collage), 0, 3);
$prices = array_filter(array_map(static fn ($p) => (int) ($p['price'] ?? 0), array_merge($newArrivals, $bestSellers)));
$fromPrice = $prices ? min($prices) : null;
// Sale items shown on the promo band when it has no image of its own.
$saleItems = [];
foreach (array_merge($newArrivals, $bestSellers) as $p) {
    if (!empty($p['on_sale']) && $p['image'] && !isset($saleItems[$p['id']])) {
        $saleItems[$p['id']] = $p;
    }
}
$saleItems = array_slice(array_values($saleItems), 0, 2);
$freeOver = (int) (App\Services\Settings::get('shipping.free_threshold_minor', 0));
// Scrolling highlights strip (assurance texts + store promises).
$ticker = array_values(array_filter(array_merge(
    ['New season styles just landed'],
    array_map(static fn ($a) => (string) ($a['title'] ?? ''), $assurances),
    $freeOver > 0 ? ['Free US shipping over ' . price($freeOver)] : [],
    ['Pay with USDT · BTC · ETH']
)));
// Category rails only when they fill a full row — no half-empty shelves.
$rails = array_values(array_filter($rails, static fn ($r) => count($r['products']) >= 4));
$section = static function (string $eyebrow, string $title, string $href, string $linkLabel, string $id): string {
    return '<div class="hx-head"><div><span class="eyebrow">' . e($eyebrow) . '</span><h2 id="' . e($id) . '">' . e($title) . '</h2></div>'
        . '<a class="link-arrow" href="' . e(url($href)) . '">' . e($linkLabel) . ' ' . icon('arrow-right', 'icon icon-sm') . '</a></div>';
};
$grid = static function (array $items, array $wishlistIds): string {
    $out = '<ul class="product-grid">';
    foreach (array_slice($items, 0, 8) as $p) {
        $out .= App\Core\View::partial('partials/product-card', ['p' => $p, 'wishlistIds' => $wishlistIds]);
    }
    return $out . '</ul>';
};
?>
<?php /* ── Hero ── */ ?>
<section class="hx-hero on-dark" aria-labelledby="hero-title">
  <?php if ($hero && $hero['image_path'] && $collage): ?><img class="hx-hero-bg" src="<?= e(media($hero['image_path'], 1600)) ?>" alt="" width="1600" height="600" decoding="async"><?php endif; ?>
  <div class="container hx-hero-inner">
    <div class="hx-hero-copy">
      <span class="eyebrow"><?= e($hero['eyebrow'] ?? $sec['hero_eyebrow']) ?></span>
      <h1 id="hero-title"><?= e($hero['title'] ?? $sec['hero_title']) ?></h1>
      <p class="hx-lead"><?= e($hero['subtitle'] ?? $sec['hero_subtitle']) ?></p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e(url($hero['cta_href'] ?? '/shop')) ?>"><?= e($hero['cta_label'] ?? 'Shop now') ?> <?= icon('arrow-right', 'icon icon-sm') ?></a>
        <?php if (!$hero || $hero['secondary_cta_label']): ?><a class="btn btn-outline-light" href="<?= e(url($hero['secondary_cta_href'] ?? '/new-arrivals')) ?>"><?= e($hero['secondary_cta_label'] ?? 'New arrivals') ?></a><?php endif; ?>
      </div>
      <?php if ($assurances && empty($sec['show_assurances'])): /* the trust strip below shows these otherwise */ ?>
        <ul class="hx-hero-points">
          <?php foreach (array_slice($assurances, 0, 3) as $a): ?><li><?= icon('check', 'icon icon-sm') ?><?= e($a['text'] ?? $a['title'] ?? '') ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="hx-hero-visual" aria-hidden="true">
      <?php if ($hero && $hero['image_path'] && !$collage): ?>
        <figure class="hx-photo hx-photo-single"><img src="<?= e(media($hero['image_path'], 1200)) ?>" srcset="<?= e(media_srcset($hero['image_path'])) ?>" sizes="(min-width:1024px) 45vw, 100vw" alt="" fetchpriority="high" width="1200" height="1500"></figure>
      <?php else: ?>
        <?php foreach ($collage as $i => $p): ?>
          <figure class="hx-photo hx-photo-<?= $i + 1 ?>"><img src="<?= e(media($p['image'], 800)) ?>" alt="" width="600" height="800" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async"><figcaption><?= e($p['title']) ?><b><?= e(price((int) $p['price'])) ?></b></figcaption></figure>
        <?php endforeach; ?>
        <?php if ($collage): ?><span class="hx-badge"><strong><?= e($sec['new_title'] ?? 'New in') ?></strong><span>Autumn / Winter</span></span><?php endif; ?>
        <?php if ($collage && $fromPrice): ?><span class="hx-chip hx-chip-price">From <b><?= e(price($fromPrice)) ?></b></span><?php endif; ?>
        <?php if ($collage && $freeOver > 0): ?><span class="hx-chip hx-chip-ship"><?= icon('truck', 'icon icon-sm') ?> Free shipping <?= e(price($freeOver)) ?>+</span><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php /* ── Highlights ticker (decorative; the content is also in the trust strip) ── */ ?>
<?php if ($ticker): ?>
<div class="hx-ticker" aria-hidden="true">
  <div class="hx-ticker-track">
    <?php for ($copy = 0; $copy < 2; $copy++): ?>
      <ul><?php foreach ($ticker as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<?php /* ── Trust strip ── */ ?>
<?php if ($assurances && !empty($sec['show_assurances'])): ?>
<section class="hx-trust" aria-label="Why shop with URAVEX">
  <ul class="container">
    <?php foreach ($assurances as $a): ?><li><span class="hx-trust-icon"><?= icon($a['icon'] ?? 'check') ?></span><span><strong><?= e($a['title'] ?? '') ?></strong><small><?= e($a['text'] ?? '') ?></small></span></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<div class="hx-page">
  <?php /* ── Featured edits (homepage collections) ── */ ?>
  <?php if ($edits): ?>
  <section class="hx-section" aria-labelledby="edits-title">
    <div class="container">
      <?= $section('Curated', $sec['collections_title'] ?: 'Featured edits', '/collections', 'All collections', 'edits-title') ?>
      <div class="hx-edits">
        <?php foreach (array_slice($edits, 0, 3) as $i => $t): ?>
          <a class="hx-edit hx-edit-<?= $i + 1 ?>" href="<?= e(url($t['href'])) ?>">
            <?php if ($t['image']): ?><img src="<?= e(media($t['image'], 800)) ?>" alt="" loading="lazy" width="600" height="800"><?php endif; ?>
            <span class="hx-edit-copy">
              <span class="hx-edit-title"><?= e($t['title']) ?></span>
              <?php if ($t['subtitle']): ?><span class="hx-edit-sub"><?= e($t['subtitle']) ?></span><?php endif; ?>
              <span class="hx-edit-cta">Shop the edit <?= icon('arrow-right', 'icon icon-sm') ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* ── Shop by category ── */ ?>
  <?php if ($cats): ?>
  <section class="hx-section hx-band hx-band-sage" aria-labelledby="cats-title">
    <div class="container">
      <?= $section('Browse', 'Shop by category', '/shop', 'Shop all', 'cats-title') ?>
      <ul class="hx-cats" tabindex="0" aria-label="Categories — scroll sideways on small screens">
        <?php foreach ($cats as $t): ?>
          <li><a class="hx-cat" href="<?= e(url($t['href'])) ?>"><span class="hx-cat-media"><img src="<?= e(media($t['image'], 400)) ?>" alt="" loading="lazy" width="400" height="500"></span><span class="hx-cat-name"><?= e($t['title']) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <?php /* ── New arrivals ── */ ?>
  <?php if (!empty($sec['show_new']) && $newArrivals): ?>
  <section class="hx-section" aria-labelledby="new-title">
    <div class="container">
      <?= $section($sec['new_eyebrow'] ?: 'Just landed', $sec['new_title'] ?: 'New arrivals', '/new-arrivals', 'See all', 'new-title') ?>
      <?= $grid($newArrivals, $wishlistIds) ?>
    </div>
  </section>
  <?php endif; ?>

  <?php /* ── Promo bands ── */ ?>
  <?php foreach ($promos as $promo): ?>
  <section class="hx-section" aria-label="<?= e($promo['title']) ?>">
    <div class="container">
      <div class="hx-promo on-dark<?= $promo['image_path'] || $saleItems ? ' has-image' : '' ?>">
        <div class="hx-promo-copy">
          <?php if ($promo['eyebrow']): ?><span class="eyebrow"><?= e($promo['eyebrow']) ?></span><?php endif; ?>
          <h2><?= e($promo['title']) ?></h2>
          <?php if ($promo['subtitle']): ?><p><?= e($promo['subtitle']) ?></p><?php endif; ?>
          <?php if ($promo['cta_label'] && $promo['cta_href']): ?><a class="btn btn-primary" href="<?= e(url($promo['cta_href'])) ?>"><?= e($promo['cta_label']) ?> <?= icon('arrow-right', 'icon icon-sm') ?></a><?php endif; ?>
        </div>
        <?php if ($promo['image_path']): ?><img src="<?= e(media($promo['image_path'], 800)) ?>" alt="<?= e($promo['image_alt']) ?>" loading="lazy" width="800" height="600">
        <?php elseif ($saleItems): ?>
          <div class="hx-promo-items">
            <?php foreach ($saleItems as $i => $p): ?>
              <a class="hx-promo-item hx-promo-item-<?= $i + 1 ?>" href="<?= e(url('/product/' . $p['slug'])) ?>"><img src="<?= e(media($p['image'], 400)) ?>" alt="" loading="lazy" width="400" height="533"><span><?= e($p['title']) ?><b><?= e(price((int) $p['price'])) ?> <s><?= e(price((int) $p['regular'])) ?></s></b></span></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

  <?php /* ── Best sellers ── */ ?>
  <?php if ($bestSellers): ?>
  <section class="hx-section hx-band hx-band-blush" aria-labelledby="best-title">
    <div class="container">
      <?= $section($sec['bestsellers_eyebrow'] ?: 'Most loved', $sec['bestsellers_title'] ?: 'Best sellers', '/shop?sort=best_selling', 'See all', 'best-title') ?>
      <?= $grid($bestSellers, $wishlistIds) ?>
    </div>
  </section>
  <?php endif; ?>

  <?php /* ── Category rails ── */ ?>
  <?php foreach ($rails as $rail): ?>
  <section class="hx-section" aria-labelledby="rail-<?= (int) $rail['category']['id'] ?>">
    <div class="container">
      <?= $section('Category', $rail['category']['name'], '/category/' . $rail['category']['slug'], 'See all', 'rail-' . (int) $rail['category']['id']) ?>
      <ul class="rail-track" tabindex="0" aria-label="<?= e($rail['category']['name']) ?> products — scroll sideways">
        <?php foreach ($rail['products'] as $p): ?><?= App\Core\View::partial('partials/product-card', ['p' => $p, 'wishlistIds' => $wishlistIds]) ?><?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endforeach; ?>

  <?php /* ── Brand story ── */ ?>
  <?php if (!empty($sec['show_story'])): ?>
  <section class="hx-section" aria-labelledby="story-title">
    <div class="container">
      <div class="hx-story">
        <div class="hx-story-media"><img src="<?= e(!empty($home['story_image']) ? media($home['story_image'], 800) : asset('brand/uravex-logo-1200.png')) ?>" alt="<?= !empty($home['story_image']) ? 'URAVEX atelier' : 'URAVEX Apparel logo' ?>" loading="lazy" width="800" height="1000"></div>
        <div class="hx-story-copy">
          <span class="eyebrow"><?= e($home['story_eyebrow'] ?? '') ?></span>
          <h2 id="story-title"><?= e($home['story_title'] ?? '') ?></h2>
          <hr class="gold-rule">
          <p><?= nl2br(e($home['story_body'] ?? '')) ?></p>
          <?php if (!empty($home['story_cta_href'])): ?><a class="btn btn-outline" href="<?= e(url($home['story_cta_href'])) ?>"><?= e($home['story_cta_label'] ?? 'Read more') ?></a><?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* ── Sell on URAVEX ── */ ?>
  <?php if (App\Services\Marketplace::setting('applications_open')): ?>
  <section class="hx-section" aria-labelledby="sell-title">
    <div class="container">
      <div class="hx-sell">
        <div>
          <span class="eyebrow">For brands &amp; makers</span>
          <h2 id="sell-title">Sell on URAVEX</h2>
          <p>Open your own shop, reach customers across the United States and get paid in USDT. No upfront fees — we only earn when you do.</p>
        </div>
        <a class="btn btn-dark" href="<?= e(url('/sell')) ?>">Open a shop <?= icon('arrow-right', 'icon icon-sm') ?></a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php /* ── Newsletter ── */ ?>
  <?php if (!empty($sec['show_newsletter'])): ?>
  <section class="hx-section" aria-labelledby="nl-title">
    <div class="container center container-narrow">
      <span class="eyebrow">Newsletter</span>
      <h2 id="nl-title"><?= e($home['newsletter_title'] ?? 'Join the list') ?></h2>
      <p class="muted"><?= e($home['newsletter_body'] ?? '') ?></p>
      <form class="newsletter-form" method="post" action="<?= e(url('/newsletter')) ?>">
        <?= csrf_field() ?>
        <label class="sr-only" for="nl-email">Email address</label>
        <input class="input" id="nl-email" type="email" name="email" required autocomplete="email" placeholder="Your email address" maxlength="191">
        <input type="text" name="website" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button class="btn btn-dark" type="submit">Subscribe</button>
      </form>
    </div>
  </section>
  <?php endif; ?>
</div>

<?php /* ── Sign-up call to action (guests) / deals by email (signed in) ── */ ?>
<?php if (!empty($sec['show_signup'])): ?>
<section class="m-signup on-dark" aria-labelledby="signup-title">
  <div class="container center container-narrow">
    <span class="m-pill"><?= e($sec['signup_eyebrow']) ?></span>
    <h2 id="signup-title"><?= e($sec['signup_title']) ?></h2>
    <p><?= e($sec['signup_body']) ?></p>
    <?php if (!$user): ?>
      <a class="btn btn-primary m-signup-btn" href="<?= e(url('/register')) ?>"><?= e($sec['signup_cta']) ?> <?= icon('arrow-right', 'icon icon-sm') ?></a>
    <?php else: ?>
      <form class="newsletter-form" method="post" action="<?= e(url('/newsletter')) ?>">
        <?= csrf_field() ?>
        <label class="sr-only" for="su-email">Email address</label>
        <input class="input" id="su-email" type="email" name="email" required autocomplete="email" value="<?= e($user['email']) ?>">
        <input type="text" name="website" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button class="btn btn-primary" type="submit">Get deals by email</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
