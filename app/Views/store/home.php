<?php
/**
 * Homepage (corporate-catalogue layout): hero (copy + product collage) → promises →
 * about us → store figures → categories → new arrivals → collections band →
 * featured offer → best sellers → shipping band → sell on URAVEX → membership.
 * Texts, banners and section switches: Admin → Content → Homepage.
 */
$hero = $heroes[0] ?? null;
$assurances = $home['assurances'] ?? [];
$edits = array_values(array_filter($tiles, static fn ($t) => str_starts_with($t['href'], '/collections/')));
$cats = array_values(array_filter($tiles, static fn ($t) => str_starts_with($t['href'], '/category/') && $t['image']));
// Distinct product photos for the hero frame and the about mosaic.
$photos = [];
foreach (array_merge($newArrivals, $bestSellers) as $p) {
    if ($p['image'] && !isset($photos[$p['image']])) {
        $photos[$p['image']] = $p;
    }
}
$photos = array_values($photos);
$heroPhoto = $photos[0] ?? null;
$mosaic = array_slice($photos, 1, 9);
$saleItem = null;
foreach (array_merge($bestSellers, $newArrivals) as $p) {
    if (!empty($p['on_sale']) && $p['image']) {
        $saleItem = $p;
        break;
    }
}
$promo = $promos[0] ?? null;
$freeOver = (int) App\Services\Settings::get('shipping.free_threshold_minor', 0);
$heading = static function (string $eyebrow, string $title, string $id, bool $center = true): string {
    return '<div class="px-heading' . ($center ? ' is-center' : '') . '"><span class="px-eyebrow">' . e($eyebrow) . '</span><h2 id="' . e($id) . '">' . e($title) . '</h2></div>';
};
$grid = static function (array $items, array $wishlistIds): string {
    $out = '<ul class="product-grid">';
    foreach (array_slice($items, 0, 8) as $p) {
        $out .= App\Core\View::partial('partials/product-card', ['p' => $p, 'wishlistIds' => $wishlistIds]);
    }
    return $out . '</ul>';
};
?>
<?php /* ── Hero: copy + trust points on the left, product collage on the right ── */
$heroCards = array_slice($photos, 1, 2);
$heroTitle = (string) ($hero['title'] ?? $sec['hero_title']);
?>
<section class="hx2" aria-labelledby="hero-title">
  <?php if ($hero && $hero['image_path']): ?><img class="hx2-bg" src="<?= e(media($hero['image_path'], 1600)) ?>" srcset="<?= e(media_srcset($hero['image_path'])) ?>" sizes="100vw" alt="" fetchpriority="high" width="1600" height="900"><?php endif; ?>
  <div class="container hx2-inner">
    <div class="hx2-copy">
      <span class="hx2-pill"><i aria-hidden="true"></i><?= e($hero['eyebrow'] ?? $sec['hero_eyebrow']) ?></span>
      <h1 id="hero-title"><?= e($heroTitle) ?></h1>
      <p class="hx2-lead"><?= e($hero['subtitle'] ?? $sec['hero_subtitle']) ?></p>
      <div class="hx2-actions">
        <a class="btn hx2-btn-gold" href="<?= e(url($hero['cta_href'] ?? '/shop')) ?>"><?= e($hero['cta_label'] ?? 'Shop now') ?><?= icon('arrow-right', 'icon icon-sm') ?></a>
        <?php if (!$hero || $hero['secondary_cta_label']): ?><a class="btn hx2-btn-line" href="<?= e(url($hero['secondary_cta_href'] ?? '/new-arrivals')) ?>"><?= e($hero['secondary_cta_label'] ?? 'New arrivals') ?></a><?php endif; ?>
      </div>
      <ul class="hx2-trust" aria-label="Why shop with URAVEX">
        <li><?= icon('truck', 'icon icon-sm') ?><span><strong>US-wide delivery</strong><?= $freeOver > 0 ? 'Free over ' . e(money($freeOver)) : 'Tracked to every state' ?></span></li>
        <li><?= icon('shield', 'icon icon-sm') ?><span><strong>Secure crypto pay</strong>USDT · BTC · ETH</span></li>
        <li><?= icon('refresh', 'icon icon-sm') ?><span><strong>Easy exchanges</strong>Hassle-free returns</span></li>
      </ul>
    </div>

    <?php if ($heroPhoto): ?>
    <div class="hx2-visual">
      <div class="hx2-ring" aria-hidden="true"></div>
      <a class="hx2-main" href="<?= e(url('/product/' . $heroPhoto['slug'])) ?>" aria-label="<?= e($heroPhoto['title']) ?>">
        <img src="<?= e(media($heroPhoto['image'], 800)) ?>" alt="<?= e($heroPhoto['image_alt'] ?: $heroPhoto['title']) ?>" width="600" height="760" fetchpriority="high">
        <span class="hx2-main-tag"><span><?= e($heroPhoto['category_name'] ?? 'Featured') ?></span><strong><?= e($heroPhoto['title']) ?></strong><em><?= e(price($heroPhoto['price'])) ?></em></span>
      </a>
      <?php foreach ($heroCards as $i => $c): ?>
        <a class="hx2-float hx2-float-<?= $i + 1 ?>" href="<?= e(url('/product/' . $c['slug'])) ?>">
          <img src="<?= e(media($c['image'], 400)) ?>" alt="" width="64" height="64" loading="lazy">
          <span><strong><?= e($c['title']) ?></strong><em><?= e(price($c['price'])) ?><?php if (!empty($c['on_sale'])): ?> <s><?= e(price($c['regular'])) ?></s><?php endif; ?></em></span>
        </a>
      <?php endforeach; ?>
      <div class="hx2-badge" aria-hidden="true"><strong><?= (int) $stats['products'] ?>+</strong><span>styles</span></div>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php /* ── Promises ── */ ?>
