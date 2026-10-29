<div class="container error-page">
  <p class="code" aria-hidden="true">429</p>
  <h1>Please slow down</h1>
  <p class="muted"><?= e($message ?? 'Too many attempts. Please wait a moment and try again.') ?></p>
  <a class="btn btn-dark" href="<?= e(url('/')) ?>">Back to the store</a>
</div>
