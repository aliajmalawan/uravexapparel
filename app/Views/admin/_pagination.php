<?php
/** @var int $page @var int $pages @var string $path @var array $query */
if ($pages <= 1) { return; }
$q = $query;
?>
<nav class="pagination" aria-label="Pagination">
  <?php if ($page > 1): ?><a href="<?= e(url($path, array_merge($q, ['page' => $page - 1]))) ?>" rel="prev" aria-label="Previous page"><?= icon('chevron-left', 'icon icon-sm') ?></a><?php endif; ?>
  <?php for ($n = max(1, $page - 2); $n <= min($pages, $page + 2); $n++): ?>
    <?php if ($n === $page): ?><span aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e(url($path, array_merge($q, ['page' => $n]))) ?>"><?= $n ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?><a href="<?= e(url($path, array_merge($q, ['page' => $page + 1]))) ?>" rel="next" aria-label="Next page"><?= icon('chevron-right', 'icon icon-sm') ?></a><?php endif; ?>
</nav>
