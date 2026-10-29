<?php $subject = '[URAVEX] ' . $headline; ?>
<h1 style="font-size:20px;margin:0 0 12px;"><?= e($headline) ?></h1>
<p><?= e($body) ?></p>
<?= App\Core\View::partial('emails/_button', ['href' => $adminUrl, 'label' => 'Open admin']) ?>
