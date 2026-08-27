<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$instId = (int)($user['institution_id'] ?? 0);
$deptId = $isAdmin
    ? (int)($_GET['department_id'] ?? ($user['department_id'] ?? 0))
    : hod_department_id($user);

ProfessorHodMessageTools::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');
    if ($action === 'reply_professor') {
        if ($isAdmin && $deptId > 0) {
            $user['department_id'] = $deptId;
        }
        $result = ProfessorHodMessageTools::hodReply(
            $user,
            (int)post('thread_id'),
            (string)post('message'),
            isset($_FILES['attachment']) && is_array($_FILES['attachment']) ? $_FILES['attachment'] : null
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'Unable to send reply.');
        } else {
            flash('success', 'Reply sent to professor.');
        }
        $q = $isAdmin && $deptId > 0 ? ('?department_id=' . $deptId) : '';
        redirect('/hod/compliance' . $q);
    }
}

if ($threadRead = (int)get('read_thread')) {
    if ($isAdmin && $deptId > 0) {
        $user['department_id'] = $deptId;
    }
    ProfessorHodMessageTools::markThreadReadForHod($user, $threadRead);
    $q = $isAdmin && $deptId > 0 ? ('?department_id=' . $deptId) : '';
    redirect('/hod/compliance' . $q);
}

// Auto-generate alerts from plans
$plans = $deptId > 0
    ? Database::fetchAll(
        'SELECT * FROM course_plans WHERE institution_id=? AND department_id=?',
        [$instId, $deptId]
    )
    : [];
foreach ($plans as $p) {
    $bloom = json_decode($p['bloom_data'] ?: '{}', true) ?: [];
    $higher = (float)($bloom['K4'] ?? 0) + (float)($bloom['K5'] ?? 0) + (float)($bloom['K6'] ?? 0);
    if ($higher < 30 && $p['status'] !== 'draft') {
        $exists = Database::fetch(
            'SELECT id FROM compliance_alerts WHERE plan_id=? AND alert_type="low_bloom" AND is_resolved=0',
            [$p['id']]
        );
        if (!$exists) {
            Database::insert('compliance_alerts', [
                'institution_id' => $instId,
                'department_id' => $deptId,
                'plan_id' => $p['id'],
                'alert_type' => 'low_bloom',
                'severity' => 'high',
                'message' => $p['subject_name'] . ': Low K4-K6 coverage (' . $higher . '%). NBA risk.',
            ]);
        }
    }
    if (($p['ai_score'] !== null && (float)$p['ai_score'] < 65)) {
        $exists = Database::fetch(
            'SELECT id FROM compliance_alerts WHERE plan_id=? AND alert_type="low_score" AND is_resolved=0',
            [$p['id']]
        );
        if (!$exists) {
            Database::insert('compliance_alerts', [
                'institution_id' => $instId,
                'department_id' => $deptId,
                'plan_id' => $p['id'],
                'alert_type' => 'low_score',
                'severity' => 'medium',
                'message' => $p['subject_name'] . ': AI quality score below 65.',
            ]);
        }
    }
}
$alerts = $deptId > 0
    ? Database::fetchAll(
        'SELECT * FROM compliance_alerts WHERE department_id=? ORDER BY is_resolved, FIELD(severity,"high","medium","low"), id DESC',
        [$deptId]
    )
    : [];

$hodUser = $user;
if ($isAdmin && $deptId > 0) {
    $hodUser['department_id'] = $deptId;
    $hodUser['role'] = 'hod'; // allow tools dept scope when admin browsing
}
$threads = $deptId > 0 ? ProfessorHodMessageTools::threadsForHod($hodUser, 40) : [];
$unreadFaculty = $deptId > 0 ? ProfessorHodMessageTools::unreadCountForHod($hodUser) : 0;
$deptLabel = $deptId > 0 ? ProfessorHodMessageTools::departmentLabel($deptId) : 'Department';

render_header('Compliance Alerts', 'compliance', [
    'subtitle' => 'Faculty messages & NBA / quality risks',
]);
?>
<?php if (!$isAdmin && $deptId < 1): ?>
<div class="panel">
  <div class="alert alert-warn">Your HOD account is not linked to a department. Contact the College Admin.</div>
</div>
<?php else: ?>

