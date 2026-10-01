<?php
/** @var array<string,mixed> $summary */
/** @var array{target:float,target_percent:float,months:int,rows:list<array{label:string,collected:float,percent:float}>} $months */
/** @var list<array<string,mixed>> $revenueBreakdown */
/** @var list<array{year:int,stored:bool,total:float,months:array<int,array{total:float,entries:int}>,topCategoryName:string,topCategoryTotal:float}> $yearArchives */
/** @var array{paid:float,outstanding:float,records:int} $payroll */
/** @var array{label:string} $window */
/** @var string $academicYear */
$summary = $summary ?? [];
$months = $months ?? ['target' => 0.0, 'target_percent' => 0.0, 'months' => 0, 'rows' => []];
$revenueBreakdown = $revenueBreakdown ?? [];
$yearArchives = is_array($yearArchives ?? null) ? $yearArchives : [];
$monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
$payroll = $payroll ?? ['paid' => 0.0, 'outstanding' => 0.0, 'records' => 0];
$academicYear = (string)($academicYear ?? '');
$windowLabel = (string)($window['label'] ?? '');

// Compact Indian money for headline cards. Exact rupees stay in the month cards.
$short = static function (float $amount): string {
    $sign = $amount < 0 ? '-' : '';
    $abs = abs($amount);
    if ($abs >= 10000000) {
        return $sign . '₹' . rtrim(rtrim(number_format($abs / 10000000, 2, '.', ''), '0'), '.') . ' Cr';
    }
    if ($abs >= 100000) {
        return $sign . '₹' . rtrim(rtrim(number_format($abs / 100000, 2, '.', ''), '0'), '.') . ' L';
    }
    return $sign . fee_money($abs);
};
$pct = static function (?float $n): string {
    if ($n === null) {
        return '—';
    }
    $text = rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    return ($text === '' ? '0' : $text) . '%';
};
$slices = ['is-tuition', 'is-bus', 'is-hostel', 'is-other'];
$revenueTotal = 0.0;
foreach ($revenueBreakdown as $slice) {
    $revenueTotal += (float)$slice['collected'];
}
$gradient = [];
$cursor = 0.0;
foreach ($revenueBreakdown as $i => $slice) {
    if ($revenueTotal <= 0) {
        break;
    }
    $sliceShare = ((float)$slice['collected'] * 100) / $revenueTotal;
    if ($sliceShare <= 0) {
        continue;
    }
    $colors = ['#a78bfa', '#38bdf8', '#fbbf24', '#4ade80'];
    $color = $colors[$i % 4];
    $gradient[] = $color . ' ' . round($cursor, 2) . '% ' . round($cursor + $sliceShare, 2) . '%';
    $cursor += $sliceShare;
}
?>
<div class="adm-fin">
  <header class="adm-fin-head">
    <div>
      <h2><?= icon('finance', 'icon-inline') ?> Finance Overview</h2>
      <p><?= $academicYear !== '' ? 'Academic Year ' . e($academicYear) . ' · ' : '' ?>All figures in INR</p>
    </div>
    <span class="adm-fin-asof"><?= e($windowLabel) ?></span>
  </header>

  <div class="adm-fin-kpis">
    <div class="adm-fin-kpi is-ok">
      <strong><?= e($short((float)($summary['revenue'] ?? 0))) ?></strong>
      <span>Total Revenue (YTD)</span>
      <small>Fee payments received<?= (float)($summary['billed'] ?? 0) > 0 ? ' · ' . e($pct(round(((float)$summary['revenue'] * 100) / (float)$summary['billed'], 1))) . ' of billed' : '' ?></small>
    </div>
    <div class="adm-fin-kpi is-bad">
      <strong><?= e($short((float)($summary['expenditure'] ?? 0))) ?></strong>
      <span>Total Expenditure (YTD)</span>
      <small>Expense records + paid payroll</small>
    </div>
    <div class="adm-fin-kpi is-warn">
      <strong><?= e($short((float)($summary['arrears'] ?? 0))) ?></strong>
      <span>Fee Pending</span>
      <small><?= (int)($summary['arrears_students'] ?? 0) > 0
        ? (int)$summary['arrears_students'] . ' ' . ((int)$summary['arrears_students'] === 1 ? 'student' : 'students') . ' with dues'
        : 'No dues recorded' ?></small>
    </div>
  </div>

  <div class="adm-fin-split">
    <section class="adm-fin-card">
      <h3><?= icon('chart', 'icon-inline') ?> Monthly Fee Collection vs Target</h3>
      <?php if (!$months['rows']): ?>
        <div class="empty">No fee payments recorded for this academic year yet.</div>
      <?php else: ?>
        <ul class="adm-fin-legend">
          <li class="is-collected">Collected</li>
          <li class="is-target">Target<?= (float)$months['target'] > 0 ? ' · ' . e($short((float)$months['target'])) . '/month' : '' ?></li>
        </ul>
        <div class="adm-fin-chart">
          <?php if ((float)$months['target_percent'] > 0): ?>
            <div class="adm-fin-target" style="bottom: <?= e((string)min(100, (float)$months['target_percent'])) ?>%">
              <span>Target</span>
            </div>
          <?php endif; ?>
          <?php foreach ($months['rows'] as $row): ?>
            <div class="adm-fin-bar">
              <b><?= (float)$row['collected'] > 0 ? e($short((float)$row['collected'])) : '' ?></b>
              <div class="adm-fin-bar-track">
                <?php if ((float)$row['collected'] > 0): ?>
                  <span style="height: <?= e((string)max(2, (float)$row['percent'])) ?>%"></span>
                <?php endif; ?>
              </div>
              <em><?= e((string)$row['label']) ?></em>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ((float)$months['target'] > 0): ?>
          <p class="adm-fin-note">Target line is the year's billed fees spread evenly over 12 months. No monthly target is stored in the system.</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="adm-fin-card">
      <h3><?= icon('card', 'icon-inline') ?> Revenue Breakdown</h3>
      <?php if (!$revenueBreakdown || $revenueTotal <= 0): ?>
        <div class="empty">No fee payments recorded yet. Fee types appear here once payments are recorded in Fee Collection.</div>
      <?php else: ?>
        <div class="adm-fin-donut-wrap">
          <div class="adm-fin-donut" style="background: conic-gradient(<?= e(implode(', ', $gradient)) ?>)">
            <div class="adm-fin-donut-hole">
              <strong><?= e($short($revenueTotal)) ?></strong>
              <small>Total Revenue</small>
            </div>
          </div>
        </div>
        <ul class="adm-fin-slices">
          <?php foreach ($revenueBreakdown as $i => $slice): ?>
            <li class="<?= e($slices[$i % 4]) ?>">
              <span><?= e((string)$slice['label']) ?></span>
              <b><?= e($pct($slice['share'])) ?></b>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <?php foreach ($yearArchives as $block): ?>
    <?php
      $blockYear = (int)($block['year'] ?? 0);
      $blockStored = !empty($block['stored']);
      $blockTotal = (float)($block['total'] ?? 0);
      $blockMonths = is_array($block['months'] ?? null) ? $block['months'] : [];
      $blockTopName = trim((string)($block['topCategoryName'] ?? ''));
      $blockTopTotal = (float)($block['topCategoryTotal'] ?? 0);
      $isLiveYear = $blockYear === (int)date('Y');
    ?>
    <div class="panel finance-year-archive">
      <div class="panel-h" style="align-items:flex-start">
        <div>
          <h3 style="margin:0"><?= $blockYear ?> month expenses</h3>
          <p class="muted" style="margin:.35rem 0 0;font-size:.85rem">
            <?php if ($isLiveYear && !$blockStored): ?>
              This year starts at zero. Saved years stay stored below and are not cleared.
            <?php elseif ($isLiveYear): ?>
              Live <?= $blockYear ?> totals. On 1 January the Expense cards and ledger reset; this <?= $blockYear ?> block stays stored.
            <?php else: ?>
              Permanently stored <?= $blockYear ?> record. Not reset when a new year starts.
            <?php endif; ?>
          </p>
        </div>
        <span class="chip"><?= $blockStored ? 'Stored' : 'This year' ?> · ₹<?= number_format($blockTotal) ?></span>
      </div>
      <div class="finance-year-summary">
        <div class="finance-month-total<?= $blockTotal > 0 ? ' has-amount' : '' ?>">
          <div class="label">Year total</div>
          <div class="value">₹<?= number_format($blockTotal) ?></div>
          <div class="hint"><?= $blockYear ?></div>
        </div>
        <div class="finance-month-total<?= $blockTopName !== '' ? ' has-amount' : '' ?>">
          <div class="label">Highest category</div>
          <div class="value"><?= $blockTopName !== '' ? e($blockTopName) : '—' ?></div>
          <div class="hint"><?= $blockTopName !== '' ? '₹' . number_format($blockTopTotal) . ' · ' . $blockYear : 'No expenses yet' ?></div>
        </div>
      </div>
      <div class="finance-month-totals">
        <?php foreach ($monthNames as $num => $label): ?>
          <?php
            $cell = $blockMonths[$num] ?? ['total' => 0.0, 'entries' => 0];
            $cellTotal = (float)$cell['total'];
            $cellEntries = (int)$cell['entries'];
          ?>
          <div class="finance-month-total<?= $cellTotal > 0 ? ' has-amount' : '' ?>">
            <div class="label"><?= e($label) ?></div>
            <div class="value">₹<?= number_format($cellTotal) ?></div>
            <div class="hint"><?= $cellEntries ?> entr<?= $cellEntries === 1 ? 'y' : 'ies' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="finance-pdf-actions">
        <a class="btn btn-primary" href="<?= e(url('/admin/finance/pdf?scope=year&year=' . $blockYear)) ?>">
          Generate <?= $blockYear ?> PDF
        </a>
      </div>
    </div>
  <?php endforeach; ?>

  <p class="adm-fin-foot">
    This page only summarises other modules. Manage records in
    <a href="<?= e(url('/admin/fee-collection')) ?>">Fee Collection</a>,
    <a href="<?= e(url('/admin/finance')) ?>">Expense</a>, and
    <a href="<?= e(url('/admin/salary')) ?>">Salary &amp; Payroll</a>.
  </p>
</div>
