<?php
/**
 * Homepage: hero (copy + product arch) → promises → category strip → new arrivals →
 * featured products → story & figures → collections → featured offer → on sale →
 * best sellers → delivery / sell → membership. Texts, banners and section switches: Admin → Content → Homepage.
 *
 * Content is complete in the HTML; motion (public/assets/js/app.js, "Home motion")
 * only enhances it and is skipped entirely under reduced motion or with JS off.
 */
$hero = $heroes[0] ?? null;
$assurances = $home['assurances'] ?? [];
$edits = array_values(array_filter($tiles, static fn ($t) => str_starts_with($t['href'], '/collections/')));
$cats = array_values(array_filter($tiles, static fn ($t) => str_starts_with($t['href'], '/category/') && $t['image']));
// Distinct product photos for the hero arch and the story frames.
$photos = [];
foreach (array_merge($newArrivals, $featured, $bestSellers) as $p) {
    if ($p['image'] && !isset($photos[$p['image']])) {
        $photos[$p['image']] = $p;
    }
}
$photos = array_values($photos);
$heroPhoto = $photos[0] ?? null;
$heroCards = array_slice($photos, 1, 2);
$storyPhotos = array_slice($photos, 3, 2);
$saleItem = null;
foreach (array_merge($onSale, $bestSellers, $newArrivals, $featured) as $p) {
    if (!empty($p['on_sale']) && $p['image']) {
        $saleItem = $p;
        break;
    }
}
$saleOff = $saleItem && (int) $saleItem['regular'] > 0 ? (int) round(100 - 100 * (int) $saleItem['price'] / (int) $saleItem['regular']) : 0;
$promo = $promos[0] ?? null;
$freeOver = (int) App\Services\Settings::get('shipping.free_threshold_minor', 0);
$heroTitle = (string) ($hero['title'] ?? $sec['hero_title']);

/** Section heading with the stitched gold seam under the eyebrow. */
$head = static function (string $eyebrow, string $title, string $id, string $tone = ''): string {
    return '<div class="hm-head' . ($tone ? ' ' . $tone : '') . '" data-rv="mask">'
        . '<span class="hm-eyebrow"><svg class="hm-seam" viewBox="0 0 40 4" aria-hidden="true" focusable="false"><path d="M1 2h38" pathLength="1"/></svg>' . e($eyebrow) . '</span>'
        . '<h2 id="' . e($id) . '">' . e($title) . '</h2></div>';
};
$grid = static function (array $items, array $wishlistIds): string {
    $out = '<ul class="product-grid" data-rv="stagger">';
    foreach (array_slice($items, 0, 8) as $p) {
        $out .= App\Core\View::partial('partials/product-card', ['p' => $p, 'wishlistIds' => $wishlistIds]);
    }
    return $out . '</ul>';
};
?>
<div class="hm" data-home>

