<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'URAVEX Apparel') ?> | URAVEX</title>
<link rel="icon" href="<?= e(path_url('favicon.ico')) ?>" sizes="any">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Montserrat:wght@400;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body>
<main id="main"><?= $content ?></main>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
