<?php
use App\Core\Money;

$p = $p ?? null;
$v = static fn (string $k, $default = '') => old($k, $p[$k] ?? $default);
$m = static fn (?int $minor) => $minor === null ? '' : rtrim(rtrim(Money::decimal($minor), '0'), '.');
$dis = $canManage ? '' : ' disabled';
$colors = array_values(array_unique(array_filter(array_column($variants, 'color'))));
?>
<div class="page-actions">
  <a class="link-arrow" href="<?= e(url('/admin/products')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?> All products</a>
  <?php if ($p): ?>
    <div class="flex">
      <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/products/' . $p['id'] . '/preview')) ?>" target="_blank" rel="noopener"><?= icon('eye', 'icon icon-sm') ?> Preview</a>
      <?php if ($p['status'] === 'active'): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/product/' . $p['slug'])) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> View live</a><?php endif; ?>
      <?php if (App\Auth\Auth::can('products.feature')): ?>
        <form method="post" action="<?= e(url('/admin/products/' . $p['id'] . '/feature')) ?>"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit"><?= $p['featured'] ? '★ Unfeature' : '☆ Feature on home' ?></button></form>
      <?php endif; ?>
      <?php if ($canManage): ?>
        <?php foreach (['active' => 'Publish', 'draft' => 'Move to draft', 'archived' => 'Archive'] as $st => $label): if ($p['status'] === $st) continue; ?>
          <form method="post" action="<?= e(url('/admin/products/' . $p['id'] . '/status')) ?>"<?= $st === 'archived' ? ' data-confirm="Archive this product? It will be hidden from the store."' : '' ?>><?= csrf_field() ?><input type="hidden" name="status" value="<?= $st ?>"><button class="btn <?= $st === 'active' ? 'btn-primary' : 'btn-outline' ?> btn-sm" type="submit"><?= $label ?></button></form>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<form class="admin-form" method="post" action="<?= e(url($p ? '/admin/products/' . $p['id'] : '/admin/products')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="grid-2 grid-2-1">
    <div>
      <section class="card">
        <h2>Details</h2>
        <div class="field"><label for="title">Title</label><input class="input" id="title" name="title" required maxlength="191" value="<?= e($v('title')) ?>" data-slug-source<?= error_attrs('title') . $dis ?>><?= error_text('title') ?></div>
        <div class="form-row c2">
          <div class="field"><label for="slug">URL slug</label><input class="input" id="slug" name="slug" maxlength="191" value="<?= e($v('slug')) ?>" data-slug-target placeholder="auto from title"<?= error_attrs('slug') . $dis ?>><p class="hint">/product/<?= e($p['slug'] ?? 'your-slug') ?></p><?= error_text('slug') ?></div>
          <div class="field"><label for="sku">Base SKU</label><input class="input" id="sku" name="sku" required maxlength="64" value="<?= e($v('sku')) ?>"<?= error_attrs('sku') . $dis ?>><?= error_text('sku') ?></div>
        </div>
        <div class="field"><label for="description">Description</label><textarea class="textarea" id="description" name="description" rows="8"<?= error_attrs('description') . $dis ?>><?= e($v('description')) ?></textarea><p class="hint">Basic HTML allowed: &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;ul&gt;/&lt;li&gt;, &lt;h3&gt;, links. Scripts and styles are removed.</p><?= error_text('description') ?></div>
        <div class="form-row c2">
          <div class="field"><label for="care_details">Fabric &amp; care</label><textarea class="textarea" id="care_details" name="care_details" rows="4"<?= $dis ?>><?= e($v('care_details')) ?></textarea></div>
          <div class="field"><label for="shipping_returns">Shipping &amp; returns (optional override)</label><textarea class="textarea" id="shipping_returns" name="shipping_returns" rows="4"<?= $dis ?>><?= e($v('shipping_returns')) ?></textarea></div>
        </div>
      </section>
      <section class="card">
        <h2>Pricing (<?= e(App\Core\Money::defaultCurrency()) ?>)</h2>
        <div class="form-row c3">
          <div class="field"><label for="price">Regular price</label><input class="input" id="price" name="price" inputmode="decimal" required value="<?= e(old('price', $m(isset($p['price_minor']) ? (int) $p['price_minor'] : null))) ?>"<?= error_attrs('price') . $dis ?>><?= error_text('price') ?></div>
          <div class="field"><label for="sale_price">Sale price</label><input class="input" id="sale_price" name="sale_price" inputmode="decimal" value="<?= e(old('sale_price', $m(isset($p['sale_price_minor']) ? (int) $p['sale_price_minor'] : null))) ?>"<?= error_attrs('sale_price') . $dis ?>><?= error_text('sale_price') ?></div>
          <div class="field"><label for="cost">Cost (internal)</label><input class="input" id="cost" name="cost" inputmode="decimal" value="<?= e(old('cost', $m(isset($p['cost_minor']) ? (int) $p['cost_minor'] : null))) ?>"<?= $dis ?>><p class="hint">Never shown to customers.</p></div>
        </div>
        <p class="hint">Variants can override price below. All totals are recalculated on the server at checkout.</p>
      </section>
      <section class="card">
        <h2>Search engine listing</h2>
        <div class="field"><label for="seo_title">SEO title</label><input class="input" id="seo_title" name="seo_title" maxlength="191" value="<?= e($v('seo_title')) ?>"<?= $dis ?>></div>
        <div class="field"><label for="seo_description">Meta description</label><textarea class="textarea" id="seo_description" name="seo_description" rows="2" maxlength="320"<?= $dis ?>><?= e($v('seo_description')) ?></textarea></div>
      </section>
    </div>
    <div>
      <section class="card">
        <h2>Organisation</h2>
        <div class="field"><label for="status">Status</label>
          <select class="select" id="status" name="status"<?= $dis ?>>
            <?php foreach (['draft' => 'Draft (hidden)', 'active' => 'Active (visible)', 'archived' => 'Archived'] as $k => $l): ?><option value="<?= $k ?>"<?= $v('status', 'draft') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label for="category_id">Category</label>
          <select class="select" id="category_id" name="category_id"<?= $dis ?>><option value="">— None —</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (string) $v('category_id') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select></div>
        <fieldset class="field"><legend class="label">Collections</legend>
          <?php foreach ($collections as $c): ?>
            <label class="check"><input type="checkbox" name="collections[]" value="<?= (int) $c['id'] ?>"<?= in_array((int) $c['id'], $selectedCollections, true) ? ' checked' : '' ?><?= $dis ?>> <?= e($c['name']) ?></label>
          <?php endforeach; ?>
          <?php if (!$collections): ?><p class="hint">No collections yet.</p><?php endif; ?>
        </fieldset>
        <div class="field"><label for="tags">Tags</label><input class="input" id="tags" name="tags" maxlength="500" value="<?= e($v('tags')) ?>" placeholder="linen, summer"<?= $dis ?>><p class="hint">Comma separated; used in search.</p></div>
        <label class="check"><input type="checkbox" name="featured" value="1"<?= $v('featured') ? ' checked' : '' ?><?= $dis ?>> Featured on homepage</label>
      </section>
      <?php if ($p): ?>
        <section class="card"><h2>Record</h2><dl class="kv mt-2"><dt>Created</dt><dd><?= e(dt($p['created_at'])) ?></dd><dt>Updated</dt><dd><?= e(dt($p['updated_at'])) ?></dd><dt>Published</dt><dd><?= e(dt($p['published_at'])) ?></dd></dl></section>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($canManage): ?><div class="sticky-actions"><button class="btn btn-primary" type="submit"><?= $p ? 'Save product' : 'Create product' ?></button></div><?php endif; ?>
