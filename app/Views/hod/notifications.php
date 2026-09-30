<?php
/** @var list<array<string,mixed>> $rows */
/** @var string|null $priorityFilter */
$rows = $rows ?? [];
$priorityFilter = $priorityFilter ?? null;
$group = (string)get('group', 'all');
if (!in_array($group, ['all', 'approvals', 'compliance', 'faculty'], true)) {
    $group = 'all';
}

$classify = static function (array $n): string {
    $meta = json_decode((string)($n['meta'] ?? ''), true);
    $meta = is_array($meta) ? $meta : [];
    $type = strtolower((string)($n['type'] ?? ''));
    $cat = strtolower((string)($meta['category'] ?? ''));
    $hay = strtolower($type . ' ' . $cat . ' ' . (string)($n['title'] ?? '') . ' ' . (string)($n['body'] ?? '') . ' ' . (string)($n['action_type'] ?? ''));
    if (preg_match('/compliance|naac|nba|accreditation|evidence/', $hay)) {
        return 'compliance';
    }
    if (preg_match('/reminder|not submitted|deadline reminder|faculty action/', $hay)) {
        return 'faculty';
    }
    if (
        $type === 'approval'
        || $cat === 'approvals'
        || str_contains($hay, 'approv')
        || str_contains($hay, 'submitted')
        || str_contains($hay, 'returned')
        || str_contains($hay, 'revision')
        || str_contains($hay, 'course plan')
    ) {
        return 'approvals';
    }
    if (str_contains($hay, 'faculty') || str_contains($hay, 'professor')) {
        return 'faculty';
    }
    return 'other';
};

$when = static function (string $ts): string {
    try {
        $tz = new DateTimeZone('Asia/Kolkata');
        $dt = new DateTime($ts, $tz);
    } catch (Throwable) {
        return $ts;
    }
    $now = new DateTime('now', $tz);
    $time = $dt->format('g:i A');
    if ($dt->format('Y-m-d') === $now->format('Y-m-d')) {
        return 'Today, ' . $time;
    }
    if ($dt->format('Y-m-d') === (clone $now)->modify('-1 day')->format('Y-m-d')) {
        return 'Yesterday, ' . $time;
    }
    return $dt->format('d M Y, g:i A');
};

$toneFor = static function (array $n, string $bucket): array {
    $hay = strtolower((string)($n['title'] ?? '') . ' ' . (string)($n['body'] ?? '') . ' ' . (string)($n['action_type'] ?? ''));
    if ($bucket === 'compliance') {
        return ['alert', 'is-warn'];
    }
    if ($bucket === 'faculty') {
        return ['users', 'is-info'];
    }
    if (str_contains($hay, 'approved') || str_contains($hay, 'returned') || str_contains($hay, 'revision')) {
        return ['check', 'is-ok'];
    }
    return ['file', 'is-brand'];
};

$visible = [];
foreach ($rows as $n) {
    $bucket = $classify($n);
    $n['_bucket'] = $bucket;
    if ($group !== 'all' && $bucket !== $group) {
        continue;
    }
    $visible[] = $n;
}
$unread = [];
$earlier = [];
foreach ($visible as $n) {
    if ((int)($n['is_read'] ?? 0) === 1) {
        $earlier[] = $n;
    } else {
        $unread[] = $n;
    }
}
$unreadAll = 0;
foreach ($rows as $n) {
    if ((int)($n['is_read'] ?? 0) !== 1) {
        $unreadAll++;
    }
}

$q = static function (array $extra = []) use ($group, $priorityFilter): string {
    $params = array_filter([
        'group' => $group !== 'all' ? $group : null,
        'priority' => $priorityFilter ?: null,
    ], static fn($v) => $v !== null && $v !== '');
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $built = http_build_query($params);
    return $built === '' ? '?' : '?' . $built;
};

