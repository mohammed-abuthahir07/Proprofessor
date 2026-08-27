<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireLogin();
$user = Auth::user();
$role = $user['role'];
$base = match ($role) {
    'student' => 'student',
    'hod' => 'hod',
    'admin', 'superadmin' => 'admin',
    default => 'professor',
};

NotificationService::ensureSchema();

// Safe action open — never trust client-supplied record IDs.
if ($goId = (int)get('go')) {
    $resolved = NotificationService::resolveAction($user, $goId);
    if (!$resolved['ok']) {
        flash('error', $resolved['error'] ?? 'Unable to open notification action.');
        redirect("/$base/notifications.php");
    }
    Database::query('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [$goId, $user['id']]);
    redirect($resolved['path']);
}

if (get('read') === 'all') {
    Database::query('UPDATE notifications SET is_read=1 WHERE user_id=?', [$user['id']]);
    redirect("/$base/notifications.php");
}
if ($id = (int)get('read_id')) {
    Database::query('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [$id, $user['id']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'generate_digest') {
    verify_csrf();
    $mode = (string)post('digest_mode', 'daily');
    NotificationService::publishDigest($user, $mode);
    flash('success', 'Digest summary added to your feed.');
    redirect("/$base/notifications.php");
}

$type = get('type');
$priorityFilter = strtolower((string)get('priority'));
if (!in_array($priorityFilter, ['high', 'medium', 'low'], true)) {
    $priorityFilter = '';
}

$sql = 'SELECT * FROM notifications WHERE user_id=?';
$params = [$user['id']];
if ($type) {
    $sql .= ' AND type=?';
    $params[] = $type;
}
if ($priorityFilter !== '') {
    $sql .= ' AND priority=?';
    $params[] = $priorityFilter;
}
$sql .= ' ORDER BY created_at DESC LIMIT 100';
$rows = Database::fetchAll($sql, $params);

// Backfill display priority for legacy rows missing column value (default medium / inferred).
foreach ($rows as &$n) {
    if (empty($n['priority'])) {
        $n['priority'] = NotificationService::inferPriority((string)$n['type'], (string)$n['title'], (string)($n['body'] ?? ''));
    }
}
unset($n);

$prefs = NotificationService::preferencesFromUser($user);
$providers = NotificationService::allProviderStatuses();
$digestMode = (string)($prefs['digest_mode'] ?? 'immediate');
$digestPreview = null;
if ($digestMode === 'daily' || $digestMode === 'weekly') {
    $digestPreview = NotificationService::buildDigest($user, $digestMode);
} elseif (get('digest') === 'daily' || get('digest') === 'weekly') {
    $digestPreview = NotificationService::buildDigest($user, (string)get('digest'));
}

render_header('Notifications', 'notifications', ['subtitle' => 'Approvals, AI completions & system events']);
?>
<div class="panel">
  <div class="panel-h">
    <div class="chip-row">
      <a class="chip" href="?">All</a>
      <a class="chip" href="?type=approval">Approvals</a>
      <a class="chip" href="?type=system">System</a>
      <a class="chip" href="?type=ai">AI</a>
      <a class="chip" href="?type=announcement">Messages</a>
    </div>
    <a class="btn btn-sm btn-ghost" href="?read=all">Mark all read</a>
  </div>
  <div class="chip-row" style="margin-top:.55rem">
    <span class="muted" style="font-size:.85rem;margin-right:.25rem">Priority:</span>
    <a class="chip" href="?<?= e(http_build_query(array_filter(['type' => $type ?: null]))) ?>">All</a>
    <a class="chip" href="?<?= e(http_build_query(array_filter(['type' => $type ?: null, 'priority' => 'high']))) ?>">High</a>
    <a class="chip" href="?<?= e(http_build_query(array_filter(['type' => $type ?: null, 'priority' => 'medium']))) ?>">Medium</a>
    <a class="chip" href="?<?= e(http_build_query(array_filter(['type' => $type ?: null, 'priority' => 'low']))) ?>">Low</a>
  </div>
</div>

<div class="panel" style="margin-top:1rem">
  <strong>Delivery channels</strong>
  <div class="chip-row" style="margin-top:.45rem">
    <?php foreach ($providers as $ch => $st): ?>
      <span class="chip" title="<?= e($st['detail']) ?>"><?= e(ucfirst(str_replace('_', '-', $ch))) ?>: <?= e($st['label']) ?></span>
    <?php endforeach; ?>
  </div>
  <div class="muted" style="margin-top:.4rem;font-size:.82rem">WhatsApp/SMS stay “Not configured” until real provider credentials are set in server config. Preferences: Settings.</div>
</div>

<?php if ($digestPreview): ?>
<div class="panel" style="margin-top:1rem">
  <div class="panel-h">
    <strong><?= e($digestPreview['title']) ?></strong>
    <form method="post" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="generate_digest">
      <input type="hidden" name="digest_mode" value="<?= e($digestMode === 'immediate' ? 'daily' : $digestMode) ?>">
      <button class="btn btn-sm btn-ghost" type="submit">Add digest to feed</button>
    </form>
  </div>
  <div class="muted" style="margin-top:.25rem"><?= e($digestPreview['period']) ?> · based on your <?= (int)$digestPreview['count'] ?> notification(s)</div>
  <ul style="margin:.55rem 0 0;padding-left:1.1rem">
    <?php foreach ($digestPreview['lines'] as $line): ?>
      <li><?= e($line) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php else: ?>
<div class="panel" style="margin-top:1rem">
  <div class="panel-h">
    <strong>Digest</strong>
    <div class="chip-row">
      <a class="chip" href="?digest=daily">Preview daily</a>
      <a class="chip" href="?digest=weekly">Preview weekly</a>
    </div>
  </div>
  <div class="muted" style="font-size:.85rem">Mode: <?= e(ucfirst($digestMode)) ?> (change in Settings). Individual notifications always remain in the feed below.</div>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:1rem">
  <?php if (!$rows): ?><div class="empty">No notifications.</div><?php else: ?>
  <?php foreach ($rows as $n):
    $prio = strtolower((string)($n['priority'] ?? 'medium'));
    $delivery = json_decode((string)($n['delivery_status'] ?? ''), true) ?: [];
    $nmeta = json_decode((string)($n['meta'] ?? ''), true) ?: [];
    $hasAction = !empty($n['action_type']) || !empty($n['action_url']);
    $btnLabel = NotificationService::actionLabel(
        $n['action_type'] ?? null,
        !empty($n['action_url']) ? 'Open' : null
    );
    $msgAttachment = null;
    if (($nmeta['kind'] ?? '') === 'professor_student_message' && !empty($nmeta['announcement_id']) && !empty($nmeta['has_attachment'])) {
        $msgAttachment = [
            'announcement_id' => (int)$nmeta['announcement_id'],
            'name' => (string)($nmeta['attachment_original_name'] ?? 'attachment'),
        ];
    }
  ?>
    <div style="padding:.9rem 0;border-bottom:1px solid var(--line);opacity:<?= $n['is_read'] ? '.65' : '1' ?>">
      <div style="display:flex;justify-content:space-between;gap:1rem">
        <div>
          <div style="font-size:.78rem;margin-bottom:.2rem">
            <?= e(NotificationService::priorityEmoji($prio)) ?>
            <strong style="letter-spacing:.02em"><?= e(NotificationService::priorityLabel($prio)) ?></strong>
          </div>
          <strong><?= e($n['title']) ?></strong>
          <div style="color:var(--muted);font-size:.88rem;white-space:pre-wrap"><?= e((string)$n['body']) ?></div>
          <?php if ($msgAttachment): ?>
            <?php
              $attachExt = strtolower(pathinfo($msgAttachment['name'], PATHINFO_EXTENSION));
              $dlLabel = $attachExt === 'pdf' ? 'Download PDF' : ($attachExt === 'docx' ? 'Download DOCX' : 'Download');
            ?>
            <div style="margin-top:.45rem;font-size:.88rem">
              <strong>Attachment:</strong><br>
              📄 <?= e($msgAttachment['name']) ?>
              <a class="btn btn-sm btn-ghost" style="margin-left:.25rem;margin-top:.25rem" href="<?= e(base_url('/api/messages/attachment?id=' . $msgAttachment['announcement_id'])) ?>"><?= e($dlLabel) ?></a>
            </div>
          <?php endif; ?>
          <div class="chip-row" style="margin-top:.35rem">
            <span class="chip"><?= e($n['type']) ?></span>
            <span class="chip"><?= e($n['created_at']) ?></span>
            <?php if ($delivery): ?>
              <?php foreach (['in_app' => 'In-App', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS'] as $k => $lab):
                $st = $delivery[$k]['status'] ?? null;
                if ($st === null) continue;
                $label = match ((string)$st) {
                    'delivered' => 'Delivered',
                    'failed' => 'Failed',
                    'not_configured' => 'Not configured',
                    'disabled' => 'Off',
                    'skipped' => 'Skipped',
                    default => (string)$st,
                };
              ?>
                <span class="chip"><?= e($lab) ?>: <?= e($label) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:.35rem;align-items:flex-end">
          <?php if ($hasAction): ?>
            <a class="btn btn-sm btn-primary" href="?go=<?= (int)$n['id'] ?>"><?= e($btnLabel) ?></a>
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
