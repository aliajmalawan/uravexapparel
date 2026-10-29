<?= App\Core\View::partial('admin/content/_tabs') ?>
<div class="grid-2 grid-2-1">
  <section>
    <?php foreach ($rows as $f): ?>
      <form class="card" method="post" action="<?= e(url('/admin/content/faqs')) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <div class="field"><label for="q<?= (int) $f['id'] ?>">Question</label><input class="input" id="q<?= (int) $f['id'] ?>" name="question" required value="<?= e($f['question']) ?>"></div>
        <div class="field"><label for="a<?= (int) $f['id'] ?>">Answer</label><textarea class="textarea" id="a<?= (int) $f['id'] ?>" name="answer" rows="3" required><?= e($f['answer']) ?></textarea></div>
        <div class="flex"><label class="small">Order <input class="input" type="number" name="sort_order" min="0" value="<?= (int) $f['sort_order'] ?>"></label><label class="check"><input type="checkbox" name="is_active" value="1"<?= $f['is_active'] ? ' checked' : '' ?>> Visible</label><button class="btn btn-dark btn-sm" type="submit">Save</button></div>
      </form>
      <form method="post" action="<?= e(url('/admin/content/faqs/' . $f['id'] . '/delete')) ?>" data-confirm="Delete this FAQ?" class="mb-4"><?= csrf_field() ?><button class="btn btn-link small" type="submit">Delete “<?= e(str_limit($f['question'], 40)) ?>”</button></form>
    <?php endforeach; ?>
  </section>
  <form class="card" method="post" action="<?= e(url('/admin/content/faqs')) ?>">
    <?= csrf_field() ?><h2>Add FAQ</h2>
    <div class="field mt-4"><label for="nq">Question</label><input class="input" id="nq" name="question" required<?= error_attrs('question') ?>><?= error_text('question') ?></div>
    <div class="field"><label for="na">Answer</label><textarea class="textarea" id="na" name="answer" rows="4" required<?= error_attrs('answer') ?>></textarea><?= error_text('answer') ?></div>
    <div class="field"><label for="no">Order</label><input class="input" id="no" type="number" name="sort_order" min="0" value="<?= count($rows) ?>"></div>
    <label class="check"><input type="checkbox" name="is_active" value="1" checked> Visible</label>
    <button class="btn btn-primary btn-sm mt-4" type="submit">Add FAQ</button>
  </form>
</div>
