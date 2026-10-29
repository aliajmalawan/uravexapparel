<?= App\Core\View::partial('admin/content/_tabs') ?>
<?php
$field = static function (string $group, string $name, string $label, $value, string $type = 'text', string $hint = '') {
    $id = $group . '-' . $name;
    $val = e(is_bool($value) ? '' : (string) $value);
    $input = $type === 'textarea'
        ? "<textarea class=\"textarea\" id=\"{$id}\" name=\"{$name}\" rows=\"3\">{$val}</textarea>"
        : "<input class=\"input\" id=\"{$id}\" name=\"{$name}\" type=\"{$type}\" value=\"{$val}\">";
    return "<div class=\"field\"><label for=\"{$id}\">" . e($label) . "</label>{$input}" . ($hint ? '<p class="hint">' . e($hint) . '</p>' : '') . error_text($name) . '</div>';
};
?>
<div class="grid-2">
  <form class="card" method="post" action="<?= e(url('/admin/content/settings/announcement')) ?>">
    <?= csrf_field() ?><h2>Announcement bar</h2>
    <label class="check mt-4"><input type="checkbox" name="enabled" value="1"<?= !empty($s['announcement']['enabled']) ? ' checked' : '' ?>> Show announcement bar</label>
    <?= $field('ann', 'text', 'Message', $s['announcement']['text'] ?? '') ?>
    <?= $field('ann', 'link', 'Link (optional)', $s['announcement']['link'] ?? '', 'text', 'e.g. /sale or https://…') ?>
    <button class="btn btn-dark btn-sm" type="submit">Save</button>
  </form>

  <form class="card" method="post" action="<?= e(url('/admin/content/settings/store')) ?>">
    <?= csrf_field() ?><h2>Store &amp; contact</h2>
    <div class="form-row c2 mt-4">
      <?= $field('store', 'name', 'Store name', $s['store']['name'] ?? '') ?>
      <?= $field('store', 'tagline', 'Tagline', $s['store']['tagline'] ?? '') ?>
      <?= $field('store', 'support_email', 'Support email', $s['store']['support_email'] ?? '', 'email') ?>
      <?= $field('store', 'support_phone', 'Support phone', $s['store']['support_phone'] ?? '', 'tel') ?>
      <?= $field('store', 'whatsapp', 'WhatsApp', $s['store']['whatsapp'] ?? '', 'tel') ?>
      <?= $field('store', 'business_hours', 'Business hours', $s['store']['business_hours'] ?? '') ?>
    </div>
    <?= $field('store', 'address', 'Address', $s['store']['address'] ?? '', 'textarea') ?>
    <button class="btn btn-dark btn-sm" type="submit">Save</button>
  </form>

  <form class="card" method="post" action="<?= e(url('/admin/content/settings/social')) ?>">
    <?= csrf_field() ?><h2>Social links</h2>
    <div class="mt-4"></div>
    <?php foreach (['instagram', 'facebook', 'tiktok', 'youtube', 'linkedin'] as $n): ?><?= $field('social', $n, ucfirst($n) . ' URL', $s['social'][$n] ?? '', 'url') ?><?php endforeach; ?>
    <button class="btn btn-dark btn-sm" type="submit">Save</button>
  </form>

  <div>
    <form class="card" method="post" action="<?= e(url('/admin/content/settings/footer')) ?>">
      <?= csrf_field() ?><h2>Footer</h2>
      <div class="mt-4"></div>
      <?= $field('footer', 'about', 'About text', $s['footer']['about'] ?? '', 'textarea') ?>
      <?= $field('footer', 'copyright', 'Copyright line', $s['footer']['copyright'] ?? '') ?>
      <p class="hint">Policy links in the footer come from active pages under “Pages &amp; policies”.</p>
      <button class="btn btn-dark btn-sm" type="submit">Save</button>
    </form>
    <form class="card" method="post" action="<?= e(url('/admin/content/settings/seo')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?><h2>Default SEO</h2>
      <div class="mt-4"></div>
      <?= $field('seo', 'default_title', 'Default page title', $s['seo']['default_title'] ?? '') ?>
      <?= $field('seo', 'default_description', 'Default meta description', $s['seo']['default_description'] ?? '', 'textarea') ?>
      <div class="field"><label for="og_image">Social share image (1200×630 recommended)</label><input class="input" id="og_image" type="file" name="og_image" accept="image/jpeg,image/png,image/webp"></div>
      <button class="btn btn-dark btn-sm" type="submit">Save</button>
    </form>
  </div>
</div>
