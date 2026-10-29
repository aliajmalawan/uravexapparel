<table class="admin-table stack-sm">
  <thead><tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col" class="num">Items</th><th scope="col" class="num">Your earnings</th><th scope="col">Tracking</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $o): ?>
    <tr>
      <td data-label="Order"><a href="<?= e(url('/seller/orders/' . $o['order_number'])) ?>"><strong><?= e($o['order_number']) ?></strong></a></td>
      <td data-label="Date"><?= e(date_only($o['placed_at'])) ?></td>
      <td data-label="Status"><span class="status-pill s-<?= e($o['status']) ?>"><?= e(App\Services\OrderState::label($o['status'])) ?></span></td>
      <td data-label="Items" class="num"><?= (int) $o['qty'] ?></td>
      <td data-label="Your earnings" class="num"><?= e(money((int) $o['earnings'], $o['currency'])) ?></td>
      <td data-label="Tracking"><?= (int) $o['shipped_by_me'] ? '<span class="status-pill s-delivered">Added</span>' : (in_array($o['status'], ['confirmed', 'processing', 'packed'], true) ? '<span class="status-pill s-pending">Ship now</span>' : '—') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