<?php /* ── Hero ── */ ?>
<section class="hm-hero" aria-labelledby="hero-title">
  <?php if ($hero && $hero['image_path']): ?><img class="hm-hero-bg" src="<?= e(media($hero['image_path'], 1600)) ?>" srcset="<?= e(media_srcset($hero['image_path'])) ?>" sizes="100vw" alt="" fetchpriority="high" width="1600" height="900"><?php endif; ?>
  <div class="hm-hero-glow" aria-hidden="true"></div>
  <div class="container hm-hero-inner">
    <div class="hm-hero-copy">
      <span class="hm-pill"><i aria-hidden="true"></i><?= e($hero['eyebrow'] ?? $sec['hero_eyebrow']) ?></span>
      <h1 id="hero-title"><?= e($heroTitle) ?></h1>
      <p class="hm-hero-lead"><?= e($hero['subtitle'] ?? $sec['hero_subtitle']) ?></p>
      <div class="hm-actions">
        <a class="btn hm-btn hm-btn-gold" href="<?= e(url($hero['cta_href'] ?? '/shop')) ?>"><?= e($hero['cta_label'] ?? 'Shop now') ?><?= icon('arrow-right', 'icon icon-sm') ?></a>
        <?php if (!$hero || $hero['secondary_cta_label']): ?><a class="btn hm-btn hm-btn-line" href="<?= e(url($hero['secondary_cta_href'] ?? '/new-arrivals')) ?>"><?= e($hero['secondary_cta_label'] ?? 'New arrivals') ?></a><?php endif; ?>
      </div>
      <ul class="hm-hero-trust" aria-label="Why shop with URAVEX">
        <li><?= icon('truck', 'icon icon-sm') ?><span><strong>US-wide delivery</strong><?= $freeOver > 0 ? 'Free over ' . e(money($freeOver)) : 'Tracked to every state' ?></span></li>
        <li><?= icon('shield', 'icon icon-sm') ?><span><strong>Secure crypto pay</strong>USDT · BTC · ETH</span></li>
        <li><?= icon('refresh', 'icon icon-sm') ?><span><strong>Easy exchanges</strong>Hassle-free returns</span></li>
      </ul>
    </div>

    <?php if ($heroPhoto): ?>
    <div class="hm-hero-visual">
      <div class="hm-ring" aria-hidden="true"><span></span></div>
      <a class="hm-arch" href="<?= e(url('/product/' . $heroPhoto['slug'])) ?>" data-tilt>
        <span class="hm-arch-frame"><img src="<?= e(media($heroPhoto['image'], 800)) ?>" alt="<?= e($heroPhoto['image_alt'] ?: $heroPhoto['title']) ?>" width="600" height="760" fetchpriority="high"></span>
        <span class="hm-arch-tag"><span><?= e($heroPhoto['category_name'] ?? 'Featured') ?></span><strong><?= e($heroPhoto['title']) ?></strong><em><?= e(price($heroPhoto['price'])) ?></em></span>
      </a>
      <?php foreach ($heroCards as $i => $c): ?>
        <a class="hm-float hm-float-<?= $i + 1 ?>" href="<?= e(url('/product/' . $c['slug'])) ?>" data-depth="<?= $i === 0 ? '0.06' : '0.1' ?>">
          <img src="<?= e(media($c['image'], 400)) ?>" alt="" width="64" height="64" loading="lazy">
          <span><strong><?= e($c['title']) ?></strong><em><?= e(price($c['price'])) ?><?php if (!empty($c['on_sale'])): ?> <s><?= e(price($c['regular'])) ?></s><?php endif; ?></em></span>
        </a>
      <?php endforeach; ?>
      <p class="hm-badge"><strong data-count="<?= (int) $stats['products'] ?>" data-suffix="+"><?= number_format((int) $stats['products']) ?>+</strong><span>styles in store</span></p>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php /* ── Promises ── */ ?>
<?php if ($assurances && !empty($sec['show_assurances'])): ?>
<section class="hm-promises" aria-label="Why shop with URAVEX">
  <div class="container">
    <ul class="hm-promise-bar" data-rv="stagger">
      <?php foreach ($assurances as $a): ?>
        <li><span class="hm-ico"><?= icon($a['icon'] ?? 'check') ?></span><span><strong><?= e($a['title'] ?? '') ?></strong><small><?= e($a['text'] ?? '') ?></small></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php /* ── Category marquee (pausable, off under reduced motion) + category strip ── */ ?>
<?php if ($cats): ?>
<section class="hm-section hm-cats-section" aria-labelledby="cats-title">
  <div class="hm-ticker" data-ticker>
    <div class="hm-ticker-track">
      <?php for ($copy = 0; $copy < 2; $copy++): ?>
        <ul class="hm-ticker-list"<?= $copy ? ' aria-hidden="true"' : ' aria-label="All categories"' ?>>
          <?php foreach ($cats as $t): ?>
            <li><a href="<?= e(url($t['href'])) ?>"<?= $copy ? ' tabindex="-1"' : '' ?>><?= e($t['title']) ?><?php if (!empty($t['count'])): ?> <small><?= (int) $t['count'] ?></small><?php endif; ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endfor; ?>
    </div>
    <button class="hm-ticker-toggle" type="button" data-ticker-toggle aria-pressed="false"><span class="sr-only">Pause the moving category list</span><?= icon('pause', 'icon icon-sm') ?></button>
  </div>

  <div class="container">
    <div class="hm-head-row">
      <?= $head('Browse', 'Shop by category', 'cats-title') ?>
      <div class="hm-strip-nav">
        <button class="hm-round" type="button" data-strip-prev aria-controls="cat-strip"><?= icon('chevron-left', 'icon icon-sm') ?><span class="sr-only">Previous categories</span></button>
        <button class="hm-round" type="button" data-strip-next aria-controls="cat-strip"><?= icon('chevron-right', 'icon icon-sm') ?><span class="sr-only">More categories</span></button>
        <a class="hm-link" href="<?= e(url('/shop')) ?>">View all <?= icon('arrow-right', 'icon icon-sm') ?></a>
      </div>
    </div>
    <ul class="hm-strip" id="cat-strip" tabindex="0" aria-label="Categories — scroll sideways for more" data-strip data-rv="stagger">
      <?php foreach ($cats as $t): ?>
        <li><a class="hm-cat" href="<?= e(url($t['href'])) ?>">
          <span class="hm-cat-media"><img src="<?= e(media($t['image'], 400)) ?>" alt="" loading="lazy" width="400" height="500"></span>
          <span class="hm-cat-info">
            <strong><?= e($t['title']) ?></strong>
            <?php if (!empty($t['count'])): ?><small><?= e(pluralize((int) $t['count'], 'product')) ?></small><?php endif; ?>
          </span>
        </a></li>
      <?php endforeach; ?>
    </ul>
    <div class="hm-strip-seam" aria-hidden="true"><span data-strip-progress></span></div>
  </div>
