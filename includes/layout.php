<?php
declare(strict_types=1);

function render_header(string $title, string $active = '', array $opts = []): void
{
    if (!class_exists(\App\Services\NavService::class)) {
        require_once dirname(__DIR__) . '/app/Services/NavService.php';
    }
    if (!class_exists('Icons', false)) {
        require_once __DIR__ . '/Icons.php';
    }
    $user = Auth::user();
    $role = $user['role'] ?? '';
    $groups = \App\Services\NavService::grouped($role);
    $unread = $user ? unread_notifications_count((int)$user['id']) : 0;
    $first = trim((string)($user['full_name'] ?? 'U'));
    $parts = preg_split('/\s+/', $first) ?: ['U'];
    $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1] ?? '', 0, 1));
    $notif = match ($role) {
        'student' => '/student/notifications',
        'hod' => '/hod/notifications',
        'admin', 'superadmin' => '/admin/notifications',
        default => '/professor/notifications',
    };
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e(config('app_name')) ?></title>
  <link rel="icon" href="<?= e(base_url('/assets/img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="alternate icon" href="<?= e(base_url('/assets/img/favicon.svg')) ?>">
  <link rel="apple-touch-icon" href="<?= e(base_url('/assets/img/logo.svg')) ?>">
  <meta name="theme-color" content="#0b091c">
  <meta name="app-base" content="<?= e(app_base_path()) ?>">
  <meta name="asset-base" content="<?= e(rtrim(base_url('/assets'), '/')) ?>">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(base_url('/assets/css/app.css')) ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
</head>
<body class="app-body" data-effects="on">
<div class="page-glow" aria-hidden="true"></div>
<div class="overlay" id="sidebarOverlay"></div>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img class="brand-logo" src="<?= e(base_url('/assets/img/logo.svg')) ?>" width="42" height="42" alt="ProProfessor AI">
      <div>
        <div class="brand-name">ProProfessor AI</div>
        <div class="brand-sub"><?= e(\App\Services\NavService::roleLabel($role)) ?></div>
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
            <a class="nav-link <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e(base_url($item['href'])) ?>">
              <span class="nav-ico"><?= icon($item['icon'] ?? 'spark') ?></span>
              <span><?= e($item['label']) ?></span>
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
      <a class="nav-link" href="<?= e(base_url('/logout')) ?>"><span class="nav-ico"><?= icon('logout') ?></span> Logout</a>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="icon-btn" id="menuToggle" type="button" aria-label="Menu" aria-controls="sidebar" aria-expanded="false"><?= icon('menu') ?></button>
      <div class="topbar-title">
        <h1><?= e($title) ?></h1>
        <p><?= e($opts['subtitle'] ?? '') ?></p>
      </div>
      <div class="topbar-actions">
        <?php if (!empty($opts['actions'])): ?>
          <div class="topbar-page-actions"><?= $opts['actions'] ?></div>
        <?php endif; ?>
        <span class="proto-badge">Interactive Prototype v1.0</span>
        <a class="icon-btn" href="<?= e(base_url($notif)) ?>"><?= icon('bell') ?><?php if ($unread): ?><span class="dot"><?= (int)$unread ?></span><?php endif; ?></a>
      </div>
    </header>
    <main class="content" id="pageContent">
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> reveal"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
<?php
}

function render_footer(array $opts = []): void
{
    ?>
    </main>
  </div>
</div>
<script src="<?= e(base_url('/assets/js/app.js')) ?>"></script>
<?= $opts['scripts'] ?? '' ?>
</body>
</html>
<?php
}

function role_nav(string $role): array
{
    if (!class_exists(\App\Services\NavService::class)) {
        require_once dirname(__DIR__) . '/app/Services/NavService.php';
    }
    return \App\Services\NavService::forRole($role);
}
