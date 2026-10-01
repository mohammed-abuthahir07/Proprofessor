<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
$academic = student_academic_context($user);
$classId = (int)$academic['class_id'];
$classLabel = (string)$academic['class_label'];
$programLevel = '';
if ($classId > 0) {
    $classRow = Database::fetch(
        'SELECT c.*, d.code AS dept_code, d.name AS dept_name
         FROM classes c
         LEFT JOIN departments d ON d.id = c.department_id
         WHERE c.id = ?',
        [$classId]
    );
    $programLevel = class_program_level($classRow);
}
$departmentLabel = trim((string)($academic['department_code'] ?: $academic['department_name']));
$sectionLabel = trim((string)$academic['section']) !== ''
    ? 'Section ' . trim((string)$academic['section'])
    : '—';
$yearLabel = (string)($academic['year_label'] ?: '—');
$semesterLabel = (string)($academic['semester_label'] ?: '—');

$exams = exam_timetable_for_student($user);

$events = [];
if ($classId > 0) {
    $currentSubjects = courses_for_student($user);
    $allowedSubjectIds = array_map(static fn($s) => (int)$s['id'], $currentSubjects);
    if ($allowedSubjectIds) {
        $placeholders = implode(',', array_fill(0, count($allowedSubjectIds), '?'));
        $params = [(int)$user['institution_id'], $classId];
        foreach ($allowedSubjectIds as $sid) {
            $params[] = $sid;
        }
        $events = Database::fetchAll(
            "SELECT ae.*
             FROM academic_events ae
             INNER JOIN course_plans cp
               ON cp.institution_id = ae.institution_id
               AND cp.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(ae.meta, '$.plan_id')) AS UNSIGNED)
             WHERE ae.institution_id = ?
               AND ae.event_type = 'lesson_session'
               AND cp.class_id = ?
               AND cp.subject_id IN ($placeholders)
             ORDER BY ae.event_date, ae.id",
            $params
        );
    }
}

render_header('Academic Calendar', 'calendar', [
    'subtitle' => 'Your class calendar',
]);
?>
<div class="panel" style="margin-bottom:1rem">
  <h2>Current Academic Details</h2>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>Academic Level</th>
        <th>Year</th>
        <th>Department</th>
        <th>Class / Section</th>
        <th>Semester</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><?= e($programLevel !== '' ? $programLevel : '—') ?></td>
        <td><?= e($yearLabel) ?></td>
        <td><?= e($departmentLabel !== '' ? $departmentLabel : '—') ?></td>
        <td><?= e($sectionLabel) ?></td>
        <td><?= e($semesterLabel) ?></td>
      </tr>
    </tbody>
  </table></div>
</div>
<div class="panel">
  <h2>Calendar</h2>
  <?php if ($classId < 1): ?>
    <div class="empty">Your account is not assigned to a class. Ask College Admin to put you in the correct year and section.</div>
  <?php elseif (!$events && !$exams): ?>
    <div class="empty">No calendar events for <?= e($classLabel !== '' ? $classLabel : 'your class') ?> yet.</div>
  <?php else: ?>
  <?php if ($exams): ?>
  <h3 class="stu-exam-h">Examination Schedule</h3>
  <ul class="stu-exam-list">
    <?php foreach ($exams as $exam): ?>
      <?php
        $examDate = DateTime::createFromFormat('Y-m-d', (string)$exam['exam_date']);
        $start = DateTime::createFromFormat('H:i:s', (string)$exam['start_time']);
        $end = DateTime::createFromFormat('H:i:s', (string)$exam['end_time']);
      ?>
      <li class="stu-exam">
        <div class="stu-exam-date">
          <strong><?= e($examDate ? $examDate->format('M') : '') ?></strong>
          <span><?= e($examDate ? $examDate->format('d') : (string)$exam['exam_date']) ?></span>
        </div>
        <div class="stu-exam-body">
          <strong><?= e((string)$exam['subject_name']) ?></strong>
          <span><?= e(exam_timetable_type_label((string)$exam['exam_type'], true)) ?><?= (int)$exam['is_lab'] === 1 ? ' · Lab / Practical' : '' ?></span>
          <em>
            <?= e($start ? ltrim($start->format('h:i A'), '0') : (string)$exam['start_time']) ?>
            – <?= e($end ? ltrim($end->format('h:i A'), '0') : (string)$exam['end_time']) ?>
            <?php if (trim((string)($exam['exam_hall'] ?? '')) !== ''): ?>
              · <?= e(trim((string)$exam['exam_hall'])) ?>
            <?php endif; ?>
          </em>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($events): ?>
  <h3 class="stu-exam-h">Class Events</h3>
  <div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Event</th><th>Type</th></tr></thead>
    <tbody>
    <?php foreach ($events as $e): ?>
      <tr><td><?= e($e['event_date']) ?></td><td><?= e($e['title']) ?></td><td><span class="chip"><?= e((string)$e['event_type']) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
