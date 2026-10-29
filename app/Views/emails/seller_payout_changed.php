<?php $subject = 'Your payout address was changed — ' . $seller['shop_name']; ?>
<h1 style="font-size:22px;margin:0 0 12px;">Payout address changed</h1>
<p>The USDT (TRC20) payout address for <strong><?= e($seller['shop_name']) ?></strong> was changed to:</p>
<p style="background:#F5F3EE;padding:12px;font-family:Consolas,monospace;word-break:break-all;"><?= e($address ?: '(removed)') ?></p>
<p>If you did not make this change, reset your password immediately and contact us.</p>
