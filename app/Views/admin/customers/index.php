<div class="page-actions">
  <form class="toolbar" method="get" action="<?= e(url('/admin/customers')) ?>" role="search">
    <div class="field grow"><label class="sr-only" for="q">Search customers</label><input class="input" id="q" type="search" name="q" placeholder="Name, email or phone" value="<?= e($query['q'] ?? '') ?>"></div>
    <div class="field"><label class="sr-only" for="sort">Sort</label><select class="select" id="sort" name="sort"><option value="">Newest</option><option value="spend"<?= ($query['sort'] ?? '') === 'spend' ? ' selected' : '' ?>>Lifetime spend</option></select></div>
    <button class="btn btn-dark btn-sm" type="submit">Search</button>
  </form>
  <?php if (App\Auth\Auth::can('customers.export')): ?>
    <div class="flex">
      <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/customers/export', array_merge($query, ['masked' => '1']))) ?>"><?= icon('download', 'icon icon-sm') ?> Export (masked)</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/customers/export', $query)) ?>" data-confirm="This export contains personal data. Store it securely and delete it when done."><?= icon('download', 'icon icon-sm') ?> Export (full)</a>
    </div>
  <?php endif; ?>
</div>
<p class="small muted"><?= pluralize($total, 'customer') ?></p>
<?php if ($rows): ?>
<table class="admin-table stack-sm">
  <thead><tr><th scope="col">Customer</th><th scope="col">Joined</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Lifetime spend</th><th scope="col">Last order</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $u): ?>
    <tr>
      <td data-label="Customer"><a href="<?= e(url('/admin/customers/' . $u['id'])) ?>"><strong><?= e($u['name']) ?></strong></a><?= $u['is_demo'] ? ' <span class="tag tag-muted">demo</span>' : '' ?><br><span class="xsmall muted"><?= e($u['email']) ?><?= $u['email_verified_at'] ? '' : ' · unverified' ?></span></td>
      <td data-label="Joined"><?= e(date_only($u['created_at'])) ?></td>
      <td data-label="Orders" class="num"><?= (int) $u['orders'] ?></td>
      <td data-label="Lifetime spend" class="num"><?= e(money((int) $u['spend'])) ?></td>
      <td data-label="Last order"><?= e($u['last_order'] ? date_only($u['last_order']) : '—') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?= App\Core\View::partial('admin/_pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/customers', 'query' => $query]) ?>
<?php else: ?><div class="card empty-state"><?= icon('users') ?><h2>No customers found</h2></div><?php endif; ?>
