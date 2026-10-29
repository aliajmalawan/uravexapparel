<?= App\Core\View::partial('admin/content/_tabs') ?>
<?php
$tf = static fn (string $k, string $label, int $max = 160) => '<div class="field"><label for="s-' . $k . '">' . e($label) . '</label><input class="input" id="s-' . $k . '" name="' . $k . '" maxlength="' . $max . '" value="' . e((string) ($sec[$k] ?? '')) . '"></div>';
$cb = static fn (string $k, string $label) => '<label class="check"><input type="checkbox" name="' . $k . '" value="1"' . (!empty($sec[$k]) ? ' checked' : '') . '> ' . e($label) . '</label>';
?>
<form class="card admin-form" method="post" action="<?= e(url('/admin/content/settings/sections')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="card-head"><h2>Homepage sections</h2><span class="definition">Order: category bar → promo banner → Discover tiles → new arrivals → more tiles → best sellers → category rows → promo bands → story → assurances → newsletter → sign-up banner</span></div>
  <div class="form-row c3">
    <div><h3 class="small">Hero (used when no hero banner is active)</h3><?= $tf('hero_eyebrow', 'Eyebrow', 80) ?><?= $tf('hero_title', 'Heading') ?><?= $tf('hero_subtitle', 'Text', 320) ?></div>
    <div><h3 class="small">New arrivals</h3><?= $cb('show_new', 'Show section') ?><?= $tf('new_eyebrow', 'Eyebrow', 80) ?><?= $tf('new_title', 'Heading', 120) ?>
      <div class="field"><label for="s-new_count">Products shown</label><input class="input" id="s-new_count" name="new_count" type="number" min="1" max="24" value="<?= (int) ($sec['new_count'] ?? 8) ?>"></div></div>
    <div><h3 class="small">Best sellers</h3><?= $cb('show_bestsellers', 'Show section') ?><?= $tf('bestsellers_eyebrow', 'Eyebrow', 80) ?><?= $tf('bestsellers_title', 'Heading', 120) ?>
      <div class="field"><label for="s-bestsellers_count">Products shown</label><input class="input" id="s-bestsellers_count" name="bestsellers_count" type="number" min="1" max="24" value="<?= (int) ($sec['bestsellers_count'] ?? 8) ?>"></div></div>
    <div><h3 class="small">Featured collections</h3><?= $cb('show_collections', 'Show section') ?><?= $tf('collections_eyebrow', 'Eyebrow', 80) ?><?= $tf('collections_title', 'Heading', 120) ?></div>
    <div><h3 class="small">Layout blocks</h3><?= $cb('show_category_rail', 'Category icon bar (under the header)') ?><?= $cb('show_discover', 'Discover tiles (collections & categories)') ?><?= $cb('show_rails', 'Product rows per category') ?>
      <div class="field"><label for="s-rails_count">Number of category rows</label><input class="input" id="s-rails_count" name="rails_count" type="number" min="1" max="8" value="<?= (int) ($sec['rails_count'] ?? 3) ?>"></div>
      <?= $cb('show_promos', 'Promo bands (from Banners)') ?><?= $cb('show_story', 'Brand story') ?><?= $cb('show_assurances', 'Assurance strip') ?><?= $cb('show_newsletter', 'Newsletter block') ?></div>
    <div><h3 class="small">Sign-up banner</h3><?= $cb('show_signup', 'Show sign-up banner') ?><?= $tf('signup_eyebrow', 'Small label', 60) ?><?= $tf('signup_title', 'Heading', 120) ?>
      <div class="field"><label for="s-signup_body">Text</label><textarea class="textarea" id="s-signup_body" name="signup_body" rows="3" maxlength="400"><?= e((string) ($sec['signup_body'] ?? '')) ?></textarea></div><?= $tf('signup_cta', 'Button', 40) ?></div>
    <div><h3 class="small">Footer help box</h3><?= $tf('help_title', 'Heading', 60) ?><?= $tf('help_body', 'Text', 200) ?><p class="hint">The chat button opens WhatsApp when a WhatsApp number is set under Content → General → Store &amp; contact; otherwise it links to the contact page.</p></div>
  </div>
  <button class="btn btn-dark btn-sm" type="submit">Save sections</button>
