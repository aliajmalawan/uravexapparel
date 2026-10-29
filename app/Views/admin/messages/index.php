<div class="page-actions">
  <div class="flex"><a class="btn btn-sm <?= $showAll ? 'btn-outline' : 'btn-dark' ?>" href="<?= e(url('/admin/messages')) ?>">Open</a><a class="btn btn-sm <?= $showAll ? 'btn-dark' : 'btn-outline' ?>" href="<?= e(url('/admin/messages', ['all' => '1'])) ?>">All</a></div>
  <?php if (App\Auth\Auth::can('customers.export')): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/newsletter/export')) ?>"><?= icon('download', 'icon icon-sm') ?> Newsletter list (<?= (int) $subscribers ?>)</a><?php endif; ?>
</div>
<?php foreach ($rows as $m): ?>
  <article class="card">
    <div class="card-head"><h2><?= e($m['subject']) ?></h2><span class="xsmall muted"><?= e(dt($m['created_at'])) ?></span></div>
    <p class="small"><strong><?= e($m['name']) ?></strong> · <a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['subject']) ?>"><?= e($m['email']) ?></a><?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?></p>
    <p class="small prose"><?= nl2br(e($m['message'])) ?></p>
    <form method="post" action="<?= e(url('/admin/messages/' . $m['id'] . '/handled')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit"><?= $m['handled'] ? 'Mark as open' : 'Mark handled' ?></button></form>
  </article>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card empty-state"><?= icon('mail') ?><h2>No messages</h2></div><?php endif; ?>
<?= App\Core\View::partial('admin/_pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/messages', 'query' => $showAll ? ['all' => '1'] : []]) ?>
