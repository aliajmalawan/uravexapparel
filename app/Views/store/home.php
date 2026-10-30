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

<?php /* ── Promises: white bar overlapping the hero ── */ ?>
<?php if ($assurances && !empty($sec['show_assurances'])): ?>
<section class="hp-promises" aria-label="Why shop with URAVEX">
  <div class="container">
    <ul class="hp-promise-bar">
      <?php foreach ($assurances as $a): ?>
        <li><span class="hp-ico"><?= icon($a['icon'] ?? 'check') ?></span><span><strong><?= e($a['title'] ?? '') ?></strong><small><?= e($a['text'] ?? '') ?></small></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php /* ── Categories ── */ ?>
<?php if ($cats): ?>
<section class="hp-section" aria-labelledby="cats-title">
  <div class="container">
    <div class="hp-head">
      <div><span class="hp-eyebrow">Browse</span><h2 id="cats-title">Shop by category</h2></div>
      <a class="hp-link" href="<?= e(url('/shop')) ?>">View all products <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <ul class="hp-cats" tabindex="0" aria-label="Categories — scroll sideways on small screens">
      <?php foreach ($cats as $t): ?>
        <li><a class="hp-cat" href="<?= e(url($t['href'])) ?>">
          <img src="<?= e(media($t['image'], 400)) ?>" alt="" loading="lazy" width="400" height="500">
          <span class="hp-cat-info"><strong><?= e($t['title']) ?></strong><?php if (!empty($t['count'])): ?><small><?= e(pluralize((int) $t['count'], 'product')) ?></small><?php endif; ?></span>
          <span class="hp-cat-go" aria-hidden="true"><?= icon('arrow-right', 'icon icon-sm') ?></span>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php /* ── New arrivals ── */ ?>
<?php if (!empty($sec['show_new']) && $newArrivals): ?>
<section class="hp-section hp-soft" aria-labelledby="new-title">
  <div class="container">
    <div class="hp-head">
      <div><span class="hp-eyebrow"><?= e($sec['new_eyebrow'] ?: 'Just landed') ?></span><h2 id="new-title"><?= e($sec['new_title'] ?: 'New arrivals') ?></h2></div>
      <a class="hp-link" href="<?= e(url('/new-arrivals')) ?>">View all <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <div class="hp-products"><?= $grid($newArrivals, $wishlistIds) ?></div>
  </div>
</section>
<?php endif; ?>

<?php /* ── About + store figures ── */ ?>
<section class="hp-section hp-about" aria-labelledby="about-title">
  <div class="container hp-about-grid">
    <div class="hp-about-art" aria-hidden="true">
      <?php foreach (array_slice($mosaic, 2, 2) as $i => $p): ?>
        <div class="hp-about-img hp-about-img-<?= $i + 1 ?>"><img src="<?= e(media($p['image'], 600)) ?>" alt="" width="400" height="500" loading="lazy"></div>
      <?php endforeach; ?>
      <div class="hp-about-badge"><strong><?= (int) $stats['products'] ?>+</strong><span>styles in store</span></div>
    </div>
    <div class="hp-about-copy">
      <span class="hp-eyebrow"><?= e($home['story_eyebrow'] ?: 'About us') ?></span>
      <h2 id="about-title"><?= e($home['story_title'] ?: 'Made with intent. Built to last.') ?></h2>
      <?php if (trim((string) ($home['story_body'] ?? '')) !== ''): ?><p class="hp-lead"><?= nl2br(e($home['story_body'])) ?></p><?php endif; ?>
      <div class="hp-values">
        <div><span class="hp-ico"><?= icon('eye') ?></span><span><strong><?= e($home['vision_title'] ?? 'Our vision') ?></strong><small><?= e($home['vision_body'] ?? '') ?></small></span></div>
        <div><span class="hp-ico"><?= icon('history') ?></span><span><strong><?= e($home['history_title'] ?? 'Our story') ?></strong><small><?= e($home['history_body'] ?? '') ?></small></span></div>
      </div>
      <ul class="hp-figures" aria-label="URAVEX in numbers">
        <li><strong><?= (int) $stats['products'] ?>+</strong><span>Products</span></li>
        <li><strong><?= (int) $stats['categories'] ?></strong><span>Categories</span></li>
        <li><strong><?= (int) $stats['colours'] ?>+</strong><span>Colours</span></li>
        <li><strong>50</strong><span>States we ship to</span></li>
      </ul>
      <?php if (!empty($home['story_cta_href'])): ?><a class="btn hp-btn-dark" href="<?= e(url($home['story_cta_href'])) ?>"><?= e($home['story_cta_label'] ?? 'Read more') ?><?= icon('arrow-right', 'icon icon-sm') ?></a><?php endif; ?>
    </div>
  </div>
