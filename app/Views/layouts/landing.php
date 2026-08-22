<?php
/** @var callable $content */
/** @var string $title */
$title = $title ?? 'ProProfessor AI';
?>
<!DOCTYPE html>
<html lang="en" class="lp-html">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e(config('app_name')) ?></title>
  <meta name="description" content="ProProfessor AI is an AI-native academic operating system for institutions, HODs, professors, and students.">
  <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="alternate icon" href="<?= e(asset('img/favicon.svg')) ?>">
  <link rel="apple-touch-icon" href="<?= e(asset('img/logo.svg')) ?>">
  <meta name="theme-color" content="#0b091c">
  <meta name="app-base" content="<?= e(app_base_path()) ?>">
  <meta name="asset-base" content="<?= e(rtrim(asset(''), '/')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
</head>
<body class="lp-body" data-effects="on">
<div class="page-glow" aria-hidden="true"></div>
<canvas class="lp-particles" id="lpParticles" aria-hidden="true"></canvas>
<?php $content(); ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/landing.js')) ?>"></script>
</body>
</html>
