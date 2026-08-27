<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::requireRole('hod', 'admin');
$user = Auth::user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$instId = (int)$user['institution_id'];
$deptId = $isAdmin ? (int)($_GET['department_id'] ?? ($user['department_id'] ?? 0)) : hod_department_id($user);

$year = (int)($_GET['year'] ?? $_POST['year'] ?? 1);
if ($year < 1 || $year > 4) {
    $year = 1;
}
$semesterKey = strtolower(trim((string)($_GET['semester'] ?? $_POST['semester'] ?? 'odd')));
$semesterKey = $semesterKey === 'even' ? 'even' : 'odd';
$courseType = strtolower(trim((string)($_GET['type'] ?? $_POST['course_type'] ?? 'theory')));
$courseType = $courseType === 'lab' ? 'lab' : 'theory';
$semesterLabel = $semesterKey === 'even' ? 'Even Semester' : 'Odd Semester';

function hod_subjects_query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    if (isset($params['year']) && (int)$params['year'] < 1) {
        unset($params['year']);
    }
    $query = http_build_query($params);
    return url('/hod/subjects' . ($query !== '' ? '?' . $query : ''));
}

function hod_subjects_redirect_path(int $year, string $semesterKey, string $courseType, bool $isAdmin, int $deptId): string
{
    $params = [
        'year' => $year,
        'semester' => $semesterKey,
        'type' => $courseType,
    ];
    if ($isAdmin && $deptId > 0) {
        $params['department_id'] = $deptId;
    }
    return '/hod/subjects?' . http_build_query($params);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$isAdmin && $deptId < 1) {
        flash('error', 'Your HOD account is not linked to a department.');
        redirect('/hod/subjects');
    }
    $action = (string)post('action');
    $postYear = (int)post('year', $year);
    if ($postYear < 1 || $postYear > 4) {
        $postYear = $year;
    }
    $postSemesterKey = subject_semester_key((string)post('semester', $semesterKey));
    $postType = strtolower(trim((string)post('course_type', $courseType))) === 'lab' ? 'lab' : 'theory';
    try {
        if ($action === 'add_subject') {
            hod_save_subject($user, (string)post('subject_code'), (string)post('subject_name'), [
                'credits' => post('credits', $postType === 'lab' ? 2 : 4),
                'contact_hours' => post('contact_hours', $postType === 'lab' ? 30 : 60),
                'semester' => $postSemesterKey === 'even' ? 'Even Semester' : 'Odd Semester',
                'syllabus_text' => post('syllabus_text'),
                'year' => $postYear,
                'course_type' => $postType,
            ]);
            flash('success', ($postType === 'lab' ? 'Lab' : 'Course') . ' saved for ' . subject_year_label($postYear) . ' · ' . subject_normalize_semester($postSemesterKey) . '.');
        } elseif ($action === 'assign_professor') {
            hod_assign_professor_subject(
                $user,
                (int)post('subject_id'),
                (int)post('professor_id'),
                (int)post('class_id')
            );
            flash('success', 'Professor assigned to course and class. Students in that class are enrolled.');
        } elseif ($action === 'remove_assignment') {
            $assignmentId = (int)post('assignment_id');
            $row = Database::fetch(
                'SELECT sa.id FROM subject_assignments sa
                 JOIN subjects s ON s.id = sa.subject_id
                 WHERE sa.id = ? AND s.institution_id = ? AND s.department_id = ?',
                [$assignmentId, $instId, $deptId]
            );
            if (!$row) {
                throw new RuntimeException('Assignment not found in your department.');
            }
            Database::query('DELETE FROM subject_assignments WHERE id = ?', [$assignmentId]);
            flash('success', 'Professor assignment removed.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect(hod_subjects_redirect_path($postYear, $postSemesterKey, $postType, $isAdmin, $deptId));
}

$dept = $deptId > 0
    ? Database::fetch('SELECT id, name, code FROM departments WHERE id = ? AND institution_id = ?', [$deptId, $instId])
    : null;
$allSubjects = ($deptId > 0 && $dept) ? subjects_for_department($instId, $deptId) : [];
$subjects = ($deptId > 0 && $dept)
    ? subjects_for_department_context($instId, $deptId, $year, $semesterKey, $courseType)
    : [];
$assignSubjects = ($deptId > 0 && $dept)
    ? subjects_for_department_context($instId, $deptId, $year, $semesterKey, null)
    : [];
$assignments = ($deptId > 0 && $dept) ? subject_assignments_for_department($instId, $deptId) : [];
$professors = ($deptId > 0 && $dept)
    ? Database::fetchAll(
        'SELECT id, full_name, email FROM users
         WHERE institution_id = ? AND department_id = ? AND role = "professor" AND is_active = 1
         ORDER BY full_name',
        [$instId, $deptId]
    )
    : [];
$classes = ($deptId > 0 && $dept) ? academic_classes($instId, $deptId) : [];
$classes = array_values(array_filter($classes, static fn($c) => (int)($c['year'] ?? 0) === $year));

$contextAssignments = [];
foreach ($assignments as $a) {
    $subjectRow = [
        'semester' => $a['subject_semester'] ?? ($a['semester'] ?? ''),
        'meta' => $a['subject_meta'] ?? null,
    ];
    // Prefer joined subject fields when present; fall back to looking up from catalog.
    $matched = null;
    foreach ($allSubjects as $s) {
        if ((int)$s['id'] === (int)($a['subject_id'] ?? 0)) {
            $matched = $s;
            break;
        }
    }
    if ($matched) {
        $subjectRow = $matched;
    }
    if (subject_academic_year_level($subjectRow) !== $year) {
        continue;
    }
    if (subject_semester_key((string)($subjectRow['semester'] ?? '')) !== $semesterKey) {
        continue;
    }
    if (subject_course_type($subjectRow) !== $courseType) {
        // Show assignments for both types in the list under current year/semester,
        // but keep type filter aligned with the active Courses/Labs tab.
        continue;
    }
    $contextAssignments[] = $a;
}

$unscopedCount = 0;
foreach ($allSubjects as $s) {
    if (subject_academic_year_level($s) < 1) {
        $unscopedCount++;
    }
}

render_header('Department Courses', 'subjects');
?>
<?php if (!$isAdmin && $deptId < 1): ?>
<div class="panel">
  <div class="alert alert-warn">Your HOD account is not linked to a department. Contact the College Admin.</div>
</div>
<?php else: ?>
<section class="welcome-banner reveal">
  <div>
    <h2><?= e((string)($dept['name'] ?? 'Department')) ?> courses</h2>
    <p>Manage courses and labs by academic year and semester. Assign professors to classes/sections so students receive only the courses for their year.</p>
  </div>
</section>

<div class="panel reveal hod-course-nav">
  <div class="form-row">
    <label>Academic year</label>
    <div class="chip-row" role="navigation" aria-label="Academic year">
      <?php foreach ([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $yVal => $yLabel): ?>
        <a class="chip<?= $year === $yVal ? ' active' : '' ?>" href="<?= e(hod_subjects_query(['year' => $yVal, 'semester' => $semesterKey, 'type' => $courseType])) ?>"><?= e($yLabel) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-row">
    <label>Semester</label>
    <div class="chip-row" role="navigation" aria-label="Semester">
      <a class="chip<?= $semesterKey === 'odd' ? ' active' : '' ?>" href="<?= e(hod_subjects_query(['year' => $year, 'semester' => 'odd', 'type' => $courseType])) ?>">Odd Semester</a>
      <a class="chip<?= $semesterKey === 'even' ? ' active' : '' ?>" href="<?= e(hod_subjects_query(['year' => $year, 'semester' => 'even', 'type' => $courseType])) ?>">Even Semester</a>
    </div>
  </div>
  <div class="form-row">
    <label>Catalog</label>
    <div class="chip-row" role="navigation" aria-label="Course type">
      <a class="chip<?= $courseType === 'theory' ? ' active' : '' ?>" href="<?= e(hod_subjects_query(['year' => $year, 'semester' => $semesterKey, 'type' => 'theory'])) ?>">Courses</a>
      <a class="chip<?= $courseType === 'lab' ? ' active' : '' ?>" href="<?= e(hod_subjects_query(['year' => $year, 'semester' => $semesterKey, 'type' => 'lab'])) ?>">Labs</a>
    </div>
  </div>
  <p class="hod-course-context">
    <strong><?= e(subject_year_label($year)) ?></strong>
    · <?= e($semesterLabel) ?>
    · <?= $courseType === 'lab' ? 'Labs' : 'Courses' ?>
    · <?= count($subjects) ?> item(s)
  </p>
</div>

<div class="grid grid-2" style="margin-top:1.25rem">
  <div class="panel reveal">
    <h3><?= $courseType === 'lab' ? 'Add lab' : 'Add course' ?></h3>
    <p class="cell-sub" style="margin-top:0">Saved under <?= e(subject_year_label($year)) ?> · <?= e($semesterLabel) ?> · <?= $courseType === 'lab' ? 'Lab' : 'Theory' ?>.</p>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_subject">
      <input type="hidden" name="year" value="<?= $year ?>">
      <input type="hidden" name="semester" value="<?= e($semesterKey) ?>">
      <input type="hidden" name="course_type" value="<?= e($courseType) ?>">
      <div class="form-row two">
        <div><label>Course code</label><input name="subject_code" required placeholder="<?= $courseType === 'lab' ? 'CS301L' : 'CS301' ?>"></div>
        <div><label>Course name</label><input name="subject_name" required placeholder="<?= $courseType === 'lab' ? 'Programming Lab' : 'Database Management Systems' ?>"></div>
      </div>
      <div class="form-row two">
        <div><label>Credits</label><input name="credits" type="number" step="0.5" value="<?= $courseType === 'lab' ? '2' : '4' ?>"></div>
        <div><label>Contact hours</label><input name="contact_hours" type="number" value="<?= $courseType === 'lab' ? '30' : '60' ?>"></div>
      </div>
      <div class="form-row"><label>Syllabus (optional)</label><textarea name="syllabus_text" placeholder="Unit-wise syllabus for professors to use in course plans"></textarea></div>
      <button class="btn btn-primary" type="submit"><?= $courseType === 'lab' ? 'Save lab' : 'Save course' ?></button>
    </form>
  </div>

  <div class="panel reveal">
    <h3>Assign professor</h3>
    <p class="cell-sub" style="margin-top:0">Assign within <?= e(subject_year_label($year)) ?> · <?= e($semesterLabel) ?>. Class list is limited to this year.</p>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="assign_professor">
      <input type="hidden" name="year" value="<?= $year ?>">
      <input type="hidden" name="semester" value="<?= e($semesterKey) ?>">
      <input type="hidden" name="course_type" value="<?= e($courseType) ?>">
      <div class="form-row"><label>Course / lab</label>
        <select name="subject_id" required>
          <option value="">Select course</option>
          <?php foreach ($assignSubjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>">
              <?= e($s['code'] . ' · ' . $s['name']) ?>
              (<?= subject_course_type($s) === 'lab' ? 'Lab' : 'Course' ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>Professor</label>
        <select name="professor_id" required>
          <option value="">Select professor</option>
          <?php foreach ($professors as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?> · <?= e($p['email']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>Class (section)</label>
        <select name="class_id" required>
          <option value="">Select class</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e(class_batch_label($c)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!$classes): ?>
        <p class="alert alert-warn" style="margin:0">No <?= e(subject_year_label($year)) ?> classes found in your department. Ask Admin to create classes for this year.</p>
      <?php endif; ?>
      <p style="color:var(--muted);font-size:.85rem;margin:0">Students in the selected class are auto-enrolled for the current academic year.</p>
      <button class="btn btn-primary" type="submit"<?= !$assignSubjects || !$classes ? ' disabled' : '' ?>>Assign professor</button>
    </form>
  </div>
</div>

<div class="panel reveal" style="margin-top:1rem">
  <div class="panel-h">
    <h2><?= $courseType === 'lab' ? 'Labs' : 'Courses' ?> · <?= e(subject_year_label($year)) ?> · <?= e($semesterLabel) ?></h2>
  </div>
  <?php if (!$subjects): ?>
    <div class="empty">No <?= $courseType === 'lab' ? 'labs' : 'courses' ?> yet for this year and semester. Add as many as you need above.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Code</th><th>Name</th><th>Type</th><th>Credits</th><th>Hours</th><th>Year</th><th>Semester</th></tr>
        </thead>
        <tbody>
          <?php foreach ($subjects as $s): ?>
            <tr>
              <td><strong><?= e($s['code']) ?></strong></td>
              <td><?= e($s['name']) ?></td>
              <td><?= subject_course_type($s) === 'lab' ? 'Lab' : 'Course' ?></td>
              <td><?= e((string)$s['credits']) ?></td>
              <td><?= e((string)$s['contact_hours']) ?></td>
              <td><?= e(subject_year_label(subject_academic_year_level($s))) ?></td>
              <td><?= e(subject_normalize_semester((string)($s['semester'] ?? ''))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel reveal" style="margin-top:1rem">
  <h3>Professor assignments · <?= e(subject_year_label($year)) ?> · <?= e($semesterLabel) ?> · <?= $courseType === 'lab' ? 'Labs' : 'Courses' ?></h3>
  <?php if (!$contextAssignments): ?>
    <div class="empty">No professor assignments yet for this academic context.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Course</th><th>Professor</th><th>Class</th><th>Year</th><th>Section</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($contextAssignments as $a):
            $classRow = [
                'year' => $a['class_year'] ?? null,
                'section' => $a['class_section'] ?? null,
                'dept_code' => $dept['code'] ?? '',
                'dept_name' => $dept['name'] ?? '',
                'name' => $a['class_name'] ?? '',
                'meta' => $a['class_meta'] ?? null,
            ];
          ?>
            <tr>
              <td><strong><?= e($a['subject_code']) ?></strong><div class="cell-sub"><?= e($a['subject_name']) ?></div></td>
              <td><?= e($a['professor_name']) ?><div class="cell-sub"><?= e($a['professor_email']) ?></div></td>
              <td><?= e(class_batch_label($classRow)) ?></td>
              <td><?= (int)($a['class_year'] ?? 0) ?: '—' ?></td>
              <td><?= e((string)($a['class_section'] ?? '—')) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Remove this assignment?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove_assignment">
                  <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                  <input type="hidden" name="year" value="<?= $year ?>">
                  <input type="hidden" name="semester" value="<?= e($semesterKey) ?>">
                  <input type="hidden" name="course_type" value="<?= e($courseType) ?>">
                  <button class="btn btn-ghost" type="submit">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($unscopedCount > 0): ?>
<div class="panel reveal" style="margin-top:1rem">
  <h3>Legacy courses without year</h3>
  <p class="cell-sub"><?= (int)$unscopedCount ?> course(s) were created before year/semester structure. Re-save them from the correct year tab to place them in the catalog.</p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Code</th><th>Course</th><th>Semester</th></tr>
      </thead>
      <tbody>
        <?php foreach ($allSubjects as $s): ?>
          <?php if (subject_academic_year_level($s) > 0) continue; ?>
          <tr>
            <td><strong><?= e($s['code']) ?></strong></td>
            <td><?= e($s['name']) ?></td>
            <td><?= e((string)($s['semester'] ?? '—')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php render_footer(); ?>
