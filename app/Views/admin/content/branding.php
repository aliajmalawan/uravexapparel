<?= App\Core\View::partial('admin/content/_tabs') ?>
<form class="admin-form" method="post" action="<?= e(url('/admin/content/settings/branding')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="grid-2">
    <section class="card">
      <h2>Emblem (header, footer, admin)</h2>
      <p class="hint mt-2">Used on the black header, footer and admin sidebar. Upload a square image (at least 400×400 px) designed for a dark background. PNG/WebP keep transparency.</p>
      <div class="brand-preview dark mt-4"><img src="<?= e(brand_emblem(96)) ?>" alt="Current emblem" width="72" height="72"></div>
      <div class="field mt-4"><label for="emblem">Upload new emblem</label><input class="input" id="emblem" name="emblem" type="file" accept="image/png,image/webp,image/jpeg"><?= error_text('file') ?></div>
      <?php if (!empty($b['emblem'])): ?><label class="check"><input type="checkbox" name="reset_emblem" value="1"> Restore the original emblem</label><?php endif; ?>
      <label class="check"><input type="checkbox" name="show_wordmark" value="1"<?= !empty($b['show_wordmark']) ? ' checked' : '' ?>> Show the “URAVEX APPAREL” text next to the emblem (desktop and tablet)</label>
    </section>
    <section class="card">
      <h2>Full logo (light pages)</h2>
      <p class="hint mt-2">Used on sign-in pages, the About page, invoices and packing slips. Keep the original proportions — the image is never stretched.</p>
      <div class="brand-preview mt-4"><img src="<?= e(brand_logo()) ?>" alt="Current logo" width="240" height="120"></div>
      <div class="field mt-4"><label for="logo">Upload new logo</label><input class="input" id="logo" name="logo" type="file" accept="image/png,image/webp,image/jpeg"></div>
      <?php if (!empty($b['logo'])): ?><label class="check"><input type="checkbox" name="reset_logo" value="1"> Restore the original logo</label><?php endif; ?>
      <p class="hint">The browser-tab icon (favicon) and email header use the original brand files.</p>
    </section>
  </div>
  <div class="sticky-actions"><button class="btn btn-primary" type="submit">Save branding</button></div>
</form>
