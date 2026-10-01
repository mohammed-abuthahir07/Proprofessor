<?php
/** @var array<string,mixed> $faculty */
/** @var list<array<string,mixed>> $rows */
/** @var array<string,string> $statuses */
/** @var string $returnTo */
/** @var string $back */
$faculty = $faculty ?? [];
$rows = $rows ?? [];
$statuses = $statuses ?? [];
$returnTo = (string)($returnTo ?? '/admin/salary');
$back = (string)($back ?? '/admin/salary');
$dept = ($faculty['dept_code'] ?? '') !== '' ? (string)$faculty['dept_code'] : ((($faculty['dept_name'] ?? '') !== '') ? (string)$faculty['dept_name'] : '—');
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
      <a class="adm-pay-back" href="<?= e(url($back)) ?>">← Salary &amp; Payroll</a>
      <h2><?= e((string)($faculty['name'] ?? 'Faculty')) ?></h2>
      <p>Monthly salary history stays separate for each month.</p>
    </div>
  </div>
  <div class="adm-pay-meta">
    <div><span>Employee ID</span><strong><?= e(($faculty['employee_id'] ?? '') !== '' ? (string)$faculty['employee_id'] : '—') ?></strong></div>
    <div><span>Department</span><strong><?= e($dept) ?></strong></div>
    <div><span>Qualification</span><strong><?= e(($faculty['qualification'] ?? '') !== '' ? (string)$faculty['qualification'] : '—') ?></strong></div>
  </div>
  <div class="panel adm-pay-table">
    <?php if (!$rows): ?>
      <div class="empty">No salary records for this faculty member yet.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Month</th>
              <th>Salary</th>
              <th>Payment date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e((string)$row['month_label']) ?></td>
              <td><?= e(fee_money((float)$row['amount'])) ?></td>
              <td><?= e((string)$row['payment_label']) ?></td>
              <td><span class="adm-pay-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($statuses[$row['status']] ?? '') ?></span></td>
              <td class="adm-pay-actions">
                <button class="adm-pay-view" type="button" data-pay-edit
                  data-id="<?= (int)$row['id'] ?>"
                  data-name="<?= e((string)$row['month_label']) ?>"
                  data-month="<?= e((string)$row['month']) ?>"
                  data-amount="<?= e((string)$row['amount']) ?>"
                  data-date="<?= e((string)($row['payment_date'] ?? '')) ?>"
                  data-status="<?= e((string)$row['status']) ?>">Edit</button>
                <button class="adm-pay-view is-delete" type="button" data-pay-delete
                  data-id="<?= (int)$row['id'] ?>"
                  data-name="<?= e((string)($faculty['name'] ?? '')) ?>"
                  data-month-label="<?= e((string)$row['month_label']) ?>">Delete</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

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
        <p id="payDeleteText">This removes the selected monthly payroll record. The faculty account stays in place.</p>
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
  function dialog(id) {
    var node = document.getElementById(id);
    node?.querySelectorAll('[data-pay-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { node.close(); });
    });
    return node;
  }
  var editDialog = dialog('payEditDialog');
  var deleteDialog = dialog('payDeleteDialog');
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
})();
</script>
