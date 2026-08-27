<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
ensure_student_academic_schema();

$classId = (int)get('class_id');
$year = (int)get('year');
$semesterKey = strtolower(trim((string)get('semester')));
if (!in_array($semesterKey, ['odd', 'even'], true)) {
    $semesterKey = '';
}
$subjectId = (int)get('subject_id');

$current = student_academic_context($user);
$historyByYear = StudentAcademicHistoryTools::historicalContextsByYear($user);

$subjects = [];
$detail = null;
$contextLabel = '';

if ($classId > 0 && $year > 0 && $semesterKey !== '') {
    if ($subjectId > 0) {
        $detail = StudentAcademicHistoryTools::historicalSubjectDetail($user, $classId, $subjectId, $year, $semesterKey);
        if (!$detail) {
            flash('error', 'Historical record not found or access denied.');
            redirect('/student/academic-history.php?class_id=' . $classId . '&year=' . $year . '&semester=' . urlencode($semesterKey));
        }
    } else {
        $subjects = StudentAcademicHistoryTools::historicalSubjects($user, $classId, $year, $semesterKey);
        if (!$subjects) {
            flash('error', 'No historical subjects found for this period.');
            redirect('/student/academic-history.php');
        }
    }
    foreach ($historyByYear as $yg) {
        foreach ($yg['contexts'] as $ctx) {
            if ((int)$ctx['class_id'] === $classId && (int)$ctx['year'] === $year && (string)$ctx['semester_key'] === $semesterKey) {
                $contextLabel = $ctx['year_label'] . ' · ' . $ctx['class_label'] . ' · ' . $ctx['semester_label'] . ' Semester';
                break 2;
            }
        }
    }
}

render_header('Academic History', 'history', [
    'subtitle' => 'Previous years, semesters, and academic records',
]);
?>

<div class="panel" style="margin-bottom:1rem">
  <strong>Current academic context</strong>
  <div class="chip-row" style="margin-top:.4rem">
    <span class="chip"><?= e($current['year_label'] ?: 'Year not set') ?></span>
    <span class="chip"><?= e($current['department_code'] ?: ($current['department_name'] ?: 'Department')) ?></span>
    <?php if ($current['section'] !== ''): ?>
      <span class="chip">Section <?= e($current['section']) ?></span>
    <?php endif; ?>
    <span class="chip"><?= e($current['semester_label']) ?> Semester</span>
  </div>
  <div class="muted" style="margin-top:.35rem;font-size:.82rem">
    Use Attendance, Assignments, and Internal Marks for your <strong>current</strong> semester.
    This page shows <strong>past</strong> records only (read-only).
  </div>
</div>

<?php if ($detail): ?>
  <?php
    $sub = $detail['subject'];
    $prof = trim((string)($detail['professor_name'] ?? ''));
    $att = $detail['attendance_pct'];
    $asg = $detail['assignments'];
    $marks = $detail['internal_marks'];
    $tests = $detail['tests'];
  ?>
  <div class="panel">
    <div class="panel-h">
      <div>
        <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/student/academic-history.php?class_id=' . $classId . '&year=' . $year . '&semester=' . urlencode($semesterKey))) ?>">← Back to subjects</a>
        <h2 style="margin:.5rem 0 0"><?= e((string)$sub['name']) ?></h2>
        <div class="muted" style="font-size:.88rem"><?= e($detail['year_label']) ?> · <?= e($detail['class_label']) ?> · <?= e($detail['semester_label']) ?> Semester</div>
      </div>
    </div>
    <div class="grid grid-2" style="margin-top:1rem;gap:1rem">
      <div class="stat"><div class="label">Professor</div><div class="value" style="font-size:1rem"><?= e($prof !== '' ? $prof : 'Not Assigned') ?></div></div>
      <div class="stat"><div class="label">Attendance</div><div class="value"><?= $att !== null ? e((string)$att) . '%' : '—' ?></div></div>
      <div class="stat"><div class="label">Assignments</div><div class="value" style="font-size:1rem">
        <?php if ($asg): ?>
          <?= (int)$asg['submitted'] ?> / <?= (int)$asg['total'] ?> submitted
          <?php if ((int)$asg['graded'] > 0): ?>
            <div class="hint"><?= (int)$asg['graded'] ?> graded</div>
          <?php endif; ?>
        <?php else: ?>—<?php endif; ?>
      </div></div>
      <div class="stat"><div class="label">Internal Marks</div><div class="value" style="font-size:1rem">
        <?php if ($marks && $marks['computed_total'] !== null): ?>
          <?= e((string)$marks['computed_total']) ?> / <?= e((string)$marks['total_max']) ?>
          <?php if ($marks['grade_letter'] !== ''): ?>
            <div class="hint">Grade <?= e($marks['grade_letter']) ?></div>
          <?php endif; ?>
        <?php else: ?>—<?php endif; ?>
      </div></div>
      <?php if ($tests): ?>
      <div class="stat"><div class="label">Tests / Practice</div><div class="value" style="font-size:1rem">
        <?= (int)$tests['correct'] ?> / <?= (int)$tests['attempts'] ?> correct
        <?php if ($tests['score_sum'] > 0): ?>
          <div class="hint">Score <?= e((string)$tests['score_sum']) ?></div>
        <?php endif; ?>
      </div></div>
      <?php endif; ?>
    </div>
    <?php if ($marks && !empty($marks['marks_data'])): ?>
    <div style="margin-top:1.25rem">
      <strong>Mark components</strong>
      <div class="table-wrap" style="margin-top:.5rem"><table>
        <thead><tr><th>Component</th><th style="text-align:right">Score</th></tr></thead>
        <tbody>
        <?php foreach ($marks['marks_data'] as $code => $val): ?>
          <tr><td><?= e(ucfirst(str_replace('_', ' ', (string)$code))) ?></td><td style="text-align:right"><?= e((string)$val) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <?php endif; ?>
  </div>

