<?php
/** @var list<array<string,mixed>> $cards */
/** @var array{attendance:?float,plans:?float} $summary */
$cards = $cards ?? [];
$summary = $summary ?? ['attendance' => null, 'plans' => null];

$fmtPct = static function (?float $n): string {
    if ($n === null) {
        return '—';
    }
    $text = rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    return ($text === '' ? '0' : $text) . '%';
};
$fmtMoney = static function (?float $n): string {
    if ($n === null) {
        return '—';
    }
    if ($n >= 10000000) {
        return '₹' . rtrim(rtrim(number_format($n / 10000000, 1, '.', ''), '0'), '.') . 'Cr';
    }
    if ($n >= 100000) {
        return '₹' . rtrim(rtrim(number_format($n / 100000, 1, '.', ''), '0'), '.') . 'L';
    }
    return '₹' . number_format($n, 0, '.', ',');
};
$tone = static function (?float $n, float $good = 75.0): string {
    if ($n === null) {
        return 'is-muted';
    }
    if ($n >= $good) {
        return 'is-ok';
    }
    if ($n >= 50) {
        return 'is-warn';
    }
    return 'is-bad';
};
?>
<div class="adm-dept">
  <div class="adm-dept-kpis">
    <div class="adm-dept-kpi <?= e($tone($summary['attendance'])) ?>">
      <strong><?= e($fmtPct($summary['attendance'])) ?></strong>
      <span>Avg attendance</span>
    </div>
    <div class="adm-dept-kpi <?= e($tone($summary['plans'])) ?>">
      <strong><?= e($fmtPct($summary['plans'])) ?></strong>
      <span>Avg plan completion</span>
    </div>
    <div class="adm-dept-kpi is-muted">
      <strong>—</strong>
      <span>Total fee pending</span>
      <em>Not recorded</em>
    </div>
  </div>

  <?php if (!$cards): ?>
    <div class="empty">No departments are set up for this college yet.</div>
  <?php else: ?>
    <div class="adm-dept-list">
      <?php foreach ($cards as $card): ?>
        <article class="adm-dept-card">
          <header class="adm-dept-head">
            <span class="adm-dept-mark is-<?= (int)$card['tone'] ?>"><?= e((string)$card['mark']) ?></span>
            <div class="adm-dept-title">
              <h2><?= e((string)$card['name']) ?></h2>
              <p>
                <?= $card['code'] !== '' ? e((string)$card['code']) : 'Department' ?>
                <?php if (!$card['active']): ?> · Inactive<?php endif; ?>
              </p>
            </div>
            <a class="adm-dept-link" href="<?= e(url('/admin/users?role=professor&department_id=' . (int)$card['id'])) ?>">View Faculty</a>
          </header>
          <div class="adm-dept-stats">
            <div class="adm-dept-stat is-students">
              <strong><?= (int)$card['students'] ?></strong>
              <span>Students</span>
            </div>
            <div class="adm-dept-stat is-faculty">
              <strong><?= (int)$card['faculty'] ?></strong>
              <span>Faculty</span>
            </div>
            <div class="adm-dept-stat <?= e($tone($card['attendance'])) ?>">
              <strong><?= e($fmtPct($card['attendance'])) ?></strong>
              <span>Attendance</span>
            </div>
            <div class="adm-dept-stat is-muted">
              <strong>—</strong>
              <span>Fee pending</span>
            </div>
            <div class="adm-dept-stat <?= e($tone($card['plans'])) ?>">
              <strong><?= e($fmtPct($card['plans'])) ?></strong>
              <span>Plans done</span>
            </div>
          </div>
          <footer class="adm-dept-foot">
            <span>Dept budget: <?= e($fmtMoney($card['budget'])) ?><?= $card['budget'] !== null && $card['budget_year'] !== '' ? ' · ' . e((string)$card['budget_year']) : '' ?></span>
            <span>HOD: <?= e($card['hod'] !== '' ? (string)$card['hod'] : 'Not assigned') ?></span>
          </footer>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
