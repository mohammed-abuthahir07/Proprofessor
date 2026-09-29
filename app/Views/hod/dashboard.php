<?php
/** @var int $pending */
/** @var int $approved */
/** @var int $facultyCount */
/** @var float|null $avgAi */
/** @var list<array<string,mixed>> $alerts */
/** @var string $deptName */
/** @var string $naacGrade */
/** @var string $contextLine */
/** @var string $periodLabel */
/** @var array<string,float> $bloom */
/** @var int $bloomSamples */
/** @var int $bloomWarnings */
/** @var int $planCount */
/** @var list<array<string,mixed>> $queue */
/** @var list<array<string,mixed>> $facultyRows */
/** @var int $facultyTotal */
/** @var array<int,list<array<string,mixed>>> $assignmentMap */
/** @var array<int,?float> $attendanceByProfessor */
/** @var float|null $deptAttendance */
/** @var float $attendanceMin */

$fmt = static function (float|int|string|null $n): string {
    if ($n === null || $n === '') {
        return '—';
    }
    $r = round((float)$n, 1);
    return abs($r - round($r)) < 0.05 ? (string)(int)round($r) : number_format($r, 1);
};

$ago = static function (?string $dt): string {
    $dt = trim((string)$dt);
    if ($dt === '') {
        return '';
    }
    $ts = strtotime($dt);
    if (!$ts) {
        return $dt;
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $m = (int)floor($diff / 60);
        return $m . ' min ago';
    }
    if ($diff < 86400) {
        $h = (int)floor($diff / 3600);
        return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400 * 7) {
        $d = (int)floor($diff / 86400);
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    return date('d M Y', $ts);
};

$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
    if (!$parts) {
        return 'F';
    }
    $first = mb_substr($parts[0], 0, 1);
    $last = mb_substr($parts[count($parts) - 1], 0, 1);
    return strtoupper($first . ($parts[0] !== $parts[count($parts) - 1] ? $last : ''));
};

$higherBloom = $bloomSamples > 0
    ? round((float)($bloom['K4'] ?? 0) + (float)($bloom['K5'] ?? 0) + (float)($bloom['K6'] ?? 0), 1)
    : null;
$approvalPct = $planCount > 0 ? round($approved * 100 / $planCount, 1) : null;
$bloomStatus = null;
if ($bloomSamples > 0) {
    $bloomStatus = $bloomWarnings > 0 ? 'Needs Attention' : 'On Track';
}
$approvalStatus = null;
if ($pending > 0) {
    $approvalStatus = 'Needs Attention';
} elseif ($planCount > 0 && $approved === $planCount) {
    $approvalStatus = 'On Track';
}

$naacTitle = 'NAAC Compliance Readiness';
if ($deptName !== '') {
    $naacTitle .= ' — ' . $deptName;
}
$bloomTitle = "Department-wide Bloom's Distribution";
if ($periodLabel !== '') {
    $bloomTitle .= ' — ' . $periodLabel;
}

$bloomLabels = [
    'K1' => 'Remember',
    'K2' => 'Understand',
    'K3' => 'Apply',
    'K4' => 'Analyse',
    'K5' => 'Evaluate',
    'K6' => 'Create',
];

