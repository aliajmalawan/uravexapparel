<div class="page-actions">
  <form class="toolbar" method="get" action="<?= e(url('/admin/products')) ?>" role="search">
    <div class="field grow"><label class="sr-only" for="q">Search products</label><input class="input" id="q" name="q" type="search" placeholder="Search title or SKU" value="<?= e($q) ?>"></div>
    <div class="field"><label class="sr-only" for="status">Status</label>
      <select class="select" id="status" name="status">
        <option value="">Draft &amp; active</option>
        <?php foreach (['active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label class="sr-only" for="category">Category</label>
      <select class="select" id="category" name="category"><option value="">All categories</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $category === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <button class="btn btn-dark btn-sm" type="submit">Filter</button>
  </form>
  <div class="flex">
    <?php if (App\Auth\Auth::can('products.import')): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/products/import')) ?>"><?= icon('upload', 'icon icon-sm') ?> Import CSV</a><?php endif; ?>
    <?php if (App\Auth\Auth::can('products.manage')): ?>
      <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/products/export')) ?>"><?= icon('download', 'icon icon-sm') ?> Export</a>
      <a class="btn btn-primary btn-sm" href="<?= e(url('/admin/products/new')) ?>"><?= icon('plus', 'icon icon-sm') ?> New product</a>
    <?php endif; ?>
  </div>
</div>
<p class="small muted"><?= pluralize($total, 'product') ?></p>
<?php if ($rows): ?>
<table class="admin-table stack-sm">
  <thead><tr><th scope="col"><span class="sr-only">Image</span></th><th scope="col">Product</th><th scope="col">Status</th><th scope="col">Category</th><th scope="col" class="num">Price</th><th scope="col" class="num">Stock</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): ?>
    <tr>
      <td data-label="Image"><img class="thumb" src="<?= e(media($p['image'], 400)) ?>" alt="" width="44" height="58" loading="lazy"></td>
      <td data-label="Product"><a href="<?= e(url('/admin/products/' . $p['id'] . '/edit')) ?>"><strong><?= e($p['title']) ?></strong></a><br><span class="xsmall muted"><?= $p['seller_name'] ? 'by ' . e($p['seller_name']) . ' · ' : '' ?><?= e($p['sku']) ?> · <?= pluralize((int) $p['variant_count'], 'variant') ?><?= $p['featured'] ? ' · ★ featured' : '' ?><?= $p['is_demo'] ? ' · demo' : '' ?></span></td>
      <td data-label="Status"><span class="status-pill s-<?= $p['status'] === 'active' ? 'delivered' : ($p['status'] === 'draft' ? 'pending' : 'cancelled') ?>"><?= e(ucfirst($p['status'])) ?></span></td>
      <td data-label="Category"><?= e($p['category_name'] ?? '—') ?></td>
      <td data-label="Price" class="num"><?= e(money((int) ($p['sale_price_minor'] ?? $p['price_minor']))) ?><?php if ($p['sale_price_minor']): ?><br><s class="xsmall muted"><?= e(money((int) $p['price_minor'])) ?></s><?php endif; ?></td>
      <td data-label="Stock" class="num<?= (int) $p['stock'] === 0 ? ' text-error' : '' ?>"><?= (int) $p['stock'] ?></td>
      <td data-label="Actions"><a class="btn btn-sm btn-outline" href="<?= e(url('/admin/products/' . $p['id'] . '/edit')) ?>">Edit<span class="sr-only"> <?= e($p['title']) ?></span></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?= App\Core\View::partial('admin/_pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/products', 'query' => array_filter(['q' => $q, 'status' => $status, 'category' => $category ?: null])]) ?>
<?php else: ?>
  <div class="card empty-state"><?= icon('tag') ?><h2>No products found</h2><p class="muted">Adjust the filters or create your first product.</p></div>
<?php endif; ?>