<?php if ($assurances && !empty($sec['show_assurances'])): ?>
<section class="px-section px-features" aria-label="Why shop with URAVEX">
  <ul class="container">
    <?php foreach ($assurances as $a): ?>
      <li><span class="px-icon-tile"><?= icon($a['icon'] ?? 'check') ?></span><h3><?= e($a['title'] ?? '') ?></h3><p><?= e($a['text'] ?? '') ?></p></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php /* ── About us ── */ ?>
<section class="px-section px-about" aria-labelledby="about-title">
  <div class="container px-about-grid">
    <div>
      <?= $heading($home['story_eyebrow'] ?: 'About Us', $home['story_title'] ?: 'Made with intent. Built to last.', 'about-title', false) ?>
      <p class="px-lead"><?= nl2br(e($home['story_body'] ?? '')) ?></p>
      <div class="px-cards-2">
        <div class="px-card px-card-dark"><span class="px-icon-tile is-light"><?= icon('eye') ?></span><h3><?= e($home['vision_title'] ?? 'Our Vision') ?></h3><p><?= e($home['vision_body'] ?? '') ?></p></div>
        <div class="px-card px-card-light"><span class="px-icon-tile"><?= icon('history') ?></span><h3><?= e($home['history_title'] ?? 'Our Story') ?></h3><p><?= e($home['history_body'] ?? '') ?></p></div>
      </div>
      <?php if (!empty($home['story_cta_href'])): ?><a class="btn px-btn-dark mt-4" href="<?= e(url($home['story_cta_href'])) ?>"><?= e($home['story_cta_label'] ?? 'Read more') ?></a><?php endif; ?>
    </div>
    <div class="px-about-art" aria-hidden="true">
      <div class="px-mosaic"><?php foreach ($mosaic as $p): ?><img src="<?= e(media($p['image'], 400)) ?>" alt="" width="200" height="266" loading="lazy"><?php endforeach; ?></div>
      <div class="px-stat-float"><?= icon('tag') ?><strong><?= (int) $stats['products'] ?>+</strong><span>Styles in store</span></div>
    </div>
  </div>
</section>

<?php /* ── Store figures (real numbers from the catalogue) ── */ ?>
<section class="px-section px-stats-wrap" aria-label="URAVEX in numbers">
  <div class="container">
    <ul class="px-stats">
      <li><?= icon('tag') ?><strong><?= (int) $stats['products'] ?>+</strong><span>Products</span></li>
      <li><?= icon('grid') ?><strong><?= (int) $stats['categories'] ?></strong><span>Categories</span></li>
      <li><?= icon('sparkle') ?><strong><?= (int) $stats['colours'] ?>+</strong><span>Colours</span></li>
      <li><?= icon('truck') ?><strong>50</strong><span>States we ship to</span></li>
    </ul>
  </div>
