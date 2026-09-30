<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
AttendanceTools::ensureSchema();
$classId = student_class_id($user);
if ($classId) {
    sync_class_roster((int)$user['institution_id'], $classId);
}
$academic = student_academic_context($user);
$currentSubjects = courses_for_student($user);
$allowedSubjectIds = array_map(static fn($s) => (int)$s['id'], $currentSubjects);
$openSubjectId = (int)get('subject_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('action') === 'request_regularization') {
        $sessionId = (int)post('session_id');
        $session = Database::fetch('SELECT id, subject_id, class_id FROM attendance_sessions WHERE id = ?', [$sessionId]);
        $sessionSubjectId = $session ? (int)$session['subject_id'] : 0;
        if (!$session || (int)$session['class_id'] !== $classId || !in_array($sessionSubjectId, $allowedSubjectIds, true)) {
            flash('error', 'You can only request regularization for your current subjects.');
            redirect('/student/attendance.php');
        }
        $proofUrl = null;
        if (!empty($_FILES['proof']['name'])) {
            $ext = pathinfo((string)$_FILES['proof']['name'], PATHINFO_EXTENSION);
            $name = 'reg_' . $user['id'] . '_' . time() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
            $dir = dirname(__DIR__) . '/uploads/attendance';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $dest = $dir . '/' . $name;
            if (move_uploaded_file((string)$_FILES['proof']['tmp_name'], $dest)) {
                $proofUrl = '/professor/uploads/attendance/' . $name;
            }
        }
        $result = AttendanceTools::requestRegularization(
            $user,
            $sessionId,
            (string)post('requested_status'),
            (string)post('reason'),
            $proofUrl
        );
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Regularization request submitted.' : ($result['error'] ?? 'Failed.'));
        redirect('/student/attendance.php?subject_id=' . $sessionSubjectId);
    }
}

$cards = AttendanceTools::studentCurrentSubjectAttendance($user);

$programLevel = '';
if ($classId > 0) {
    $classRow = Database::fetch('SELECT meta FROM classes WHERE id = ?', [$classId]);
    $programLevel = class_program_level($classRow);
}
$subtitleParts = [];
if ($programLevel !== '') {
    $subtitleParts[] = $programLevel;
}
if ((int)$academic['year'] > 0) {
    $subtitleParts[] = 'Year ' . (int)$academic['year'];
}
if ($academic['department_code'] !== '') {
    $subtitleParts[] = $academic['department_code'];
} elseif ($academic['department_name'] !== '') {
    $subtitleParts[] = $academic['department_name'];
}
if ($academic['section'] !== '') {
    $subtitleParts[] = 'Sec ' . $academic['section'];
}
$subtitleParts[] = $academic['semester_label'] . ' Semester';
$subtitle = implode(' · ', $subtitleParts);

$sumPresent = 0;
$sumAbsent = 0;
$sumTotal = 0;
$minPct = 75.0;
foreach ($cards as $card) {
    $sumPresent += (int)$card['present'];
    $sumAbsent += (int)$card['absent'];
    $sumTotal += (int)$card['total'];
    $minPct = (float)($card['band']['min'] ?? $minPct);
}
$overallPct = $sumTotal > 0 ? round($sumPresent * 100 / $sumTotal, 1) : 0.0;
$overallBand = $sumTotal > 0
    ? AttendanceTools::shortageBand($overallPct, $minPct)
    : ['band' => 'none', 'label' => 'No classes yet', 'percent' => 0.0, 'min' => $minPct];
$fmtPct = static function (float $n): string {
    $text = rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    return ($text === '' ? '0' : $text) . '%';
};

render_header('My Attendance', 'attendance', ['compactTitle' => true]);
?>
<div class="student-att-page">
<?php if ($classId < 1): ?>
  <div class="empty">Your account is not assigned to a class. Ask College Admin to put you in the correct year and section.</div>
<?php elseif (!$cards): ?>
  <div class="empty">No subjects for <?= e($subtitle) ?> yet. Past attendance stays in Academic History.</div>
