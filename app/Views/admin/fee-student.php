<?php
/** @var array<string,mixed> $student */
/** @var string $academicYear */
/** @var list<array<string,mixed>> $fees */
/** @var string $overall */
/** @var list<array<string,mixed>> $outstanding */
/** @var list<string> $years */
/** @var array<string,string> $types */
$student = $student ?? [];
$academicYear = (string)($academicYear ?? '');
$fees = $fees ?? [];
$outstanding = $outstanding ?? [];
$years = $years ?? [];
$types = $types ?? [];
$level = (int)($student['academic_year_level'] ?? 0);
if ($level < 1) {
    $level = (int)($student['class_year'] ?? 0);
}
$section = trim((string)($student['class_section'] ?? ''));
$dept = trim((string)($student['dept_name'] ?? ''));
if ($dept === '') {
    $dept = trim((string)($student['dept_code'] ?? ''));
}
$register = trim((string)($student['register_no'] ?? ''));
$returnTo = '/admin/fee-collection/student?student_id=' . (int)($student['id'] ?? 0) . '&academic_year=' . rawurlencode($academicYear);
$overallLabel = match ($overall) {
    'paid' => 'Fully Paid',
    'partial' => 'Partially Paid',
    'pending' => 'Pending',
    default => 'Not Configured',
};
$feeLabel = static function (string $status): string {
    return match ($status) {
        'paid' => 'Paid',
        'partial' => 'Partially Paid',
        default => 'Pending',
    };
};
$when = static function (string $value): string {
    $dt = DateTime::createFromFormat('Y-m-d', substr($value, 0, 10));
    return $dt ? $dt->format('j M Y') : '—';
};
?>
<div class="adm-fee">
  <div class="adm-fee-detail-head">
    <div>
      <a class="adm-fee-back" href="<?= e(url('/admin/fee-collection?academic_year=' . rawurlencode($academicYear))) ?>">← Fee Collection</a>
      <h2>Student Fee Details</h2>
      <p><?= e((string)($student['full_name'] ?? '')) ?> · <?= e($academicYear) ?></p>
    </div>
    <div class="adm-fee-detail-actions">
      <?php if ($outstanding !== []): ?>
        <form method="post" action="<?= e(url('/admin/fee-collection')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="remind_all">
          <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
          <input type="hidden" name="academic_year" value="<?= e($academicYear) ?>">
          <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
          <button class="btn btn-sm btn-ghost" type="submit">Send Reminder for All Pending Fees</button>
        </form>
      <?php endif; ?>
      <button class="btn btn-sm btn-primary" type="button" id="feeAddOpen">+ Add Fee</button>
    </div>
  </div>

  <div class="adm-fee-meta">
    <div><span>Register no</span><strong><?= e($register !== '' ? $register : '—') ?></strong></div>
    <div><span>Department</span><strong><?= e($dept !== '' ? $dept : '—') ?></strong></div>
    <div><span>Year</span><strong><?= e($level > 0 ? subject_year_label($level) : '—') ?></strong></div>
    <div><span>Section</span><strong><?= e($section !== '' ? $section : '—') ?></strong></div>
    <div><span>Academic year</span><strong><?= e($academicYear) ?></strong></div>
    <div><span>Overall</span><strong class="adm-fee-overall is-<?= e((string)$overall) ?>"><?= e($overallLabel) ?></strong></div>
  </div>

  <?php if (!$fees): ?>
    <div class="panel empty">No fee records for <?= e($academicYear) ?>. Fees that are not added do not appear as unpaid.</div>
  <?php else: ?>
    <div class="adm-fee-cards">
      <?php foreach ($fees as $fee): ?>
        <article class="adm-fee-card">
          <header>
            <h3><?= e((string)$fee['label']) ?></h3>
            <span class="adm-fee-badge is-<?= e((string)$fee['status'] === 'paid' ? 'ok' : ((string)$fee['status'] === 'partial' ? 'warn' : 'bad')) ?>"><?= e($feeLabel((string)$fee['status'])) ?></span>
          </header>
          <dl>
            <div><dt>Total</dt><dd><?= e(fee_money((float)$fee['total'])) ?></dd></div>
            <div><dt>Paid</dt><dd><?= e(fee_money((float)$fee['paid'])) ?></dd></div>
            <div><dt>Remaining</dt><dd><?= e(fee_money((float)$fee['remaining'])) ?></dd></div>
            <div><dt>Due date</dt><dd><?= e($fee['due_date'] !== '' ? $when((string)$fee['due_date']) : '—') ?></dd></div>
          </dl>
          <?php if ($fee['notes'] !== ''): ?><p class="adm-fee-notes"><?= e((string)$fee['notes']) ?></p><?php endif; ?>
          <?php if ($fee['payments']): ?>
            <h4>Payment history</h4>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Date</th><th>Amount</th><th>Reference</th><th>Recorded by</th></tr></thead>
                <tbody>
                <?php foreach ($fee['payments'] as $payment): ?>
                  <tr>
                    <td><?= e($when((string)$payment['payment_date'])) ?></td>
                    <td><?= e(fee_money((float)$payment['amount'])) ?></td>
                    <td><?= e(trim((string)($payment['payment_reference'] ?? '')) !== '' ? (string)$payment['payment_reference'] : '—') ?></td>
                    <td><?= e(trim((string)($payment['recorder'] ?? '')) !== '' ? (string)$payment['recorder'] : 'Admin') ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="adm-fee-notes">No payments recorded yet.</p>
          <?php endif; ?>
          <div class="adm-fee-card-actions">
            <?php if ($fee['status'] !== 'paid'): ?>
              <button class="btn btn-sm btn-primary" type="button" data-pay="<?= (int)$fee['id'] ?>" data-label="<?= e((string)$fee['label']) ?>" data-remaining="<?= e(fee_money((float)$fee['remaining'])) ?>">Record Payment</button>
              <form method="post" action="<?= e(url('/admin/fee-collection')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remind">
                <input type="hidden" name="fee_id" value="<?= (int)$fee['id'] ?>">
                <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                <input type="hidden" name="academic_year" value="<?= e($academicYear) ?>">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <button class="btn btn-sm btn-ghost" type="submit">Send Reminder</button>
              </form>
            <?php endif; ?>
            <button class="btn btn-sm btn-ghost" type="button"
              data-edit="<?= (int)$fee['id'] ?>"
              data-label="<?= e((string)$fee['label']) ?>"
              data-total="<?= e((string)$fee['total']) ?>"
              data-due="<?= e((string)$fee['due_date']) ?>"
              data-notes="<?= e((string)$fee['notes']) ?>">Edit</button>
            <button class="btn btn-sm btn-ghost adm-fee-delete" type="button" data-delete="<?= (int)$fee['id'] ?>" data-label="<?= e((string)$fee['label']) ?>">Delete</button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<dialog class="adm-fee-dialog" id="feeAddDialog">
  <form method="post" action="<?= e(url('/admin/fee-collection')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_fee">
    <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-fee-dialog-h">
      <div><h2>Add Fee</h2><p><?= e((string)$student['full_name']) ?> · only this fee type will apply.</p></div>
      <button class="icon-btn" type="button" data-fee-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <div class="form-row two">
      <label>Academic year
        <select name="academic_year" required>
          <?php foreach ($years as $option): ?>
            <option value="<?= e($option) ?>" <?= $academicYear === $option ? 'selected' : '' ?>><?= e($option) ?></option>
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