</form>

<?php if ($p): ?>
<section class="card mt-4" id="variants">
  <div class="card-head"><h2>Variants &amp; stock</h2><span class="definition">Stock edits are logged as inventory adjustments.</span></div>
  <?= error_text('variants') ?>
  <form method="post" action="<?= e(url('/admin/products/' . $p['id'] . '/variants')) ?>">
    <?= csrf_field() ?>
    <div class="table-scroll">
      <table class="admin-table variant-table">
        <thead><tr><th scope="col">SKU</th><th scope="col">Size</th><th scope="col">Colour</th><th scope="col">Hex</th><th scope="col">Price</th><th scope="col">Sale</th><th scope="col">Stock</th><th scope="col">Low at</th><th scope="col">Active</th><th scope="col">Remove</th></tr></thead>
        <tbody data-variant-rows>
          <?php foreach ($variants as $var): $i = (int) $var['id']; ?>
            <tr>
              <td><input type="hidden" name="variants[<?= $i ?>][id]" value="<?= $i ?>"><label class="sr-only" for="vs<?= $i ?>">SKU</label><input class="input" id="vs<?= $i ?>" name="variants[<?= $i ?>][sku]" value="<?= e($var['sku']) ?>" required<?= $dis ?>></td>
              <td><input class="input" aria-label="Size" name="variants[<?= $i ?>][size]" value="<?= e($var['size']) ?>" size="5"<?= $dis ?>></td>
              <td><input class="input" aria-label="Colour" name="variants[<?= $i ?>][color]" value="<?= e($var['color']) ?>" size="8"<?= $dis ?>></td>
              <td><input class="input" aria-label="Colour hex" name="variants[<?= $i ?>][color_hex]" value="<?= e((string) $var['color_hex']) ?>" size="7" placeholder="#000000"<?= $dis ?>></td>
              <td><input class="input" aria-label="Price override" name="variants[<?= $i ?>][price]" value="<?= e($m($var['price_minor'] !== null ? (int) $var['price_minor'] : null)) ?>" size="7" inputmode="decimal"<?= $dis ?>></td>
              <td><input class="input" aria-label="Sale price override" name="variants[<?= $i ?>][sale_price]" value="<?= e($m($var['sale_price_minor'] !== null ? (int) $var['sale_price_minor'] : null)) ?>" size="7" inputmode="decimal"<?= $dis ?>></td>
              <td><input class="input" aria-label="Stock" name="variants[<?= $i ?>][stock]" value="<?= (int) $var['stock'] ?>" size="4" inputmode="numeric"<?= App\Auth\Auth::can('inventory.adjust') ? '' : ' readonly' ?><?= $dis ?>></td>
              <td><input class="input" aria-label="Low stock threshold" name="variants[<?= $i ?>][low]" value="<?= (int) $var['low_stock_threshold'] ?>" size="3" inputmode="numeric"<?= $dis ?>></td>
              <td><input type="checkbox" aria-label="Active" name="variants[<?= $i ?>][active]" value="1"<?= $var['is_active'] ? ' checked' : '' ?><?= $dis ?>></td>
              <td><input type="checkbox" aria-label="Remove variant" name="variants[<?= $i ?>][remove]" value="1"<?= $dis ?>></td>
            </tr>
          <?php endforeach; ?>
          <?php for ($n = 0; $n < ($variants ? 1 : 4); $n++): ?>
            <tr>
              <td><input class="input" aria-label="New SKU" name="variants[new<?= $n ?>][sku]" placeholder="<?= e($p['sku']) ?>-BLK-M"<?= $dis ?>></td>
              <td><input class="input" aria-label="Size" name="variants[new<?= $n ?>][size]" size="5"<?= $dis ?>></td>
              <td><input class="input" aria-label="Colour" name="variants[new<?= $n ?>][color]" size="8"<?= $dis ?>></td>
              <td><input class="input" aria-label="Colour hex" name="variants[new<?= $n ?>][color_hex]" size="7" placeholder="#000000"<?= $dis ?>></td>
              <td><input class="input" aria-label="Price override" name="variants[new<?= $n ?>][price]" size="7"<?= $dis ?>></td>
              <td><input class="input" aria-label="Sale price override" name="variants[new<?= $n ?>][sale_price]" size="7"<?= $dis ?>></td>
              <td><input class="input" aria-label="Opening stock" name="variants[new<?= $n ?>][stock]" value="0" size="4"<?= $dis ?>></td>
              <td><input class="input" aria-label="Low stock threshold" name="variants[new<?= $n ?>][low]" value="5" size="3"<?= $dis ?>></td>
              <td colspan="2" class="xsmall muted">new</td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
    <template id="variant-row-template">
      <tr>
        <td><input class="input" aria-label="New SKU" name="variants[__i__][sku]"></td>
        <td><input class="input" aria-label="Size" name="variants[__i__][size]" size="5"></td>
        <td><input class="input" aria-label="Colour" name="variants[__i__][color]" size="8"></td>
        <td><input class="input" aria-label="Colour hex" name="variants[__i__][color_hex]" size="7" placeholder="#000000"></td>
        <td><input class="input" aria-label="Price override" name="variants[__i__][price]" size="7"></td>
        <td><input class="input" aria-label="Sale price override" name="variants[__i__][sale_price]" size="7"></td>
        <td><input class="input" aria-label="Opening stock" name="variants[__i__][stock]" value="0" size="4"></td>
        <td><input class="input" aria-label="Low stock threshold" name="variants[__i__][low]" value="5" size="3"></td>
        <td colspan="2" class="xsmall muted">new</td>
      </tr>
    </template>
    <?php if ($canManage): ?>
      <div class="flex mt-4"><button class="btn btn-outline btn-sm" type="button" data-add-variant><?= icon('plus', 'icon icon-sm') ?> Add row</button><button class="btn btn-dark" type="submit">Save variants</button></div>
      <p class="hint">Leave price blank to use the product price. Variants with order history are deactivated instead of deleted.</p>
    <?php endif; ?>
  </form>
