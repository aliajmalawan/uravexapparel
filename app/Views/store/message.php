<div class="container container-narrow error-page">
  <h1><?= e($heading) ?></h1>
  <p class="muted"><?= e($body) ?></p>
  <a class="btn btn-dark" href="<?= e(url($cta_href ?? '/')) ?>"><?= e($cta_label ?? 'Back to the store') ?></a>
</div>