</section>

<?php /* ── Categories ── */ ?>
<?php if ($cats): ?>
<section class="px-section" aria-labelledby="cats-title">
  <div class="container">
    <?= $heading('Browse', 'Shop by Category', 'cats-title') ?>
    <ul class="px-cats" tabindex="0" aria-label="Categories — scroll sideways on small screens">
      <?php foreach ($cats as $t): ?>
        <li><a class="px-cat" href="<?= e(url($t['href'])) ?>"><span class="px-cat-media"><img src="<?= e(media($t['image'], 400)) ?>" alt="" loading="lazy" width="400" height="500"></span><span class="px-cat-name"><?= e($t['title']) ?></span><span class="px-more">Shop now <?= icon('arrow-right', 'icon icon-sm') ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php /* ── New arrivals ── */ ?>
<?php if (!empty($sec['show_new']) && $newArrivals): ?>
<section class="px-section" aria-labelledby="new-title">
  <div class="container">
    <?= $heading($sec['new_eyebrow'] ?: 'Just landed', $sec['new_title'] ?: 'New Arrivals', 'new-title') ?>
    <?= $grid($newArrivals, $wishlistIds) ?>
    <p class="center mt-4"><a class="btn px-btn-dark" href="<?= e(url('/new-arrivals')) ?>">View all new arrivals</a></p>
  </div>
</section>
<?php endif; ?>

<?php /* ── Collections band (slanted, over photo) ── */ ?>
<?php if ($edits): ?>
<section class="px-band" aria-labelledby="edits-title">
  <?php if ($hero && $hero['image_path']): ?><img class="px-band-bg" src="<?= e(media($hero['image_path'], 1600)) ?>" alt="" loading="lazy" width="1600" height="900"><?php endif; ?>
  <div class="container">
    <div class="px-band-head">
      <div>
        <span class="px-eyebrow">Our Collections</span>
        <h2 id="edits-title"><?= e($sec['collections_title'] ?: 'Curated edits, made to last') ?></h2>
        <p>Signature tailoring, everyday essentials and seasonal layers — each edit is put together by our team so you can shop a complete look in minutes.</p>
      </div>
      <a class="btn px-btn-silver" href="<?= e(url('/collections')) ?>">All collections</a>
    </div>
    <div class="px-glass-cards">
      <?php foreach (array_slice($edits, 0, 3) as $i => $t): ?>
        <a class="px-glass" href="<?= e(url($t['href'])) ?>">
          <span class="px-icon-tile <?= $i === 0 ? 'is-lilac' : '' ?>"><?= icon(['layers', 'sparkle', 'box'][$i] ?? 'tag') ?></span>
          <h3><?= e($t['title']) ?></h3>
          <?php if ($t['subtitle']): ?><p><?= e($t['subtitle']) ?></p><?php endif; ?>
          <span class="px-more">Shop the edit <?= icon('arrow-right', 'icon icon-sm') ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* ── Featured offer ── */ ?>
<?php if ($promo && !empty($sec['show_promos'])): ?>
<section class="px-section" aria-labelledby="promo-title">
  <div class="container">
    <?= $heading($promo['eyebrow'] ?: 'Limited time', $promo['title'], 'promo-title') ?>
    <div class="px-split">
      <div class="px-split-media">
        <?php if ($promo['image_path']): ?><img src="<?= e(media($promo['image_path'], 800)) ?>" alt="<?= e($promo['image_alt']) ?>" loading="lazy" width="800" height="600">
        <?php elseif ($saleItem): ?><img src="<?= e(media($saleItem['image'], 800)) ?>" alt="<?= e($saleItem['title']) ?>" loading="lazy" width="600" height="800"><?php endif; ?>
      </div>
      <div class="px-split-copy">
        <?php if ($promo['subtitle']): ?><p class="px-lead"><?= e($promo['subtitle']) ?></p><?php endif; ?>
        <?php if ($saleItem): ?><p class="px-price-line"><?= e($saleItem['title']) ?> — <strong><?= e(price((int) $saleItem['price'])) ?></strong> <s><?= e(price((int) $saleItem['regular'])) ?></s></p><?php endif; ?>
        <?php if ($promo['cta_label'] && $promo['cta_href']): ?><a class="btn px-btn-dark" href="<?= e(url($promo['cta_href'])) ?>"><?= e($promo['cta_label']) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* ── Best sellers ── */ ?>
