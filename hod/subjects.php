<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::requireRole('hod', 'admin');
$user = Auth::user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$instId = (int)$user['institution_id'];
$deptId = $isAdmin ? (int)($_GET['department_id'] ?? ($user['department_id'] ?? 0)) : hod_department_id($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$isAdmin && $deptId < 1) {
        flash('error', 'Your HOD account is not linked to a department.');
        redirect('/hod/subjects');
    }
    $action = (string)post('action');
    try {
        if ($action === 'add_subject') {
            hod_save_subject($user, (string)post('subject_code'), (string)post('subject_name'), [
                'credits' => post('credits', 3),
                'contact_hours' => post('contact_hours', 45),
                'semester' => post('semester'),
                'syllabus_text' => post('syllabus_text'),
            ]);
            flash('success', 'Course/subject saved for your department.');
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
    redirect('/hod/subjects');
}

$dept = $deptId > 0
    ? Database::fetch('SELECT id, name, code FROM departments WHERE id = ? AND institution_id = ?', [$deptId, $instId])
    : null;
$subjects = ($deptId > 0 && $dept) ? subjects_for_department($instId, $deptId) : [];
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
$yearFilter = (int)(get('year', 0));
if ($yearFilter > 0) {
    $classes = array_values(array_filter($classes, static fn($c) => (int)($c['year'] ?? 0) === $yearFilter));
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
    <p>Create department subjects and assign professors to specific classes/sections. Professors then build course plans and academic data only for assigned courses.</p>
  </div>
</section>

<div class="grid grid-2">
  <div class="panel reveal">
    <h3>Add course / subject</h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_subject">
      <div class="form-row two">
        <div><label>Course code</label><input name="subject_code" required placeholder="CS301"></div>
        <div><label>Course name</label><input name="subject_name" required placeholder="Database Management Systems"></div>
      </div>
      <div class="form-row two">
        <div><label>Credits</label><input name="credits" type="number" step="0.5" value="4"></div>
        <div><label>Contact hours</label><input name="contact_hours" type="number" value="60"></div>
      </div>
      <div class="form-row"><label>Semester</label><input name="semester" placeholder="Odd Semester"></div>
      <div class="form-row"><label>Syllabus (optional)</label><textarea name="syllabus_text" placeholder="Unit-wise syllabus for professors to use in course plans"></textarea></div>
      <button class="btn btn-primary" type="submit">Save course</button>
    </form>
  </div>

  <div class="panel reveal">
    <h3>Assign professor to course</h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="assign_professor">
      <div class="form-row"><label>Course</label>
        <select name="subject_id" required>
          <option value="">Select course</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= e($s['code'] . ' · ' . $s['name']) ?></option>
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
      <div class="form-row"><label>Class (year + section)</label>
        <select name="class_id" required>
          <option value="">Select class</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e(class_batch_label($c)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <p style="color:var(--muted);font-size:.85rem;margin:0">Students in the selected class are auto-enrolled for the current academic year.</p>
      <button class="btn btn-primary" type="submit">Assign professor</button>
    </form>
  </div>
</div>

<div class="panel reveal" style="margin-top:1rem">
  <div class="panel-h">
    <h2>Department course catalog</h2>
    <div class="chip-row">
      <?php foreach ([0 => 'All years', 1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $yVal => $yLabel): ?>
        <a class="chip<?= $yearFilter === $yVal ? ' active' : '' ?>" href="<?= e(url('/hod/subjects' . ($yVal ? '?year=' . $yVal : ''))) ?>"><?= e($yLabel) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if (!$subjects): ?>
    <div class="empty">No courses yet. Add your first department course above.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Code</th><th>Course</th><th>Credits</th><th>Semester</th></tr>
        </thead>
        <tbody>
          <?php foreach ($subjects as $s): ?>
            <tr>
              <td><strong><?= e($s['code']) ?></strong></td>
              <td><?= e($s['name']) ?></td>
              <td><?= e((string)$s['credits']) ?></td>
              <td><?= e((string)($s['semester'] ?? '—')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel reveal" style="margin-top:1rem">
  <h3>Professor assignments</h3>
  <?php if (!$assignments): ?>
    <div class="empty">No professor assignments yet. Assign a professor to a course and class above.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Course</th><th>Professor</th><th>Class</th><th>Year</th><th>Section</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($assignments as $a):
            if ($yearFilter > 0 && (int)($a['class_year'] ?? 0) !== $yearFilter) {
                continue;
            }
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
<?php endif; ?>
<?php render_footer(); ?>
