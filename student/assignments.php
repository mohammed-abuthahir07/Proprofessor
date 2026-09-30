<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
AssignmentTools::ensureSchema();
AssignmentTools::dispatchDeadlineReminders($user);
$classId = student_class_id($user);
$classLabel = $classId ? class_label_by_id($classId) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action', 'submit');

    if ($action === 'request_extension') {
        $aid = (int)post('assignment_id');
        $result = AssignmentTools::requestExtension(
            $user,
            $aid,
            (string)post('reason'),
            (string)post('requested_deadline')
        );
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Extension request sent.' : ($result['error'] ?? 'Request failed.'));
        redirect('/student/assignments.php');
    }

    $aid = (int)post('assignment_id');
    if (!student_can_submit_assignment($aid, $user)) {
        flash('error', 'This assignment is not for your class.');
        redirect('/student/assignments.php');
    }
    $asg = Database::fetch('SELECT * FROM assignments WHERE id = ?', [$aid]);
    $effective = $asg ? AssignmentTools::studentEffectiveDeadline($asg, $user) : null;
    // Soft late flag only — do not block submit (existing behavior allowed late submits).
    $isLate = $effective && strtotime($effective) !== false && time() > strtotime($effective);

    $text = trim((string)post('content_text'));
    $fileUrl = null;
    if (!empty($_FILES['file']['name'])) {
        $ext = pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION);
        $name = 'asg_' . $user['id'] . '_' . time() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
        $dir = dirname(__DIR__) . '/uploads/assignments';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $dest = $dir . '/' . $name;
        if (move_uploaded_file((string)$_FILES['file']['tmp_name'], $dest)) {
            $fileUrl = '/professor/uploads/assignments/' . $name;
        }
    }
    $status = $isLate ? 'late' : 'submitted';
    Database::query(
        'INSERT INTO assignment_submissions (assignment_id, student_id, content_text, file_url, submitted_at, status)
         VALUES (?,?,?,?,NOW(),?)
         ON DUPLICATE KEY UPDATE content_text=VALUES(content_text), file_url=COALESCE(VALUES(file_url), file_url),
           submitted_at=NOW(), status=VALUES(status)',
        [$aid, $user['id'], $text, $fileUrl, $status]
    );
    flash('success', $isLate ? 'Submitted (after deadline).' : 'Submitted.');
    redirect('/student/assignments.php');
}

$list = assignments_visible_to_student($user);

$subjectNames = [];
$professorNames = [];
$submissionExtra = [];
if ($list) {
    $subjectIds = array_values(array_unique(array_filter(array_map(static fn(array $a): int => (int)($a['subject_id'] ?? 0), $list))));
    $professorIds = array_values(array_unique(array_filter(array_map(static fn(array $a): int => (int)($a['professor_id'] ?? 0), $list))));
    $assignmentIds = array_map(static fn(array $a): int => (int)$a['id'], $list);
    if ($subjectIds) {
        $ph = implode(',', array_fill(0, count($subjectIds), '?'));
        foreach (Database::fetchAll("SELECT id, name FROM subjects WHERE id IN ($ph)", $subjectIds) as $row) {
            $subjectNames[(int)$row['id']] = (string)$row['name'];
        }
    }
    if ($professorIds) {
        $ph = implode(',', array_fill(0, count($professorIds), '?'));
        foreach (Database::fetchAll("SELECT id, full_name FROM users WHERE id IN ($ph)", $professorIds) as $row) {
            $professorNames[(int)$row['id']] = (string)$row['full_name'];
        }
    }
    $ph = implode(',', array_fill(0, count($assignmentIds), '?'));
    foreach (Database::fetchAll(
        "SELECT assignment_id, submitted_at, file_url FROM assignment_submissions WHERE student_id = ? AND assignment_id IN ($ph)",
        array_merge([(int)$user['id']], $assignmentIds)
    ) as $row) {
        $submissionExtra[(int)$row['assignment_id']] = $row;
    }
}

