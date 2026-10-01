<?php
/** @var list<array<string,mixed>> $rows */
/** @var array{students:int,fully:int,partial:int,pending:int,collected:float,outstanding:float} $summary */
/** @var list<array<string,mixed>> $departments */
/** @var list<array<string,mixed>> $students */
/** @var list<string> $years */
/** @var array{q:string,department_id:int,year:int,academic_year:string,status:string,fee_type:string} $filters */
/** @var array<string,string> $types */
/** @var int $pageSize */
$rows = $rows ?? [];
$summary = $summary ?? ['students' => 0, 'fully' => 0, 'partial' => 0, 'pending' => 0, 'collected' => 0, 'outstanding' => 0];
$departments = $departments ?? [];
$students = $students ?? [];
$years = $years ?? [];
$filters = $filters ?? ['q' => '', 'department_id' => 0, 'year' => 0, 'academic_year' => '', 'status' => '', 'fee_type' => ''];
$types = $types ?? [];
$pageSize = max(1, (int)($pageSize ?? 10));
$shown = count($rows);
$first = min($pageSize, $shown);
$year = (string)$filters['academic_year'];
$statuses = [
    'paid' => 'Fully Paid',
    'partial' => 'Partially Paid',
    'pending' => 'Pending',
    'none' => 'Not Configured',
];
$statusClass = static function (string $key): string {
    return match ($key) {
        'paid' => 'is-ok',
        'partial' => 'is-warn',
        'pending' => 'is-bad',
        default => 'is-muted',
    };
};
?>
<div class="adm-fee">
  <div class="adm-fee-kpis">
    <div class="adm-fee-kpi"><strong><?= (int)$summary['students'] ?></strong><span>Total students</span></div>
    <div class="adm-fee-kpi is-ok"><strong><?= (int)$summary['fully'] ?></strong><span>Fully paid</span></div>
    <div class="adm-fee-kpi is-warn"><strong><?= (int)$summary['partial'] ?></strong><span>Partially paid</span></div>
    <div class="adm-fee-kpi is-bad"><strong><?= (int)$summary['pending'] ?></strong><span>Pending</span></div>
    <div class="adm-fee-kpi is-ok"><strong><?= e(fee_money((float)$summary['collected'])) ?></strong><span>Total collected</span></div>
    <div class="adm-fee-kpi is-bad"><strong><?= e(fee_money((float)$summary['outstanding'])) ?></strong><span>Total pending</span></div>
  </div>

  <form class="adm-fee-bar" method="get" action="<?= e(url('/admin/fee-collection')) ?>">
    <label class="adm-fee-search">
      <span class="sr-only">Search students</span>
      <input type="search" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Search name, register no, or email">
    </label>
    <select name="department_id" aria-label="Department">
      <option value="">All departments</option>
      <?php foreach ($departments as $dept): ?>
        <option value="<?= (int)$dept['id'] ?>" <?= (int)$filters['department_id'] === (int)$dept['id'] ? 'selected' : '' ?>><?= e((string)$dept['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="year" aria-label="Year">
      <option value="">All years</option>
      <?php foreach ([1, 2, 3, 4] as $level): ?>
        <option value="<?= $level ?>" <?= (int)$filters['year'] === $level ? 'selected' : '' ?>><?= e(subject_year_label($level)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="academic_year" aria-label="Academic year">
      <?php foreach ($years as $option): ?>
        <option value="<?= e($option) ?>" <?= $year === $option ? 'selected' : '' ?>><?= e($option) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" aria-label="Fee status">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="fee_type" aria-label="Fee type">
      <option value="">All fee types</option>
      <?php foreach ($types as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $filters['fee_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-ghost" type="submit">Search</button>
    <button class="btn btn-sm btn-primary" type="button" id="feeAddOpen">+ Add Fee</button>
  </form>

  <div class="panel adm-fee-table">
    <?php if ($year === ''): ?>
      <div class="empty">Set an academic year on the institution before collecting fees.</div>
    <?php elseif (!$rows): ?>
      <div class="empty">No students match this search.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Register no</th>
              <th>Department</th>
              <th>Year</th>
              <th>Applicable fees</th>
              <th>Paid</th>
              <th>Pending</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $row): ?>
            <tr class="adm-fee-row<?= $i >= $pageSize ? ' is-extra' : '' ?>"<?= $i >= $pageSize ? ' hidden' : '' ?>>
              <td>
                <div class="adm-fee-person">
                  <span class="adm-fee-avatar"><?= e((string)$row['initials']) ?></span>
                  <strong><?= e((string)$row['name']) ?></strong>
                </div>
              </td>
              <td><?= e($row['register_no'] !== '' ? (string)$row['register_no'] : '—') ?></td>
              <td><?= e($row['dept'] !== '' ? (string)$row['dept'] : '—') ?></td>
              <td><?= e($row['year_label'] !== '' ? (string)$row['year_label'] : '—') ?></td>
              <td>
                <?php if (!$row['fees']): ?>
                  <span class="adm-fee-muted">Not configured</span>
                <?php else: ?>
                  <?php foreach ($row['fees'] as $fee): ?>
                    <span class="adm-fee-chip is-<?= e((string)$fee['type']) ?>"><?= e((string)$fee['chip']) ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td><?= $row['fees'] ? e(fee_money((float)$row['paid'])) : '—' ?></td>
              <td><?= $row['fees'] ? e(fee_money((float)$row['pending'])) : '—' ?></td>
              <td><span class="adm-fee-badge <?= e($statusClass((string)$row['overall'])) ?>"><?= e($statuses[(string)$row['overall']] ?? 'Not Configured') ?></span></td>
              <td><a class="adm-fee-view" href="<?= e(url('/admin/fee-collection/student?student_id=' . (int)$row['id'] . '&academic_year=' . rawurlencode($year))) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="adm-fee-foot">
        <span id="admFeeCount">Showing <?= (int)$first ?> of <?= (int)$shown ?> students · <?= e($year) ?></span>
        <?php if ($shown > $pageSize): ?>
          <button class="btn btn-sm btn-ghost" type="button" id="admFeeMore">Load more</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<dialog class="adm-fee-dialog" id="feeAddDialog">
  <form method="post" action="<?= e(url('/admin/fee-collection')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_fee">
    <input type="hidden" name="return_to" value="<?= e('/admin/fee-collection' . ($year !== '' ? '?academic_year=' . rawurlencode($year) : '')) ?>">
    <div class="adm-fee-dialog-h">
      <div>
        <h2>Add Fee</h2>
        <p>Only the fee types you add apply to this student.</p>
      </div>
      <button class="icon-btn" type="button" data-fee-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <label>Student
      <input type="search" id="feeStudentFilter" placeholder="Search student name or register no">
      <select name="student_id" id="feeStudent" required>
        <option value="">Select student</option>
        <?php foreach ($students as $student): ?>
          <option value="<?= (int)$student['id'] ?>"><?= e((string)$student['name']) ?><?= $student['register_no'] !== '' ? ' · ' . e((string)$student['register_no']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="form-row two">
      <label>Academic year
        <select name="academic_year" required>
          <?php foreach ($years as $option): ?>
            <option value="<?= e($option) ?>" <?= $year === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Fee type
        <select name="fee_type" required>
          <?php foreach ($types as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="form-row two">
      <label>Total amount<input name="total_amount" inputmode="decimal" required placeholder="25000"></label>
      <label>Due date<input type="date" name="due_date"></label>
    </div>
    <label>Notes<textarea name="notes" rows="3" placeholder="Optional"></textarea></label>
    <div class="adm-fee-dialog-actions">
      <button class="btn btn-ghost" type="button" data-fee-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Add Fee</button>
    </div>
  </form>
</dialog>
<script>
(function () {
  const dialog = document.getElementById('feeAddDialog');
  document.getElementById('feeAddOpen')?.addEventListener('click', function () { dialog?.showModal(); });
  dialog?.querySelectorAll('[data-fee-close]').forEach(function (btn) {
    btn.addEventListener('click', function () { dialog.close(); });
  });
  const select = document.getElementById('feeStudent');
  const filter = document.getElementById('feeStudentFilter');
  if (select && filter) {
    const all = [...select.options].map(function (opt) { return { value: opt.value, text: opt.text }; });
    filter.addEventListener('input', function () {
      const q = filter.value.trim().toLowerCase();
      const current = select.value;
      select.innerHTML = '';
      all.forEach(function (opt) {
        if (opt.value !== '' && q !== '' && !opt.text.toLowerCase().includes(q)) return;
        const node = document.createElement('option');
        node.value = opt.value;
        node.textContent = opt.text;
        select.appendChild(node);
      });
      if ([...select.options].some(function (opt) { return opt.value === current; })) select.value = current;
    });
  }
  document.getElementById('admFeeMore')?.addEventListener('click', function () {
    document.querySelectorAll('.adm-fee-row.is-extra').forEach(function (row) { row.hidden = false; });
    this.hidden = true;
    const total = document.querySelectorAll('.adm-fee-row').length;
    const label = document.getElementById('admFeeCount');
    if (label) label.textContent = 'Showing ' + total + ' of ' + total + ' students';
  });
})();
</script>
