<?php
/** @var array $rows */
/** @var string $rolePrefix */
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
    <div class="notif-item" style="opacity:<?= $n['is_read'] ? '.65' : '1' ?>">
      <div class="notif-row">
        <div class="notif-body">
          <strong><?= e($n['title']) ?></strong>
          <div class="notif-text"><?= e((string)$n['body']) ?></div>
          <div class="chip-row" style="margin-top:.35rem">
            <span class="chip"><?= e($n['type']) ?></span>
            <span class="chip"><?= e($n['created_at']) ?></span>
          </div>
        </div>
        <div class="notif-actions">
          <?php if ($n['action_url']): ?>
            <a class="btn btn-sm btn-primary" href="<?= e(url($n['action_url'])) ?>">Open</a>
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
