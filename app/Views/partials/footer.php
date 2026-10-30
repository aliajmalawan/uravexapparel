<?php
$store = $settings['store'] ?? [];
$social = array_filter($settings['social'] ?? []);
$footer = $settings['footer'] ?? [];
$sec = $settings['sections'] ?? [];
$policyPages = App\Core\Database::get()->all("SELECT slug, title FROM content_pages WHERE status = 'active' AND slug NOT IN ('about') ORDER BY title");
$methods = array_filter(App\Payments\Registry::all(), static fn ($p) => $p->isImplemented() && $p->isConfigured() && $p->isEnabled() && $p->code() !== 'sandbox');
$whatsapp = preg_replace('/\D/', '', (string) ($store['whatsapp'] ?? ''));
$chatHref = $whatsapp !== '' ? 'https://wa.me/' . $whatsapp : url('/contact');
$methodIcons = ['cod' => 'cash', 'bank_transfer' => 'bank', 'usdt_trc20' => 'coin', 'usdt_erc20' => 'coin', 'btc' => 'coin', 'eth' => 'coin'];
$phone = trim((string) ($store['support_phone'] ?? ''));
?>
<footer class="site-footer ft on-dark">
  <div class="container ft-top">
    <div class="ft-brand">
      <a class="ft-logo" href="<?= e(url('/')) ?>" aria-label="URAVEX Store home"><img src="<?= e(brand_emblem(96)) ?>" width="44" height="44" alt=""><span class="brand-word">URAVEX<small>STORE</small></span></a>
      <?php if (!empty($footer['about'])): ?><p class="ft-about"><?= e($footer['about']) ?></p><?php endif; ?>
      <form class="ft-news" method="post" action="<?= e(url('/newsletter')) ?>">
        <?= csrf_field() ?>
        <label for="ft-email">Get new drops &amp; private sales by email</label>
        <div class="ft-news-row">
          <input class="input" id="ft-email" type="email" name="email" required autocomplete="email" placeholder="Your email address" maxlength="191">
          <input type="text" name="website" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button class="btn btn-primary" type="submit">Subscribe</button>
        </div>
        <p class="ft-fine">No spam. Unsubscribe anytime.</p>
      </form>
      <?php if ($social): ?>
        <div class="socials">
          <?php foreach ($social as $network => $href): ?>
            <a href="<?= e($href) ?>" rel="noopener noreferrer" target="_blank" aria-label="URAVEX on <?= e(ucfirst($network)) ?> (opens in new tab)"><?= icon($network) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <nav class="ft-links" aria-label="Footer">
      <?php foreach ((array) ($settings['navigation']['footer'] ?? []) as $col): ?>
        <div>
          <?php if ($col['title'] !== ''): ?><h2><?= e($col['title']) ?></h2><?php endif; ?>
          <ul>
            <?php foreach ($col['links'] as $link): ?>
              <li><a href="<?= e(url($link['href'])) ?>"<?= preg_match('#^https?://#i', $link['href']) ? ' rel="noopener" target="_blank"' : '' ?>><?= e($link['label']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
      <div>
        <h2><?= e($settings['navigation']['footer_pages_title'] ?? 'Company') ?></h2>
        <ul>
          <li><a href="<?= e(url('/about')) ?>">About URAVEX</a></li>
          <li><a href="<?= e(url('/sell')) ?>">Sell on URAVEX</a></li>
          <?php foreach ($policyPages as $p): ?>
            <li><a href="<?= e(url('/pages/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </nav>

    <div class="ft-contact">
      <h2><?= e($sec['help_title'] ?? 'Need help?') ?></h2>
      <?php if (!empty($sec['help_body'])): ?><p class="small"><?= e($sec['help_body']) ?></p><?php endif; ?>
      <ul class="ft-contact-list">
        <?php if (!empty($store['support_email'])): ?><li><?= icon('mail', 'icon icon-sm') ?><a href="mailto:<?= e($store['support_email']) ?>"><?= e($store['support_email']) ?></a></li><?php endif; ?>
        <?php if ($phone !== ''): ?><li><?= icon('phone', 'icon icon-sm') ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        <?php if (!empty($store['business_hours'])): ?><li><?= icon('history', 'icon icon-sm') ?><span><?= e($store['business_hours']) ?></span></li><?php endif; ?>
        <?php if (!empty($store['address'])): ?><li><?= icon('pin', 'icon icon-sm') ?><span><?= e($store['address']) ?></span></li><?php endif; ?>
      </ul>
      <a class="btn-chat" href="<?= e($chatHref) ?>"<?= $whatsapp !== '' ? ' rel="noopener" target="_blank"' : '' ?>><?= icon('chat', 'icon icon-sm') ?> <?= $whatsapp !== '' ? 'Chat on WhatsApp' : 'Contact us' ?></a>
      <?php if ($methods): ?>
        <h2 class="ft-pay-title">We accept</h2>
        <ul class="pay-badges" aria-label="Payment methods">
          <?php foreach ($methods as $code => $p): ?><li><?= icon($methodIcons[$code] ?? 'card', 'icon icon-sm') ?><?= e($p instanceof App\Payments\CryptoTransferProvider ? ($p->asset() === 'USDT' ? 'USDT ' . $p->badge() : $p->badge()) : $p->label()) ?></li><?php endforeach; ?>
        </ul>
        <p class="ft-fine"><?= icon('shield', 'icon icon-sm') ?> Payments go straight to our wallet — verified on the blockchain.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="ft-bottom">
    <div class="container">
      <span>© <?= gmdate('Y') ?> <?= e($footer['copyright'] ?? 'URAVEX Apparel') ?></span>
      <span class="footer-legal-links">
        <?php foreach ($policyPages as $p): if (!in_array($p['slug'], ['privacy-policy', 'terms'], true)) continue; ?><a href="<?= e(url('/pages/' . $p['slug'])) ?>"><?= e($p['title']) ?></a><?php endforeach; ?>
      </span>
      <span class="footer-legal-currency"><?= App\Core\View::partial('partials/currency-switcher', ['place' => 'f']) ?></span>
      <a href="#top" class="ft-totop"><?= icon('arrow-up', 'icon icon-sm') ?> Back to top</a>
    </div>
  </div>
</footer>
