<header class="page-head-dark on-dark">
  <div class="container container-narrow">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="<?= e(url('/')) ?>">Home</a></li><li aria-current="page"><?= e($page['title']) ?></li></ol></nav>
    <span class="eyebrow"><?= $isAbout ? 'Our story' : 'Information' ?></span>
    <h1><?= e($page['title']) ?></h1>
  </div>
</header>
<article class="container container-narrow section prose">
  <?php if ($isAbout): ?><img class="about-logo" src="<?= e(brand_logo()) ?>" alt="URAVEX Apparel — global clothing manufacturing &amp; exports" width="600" height="300" loading="lazy"><?php endif; ?>
  <?= rich($page["body"]) /* sanitized on save */ ?>
  <?php if (!empty($page['updated_at'])): ?><p class="xsmall muted mt-4">Last updated <?= e(date_only($page['updated_at'])) ?></p><?php endif; ?>
</article>