</section>

<section class="card" id="images">
  <div class="card-head"><h2>Images</h2><span class="definition">First image is the featured image. JPG/PNG/WebP up to 8 MB; resized to 400/800/1600 px WebP.</span></div>
  <?php if ($images): ?>
    <form method="post" action="<?= e(url('/admin/products/' . $p['id'] . '/images/order')) ?>">
      <?= csrf_field() ?>
      <ul class="image-grid">
        <?php foreach ($images as $i => $img): ?>
          <li class="<?= $i === 0 ? 'is-featured' : '' ?>">
            <img src="<?= e(media($img['path'], 400)) ?>" alt="<?= e($img['alt']) ?>" width="120" height="160" loading="lazy">
            <label class="sr-only" for="alt<?= (int) $img['id'] ?>">Alt text</label>
            <input class="input mt-2" id="alt<?= (int) $img['id'] ?>" name="alt[<?= (int) $img['id'] ?>]" value="<?= e($img['alt']) ?>" placeholder="Alt text"<?= $dis ?>>
            <label class="sr-only" for="ic<?= (int) $img['id'] ?>">Colour</label>
            <input class="input mt-2" id="ic<?= (int) $img['id'] ?>" name="img_color[<?= (int) $img['id'] ?>]" value="<?= e((string) $img['color']) ?>" placeholder="Colour" list="color-list"<?= $dis ?>>
            <div class="img-actions">
              <label class="xsmall">Pos <input class="input" type="number" name="position[<?= (int) $img['id'] ?>]" value="<?= (int) $img['position'] ?>" min="0"<?= $dis ?>></label>
              <label class="xsmall"><input type="radio" name="featured" value="<?= (int) $img['id'] ?>"<?= $i === 0 ? ' checked' : '' ?><?= $dis ?>> Main</label>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <datalist id="color-list"><?php foreach ($colors as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
      <?php if ($canManage): ?><button class="btn btn-dark btn-sm" type="submit">Save order &amp; details</button><?php endif; ?>
    </form>
    <?php if ($canManage): ?>
      <details class="inline-form mt-4"><summary class="btn btn-link small">Delete an image…</summary>
        <div class="flex">
          <?php foreach ($images as $i => $img): ?>
            <form method="post" action="<?= e(url('/admin/products/' . $p['id'] . '/images/' . $img['id'] . '/delete')) ?>" data-confirm="Delete this image permanently?"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete #<?= $i + 1 ?></button></form>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted small">No images yet.</p>
  <?php endif; ?>
  <?php if ($canManage): ?>
    <form class="dropzone mt-4" method="post" action="<?= e(url('/admin/products/' . $p['id'] . '/images')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="form-row c3">
        <div class="field"><label for="images-input">Upload images</label><input class="input" id="images-input" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></div>
        <div class="field"><label for="img-color">Colour (optional)</label><input class="input" id="img-color" name="color" list="color-list"></div>
        <div class="field"><label for="img-alt">Alt text</label><input class="input" id="img-alt" name="alt" value="<?= e($p['title']) ?>"></div>
      </div>
      <?= error_text('images') ?><?= error_text('file') ?>
      <button class="btn btn-dark btn-sm" type="submit"><?= icon('upload', 'icon icon-sm') ?> Upload</button>
    </form>
  <?php endif; ?>
</section>

<?php if ($canManage): ?>
<section class="card">
  <h2>Danger zone</h2>
  <p class="small muted">Products with order history are archived instead of deleted, so past orders keep their records.</p>
  <form method="post" action="<?= e(url('/admin/products/' . $p['id'] . '/delete')) ?>" data-confirm="Delete this product? This cannot be undone."><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete product</button></form>
</section>
<?php endif; ?>
<?php endif; ?>
