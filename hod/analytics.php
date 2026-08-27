<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$instId = (int)($user['institution_id'] ?? 0);
$deptId = $isAdmin
    ? (int)($_GET['department_id'] ?? ($user['department_id'] ?? 0))
    : hod_department_id($user);

$dept = $deptId > 0
    ? Database::fetch('SELECT id, name, code FROM departments WHERE id = ? AND institution_id = ?', [$deptId, $instId])
    : null;

$plans = $deptId
    ? Database::fetchAll(
        'SELECT subject_name, ai_score, bloom_data, status FROM course_plans WHERE institution_id=? AND department_id=? ORDER BY subject_name',
        [$instId, $deptId]
    )
    : ($isAdmin
        ? Database::fetchAll(
            'SELECT subject_name, ai_score, bloom_data, status FROM course_plans WHERE institution_id=? ORDER BY subject_name',
            [$instId]
        )
        : []);

$dist = ['K1' => 0, 'K2' => 0, 'K3' => 0, 'K4' => 0, 'K5' => 0, 'K6' => 0];
$n = 0;
foreach ($plans as $p) {
    $b = json_decode($p['bloom_data'] ?: '{}', true) ?: [];
    foreach ($dist as $k => $_) {
        $dist[$k] += (float)($b[$k] ?? 0);
    }
    if ($b) {
        $n++;
    }
}
if ($n) {
    foreach ($dist as $k => $v) {
        $dist[$k] = round($v / $n, 1);
    }
}
$statusOrder = [
    'draft' => 'Draft',
    'submitted' => 'Submitted',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'returned' => 'Returned',
];
$statusCounts = [];
foreach ($plans as $p) {
    $key = (string)($p['status'] ?? 'draft');
    $statusCounts[$key] = ($statusCounts[$key] ?? 0) + 1;
}
$totalPlans = max(1, count($plans));
$planCount = count($plans);

$scoreSum = 0.0;
$scoreN = 0;
foreach ($plans as $p) {
    if ($p['ai_score'] === null || $p['ai_score'] === '') {
        continue;
    }
    $scoreSum += (float)$p['ai_score'];
    $scoreN++;
}
$avgAiScore = $scoreN > 0 ? round($scoreSum / $scoreN, 1) : 0.0;

$studentsByYear = hod_analytics_students_by_year($instId, $deptId);
$workload = hod_analytics_professor_workload($instId, $deptId);
usort($workload, static fn(array $a, array $b): int => ((int)$b['total'] <=> (int)$a['total']) ?: strcmp((string)$a['name'], (string)$b['name']));
$workloadNames = array_map(static fn(array $r): string => $r['name'], $workload);
$theoryCounts = array_map(static fn(array $r): int => (int)$r['theory'], $workload);
$labCounts = array_map(static fn(array $r): int => (int)$r['labs'], $workload);
$hasTheoryWorkload = array_sum($theoryCounts) > 0;
$hasLabWorkload = array_sum($labCounts) > 0;
$hasAnyWorkload = $hasTheoryWorkload || $hasLabWorkload;
$useBarForProfCharts = count($workload) > 6;
$maxWorkload = 0;
foreach ($workload as $row) {
    $maxWorkload = max($maxWorkload, (int)$row['total']);
}
$professorCount = count($workload);
$approvedCount = (int)($statusCounts['approved'] ?? 0);
$deptLabel = $dept
    ? trim((string)(($dept['code'] ?? '') !== '' ? $dept['code'] . ' — ' . ($dept['name'] ?? '') : ($dept['name'] ?? 'Department')))
    : 'Department';

render_header('Department Analytics', 'analytics');
?>
<?php if (!$isAdmin && $deptId < 1): ?>
<div class="panel">
  <div class="alert alert-warn">Your HOD account is not linked to a department. Contact the College Admin.</div>
</div>
<?php else: ?>

