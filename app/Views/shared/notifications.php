<?php
/** @var array $rows */
/** @var string $rolePrefix */
/** @var string|null $typeFilter */
/** @var string|null $priorityFilter */
/** @var array $providers */
/** @var string $digestMode */
/** @var array|null $digestPreview */
/** @var bool $canMessageHods */
/** @var int $hodRecipientCount */
/** @var array $hodSentHistory */
$typeFilter = $typeFilter ?? null;
$priorityFilter = $priorityFilter ?? null;
$providers = $providers ?? \NotificationService::allProviderStatuses();
$digestMode = $digestMode ?? 'immediate';
$digestPreview = $digestPreview ?? null;
$canMessageHods = !empty($canMessageHods);
$hodRecipientCount = (int)($hodRecipientCount ?? 0);
$hodSentHistory = $hodSentHistory ?? [];
?>
<?php if ($canMessageHods): ?>
<div class="grid grid-2" style="margin-bottom:1rem;align-items:start">
  <div class="panel">
    <div class="panel-h">
      <h2 style="margin:0;font-size:1.05rem">Message all HODs</h2>
      <span class="chip"><?= (int)$hodRecipientCount ?> HOD<?= $hodRecipientCount === 1 ? '' : 's' ?></span>
    </div>
    <p class="muted" style="font-size:.85rem;margin:.35rem 0 .85rem">Send a message (and optional PDF/DOCX) to every active HOD in this institution.</p>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send_hod_message">
      <div class="form-row">
        <label for="hod_title">Title <span class="muted">(optional)</span></label>
        <input type="text" name="title" id="hod_title" maxlength="200" placeholder="Message from College Admin" <?= $hodRecipientCount < 1 ? 'disabled' : '' ?>>
      </div>
      <div class="form-row">
        <label for="hod_message">Message</label>
        <textarea name="message" id="hod_message" rows="5" maxlength="4000" required placeholder="Write your message to all HODs…" <?= $hodRecipientCount < 1 ? 'disabled' : '' ?>></textarea>
        <div class="muted" style="font-size:.8rem;margin-top:.25rem">Max 4000 characters.</div>
      </div>
      <div class="form-row">
        <label for="hod_attachment">Attachment <span class="muted">(optional)</span></label>
        <input type="file" name="attachment" id="hod_attachment" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" <?= $hodRecipientCount < 1 ? 'disabled' : '' ?>>
        <div class="muted" style="font-size:.8rem;margin-top:.25rem">Supported: PDF, DOCX · Max 10 MB.</div>
      </div>
      <button class="btn btn-primary" type="submit" <?= $hodRecipientCount < 1 ? 'disabled' : '' ?>>Send to all HODs</button>
    </form>
  </div>
  <div class="panel" style="max-height:min(70vh, 640px);overflow:auto">
    <div class="panel-h"><strong>Sent to HODs</strong></div>
    <?php if (!$hodSentHistory): ?>
      <div class="empty">No HOD messages sent yet.</div>
    <?php else: ?>
      <?php foreach ($hodSentHistory as $h):
        $hasAtt = trim((string)($h['attachment_path'] ?? '')) !== '';
        $attName = (string)($h['attachment_original_name'] ?? '');
        $attExt = strtolower(pathinfo($attName, PATHINFO_EXTENSION));
      ?>
        <div style="padding:.85rem 0;border-bottom:1px solid var(--line)">
          <div style="display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start">
            <strong><?= e((string)$h['title']) ?></strong>
            <form method="post" style="margin:0;flex-shrink:0" onsubmit="return confirm('Delete this message for admin and all HODs?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_hod_message">
              <input type="hidden" name="announcement_id" value="<?= (int)$h['id'] ?>">
              <button class="btn btn-sm btn-ghost" type="submit" style="color:#f87171">Delete</button>
            </form>
          </div>
          <div style="white-space:pre-wrap;font-size:.92rem;margin-top:.25rem"><?= e((string)$h['body']) ?></div>
          <?php if ($hasAtt && $attName !== ''): ?>
            <div style="margin-top:.4rem;font-size:.88rem">
              📄 <?= e($attName) ?>
              <a class="btn btn-sm btn-ghost" style="margin-left:.25rem" href="<?= e(base_url('/api/messages/attachment?source=admin_hod&id=' . (int)$h['id'])) ?>">Download<?= $attExt === 'pdf' ? ' PDF' : ($attExt === 'docx' ? ' DOCX' : '') ?></a>
            </div>
          <?php endif; ?>
          <div class="chip-row" style="margin-top:.35rem">
            <span class="chip">Recipients: <?= (int)$h['recipient_count'] ?></span>
            <span class="chip"><?= e((string)$h['created_at']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!$canMessageHods): ?>
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
    $nmeta = json_decode((string)($n['meta'] ?? ''), true) ?: [];
    $hasAction = !empty($n['action_type']) || !empty($n['action_url']);
    $btnLabel = \NotificationService::actionLabel($n['action_type'] ?? null, !empty($n['action_url']) ? 'Open' : null);
    $adminHodAtt = null;
    if (($nmeta['kind'] ?? '') === 'admin_hod_message' && !empty($nmeta['announcement_id']) && !empty($nmeta['has_attachment'])) {
        $adminHodAtt = [
            'id' => (int)$nmeta['announcement_id'],
            'name' => (string)($nmeta['attachment_original_name'] ?? 'attachment'),
        ];
    }
    $profStuAtt = null;
    if (($nmeta['kind'] ?? '') === 'professor_student_message' && !empty($nmeta['announcement_id']) && !empty($nmeta['has_attachment'])) {
        $profStuAtt = [
            'id' => (int)$nmeta['announcement_id'],
            'name' => (string)($nmeta['attachment_original_name'] ?? 'attachment'),
        ];
    }
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
          <?php if ($adminHodAtt): ?>
            <?php $ext = strtolower(pathinfo($adminHodAtt['name'], PATHINFO_EXTENSION)); ?>
            <div style="margin-top:.45rem;font-size:.88rem">
              <strong>Attachment:</strong><br>
              📄 <?= e($adminHodAtt['name']) ?>
              <a class="btn btn-sm btn-ghost" style="margin-left:.25rem;margin-top:.25rem" href="<?= e(base_url('/api/messages/attachment?source=admin_hod&id=' . $adminHodAtt['id'])) ?>">Download<?= $ext === 'pdf' ? ' PDF' : ($ext === 'docx' ? ' DOCX' : '') ?></a>
            </div>
          <?php elseif ($profStuAtt): ?>
            <?php $ext = strtolower(pathinfo($profStuAtt['name'], PATHINFO_EXTENSION)); ?>
            <div style="margin-top:.45rem;font-size:.88rem">
              <strong>Attachment:</strong><br>
              📄 <?= e($profStuAtt['name']) ?>
              <a class="btn btn-sm btn-ghost" style="margin-left:.25rem;margin-top:.25rem" href="<?= e(base_url('/api/messages/attachment?id=' . $profStuAtt['id'])) ?>">Download<?= $ext === 'pdf' ? ' PDF' : ($ext === 'docx' ? ' DOCX' : '') ?></a>
            </div>
          <?php endif; ?>
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
<?php endif; ?>
