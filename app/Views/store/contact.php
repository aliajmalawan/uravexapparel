<header class="page-head-dark on-dark"><div class="container"><span class="eyebrow">Customer care</span><h1>Contact us</h1></div></header>
<div class="container section contact-grid">
  <div>
    <h2>We're here to help</h2>
    <p class="muted">Questions about sizing, an order or wholesale manufacturing? Send us a message.</p>
    <ul class="assurances single">
      <?php if (!empty($store['support_email'])): ?><li><?= icon('mail') ?><div><strong>Email</strong><span><a href="mailto:<?= e($store['support_email']) ?>"><?= e($store['support_email']) ?></a></span></div></li><?php endif; ?>
      <?php if (!empty($store['support_phone'])): ?><li><?= icon('phone') ?><div><strong>Phone</strong><span><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $store['support_phone'])) ?>"><?= e($store['support_phone']) ?></a></span></div></li><?php endif; ?>
      <?php if (!empty($store['address'])): ?><li><?= icon('pin') ?><div><strong>Studio</strong><span><?= nl2br(e($store['address'])) ?></span></div></li><?php endif; ?>
      <?php if (!empty($store['business_hours'])): ?><li><?= icon('history') ?><div><strong>Hours</strong><span><?= e($store['business_hours']) ?></span></div></li><?php endif; ?>
    </ul>
  </div>
  <form class="panel" method="post" action="<?= e(url('/contact')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="text" name="website" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="form-grid cols-2">
      <div class="field"><label for="c-name">Name</label><input class="input" id="c-name" name="name" required autocomplete="name" maxlength="120" value="<?= e(old('name', $user['name'] ?? '')) ?>"<?= error_attrs('name') ?>><?= error_text('name') ?></div>
      <div class="field"><label for="c-email">Email</label><input class="input" id="c-email" name="email" type="email" required autocomplete="email" value="<?= e(old('email', $user['email'] ?? '')) ?>"<?= error_attrs('email') ?>><?= error_text('email') ?></div>
      <div class="field"><label for="c-phone">Phone <span class="muted">(optional)</span></label><input class="input" id="c-phone" name="phone" type="tel" autocomplete="tel" value="<?= e(old('phone')) ?>"<?= error_attrs('phone') ?>><?= error_text('phone') ?></div>
      <div class="field"><label for="c-subject">Subject</label><input class="input" id="c-subject" name="subject" required maxlength="191" value="<?= e(old('subject')) ?>"<?= error_attrs('subject') ?>><?= error_text('subject') ?></div>
      <div class="field span-2"><label for="c-message">Message</label><textarea class="textarea" id="c-message" name="message" required rows="6" maxlength="5000"<?= error_attrs('message') ?>><?= e(old('message')) ?></textarea><?= error_text('message') ?></div>
    </div>
    <button class="btn btn-dark" type="submit">Send message</button>
  </form>
</div>
