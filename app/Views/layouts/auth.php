<?php
/** @var callable $content */
/** @var string $title */
$title = $title ?? 'ProProfessor AI';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e(config('app_name')) ?></title>
  <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="alternate icon" href="<?= e(asset('img/favicon.svg')) ?>">
  <link rel="apple-touch-icon" href="<?= e(asset('img/logo.svg')) ?>">
  <meta name="theme-color" content="#0b091c">
  <meta name="app-base" content="<?= e(app_base_path()) ?>">
  <meta name="asset-base" content="<?= e(rtrim(asset(''), '/')) ?>">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body data-effects="on">
<div class="page-glow" aria-hidden="true"></div>
<?php $content(); ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