$bucketOf = static function (array $a): string {
    $status = strtolower((string)($a['sub_status'] ?? ''));
    if ($a['grade'] !== null && $a['grade'] !== '' || $status === 'graded') {
        return 'graded';
    }
    if (in_array($status, ['submitted', 'late'], true)) {
        return 'submitted';
    }
    return 'pending';
};
$counts = ['all' => count($list), 'pending' => 0, 'submitted' => 0, 'graded' => 0];
foreach ($list as $a) {
    $counts[$bucketOf($a)]++;
}
$tab = strtolower((string)get('tab', 'all'));
if (!in_array($tab, ['all', 'pending', 'submitted', 'graded'], true)) {
    $tab = 'all';
}
$openId = (int)get('id', 0);
$open = null;
if ($openId > 0) {
    foreach ($list as $a) {
        if ((int)$a['id'] === $openId) {
            $open = $a;
            break;
        }
    }
}

$typeLabel = static function (string $type): string {
    $type = trim(str_replace('_', ' ', $type));
    return $type === '' ? 'Assignment' : ucwords($type);
};
$dueState = static function (?string $due): array {
    if ($due === null || $due === '' || strtotime($due) === false) {
        return ['label' => 'No deadline', 'tone' => 'is-neutral', 'date' => ''];
    }
    $ts = strtotime($due);
    $date = date('M j, Y', $ts);
    if (time() > $ts) {
        return ['label' => 'Overdue', 'tone' => 'is-overdue', 'date' => $date];
    }
    $dueDay = date('Y-m-d', $ts);
    if ($dueDay === date('Y-m-d')) {
        return ['label' => 'Due today', 'tone' => 'is-soon', 'date' => $date];
    }
    if ($dueDay === date('Y-m-d', strtotime('+1 day'))) {
        return ['label' => 'Due tomorrow', 'tone' => 'is-tomorrow', 'date' => $date];
    }
    $days = (int)floor(($ts - time()) / 86400);
    if ($days <= 6) {
        return ['label' => $days . ' days left', 'tone' => 'is-soon', 'date' => $date];
    }
    return ['label' => 'Due ' . $date, 'tone' => 'is-neutral', 'date' => $date];
};
$marksLabel = static function ($marks): string {
    if ($marks === null || $marks === '') {
        return '';
    }
    $n = (float)$marks;
    $text = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    return 'Max ' . $text . ' marks';
};

render_header('Assignments', 'assignments', ['compactTitle' => true]);
?>
<?php if ($classId < 1): ?>
  <div class="empty">Your account is not assigned to a class. Ask College Admin to put you in the correct year and section.</div>
<?php elseif ($open): ?>
<?php
  $a = $open;
  $due = AssignmentTools::studentEffectiveDeadline($a, $user);
  $extReq = Database::fetch(
      'SELECT * FROM assignment_extension_requests WHERE assignment_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1',
      [(int)$a['id'], (int)$user['id']]
  );
  $bucket = $bucketOf($a);