<?php elseif ($subjects): ?>
  <div class="panel">
    <div class="panel-h">
      <div>
        <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/student/academic-history.php')) ?>">← All history</a>
        <h2 style="margin:.5rem 0 0"><?= e($contextLabel) ?></h2>
      </div>
    </div>
    <?php
      $theory = array_filter($subjects, static fn($s) => ($s['course_type'] ?? '') !== 'lab');
      $labs = array_filter($subjects, static fn($s) => ($s['course_type'] ?? '') === 'lab');
    ?>
    <?php if ($theory): ?>
    <h3 style="margin:1rem 0 .5rem;font-size:1rem">Subjects</h3>
    <div class="plan-list">
      <?php foreach ($theory as $s): ?>
        <a class="plan-row" href="<?= e(base_url('/student/academic-history.php?class_id=' . $classId . '&year=' . $year . '&semester=' . urlencode($semesterKey) . '&subject_id=' . (int)$s['id'])) ?>" style="text-decoration:none;color:inherit">
          <div class="plan-ico"><?= icon('book') ?></div>
          <div class="meta">
            <strong><?= e($s['name']) ?></strong>
            <span><?= e($s['code']) ?> · Professor: <?= e(trim((string)($s['professor_name'] ?? '')) !== '' ? (string)$s['professor_name'] : 'Not Assigned') ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($labs): ?>
    <h3 style="margin:1rem 0 .5rem;font-size:1rem">Labs</h3>
    <div class="plan-list">
      <?php foreach ($labs as $s): ?>
        <a class="plan-row" href="<?= e(base_url('/student/academic-history.php?class_id=' . $classId . '&year=' . $year . '&semester=' . urlencode($semesterKey) . '&subject_id=' . (int)$s['id'])) ?>" style="text-decoration:none;color:inherit">
          <div class="plan-ico"><?= icon('monitor') ?></div>
          <div class="meta">
            <strong><?= e($s['name']) ?></strong>
            <span><?= e($s['code']) ?> · Professor: <?= e(trim((string)($s['professor_name'] ?? '')) !== '' ? (string)$s['professor_name'] : 'Not Assigned') ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div class="panel">
    <div class="panel-h"><h2>Academic History</h2></div>
    <?php if (!$historyByYear): ?>
      <div class="empty">No previous academic records yet. When you complete a semester or year and move to a new one, your past attendance, marks, and assignments will appear here.</div>
    <?php else: ?>
      <?php foreach ($historyByYear as $yearGroup): ?>
        <div style="padding:1rem 0;border-bottom:1px solid var(--line)">
          <strong><?= e($yearGroup['year_label']) ?></strong>
          <?php foreach ($yearGroup['contexts'] as $ctx): ?>
            <div style="margin-top:.75rem;padding:.85rem 1rem;background:var(--surface-2, rgba(255,255,255,.03));border-radius:var(--radius,10px)">
              <div class="muted" style="font-size:.85rem;margin-bottom:.35rem"><?= e($ctx['class_label']) ?></div>
              <a class="btn btn-ghost btn-sm" href="<?= e(base_url('/student/academic-history.php?class_id=' . (int)$ctx['class_id'] . '&year=' . (int)$ctx['year'] . '&semester=' . urlencode((string)$ctx['semester_key']))) ?>">
                <?= e($ctx['semester_label']) ?> Semester
                <span class="chip" style="margin-left:.35rem"><?= (int)$ctx['subject_count'] ?> subject<?= (int)$ctx['subject_count'] === 1 ? '' : 's' ?></span>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php render_footer(); ?>
