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
?>
<div class="page-top">
  <div class="container">
    <a href="#top" class="page-top-link"><?= icon('arrow-up', 'icon icon-sm') ?> Page top</a>
    <?php if (!empty($store['support_email'])): ?><a href="mailto:<?= e($store['support_email']) ?>" class="page-top-mail"><?= e($store['support_email']) ?></a><?php endif; ?>
  </div>
</div>
<footer class="site-footer on-dark">
  <div class="container footer-grid">
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
    <div class="footer-side">
      <?php if ($methods): ?>
        <h2>We accept</h2>
        <ul class="pay-badges" aria-label="Payment methods">
          <?php foreach ($methods as $code => $p): ?><li><?= icon($methodIcons[$code] ?? 'card', 'icon icon-sm') ?><?= e($p instanceof App\Payments\CryptoTransferProvider ? ($p->asset() === 'USDT' ? 'USDT ' . $p->badge() : $p->badge()) : $p->label()) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <h2 class="mt-4"><?= e($sec['help_title'] ?? 'Need help?') ?></h2>
      <p class="small"><?= e($sec['help_body'] ?? '') ?></p>
      <a class="btn-chat" href="<?= e($chatHref) ?>"<?= $whatsapp !== '' ? ' rel="noopener" target="_blank"' : '' ?>><?= icon('chat', 'icon icon-sm') ?> <?= $whatsapp !== '' ? 'Chat on WhatsApp' : 'Contact us' ?></a>
      <?php if ($social): ?>
        <div class="socials">
          <?php foreach ($social as $network => $href): ?>
            <a href="<?= e($href) ?>" rel="noopener noreferrer" target="_blank" aria-label="URAVEX on <?= e(ucfirst($network)) ?> (opens in new tab)"><?= icon($network) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</footer>
<div class="footer-legal">
  <div class="container">
    <span>© <?= gmdate('Y') ?> <?= e($footer['copyright'] ?? 'URAVEX Apparel') ?></span>
    <span class="footer-legal-links">
      <?php foreach ($policyPages as $p): if (!in_array($p['slug'], ['privacy-policy', 'terms'], true)) continue; ?><a href="<?= e(url('/pages/' . $p['slug'])) ?>"><?= e($p['title']) ?></a><?php endforeach; ?>
    </span>
    <span class="footer-legal-currency"><?= App\Core\View::partial('partials/currency-switcher', ['place' => 'f']) ?></span>
  </div>
</div>
