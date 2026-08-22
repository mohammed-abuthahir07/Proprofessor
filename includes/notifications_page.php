<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireLogin();
$user = Auth::user();
$role = $user['role'];
$base = match($role) {
    'student' => 'student',
    'hod' => 'hod',
    'admin', 'superadmin' => 'admin',
    default => 'professor',
};

if (get('read') === 'all') {
    Database::query('UPDATE notifications SET is_read=1 WHERE user_id=?', [$user['id']]);
    redirect("/$base/notifications.php");
}
if ($id = (int)get('read_id')) {
    Database::query('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [$id, $user['id']]);
}

$type = get('type');
$sql = 'SELECT * FROM notifications WHERE user_id=?';
$params = [$user['id']];
if ($type) { $sql .= ' AND type=?'; $params[] = $type; }
$sql .= ' ORDER BY created_at DESC LIMIT 100';
$rows = Database::fetchAll($sql, $params);

render_header('Notifications', 'notifications', ['subtitle' => 'Approvals, AI completions & system events']);
?>
<div class="panel">
  <div class="panel-h">
    <div class="chip-row">
      <a class="chip" href="?">All</a>
      <a class="chip" href="?type=approval">Approvals</a>
      <a class="chip" href="?type=system">System</a>
      <a class="chip" href="?type=ai">AI</a>
    </div>
    <a class="btn btn-sm btn-ghost" href="?read=all">Mark all read</a>
  </div>
  <?php if (!$rows): ?><div class="empty">No notifications.</div><?php else: ?>
  <?php foreach ($rows as $n): ?>
    <div style="padding:.9rem 0;border-bottom:1px solid var(--line);opacity:<?= $n['is_read']?'.65':'1' ?>">
      <div style="display:flex;justify-content:space-between;gap:1rem">
        <div>
          <strong><?= e($n['title']) ?></strong>
          <div style="color:var(--muted);font-size:.88rem"><?= e((string)$n['body']) ?></div>
          <div class="chip-row" style="margin-top:.35rem">
            <span class="chip"><?= e($n['type']) ?></span>
            <span class="chip"><?= e($n['created_at']) ?></span>
          </div>
        </div>
        <div>
          <?php if ($n['action_url']): ?>
            <a class="btn btn-sm btn-primary" href="<?= e(base_url($n['action_url'])) ?>">Open</a>
          <?php endif; ?>
          <?php if (!$n['is_read']): ?>
            <a class="btn btn-sm btn-ghost" href="?read_id=<?= (int)$n['id'] ?>">Read</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
