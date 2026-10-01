<?php
/** @var array $stats */
/** @var array<string,mixed> $overview */
/** @var array{days:list<array{label:string,percent:?float}>,low:?array{label:string,percent:float}} $week */
/** @var list<array{tone:string,text:string,meta:string,href:string}> $actions */
/** @var list<array<string,mixed>> $departments */
$user = \Auth::user();
$firstName = explode(' ', (string)($user['full_name'] ?? 'Admin'))[0];
$overview = $overview ?? [];
$week = $week ?? ['days' => [], 'low' => null];
$actions = $actions ?? [];
$departments = $departments ?? [];
$instName = trim((string)($inst['name'] ?? 'Institution'));

$pct = static function (?float $n): string {
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
    if ($n >= 85) {
        return 'is-ok';
    }
    if ($n >= 75) {
        return 'is-warn';
    }
    return 'is-bad';
};
// Compact Indian money for the summary cards. Exact figures stay on their own pages.
$short = static function (float $amount) {
    if ($amount >= 10000000) {
        return '₹' . rtrim(rtrim(number_format($amount / 10000000, 2, '.', ''), '0'), '.') . 'Cr';
    }
    if ($amount >= 100000) {
        return '₹' . rtrim(rtrim(number_format($amount / 100000, 2, '.', ''), '0'), '.') . 'L';
    }
    return fee_money($amount);
};
$fees = $overview['fees'] ?? ['collected' => 0.0, 'pending' => 0.0, 'students' => 0, 'overdue' => 0];
$plans = $overview['plans'] ?? ['total' => 0, 'approved' => 0, 'percent' => null];
$exam = $overview['exam'] ?? ['days' => null, 'label' => '—', 'hint' => 'No exam date set'];
$year = (string)($overview['academic_year'] ?? '');
?>
<div class="adm-ov">
  <header class="adm-ov-head">
    <div>
      <h2><?= icon('building', 'icon-inline') ?> Institution Overview</h2>
      <p><?= e((string)($subtitle ?? $instName)) ?></p>
    </div>
    <span class="adm-ov-asof">As of <?= e((string)($overview['as_of'] ?? '')) ?></span>
  </header>

  <div class="adm-ov-kpis">
    <div class="adm-ov-kpi is-ok">
      <strong><?= number_format((int)$stats['students']) ?></strong>
      <span>Total Students</span>
      <small><?= $fees['students'] > 0 ? (int)$fees['students'] . ' with fee records' : 'Enrolled portal users' ?></small>
    </div>
    <div class="adm-ov-kpi is-info">
      <strong><?= number_format((int)($overview['faculty'] ?? 0)) ?></strong>
      <span>Total Faculty</span>
      <small><?= (int)($overview['departments'] ?? 0) ?> <?= (int)($overview['departments'] ?? 0) === 1 ? 'department' : 'departments' ?></small>
    </div>
    <div class="adm-ov-kpi <?= e($tone($overview['attendance'] ?? null)) ?>">
      <strong><?= e($pct($overview['attendance'] ?? null)) ?></strong>
      <span>Avg Attendance</span>
      <small><?= ($overview['attendance'] ?? null) === null ? 'No sessions recorded' : 'Across departments' ?></small>
    </div>
    <div class="adm-ov-kpi is-bad">
      <strong><?= e($short((float)$fees['pending'])) ?></strong>
      <span>Fee Pending</span>
      <small><?= (int)$fees['overdue'] > 0 ? (int)$fees['overdue'] . ' ' . ((int)$fees['overdue'] === 1 ? 'student' : 'students') . ' with dues' : 'No dues recorded' ?></small>
    </div>
    <div class="adm-ov-kpi is-ok">
      <strong><?= e($short((float)$fees['collected'])) ?></strong>
      <span>Fee Collected</span>
      <small><?= $year !== '' ? e($year) : 'Recorded payments' ?></small>
    </div>
    <div class="adm-ov-kpi is-warn">
      <strong><?= number_format((int)$plans['total']) ?></strong>
      <span>Plans Submitted</span>
      <small><?= $plans['percent'] === null ? 'None yet' : e($pct($plans['percent'])) . ' approved' ?></small>
    </div>
    <div class="adm-ov-kpi is-info">
      <strong><?= (int)($overview['departments'] ?? 0) ?></strong>
      <span>Departments</span>
      <small><?= number_format((int)$stats['users']) ?> accounts</small>
    </div>
    <div class="adm-ov-kpi is-accent">
      <strong><?= e((string)$exam['label']) ?></strong>
      <span>Exam Start Date</span>
      <small><?= e((string)$exam['hint']) ?></small>
    </div>
  </div>

  <div class="adm-ov-split">
    <section class="adm-ov-card">
      <h3><?= icon('chart', 'icon-inline') ?> Attendance Trend (This Week)</h3>
      <?php
      $hasWeek = false;
      foreach ($week['days'] as $day) {
          if ($day['percent'] !== null) {
              $hasWeek = true;
              break;
          }
      }
      ?>
      <?php if (!$hasWeek): ?>
        <div class="empty">No attendance sessions recorded this week.</div>
      <?php else: ?>
        <div class="adm-ov-chart">
          <?php foreach ($week['days'] as $day): ?>
            <div class="adm-ov-bar <?= e($tone($day['percent'])) ?>">
              <div class="adm-ov-bar-track">
                <?php if ($day['percent'] !== null): ?>
                  <span style="height: <?= e((string)max(4, (float)$day['percent'])) ?>%" title="<?= e($pct($day['percent'])) ?>"></span>
                <?php endif; ?>
              </div>
              <em><?= e(mb_substr((string)$day['label'], 0, 1)) ?></em>
              <small><?= $day['percent'] === null ? '—' : e($pct($day['percent'])) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
        <ul class="adm-ov-legend">
          <li class="is-ok">85% and above</li>
          <li class="is-warn">75–84%</li>
          <li class="is-bad">Below 75%</li>
        </ul>
      <?php endif; ?>
      <?php if ($week['low'] !== null): ?>
        <p class="adm-ov-note">Department with lowest attendance:
          <strong class="<?= e($tone($week['low']['percent'])) ?>"><?= e((string)$week['low']['label']) ?> (<?= e($pct($week['low']['percent'])) ?>)</strong>
        </p>
      <?php endif; ?>
    </section>

    <section class="adm-ov-card">
      <h3><?= icon('alert', 'icon-inline') ?> Action Required</h3>
      <?php if (!$actions): ?>
        <div class="empty">Nothing needs attention right now.</div>
      <?php else: ?>
        <ul class="adm-ov-actions">
          <?php foreach ($actions as $item): ?>
            <li class="<?= e((string)$item['tone']) ?>">
              <a href="<?= e(url((string)$item['href'])) ?>">
                <span><?= e((string)$item['text']) ?></span>
                <?php if ((string)$item['meta'] !== ''): ?><em><?= e((string)$item['meta']) ?></em><?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <section class="adm-ov-card adm-ov-table">
    <h3><?= icon('grid', 'icon-inline') ?> Department Quick Stats</h3>
    <?php if (!$departments): ?>
      <div class="empty">No departments configured yet.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Department</th>
              <th>Students</th>
              <th>Faculty</th>
              <th>Attendance</th>
              <th>Plans done</th>
              <th>Fee pending</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($departments as $dept): ?>
            <tr>
              <td>
                <strong><?= e((string)$dept['label']) ?></strong>
                <?php if ((string)$dept['code'] !== '' && (string)$dept['code'] !== (string)$dept['name']): ?>
                  <small><?= e((string)$dept['name']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= (int)$dept['students'] ?></td>
              <td><?= (int)$dept['faculty'] ?></td>
              <td class="<?= e($tone($dept['attendance'])) ?>"><?= e($pct($dept['attendance'])) ?></td>
              <td class="<?= e($tone($dept['plans'])) ?>"><?= e($pct($dept['plans'])) ?></td>
              <td class="<?= $dept['fee_pending'] === null || (float)$dept['fee_pending'] <= 0 ? 'is-muted' : 'is-bad' ?>">
                <?= $dept['fee_pending'] === null ? 'Not recorded' : e(fee_money((float)$dept['fee_pending'])) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="adm-ov-card">
    <h3><?= icon('spark', 'icon-inline') ?> Quick Actions</h3>
    <div class="adm-ov-quick">
      <a href="<?= e(url('/admin/users')) ?>"><?= icon('users') ?> Users &amp; roles</a>
      <a href="<?= e(url('/admin/features')) ?>"><?= icon('puzzle') ?> Feature flags</a>
      <a href="<?= e(url('/admin/finance')) ?>"><?= icon('finance') ?> Expense</a>
      <a href="<?= e(url('/admin/formulas')) ?>"><?= icon('formula') ?> Marks formulas</a>
      <a href="<?= e(url('/admin/naac')) ?>"><?= icon('file') ?> NAAC builder</a>
    </div>
  </section>
</div>
