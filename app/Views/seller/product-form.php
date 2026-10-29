<?php
/** @var ?array $p @var array $variants @var array $images @var array $categories */
$isNew = $p === null;
$action = $isNew ? url('/seller/products') : url('/seller/products/' . $p['id']);
// Seller descriptions are stored as safe <p> HTML; show them back as plain text.
$descText = $p ? trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], ["\n\n", "\n"], (string) $p['description'])), ENT_QUOTES)) : '';
$review = $p['review_status'] ?? 'none';
?>
<header class="container page-head"><nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="<?= e(url('/seller/products')) ?>">My products</a></li><li aria-current="page"><?= $isNew ? 'New' : e($p['title']) ?></li></ol></nav><h1><?= $isNew ? 'Add a product' : e($p['title']) ?></h1></header>
<div class="container account-layout seller-panel">
  <?= App\Core\View::partial('seller/_nav', ['seller' => $seller]) ?>
  <div>
    <?php if (!$isNew): ?>
      <section class="panel">
        <?php if ($review === 'rejected'): ?><div class="alert alert-error"><p><strong>Changes needed:</strong> <?= e((string) $p['review_note']) ?></p></div><?php endif; ?>
        <?php if ($review === 'pending'): ?><div class="alert alert-info"><p>In review — we will email you when it is live.</p></div><?php endif; ?>
        <?php if ($p['status'] === 'active'): ?><p class="small">Live in the store: <a href="<?= e(url('/product/' . $p['slug'])) ?>" target="_blank" rel="noopener">view product ↗</a>. Editing the title, description, category or price sends it back to review.</p><?php endif; ?>
        <div class="flex">
          <?php if (in_array($review, ['none', 'rejected'], true)): ?>
            <form method="post" action="<?= e(url('/seller/products/' . $p['id'] . '/submit')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit">Submit for review</button></form>
          <?php endif; ?>
          <form method="post" action="<?= e(url('/seller/products/' . $p['id'] . '/unpublish')) ?>" data-confirm="Remove this product from your shop?"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">Remove product</button></form>
        </div>
        <?= error_text('submit') ?>
      </section>
    <?php endif; ?>

    <form class="panel" method="post" action="<?= e($action) ?>" novalidate>
      <?= csrf_field() ?>
      <h2>Details</h2>
      <div class="field"><label for="title">Product name</label><input class="input" id="title" name="title" required maxlength="191" value="<?= e(old('title', $p['title'] ?? '')) ?>"<?= error_attrs('title') ?>><?= error_text('title') ?></div>
      <div class="field"><label for="description">Description</label><textarea class="textarea" id="description" name="description" rows="6" required maxlength="5000" placeholder="Fabric, fit, care and anything a shopper should know. Leave a blank line between paragraphs."<?= error_attrs('description') ?>><?= e(old('description', $descText)) ?></textarea><?= error_text('description') ?></div>
      <div class="form-grid cols-2">
        <div class="field"><label for="category_id">Category</label><select class="select" id="category_id" name="category_id" required<?= error_attrs('category_id') ?>><option value="">Choose…</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) old('category_id', $p['category_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><?= error_text('category_id') ?></div>
        <div class="field"><label for="tags">Tags <span class="muted">(comma separated)</span></label><input class="input" id="tags" name="tags" maxlength="300" value="<?= e(old('tags', $p['tags'] ?? '')) ?>"></div>
        <div class="field"><label for="price">Price (<?= e(App\Core\Money::defaultCurrency()) ?>)</label><input class="input" id="price" name="price" inputmode="decimal" required value="<?= e(old('price', $p ? App\Core\Money::decimal((int) $p['price_minor']) : '')) ?>"<?= error_attrs('price') ?>><?= error_text('price') ?></div>
        <div class="field"><label for="sale_price">Sale price <span class="muted">(optional)</span></label><input class="input" id="sale_price" name="sale_price" inputmode="decimal" value="<?= e(old('sale_price', $p && $p['sale_price_minor'] !== null ? App\Core\Money::decimal((int) $p['sale_price_minor']) : '')) ?>"<?= error_attrs('sale_price') ?>><?= error_text('sale_price') ?></div>
      </div>
      <p class="hint">You receive the sale price minus <?= e(rtrim(rtrim(App\Services\Marketplace::commissionPercent($seller), '0'), '.')) ?>% commission. Customer shipping fees stay with URAVEX, so price your item to cover your shipping.</p>
      <button class="btn btn-dark" type="submit"><?= $isNew ? 'Save draft' : 'Save details' ?></button>
    </form>

    <?php if (!$isNew): ?>
      <form class="panel" id="variants" method="post" action="<?= e(url('/seller/products/' . $p['id'] . '/variants')) ?>" novalidate>
        <?= csrf_field() ?>
        <h2>Sizes, colours &amp; stock</h2>
        <?= error_text('variants') ?>
        <div class="table-scroll">
          <table class="admin-table">
            <thead><tr><th scope="col">Size</th><th scope="col">Colour</th><th scope="col">Stock</th><th scope="col">Remove</th></tr></thead>
            <tbody>
            <?php foreach ($variants as $v): if (!$v['is_active']) continue; ?>
              <tr>
                <td><input type="hidden" name="variants[<?= (int) $v['id'] ?>][id]" value="<?= (int) $v['id'] ?>"><input class="input" name="variants[<?= (int) $v['id'] ?>][size]" maxlength="40" value="<?= e($v['size']) ?>" aria-label="Size"></td>
                <td><input class="input" name="variants[<?= (int) $v['id'] ?>][color]" maxlength="60" value="<?= e($v['color']) ?>" aria-label="Colour"></td>
                <td><input class="input order-input" type="number" min="0" max="999999" name="variants[<?= (int) $v['id'] ?>][stock]" value="<?= (int) $v['stock'] ?>" aria-label="Stock"></td>
                <td><input type="checkbox" name="variants[<?= (int) $v['id'] ?>][remove]" value="1" aria-label="Remove <?= e($v['size'] . ' ' . $v['color']) ?>"></td>
              </tr>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < 3; $i++): ?>
              <tr>
                <td><input class="input" name="variants[new<?= $i ?>][size]" maxlength="40" placeholder="e.g. M" aria-label="New size"></td>
                <td><input class="input" name="variants[new<?= $i ?>][color]" maxlength="60" placeholder="e.g. Black" aria-label="New colour"></td>
                <td><input class="input order-input" type="number" min="0" max="999999" name="variants[new<?= $i ?>][stock]" value="0" aria-label="New stock"></td>
                <td></td>
              </tr>
            <?php endfor; ?>
            </tbody>
          </table>
        </div>
        <button class="btn btn-dark btn-sm" type="submit">Save sizes &amp; stock</button>
      </form>

      <section class="panel" id="images">
        <h2>Photos</h2>
        <?php if ($images): ?>
          <ul class="thumb-grid">
            <?php foreach ($images as $img): ?>
              <li><img src="<?= e(media($img['path'], 400)) ?>" alt="" width="120" height="160" loading="lazy">
                <form method="post" action="<?= e(url('/seller/products/' . $p['id'] . '/images/' . $img['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn btn-link xsmall" type="submit">Remove</button></form></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/seller/products/' . $p['id'] . '/images')) ?>" enctype="multipart/form-data" class="mt-2">
          <?= csrf_field() ?>
          <div class="field"><label for="images-input">Add photos <span class="muted">(JPG, PNG or WebP; up to 10 per product; the first is the main photo)</span></label><input class="input" id="images-input" name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp"<?= error_attrs('images') ?>><?= error_text('images') ?></div>
          <button class="btn btn-dark btn-sm" type="submit">Upload</button>
        </form>
      </section>
    <?php endif; ?>
  </div>
</div>
