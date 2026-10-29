<?php
/** @var array $o order detail */
$steps = App\Services\OrderState::TIMELINE;
$reached = array_search($o['status'], $steps, true);
$terminal = in_array($o['status'], ['cancelled', 'refunded'], true);
?>
<div class="flex between">
  <p><strong><?= e($o['order_number']) ?></strong> · <?= e(dt($o['placed_at'])) ?></p>
  <span class="status-pill s-<?= e($o['status']) ?>"><?= e(App\Services\OrderState::label($o['status'])) ?></span>
</div>
<?php if (!$terminal && $reached !== false): ?>
  <ol class="timeline" aria-label="Order progress">
    <?php foreach ($steps as $i => $step): ?>
      <li class="<?= $i <= $reached ? 'done' : '' ?>"<?= $i === $reached ? ' aria-current="step"' : '' ?>><?= e(App\Services\OrderState::label($step)) ?></li>
    <?php endforeach; ?>
  </ol>
<?php elseif ($o['status'] === 'awaiting_payment'): ?>
  <div class="alert alert-warning"><p>Waiting for payment<?= $o['payment_status'] === 'pending_verification' ? ' verification' : '' ?>.</p></div>
<?php endif; ?>

<?php foreach ($o['seller_shipments'] ?? [] as $ss): ?>
  <div class="bank-box mt-4">
    <dl>
      <dt>Items from</dt><dd><?= e($ss['shop_name']) ?></dd>
      <dt>Carrier</dt><dd><?= e($ss['courier']) ?></dd>
      <dt>Tracking no.</dt><dd><?= e($ss['tracking_number']) ?></dd>
    </dl>
    <?php if ($ss['tracking_url']): ?><p class="mt-2"><a class="link-arrow" href="<?= e($ss['tracking_url']) ?>" target="_blank" rel="noopener noreferrer">Track package <?= icon('external', 'icon icon-sm') ?></a></p><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php foreach ($o['shipments'] as $sh): ?>
  <div class="bank-box mt-4">
    <dl>
      <dt>Courier</dt><dd><?= e($sh['courier']) ?></dd>
      <dt>Tracking no.</dt><dd><?= e($sh['tracking_number']) ?></dd>
      <dt>Shipped</dt><dd><?= e(dt($sh['shipped_at'])) ?></dd>
    </dl>
    <?php if ($sh['tracking_url']): ?><p class="mt-2"><a class="link-arrow" href="<?= e($sh['tracking_url']) ?>" target="_blank" rel="noopener noreferrer">Track with courier <?= icon('external', 'icon icon-sm') ?></a></p><?php endif; ?>
  </div>
<?php endforeach; ?>

<?php $visible = array_filter($o['history'], fn ($h) => $h['visible']); ?>
<?php if ($visible): ?>
  <h3 class="small mt-4">Updates</h3>
  <ul class="history">
    <?php foreach (array_reverse($visible) as $h): ?>
      <li><strong><?= e(App\Services\OrderState::label($h['to_status'])) ?></strong> — <?= e(dt($h['created_at'])) ?><?php if ($h['note']): ?><br><span class="muted"><?= e($h['note']) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