</section>
<?php endif; ?>

<?php /* ── New arrivals ── */ ?>
<?php if (!empty($sec['show_new']) && $newArrivals): ?>
<section class="hm-section hm-soft" aria-labelledby="new-title">
  <div class="container">
    <div class="hm-head-row">
      <?= $head($sec['new_eyebrow'] ?: 'Just landed', $sec['new_title'] ?: 'New arrivals', 'new-title') ?>
      <a class="hm-link" href="<?= e(url('/new-arrivals')) ?>">View all <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <div class="hp-products hm-products"><?= $grid($newArrivals, $wishlistIds) ?></div>
  </div>
</section>
<?php endif; ?>

<?php /* ── Featured products ── */ ?>
<?php if ($featured): ?>
<section class="hm-section" aria-labelledby="featured-title">
  <div class="container">
    <div class="hm-head-row">
      <?= $head($sec['featured_eyebrow'] ?? '' ?: 'Editor\'s picks', $sec['featured_title'] ?? '' ?: 'Featured products', 'featured-title') ?>
      <a class="hm-link" href="<?= e(url('/shop')) ?>">Shop all <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <div class="hp-products hm-products"><?= $grid($featured, $wishlistIds) ?></div>
  </div>
</section>
<?php endif; ?>

<?php /* ── Story + store figures ── */ ?>
<section class="hm-section hm-story<?= $featured ? ' hm-soft' : '' ?>" aria-labelledby="about-title">
  <div class="container hm-story-grid">
    <div class="hm-story-art" aria-hidden="true">
      <?php foreach ($storyPhotos as $i => $p): ?>
        <div class="hm-story-frame hm-story-frame-<?= $i + 1 ?>" data-depth="<?= $i === 0 ? '0.06' : '-0.08' ?>" data-rv="<?= $i === 0 ? 'left' : 'right' ?>"><img src="<?= e(media($p['image'], 600)) ?>" alt="" width="400" height="500" loading="lazy"></div>
      <?php endforeach; ?>
    </div>
    <div class="hm-story-copy">
      <?= $head($home['story_eyebrow'] ?: 'About us', $home['story_title'] ?: 'Made with intent. Built to last.', 'about-title') ?>
      <?php if (trim((string) ($home['story_body'] ?? '')) !== ''): ?><p class="hm-lead" data-rv="rise"><?= nl2br(e($home['story_body'])) ?></p><?php endif; ?>
      <div class="hm-values" data-rv="stagger">
        <div><span class="hm-ico"><?= icon('eye') ?></span><span><strong><?= e($home['vision_title'] ?? 'Our vision') ?></strong><small><?= e($home['vision_body'] ?? '') ?></small></span></div>
        <div><span class="hm-ico"><?= icon('history') ?></span><span><strong><?= e($home['history_title'] ?? 'Our story') ?></strong><small><?= e($home['history_body'] ?? '') ?></small></span></div>
      </div>
      <dl class="hm-figures" data-rv="stagger">
        <div><dt>Products</dt><dd data-count="<?= (int) $stats['products'] ?>" data-suffix="+"><?= number_format((int) $stats['products']) ?>+</dd></div>
        <div><dt>Categories</dt><dd data-count="<?= (int) $stats['categories'] ?>"><?= number_format((int) $stats['categories']) ?></dd></div>
        <div><dt>Colours</dt><dd data-count="<?= (int) $stats['colours'] ?>" data-suffix="+"><?= number_format((int) $stats['colours']) ?>+</dd></div>
        <div><dt>States we ship to</dt><dd data-count="50">50</dd></div>
      </dl>
      <?php if (!empty($home['story_cta_href'])): ?><a class="btn hm-btn hm-btn-dark" href="<?= e(url($home['story_cta_href'])) ?>"><?= e($home['story_cta_label'] ?? 'Read more') ?><?= icon('arrow-right', 'icon icon-sm') ?></a><?php endif; ?>
    </div>
  </div>
