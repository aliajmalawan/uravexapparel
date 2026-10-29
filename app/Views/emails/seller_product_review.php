<?php $subject = ($approved ? 'Product approved: ' : 'Changes needed: ') . $p['title']; ?>
<h1 style="font-size:22px;margin:0 0 12px;"><?= $approved ? 'Your product is live' : 'Your product needs changes' ?></h1>
<p><strong><?= e($p['title']) ?></strong> <?= $approved ? 'has been approved and is now visible in the URAVEX store.' : 'was not approved yet.' ?></p>
<?php if (!$approved && $note !== ''): ?><p style="background:#F5F3EE;padding:12px;"><?= e($note) ?></p><p>Update the product and submit it again.</p><?php endif; ?>
<?= App\Core\View::partial('emails/_button', ['href' => $url, 'label' => 'Open product']) ?>
