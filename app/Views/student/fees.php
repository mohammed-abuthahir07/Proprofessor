<?php
/** @var string $studentName */
/** @var string $registerNo */
/** @var string $department */
/** @var string $yearLabel */
/** @var string $academicYear */
/** @var array<string,mixed> $snapshot */
$studentName = (string)($studentName ?? '');
$registerNo = (string)($registerNo ?? '');
$department = (string)($department ?? '');
$yearLabel = (string)($yearLabel ?? '');
$academicYear = (string)($academicYear ?? '');
$snapshot = $snapshot ?? ['fees' => [], 'total' => 0, 'paid' => 0, 'pending' => 0, 'overall' => 'none', 'reminders' => []];
$fees = $snapshot['fees'] ?? [];
$overall = (string)($snapshot['overall'] ?? 'none');
$when = static function (string $value): string {
    $value = substr(trim($value), 0, 10);
    if ($value === '') {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt ? $dt->format('d M Y') : $value;
};
?>
<div class="stu-fee">
  <div class="stu-fee-head">
    <div>
      <a class="stu-fee-back" href="<?= e(url('/student/dashboard')) ?>">← Dashboard</a>
      <h2>Fee History</h2>
      <p>Read-only copy of the fee records College Admin entered for your account.</p>
    </div>
  </div>

  <div class="stu-fee-meta">
    <div><span>Student name</span><strong><?= e($studentName !== '' ? $studentName : '—') ?></strong></div>
    <div><span>Register number</span><strong><?= e($registerNo !== '' ? $registerNo : '—') ?></strong></div>
    <div><span>Department</span><strong><?= e($department !== '' ? $department : '—') ?></strong></div>
    <div><span>Year</span><strong><?= e($yearLabel !== '' ? $yearLabel : '—') ?></strong></div>
    <div><span>Academic year</span><strong><?= e($academicYear !== '' ? $academicYear : '—') ?></strong></div>
  </div>

  <div class="stu-fee-kpis">
    <div class="stu-fee-kpi">
      <strong><?= e(fee_money((float)$snapshot['total'])) ?></strong>
      <span>Total fees</span>
    </div>
    <div class="stu-fee-kpi is-ok">
      <strong><?= e(fee_money((float)$snapshot['paid'])) ?></strong>
      <span>Total paid</span>
    </div>
    <div class="stu-fee-kpi is-warn">
      <strong><?= e(fee_money((float)$snapshot['pending'])) ?></strong>
      <span>Total pending</span>
    </div>
    <div class="stu-fee-kpi is-<?= e($overall) ?>">
      <strong class="stu-fee-status"><?= e(student_fee_overall_label($overall)) ?></strong>
      <span>Overall payment status</span>
    </div>
  </div>

  <?php if ($snapshot['reminders']): ?>
    <div class="stu-fee-remind" role="status">
      <strong>Payment reminder</strong>
      <?php foreach ($snapshot['reminders'] as $line): ?>
        <p><?= e((string)$line) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="panel stu-fee-table">
    <h3>Fee records</h3>
    <?php if (!$fees): ?>
      <div class="empty">College Admin has not entered any fee records for your account. Missing fee types are not treated as unpaid.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Fee Type</th>
              <th>Academic Year</th>
              <th>Amount</th>
              <th>Paid Amount</th>
              <th>Pending</th>
              <th>Payment Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($fees as $fee): ?>
            <tr>
              <td><strong><?= e((string)$fee['label']) ?></strong></td>
              <td><?= e((string)$fee['academic_year']) ?></td>
              <td><?= e(fee_money((float)$fee['total'])) ?></td>
              <td><?= e(fee_money((float)$fee['paid'])) ?></td>
              <td><?= e(fee_money((float)$fee['remaining'])) ?></td>
              <td><?= e($when((string)$fee['last_payment_date'])) ?></td>
              <td><span class="stu-fee-badge is-<?= e((string)$fee['status']) ?>"><?= e(student_fee_status_label((string)$fee['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php foreach ($fees as $fee): ?>
    <?php if (($fee['payments'] ?? []) === []) { continue; } ?>
    <section class="panel stu-fee-history">
      <h3><?= e((string)$fee['label']) ?></h3>
      <p>Total fee: <?= e(fee_money((float)$fee['total'])) ?></p>
      <ul>
        <?php foreach ($fee['payments'] as $payment): ?>
          <li>
            <strong><?= e(fee_money((float)$payment['amount'])) ?></strong>
            <span><?= e($when((string)$payment['payment_date'])) ?></span>
            <?php if (trim((string)($payment['payment_reference'] ?? '')) !== ''): ?>
              <em><?= e((string)$payment['payment_reference']) ?></em>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="stu-fee-history-foot">
        <span>Total paid: <?= e(fee_money((float)$fee['paid'])) ?></span>
        <span>Remaining: <?= e(fee_money((float)$fee['remaining'])) ?></span>
      </div>
    </section>
  <?php endforeach; ?>
</div>
<script>
(function () {
  var titleBox = document.querySelector('.topbar-title');
  if (titleBox) titleBox.hidden = true;
})();
</script>
