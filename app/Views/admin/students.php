<?php
/** @var list<array<string,mixed>> $rows */
/** @var array{enrolled:int,detention:int,low:int,shown:int} $summary */
/** @var list<array<string,mixed>> $departments */
/** @var array{q:string,department_id:int,semester:string,year:int} $filters */
/** @var int $pageSize */
/** @var string $exportQuery */
$rows = $rows ?? [];
$summary = $summary ?? ['enrolled' => 0, 'detention' => 0, 'low' => 0, 'shown' => 0];
$departments = $departments ?? [];
$filters = $filters ?? ['q' => '', 'department_id' => 0, 'semester' => '', 'year' => 0];
$pageSize = max(1, (int)($pageSize ?? 10));
$exportQuery = (string)($exportQuery ?? '');
$shown = count($rows);
$first = min($pageSize, $shown);

$fmtPct = static function (?float $n): string {
    if ($n === null) {
        return '—';
    }
    $text = rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    return ($text === '' ? '0' : $text) . '%';
};
$tone = static function (?float $n): string {
    if ($n === null) {
        return 'is-muted';
    }
    if ($n >= 75) {
        return 'is-ok';
    }
    if ($n >= 65) {
        return 'is-warn';
    }
    return 'is-bad';
};
?>
<div class="adm-stu">
  <div class="adm-stu-kpis">
    <div class="adm-stu-kpi is-enrolled">
      <strong><?= (int)$summary['enrolled'] ?></strong>
      <span>Total enrolled</span>
    </div>
    <div class="adm-stu-kpi is-risk">
      <strong><?= (int)$summary['detention'] ?></strong>
      <span>Detention risk (&lt;65%)</span>
    </div>
    <div class="adm-stu-kpi is-low">
      <strong><?= (int)$summary['low'] ?></strong>
      <span>Low attendance (&lt;75%)</span>
    </div>
    <div class="adm-stu-kpi is-muted">
      <strong>—</strong>
      <span>Fee cleared</span>
      <em>Not recorded</em>
    </div>
  </div>

  <form class="adm-stu-bar" method="get" action="<?= e(url('/admin/students')) ?>">
    <label class="adm-stu-search">
      <span class="sr-only">Search students</span>
      <input type="search" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Search student name or roll number">
    </label>
    <select name="department_id" aria-label="Department">
      <option value="">All departments</option>
      <?php foreach ($departments as $dept): ?>
        <option value="<?= (int)$dept['id'] ?>" <?= (int)$filters['department_id'] === (int)$dept['id'] ? 'selected' : '' ?>><?= e((string)$dept['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="semester" aria-label="Semester">
      <option value="">All semesters</option>
      <option value="odd" <?= $filters['semester'] === 'odd' ? 'selected' : '' ?>>Odd</option>
      <option value="even" <?= $filters['semester'] === 'even' ? 'selected' : '' ?>>Even</option>
    </select>
    <select name="year" aria-label="Year">
      <option value="">All years</option>
      <?php foreach ([1, 2, 3, 4] as $yr): ?>
        <option value="<?= $yr ?>" <?= (int)$filters['year'] === $yr ? 'selected' : '' ?>><?= e(subject_year_label($yr)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-ghost" type="submit">Search</button>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/students/export' . $exportQuery)) ?>">Export</a>
    <a class="btn btn-sm btn-primary" href="<?= e(url('/admin/users')) ?>">Enrol Student</a>
  </form>

  <div class="panel adm-stu-table">
    <?php if (!$rows): ?>
      <div class="empty">No students match this search.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Dept</th>
              <th>Semester</th>
              <th>Roll no</th>
              <th>Attendance</th>
              <th>Avg marks</th>
              <th>Fee status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $row): ?>
            <tr class="adm-stu-row<?= $i >= $pageSize ? ' is-extra' : '' ?>"<?= $i >= $pageSize ? ' hidden' : '' ?>>
              <td>
                <div class="adm-stu-person">
                  <span class="adm-stu-avatar"><?= e((string)$row['initials']) ?></span>
                  <strong><?= e((string)$row['name']) ?></strong>
                </div>
              </td>
              <td><?= e($row['dept_code'] !== '' ? (string)$row['dept_code'] : ((string)$row['dept_name'] !== '' ? (string)$row['dept_name'] : '—')) ?></td>
              <td>
                <?= e((string)$row['semester_label']) ?>
                <?php if ($row['year_label'] !== ''): ?><span class="adm-stu-sub"><?= e((string)$row['year_label']) ?></span><?php endif; ?>
              </td>
              <td><?= e($row['roll'] !== '' ? (string)$row['roll'] : '—') ?></td>
              <td class="<?= e($tone($row['attendance'])) ?>"><?= e($fmtPct($row['attendance'])) ?></td>
              <td class="<?= e($tone($row['marks'])) ?>"><?= e($fmtPct($row['marks'])) ?></td>
              <td class="is-muted">Not recorded</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="adm-stu-foot">
        <span id="admStuCount">Showing <?= (int)$first ?> of <?= (int)$shown ?> students</span>
        <?php if ($shown > $pageSize): ?>
          <button class="btn btn-sm btn-ghost" type="button" id="admStuMore">Load more</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php if ($shown > $pageSize): ?>
<script>
document.getElementById('admStuMore')?.addEventListener('click', function () {
  document.querySelectorAll('.adm-stu-row.is-extra').forEach(function (row) { row.hidden = false; });
  this.hidden = true;
  var total = document.querySelectorAll('.adm-stu-row').length;
  var label = document.getElementById('admStuCount');
  if (label) label.textContent = 'Showing ' + total + ' of ' + total + ' students';
});
</script>
<?php endif; ?>