$bar = static function (?float $pct): string {
    if ($pct === null) {
        return '0';
    }
    return (string)max(0, min(100, round($pct, 1)));
};
?>
<div class="hod-dash">
  <section class="hod-hero">
    <div class="hod-hero-copy">
      <h2><?= icon('building', 'icon-inline') ?> HOD Dashboard</h2>
      <p><?= e($contextLine) ?></p>
    </div>
    <a class="btn btn-primary btn-shine" href="<?= e(url('/hod/approvals')) ?>"><?= icon('check') ?> Review Approvals</a>
  </section>

  <section class="hod-kpi" aria-label="Department summary">
    <a class="hod-kpi-card" href="<?= e(url('/hod/faculty')) ?>">
      <span class="label">Faculty Members</span>
      <strong class="value tone-brand"><?= (int)$facultyCount ?></strong>
      <span class="hint">Active in this department</span>
    </a>
    <a class="hod-kpi-card" href="<?= e(url('/hod/approvals')) ?>">
      <span class="label">Pending Review</span>
      <strong class="value tone-warn"><?= (int)$pending ?></strong>
      <span class="hint">Plans awaiting review</span>
    </a>
    <a class="hod-kpi-card" href="<?= e(url('/hod/approvals')) ?>">
      <span class="label">Approved</span>
      <strong class="value tone-ok"><?= (int)$approved ?></strong>
      <span class="hint">Approved plans</span>
    </a>
    <a class="hod-kpi-card" href="<?= e(url('/hod/reports')) ?>">
      <span class="label">NAAC Readiness</span>
      <strong class="value tone-brand"><?= $naacGrade !== '' ? e($naacGrade) : '—' ?></strong>
      <span class="hint"><?= $naacGrade !== '' ? 'Institution NAAC grade' : 'Grade not recorded' ?></span>
    </a>
    <div class="hod-kpi-card">
      <span class="label">Dept Attendance</span>
      <?php $attLow = $deptAttendance !== null && $deptAttendance < $attendanceMin; ?>
      <strong class="value <?= $attLow ? 'tone-warn' : 'tone-info' ?>">
        <?php if ($deptAttendance === null): ?>—<?php else: ?><?= e($fmt($deptAttendance)) ?><span class="unit">%</span><?php endif; ?>
      </strong>
      <span class="hint">
        <?php if ($deptAttendance === null): ?>
          No attendance recorded
        <?php else: ?>
          Minimum <?= e($fmt($attendanceMin)) ?>%
        <?php endif; ?>
      </span>
    </div>
  </section>

  <section class="hod-panel">
    <div class="hod-panel-h">
      <h2><?= icon('file', 'icon-inline') ?> <?= e($naacTitle) ?></h2>
      <a class="hod-link" href="<?= e(url('/hod/reports')) ?>">View Full Report</a>
    </div>
    <?php if ($planCount < 1 && !$alerts): ?>
      <div class="empty">No course-plan evidence yet. The full NAAC report fills in as faculty create plans.</div>
    <?php else: ?>
      <div class="hod-criteria">
        <?php if ($approvalPct !== null): ?>
          <div class="hod-criterion">
            <div class="hod-criterion-h">
              <span>Course plan approval</span>
              <span class="hod-criterion-meta">
                <strong><?= e($fmt($approvalPct)) ?>%</strong>
                <?php if ($approvalStatus): ?><span class="hod-status <?= $approvalStatus === 'On Track' ? 'is-ok' : 'is-warn' ?>"><?= e($approvalStatus) ?></span><?php endif; ?>
              </span>
            </div>
            <div class="hod-bar <?= $approvalStatus === 'Needs Attention' ? 'is-warn' : 'is-ok' ?>"><span style="width:<?= e($bar($approvalPct)) ?>%"></span></div>
            <p><?= (int)$approved ?> of <?= (int)$planCount ?> plans approved</p>
          </div>
        <?php endif; ?>
        <?php if ($higherBloom !== null): ?>
          <div class="hod-criterion">
            <div class="hod-criterion-h">
              <span>Higher-order Bloom (K4–K6)</span>
              <span class="hod-criterion-meta">
                <strong><?= e($fmt($higherBloom)) ?>%</strong>
                <?php if ($bloomStatus): ?><span class="hod-status <?= $bloomStatus === 'On Track' ? 'is-ok' : 'is-warn' ?>"><?= e($bloomStatus) ?></span><?php endif; ?>
              </span>
            </div>
            <div class="hod-bar <?= $bloomStatus === 'Needs Attention' ? 'is-warn' : 'is-ok' ?>"><span style="width:<?= e($bar($higherBloom)) ?>%"></span></div>
            <p>Department average from course-plan Bloom data</p>
          </div>
        <?php endif; ?>
        <?php if ($avgAi !== null): ?>
          <div class="hod-criterion">
            <div class="hod-criterion-h">
              <span>AI plan quality</span>
              <span class="hod-criterion-meta"><strong><?= e($fmt($avgAi)) ?></strong></span>
            </div>
            <div class="hod-bar is-brand"><span style="width:<?= e($bar((float)$avgAi)) ?>%"></span></div>
            <p>Average AI plan score</p>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($alerts): ?>
      <div class="hod-alerts">
        <?php foreach ($alerts as $alert):
          $severity = (string)($alert['severity'] ?? 'medium');
          $alertStatus = match ($severity) {
              'high' => 'Action Required',
              'low' => 'On Track',
              default => 'Needs Attention',
          };
          $alertClass = match ($alertStatus) {
              'Action Required' => 'is-bad',
              'On Track' => 'is-ok',
              default => 'is-warn',
          };
        ?>
          <div class="hod-alert">
            <span class="hod-status <?= e($alertClass) ?>"><?= e($alertStatus) ?></span>
            <span><?= e((string)$alert['message']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="hod-dash-split">
    <section class="hod-panel">
      <div class="hod-panel-h">
        <h2><?= icon('check', 'icon-inline') ?> Pending Approvals</h2>
        <a class="hod-link" href="<?= e(url('/hod/approvals')) ?>">See All</a>
      </div>
      <?php if (!$queue): ?>
        <div class="empty">No plans are awaiting review.</div>
      <?php else: ?>
        <div class="hod-list">
          <?php foreach ($queue as $item):
            $bloomRow = json_decode((string)($item['bloom_data'] ?? '{}'), true) ?: [];
            $k46 = $bloomRow
                ? (float)($bloomRow['K4'] ?? 0) + (float)($bloomRow['K5'] ?? 0) + (float)($bloomRow['K6'] ?? 0)
                : null;
            $subject = trim((string)($item['subject_name'] ?? '')) ?: (string)($item['title'] ?? 'Course plan');
            $classBits = array_filter([
                trim((string)($item['class_name'] ?? '')),
                trim((string)($item['class_section'] ?? '')) !== '' ? ('Sec ' . trim((string)$item['class_section'])) : '',
                (int)($item['class_year'] ?? 0) > 0 ? subject_year_label((int)$item['class_year']) : '',
                trim((string)($item['semester'] ?? '')),
            ], static fn($v) => $v !== '');
            $metaBits = array_filter([
                trim((string)($item['professor_name'] ?? '')),
                $classBits ? implode(' · ', $classBits) : '',
                $ago($item['submitted_at'] ?? null),
            ], static fn($v) => $v !== '');
          ?>
            <article class="hod-row">
              <div class="hod-row-ico"><?= icon('book') ?></div>
              <div class="hod-row-body">
                <a class="hod-row-title" href="<?= e(url('/hod/approvals?id=' . (int)$item['id'])) ?>"><?= e($subject) ?></a>
                <p><?= e(implode(' · ', $metaBits)) ?></p>
                <?php if ($k46 !== null): ?><p class="hod-k46">K4–K6 <?= e($fmt($k46)) ?>%</p><?php endif; ?>
              </div>
              <div class="hod-row-side">
                <?php if ($item['ai_score'] !== null && $item['ai_score'] !== ''): ?>
                  <span class="hod-score">AI <?= e($fmt($item['ai_score'])) ?></span>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/hod/approvals')) ?>" class="hod-actions">
                  <?= csrf_field() ?>
                  <input type="hidden" name="plan_id" value="<?= (int)$item['id'] ?>">
                  <button class="hod-btn hod-btn-ok" type="submit" name="action" value="approve">Approve</button>
                  <button class="hod-btn hod-btn-bad" type="submit" name="action" value="reject">Return</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="hod-panel">
      <div class="hod-panel-h">
        <h2><?= icon('users', 'icon-inline') ?> Faculty Activity</h2>
        <a class="hod-link" href="<?= e(url('/hod/faculty')) ?>">Manage Faculty</a>
      </div>
      <?php if (!$facultyRows): ?>
        <div class="empty">No active faculty in this department.</div>
      <?php else: ?>
        <div class="hod-list">
          <?php foreach ($facultyRows as $member):
            $id = (int)$member['id'];
            $plansN = (int)($member['total'] ?? 0);
            $pendingN = (int)($member['pending'] ?? 0);
            $score = ($member['avg_score'] !== null && $member['avg_score'] !== '') ? (float)$member['avg_score'] : null;
            $att = $attendanceByProfessor[$id] ?? null;
            $assignments = $assignmentMap[$id] ?? [];
            $subjects = [];
            $classes = [];
            foreach ($assignments as $assignment) {
                $subjectName = trim((string)($assignment['subject_name'] ?? ''));
                if ($subjectName !== '' && !in_array($subjectName, $subjects, true)) {
                    $subjects[] = $subjectName;
                }
                $classLabel = trim((string)($assignment['class_name'] ?? ''));
                $section = trim((string)($assignment['section'] ?? ''));
                if ($section !== '') {
                    $classLabel = trim($classLabel . ' ' . $section);
                }
                if ($classLabel !== '' && !in_array($classLabel, $classes, true)) {
                    $classes[] = $classLabel;
                }
            }
            $detail = [];
            $detail[] = $plansN === 1 ? '1 plan' : ($plansN . ' plans');
            if ($classes) {
                $detail[] = count($classes) === 1 ? $classes[0] : (count($classes) . ' classes');
            }
            if ($subjects) {
                $shown = array_slice($subjects, 0, 2);
                $extra = count($subjects) - count($shown);
                $detail[] = implode(', ', $shown) . ($extra > 0 ? ' +' . $extra : '');
            }
            if ($score !== null) {
                $detail[] = 'Avg AI ' . $fmt($score);
            }
          ?>
            <article class="hod-row hod-faculty-row">
              <div class="hod-avatar" aria-hidden="true"><?= e($initials((string)$member['full_name'])) ?></div>
              <div class="hod-row-body">
                <div class="hod-faculty-name">
                  <strong><?= e((string)$member['full_name']) ?></strong>
                  <?php if ($pendingN > 0): ?><span class="hod-status is-bad">Action needed</span><?php elseif ($plansN < 1 && !$assignments): ?><span class="hod-status is-warn">No activity</span><?php endif; ?>
                </div>
                <p><?= e(implode(' · ', $detail)) ?></p>
                <?php if ($att !== null): ?>
                  <div class="hod-mini">
                    <div class="hod-bar is-brand"><span style="width:<?= e($bar($att)) ?>%"></span></div>
                    <span><?= e($fmt($att)) ?>%</span>
                  </div>
                <?php else: ?>
                  <p class="hod-muted">No attendance recorded</p>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if ($facultyTotal > count($facultyRows)): ?>
          <p class="hod-more">Showing <?= count($facultyRows) ?> of <?= (int)$facultyTotal ?> faculty.</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>

  <section class="hod-panel">
    <div class="hod-panel-h">
      <h2><?= icon('chart', 'icon-inline') ?> <?= e($bloomTitle) ?></h2>
    </div>
    <?php if ($bloomSamples < 1): ?>
      <div class="empty">Bloom distribution appears after course plans include Bloom data.</div>
    <?php else: ?>
      <div class="hod-bloom-grid">
        <?php foreach ($bloomLabels as $key => $label):
          $pct = (float)($bloom[$key] ?? 0);
        ?>
          <article class="hod-bloom hod-bloom-<?= e(strtolower($key)) ?>">
            <strong><?= e($fmt($pct)) ?>%</strong>
            <span><?= e($key) ?> <?= e($label) ?></span>
            <div class="hod-bar"><span style="width:<?= e($bar($pct)) ?>%"></span></div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($bloomWarnings > 0): ?>
        <div class="hod-note">
          <?= icon('alert', 'icon-inline') ?>
          <?= (int)$bloomWarnings ?> course plan<?= $bloomWarnings === 1 ? '' : 's' ?> ha<?= $bloomWarnings === 1 ? 's' : 've' ?> a high concentration of lower-order Bloom levels (K1/K2). Consider adding more higher-order learning activities.
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