</section>

<?php /* ── Collections ── */ ?>
<?php if ($edits): ?>
<section class="hm-section hm-dark" aria-labelledby="edits-title">
  <div class="container">
    <div class="hm-head-row">
      <div>
        <?= $head('Our collections', $sec['collections_title'] ?: 'Curated edits, made to last', 'edits-title', 'on-dark') ?>
        <p class="hm-sub" data-rv="rise">Signature tailoring, everyday essentials and seasonal layers — each edit is put together by our team so you can shop a complete look in minutes.</p>
      </div>
      <a class="btn hm-btn hm-btn-gold" href="<?= e(url('/collections')) ?>">All collections <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <ul class="hm-edits" data-rv="stagger">
      <?php foreach (array_slice($edits, 0, 3) as $t): ?>
        <li><a class="hm-edit" href="<?= e(url($t['href'])) ?>">
          <?php if ($t['image']): ?><span class="hm-edit-media"><img src="<?= e(media($t['image'], 600)) ?>" alt="" loading="lazy" width="600" height="450"></span><?php endif; ?>
          <span class="hm-edit-body">
            <strong><?= e($t['title']) ?></strong>
            <?php if ($t['subtitle']): ?><small><?= e($t['subtitle']) ?></small><?php endif; ?>
            <span class="hm-edit-more"><?= !empty($t['count']) ? e(pluralize((int) $t['count'], 'piece')) . ' · ' : '' ?>Shop the edit <?= icon('arrow-right', 'icon icon-sm') ?></span>
          </span>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php /* ── Featured offer ── */ ?>
<?php if ($promo && !empty($sec['show_promos'])): ?>
<section class="hm-section" aria-labelledby="promo-title">
  <div class="container">
    <div class="hm-offer" data-rv="scale">
      <div class="hm-offer-media">
        <?php if ($promo['image_path']): ?><img src="<?= e(media($promo['image_path'], 800)) ?>" alt="<?= e($promo['image_alt']) ?>" loading="lazy" width="800" height="600">
        <?php elseif ($saleItem): ?><img src="<?= e(media($saleItem['image'], 800)) ?>" alt="<?= e($saleItem['title']) ?>" loading="lazy" width="600" height="800"><?php endif; ?>
        <?php if ($saleOff > 0): ?>
          <p class="hm-off"><svg viewBox="0 0 64 64" aria-hidden="true" focusable="false"><circle class="hm-arc-track" cx="32" cy="32" r="28"/><circle class="hm-arc-fill" cx="32" cy="32" r="28" pathLength="100" stroke-dasharray="<?= $saleOff ?> 100" data-arc/></svg><strong><?= $saleOff ?>%</strong><span>off</span></p>
        <?php endif; ?>
      </div>
      <div class="hm-offer-copy">
        <?= $head($promo['eyebrow'] ?: 'Limited time', (string) $promo['title'], 'promo-title') ?>
        <?php if ($promo['subtitle']): ?><p class="hm-lead"><?= e($promo['subtitle']) ?></p><?php endif; ?>
        <?php if ($saleItem): ?>
          <a class="hm-offer-item" href="<?= e(url('/product/' . $saleItem['slug'])) ?>">
            <span><small>Featured deal</small><strong><?= e($saleItem['title']) ?></strong></span>
            <span class="hm-offer-price"><strong><?= e(price((int) $saleItem['price'])) ?></strong><s><?= e(price((int) $saleItem['regular'])) ?></s></span>
          </a>
        <?php endif; ?>
        <?php if ($promo['cta_label'] && $promo['cta_href']): ?><a class="btn hm-btn hm-btn-gold" href="<?= e(url($promo['cta_href'])) ?>"><?= e($promo['cta_label']) ?><?= icon('arrow-right', 'icon icon-sm') ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* ── On sale ── */ ?>
<?php if ($onSale): ?>
<section class="hm-section hm-soft" aria-labelledby="sale-title">
  <div class="container">
    <div class="hm-head-row">
      <?= $head($sec['sale_eyebrow'] ?? '' ?: 'Limited time', $sec['sale_title'] ?? '' ?: 'On sale now', 'sale-title') ?>
      <a class="hm-link" href="<?= e(url('/sale')) ?>">View all deals <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <div class="hp-products hm-products"><?= $grid($onSale, $wishlistIds) ?></div>
  </div>
</section>
<?php endif; ?>

