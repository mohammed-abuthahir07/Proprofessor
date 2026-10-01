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
/** @var array<string,string> $audienceOptions */
/** @var array<string,int> $audienceCounts */
$typeFilter = $typeFilter ?? null;
$priorityFilter = $priorityFilter ?? null;
$providers = $providers ?? \NotificationService::allProviderStatuses();
$digestMode = $digestMode ?? 'immediate';
$digestPreview = $digestPreview ?? null;
$canMessageHods = !empty($canMessageHods);
$hodRecipientCount = (int)($hodRecipientCount ?? 0);
$hodSentHistory = $hodSentHistory ?? [];
$audienceOptions = $audienceOptions ?? \AdminHodMessageTools::AUDIENCES;
$audienceCounts = $audienceCounts ?? [];
?>
<?php if ($canMessageHods): ?>
<?php
$annWhen = static function (string $ts): string {
    try {
        $dt = new DateTime($ts, new DateTimeZone('Asia/Kolkata'));
    } catch (Throwable) {
        return $ts;
    }
    $diff = time() - $dt->getTimestamp();
    if ($diff >= 0 && $diff < 60) {
        return 'Just now';
    }
    if ($diff >= 0 && $diff < 3600) {
        $m = (int)floor($diff / 60);
        return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($diff >= 0 && $diff < 86400) {
        $h = (int)floor($diff / 3600);
        return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }
    if ($diff >= 0 && $diff < 86400 * 7) {
        $d = (int)floor($diff / 86400);
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    return $dt->format('M j, Y');
};
?>
<div class="adm-ann">
  <div class="adm-ann-head">
    <div>
      <h2><?= icon('bell', 'icon-inline') ?> Announcements</h2>
      <p>Broadcast notices to students, faculty &amp; staff</p>
    </div>
    <button class="btn btn-primary" type="button" id="admAnnOpen">+ New Announcement</button>
  </div>
  <h3 class="adm-ann-kicker">Recent announcements</h3>
  <?php if (!$hodSentHistory): ?>
    <div class="empty">No announcements sent yet.</div>
  <?php else: ?>
    <div class="adm-ann-list">
      <?php foreach ($hodSentHistory as $h):
        $hasAtt = trim((string)($h['attachment_path'] ?? '')) !== '';
        $attName = (string)($h['attachment_original_name'] ?? '');
        $attExt = strtolower(pathinfo($attName, PATHINFO_EXTENSION));
        $hMeta = json_decode((string)($h['meta'] ?? ''), true) ?: [];
        $noticeCode = strtoupper((string)($hMeta['notice_type'] ?? ''));
        $tone = match ($noticeCode) {
            'IMPORTANT' => 'is-important',
            'ACADEMIC' => 'is-academic',
            'EVENT' => 'is-event',
            default => 'is-general',
        };
        $iconName = match ($noticeCode) {
            'IMPORTANT' => 'alert',
            'ACADEMIC' => 'book',
            'EVENT' => 'spark',
            default => 'bell',
        };
        $targetLabel = \AdminHodMessageTools::audienceLabel((string)($hMeta['audience'] ?? 'ALL_HODS'));
        $noticeLabel = \AdminHodMessageTools::noticeTypeLabel($noticeCode);
      ?>
        <article class="adm-ann-card <?= e($tone) ?>">
          <div class="adm-ann-card-top">
            <span class="adm-ann-ico"><?= icon($iconName) ?></span>
            <div class="adm-ann-copy">
              <div class="adm-ann-title-row">
                <h4><?= e((string)$h['title']) ?></h4>
                <span class="adm-ann-sent">Sent</span>
              </div>
              <p class="adm-ann-meta">To: <?= e($targetLabel) ?> · <?= e($annWhen((string)($h['created_at'] ?? ''))) ?></p>
              <p class="adm-ann-body"><?= e((string)$h['body']) ?></p>
              <?php if ($hasAtt && $attName !== ''): ?>
                <p class="adm-ann-file">
                  <?= e($attName) ?>
                  <a href="<?= e(base_url('/api/messages/attachment?source=admin_hod&id=' . (int)$h['id'])) ?>">Download<?= $attExt === 'pdf' ? ' PDF' : ($attExt === 'docx' ? ' DOCX' : '') ?></a>
                </p>
              <?php endif; ?>
              <div class="adm-ann-chips">
                <?php if ($noticeLabel !== ''): ?><span class="chip"><?= e($noticeLabel) ?></span><?php endif; ?>
                <span class="chip">Target: <?= e($targetLabel) ?></span>
                <span class="chip">Recipients: <?= (int)$h['recipient_count'] ?></span>
              </div>
            </div>
            <form method="post" class="adm-ann-delete" onsubmit="return confirm('Delete this message for the admin and its recipients?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_hod_message">
              <input type="hidden" name="announcement_id" value="<?= (int)$h['id'] ?>">
              <button class="btn btn-sm btn-ghost" type="submit">Delete</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<dialog class="adm-ann-dialog" id="admAnnDialog">
  <div class="adm-ann-dialog-h">
    <div>
      <h2>New announcement</h2>
      <p>Send a message (and optional PDF/DOCX) to the audience you select.</p>
    </div>
    <button class="btn btn-sm btn-ghost" type="button" id="admAnnClose">Close</button>
  </div>
  <span class="chip" id="audienceCount">Choose audience</span>
  <form method="post" enctype="multipart/form-data" class="form-grid" id="audienceForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send_hod_message">
      <div class="form-row">
        <label for="audience">Target audience</label>
        <select name="audience" id="audience" required>
          <option value="">Select target audience</option>
          <?php foreach ($audienceOptions as $code => $label): ?>
            <option value="<?= e($code) ?>" data-count="<?= (int)($audienceCounts[$code] ?? 0) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label for="notice_type">Category</label>
        <select name="notice_type" id="notice_type" required>
          <option value="">Select category</option>
          <?php foreach (\AdminHodMessageTools::NOTICE_TYPES as $code => $label): ?>
            <option value="<?= e($code) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label for="hod_title">Title <span class="muted">(optional)</span></label>
        <input type="text" name="title" id="hod_title" maxlength="200" placeholder="Message from College Admin">
      </div>
      <div class="form-row">
        <label for="hod_message">Message</label>
        <textarea name="message" id="hod_message" rows="5" maxlength="4000" required placeholder="Write your announcement…"></textarea>
        <div class="muted" style="font-size:.8rem;margin-top:.25rem">Max 4000 characters.</div>
      </div>
      <div class="form-row">
        <label for="hod_attachment">Attachment <span class="muted">(optional)</span></label>
        <input type="file" name="attachment" id="hod_attachment" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        <div class="muted" style="font-size:.8rem;margin-top:.25rem">Supported: PDF, DOCX · Max 10 MB.</div>
      </div>
      <button class="btn btn-primary" type="submit" id="audienceSend">Send announcement</button>
    </form>
</dialog>
<script>
(function () {
  var dialog = document.getElementById('admAnnDialog');
  var openBtn = document.getElementById('admAnnOpen');
  var closeBtn = document.getElementById('admAnnClose');
  var titleBox = document.querySelector('.topbar-title');
  var heading = titleBox ? titleBox.querySelector('h1') : null;
  var sub = titleBox ? titleBox.querySelector('p') : null;
  if (heading) heading.textContent = 'Announcements';
  if (sub) sub.textContent = 'Broadcast notices to students, faculty & staff';
  if (titleBox) titleBox.hidden = true;
  document.title = document.title.replace(/^Notifications/, 'Announcements');
  if (openBtn && dialog) openBtn.addEventListener('click', function () { dialog.showModal(); });
  if (closeBtn && dialog) closeBtn.addEventListener('click', function () { dialog.close(); });
  if (dialog && document.querySelector('.alert-error')) dialog.showModal();
  var sel = document.getElementById('audience');
  var chip = document.getElementById('audienceCount');
  var btn = document.getElementById('audienceSend');
  if (!sel || !chip || !btn) return;
  function sync() {
    var opt = sel.options[sel.selectedIndex];
    if (!sel.value) {
      chip.textContent = 'Choose audience';
      btn.disabled = false;
      return;
    }
    var n = parseInt(opt.getAttribute('data-count') || '0', 10) || 0;
    chip.textContent = n + (n === 1 ? ' recipient' : ' recipients');
    btn.disabled = n < 1;
  }
  sel.addEventListener('change', sync);
})();
</script>
<?php endif; ?>

<?php if (!$canMessageHods && ($rolePrefix ?? '') === 'hod'): ?>
<?php require __DIR__ . '/../hod/notifications.php'; ?>
<?php elseif (!$canMessageHods && ($rolePrefix ?? '') === 'student'): ?>
<?php require __DIR__ . '/../student/notifications.php'; ?>
<?php elseif (!$canMessageHods): ?>
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

<?php if (($rolePrefix ?? '') !== 'hod'): ?>
<div class="panel" style="margin-top:1rem">
  <strong>Delivery channels</strong>
  <div class="chip-row" style="margin-top:.45rem">
    <?php foreach ($providers as $ch => $st): ?>
      <span class="chip" title="<?= e($st['detail']) ?>"><?= e(ucfirst(str_replace('_', '-', $ch))) ?>: <?= e($st['label']) ?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

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
    if (in_array(($nmeta['kind'] ?? ''), ['admin_hod_message', 'admin_audience_message'], true) && !empty($nmeta['announcement_id']) && !empty($nmeta['has_attachment'])) {
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
            <?php $rowNotice = \AdminHodMessageTools::noticeTypeLabel((string)($nmeta['notice_type'] ?? '')); ?>
            <?php if ($rowNotice !== ''): ?><span class="chip"><?= e($rowNotice) ?></span><?php endif; ?>
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
          <form method="post" style="margin:0;display:inline" onsubmit="return confirm('Delete this notification from your account?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_notification">
            <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
            <button class="btn btn-sm btn-ghost" type="submit" style="color:#f87171">Delete</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
