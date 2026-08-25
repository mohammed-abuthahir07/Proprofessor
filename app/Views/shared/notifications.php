<?php
/** @var array $rows */
/** @var string $rolePrefix */
/** @var string|null $typeFilter */
/** @var string|null $priorityFilter */
/** @var array $providers */
/** @var string $digestMode */
/** @var array|null $digestPreview */
$typeFilter = $typeFilter ?? null;
$priorityFilter = $priorityFilter ?? null;
$providers = $providers ?? \NotificationService::allProviderStatuses();
$digestMode = $digestMode ?? 'immediate';
$digestPreview = $digestPreview ?? null;
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
  <div class="chip-row" style="margin-top:.55rem">
    <span class="muted" style="font-size:.85rem;margin-right:.25rem">Priority:</span>
    <a class="chip" href="?<?= e(http_build_query(array_filter(['type' => $typeFilter ?: null]))) ?>">All</a>
    <a class="chip" href="?<?= e(http_build_query(array_filter(['type' => $typeFilter ?: null, 'priority' => 'high']))) ?>">High</a>
    <a class="chip" href="?<?= e(http_build_query(array_filter(['type' => $typeFilter ?: null, 'priority' => 'medium']))) ?>">Medium</a>
    <a class="chip" href="?<?= e(http_build_query(array_filter(['type' => $typeFilter ?: null, 'priority' => 'low']))) ?>">Low</a>
  </div>
</div>

<div class="panel" style="margin-top:1rem">
  <strong>Delivery channels</strong>
  <div class="chip-row" style="margin-top:.45rem">
    <?php foreach ($providers as $ch => $st): ?>
      <span class="chip" title="<?= e($st['detail']) ?>"><?= e(ucfirst(str_replace('_', '-', $ch))) ?>: <?= e($st['label']) ?></span>
    <?php endforeach; ?>
  </div>
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
  <ul style="margin:.55rem 0 0;padding-left:1.1rem">
    <?php foreach ($digestPreview['lines'] as $line): ?>
      <li><?= e($line) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:1rem">
  <?php if (!$rows): ?><div class="empty">No notifications.</div><?php else: ?>
  <?php foreach ($rows as $n):
    $prio = strtolower((string)($n['priority'] ?? 'medium'));
    $hasAction = !empty($n['action_type']) || !empty($n['action_url']);
    $btnLabel = \NotificationService::actionLabel($n['action_type'] ?? null, !empty($n['action_url']) ? 'Open' : null);
  ?>
    <div class="notif-item" style="opacity:<?= $n['is_read'] ? '.65' : '1' ?>">
      <div class="notif-row">
        <div class="notif-body">
          <div style="font-size:.78rem;margin-bottom:.2rem">
            <?= e(\NotificationService::priorityEmoji($prio)) ?>
            <strong><?= e(\NotificationService::priorityLabel($prio)) ?></strong>
          </div>
          <strong><?= e($n['title']) ?></strong>
          <div class="notif-text" style="white-space:pre-wrap"><?= e((string)$n['body']) ?></div>
          <div class="chip-row" style="margin-top:.35rem">
            <span class="chip"><?= e($n['type']) ?></span>
            <span class="chip"><?= e($n['created_at']) ?></span>
          </div>
        </div>
        <div class="notif-actions">
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
