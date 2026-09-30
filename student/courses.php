<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

use App\Models\MarksFormula;

Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
ensure_student_academic_schema();
$courses = courses_for_student($user);
$academic = student_academic_context($user);
$instId = (int)$user['institution_id'];
$classId = student_class_id($user);
$academicYear = institution_academic_year($instId);
MarksFormula::ensureInternalMarksSchema();

$fmtNum = static function ($n): string {
    return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
};
$fmtPct = static function (float $n): string {
    $text = rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    return ($text === '' ? '0' : $text) . '%';
};

$attendanceBySubject = [];
foreach (AttendanceTools::studentCurrentSubjectAttendance($user) as $card) {
    $attendanceBySubject[(int)$card['subject_id']] = $card;
}

$marksBySubject = [];
if ($classId > 0 && $courses) {
    $reg = trim((string)($user['register_no'] ?? ''));
    $allowedSubjectIds = array_map(static fn($s) => (int)$s['id'], $courses);
    $params = [$instId, $classId, (int)$user['id'], $reg];
    $sql = 'SELECT m.subject_id, m.computed_total, m.meta, f.total_max AS formula_total_max
            FROM internal_marks m
            LEFT JOIN marks_formulas f ON f.id = m.formula_id AND f.institution_id = m.institution_id
            WHERE m.institution_id = ?
              AND m.class_id = ?
              AND (m.student_id = ? OR (m.register_no <> "" AND m.register_no = ?))';
    if ($academicYear !== '') {
        $sql .= ' AND (m.academic_year = ? OR m.academic_year = "")';
        $params[] = $academicYear;
    }
    $placeholders = implode(',', array_fill(0, count($allowedSubjectIds), '?'));
    $sql .= " AND m.subject_id IN ($placeholders)";
    foreach ($allowedSubjectIds as $sid) {
        $params[] = $sid;
    }
    foreach (Database::fetchAll($sql, $params) as $row) {
        $meta = json_decode((string)($row['meta'] ?? '{}'), true) ?: [];
        $totalMax = (float)($meta['total_max'] ?? $row['formula_total_max'] ?? 0);
        $total = $row['computed_total'];
        if ($total === null || $total === '' || $totalMax <= 0) {
            continue;
        }
        $marksBySubject[(int)$row['subject_id']] = [
            'total' => (float)$total,
            'max' => $totalMax,
        ];
    }
}

$pendingBySubject = [];
foreach (assignments_visible_to_student($user) as $assignment) {
    $grade = trim((string)($assignment['grade'] ?? ''));
    $status = (string)($assignment['sub_status'] ?? '');
    if ($grade !== '' || in_array($status, ['graded', 'submitted', 'late'], true)) {
        continue;
    }
    $sid = (int)($assignment['subject_id'] ?? 0);
    $pendingBySubject[$sid] = ($pendingBySubject[$sid] ?? 0) + 1;
}

$subjectCount = count($courses);
$subtitleParts = [($academic['semester_label'] ?: 'Current') . ' Semester'];
if ($academicYear !== '') {
    $subtitleParts[] = $academicYear;
}
if (($academic['class_label'] ?? '') !== '') {
    $subtitleParts[] = (string)$academic['class_label'];
}
$subtitleParts[] = $subjectCount . ' ' . ($subjectCount === 1 ? 'Subject' : 'Subjects');
$subtitle = implode(' · ', $subtitleParts);

render_header('My Subjects', 'courses', ['compactTitle' => true]);
?>
<div class="stu-subj">
  <section class="stu-subj-head">
    <h2><?= icon('book', 'icon-inline') ?> My Subjects</h2>
    <p><?= e($subtitle) ?></p>
  </section>
  <?php if (!$courses): ?>
    <div class="empty">No matching subjects for your year and semester.</div>
  <?php else: ?>
    <div class="stu-subj-grid">
    <?php foreach ($courses as $c):
      $sid = (int)$c['id'];
      $prof = trim((string)($c['professor_name'] ?? ''));
      $type = subject_course_type($c);
      $att = $attendanceBySubject[$sid] ?? null;
      $attTotal = (int)($att['total'] ?? 0);
      $attPct = (float)($att['percent'] ?? 0);
      $attBand = (string)($att['band']['band'] ?? 'none');
      $lowAttendance = $attTotal > 0 && $attBand === 'below';
      $mark = $marksBySubject[$sid] ?? null;
      $pending = (int)($pendingBySubject[$sid] ?? 0);
    ?>
      <article class="stu-subj-card">
        <div class="stu-subj-top">
          <div>
            <h3><?= e($c['name']) ?></h3>
            <p class="stu-subj-prof"><?= e($prof !== '' ? $prof : 'Not assigned') ?></p>
            <p class="stu-subj-code"><?= e((string)$c['code']) ?> · <?= e($type === 'lab' ? 'Lab' : 'Subject') ?></p>
          </div>
          <?php if ($lowAttendance): ?>
            <span class="stu-subj-badge is-low">Low attendance</span>
          <?php else: ?>
            <span class="stu-subj-badge is-active">Active</span>
          <?php endif; ?>
        </div>
        <div class="stu-subj-metrics">
          <div class="stu-subj-metric is-att<?= $attTotal < 1 ? ' is-empty' : ($lowAttendance ? ' is-low' : '') ?>">
            <strong><?= $attTotal > 0 ? e($fmtPct($attPct)) : '—' ?></strong>
            <span>Attendance</span>
          </div>
          <div class="stu-subj-metric is-marks<?= $mark ? '' : ' is-empty' ?>">
            <strong><?= $mark ? e($fmtNum($mark['total']) . '/' . $fmtNum($mark['max'])) : '—' ?></strong>
            <span>Int. marks</span>
          </div>
          <div class="stu-subj-metric is-pending<?= $pending > 0 ? '' : ' is-zero' ?>">
            <strong><?= $pending ?></strong>
            <span>Pending</span>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
