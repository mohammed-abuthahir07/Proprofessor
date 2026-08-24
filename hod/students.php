<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

use App\Models\User;

Auth::requireRole('hod', 'admin');
$user = Auth::user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$instId = (int)$user['institution_id'];
$deptId = $isAdmin ? (int)($_GET['department_id'] ?? ($user['department_id'] ?? 0)) : (int)($user['department_id'] ?? 0);

$year = (int)($_GET['year'] ?? 0);
if ($year < 0 || $year > 4) {
    $year = 0;
}
$section = trim((string)($_GET['section'] ?? ''));
$classId = (int)($_GET['class_id'] ?? 0);
$programLevel = strtoupper(trim((string)($_GET['program_level'] ?? '')));
if (!in_array($programLevel, ['UG', 'PG'], true)) {
    $programLevel = '';
}
$search = trim((string)($_GET['q'] ?? ''));

$filters = [
    'year' => $year,
    'section' => $section,
    'class_id' => $classId,
    'program_level' => $programLevel,
    'q' => $search,
];

$dept = $deptId > 0
    ? Database::fetch('SELECT id, name, code FROM departments WHERE id = ? AND institution_id = ?', [$deptId, $instId])
    : null;

$students = ($deptId > 0 && $dept)
    ? User::studentsForDepartment($instId, $deptId, $filters)
    : [];

$classes = $deptId > 0 ? academic_classes($instId, $deptId) : [];
$sections = [];
foreach ($classes as $classRow) {
    $sec = trim((string)($classRow['section'] ?? ''));
    if ($sec !== '') {
        $sections[$sec] = true;
    }
}
ksort($sections);

$grouped = [];
foreach ($students as $student) {
    $studentYear = (int)($student['class_year'] ?? 0);
    $yearKey = $studentYear > 0 ? $studentYear : 0;
    $sectionKey = trim((string)($student['class_section'] ?? '')) ?: 'Unassigned';
    $grouped[$yearKey][$sectionKey][] = $student;
}
ksort($grouped);
foreach ($grouped as &$sectionGroups) {
    ksort($sectionGroups);
}
unset($sectionGroups);

function hod_students_query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    unset($params['department_id']);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === 0 || $value === '0') {
            unset($params[$key]);
        }
    }
    $query = http_build_query($params);
    return url('/hod/students' . ($query !== '' ? '?' . $query : ''));
}

function hod_year_label(int $year): string
{
    return match ($year) {
        1 => '1st Year',
        2 => '2nd Year',
        3 => '3rd Year',
        4 => '4th Year',
        default => 'Year ' . $year,
    };
}

render_header('Department Students', 'students');
?>
<?php if (!$isAdmin && $deptId < 1): ?>
<div class="panel">
  <div class="alert alert-warn">Your HOD account is not linked to a department. Contact the College Admin.</div>
</div>
<?php else: ?>
<div class="hod-students-page">
<section class="welcome-banner reveal">
  <div>
    <h2><?= e((string)($dept['name'] ?? 'Department')) ?> students</h2>
    <p><?= count($students) ?> active student(s) in your department<?= $year > 0 ? ' · Year ' . $year : '' ?>.</p>
  </div>
</section>

<div class="panel reveal hod-students-filters-panel">
  <form method="get" class="form-grid hod-student-filters">
    <div class="form-row hod-student-year-tabs">
      <label>Year</label>
      <div class="chip-row">
        <?php foreach ([0 => 'All Years', 1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $yearValue => $yearLabel): ?>
          <a class="chip<?= $year === $yearValue ? ' active' : '' ?>" href="<?= e(hod_students_query(['year' => $yearValue])) ?>"><?= e($yearLabel) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-row two">
      <div>
        <label>Section</label>
        <select name="section" onchange="this.form.submit()">
          <option value="">All sections</option>
          <?php foreach (array_keys($sections) as $sec): ?>
            <option value="<?= e($sec) ?>"<?= $section === $sec ? ' selected' : '' ?>><?= e($sec) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Class</label>
        <select name="class_id" onchange="this.form.submit()">
          <option value="">All classes</option>
          <?php foreach ($classes as $classRow): ?>
            <option value="<?= (int)$classRow['id'] ?>"<?= $classId === (int)$classRow['id'] ? ' selected' : '' ?>>
              <?= e(class_batch_label($classRow)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row two">
      <div>
        <label>Program</label>
        <select name="program_level" onchange="this.form.submit()">
          <option value="">All programs</option>
          <option value="UG"<?= $programLevel === 'UG' ? ' selected' : '' ?>>UG</option>
          <option value="PG"<?= $programLevel === 'PG' ? ' selected' : '' ?>>PG</option>
        </select>
      </div>
      <div>
        <label>Search</label>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email, or roll number">
      </div>
    </div>
    <?php if ($year > 0): ?>
      <input type="hidden" name="year" value="<?= $year ?>">
    <?php endif; ?>
    <div class="filters hod-students-filter-actions">
      <button class="btn btn-primary" type="submit">Apply filters</button>
      <a class="btn btn-ghost" href="<?= e(url('/hod/students')) ?>">Reset</a>
    </div>
  </form>
</div>

<div class="panel reveal hod-students-list-panel">
  <?php if (!$students): ?>
    <div class="empty">No students match the current filters in this department.</div>
  <?php else: ?>
    <div class="students-tree">
      <?php foreach ($grouped as $yearKey => $sectionGroups): ?>
        <div class="students-year">
          <h3><?= $yearKey > 0 ? e(hod_year_label($yearKey)) : 'Unassigned year' ?></h3>
          <?php foreach ($sectionGroups as $sectionLabel => $rows): ?>
            <div class="students-section">
              <h4>Section <?= e($sectionLabel) ?> <span class="muted-count"><?= count($rows) ?></span></h4>
              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Roll / Reg. No.</th>
                      <th>Program</th>
                      <th>Class</th>
                      <th>Section</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $row):
                      $classMeta = json_decode((string)($row['class_meta'] ?? ''), true) ?: [];
                      $level = strtoupper((string)($classMeta['level'] ?? ''));
                    ?>
                      <tr>
                        <td data-label="Student">
                          <strong><?= e($row['full_name']) ?></strong>
                          <div class="cell-sub"><?= e($row['email']) ?></div>
                          <div class="cell-sub"><?= e((string)($row['dept_name'] ?? '')) ?></div>
                        </td>
                        <td data-label="Roll / Reg. No."><?= e((string)($row['register_no'] ?? '—')) ?></td>
                        <td data-label="Program"><?= $level !== '' ? e($level) : '—' ?></td>
                        <td data-label="Class"><?= e((string)($row['class_name'] ?? '—')) ?></td>
                        <td data-label="Section"><?= e((string)($row['class_section'] ?? '—')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</div>
<?php endif; ?>
<?php render_footer(); ?>