</section>

<?php /* ── Collections: dark band, same look as the hero ── */ ?>
<?php if ($edits): ?>
<section class="hp-section hp-dark" aria-labelledby="edits-title">
  <div class="container">
    <div class="hp-head">
      <div>
        <span class="hp-eyebrow">Our collections</span>
        <h2 id="edits-title"><?= e($sec['collections_title'] ?: 'Curated edits, made to last') ?></h2>
        <p class="hp-sub">Signature tailoring, everyday essentials and seasonal layers — each edit is put together by our team so you can shop a complete look in minutes.</p>
      </div>
      <a class="btn hp-btn-gold" href="<?= e(url('/collections')) ?>">All collections <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <ul class="hp-edits">
      <?php foreach (array_slice($edits, 0, 3) as $t): ?>
        <li><a class="hp-edit" href="<?= e(url($t['href'])) ?>">
          <?php if ($t['image']): ?><span class="hp-edit-media"><img src="<?= e(media($t['image'], 600)) ?>" alt="" loading="lazy" width="600" height="450"></span><?php endif; ?>
          <span class="hp-edit-body">
            <strong><?= e($t['title']) ?></strong>
            <?php if ($t['subtitle']): ?><small><?= e($t['subtitle']) ?></small><?php endif; ?>
            <span class="hp-edit-more"><?= !empty($t['count']) ? e(pluralize((int) $t['count'], 'piece')) . ' · ' : '' ?>Shop the edit <?= icon('arrow-right', 'icon icon-sm') ?></span>
          </span>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php /* ── Featured offer ── */ ?>
<?php if ($promo && !empty($sec['show_promos'])): ?>
<section class="hp-section" aria-labelledby="promo-title">
  <div class="container">
    <div class="hp-offer">
      <div class="hp-offer-media">
        <?php if ($promo['image_path']): ?><img src="<?= e(media($promo['image_path'], 800)) ?>" alt="<?= e($promo['image_alt']) ?>" loading="lazy" width="800" height="600">
        <?php elseif ($saleItem): ?><img src="<?= e(media($saleItem['image'], 800)) ?>" alt="<?= e($saleItem['title']) ?>" loading="lazy" width="600" height="800"><?php endif; ?>
        <?php if ($saleItem && (int) $saleItem['regular'] > 0): ?><span class="hp-offer-off">−<?= (int) round(100 - 100 * (int) $saleItem['price'] / (int) $saleItem['regular']) ?>%</span><?php endif; ?>
      </div>
      <div class="hp-offer-copy">
        <span class="hp-eyebrow"><?= e($promo['eyebrow'] ?: 'Limited time') ?></span>
        <h2 id="promo-title"><?= e($promo['title']) ?></h2>
        <?php if ($promo['subtitle']): ?><p class="hp-lead"><?= e($promo['subtitle']) ?></p><?php endif; ?>
        <?php if ($saleItem): ?>
          <a class="hp-offer-item" href="<?= e(url('/product/' . $saleItem['slug'])) ?>">
            <span><small>Featured deal</small><strong><?= e($saleItem['title']) ?></strong></span>
            <span class="hp-offer-price"><strong><?= e(price((int) $saleItem['price'])) ?></strong><s><?= e(price((int) $saleItem['regular'])) ?></s></span>
          </a>
        <?php endif; ?>
        <?php if ($promo['cta_label'] && $promo['cta_href']): ?><a class="btn hp-btn-gold" href="<?= e(url($promo['cta_href'])) ?>"><?= e($promo['cta_label']) ?><?= icon('arrow-right', 'icon icon-sm') ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* ── Best sellers ── */ ?>
