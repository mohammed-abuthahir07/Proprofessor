<?php
/** @var list<array<string,mixed>> $rows */
/** @var array{total:float,processed:int,waiting:int,ytd:float,ytd_label:string} $summary */
/** @var list<array{label:string,total:float,width:float}> $departments */
/** @var list<array<string,mixed>> $departmentOptions */
/** @var list<array<string,mixed>> $faculty */
/** @var array{month:string,department_id:int,status:string,faculty_id:int,q:string} $filters */
/** @var array<string,string> $statuses */
/** @var int $pageSize */
/** @var string $returnTo */
/** @var bool $openPayroll */
$rows = $rows ?? [];
$summary = $summary ?? ['total' => 0, 'processed' => 0, 'waiting' => 0, 'ytd' => 0, 'ytd_label' => ''];
$departments = $departments ?? [];
$departmentOptions = $departmentOptions ?? [];
$faculty = $faculty ?? [];
$filters = $filters ?? ['month' => date('Y-m'), 'department_id' => 0, 'status' => '', 'faculty_id' => 0, 'q' => ''];
$statuses = $statuses ?? [];
$pageSize = max(1, (int)($pageSize ?? 10));
$returnTo = (string)($returnTo ?? '/admin/salary');
$openPayroll = !empty($openPayroll);
$month = (string)$filters['month'];
$shown = count($rows);
$first = min($pageSize, $shown);
$monthDt = DateTime::createFromFormat('Y-m-d', $month . '-01');
$monthLabel = $monthDt ? $monthDt->format('F Y') : $month;
$deptLabel = static function (array $row): string {
    if (($row['dept_code'] ?? '') !== '') {
        return (string)$row['dept_code'];
    }
    return ($row['dept_name'] ?? '') !== '' ? (string)$row['dept_name'] : '—';
};
$statusClass = static function (string $key): string {
    return match ($key) {
        'paid' => 'is-ok',
        'pending' => 'is-warn',
        'on_hold' => 'is-bad',
        default => 'is-muted',
    };
};
?>
<div class="adm-pay">
  <div class="adm-pay-head">
    <div>
      <h2>Salary &amp; Payroll</h2>
      <p>Manage faculty salaries, monthly payroll, payment status, and salary history</p>
    </div>
    <div class="adm-pay-head-actions">
      <button class="btn btn-ghost btn-sm" type="button" id="payAddOpen">+ Add Salary</button>
      <button class="btn btn-primary btn-sm" type="button" id="payRunOpen">Run Payroll</button>
    </div>
  </div>

  <div class="adm-pay-kpis">
    <div class="adm-pay-kpi">
      <strong><?= e(fee_money((float)$summary['total'])) ?></strong>
      <span>Total payroll</span>
    </div>
    <div class="adm-pay-kpi is-ok">
      <strong><?= (int)$summary['processed'] ?></strong>
      <span>Salaries processed</span>
    </div>
    <div class="adm-pay-kpi is-warn">
      <strong><?= (int)$summary['waiting'] ?></strong>
      <span>Pending / on hold</span>
    </div>
    <div class="adm-pay-kpi">
      <strong><?= e(fee_money((float)$summary['ytd'])) ?></strong>
      <span>YTD salary spend</span>
      <small><?= e((string)$summary['ytd_label']) ?></small>
    </div>
  </div>

  <form class="adm-pay-bar" method="get" action="<?= e(url('/admin/salary')) ?>">
    <label class="adm-pay-search">
      <span class="sr-only">Search faculty</span>
      <input type="search" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Search faculty name or employee ID">
    </label>
    <label class="adm-pay-month">
      <span class="sr-only">Month</span>
      <input type="month" name="month" value="<?= e($month) ?>" aria-label="Month">
    </label>
    <select name="department_id" aria-label="Department">
      <option value="">All departments</option>
      <?php foreach ($departmentOptions as $dept): ?>
        <option value="<?= (int)$dept['id'] ?>" <?= (int)$filters['department_id'] === (int)$dept['id'] ? 'selected' : '' ?>><?= e((string)$dept['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="faculty_id" aria-label="Faculty">
      <option value="">All faculty</option>
      <?php foreach ($faculty as $member): ?>
        <option value="<?= (int)$member['id'] ?>" <?= (int)$filters['faculty_id'] === (int)$member['id'] ? 'selected' : '' ?>><?= e((string)$member['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" aria-label="Payment status">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-ghost" type="submit">Search</button>
  </form>

  <div class="panel adm-pay-table">
    <?php if (!$rows): ?>
      <div class="empty">No salary records for <?= e($monthLabel) ?>. Use Add Salary or Run Payroll to create this month. Earlier months stay unchanged.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Faculty</th>
              <th>Employee ID</th>
              <th>Department</th>
              <th>Qualification</th>
              <th>Salary</th>
              <th>Month</th>
              <th>Payment date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $row): ?>
            <tr class="adm-pay-row<?= $i >= $pageSize ? ' is-extra' : '' ?>"<?= $i >= $pageSize ? ' hidden' : '' ?>>
              <td>
                <div class="adm-pay-person">
                  <span class="adm-pay-avatar"><?= e((string)$row['initials']) ?></span>
                  <strong><?= e((string)$row['name']) ?></strong>
                </div>
              </td>
              <td><?= e($row['employee_id'] !== '' ? (string)$row['employee_id'] : '—') ?></td>
              <td><?= e($deptLabel($row)) ?></td>
              <td><?= e($row['qualification'] !== '' ? (string)$row['qualification'] : '—') ?></td>
              <td><?= e(fee_money((float)$row['amount'])) ?></td>
              <td><?= e((string)$row['month_label']) ?></td>
              <td><?= e((string)$row['payment_label']) ?></td>
              <td><span class="adm-pay-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($statuses[$row['status']] ?? '') ?></span></td>
              <td class="adm-pay-actions">
                <a class="adm-pay-view" href="<?= e(url('/admin/salary/history?faculty_id=' . (int)$row['faculty_id'] . '&month=' . rawurlencode($month))) ?>">History</a>
                <button class="adm-pay-view" type="button" data-pay-edit
                  data-id="<?= (int)$row['id'] ?>"
                  data-name="<?= e((string)$row['name']) ?>"
                  data-month="<?= e((string)$row['month']) ?>"
                  data-amount="<?= e((string)$row['amount']) ?>"
                  data-date="<?= e((string)($row['payment_date'] ?? '')) ?>"
                  data-status="<?= e((string)$row['status']) ?>">Edit</button>
                <button class="adm-pay-view is-delete" type="button" data-pay-delete
                  data-id="<?= (int)$row['id'] ?>"
                  data-name="<?= e((string)$row['name']) ?>"
                  data-month-label="<?= e((string)$row['month_label']) ?>">Delete</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="adm-pay-foot">
        <span id="admPayCount">Showing <?= (int)$first ?> of <?= (int)$shown ?> salary records · <?= e($monthLabel) ?></span>
        <?php if ($shown > $pageSize): ?>
          <button class="btn btn-sm btn-ghost" type="button" id="admPayMore">Load more</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <section class="adm-pay-depts">
    <h3>Salary by Department</h3>
    <p><?= e($monthLabel) ?></p>
    <?php if (!$departments): ?>
      <div class="empty">No department totals yet for this month.</div>
    <?php else: ?>
      <ul>
        <?php foreach ($departments as $dept): ?>
          <li>
            <div class="adm-pay-dept-top">
              <strong><?= e((string)$dept['label']) ?></strong>
              <span><?= e(fee_money((float)$dept['total'])) ?></span>
            </div>
            <div class="adm-pay-barline" aria-hidden="true"><span style="width: <?= e((string)$dept['width']) ?>%"></span></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<dialog class="adm-pay-dialog" id="payAddDialog">
  <form method="post" action="<?= e(url('/admin/salary')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_salary">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-pay-dialog-h">
      <div>
        <h2>Add Salary</h2>
        <p>The record is stored for this faculty member and month only.</p>
      </div>
      <button class="icon-btn" type="button" data-pay-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <label>Faculty
      <input type="search" id="payFacultyFilter" placeholder="Search faculty name or employee ID">
      <select name="faculty_user_id" id="payFaculty" required>
        <option value="">Select faculty</option>
        <?php foreach ($faculty as $member): ?>
          <option value="<?= (int)$member['id'] ?>"
            data-employee="<?= e($member['employee_id'] !== '' ? (string)$member['employee_id'] : '—') ?>"
            data-dept="<?= e($deptLabel($member)) ?>"
            data-qualification="<?= e($member['qualification'] !== '' ? (string)$member['qualification'] : '—') ?>">
            <?= e((string)$member['name']) ?><?= $member['employee_id'] !== '' ? ' · ' . e((string)$member['employee_id']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="adm-pay-meta" id="payFacultyMeta">
      <div><span>Employee ID</span><strong id="payEmp">—</strong></div>
      <div><span>Department</span><strong id="payDept">—</strong></div>
      <div><span>Qualification</span><strong id="payQual">—</strong></div>
    </div>
    <div class="form-row two">
      <label>Salary amount<input name="amount" inputmode="decimal" required placeholder="95000"></label>
      <label>Salary month<input type="month" name="salary_month" required value="<?= e($month) ?>"></label>
    </div>
    <div class="form-row two">
      <label>Payment date<input type="date" name="payment_date"></label>
      <label>Payment status
        <select name="status" required>
          <?php foreach ($statuses as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $key === 'pending' ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="adm-pay-dialog-actions">
      <button class="btn btn-ghost" type="button" data-pay-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Add Salary</button>
    </div>
  </form>
</dialog>

<dialog class="adm-pay-dialog adm-pay-dialog-wide" id="payRunDialog">
  <form method="post" action="<?= e(url('/admin/salary')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="run_payroll">
    <input type="hidden" name="return_to" value="<?= e('/admin/salary?month=' . rawurlencode($month)) ?>">
    <div class="adm-pay-dialog-h">
      <div>
        <h2>Run Payroll</h2>
        <p>Review faculty and amounts, then create this month. Existing records for the month are skipped.</p>
      </div>
      <button class="icon-btn" type="button" data-pay-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <div class="form-row two">
      <label>Salary month
        <input type="month" name="salary_month" id="payRunMonth" required value="<?= e($month) ?>">
      </label>
      <label>Payment date
        <input type="date" name="payment_date">
      </label>
    </div>
    <p class="adm-pay-hint">Payment date is required only for rows marked Paid. It is not filled in automatically.</p>
    <div class="adm-pay-run">
      <?php if (!$faculty): ?>
        <div class="empty">No active faculty members to include.</div>
      <?php else: ?>
        <?php foreach ($faculty as $member): ?>
          <div class="adm-pay-run-row">
            <label class="adm-pay-run-who">
              <input type="checkbox" name="include[]" value="<?= (int)$member['id'] ?>" <?= empty($member['existing_id']) ? 'checked' : 'disabled' ?>>
              <span>
                <strong><?= e((string)$member['name']) ?></strong>
                <small><?= e($member['employee_id'] !== '' ? (string)$member['employee_id'] : 'No employee ID') ?> · <?= e($deptLabel($member)) ?><?= $member['qualification'] !== '' ? ' · ' . e((string)$member['qualification']) : '' ?></small>
              </span>
            </label>
            <?php if (!empty($member['existing_id'])): ?>
              <em>Already recorded</em>
            <?php else: ?>
              <input name="amount[<?= (int)$member['id'] ?>]" inputmode="decimal" placeholder="Amount" value="<?= $member['suggested'] !== null ? e((string)$member['suggested']) : '' ?>" aria-label="Salary amount for <?= e((string)$member['name']) ?>">
              <select name="status[<?= (int)$member['id'] ?>]" aria-label="Status for <?= e((string)$member['name']) ?>">
                <?php foreach ($statuses as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= $key === 'pending' ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="adm-pay-dialog-actions">
      <button class="btn btn-ghost" type="button" data-pay-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Process payroll</button>
    </div>
  </form>
</dialog>

<dialog class="adm-pay-dialog" id="payEditDialog">
  <form method="post" action="<?= e(url('/admin/salary')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_salary">
    <input type="hidden" name="salary_id" id="payEditId">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-pay-dialog-h">
      <div>
        <h2>Edit Salary</h2>
        <p id="payEditName">This updates the payroll record only.</p>
      </div>
      <button class="icon-btn" type="button" data-pay-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <div class="form-row two">
      <label>Salary amount<input name="amount" id="payEditAmount" inputmode="decimal" required></label>
      <label>Salary month<input type="month" name="salary_month" id="payEditMonth" required></label>
    </div>
    <div class="form-row two">
      <label>Payment date<input type="date" name="payment_date" id="payEditDate"></label>
      <label>Payment status
        <select name="status" id="payEditStatus" required>
          <?php foreach ($statuses as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="adm-pay-dialog-actions">
      <button class="btn btn-ghost" type="button" data-pay-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>
</dialog>

<dialog class="adm-pay-dialog" id="payDeleteDialog">
  <form method="post" action="<?= e(url('/admin/salary')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_salary">
    <input type="hidden" name="salary_id" id="payDeleteId">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-pay-dialog-h">
      <div>
        <h2>Delete Salary Record?</h2>
        <p id="payDeleteText">This removes the selected monthly payroll record. The faculty account, department, and employee profile stay in place.</p>
      </div>
    </div>
    <div class="adm-pay-dialog-actions">
      <button class="btn btn-ghost" type="button" data-pay-close>Cancel</button>
      <button class="btn adm-pay-delete" type="submit">Delete</button>
    </div>
  </form>
</dialog>

<script>
(function () {
  var titleBox = document.querySelector('.topbar-title');
  if (titleBox) titleBox.hidden = true;
  function bindDialog(id, openId) {
    var dialog = document.getElementById(id);
    document.getElementById(openId)?.addEventListener('click', function () { dialog?.showModal(); });
    dialog?.querySelectorAll('[data-pay-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { dialog.close(); });
    });
    return dialog;
  }
  bindDialog('payAddDialog', 'payAddOpen');
  bindDialog('payRunDialog', 'payRunOpen');
  var editDialog = bindDialog('payEditDialog', '');
  var deleteDialog = bindDialog('payDeleteDialog', '');
  var faculty = document.getElementById('payFaculty');
  var filter = document.getElementById('payFacultyFilter');
  function showFaculty() {
    var opt = faculty && faculty.selectedOptions ? faculty.selectedOptions[0] : null;
    var emp = document.getElementById('payEmp');
    var dept = document.getElementById('payDept');
    var qual = document.getElementById('payQual');
    if (emp) emp.textContent = opt && opt.dataset.employee ? opt.dataset.employee : '—';
    if (dept) dept.textContent = opt && opt.dataset.dept ? opt.dataset.dept : '—';
    if (qual) qual.textContent = opt && opt.dataset.qualification ? opt.dataset.qualification : '—';
  }
  if (faculty && filter) {
    var all = [...faculty.options].map(function (opt) {
      return { value: opt.value, text: opt.text, employee: opt.dataset.employee || '', dept: opt.dataset.dept || '', qualification: opt.dataset.qualification || '' };
    });
    filter.addEventListener('input', function () {
      var q = filter.value.trim().toLowerCase();
      var current = faculty.value;
      faculty.innerHTML = '';
      all.forEach(function (opt) {
        if (opt.value !== '' && q !== '' && !opt.text.toLowerCase().includes(q)) return;
        var node = document.createElement('option');
        node.value = opt.value;
        node.textContent = opt.text;
        if (opt.employee) node.dataset.employee = opt.employee;
        if (opt.dept) node.dataset.dept = opt.dept;
        if (opt.qualification) node.dataset.qualification = opt.qualification;
        faculty.appendChild(node);
      });
      if ([...faculty.options].some(function (opt) { return opt.value === current; })) faculty.value = current;
      showFaculty();
    });
    faculty.addEventListener('change', showFaculty);
  }
  document.querySelectorAll('[data-pay-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('payEditId').value = btn.dataset.id || '';
      document.getElementById('payEditName').textContent = btn.dataset.name || 'This updates the payroll record only.';
      document.getElementById('payEditAmount').value = btn.dataset.amount || '';
      document.getElementById('payEditMonth').value = btn.dataset.month || '';
      document.getElementById('payEditDate').value = btn.dataset.date || '';
      document.getElementById('payEditStatus').value = btn.dataset.status || 'pending';
      editDialog?.showModal();
    });
  });
  document.querySelectorAll('[data-pay-delete]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('payDeleteId').value = btn.dataset.id || '';
      var text = document.getElementById('payDeleteText');
      if (text) {
        text.textContent = 'This will remove the ' + (btn.dataset.monthLabel || 'selected') + ' salary record for ' + (btn.dataset.name || 'this faculty member') + '. The faculty account is not deleted.';
      }
      deleteDialog?.showModal();
    });
  });
  var runMonth = document.getElementById('payRunMonth');
  runMonth?.addEventListener('change', function () {
    if (!runMonth.value) return;
    var url = new URL(window.location.href);
    url.searchParams.set('month', runMonth.value);
    url.searchParams.set('payroll', '1');
    window.location.href = url.toString();
  });
  document.getElementById('admPayMore')?.addEventListener('click', function () {
    document.querySelectorAll('.adm-pay-row.is-extra').forEach(function (row) { row.hidden = false; });
    this.hidden = true;
    var total = document.querySelectorAll('.adm-pay-row').length;
    var label = document.getElementById('admPayCount');
    if (label) label.textContent = 'Showing ' + total + ' of ' + total + ' salary records';
  });
  <?php if ($openPayroll): ?>
  document.getElementById('payRunDialog')?.showModal();
  <?php endif; ?>
})();
</script>
