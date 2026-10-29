<div class="container error-page">
  <p class="code" aria-hidden="true">500</p>
  <h1>Something went wrong</h1>
  <p class="muted"><?= e($message ?? 'Please try again in a moment.') ?></p>
  <?php if (!empty($ref)): ?><p class="xsmall muted">Reference: <?= e($ref) ?></p><?php endif; ?>
  <?php if (!empty($debug)): ?><pre class="alert alert-error debug-box"><?= e($debug) ?></pre><?php endif; ?>
  <a class="btn btn-dark" href="<?= e(url('/')) ?>">Back to the store</a>
</div>
