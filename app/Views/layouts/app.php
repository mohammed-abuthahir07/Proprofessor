<?php
/** @var callable $content */
/** @var string $title */
/** @var string $active */
/** @var string $subtitle */
use App\Services\NavService;

$user = \Auth::user();
$role = $user['role'] ?? '';
$groups = NavService::grouped($role);
$unread = $user ? unread_notifications_count((int)$user['id']) : 0;
$title = $title ?? 'ProProfessor AI';
$active = $active ?? '';
$subtitle = $subtitle ?? '';
$first = trim((string)($user['full_name'] ?? 'U'));
$parts = preg_split('/\s+/', $first) ?: ['U'];
$initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1] ?? '', 0, 1));
$notifPath = match ($role) {
    'student' => '/student/notifications',
    'hod' => '/hod/notifications',
    'admin', 'superadmin' => '/admin/notifications',
    default => '/professor/notifications',
};
$cta = match ($role) {
    'professor' => ['href' => '/professor/generate-plan', 'label' => 'Generate Course Plan', 'icon' => 'spark'],
    'hod' => ['href' => '/hod/approvals', 'label' => 'Review Approvals', 'icon' => 'check'],
    'admin', 'superadmin' => ['href' => '/admin/users', 'label' => 'Manage Users', 'icon' => 'users'],
    default => null,
};
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
</head>
<body class="app-body" data-effects="on">
<div class="page-glow" aria-hidden="true"></div>
<div class="overlay" id="sidebarOverlay"></div>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img class="brand-logo" src="<?= e(asset('img/logo.svg')) ?>" width="42" height="42" alt="ProProfessor AI">
      <div>
        <div class="brand-name">ProProfessor AI</div>
        <div class="brand-sub"><?= e(NavService::roleLabel($role)) ?></div>
      </div>
      <button class="icon-btn sidebar-close" id="sidebarClose" type="button" aria-label="Close menu"><?= icon('close') ?></button>
    </div>
    <nav class="side-nav">
      <?php foreach ($groups as $group): ?>
        <?php
          $navItems = [];
          foreach ($group['items'] as $item) {
              if (!empty($item['feature']) && !feature_enabled($item['feature'])) continue;
              if (!empty($item['perm']) && !admin_can($item['perm'])) continue;
              $navItems[] = $item;
          }
          if (!$navItems) continue;
        ?>
        <div class="nav-group">
          <div class="nav-group-label"><?= e($group['label']) ?></div>
          <?php foreach ($navItems as $item): ?>
            <a class="nav-link <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e(url($item['href'])) ?>">
              <span class="nav-ico"><?= icon($item['icon'] ?? 'spark', 'icon') ?></span>
              <span><?= e($item['label']) ?></span>
              <?php if ($item['key'] === 'notifications' && $unread): ?>
                <span class="nav-badge"><?= (int)$unread ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <div class="side-user">
        <div class="avatar"><?= e($initials) ?></div>
        <div>
          <strong><?= e($user['full_name'] ?? '') ?></strong>
          <small><?= e(ucfirst($role)) ?></small>
        </div>
      </div>
      <a class="nav-link" href="<?= e(url('/logout')) ?>"><span class="nav-ico"><?= icon('logout') ?></span> <span>Logout</span></a>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="icon-btn" id="menuToggle" type="button" aria-label="Menu" aria-controls="sidebar" aria-expanded="false"><?= icon('menu') ?></button>
      <div class="topbar-title">
        <h1><?= e($title) ?></h1>
        <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
      </div>
      <div class="topbar-actions">
        <span class="proto-badge">Interactive Prototype v1.0</span>
        <?php if ($cta): ?>
          <a class="btn btn-primary btn-sm btn-shine topbar-cta" href="<?= e(url($cta['href'])) ?>"><?= icon($cta['icon']) ?> <?= e($cta['label']) ?></a>
        <?php endif; ?>
        <a class="icon-btn" href="<?= e(url($notifPath)) ?>" title="Notifications">
          <?= icon('bell') ?><?php if ($unread): ?><span class="dot"><?= (int)$unread ?></span><?php endif; ?>
        </a>
      </div>
    </header>
    <main class="content" id="pageContent">
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> reveal"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
      <?php $content(); ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
