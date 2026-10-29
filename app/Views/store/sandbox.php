<div class="auth-wrap">
  <div class="auth-card center">
    <span class="eyebrow">Sandbox payment gateway</span>
    <h1>Test payment</h1>
    <p class="muted small">This simulated page stands in for a real provider's hosted checkout. No card details are collected and no money moves.</p>
    <div class="bank-box mt-4">
      <dl>
        <dt>Order</dt><dd><?= e($state['order_number']) ?></dd>
        <dt>Amount</dt><dd><?= e(money((int) $state['amount_minor'], $state['currency'])) ?></dd>
        <dt>Status</dt><dd><?= e(ucfirst($state['status'])) ?></dd>
      </dl>
    </div>
    <?php if ($state['status'] === 'pending'): ?>
      <form method="post" action="<?= e(url('/payments/sandbox/' . $ref)) ?>" class="stack mt-4">
        <?= csrf_field() ?>
        <button class="btn btn-primary btn-block" name="outcome" value="pay">Simulate successful payment</button>
        <button class="btn btn-outline btn-block" name="outcome" value="fail">Simulate declined payment</button>
        <button class="btn btn-link" name="outcome" value="cancel">Cancel and return to store</button>
      </form>
    <?php else: ?>
      <p class="mt-4">This payment session is already <?= e($state['status']) ?>.</p>
    <?php endif; ?>
  </div>
</div>