</form>
<div class="grid-2">
  <form class="card" method="post" action="<?= e(url('/admin/content/settings/home')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?><h2>Brand story &amp; newsletter</h2>
    <div class="form-row c2 mt-4">
      <div class="field"><label for="story_eyebrow">Eyebrow</label><input class="input" id="story_eyebrow" name="story_eyebrow" value="<?= e($home['story_eyebrow'] ?? '') ?>"></div>
      <div class="field"><label for="story_title">Heading</label><input class="input" id="story_title" name="story_title" value="<?= e($home['story_title'] ?? '') ?>"></div>
    </div>
    <div class="field"><label for="story_body">Story text</label><textarea class="textarea" id="story_body" name="story_body" rows="4"><?= e($home['story_body'] ?? '') ?></textarea></div>
    <div class="form-row c2">
      <div class="field"><label for="story_cta_label">Button label</label><input class="input" id="story_cta_label" name="story_cta_label" value="<?= e($home['story_cta_label'] ?? '') ?>"></div>
      <div class="field"><label for="story_cta_href">Button link</label><input class="input" id="story_cta_href" name="story_cta_href" value="<?= e($home['story_cta_href'] ?? '') ?>"<?= error_attrs('story_cta_href') ?>><?= error_text('story_cta_href') ?></div>
    </div>
    <div class="field"><label for="story_image">Story image</label><?php if (!empty($home['story_image'])): ?><img class="thumb" src="<?= e(media($home['story_image'], 400)) ?>" alt="" width="80" height="100"><?php endif; ?><input class="input" id="story_image" name="story_image" type="file" accept="image/jpeg,image/png,image/webp"></div>
    <div class="form-row c2">
      <div class="field"><label for="newsletter_title">Newsletter heading</label><input class="input" id="newsletter_title" name="newsletter_title" value="<?= e($home['newsletter_title'] ?? '') ?>"></div>
      <div class="field"><label for="newsletter_body">Newsletter text</label><input class="input" id="newsletter_body" name="newsletter_body" value="<?= e($home['newsletter_body'] ?? '') ?>"></div>
    </div>
    <fieldset class="field"><legend class="label">Assurance strip (up to 4)</legend>
      <?php for ($i = 0; $i < 4; $i++): $a = $home['assurances'][$i] ?? []; ?>
        <div class="form-row c3">
          <select class="select" name="assurances[<?= $i ?>][icon]" aria-label="Icon <?= $i + 1 ?>"><?php foreach (['truck', 'refresh', 'shield', 'chat', 'check', 'box', 'cash', 'tag'] as $ic): ?><option<?= ($a['icon'] ?? '') === $ic ? ' selected' : '' ?>><?= $ic ?></option><?php endforeach; ?></select>
          <input class="input" name="assurances[<?= $i ?>][title]" value="<?= e($a['title'] ?? '') ?>" placeholder="Title" aria-label="Title <?= $i + 1 ?>">
          <input class="input" name="assurances[<?= $i ?>][text]" value="<?= e($a['text'] ?? '') ?>" placeholder="Text" aria-label="Text <?= $i + 1 ?>">
        </div>
      <?php endfor; ?>
    </fieldset>
    <button class="btn btn-dark btn-sm" type="submit">Save homepage text</button>
    <p class="hint mt-2">The hero and promo bands are managed under <a href="<?= e(url('/admin/content/banners')) ?>">Banners</a>.</p>
  </form>

  <form class="card" method="post" action="<?= e(url('/admin/content/home/featured')) ?>">
    <?= csrf_field() ?><h2>Featured products &amp; collection order</h2>
    <p class="hint mt-2">Featured products appear first in “Shop all”; best sellers are calculated from real orders.</p>
    <table class="admin-table mt-2"><thead><tr><th>Featured product</th><th>Order</th><th>Remove</th></tr></thead><tbody>
      <?php foreach ($featured as $p): ?><tr><td><?= e($p['title']) ?><?= $p['status'] !== 'active' ? ' <span class="xsmall muted">(' . e($p['status']) . ')</span>' : '' ?></td><td><input class="input" type="number" min="0" name="product_order[<?= (int) $p['id'] ?>]" value="<?= (int) $p['featured_order'] ?>" aria-label="Order for <?= e($p['title']) ?>"></td><td><input type="checkbox" name="unfeature[]" value="<?= (int) $p['id'] ?>" aria-label="Unfeature <?= e($p['title']) ?>"></td></tr><?php endforeach; ?>
      <?php if (!$featured): ?><tr><td colspan="3" class="muted">No featured products.</td></tr><?php endif; ?>
    </tbody></table>
    <?php if ($candidates): ?><div class="field mt-4"><label for="feature">Feature more products</label><select class="select" id="feature" name="feature[]" multiple size="5"><?php foreach ($candidates as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <table class="admin-table mt-4"><thead><tr><th>Collection</th><th>On homepage</th><th>Order</th></tr></thead><tbody>
      <?php foreach ($collections as $c): ?><tr><td><?= e($c['name']) ?><?= $c['status'] !== 'active' ? ' <span class="xsmall muted">(archived)</span>' : '' ?></td><td><input type="checkbox" name="collection_home[]" value="<?= (int) $c['id'] ?>"<?= $c['featured_on_home'] ? ' checked' : '' ?> aria-label="Show <?= e($c['name']) ?> on homepage"></td><td><input class="input" type="number" min="0" name="collection_order[<?= (int) $c['id'] ?>]" value="<?= (int) $c['home_order'] ?>" aria-label="Order for <?= e($c['name']) ?>"></td></tr><?php endforeach; ?>
    </tbody></table>
    <button class="btn btn-dark btn-sm mt-4" type="submit">Save features</button>
  </form>
</div>
