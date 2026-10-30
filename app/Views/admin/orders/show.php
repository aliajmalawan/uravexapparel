<?php
use App\Auth\Auth;
use App\Services\OrderState;

$ship = $o['shipping'];
$payment = end($o['payments']) ?: null;
$refundable = (int) $o['total_minor'] - (int) $o['refunded_minor'];
$canRefund = Auth::can('orders.refund') && in_array($o['payment_status'], ['paid', 'partially_refunded'], true) && $refundable > 0;
?>
<div class="page-actions">
  <a class="link-arrow" href="<?= e(url('/admin/orders')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?> All orders</a>
  <div class="flex">
    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/orders/' . $o['id'] . '/invoice')) ?>"><?= icon('printer', 'icon icon-sm') ?> Invoice</a>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/orders/' . $o['id'] . '/packing-slip')) ?>"><?= icon('file', 'icon icon-sm') ?> Packing slip</a>
  </div>
</div>

<section class="card">
  <div class="card-head">
    <h2><?= e($o['order_number']) ?> · <?= e(dt($o['placed_at'])) ?></h2>
    <div class="flex">
      <span class="status-pill s-<?= e($o['status']) ?>"><?= e(OrderState::label($o['status'])) ?></span>
      <span class="status-pill s-<?= e($o['payment_status']) ?>">Payment: <?= e(status_label($o['payment_status'])) ?></span>
      <?php if ($o['is_test']): ?><span class="tag tag-muted">Sandbox test</span><?php endif; ?>
      <?php if ($o['is_demo']): ?><span class="tag tag-muted">Demo</span><?php endif; ?>
    </div>
  </div>
  <?php if ($o['status'] === 'cancelled' && $o['payment_status'] === 'paid'): ?>
    <div class="alert alert-error"><p>Payment was received after this order was cancelled. Refund the customer.</p></div>
  <?php endif; ?>
  <?php if ($o['status'] === 'awaiting_payment' && $o['reservation_expires_at'] && $o['payment_status'] !== 'pending_verification'): ?>
    <div class="alert alert-info"><p>Stock is reserved until <?= e(dt($o['reservation_expires_at'])) ?>; unpaid orders are cancelled automatically after that.</p></div>
  <?php endif; ?>

  <?php
  // Progress bar + what to do next, so it is always clear which button moves the order on.
  $steps = ['placed' => 'Placed', 'paid' => 'Paid', 'processing' => 'Processing', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
  $paid = in_array($o['payment_status'], ['paid', 'partially_refunded', 'refunded'], true) || ($o['payment_method'] === 'cod' && $o['status'] !== 'awaiting_payment');
  $reached = match ($o['status']) {
      'delivered' => 5, 'shipped' => 4, 'packed' => 3, 'processing' => 2,
      'confirmed' => 1, default => $paid ? 1 : 0,
  };
  $closed = in_array($o['status'], ['cancelled', 'refunded'], true);
  $hint = match (true) {
      $closed => null,
      $o['status'] === 'awaiting_payment' && $o['payment_status'] === 'pending_verification' => ['The customer submitted a payment. Check it and approve it to confirm the order.', '#payment', 'Verify payment'],
      $o['status'] === 'awaiting_payment' => ['Waiting for the customer to pay and submit the transaction hash. Nothing to do yet.', null, null],
      $o['status'] === 'confirmed' => ['Payment received. Start preparing the order.', null, null],
      $o['status'] === 'processing' => ['Pack the items, then mark the order packed.', null, null],
      $o['status'] === 'packed' => ['Add the courier and tracking number to ship the order.', '#shipment', 'Add tracking'],
      $o['status'] === 'shipped' => ['Mark the order delivered once the courier confirms delivery.', null, null],
      default => null,
  };
  ?>
  <?php if (!$closed): ?>
    <ol class="order-steps" aria-label="Order progress">
      <?php $i = 0; foreach ($steps as $label): ?>
        <li class="<?= $i < $reached ? 'is-done' : ($i === $reached ? 'is-current' : '') ?>"<?= $i === $reached ? ' aria-current="step"' : '' ?>><span class="dot"><?= $i < $reached ? icon('check', 'icon icon-sm') : $i + 1 ?></span><span class="lbl"><?= e($label) ?></span></li>
      <?php $i++; endforeach; ?>
    </ol>
  <?php endif; ?>
  <?php if ($hint): ?>
    <div class="next-step"><p><strong>Next step:</strong> <?= e($hint[0]) ?></p><?php if ($hint[1]): ?><a class="btn btn-primary btn-sm" href="<?= e($hint[1]) ?>"><?= e($hint[2]) ?> <?= icon('arrow-right', 'icon icon-sm') ?></a><?php endif; ?></div>
  <?php endif; ?>

  <div class="flex">
    <?php if (Auth::can('orders.fulfil')): ?>
      <?php foreach ($next as $to): if ($to === 'cancelled' || $to === 'shipped') continue; ?>
        <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="to" value="<?= e($to) ?>"><button class="btn btn-sm <?= $to === 'delivered' ? 'btn-primary' : 'btn-dark' ?>" type="submit">Mark <?= e(strtolower(OrderState::label($to))) ?></button></form>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<div class="grid-2 grid-2-1">
  <div>
    <section class="card">
      <h2>Items</h2>
      <div class="table-scroll mt-2">
        <table class="admin-table order-items-table">
          <thead><tr><th scope="col">Item</th><th scope="col" class="num">Unit</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Discount</th><th scope="col" class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($o['items'] as $it): ?>
              <tr>
                <td><strong><?= e($it['product_title']) ?></strong><br><span class="xsmall muted"><?= $it['seller_id'] ? 'Seller: ' . e((string) App\Core\Database::get()->value('SELECT shop_name FROM sellers WHERE id = ?', [$it['seller_id']])) . ' (gets ' . e(money((int) $it['seller_amount_minor'], $o['currency'])) . ') · ' : '' ?><?= e($it['variant_label']) ?> · <?= e($it['sku']) ?><?= $it['refunded_quantity'] ? ' · ' . (int) $it['refunded_quantity'] . ' refunded' : '' ?></span></td>
                <td class="num"><?= e(money((int) $it['unit_price_minor'], $o['currency'] ?? null)) ?></td>
                <td class="num"><?= (int) $it['quantity'] ?></td>
                <td class="num"><?= $it['discount_minor'] ? '−' . e(money((int) $it['discount_minor'], $o['currency'] ?? null)) : '—' ?></td>
                <td class="num"><?= e(money((int) $it['line_total_minor'], $o['currency'] ?? null)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="4">Subtotal</td><td class="num"><?= e(money((int) $o['subtotal_minor'], $o['currency'] ?? null)) ?></td></tr>
            <?php if ($o['discount_minor'] > 0): ?><tr><td colspan="4">Discount<?= $o['coupon_code'] ? ' (' . e($o['coupon_code']) . ')' : '' ?></td><td class="num">−<?= e(money((int) $o['discount_minor'], $o['currency'] ?? null)) ?></td></tr><?php endif; ?>
            <tr><td colspan="4">Delivery — <?= e($o['shipping_method']) ?></td><td class="num"><?= e(money((int) $o['shipping_minor'], $o['currency'] ?? null)) ?></td></tr>
            <?php if ($o['tax_minor'] > 0): ?><tr><td colspan="4">Tax</td><td class="num"><?= e(money((int) $o['tax_minor'], $o['currency'] ?? null)) ?></td></tr><?php endif; ?>
            <tr><th colspan="4">Total</th><th class="num"><?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?></th></tr>
            <?php if ($o['refunded_minor'] > 0): ?><tr><td colspan="4">Refunded</td><td class="num text-error">−<?= e(money((int) $o['refunded_minor'], $o['currency'] ?? null)) ?></td></tr><?php endif; ?>
          </tfoot>
        </table>
      </div>
    </section>

    <section class="card" id="payment">
      <h2>Payment</h2>
      <?php foreach ($o['payments'] as $pm): ?>
        <dl class="kv mt-2">
          <dt>Method</dt><dd><?= e($provider?->label() ?? $pm['provider']) ?> (<?= e($pm['mode']) ?>)</dd>
          <dt>Status</dt><dd><span class="status-pill s-<?= e($pm['status'] === 'awaiting_verification' ? 'pending_verification' : $pm['status']) ?>"><?= e(status_label($pm['status'])) ?></span></dd>
          <dt>Amount</dt><dd><?= e(money((int) $pm['amount_minor'], $pm['currency'])) ?></dd>
          <?php if ($pm['provider_reference']): ?><dt>Provider ref.</dt><dd><?= e($pm['provider_reference']) ?></dd><?php endif; ?>
          <?php if ($pm['pay_amount'] !== null): ?><dt>Expected</dt><dd><strong><?= e(rtrim(rtrim((string) $pm['pay_amount'], '0'), '.') . ' ' . $pm['pay_asset']) ?></strong> on <?= e((string) $pm['pay_network']) ?><?= $pm['pay_rate'] ? ' <span class="xsmall muted">(rate ' . e((string) $pm['pay_rate']) . ' USDT)</span>' : '' ?></dd><dt>To wallet</dt><dd><code class="wallet"><?= e((string) $pm['pay_address']) ?></code></dd><?php endif; ?>
          <?php if ($pm['customer_reference']): $mp = App\Payments\Registry::get($pm['provider']); $link = $mp instanceof App\Payments\ManualPayment ? $mp->explorerUrl($pm['customer_reference']) : null; ?><dt><?= $pm['pay_amount'] !== null ? 'TXID' : 'Customer ref.' ?></dt><dd><strong class="wallet"><?= e($pm['customer_reference']) ?></strong><?php if ($link): ?><br><a href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer">Check on blockchain explorer ↗</a><?php endif; ?></dd><?php endif; ?>
          <?php if ($pm['receipt_path'] && Auth::can('orders.payments')): ?><dt>Receipt</dt><dd><a href="<?= e(url('/admin/orders/' . $o['id'] . '/receipt/' . $pm['id'])) ?>" target="_blank" rel="noopener">View uploaded receipt</a></dd><?php endif; ?>
          <?php if ($pm['verified_at']): ?><dt>Verified</dt><dd><?= e(dt($pm['verified_at'])) ?> by <?= e($pm['verified_by_name'] ?? 'provider') ?><?= $pm['verification_note'] ? ' — ' . e($pm['verification_note']) : '' ?></dd><?php endif; ?>
          <?php if ($pm['failure_reason']): ?><dt>Failure</dt><dd class="text-error"><?= e($pm['failure_reason']) ?></dd><?php endif; ?>
        </dl>
      <?php endforeach; ?>

      <?php if (Auth::can('orders.payments') && $o['payment_method'] === 'cod' && $o['payment_status'] !== 'paid' && !in_array($o['status'], ['cancelled', 'refunded'], true)): ?>
        <form class="mt-4" method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/cod-collected')) ?>">
          <?= csrf_field() ?>
          <label class="check"><input type="checkbox" name="confirm" value="1" required> I confirm <?= e(money((int) $o['total_minor'], $o['currency'] ?? null)) ?> was collected in full.</label>
          <div class="flex mt-2"><input class="input grow" name="note" placeholder="Note (e.g. courier remittance ref.)" maxlength="500"><button class="btn btn-dark btn-sm" type="submit">Record cash collected</button></div>
          <?= error_text('confirm') ?>
        </form>
      <?php endif; ?>

      <?php $isCrypto = ($payment['pay_amount'] ?? null) !== null; if (Auth::can('orders.payments') && App\Services\Orders::manualProvider($o) && $payment && in_array($payment['status'], ['pending', 'awaiting_verification'], true)): ?>
        <form class="mt-4" method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/bank-verify')) ?>">
          <?= csrf_field() ?>
          <?php if ($payment['status'] === 'pending'): ?><p class="small muted">The customer has not submitted a transaction hash yet. Only approve if you have confirmed the funds <?= $isCrypto ? 'arrived in your wallet' : 'in your bank account' ?>.</p><?php elseif ($isCrypto): ?><p class="small muted">Open the explorer link above and check: the transaction is confirmed, it was sent to <strong>your</strong> wallet address, on the right network, for at least the expected amount — and it is not a transaction already used for another order.</p><?php endif; ?>
          <div class="field"><label for="bnote">Verification note</label><input class="input" id="bnote" name="note" maxlength="500" placeholder="<?= $isCrypto ? 'e.g. Confirmed on explorer, 25 Oct' : 'e.g. Matched in statement 25 Oct, ref 1234' ?>"<?= error_attrs('note') ?>><?= error_text('note') ?></div>
          <div class="flex">
            <button class="btn btn-primary btn-sm" type="submit" name="decision" value="approve" data-confirm="<?= $isCrypto ? e('Confirm you have verified ' . rtrim(rtrim((string) $payment['pay_amount'], '0'), '.') . ' ' . $payment['pay_asset'] . ' arrived in your wallet?') : 'Confirm you have verified ' . e(money((int) $o['total_minor'], $o['currency'] ?? null)) . ' in the bank account?' ?>">Approve payment</button>
            <button class="btn btn-danger btn-sm" type="submit" name="decision" value="reject">Reject</button>
          </div>
        </form>
      <?php endif; ?>

      <?php if ($events): ?>
        <details class="mt-4"><summary class="small">Provider events (<?= count($events) ?>)</summary>
          <table class="admin-table mt-2"><thead><tr><th>When</th><th>Event</th><th>Outcome</th></tr></thead><tbody>
            <?php foreach ($events as $ev): ?><tr><td><?= e(dt($ev['received_at'])) ?></td><td><?= e($ev['type']) ?><br><span class="xsmall muted"><?= e($ev['provider_event_id']) ?></span></td><td><?= e($ev['outcome']) ?></td></tr><?php endforeach; ?>
          </tbody></table>
        </details>
      <?php endif; ?>
    </section>

    <?php if ($canRefund): ?>
    <section class="card">
      <h2>Record refund</h2>
      <p class="small muted mt-2">Refundable balance: <strong><?= e(money($refundable, $o['currency'])) ?></strong>. Select items to mark as returned; tick “restock” to put them back into inventory.</p>
      <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/refund')) ?>" data-confirm="Record this refund? This cannot be undone.">
        <?= csrf_field() ?><input type="hidden" name="idempotency_key" value="<?= e($refundKey) ?>">
        <div class="table-scroll"><table class="admin-table"><thead><tr><th>Item</th><th>Qty to refund</th><th>Restock</th></tr></thead><tbody>
          <?php foreach ($o['items'] as $it): $left = (int) $it['quantity'] - (int) $it['refunded_quantity']; if ($left <= 0) continue; ?>
            <tr><td><?= e($it['product_title']) ?> <span class="xsmall muted"><?= e($it['variant_label']) ?></span></td>
              <td><input class="input" type="number" min="0" max="<?= $left ?>" value="0" name="qty[<?= (int) $it['id'] ?>]" aria-label="Quantity to refund for <?= e($it['product_title']) ?>"></td>
              <td><input type="checkbox" name="restock[<?= (int) $it['id'] ?>]" value="1" aria-label="Restock <?= e($it['product_title']) ?>"></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
        <div class="form-row c3 mt-4">
          <div class="field"><label for="amount">Amount (<?= e($o['currency']) ?>)</label><input class="input" id="amount" name="amount" inputmode="decimal" required value="<?= e(App\Core\Money::decimal($refundable, $o['currency'])) ?>"<?= error_attrs('amount') ?>><?= error_text('amount') ?></div>
          <div class="field"><label for="method">Method</label><select class="select" id="method" name="method"><option value="manual">Manual (bank transfer / cash)</option><?php if ($provider?->supportsRefunds()): ?><option value="provider">Through <?= e($provider->label()) ?></option><?php endif; ?></select><?= error_text('method') ?></div>
          <div class="field"><label for="reference">Reference</label><input class="input" id="reference" name="reference" maxlength="120" placeholder="Transfer ref."></div>
        </div>
        <div class="field"><label for="reason">Reason</label><input class="input" id="reason" name="reason" required maxlength="500"<?= error_attrs('reason') ?>><?= error_text('reason') ?></div>
        <button class="btn btn-danger" type="submit">Record refund</button>
      </form>
    </section>
    <?php endif; ?>

    <?php if ($o['refunds']): ?>
    <section class="card"><h2>Refunds</h2>
      <table class="admin-table stack-sm mt-2"><thead><tr><th>Date</th><th class="num">Amount</th><th>Method</th><th>Reason</th><th>By</th></tr></thead><tbody>
        <?php foreach ($o['refunds'] as $rf): ?><tr><td data-label="Date"><?= e(dt($rf['created_at'])) ?></td><td data-label="Amount" class="num"><?= e(money((int) $rf['amount_minor'], $o['currency'] ?? null)) ?></td><td data-label="Method"><?= e($rf['method']) ?><?= $rf['reference'] ? ' · ' . e($rf['reference']) : '' ?></td><td data-label="Reason"><?= e($rf['reason']) ?></td><td data-label="By"><?= e($rf['actor_name'] ?? '—') ?></td></tr><?php endforeach; ?>
      </tbody></table>
    </section>
    <?php endif; ?>
  </div>

  <div>
    <section class="card">
      <h2>Customer</h2>
      <dl class="kv mt-2">
        <dt>Name</dt><dd><?= e($o['customer_name']) ?></dd>
        <dt>Email</dt><dd><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a></dd>
        <dt>Phone</dt><dd><a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a></dd>
        <dt>Account</dt><dd><?= $o['user_id'] && Auth::can('customers.view') ? '<a href="' . e(url('/admin/customers/' . $o['user_id'])) . '">View customer</a>' : 'Guest' ?> · <?= pluralize($customerOrders, 'order') ?></dd>
      </dl>
    </section>
    <section class="card">
      <h2>Shipping address</h2>
      <p class="small mt-2"><?= e($ship['full_name'] ?? '') ?><br><?= e($ship['line1'] ?? '') ?><?= !empty($ship['line2']) ? '<br>' . e($ship['line2']) : '' ?><br><?= e(App\Services\Region::locality($ship)) ?><br><?= e($ship['phone'] ?? '') ?></p>
      <?php if ($o['customer_note']): ?><p class="small"><strong>Customer note:</strong> <?= e($o['customer_note']) ?></p><?php endif; ?>
      <?php if (Auth::can('orders.fulfil') && in_array($o['status'], ['pending', 'awaiting_payment', 'confirmed', 'processing', 'packed'], true)): ?>
        <details class="inline-form mt-2"><summary class="btn btn-link small"><?= icon('edit', 'icon icon-sm') ?> Edit delivery details</summary>
          <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/address')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field"><label for="a-name">Full name</label><input class="input" id="a-name" name="full_name" required value="<?= e(old('full_name', $ship['full_name'] ?? '')) ?>"<?= error_attrs('full_name') ?>><?= error_text('full_name') ?></div>
            <div class="form-row c2">
              <div class="field"><label for="a-phone">Phone</label><input class="input" id="a-phone" name="phone" required value="<?= e(old('phone', $ship['phone'] ?? $o['phone'])) ?>"<?= error_attrs('phone') ?>><?= error_text('phone') ?></div>
              <div class="field"><label for="a-email">Email</label><input class="input" id="a-email" name="email" type="email" required value="<?= e(old('email', $o['email'])) ?>"<?= error_attrs('email') ?>><?= error_text('email') ?></div>
            </div>
            <div class="field"><label for="a-line1">Street address</label><input class="input" id="a-line1" name="line1" required value="<?= e(old('line1', $ship['line1'] ?? '')) ?>"<?= error_attrs('line1') ?>><?= error_text('line1') ?></div>
            <div class="field"><label for="a-line2">Apartment, suite, unit</label><input class="input" id="a-line2" name="line2" value="<?= e(old('line2', (string) ($ship['line2'] ?? ''))) ?>"></div>
            <div class="form-row c3">
              <div class="field"><label for="a-city">City</label><input class="input" id="a-city" name="city" required value="<?= e(old('city', $ship['city'] ?? '')) ?>"></div>
              <div class="field"><label for="a-province">State</label><input class="input" id="a-province" name="province" required value="<?= e(old('province', $ship['province'] ?? '')) ?>"></div>
              <div class="field"><label for="a-postal">ZIP code</label><input class="input" id="a-postal" name="postal_code" value="<?= e(old('postal_code', (string) ($ship['postal_code'] ?? ''))) ?>"></div>
            </div>
            <p class="hint">Allowed until the order ships. The old details are kept in the audit log. Delivery charges are not recalculated.</p>
            <button class="btn btn-dark btn-sm" type="submit">Save delivery details</button>
          </form>
        </details>
        <?= error_text('address') ?>
      <?php endif; ?>
    </section>

    <section class="card" id="shipment">
      <h2>Shipment</h2>
      <?php foreach ($o['seller_shipments'] as $ss): ?>
        <dl class="kv mt-2"><dt>Seller</dt><dd><?= e($ss['shop_name']) ?></dd><dt>Carrier</dt><dd><?= e($ss['courier']) ?></dd><dt>Tracking</dt><dd><?= $ss['tracking_url'] ? '<a href="' . e($ss['tracking_url']) . '" target="_blank" rel="noopener noreferrer">' . e($ss['tracking_number']) . '</a>' : e($ss['tracking_number']) ?></dd></dl>
      <?php endforeach; ?>
      <?php if (array_filter($o['items'], static fn ($it) => $it['seller_id'])): ?><p class="hint">Marketplace order: sellers ship their own items and add tracking above. Mark the order shipped/delivered once everything is on its way — seller earnings unlock after delivery.</p><?php endif; ?>
      <?php foreach ($o['shipments'] as $sh): ?>
        <dl class="kv mt-2"><dt>Courier</dt><dd><?= e($sh['courier']) ?></dd><dt>Tracking</dt><dd><?= $sh['tracking_url'] ? '<a href="' . e($sh['tracking_url']) . '" target="_blank" rel="noopener noreferrer">' . e($sh['tracking_number']) . '</a>' : e($sh['tracking_number']) ?></dd><dt>Shipped</dt><dd><?= e(dt($sh['shipped_at'])) ?></dd><?php if ($sh['delivered_at']): ?><dt>Delivered</dt><dd><?= e(dt($sh['delivered_at'])) ?></dd><?php endif; ?></dl>
      <?php endforeach; ?>
      <?php if (in_array('shipped', $next, true) && Auth::can('orders.fulfil')): ?>
        <form class="mt-2" method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/ship')) ?>">
          <?= csrf_field() ?>
          <div class="field"><label for="courier">Courier</label><select class="select" id="courier" name="courier" required><?php foreach ($couriers as $c): ?><option><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="tracking_number">Tracking number</label><input class="input" id="tracking_number" name="tracking_number" required maxlength="80"<?= error_attrs('tracking_number') ?>><?= error_text('tracking_number') ?></div>
          <div class="field"><label for="tracking_url">Tracking link <span class="muted">(optional)</span></label><input class="input" id="tracking_url" name="tracking_url" type="url" maxlength="255" placeholder="https://"<?= error_attrs('tracking_url') ?>><?= error_text('tracking_url') ?></div>
          <button class="btn btn-primary btn-sm" type="submit">Mark shipped &amp; notify</button>
        </form>
      <?php elseif (!$o['shipments']): ?>
        <p class="small muted mt-2">Tracking can be added once the order is packed.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Internal note</h2>
      <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/note')) ?>" class="mt-2">
        <?= csrf_field() ?>
        <label class="sr-only" for="admin_note">Internal note</label>
        <textarea class="textarea" id="admin_note" name="admin_note" rows="3" maxlength="5000"<?= Auth::can('orders.fulfil') ? '' : ' disabled' ?>><?= e((string) $o['admin_note']) ?></textarea>
        <?php if (Auth::can('orders.fulfil')): ?><button class="btn btn-outline btn-sm mt-2" type="submit">Save note</button><?php endif; ?>
      </form>
    </section>

    <section class="card">
      <h2>Timeline</h2>
      <ul class="history mt-2">
        <?php foreach (array_reverse($o['history']) as $h): ?>
          <li><strong><?= e(OrderState::label($h['to_status'])) ?></strong><?= $h['visible'] ? '' : ' <span class="xsmall muted">(internal)</span>' ?><br><span class="xsmall muted"><?= e(dt($h['created_at'])) ?> · <?= e($h['actor_name'] ?? 'System / customer') ?></span><?php if ($h['note']): ?><br><span class="small"><?= e($h['note']) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <?php if (in_array('cancelled', $next, true) && Auth::can('orders.cancel')): ?>
    <section class="card">
      <h2>Cancel order</h2>
      <p class="small muted mt-2">Returns all items to stock and releases any coupon use. The customer is emailed.</p>
      <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/cancel')) ?>" data-confirm="Cancel this order?">
        <?= csrf_field() ?>
        <div class="field"><label for="cnote">Reason (shown to customer)</label><input class="input" id="cnote" name="note" required maxlength="1000"<?= error_attrs('note') ?>><?= error_text('note') ?></div>
        <button class="btn btn-danger btn-sm" type="submit">Cancel order</button>
      </form>
    </section>
    <?php endif; ?>
  </div>
</div>
