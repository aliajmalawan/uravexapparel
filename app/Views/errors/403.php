<div class="container error-page">
  <p class="code" aria-hidden="true">403</p>
  <h1>Access denied</h1>
  <p class="muted"><?= e($message ?? 'You do not have permission to view this page.') ?></p>
  <a class="btn btn-dark" href="<?= e(url('/')) ?>">Back to the store</a>
</div>
