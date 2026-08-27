<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::requireRole('professor', 'admin');
$user = Auth::user();
ProfessorMessageTools::ensureSchema();

$year = (int)(get('year') ?: post('year'));
$subjectId = (int)(get('subject_id') ?: post('subject_id'));
$classId = (int)(get('class_id') ?: post('class_id'));

$years = ProfessorMessageTools::yearsForProfessor($user);
if ($year > 0 && !in_array($year, $years, true)) {
    $year = 0;
}

$courses = $year > 0 ? ProfessorMessageTools::coursesForProfessorYear($user, $year) : [];
$courseIds = array_map(static fn($c) => (int)$c['id'], $courses);
if ($subjectId > 0 && !in_array($subjectId, $courseIds, true)) {
    $subjectId = 0;
}

$classes = ($year > 0 && $subjectId > 0)
    ? ProfessorMessageTools::classesForProfessorCourseYear($user, $subjectId, $year)
    : [];
$classIds = array_map(static fn($c) => (int)$c['id'], $classes);
if ($classId > 0 && !in_array($classId, $classIds, true)) {
    $classId = 0;
}

$recipientCount = 0;
if ($year > 0 && $subjectId > 0 && $classId > 0) {
    $recipientCount = ProfessorMessageTools::previewRecipientCount($user, $year, $subjectId, $classId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');
    if ($action === 'send_message') {
        $year = (int)post('year');
        $subjectId = (int)post('subject_id');
        $classId = (int)post('class_id');
        $message = (string)post('message');
        $title = trim((string)post('title'));

        $result = ProfessorMessageTools::send(
            $user,
            $year,
            $subjectId,
            $classId,
            $message,
            $title,
            isset($_FILES['attachment']) && is_array($_FILES['attachment']) ? $_FILES['attachment'] : null
        );
        $wantsJson = str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
            || (string)post('format') === 'json';

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            if (!$result['ok']) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => $result['error'] ?? 'Unable to send message.',
                    'recipient_count' => 0,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode([
                'success' => true,
                'message' => 'Message sent successfully',
                'recipient_count' => (int)($result['recipient_count'] ?? 0),
                'announcement_id' => (int)($result['announcement_id'] ?? 0),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'Unable to send message.');
            redirect(
                '/professor/messages.php?year=' . $year
                . '&subject_id=' . $subjectId
                . '&class_id=' . $classId
            );
        }

        $n = (int)($result['recipient_count'] ?? 0);
        flash('success', 'Message sent successfully to ' . $n . ' student' . ($n === 1 ? '' : 's') . '.');
        redirect('/professor/messages.php?year=' . $year . '&subject_id=' . $subjectId . '&class_id=' . $classId);
    }
}

$history = ProfessorMessageTools::sentHistory($user, 25);
$canSend = $year > 0 && $subjectId > 0 && $classId > 0;

render_header('Message Students', 'messages', [
    'subtitle' => 'Send announcements to your assigned year, course, and class',
]);
?>
<div class="grid grid-2" style="align-items:start">
<div class="panel">
  <form method="get" class="form-grid" id="scopeForm" style="margin-bottom:1rem">
    <div class="form-row">
      <label for="year">Year</label>
      <select name="year" id="year" onchange="this.form.submit()">
        <option value="">Select year…</option>
        <?php foreach ($years as $y): ?>
          <option value="<?= (int)$y ?>" <?= $year === (int)$y ? 'selected' : '' ?>><?= e(subject_year_label((int)$y)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label for="subject_id">Course</label>
      <select name="subject_id" id="subject_id" onchange="this.form.submit()" <?= $year < 1 ? 'disabled' : '' ?>>
        <option value="">Select course…</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $subjectId === (int)$c['id'] ? 'selected' : '' ?>>
            <?= e(trim(($c['code'] ?? '') . ' — ' . ($c['name'] ?? ''), ' —')) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if ($year > 0 && !$courses): ?>
        <div class="muted" style="font-size:.82rem;margin-top:.25rem">No courses assigned for this year.</div>
      <?php endif; ?>
    </div>
    <div class="form-row">
      <label for="class_id">Assigned Class / Section</label>
      <select name="class_id" id="class_id" onchange="this.form.submit()" <?= $subjectId < 1 ? 'disabled' : '' ?>>
        <option value="">Select class…</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>>
            <?= e(class_batch_label($c)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if ($subjectId > 0 && !$classes): ?>
        <div class="muted" style="font-size:.82rem;margin-top:.25rem">No class/section assigned for this course and year.</div>
      <?php endif; ?>
    </div>
  </form>

  <form method="post" class="form-grid" id="sendForm" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="send_message">
    <input type="hidden" name="year" value="<?= (int)$year ?>">
    <input type="hidden" name="subject_id" value="<?= (int)$subjectId ?>">
    <input type="hidden" name="class_id" value="<?= (int)$classId ?>">

    <div class="form-row">
      <label for="title">Title <span class="muted">(optional)</span></label>
      <input type="text" name="title" id="title" maxlength="200" placeholder="Announcement from Professor" <?= !$canSend ? 'disabled' : '' ?>>
    </div>

    <div class="form-row">
      <label for="message">Message</label>
      <textarea name="message" id="message" rows="6" maxlength="4000" placeholder="Write your message…" required <?= !$canSend ? 'disabled' : '' ?>></textarea>
      <div class="muted" style="font-size:.8rem;margin-top:.25rem">Max 4000 characters.</div>
    </div>

    <div class="form-row">
      <label for="attachment">Attachment <span class="muted">(optional)</span></label>
      <input type="file" name="attachment" id="attachment" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" <?= !$canSend ? 'disabled' : '' ?>>
      <div class="muted" style="font-size:.8rem;margin-top:.25rem">Supported formats: PDF, DOCX · Maximum file size: 10 MB.</div>
    </div>

    <div class="muted" style="margin:.25rem 0 .75rem">
      Recipients:
      <?php if ($canSend): ?>
        <strong><?= (int)$recipientCount ?> student<?= $recipientCount === 1 ? '' : 's' ?></strong>
      <?php else: ?>
        <strong>—</strong> (select year, course, and class)
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary" id="sendBtn" <?= (!$canSend || $recipientCount < 1) ? 'disabled' : '' ?>>
      Send Message
    </button>
  </form>
</div>

<div class="panel" style="max-height:min(78vh, 900px);overflow:auto">
  <div class="panel-h"><strong>Sent Messages</strong></div>
  <?php if (!$history): ?>
    <div class="empty">No messages sent yet.</div>
  <?php else: ?>
  <?php foreach ($history as $h):
    $meta = json_decode((string)($h['meta'] ?? ''), true) ?: [];
    $courseLab = trim((string)($h['subject_code'] ?? '') . ' · ' . (string)($h['subject_name'] ?? ''), ' ·');
    if ($courseLab === '') {
        $courseLab = (string)($meta['course_label'] ?? 'Course');
    }
    $classLab = (string)($meta['class_label'] ?? '');
    if ($classLab === '') {
        $classLab = class_batch_label([
            'name' => $h['class_name'] ?? '',
            'section' => $h['section'] ?? '',
            'year' => $h['class_year'] ?? $h['year'] ?? 0,
        ]);
    }
    $yearLab = subject_year_label((int)($h['year'] ?? 0));
    $hasAttachment = trim((string)($h['attachment_path'] ?? '')) !== '';
    $attachName = (string)($h['attachment_original_name'] ?? '');
    $attachExt = strtolower(pathinfo($attachName, PATHINFO_EXTENSION));
  ?>
    <div style="padding:.85rem 0;border-bottom:1px solid var(--line)">
      <strong><?= e($courseLab) ?></strong>
      <div class="muted" style="font-size:.85rem;margin:.15rem 0"><?= e($yearLab) ?> · <?= e($classLab) ?></div>
      <div style="white-space:pre-wrap;font-size:.92rem"><?= e((string)$h['body']) ?></div>
      <?php if ($hasAttachment && $attachName !== ''): ?>
        <div style="margin-top:.45rem;font-size:.88rem">
          📎 <?= e($attachName) ?>
          <a class="btn btn-sm btn-ghost" style="margin-left:.35rem" href="<?= e(base_url('/api/messages/attachment?id=' . (int)$h['id'])) ?>">Download<?= $attachExt === 'pdf' ? ' PDF' : ($attachExt === 'docx' ? ' DOCX' : '') ?></a>
        </div>
      <?php endif; ?>
      <div class="chip-row" style="margin-top:.35rem">
        <span class="chip">Recipients: <?= (int)$h['recipient_count'] ?></span>
        <span class="chip">Sent: <?= e((string)$h['created_at']) ?></span>
      </div>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
</div>

<script>
(function () {
  var msg = document.getElementById('message');
  var btn = document.getElementById('sendBtn');
  if (!msg || !btn) return;
  var baseOk = <?= ($canSend && $recipientCount > 0) ? 'true' : 'false' ?>;
  function sync() {
    btn.disabled = !(baseOk && msg.value.trim().length > 0);
  }
  msg.addEventListener('input', sync);
  sync();
})();
</script>
<?php render_footer(); ?>
