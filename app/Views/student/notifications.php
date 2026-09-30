<?php
/** @var list<array<string,mixed>> $rows */
$rows = $rows ?? [];
$board = strtolower((string)get('board', 'all'));
if (!in_array($board, ['all', 'important', 'academic', 'events'], true)) {
    $board = 'all';
}

$metaOf = static function (array $n): array {
    $meta = json_decode((string)($n['meta'] ?? ''), true);
    return is_array($meta) ? $meta : [];
};

$hayOf = static function (array $n) use ($metaOf): string {
    $meta = $metaOf($n);
    return strtolower(
        (string)($n['type'] ?? '') . ' '
        . (string)($meta['category'] ?? '') . ' '
        . (string)($n['title'] ?? '') . ' '
        . (string)($n['body'] ?? '') . ' '
        . (string)($n['action_type'] ?? '')
    );
};

$boardOf = static function (array $n) use ($metaOf, $hayOf): string {
    $notice = strtoupper((string)($metaOf($n)['notice_type'] ?? ''));
    if ($notice === 'IMPORTANT') {
        return 'important';
    }
    if ($notice === 'ACADEMIC') {
        return 'academic';
    }
    if ($notice === 'EVENT') {
        return 'events';
    }
    if (strtolower((string)($n['priority'] ?? '')) === 'high') {
        return 'important';
    }
    $hay = $hayOf($n);
    if (preg_match('/event|fest|cultural|celebrat|holiday|registration|meeting/', $hay)) {
        return 'events';
    }
    if (preg_match('/exam|mark|assignment|academic|result|attendance|schedule|syllabus|deadline|lab|submission|timetable/', $hay)) {
        return 'academic';
    }
    return 'other';
};

$badgeOf = static function (array $n, string $bucket) use ($metaOf, $hayOf): array {
    $notice = strtoupper((string)($metaOf($n)['notice_type'] ?? ''));
    if ($notice === 'IMPORTANT' || $bucket === 'important') {
        return ['Important', 'is-important'];
    }
    if ($notice === 'EVENT' || $bucket === 'events') {
        return ['Event', 'is-event'];
    }
    $hay = $hayOf($n);
    if (preg_match('/achievement|rank holder|congratulat/', $hay)) {
        return ['Achievement', 'is-achievement'];
    }
    if (preg_match('/reminder|deadline|submission|due today/', $hay)) {
        return ['Reminder', 'is-reminder'];
    }
    if ($notice === 'ACADEMIC' || $bucket === 'academic') {
        return ['Academic', 'is-academic'];
    }
    return ['Notice', 'is-notice'];
};

$iconOf = static function (array $n, string $bucket) use ($hayOf): array {
    $hay = $hayOf($n);
    if (preg_match('/fee|payment|fine/', $hay)) {
        return ['finance', 'is-warn'];
    }
    if (preg_match('/achievement|rank holder/', $hay)) {
        return ['check', 'is-ok'];
    }
    if (preg_match('/warning|shortage/', $hay)) {
        return ['alert', 'is-warn'];
    }
    if ($bucket === 'events' || preg_match('/event|fest|holiday/', $hay)) {
        return ['spark', 'is-info'];
    }
    if (preg_match('/exam|schedule|timetable/', $hay)) {
        return ['calendar', 'is-brand'];
    }
    if (preg_match('/assignment|submission|deadline|lab/', $hay)) {
        return ['clock', 'is-info'];
    }
    if ($bucket === 'academic' || preg_match('/mark|result|academic/', $hay)) {
        return ['book', 'is-brand'];
    }
    if ($bucket === 'important') {
        return ['alert', 'is-warn'];
    }
    return ['bell', 'is-brand'];
};

$when = static function (string $ts): string {
    try {
        $tz = new DateTimeZone('Asia/Kolkata');
        $dt = new DateTime($ts, $tz);
        $now = new DateTime('now', $tz);
    } catch (Throwable) {
        return $ts;
    }
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int)floor($diff / 60);
        return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($dt->format('Y-m-d') === $now->format('Y-m-d')) {
        $h = (int)floor($diff / 3600);
        return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }
    if ($dt->format('Y-m-d') === (clone $now)->modify('-1 day')->format('Y-m-d')) {
        return 'Yesterday';
    }
    if ($diff < 86400 * 7) {
        $d = (int)floor($diff / 86400);
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    return $dt->format('M j, Y');
};

