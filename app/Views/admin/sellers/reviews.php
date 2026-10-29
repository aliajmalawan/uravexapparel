<p><a href="<?= e(url('/admin/sellers')) ?>">← Sellers</a></p>
<p class="small muted">Products sellers submitted for the store. Check photos, description, category and price, then approve (goes live) or send back with a note.</p>
<?php if (!$rows): ?>
  <section class="card"><p class="muted">Nothing waiting for review.</p></section>
<?php endif; ?>
<?php foreach ($rows as $p): ?>
  <section class="card">
    <div class="flex">
      <?php if ($p['image']): ?><img src="<?= e(media($p['image'], 400)) ?>" alt="" width="96" height="128" loading="lazy"><?php endif; ?>
      <div class="grow">
        <h2 class="small"><?= e($p['title']) ?></h2>
        <p class="small">by <a href="<?= e(url('/admin/sellers/' . $p['sid'])) ?>"><?= e($p['shop_name']) ?></a> · <?= e((string) ($p['category_name'] ?? 'No category')) ?> · <?= e(money((int) ($p['sale_price_minor'] ?? $p['price_minor']), $p['currency'])) ?><?= $p['sale_price_minor'] !== null ? ' (was ' . e(money((int) $p['price_minor'], $p['currency'])) . ')' : '' ?> · <?= (int) $p['image_count'] ?> photo(s) · stock <?= (int) $p['stock'] ?></p>
        <p class="flex"><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/products/' . $p['id'] . '/preview')) ?>" target="_blank" rel="noopener">Preview ↗</a><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/products/' . $p['id'] . '/edit')) ?>">Open in editor</a></p>
      </div>
    </div>
    <form method="post" action="<?= e(url('/admin/sellers/products/' . $p['id'] . '/review')) ?>" class="mt-2">
      <?= csrf_field() ?>
      <div class="field"><label for="note-<?= (int) $p['id'] ?>">Note to the seller <span class="muted">(required to send back)</span></label><input class="input" id="note-<?= (int) $p['id'] ?>" name="note" maxlength="500"></div>
      <div class="flex">
        <button class="btn btn-primary btn-sm" type="submit" name="decision" value="approve">Approve &amp; publish</button>
        <button class="btn btn-danger btn-sm" type="submit" name="decision" value="reject">Send back</button>
      </div>
    </form>
  </section>
<?php endforeach; ?>
<?= error_text('decision') ?><?= error_text('note') ?>
