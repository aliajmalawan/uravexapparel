<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="print-body">
<div class="no-print container flex mt-4"><a class="btn btn-outline btn-sm" href="<?= e(url($back ?? '/admin/orders')) ?>">Back to order</a><button class="btn btn-dark btn-sm" type="button" data-print>Print</button></div>
<?= $content ?>
<script src="<?= e(asset('assets/js/print.js')) ?>" defer></script>
</body>
</html>