<div class="panel" style="margin-bottom:1rem">
  <div class="panel-h" style="align-items:center">
    <div>
      <h2 style="margin:0;font-size:1.05rem">Faculty messages</h2>
      <p class="muted" style="margin:.3rem 0 0;font-size:.85rem">
        Complaints & messages from <?= e($deptLabel) ?> professors only. Reply per professor below.
      </p>
    </div>
    <span class="chip"><?= (int)$unreadFaculty ?> unread</span>
  </div>

  <?php if (!$threads): ?>
    <div class="empty" style="margin-top:.85rem">No faculty messages yet.</div>
  <?php else: ?>
    <?php foreach ($threads as $thread):
      $root = $thread['root'];
      $replies = $thread['replies'];
      $tid = (int)($root['thread_id'] ?? $root['id']);
      $unread = empty($root['is_read']);
      $hasAtt = trim((string)($root['attachment_path'] ?? '')) !== '';
      $attName = (string)($root['attachment_original_name'] ?? '');
      $attExt = strtolower(pathinfo($attName, PATHINFO_EXTENSION));
      $profName = (string)($root['professor_name'] ?? 'Professor');
    ?>
      <div style="padding:1rem 0;border-bottom:1px solid var(--line);opacity:<?= $unread ? '1' : '.88' ?>">
        <div style="display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start;flex-wrap:wrap">
          <div>
            <strong><?= e((string)$root['title']) ?></strong>
            <div class="chip-row" style="margin-top:.35rem">
              <span class="chip">From: <?= e($profName) ?></span>
              <span class="chip"><?= e((string)$root['created_at']) ?></span>
              <?php if ($unread): ?><span class="chip">New</span><?php endif; ?>
            </div>
          </div>
          <?php if ($unread): ?>
            <a class="btn btn-sm btn-ghost" href="?read_thread=<?= $tid ?><?= $isAdmin && $deptId > 0 ? '&department_id=' . $deptId : '' ?>">Mark read</a>
          <?php endif; ?>
        </div>
        <div style="white-space:pre-wrap;font-size:.92rem;margin-top:.45rem"><?= e((string)$root['body']) ?></div>
        <?php if ($hasAtt && $attName !== ''): ?>
          <div style="margin-top:.4rem;font-size:.88rem">
            📄 <?= e($attName) ?>
            <a class="btn btn-sm btn-ghost" style="margin-left:.25rem" href="<?= e(base_url('/api/messages/attachment?source=professor_hod&id=' . (int)$root['id'])) ?>">Download<?= $attExt === 'pdf' ? ' PDF' : ($attExt === 'docx' ? ' DOCX' : '') ?></a>
          </div>
        <?php endif; ?>

        <?php foreach ($replies as $r):
          $rAtt = trim((string)($r['attachment_path'] ?? '')) !== '';
          $rName = (string)($r['attachment_original_name'] ?? '');
          $rExt = strtolower(pathinfo($rName, PATHINFO_EXTENSION));
        ?>
          <div style="margin-top:.75rem;padding:.75rem;border-radius:12px;background:rgba(34,211,238,.06);border:1px solid rgba(34,211,238,.2)">
            <div style="display:flex;justify-content:space-between;gap:.5rem">
              <strong style="font-size:.9rem">Your reply</strong>
              <span class="chip"><?= e((string)$r['created_at']) ?></span>
            </div>
            <div style="white-space:pre-wrap;font-size:.9rem;margin-top:.3rem"><?= e((string)$r['body']) ?></div>
            <?php if ($rAtt && $rName !== ''): ?>
              <div style="margin-top:.35rem;font-size:.85rem">
                📄 <?= e($rName) ?>
                <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/api/messages/attachment?source=professor_hod&id=' . (int)$r['id'])) ?>">Download<?= $rExt === 'pdf' ? ' PDF' : ($rExt === 'docx' ? ' DOCX' : '') ?></a>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:.85rem">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reply_professor">
          <input type="hidden" name="thread_id" value="<?= $tid ?>">
          <div class="form-row">
            <label>Reply to <?= e($profName) ?></label>
            <textarea name="message" rows="3" maxlength="4000" required placeholder="Write your reply…"></textarea>
          </div>
          <div class="form-row">
            <label>Attachment <span class="muted">(optional PDF/DOCX)</span></label>
            <input type="file" name="attachment" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
          </div>
          <button class="btn btn-primary btn-sm" type="submit">Send reply</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-h">
    <h2 style="margin:0;font-size:1.05rem">Compliance alerts</h2>
  </div>
  <?php foreach ($alerts as $a): ?>
    <div class="alert alert-<?= $a['is_resolved'] ? 'success' : 'warn' ?>">
      <strong><?= e(strtoupper((string)$a['severity'])) ?></strong> · <?= e((string)$a['message']) ?>
      <?php if ($a['plan_id']): ?> · <a href="<?= e(base_url('/hod/approvals.php?id=' . $a['plan_id'])) ?>">Review</a><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$alerts): ?><div class="empty">No alerts.</div><?php endif; ?>
</div>
<?php endif; ?>
<?php render_footer(); ?>
