<?= App\Core\View::partial('admin/content/_tabs') ?>
<?php
$header = $nav['header'] ?? [];
$footer = $nav['footer'] ?? [];
$row = static function (string $name, int $i, array $l): string {
    return '<div class="form-row c2 nav-row">'
        . '<div class="field"><label class="sr-only" for="' . $name . $i . 'l">Label ' . ($i + 1) . '</label><input class="input" id="' . $name . $i . 'l" name="' . $name . '[' . $i . '][label]" value="' . e($l['label'] ?? '') . '" placeholder="Label"></div>'
        . '<div class="field"><label class="sr-only" for="' . $name . $i . 'h">Link ' . ($i + 1) . '</label><input class="input" id="' . $name . $i . 'h" name="' . $name . '[' . $i . '][href]" value="' . e($l['href'] ?? '') . '" placeholder="/shop or https://…"></div>'
        . '</div>';
};
?>
<form class="admin-form" method="post" action="<?= e(url('/admin/content/settings/navigation')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= error_text('links') ?><?= error_text('header') ?>
  <div class="grid-2">
    <section class="card">
      <h2>Header menu</h2>
      <p class="hint mt-2">Shown in the top bar and the mobile menu. Leave a row blank to remove it. Order is top to bottom (max 10).</p>
      <?php for ($i = 0; $i < 10; $i++): ?><?= $row('header', $i, $header[$i] ?? []) ?><?php endfor; ?>
    </section>
    <section class="card">
      <h2>Footer link columns</h2>
      <p class="hint mt-2">Up to three columns with up to eight links each. Clear a column title and its links to remove it.</p>
      <?php for ($c = 0; $c < 3; $c++): $col = $footer[$c] ?? ['title' => '', 'links' => []]; ?>
        <fieldset class="field mt-4">
          <legend class="label">Column <?= $c + 1 ?></legend>
          <div class="field"><label class="sr-only" for="fc<?= $c ?>">Column <?= $c + 1 ?> title</label><input class="input" id="fc<?= $c ?>" name="footer[<?= $c ?>][title]" value="<?= e($col['title']) ?>" placeholder="Column title"></div>
          <?php for ($i = 0; $i < 8; $i++): ?><?= $row("footer[{$c}][links]", $i, $col['links'][$i] ?? []) ?><?php endfor; ?>
        </fieldset>
      <?php endfor; ?>
      <div class="field mt-4"><label for="footer_pages_title">Title of the automatic “pages” column</label><input class="input" id="footer_pages_title" name="footer_pages_title" value="<?= e($nav['footer_pages_title'] ?? 'Company') ?>"><p class="hint">This column lists your active pages (About, policies…) from “Pages &amp; policies”.</p></div>
    </section>
  </div>
  <div class="sticky-actions"><button class="btn btn-primary" type="submit">Save menus</button></div>
</form>
