<div class="container error-page">
  <p class="code" aria-hidden="true">404</p>
  <h1>We couldn't find that page</h1>
  <p class="muted"><?= e($message ?? 'The link may be broken or the page may have moved.') ?></p>
  <form class="newsletter-form mt-4" action="<?= e(url('/search')) ?>" method="get" role="search">
    <label class="sr-only" for="q404">Search products</label>
    <input class="input" id="q404" type="search" name="q" placeholder="Search products">
    <button class="btn btn-dark" type="submit">Search</button>
  </form>
  <div class="flex flex-center mt-4">
    <a class="btn btn-primary" href="<?= e(url('/shop')) ?>">Shop all</a>
    <a class="btn btn-outline" href="<?= e(url('/new-arrivals')) ?>">New arrivals</a>
  </div>
</div>
