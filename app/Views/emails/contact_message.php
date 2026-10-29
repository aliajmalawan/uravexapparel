<?php $subject = '[URAVEX] Contact form: ' . $m['subject']; ?>
<h1 style="font-size:20px;margin:0 0 12px;">New contact message</h1>
<p><strong><?= e($m['name']) ?></strong> &lt;<?= e($m['email']) ?>&gt;<?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?></p>
<p style="background:#F5F3EE;padding:12px;white-space:pre-wrap;"><?= e($m['message']) ?></p>
<?= App\Core\View::partial('emails/_button', ['href' => url('/admin/messages'), 'label' => 'Open messages']) ?>
