<?= App\Core\View::partial('admin/settings/_tabs') ?>
<?php $m = static fn ($minor) => $minor === null ? '' : rtrim(rtrim(App\Core\Money::decimal((int) $minor), '0'), '.'); ?>
<form class="card" method="post" action="<?= e(url('/admin/settings/shipping')) ?>">
  <?= csrf_field() ?><h2>Free delivery</h2>
  <div class="flex mt-4"><label for="free_threshold">Free delivery when the order (after discounts) is at least (<?= e(App\Core\Money::defaultCurrency()) ?>)</label><input class="input" id="free_threshold" name="free_threshold" inputmode="decimal" value="<?= e($m(!empty($s['free_threshold_minor']) ? (int) $s['free_threshold_minor'] : null)) ?>" placeholder="Blank = off"><button class="btn btn-dark btn-sm" type="submit">Save</button></div>
  <p class="hint">A rate's own “free over” value overrides this.</p>
</form>

<?php foreach ($zones as $z): ?>
<section class="card">
  <form method="post" action="<?= e(url('/admin/settings/shipping/zone')) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
    <div class="card-head"><h2>Zone: <?= e($z['name']) ?></h2><?= $z['is_fallback'] ? '<span class="tag tag-gold">Fallback — rest of Pakistan</span>' : '' ?></div>
    <div class="form-row c3">
      <div class="field"><label for="zn<?= (int) $z['id'] ?>">Name</label><input class="input" id="zn<?= (int) $z['id'] ?>" name="name" value="<?= e($z['name']) ?>" required></div>
      <div class="field"><label for="zc<?= (int) $z['id'] ?>">Cities (one per line)</label><textarea class="textarea" id="zc<?= (int) $z['id'] ?>" name="cities" rows="3"><?= e($z['cities']) ?></textarea></div>
      <div class="field"><label for="zp<?= (int) $z['id'] ?>">States (one per line — code or name, e.g. CA or California)</label><textarea class="textarea" id="zp<?= (int) $z['id'] ?>" name="provinces" rows="3"><?= e($z['provinces']) ?></textarea></div>
    </div>
    <div class="flex">
      <label class="check"><input type="checkbox" name="is_fallback" value="1"<?= $z['is_fallback'] ? ' checked' : '' ?>> Fallback zone</label>
      <label class="check"><input type="checkbox" name="is_active" value="1"<?= $z['is_active'] ? ' checked' : '' ?>> Active</label>
      <label class="small">Order <input class="input" type="number" name="sort_order" min="0" value="<?= (int) $z['sort_order'] ?>"></label>
      <button class="btn btn-dark btn-sm" type="submit">Save zone</button>
      <button class="btn btn-link small" type="submit" name="delete" value="1" data-confirm="Delete this zone and its rates?">Delete zone</button>
    </div>
  </form>
  <div class="table-scroll mt-4"><table class="admin-table">
    <thead><tr><th>Rate</th><th>Price</th><th>Free over</th><th>Days</th><?php if ($codOn = isset(App\Payments\Registry::all()['cod'])): ?><th>COD</th><?php endif; ?><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach (array_merge($z['rates'], [null]) as $rt): $rid = $rt['id'] ?? 'new' . $z['id']; ?>
        <tr>
          <td><form id="rate-<?= e((string) $rid) ?>" method="post" action="<?= e(url('/admin/settings/shipping/rate')) ?>"><?= csrf_field() ?><input type="hidden" name="zone_id" value="<?= (int) $z['id'] ?>"><?php if ($rt): ?><input type="hidden" name="id" value="<?= (int) $rt['id'] ?>"><?php endif; ?></form>
            <input class="input" form="rate-<?= e((string) $rid) ?>" name="name" value="<?= e($rt['name'] ?? '') ?>" placeholder="New rate name" aria-label="Rate name"<?= $rt ? ' required' : '' ?>></td>
          <td><input class="input" form="rate-<?= e((string) $rid) ?>" name="price" size="6" value="<?= e($m(isset($rt['price_minor']) ? (int) $rt['price_minor'] : null)) ?>" aria-label="Price"></td>
          <td><input class="input" form="rate-<?= e((string) $rid) ?>" name="free_over" size="6" value="<?= e($m(isset($rt['free_over_minor']) ? (int) $rt['free_over_minor'] : null)) ?>" aria-label="Free over"></td>
          <td class="flex"><input class="input" form="rate-<?= e((string) $rid) ?>" name="min_days" size="2" value="<?= (int) ($rt['min_days'] ?? 2) ?>" aria-label="Min days">–<input class="input" form="rate-<?= e((string) $rid) ?>" name="max_days" size="2" value="<?= (int) ($rt['max_days'] ?? 5) ?>" aria-label="Max days"></td>
          <?php if ($codOn): ?><td><input type="checkbox" form="rate-<?= e((string) $rid) ?>" name="cod_available" value="1"<?= ($rt['cod_available'] ?? 1) ? ' checked' : '' ?> aria-label="COD available"></td><?php endif; ?>
          <td><input type="checkbox" form="rate-<?= e((string) $rid) ?>" name="is_active" value="1"<?= ($rt['is_active'] ?? 1) ? ' checked' : '' ?> aria-label="Active"></td>
          <td class="flex"><button class="btn btn-sm btn-dark" form="rate-<?= e((string) $rid) ?>" type="submit"><?= $rt ? 'Save' : 'Add' ?></button><?php if ($rt): ?><button class="btn btn-sm btn-link" form="rate-<?= e((string) $rid) ?>" type="submit" name="delete" value="1" data-confirm="Delete this rate?">Delete</button><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endforeach; ?>

<div class="grid-2">
  <form class="card" method="post" action="<?= e(url('/admin/settings/shipping/zone')) ?>">
    <?= csrf_field() ?><h2>Add zone</h2>
    <div class="field mt-4"><label for="nzn">Name</label><input class="input" id="nzn" name="name" required></div>
    <div class="field"><label for="nzc">Cities (one per line)</label><textarea class="textarea" id="nzc" name="cities" rows="3"></textarea></div>
    <div class="field"><label for="nzp">States (one per line, e.g. AK)</label><textarea class="textarea" id="nzp" name="provinces" rows="2"></textarea></div>
    <label class="check"><input type="checkbox" name="is_active" value="1" checked> Active</label>
    <button class="btn btn-primary btn-sm mt-4" type="submit">Add zone</button>
  </form>
  <section class="card">
    <h2>Couriers</h2>
    <p class="hint mt-2">Tracking links use <code>{tracking}</code> as a placeholder, e.g. <code>https://courier.example/track?cn={tracking}</code>. Leave blank to enter links manually per shipment.</p>
    <?php foreach (array_merge($couriers, [null]) as $c): ?>
      <form class="flex mt-2" method="post" action="<?= e(url('/admin/settings/shipping/courier')) ?>">
        <?= csrf_field() ?><?php if ($c): ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><?php endif; ?>
        <input class="input" name="name" value="<?= e($c['name'] ?? '') ?>" placeholder="Courier name" aria-label="Courier name" size="12" required>
        <input class="input grow" name="tracking_url_template" value="<?= e((string) ($c['tracking_url_template'] ?? '')) ?>" placeholder="https://…{tracking}" aria-label="Tracking URL template">
        <label class="check"><input type="checkbox" name="is_active" value="1"<?= ($c['is_active'] ?? 1) ? ' checked' : '' ?>> Active</label>
        <button class="btn btn-sm btn-dark" type="submit"><?= $c ? 'Save' : 'Add' ?></button>
      </form>
    <?php endforeach; ?>
    <?= error_text('tracking_url_template') ?>
  </section>
</div>