<?php /* ── Best sellers ── */ ?>
<?php if ($bestSellers): ?>
<section class="hm-section<?= $onSale ? '' : ' hm-soft' ?>" aria-labelledby="best-title">
  <div class="container">
    <div class="hm-head-row">
      <?= $head($sec['bestsellers_eyebrow'] ?: 'Most loved', $sec['bestsellers_title'] ?: 'Best sellers', 'best-title') ?>
      <a class="hm-link" href="<?= e(url('/shop')) ?>">Shop all <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <div class="hp-products hm-products"><?= $grid($bestSellers, $wishlistIds) ?></div>
  </div>
</section>
<?php endif; ?>

<?php /* ── Delivery + sell on URAVEX ── */ ?>
<?php $sellOpen = App\Services\Marketplace::setting('applications_open'); ?>
<section class="hm-section" aria-label="Delivery and selling">
  <div class="container hm-duo<?= $sellOpen ? '' : ' is-single' ?>" data-rv="stagger">
    <div class="hm-card is-dark">
      <span class="hm-ico is-gold"><?= icon('truck') ?></span>
      <span class="hm-eyebrow on-dark"><svg class="hm-seam" viewBox="0 0 40 4" aria-hidden="true" focusable="false"><path d="M1 2h38" pathLength="1"/></svg>Delivery</span>
      <h2 id="ship-title">We ship all over the United States</h2>
      <p>Tracked delivery to every state<?= $freeOver > 0 ? ', free on orders over ' . e(price($freeOver)) : '' ?>. Pay securely with USDT, BTC or ETH.</p>
      <a class="btn hm-btn hm-btn-gold" href="<?= e(url('/shop')) ?>">Start shopping <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <?php if ($sellOpen): ?>
    <div class="hm-card">
      <span class="hm-ico"><?= icon('home') ?></span>
      <span class="hm-eyebrow"><svg class="hm-seam" viewBox="0 0 40 4" aria-hidden="true" focusable="false"><path d="M1 2h38" pathLength="1"/></svg>For brands &amp; makers</span>
      <h2 id="sell-title">Sell on URAVEX</h2>
      <p>Open your own shop, reach customers across the United States and get paid in USDT. No upfront fees — we only earn when you do.</p>
      <a class="btn hm-btn hm-btn-dark" href="<?= e(url('/sell')) ?>">Open a shop <?= icon('arrow-right', 'icon icon-sm') ?></a>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php /* ── Membership ── */ ?>
<?php if (!empty($sec['show_signup'])): ?>
<section class="hm-section hm-member-wrap" aria-labelledby="signup-title">
  <div class="container">
    <div class="hm-member" data-rv="scale">
      <div class="hm-member-copy">
        <span class="hm-eyebrow on-dark"><svg class="hm-seam" viewBox="0 0 40 4" aria-hidden="true" focusable="false"><path d="M1 2h38" pathLength="1"/></svg><?= e($sec['signup_eyebrow']) ?></span>
        <h2 id="signup-title"><?= e($sec['signup_title']) ?></h2>
        <p><?= e($sec['signup_body']) ?></p>
        <?php if (!$user): ?>
          <div class="hm-actions"><a class="btn hm-btn hm-btn-gold" href="<?= e(url('/register')) ?>"><?= e($sec['signup_cta']) ?></a><a class="btn hm-btn hm-btn-line" href="<?= e(url('/login')) ?>">Sign in</a></div>
        <?php else: ?>
          <form class="hm-member-form" method="post" action="<?= e(url('/newsletter')) ?>">
            <?= csrf_field() ?>
            <label class="sr-only" for="su-email">Email address</label>
            <input class="input" id="su-email" type="email" name="email" required autocomplete="email" value="<?= e($user['email']) ?>">
            <input type="text" name="website" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button class="btn hm-btn hm-btn-gold" type="submit">Get deals by email</button>
          </form>
        <?php endif; ?>
      </div>
      <ul class="hm-perks" aria-label="Member benefits" data-rv="stagger">
        <li><span class="hm-ico is-gold"><?= icon('heart') ?></span><span><strong>Save favourites</strong><small>Keep a wishlist on every device</small></span></li>
        <li><span class="hm-ico is-gold"><?= icon('box') ?></span><span><strong>Track every order</strong><small>Live status and tracking links</small></span></li>
        <li><span class="hm-ico is-gold"><?= icon('sparkle') ?></span><span><strong>First to new drops</strong><small>Early access to new collections</small></span></li>
        <li><span class="hm-ico is-gold"><?= icon('tag') ?></span><span><strong>Members-only offers</strong><small>Private sales by email</small></span></li>
      </ul>
    </div>
  </div>
</section>
<?php endif; ?>

</div>
