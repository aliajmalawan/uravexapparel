<header class="page-head-dark on-dark"><div class="container container-narrow"><span class="eyebrow">Help centre</span><h1>Frequently asked questions</h1></div></header>
<div class="container container-narrow section">
  <?php if ($faqs): ?>
    <div class="faq-list">
      <?php foreach ($faqs as $f): ?>
        <details><summary><?= e($f['question']) ?></summary><div class="prose acc-body"><?= App\Core\Sanitizer::paragraphs($f['answer']) ?></div></details>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state"><?= icon('chat') ?><h2>No FAQs yet</h2><p class="muted">Questions? We're happy to help.</p></div>
  <?php endif; ?>
  <p class="mt-4">Still need help? <a href="<?= e(url('/contact')) ?>">Contact our team</a>.</p>
</div>