$tabs = [
    'all' => 'All',
    'approvals' => 'Approvals',
    'compliance' => 'Compliance',
    'faculty' => 'Faculty',
];
$priorities = [
    '' => 'All',
    'high' => 'High',
    'medium' => 'Medium',
    'low' => 'Low',
];
?>
<div class="hod-notes">
  <section class="hod-panel hod-notes-bar">
    <div class="hod-notes-bar-top">
      <div>
        <div class="hod-notes-tabs" role="tablist" aria-label="Notification categories">
          <?php foreach ($tabs as $key => $label): ?>
            <a class="hod-notes-tab <?= $group === $key ? 'is-on' : '' ?>" href="<?= e($q(['group' => $key === 'all' ? null : $key, 'priority' => $priorityFilter])) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
        <p class="hod-notes-count"><?= (int)$unreadAll === 1 ? '1 unread' : ((int)$unreadAll . ' unread') ?></p>
      </div>
      <?php if ($unreadAll > 0): ?>
        <a class="btn btn-sm btn-ghost" href="?read=all">Mark all read</a>
      <?php endif; ?>
    </div>
    <div class="hod-notes-priority" aria-label="Priority">
      <span>Priority</span>
      <?php foreach ($priorities as $key => $label): ?>
        <a class="hod-notes-tab hod-notes-tab-sm <?= ($priorityFilter ?: '') === $key ? 'is-on' : '' ?>" href="<?= e($q(['priority' => $key === '' ? null : $key])) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if (!$visible): ?>
    <section class="hod-panel hod-notes-empty">
      <h2>No notifications</h2>
      <p><?= $group === 'all' && !$priorityFilter ? "You're all caught up." : 'Nothing in this view.' ?></p>
    </section>
  <?php else: ?>
    <section class="hod-notes-group" aria-label="Unread notifications">
      <h2>Unread (<?= count($unread) ?>)</h2>
      <?php if (!$unread): ?>
        <p class="hod-notes-quiet">No unread notifications in this view.</p>
      <?php endif; ?>
      <?php foreach ($unread as $n): ?>
        <?php
          $bucket = (string)$n['_bucket'];
          [$iconName, $tone] = $toneFor($n, $bucket);
          $prio = strtolower((string)($n['priority'] ?? 'medium'));
          $hasAction = !empty($n['action_type']) || !empty($n['action_url']);
          $btnLabel = NotificationService::actionLabel($n['action_type'] ?? null, !empty($n['action_url']) ? 'Open' : null);
          $noticeLabel = AdminHodMessageTools::noticeTypeLabel((string)((json_decode((string)($n['meta'] ?? ''), true) ?: [])['notice_type'] ?? ''));
        ?>
        <article class="hod-notes-card is-unread">
          <span class="hod-notes-ico <?= e($tone) ?>"><?= icon($iconName) ?></span>
          <div class="hod-notes-copy">
            <div class="hod-notes-title">
              <strong><?= e((string)$n['title']) ?></strong>
              <span class="hod-notes-new">New</span>
              <span class="hod-notes-prio is-<?= e($prio) ?>"><?= e(NotificationService::priorityLabel($prio)) ?></span>
            </div>
            <?php if (trim((string)($n['body'] ?? '')) !== ''): ?>
              <p><?= e((string)$n['body']) ?></p>
            <?php endif; ?>
            <div class="hod-notes-meta">
              <?php if ($noticeLabel !== ''): ?><span><?= e($noticeLabel) ?></span><?php endif; ?>
              <span><?= e(ucfirst($bucket === 'other' ? (string)$n['type'] : $bucket)) ?></span>
              <span><?= e($when((string)$n['created_at'])) ?></span>
            </div>
          </div>
          <div class="hod-notes-actions">
            <?php if ($hasAction): ?>
              <a class="btn btn-sm btn-primary" href="<?= e($q(['read_id' => null, 'go' => (int)$n['id']])) ?>"><?= e($btnLabel) ?></a>
            <?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="<?= e($q(['read_id' => (int)$n['id']])) ?>">Read</a>
            <form method="post" onsubmit="return confirm('Delete this notification from your account?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_notification">
              <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
              <button class="btn btn-sm btn-ghost hod-notes-delete" type="submit">Delete</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="hod-notes-group" aria-label="Earlier notifications">
      <h2>Earlier (<?= count($earlier) ?>)</h2>
      <?php if (!$earlier): ?>
        <p class="hod-notes-quiet">No earlier notifications in this view.</p>
      <?php endif; ?>
      <?php foreach ($earlier as $n): ?>
        <?php
          $bucket = (string)$n['_bucket'];
          [$iconName, $tone] = $toneFor($n, $bucket);
          $prio = strtolower((string)($n['priority'] ?? 'medium'));
          $hasAction = !empty($n['action_type']) || !empty($n['action_url']);
          $btnLabel = NotificationService::actionLabel($n['action_type'] ?? null, !empty($n['action_url']) ? 'Open' : null);
          $noticeLabel = AdminHodMessageTools::noticeTypeLabel((string)((json_decode((string)($n['meta'] ?? ''), true) ?: [])['notice_type'] ?? ''));
        ?>
        <article class="hod-notes-card">
          <span class="hod-notes-ico <?= e($tone) ?>"><?= icon($iconName) ?></span>
          <div class="hod-notes-copy">
            <div class="hod-notes-title">
              <strong><?= e((string)$n['title']) ?></strong>
              <span class="hod-notes-prio is-<?= e($prio) ?>"><?= e(NotificationService::priorityLabel($prio)) ?></span>
            </div>
            <?php if (trim((string)($n['body'] ?? '')) !== ''): ?>
              <p><?= e((string)$n['body']) ?></p>
            <?php endif; ?>
            <div class="hod-notes-meta">
              <?php if ($noticeLabel !== ''): ?><span><?= e($noticeLabel) ?></span><?php endif; ?>
              <span><?= e(ucfirst($bucket === 'other' ? (string)$n['type'] : $bucket)) ?></span>
              <span><?= e($when((string)$n['created_at'])) ?></span>
            </div>
          </div>
          <div class="hod-notes-actions">
            <?php if ($hasAction): ?>
              <a class="btn btn-sm btn-ghost" href="<?= e($q(['go' => (int)$n['id']])) ?>"><?= e($btnLabel) ?></a>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Delete this notification from your account?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_notification">
              <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
              <button class="btn btn-sm btn-ghost hod-notes-delete" type="submit">Delete</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>