<dialog class="adm-fee-dialog" id="feePayDialog">
  <form method="post" action="<?= e(url('/admin/fee-collection')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="record_payment">
    <input type="hidden" name="fee_id" id="feePayId">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-fee-dialog-h">
      <div><h2>Record Payment</h2><p id="feePayHint"></p></div>
      <button class="icon-btn" type="button" data-fee-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <div class="form-row two">
      <label>Amount paid<input name="amount" inputmode="decimal" required placeholder="20000"></label>
      <label>Payment date<input type="date" name="payment_date" required></label>
    </div>
    <label>Payment reference / receipt number<input name="payment_reference" placeholder="Optional"></label>
    <label>Notes<textarea name="notes" rows="3" placeholder="Optional"></textarea></label>
    <div class="adm-fee-dialog-actions">
      <button class="btn btn-ghost" type="button" data-fee-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Record Payment</button>
    </div>
  </form>
</dialog>

<dialog class="adm-fee-dialog" id="feeEditDialog">
  <form method="post" action="<?= e(url('/admin/fee-collection')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_fee">
    <input type="hidden" name="fee_id" id="feeEditId">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-fee-dialog-h">
      <div><h2>Edit Fee</h2><p id="feeEditHint"></p></div>
      <button class="icon-btn" type="button" data-fee-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <div class="form-row two">
      <label>Total amount<input name="total_amount" id="feeEditTotal" inputmode="decimal" required></label>
      <label>Due date<input type="date" name="due_date" id="feeEditDue"></label>
    </div>
    <label>Notes<textarea name="notes" id="feeEditNotes" rows="3"></textarea></label>
    <div class="adm-fee-dialog-actions">
      <button class="btn btn-ghost" type="button" data-fee-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>
</dialog>

<dialog class="adm-fee-dialog" id="feeDeleteDialog">
  <form method="post" action="<?= e(url('/admin/fee-collection')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_fee">
    <input type="hidden" name="fee_id" id="feeDeleteId">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-fee-dialog-h">
      <div>
        <h2>Delete Fee Record?</h2>
        <p id="feeDeleteHint">This will remove the fee record and its associated payment information.</p>
      </div>
      <button class="icon-btn" type="button" data-fee-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <div class="adm-fee-dialog-actions">
      <button class="btn btn-ghost" type="button" data-fee-close>Cancel</button>
      <button class="btn btn-primary adm-fee-delete" type="submit">Delete</button>
    </div>
  </form>
</dialog>
<script>
(function () {
  const open = function (id) { document.getElementById(id)?.showModal(); };
  document.getElementById('feeAddOpen')?.addEventListener('click', function () { open('feeAddDialog'); });
  document.querySelectorAll('[data-fee-close]').forEach(function (btn) {
    btn.addEventListener('click', function () { btn.closest('dialog')?.close(); });
  });
  document.querySelectorAll('[data-pay]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('feePayId').value = btn.getAttribute('data-pay') || '';
      document.getElementById('feePayHint').textContent = (btn.getAttribute('data-label') || 'Fee') + ' · remaining ' + (btn.getAttribute('data-remaining') || '');
      open('feePayDialog');
    });
  });
  document.querySelectorAll('[data-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('feeEditId').value = btn.getAttribute('data-edit') || '';
      document.getElementById('feeEditHint').textContent = btn.getAttribute('data-label') || '';
      document.getElementById('feeEditTotal').value = btn.getAttribute('data-total') || '';
      document.getElementById('feeEditDue').value = btn.getAttribute('data-due') || '';
      document.getElementById('feeEditNotes').value = btn.getAttribute('data-notes') || '';
      open('feeEditDialog');
    });
  });
  document.querySelectorAll('[data-delete]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('feeDeleteId').value = btn.getAttribute('data-delete') || '';
      document.getElementById('feeDeleteHint').textContent = 'This will remove the ' + (btn.getAttribute('data-label') || 'fee') + ' record and its associated payment information.';
      open('feeDeleteDialog');
    });
  });
})();
</script>