<?php if ($bestSellers): ?>
<section class="px-section px-tint" aria-labelledby="best-title">
  <div class="container">
    <?= $heading($sec['bestsellers_eyebrow'] ?: 'Most loved', $sec['bestsellers_title'] ?: 'Best Sellers', 'best-title') ?>
    <?= $grid($bestSellers, $wishlistIds) ?>
  </div>
</section>
<?php endif; ?>

<?php /* ── Shipping band (slanted) ── */ ?>
<section class="px-band px-band-slim" aria-labelledby="ship-title">
  <div class="container center">
    <span class="px-eyebrow">Delivery</span>
    <h2 id="ship-title">We ship all over the United States</h2>
    <p>Tracked delivery to every state<?= $freeOver > 0 ? ', free on orders over ' . e(price($freeOver)) : '' ?>. Pay securely with USDT, BTC or ETH.</p>
    <a class="btn px-btn-white" href="<?= e(url('/shop')) ?>">Start shopping</a>
  </div>
</section>

<?php /* ── Sell on URAVEX ── */ ?>
<?php if (App\Services\Marketplace::setting('applications_open')): ?>
<section class="px-section" aria-labelledby="sell-title">
  <div class="container">
    <div class="px-sell">
      <span class="px-icon-tile is-lilac"><?= icon('home') ?></span>
      <div><span class="px-eyebrow">For brands &amp; makers</span><h2 id="sell-title">Sell on URAVEX</h2><p>Open your own shop, reach customers across the United States and get paid in USDT. No upfront fees — we only earn when you do.</p></div>
      <a class="btn px-btn-dark" href="<?= e(url('/sell')) ?>">Open a shop</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* ── Membership ── */ ?>
<?php if (!empty($sec['show_signup'])): ?>
<section class="px-section px-member-wrap" aria-labelledby="signup-title">
  <div class="container">
    <div class="px-member">
      <div>
        <span class="px-eyebrow"><?= e($sec['signup_eyebrow']) ?></span>
        <h2 id="signup-title"><?= e($sec['signup_title']) ?></h2>
        <p><?= e($sec['signup_body']) ?></p>
        <?php if (!$user): ?>
          <div class="px-actions"><a class="btn px-btn-white" href="<?= e(url('/register')) ?>"><?= e($sec['signup_cta']) ?></a><a class="btn px-btn-ghost" href="<?= e(url('/login')) ?>">Sign in</a></div>
        <?php else: ?>
          <form class="ft-news-row px-member-form" method="post" action="<?= e(url('/newsletter')) ?>">
            <?= csrf_field() ?>
            <label class="sr-only" for="su-email">Email address</label>
            <input class="input" id="su-email" type="email" name="email" required autocomplete="email" value="<?= e($user['email']) ?>">
            <input type="text" name="website" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button class="btn px-btn-white" type="submit">Get deals by email</button>
          </form>
        <?php endif; ?>
      </div>
      <ul class="px-perks" aria-label="Member benefits">
        <li><span class="px-icon-tile is-light"><?= icon('heart') ?></span><span><strong>Save favourites</strong><small>Keep a wishlist on every device</small></span></li>
        <li><span class="px-icon-tile is-light"><?= icon('box') ?></span><span><strong>Track every order</strong><small>Live status and tracking links</small></span></li>
        <li><span class="px-icon-tile is-light"><?= icon('sparkle') ?></span><span><strong>First to new drops</strong><small>Early access to new collections</small></span></li>
        <li><span class="px-icon-tile is-light"><?= icon('tag') ?></span><span><strong>Members-only offers</strong><small>Private sales by email</small></span></li>
      </ul>
    </div>
  </div>
</section>
<?php endif; ?>