?>
<div class="stu-asg">
  <a class="stu-asg-back" href="<?= e(url('/student/assignments.php?tab=' . $bucket)) ?>">All assignments</a>
  <div class="panel">
  <div class="panel-h">
    <div>
      <h3><?= e($a['title']) ?></h3>
      <div class="chip-row">
        <span class="chip"><?= e($a['assignment_type']) ?></span>
        <span class="chip"><?= e($classLabel) ?></span>
        <span class="chip">Due <?= e((string)($due ?? $a['deadline'] ?? '—')) ?></span>
        <?php if ($due && (string)($a['deadline'] ?? '') !== '' && $due !== (string)$a['deadline']): ?>
          <span class="chip">Extended for you</span>
        <?php endif; ?>
      </div>
    </div>
    <div><?= $a['sub_status'] ? status_badge($a['sub_status']) : '<span class="badge badge-warn">Pending</span>' ?></div>
  </div>
  <p><?= nl2br(e((string)$a['description'])) ?></p>
  <?php if ($a['grade'] !== null): ?>
    <div class="alert alert-success">Grade: <?= e((string)$a['grade']) ?> · <?= e((string)$a['feedback']) ?></div>
  <?php else: ?>
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="submit">
    <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
    <div class="form-row"><label>Your answer</label><textarea name="content_text" required><?= e((string)($a['sub_content'] ?? '')) ?></textarea></div>
    <div class="form-row"><label>File (optional)</label><input type="file" name="file"></div>
    <button class="btn btn-primary" type="submit">Submit</button>
  </form>
  <?php if (!$extReq || $extReq['status'] === 'rejected'): ?>
  <details style="margin-top:.75rem">
    <summary>Request deadline extension</summary>
    <form method="post" class="form-grid" style="margin-top:.5rem">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="request_extension">
      <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
      <div class="form-row"><label>Reason</label><textarea name="reason" required maxlength="1000" placeholder="Why do you need more time?"></textarea></div>
      <div class="form-row"><label>Requested new deadline</label><input type="datetime-local" name="requested_deadline" required></div>
      <button class="btn btn-sm" type="submit">Submit request</button>
    </form>
  </details>
  <?php elseif ($extReq['status'] === 'pending'): ?>
    <p class="muted">Extension request pending professor review.</p>
  <?php elseif ($extReq['status'] === 'approved'): ?>
    <p class="muted">Extension approved until <?= e((string)$extReq['approved_deadline']) ?>.</p>
  <?php endif; ?>
  <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="stu-asg">
  <section class="hod-hero">
    <div class="hod-hero-copy">
      <h2><?= icon('edit', 'icon-inline') ?> Assignments</h2>
      <p><?= e($classLabel !== '' ? $classLabel : 'Your class assignments') ?></p>
      <p class="stu-asg-summary"><?= (int)$counts['pending'] ?> pending · <?= (int)$counts['submitted'] ?> submitted · <?= (int)$counts['graded'] ?> graded</p>
    </div>
  </section>

  <div class="stu-asg-bar">
    <div class="stu-asg-filters" role="navigation" aria-label="Assignment status">
      <?php foreach (['all' => 'All', 'pending' => 'Pending', 'submitted' => 'Submitted', 'graded' => 'Graded'] as $key => $label): ?>
        <a class="stu-asg-tab<?= $tab === $key ? ' is-on' : '' ?>" href="<?= e(url('/student/assignments.php' . ($key === 'all' ? '' : '?tab=' . $key))) ?>"><?= e($label) ?> (<?= (int)$counts[$key] ?>)</a>
      <?php endforeach; ?>
    </div>
    <label class="stu-asg-search">
      <input type="search" id="stuAsgSearch" placeholder="Search assignments..." aria-label="Search assignments" autocomplete="off">
    </label>
  </div>

  <?php
    $visible = array_values(array_filter($list, static fn(array $a): bool => $tab === 'all' || $bucketOf($a) === $tab));
    $emptyCopy = match ($tab) {
        'pending' => ['No pending assignments', "You're all caught up for this category."],
        'submitted' => ['No submitted assignments', 'Assignments you submit will show up here.'],
        'graded' => ['No graded assignments', 'Grades appear here after your professor marks them.'],
        default => ['No assignments yet', 'No assignments for ' . ($classLabel !== '' ? $classLabel : 'your class') . ' yet.'],
    };
  ?>
  <?php if (!$visible): ?>
    <section class="hod-panel stu-asg-empty">
      <h2><?= e($emptyCopy[0]) ?></h2>
      <p><?= e($emptyCopy[1]) ?></p>
    </section>
  <?php else: ?>
    <div class="stu-asg-list" id="stuAsgList">
      <?php foreach ($visible as $a):
        $bucket = $bucketOf($a);
        $due = AssignmentTools::studentEffectiveDeadline($a, $user);
        $dueInfo = $dueState($due);
        $subject = $subjectNames[(int)($a['subject_id'] ?? 0)] ?? '';
        $professor = $professorNames[(int)($a['professor_id'] ?? 0)] ?? '';
        $extra = $submissionExtra[(int)$a['id']] ?? [];
        $desc = trim((string)($a['description'] ?? ''));
        $preview = mb_strlen($desc) > 180 ? rtrim(mb_substr($desc, 0, 180)) . '…' : $desc;
        $href = url('/student/assignments.php?id=' . (int)$a['id']);
        $action = match ($bucket) {
            'submitted' => 'View submission',
            'graded' => 'View result',
            default => 'Open & submit',
        };
        $fileName = trim((string)($extra['file_url'] ?? ''));
        $fileName = $fileName !== '' ? basename(parse_url($fileName, PHP_URL_PATH) ?: $fileName) : '';
        $submittedAt = trim((string)($extra['submitted_at'] ?? ''));
        $cardTone = $bucket === 'graded' ? 'is-graded' : ($bucket === 'submitted' ? 'is-submitted' : $dueInfo['tone']);
      ?>
        <article class="stu-asg-card <?= e($cardTone) ?>" data-search="<?= e(strtolower($a['title'] . ' ' . $subject)) ?>">
          <div class="stu-asg-top">
            <h3><?= e($a['title']) ?></h3>
            <?php if ($bucket === 'graded'): ?>
              <span class="stu-asg-due is-graded">Graded</span>
            <?php elseif ($bucket === 'submitted'): ?>
              <span class="stu-asg-due is-submitted"><?= strtolower((string)$a['sub_status']) === 'late' ? 'Submitted late' : 'Submitted' ?></span>
            <?php else: ?>
              <span class="stu-asg-due <?= e($dueInfo['tone']) ?>"><?= e($dueInfo['label']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($subject !== ''): ?><p class="stu-asg-course"><?= e($subject) ?></p><?php endif; ?>
          <?php if ($professor !== ''): ?><p class="stu-asg-prof"><?= e($professor) ?> · Course teacher</p><?php endif; ?>
          <div class="stu-asg-meta">
            <span><?= e($typeLabel((string)$a['assignment_type'])) ?></span>
            <?php $marks = $marksLabel($a['max_marks'] ?? null); if ($marks !== ''): ?><span><?= e($marks) ?></span><?php endif; ?>
            <?php if ($dueInfo['date'] !== ''): ?><span>Due <?= e($dueInfo['date']) ?></span><?php endif; ?>
            <?php if ($submittedAt !== '' && strtotime($submittedAt) !== false): ?><span>Submitted <?= e(date('M j, Y', strtotime($submittedAt))) ?></span><?php endif; ?>
          </div>
          <?php if ($preview !== ''): ?><p class="stu-asg-desc"><?= e($preview) ?></p><?php endif; ?>
          <?php if ($bucket === 'graded'): ?>
            <p class="stu-asg-grade">Grade: <?= e((string)$a['grade']) ?><?php if ($marks !== ''): ?> · <?= e($marks) ?><?php endif; ?></p>
            <?php if (trim((string)($a['feedback'] ?? '')) !== ''): ?>
              <p class="stu-asg-desc"><?= e(mb_strlen((string)$a['feedback']) > 160 ? rtrim(mb_substr((string)$a['feedback'], 0, 160)) . '…' : (string)$a['feedback']) ?></p>
            <?php endif; ?>
          <?php elseif ($bucket === 'submitted' && $fileName !== ''): ?>
            <p class="stu-asg-file">File: <?= e($fileName) ?></p>
          <?php elseif ($bucket === 'pending'): ?>
            <a class="stu-asg-attach" href="<?= e($href) ?>">Attach your answer and file</a>
          <?php endif; ?>
          <a class="btn btn-sm <?= $bucket === 'pending' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($href) ?>"><?= e($action) ?></a>
        </article>
      <?php endforeach; ?>
    </div>
    <section class="hod-panel stu-asg-empty" id="stuAsgSearchEmpty" hidden>
      <h2>No assignments found</h2>
      <p>Nothing in this list matches your search.</p>
    </section>
  <?php endif; ?>
</div>
<script>
(function () {
  var input = document.getElementById('stuAsgSearch');
  var list = document.getElementById('stuAsgList');
  var empty = document.getElementById('stuAsgSearchEmpty');
  if (!input || !list || !empty) return;
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    var shown = 0;
    list.querySelectorAll('.stu-asg-card').forEach(function (card) {
      var ok = q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
      card.hidden = !ok;
      if (ok) shown++;
    });
    empty.hidden = q === '' || shown !== 0;
    list.hidden = q !== '' && shown === 0;
  });
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