<section class="hod-analytics-page">
  <section class="welcome-banner reveal">
    <div>
      <h2>Department Analytics</h2>
      <p><?= e($deptLabel) ?> — students, faculty workload, Bloom coverage, and course-plan progress.</p>
    </div>
  </section>

  <div class="hod-analytics-stats stagger">
    <div class="stat">
      <div class="label">Students</div>
      <div class="value" data-count="<?= (int)$studentsByYear['total'] ?>">0</div>
    </div>
    <div class="stat">
      <div class="label">Professors</div>
      <div class="value" data-count="<?= (int)$professorCount ?>">0</div>
    </div>
    <div class="stat">
      <div class="label">Course plans</div>
      <div class="value" data-count="<?= (int)$planCount ?>">0</div>
    </div>
    <div class="stat">
      <div class="label">Avg AI score</div>
      <div class="value" data-count="<?= e((string)$avgAiScore) ?>" data-decimals="1">0</div>
    </div>
  </div>

  <div class="grid grid-2 analytics-grid analytics-pair">
    <div class="panel reveal">
      <div class="panel-head">
        <div>
          <h2>Students by Year</h2>
          <p>Active students across years 1–4</p>
        </div>
        <span class="chip"><?= (int)$studentsByYear['total'] ?> total</span>
      </div>
      <?php if ((int)$studentsByYear['total'] < 1): ?>
        <div class="empty">No students available</div>
      <?php else: ?>
        <div class="students-year-body">
          <div class="chart-box chart-box-bar students-year-chart">
            <canvas id="studentsByYearChart" aria-label="Students by academic year"></canvas>
          </div>
          <div class="hod-analytics-legend-row">
            <?php foreach ($studentsByYear['labels'] as $i => $label): ?>
              <span class="chip"><?= e($label) ?> · <?= (int)$studentsByYear['counts'][$i] ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel reveal">
      <div class="panel-head">
        <div>
          <h2>Combined Professor Workload</h2>
          <p>Theory + lab subjects assigned per faculty</p>
        </div>
        <span class="chip"><?= (int)$professorCount ?> faculty</span>
      </div>
      <?php if (!$workload || !$hasAnyWorkload): ?>
        <div class="empty">No professor course assignments available</div>
      <?php else: ?>
        <div class="hod-workload-body">
          <div class="table-wrap hod-workload-table">
            <table>
              <thead>
                <tr>
                  <th>Professor</th>
                  <th>Theory</th>
                  <th>Labs</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($workload as $i => $row):
                  $total = max(1, (int)$row['total']);
                  $theoryPct = round(((int)$row['theory'] / $total) * 100);
                  $labPct = 100 - $theoryPct;
                  $delay = number_format(0.05 * $i, 2);
                ?>
                  <tr>
                    <td data-label="Professor">
                      <strong><?= e($row['name']) ?></strong>
                      <?php if ($maxWorkload > 0 && (int)$row['total'] > 0): ?>
                        <div class="load-mini" title="Theory vs labs share">
                          <span class="theory" style="--bar-pct:<?= (int)$theoryPct ?>%;animation-delay:<?= e($delay) ?>s"></span>
                          <span class="labs" style="--bar-pct:<?= (int)$labPct ?>%;animation-delay:<?= e(number_format(0.05 * $i + 0.12, 2)) ?>s"></span>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td data-label="Theory"><?= (int)$row['theory'] ?></td>
                    <td data-label="Labs"><?= (int)$row['labs'] ?></td>
                    <td data-label="Total"><strong><?= (int)$row['total'] ?></strong></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="chart-box chart-box-bar hod-workload-chart">
            <canvas id="combinedWorkloadChart" aria-label="Combined professor workload"></canvas>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-2 analytics-grid" style="margin-top:1rem">
    <div class="panel reveal">
      <div class="panel-head">
        <div>
          <h2>Professor Course Workload</h2>
          <p>Theory subjects only</p>
        </div>
      </div>
      <?php if (!$hasTheoryWorkload): ?>
        <div class="empty">No professor course assignments available</div>
      <?php else: ?>
        <div class="chart-box">
          <canvas id="profTheoryChart" height="220" aria-label="Professor theory course workload"></canvas>
        </div>
      <?php endif; ?>
    </div>
    <div class="panel reveal">
      <div class="panel-head">
        <div>
          <h2>Professor Lab Workload</h2>
          <p>Lab subjects only</p>
        </div>
      </div>
      <?php if (!$hasLabWorkload): ?>
        <div class="empty">No lab assignments available</div>
      <?php else: ?>
        <div class="chart-box">
          <canvas id="profLabChart" height="220" aria-label="Professor lab workload"></canvas>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-2 analytics-grid" style="margin-top:1rem">
    <div class="panel reveal">
      <div class="panel-head">
        <div>
          <h2>Bloom distribution</h2>
          <p>Department average across course plans</p>
        </div>
      </div>
      <div class="chart-box">
        <canvas id="deptBloom" height="200" aria-label="Bloom taxonomy distribution"></canvas>
      </div>
    </div>
    <div class="panel analytics-status reveal">
      <div class="panel-head">
        <div>
          <h2>Submission status</h2>
          <p><?= (int)$approvedCount ?> approved of <?= (int)$planCount ?> plans</p>
        </div>
      </div>
      <?php if (!$plans): ?>
        <div class="empty">No course plans in this department yet.</div>
      <?php else: ?>
        <div class="status-bars">
          <?php $si = 0; foreach ($statusOrder as $key => $label):
            $c = (int)($statusCounts[$key] ?? 0);
            $pct = $planCount ? round($c * 100 / $totalPlans) : 0;
            $delay = number_format(0.08 * $si, 2);
            $si++;
          ?>
            <div class="status-row">
              <div class="status-row-h">
                <span><?= e($label) ?></span>
                <span><?= $c ?> · <?= $pct ?>%</span>
              </div>
              <div class="bar"><span style="--bar-pct:<?= (int)$pct ?>%;animation-delay:<?= e($delay) ?>s"></span></div>
            </div>
          <?php endforeach; ?>
          <?php foreach ($statusCounts as $key => $c):
            if (isset($statusOrder[$key])) {
                continue;
            }
            $pct = round(((int)$c) * 100 / $totalPlans);
          ?>
            <div class="status-row">
              <div class="status-row-h">
                <span><?= e(ucfirst(str_replace('_', ' ', (string)$key))) ?></span>
                <span><?= (int)$c ?> · <?= $pct ?>%</span>
              </div>
              <div class="bar"><span style="--bar-pct:<?= (int)$pct ?>%"></span></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h3>Comparative subject scores</h3>
      <?php if (!$plans): ?>
        <div class="empty">Scores appear after faculty submit plans.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Subject</th><th>AI score</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($plans as $p): ?>
              <tr>
                <td><?= e($p['subject_name']) ?></td>
                <td><span class="score-pill"><?= e((string)($p['ai_score'] !== null && $p['ai_score'] !== '' ? $p['ai_score'] : '—')) ?></span></td>
                <td><?= status_badge((string)$p['status']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
(function () {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function animateCount(el) {
    const target = parseFloat(el.getAttribute('data-count') || '0');
    const decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
    if (reduce || !isFinite(target)) {
      el.textContent = decimals ? target.toFixed(decimals) : String(Math.round(target));
      return;
    }
    const duration = 900;
    const start = performance.now();
    function frame(now) {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      const val = target * eased;
      el.textContent = decimals ? val.toFixed(decimals) : String(Math.round(val));
      if (t < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  function bootCharts() {
    if (typeof PPAI === 'undefined' || typeof Chart === 'undefined') {
      setTimeout(bootCharts, 40);
      return;
    }
    PPAI.renderBloomChart('deptBloom', <?= json_encode($dist, JSON_UNESCAPED_UNICODE) ?>);
    <?php if ((int)$studentsByYear['total'] > 0): ?>
    PPAI.renderBarChart('studentsByYearChart', {
      labels: <?= json_encode($studentsByYear['labels'], JSON_UNESCAPED_UNICODE) ?>,
      values: <?= json_encode($studentsByYear['counts'], JSON_UNESCAPED_UNICODE) ?>,
      label: 'Students',
      color: '#8b5cf6',
      hoverColor: '#a78bfa',
      fillContainer: true
    });
    <?php endif; ?>
    <?php if ($hasTheoryWorkload): ?>
    PPAI.renderWorkloadChart('profTheoryChart', {
      labels: <?= json_encode($workloadNames, JSON_UNESCAPED_UNICODE) ?>,
      values: <?= json_encode($theoryCounts, JSON_UNESCAPED_UNICODE) ?>,
      label: 'Theory courses',
      preferBar: <?= $useBarForProfCharts ? 'true' : 'false' ?>,
      colors: ['#8b5cf6', '#a78bfa', '#6366f1', '#818cf8', '#38bdf8', '#22d3ee', '#c4b5fd', '#7c3aed']
    });
    <?php endif; ?>
    <?php if ($hasLabWorkload): ?>
    PPAI.renderWorkloadChart('profLabChart', {
      labels: <?= json_encode($workloadNames, JSON_UNESCAPED_UNICODE) ?>,
      values: <?= json_encode($labCounts, JSON_UNESCAPED_UNICODE) ?>,
      label: 'Labs',
      preferBar: <?= $useBarForProfCharts ? 'true' : 'false' ?>,
      colors: ['#22d3ee', '#38bdf8', '#6366f1', '#8b5cf6', '#a78bfa', '#fbbf24', '#fb923c', '#34d399']
    });
    <?php endif; ?>
    <?php if ($hasAnyWorkload): ?>
    PPAI.renderStackedBarChart('combinedWorkloadChart', {
      labels: <?= json_encode($workloadNames, JSON_UNESCAPED_UNICODE) ?>,
      theory: <?= json_encode($theoryCounts, JSON_UNESCAPED_UNICODE) ?>,
      labs: <?= json_encode($labCounts, JSON_UNESCAPED_UNICODE) ?>,
      fillContainer: true
    });
    <?php endif; ?>
  }

  function init() {
    document.querySelectorAll('.hod-analytics-page .value[data-count]').forEach(animateCount);
    bootCharts();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
