<div class="page-actions"><span></span><a class="btn btn-primary btn-sm" href="<?= e(url('/admin/coupons/new')) ?>"><?= icon('plus', 'icon icon-sm') ?> New coupon</a></div>
<?php if ($rows): ?>
<table class="admin-table stack-sm">
  <thead><tr><th scope="col">Code</th><th scope="col">Discount</th><th scope="col">Rules</th><th scope="col" class="num">Uses</th><th scope="col" class="num">Discounted</th><th scope="col">Status</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c):
    $now = now_utc();
    $state = !$c['is_active'] ? 'Inactive' : ($c['ends_at'] && $c['ends_at'] < $now ? 'Expired' : ($c['starts_at'] && $c['starts_at'] > $now ? 'Scheduled' : ($c['usage_limit'] !== null && $c['usage_count'] >= $c['usage_limit'] ? 'Used up' : 'Active'))); ?>
    <tr>
      <td data-label="Code"><a href="<?= e(url('/admin/coupons/' . $c['id'])) ?>"><strong><?= e($c['code']) ?></strong></a><?= $c['is_demo'] ? ' <span class="tag tag-muted">demo</span>' : '' ?><br><span class="xsmall muted"><?= e((string) $c['description']) ?></span></td>
      <td data-label="Discount"><?= $c['type'] === 'percentage' ? (int) $c['value'] . '%' : ($c['type'] === 'fixed' ? e(money((int) $c['value'])) : 'Free delivery') ?><?= $c['free_shipping'] && $c['type'] !== 'free_shipping' ? ' + free delivery' : '' ?></td>
      <td data-label="Rules" class="small"><?= $c['min_subtotal_minor'] ? 'Min ' . e(money((int) $c['min_subtotal_minor'])) . ' · ' : '' ?><?= e(ucfirst($c['scope'])) ?><?= $c['per_customer_limit'] ? ' · ' . (int) $c['per_customer_limit'] . '/customer' : '' ?><?= $c['ends_at'] ? '<br>ends ' . e(dt($c['ends_at'])) : '' ?></td>
      <td data-label="Uses" class="num"><?= (int) $c['usage_count'] ?><?= $c['usage_limit'] !== null ? ' / ' . (int) $c['usage_limit'] : '' ?></td>
      <td data-label="Discounted" class="num"><?= e(money((int) $c['discounted'])) ?></td>
      <td data-label="Status"><span class="status-pill s-<?= $state === 'Active' ? 'delivered' : ($state === 'Scheduled' ? 'pending' : 'cancelled') ?>"><?= $state ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?><div class="card empty-state"><?= icon('tag') ?><h2>No coupons yet</h2><a class="btn btn-dark" href="<?= e(url('/admin/coupons/new')) ?>">Create a coupon</a></div><?php endif; ?>