<?php else: ?>
  <section class="hod-hero">
    <div class="hod-hero-copy">
      <h2><?= icon('calendar', 'icon-inline') ?> My Attendance</h2>
      <p><?= e($subtitle) ?></p>
      <p class="student-att-summary">Overall: <?= e($fmtPct($overallPct)) ?> · Classes attended: <?= (int)$sumPresent ?> / <?= (int)$sumTotal ?></p>
    </div>
  </section>
  <div class="student-att-kpis">
    <div class="student-att-kpi is-<?= e((string)$overallBand['band']) ?>">
      <strong><?= e($fmtPct($overallPct)) ?></strong>
      <span>Overall</span>
    </div>
    <div class="student-att-kpi is-present">
      <strong><?= (int)$sumPresent ?></strong>
      <span>Present</span>
    </div>
    <div class="student-att-kpi is-absent">
      <strong><?= (int)$sumAbsent ?></strong>
      <span>Absent</span>
    </div>
  </div>
  <h3 class="student-att-section">Subject-wise breakdown</h3>
  <div class="student-att-list">
  <?php foreach ($cards as $card):
    $sid = (int)$card['subject_id'];
    $pct = (float)$card['percent'];
    $band = $card['band'];
    $prof = trim((string)($card['professor_name'] ?? ''));
    $held = (int)$card['total'];
    $barWidth = max(0, min(100, $pct));
    $who = $prof !== '' ? $prof : 'Professor not assigned';
    $heldLabel = $held === 1 ? '1 class held' : $held . ' classes held';
  ?>
    <article class="student-att-card panel" id="att-subject-<?= $sid ?>">
      <div class="student-att-main">
        <h2 class="student-att-title"><?= e((string)$card['subject_name']) ?></h2>
        <p class="student-att-meta<?= $prof === '' ? ' is-unassigned' : '' ?>">
          <?php if ((string)$card['subject_code'] !== ''): ?><?= e((string)$card['subject_code']) ?> · <?php endif; ?>
          <?= e($who) ?> · <?= e($heldLabel) ?>
          <?php if (($card['course_type'] ?? '') === 'lab'): ?> · Lab<?php endif; ?>
        </p>
        <p class="student-att-counts"><?= (int)$card['present'] ?> present · <?= (int)$card['absent'] ?> absent</p>
      </div>
      <div class="student-att-track">
        <div class="bar student-att-bar is-<?= e((string)$band['band']) ?>" role="progressbar" aria-valuenow="<?= e((string)$barWidth) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Attendance <?= e((string)$pct) ?> percent">
          <span style="width: <?= (float)$barWidth ?>%"></span>
        </div>
      </div>
      <div class="student-att-score is-<?= e((string)$band['band']) ?>">
        <strong><?= e($fmtPct($pct)) ?></strong>
        <span><?= e((string)$band['label']) ?></span>
      </div>
      <?php if ($card['sessions']): ?>
      <details class="student-att-details"<?= $openSubjectId === $sid ? ' open' : '' ?>>
        <summary>View Details</summary>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Period</th><th>Status</th><th>Regularize</th></tr></thead>
            <tbody>
            <?php foreach ($card['sessions'] as $r): ?>
              <tr>
                <td><?= e((string)$r['session_date']) ?></td>
                <td><?= e((string)($r['period'] ?? '')) ?></td>
                <td><?= e((string)$r['status']) ?></td>
                <td>
                  <?php if (in_array((string)$r['status'], ['absent', 'late', 'excused'], true)): ?>
                  <details>
                    <summary class="muted">Request</summary>
                    <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:.4rem;min-width:14rem">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="request_regularization">
                      <input type="hidden" name="session_id" value="<?= (int)$r['session_id'] ?>">
                      <div class="form-row" style="margin:0">
                        <label>Requested status</label>
                        <select name="requested_status">
                          <option value="present">Present</option>
                          <option value="late">Late</option>
                          <option value="excused">Excused</option>
                        </select>
                      </div>
                      <div class="form-row" style="margin:0"><label>Reason</label><textarea name="reason" required maxlength="1000"></textarea></div>
                      <div class="form-row" style="margin:0"><label>Proof</label><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
                      <button class="btn btn-sm btn-primary" type="submit">Submit</button>
                    </form>
                  </details>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
<?php render_footer(); ?>
