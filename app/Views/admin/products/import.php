<div class="page-actions">
  <a class="link-arrow" href="<?= e(url('/admin/products')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?> All products</a>
  <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/products/import/template')) ?>"><?= icon('download', 'icon icon-sm') ?> Download template</a>
</div>

<?php if ($plan): $s = $plan['summary']; ?>
  <section class="card">
    <div class="card-head"><h2>Preview: <?= e($filename ?? '') ?></h2></div>
    <section class="kpis">
      <div class="kpi"><p class="label">Rows</p><p class="value"><?= (int) $s['rows'] ?></p></div>
      <div class="kpi"><p class="label">Products</p><p class="value"><?= (int) $s['products'] ?></p></div>
      <div class="kpi"><p class="label">New</p><p class="value"><?= (int) $s['create'] ?></p></div>
      <div class="kpi"><p class="label">Updates</p><p class="value"><?= (int) $s['update'] ?></p></div>
      <div class="kpi"><p class="label">Variants</p><p class="value"><?= (int) $s['variants'] ?></p></div>
      <div class="kpi<?= $s['errors'] ? ' alert-kpi' : '' ?>"><p class="label">Errors</p><p class="value"><?= (int) $s['errors'] ?></p></div>
    </section>
    <?php if ($plan['errors']): ?>
      <div class="alert alert-error" role="alert"><p>Nothing was imported. Fix these rows and upload again — imports are all-or-nothing.</p></div>
      <div class="table-scroll"><table class="admin-table"><thead><tr><th scope="col">Line</th><th scope="col">Problem</th></tr></thead><tbody>
        <?php foreach (array_slice($plan['errors'], 0, 200) as $er): ?><tr><td><?= (int) $er['line'] ?></td><td><?= e($er['message']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php else: ?>
      <div class="table-scroll"><table class="admin-table"><thead><tr><th scope="col">Product SKU</th><th scope="col">Title</th><th scope="col">Action</th><th scope="col" class="num">Variants</th></tr></thead><tbody>
        <?php foreach (array_slice($plan['products'], 0, 100, true) as $sku => $p): ?><tr><td><?= e($sku) ?></td><td><?= e($p['data']['title']) ?></td><td><?= $p['id'] ? 'Update' : 'Create' ?></td><td class="num"><?= count($p['variants']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <form method="post" action="<?= e(url('/admin/products/import/commit')) ?>" class="mt-4">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn btn-primary" type="submit">Confirm import</button>
        <a class="btn btn-link" href="<?= e(url('/admin/products/import')) ?>">Cancel</a>
      </form>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="card">
  <h2>Upload CSV</h2>
  <p class="small muted mt-2">One row per variant. Rows sharing a <code>product_sku</code> belong to the same product. Existing product SKUs are updated; new SKUs are created (as drafts unless <code>status</code> says otherwise). Stock values set the on-hand quantity and are logged as “import” movements. Images are added afterwards on each product page.</p>
  <form method="post" action="<?= e(url('/admin/products/import')) ?>" enctype="multipart/form-data" class="flex mt-4">
    <?= csrf_field() ?>
    <label class="sr-only" for="csv">CSV file</label>
    <input class="input grow" id="csv" type="file" name="csv" accept=".csv,text/csv" required<?= error_attrs('file') ?>>
    <button class="btn btn-dark" type="submit">Validate file</button>
  </form>
  <?= error_text('file') ?>
  <details class="mt-4"><summary class="small">Column reference</summary>
    <table class="admin-table mt-2"><tbody>
      <tr><td><code>product_sku</code>*</td><td>Groups rows into one product.</td></tr>
      <tr><td><code>title</code>*, <code>price</code>*</td><td>Price in PKR, e.g. 2499 or 2499.50.</td></tr>
      <tr><td><code>variant_sku</code>*</td><td>Unique per variant.</td></tr>
      <tr><td><code>category</code></td><td>Existing category slug.</td></tr>
      <tr><td><code>status</code></td><td>draft (default), active or archived.</td></tr>
      <tr><td><code>sale_price</code>, <code>variant_price</code></td><td>Optional; sale must be below price.</td></tr>
      <tr><td><code>size</code>, <code>color</code>, <code>color_hex</code></td><td>Variant options; hex like #0B0B0B.</td></tr>
      <tr><td><code>stock</code>, <code>low_stock_threshold</code></td><td>Whole numbers.</td></tr>
      <tr><td><code>description</code></td><td>Basic HTML; unsafe markup is removed.</td></tr>
    </tbody></table>
  </details>
</section>

<?php if (!empty($jobs)): ?>
<section class="card">
  <h2>Recent imports</h2>
  <table class="admin-table stack-sm mt-2"><thead><tr><th scope="col">Date</th><th scope="col">File</th><th scope="col">By</th><th scope="col">Result</th></tr></thead><tbody>
    <?php foreach ($jobs as $j): ?><tr><td data-label="Date"><?= e(dt($j['created_at'])) ?></td><td data-label="File"><?= e($j['filename']) ?></td><td data-label="By"><?= e($j['actor'] ?? '—') ?></td><td data-label="Result"><?= e(ucfirst($j['status'])) ?>: <?= (int) $j['created'] ?> created, <?= (int) $j['updated'] ?> updated<?= $j['failed'] ? ', ' . (int) $j['failed'] . ' errors' : '' ?></td></tr><?php endforeach; ?>
  </tbody></table>
</section>
<?php endif; ?>