$prepared = [];
$counts = ['all' => 0, 'important' => 0, 'academic' => 0, 'events' => 0];
foreach ($rows as $n) {
    $bucket = $boardOf($n);
    $n['_bucket'] = $bucket;
    $counts['all']++;
    if (isset($counts[$bucket])) {
        $counts[$bucket]++;
    }
    $prepared[] = $n;
}
$visible = array_values(array_filter(
    $prepared,
    static fn(array $n): bool => $board === 'all' || $n['_bucket'] === $board
));
$studentId = (int)(Auth::user()['id'] ?? 0);
$unreadTotal = $studentId > 0 ? unread_notifications_count($studentId) : 0;
$q = static function (array $extra = []) use ($board): string {
    $params = ['board' => $board === 'all' ? null : $board];
    foreach ($extra as $key => $value) {
        $params[$key] = $value;
    }
    $params = array_filter($params, static fn($value): bool => $value !== null && $value !== '');
    $query = http_build_query($params);
    return $query === '' ? '?' : '?' . $query;
};
?>
<div class="stu-notes">
  <section class="hod-hero">
    <div class="hod-hero-copy">
      <h2><?= icon('bell', 'icon-inline') ?> Notices</h2>
      <p>Official communications from institution · <strong><?= (int)$unreadTotal ?> unread</strong></p>
    </div>
    <a class="btn btn-sm btn-ghost" href="?read=all">Mark all read</a>
  </section>

  <div class="stu-notes-filters" role="navigation" aria-label="Notice categories">
    <?php foreach (['all' => 'All', 'important' => 'Important', 'academic' => 'Academic', 'events' => 'Events'] as $key => $label): ?>
      <a class="stu-notes-tab<?= $board === $key ? ' is-on' : '' ?>" href="<?= e($q(['board' => $key === 'all' ? null : $key])) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$visible): ?>
    <section class="hod-panel stu-notes-empty">
      <h2>No notices found</h2>
      <p>There are no notices available in this category.</p>
    </section>
  <?php else: ?>
    <?php foreach ($visible as $n):
      $meta = $metaOf($n);
      $bucket = (string)$n['_bucket'];
      [$badge, $badgeTone] = $badgeOf($n, $bucket);
      [$iconName, $iconTone] = $iconOf($n, $bucket);
      $sender = trim((string)($meta['sender_name'] ?? ''));
      $rawBody = trim((string)($n['body'] ?? ''));
      if ($sender === '' && preg_match('/(?:^|\n)From:\s*(.+)$/m', $rawBody, $fromMatch)) {
          $sender = trim((string)$fromMatch[1]);
      }
      $displayBody = $rawBody;
      if (preg_match('/\n\nFrom:\s*.+$/s', $displayBody, $tail) && $sender !== '') {
          $displayBody = trim(substr($displayBody, 0, -strlen($tail[0])));
      }
      $isLong = mb_strlen($displayBody) > 220;
      $preview = $isLong ? rtrim(mb_substr($displayBody, 0, 220)) . '…' : $displayBody;
      $unread = empty($n['is_read']);
      $hasAction = !empty($n['action_type']) || !empty($n['action_url']);
      $btnLabel = \NotificationService::actionLabel($n['action_type'] ?? null, !empty($n['action_url']) ? 'Open' : null);
      $attachment = null;
      if (in_array(($meta['kind'] ?? ''), ['admin_hod_message', 'admin_audience_message'], true) && !empty($meta['announcement_id']) && !empty($meta['has_attachment'])) {
          $attachment = [
              'href' => base_url('/api/messages/attachment?source=admin_hod&id=' . (int)$meta['announcement_id']),
              'name' => (string)($meta['attachment_original_name'] ?? 'attachment'),
          ];
      } elseif (($meta['kind'] ?? '') === 'professor_student_message' && !empty($meta['announcement_id']) && !empty($meta['has_attachment'])) {
          $attachment = [
              'href' => base_url('/api/messages/attachment?id=' . (int)$meta['announcement_id']),
              'name' => (string)($meta['attachment_original_name'] ?? 'attachment'),
          ];
      }
    ?>
      <article class="stu-notes-card<?= $unread ? ' is-unread' : '' ?>">
        <span class="stu-notes-ico <?= e($iconTone) ?>"><?= icon($iconName) ?></span>
        <div class="stu-notes-copy">
          <div class="stu-notes-title">
            <?php if ($unread): ?><span class="stu-notes-dot" aria-label="Unread"></span><?php endif; ?>
            <strong><?= e((string)$n['title']) ?></strong>
          </div>
          <div class="stu-notes-meta">
            <?php if ($sender !== ''): ?><span>From: <?= e($sender) ?></span><?php endif; ?>
            <span><?= e($when((string)($n['created_at'] ?? ''))) ?></span>
          </div>
          <?php if ($preview !== ''): ?>
            <p><?= e($preview) ?></p>
          <?php endif; ?>
          <?php if ($isLong): ?>
            <details class="stu-notes-more">
              <summary>Full notice</summary>
              <p><?= e($displayBody) ?></p>
            </details>
          <?php endif; ?>
          <?php if ($attachment): ?>
            <?php $ext = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION)); ?>
            <div class="stu-notes-file">
              <?= e($attachment['name']) ?>
              <a class="btn btn-sm btn-ghost" href="<?= e($attachment['href']) ?>">Download<?= $ext === 'pdf' ? ' PDF' : ($ext === 'docx' ? ' DOCX' : '') ?></a>
            </div>
          <?php endif; ?>
          <div class="stu-notes-actions">
            <?php if ($hasAction): ?>
              <a class="btn btn-sm btn-primary" href="<?= e($q(['go' => (int)$n['id']])) ?>"><?= e($btnLabel) ?></a>
            <?php endif; ?>
            <?php if ($unread): ?>
              <a class="btn btn-sm btn-ghost" href="<?= e($q(['read_id' => (int)$n['id']])) ?>">Read</a>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Delete this notice from your account?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_notification">
              <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
              <button class="btn btn-sm btn-ghost stu-notes-delete" type="submit">Delete</button>
            </form>
          </div>
        </div>
        <span class="stu-notes-badge <?= e($badgeTone) ?>"><?= e($badge) ?></span>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