<?php if ($bestSellers): ?>
<section class="hp-section hp-soft" aria-labelledby="best-title">
  <div class="container">
    <div class="hp-head">
      <div><span class="hp-eyebrow"><?= e($sec['bestsellers_eyebrow'] ?: 'Most loved') ?></span><h2 id="best-title"><?= e($sec['bestsellers_title'] ?: 'Best sellers') ?></h2></div>
      <a class="hp-link" href="<?= e(url('/shop')) ?>">Shop all <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <div class="hp-products"><?= $grid($bestSellers, $wishlistIds) ?></div>
  </div>
</section>
<?php endif; ?>

<?php /* ── Delivery + sell on URAVEX ── */ ?>
<?php $sellOpen = App\Services\Marketplace::setting('applications_open'); ?>
<section class="hp-section" aria-label="Delivery and selling">
  <div class="container hp-duo<?= $sellOpen ? '' : ' is-single' ?>">
    <div class="hp-promo-card is-dark">
      <span class="hp-ico is-gold"><?= icon('truck') ?></span>
      <span class="hp-eyebrow">Delivery</span>
      <h2 id="ship-title">We ship all over the United States</h2>
      <p>Tracked delivery to every state<?= $freeOver > 0 ? ', free on orders over ' . e(price($freeOver)) : '' ?>. Pay securely with USDT, BTC or ETH.</p>
      <a class="btn hp-btn-gold" href="<?= e(url('/shop')) ?>">Start shopping <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <?php if ($sellOpen): ?>
    <div class="hp-promo-card">
      <span class="hp-ico"><?= icon('home') ?></span>
      <span class="hp-eyebrow">For brands &amp; makers</span>
      <h2 id="sell-title">Sell on URAVEX</h2>
      <p>Open your own shop, reach customers across the United States and get paid in USDT. No upfront fees — we only earn when you do.</p>
      <a class="btn hp-btn-dark" href="<?= e(url('/sell')) ?>">Open a shop <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php /* ── Membership ── */ ?>
<?php if (!empty($sec['show_signup'])): ?>
<section class="hp-section hp-member-wrap" aria-labelledby="signup-title">
  <div class="container">
    <div class="hp-member">
      <div class="hp-member-copy">
        <span class="hp-eyebrow"><?= e($sec['signup_eyebrow']) ?></span>
        <h2 id="signup-title"><?= e($sec['signup_title']) ?></h2>
        <p><?= e($sec['signup_body']) ?></p>
        <?php if (!$user): ?>
          <div class="hp-actions"><a class="btn hp-btn-gold" href="<?= e(url('/register')) ?>"><?= e($sec['signup_cta']) ?></a><a class="btn hp-btn-line" href="<?= e(url('/login')) ?>">Sign in</a></div>
        <?php else: ?>
          <form class="hp-member-form" method="post" action="<?= e(url('/newsletter')) ?>">
            <?= csrf_field() ?>
            <label class="sr-only" for="su-email">Email address</label>
            <input class="input" id="su-email" type="email" name="email" required autocomplete="email" value="<?= e($user['email']) ?>">
            <input type="text" name="website" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button class="btn hp-btn-gold" type="submit">Get deals by email</button>
          </form>
        <?php endif; ?>
      </div>
      <ul class="hp-perks" aria-label="Member benefits">
        <li><span class="hp-ico is-gold"><?= icon('heart') ?></span><span><strong>Save favourites</strong><small>Keep a wishlist on every device</small></span></li>
        <li><span class="hp-ico is-gold"><?= icon('box') ?></span><span><strong>Track every order</strong><small>Live status and tracking links</small></span></li>
        <li><span class="hp-ico is-gold"><?= icon('sparkle') ?></span><span><strong>First to new drops</strong><small>Early access to new collections</small></span></li>
        <li><span class="hp-ico is-gold"><?= icon('tag') ?></span><span><strong>Members-only offers</strong><small>Private sales by email</small></span></li>
      </ul>
    </div>
  </div>
</section>
<?php endif; ?>
