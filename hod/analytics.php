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

$studentsByYear = hod_analytics_students_by_year($instId, $deptId);
$workload = hod_analytics_professor_workload($instId, $deptId);
$workloadNames = array_map(static fn(array $r): string => $r['name'], $workload);
$theoryCounts = array_map(static fn(array $r): int => (int)$r['theory'], $workload);
$labCounts = array_map(static fn(array $r): int => (int)$r['labs'], $workload);
$hasTheoryWorkload = array_sum($theoryCounts) > 0;
$hasLabWorkload = array_sum($labCounts) > 0;
$hasAnyWorkload = $hasTheoryWorkload || $hasLabWorkload;
$useBarForProfCharts = count($workload) > 6;

render_header('Department Analytics', 'analytics');
?>
<?php if (!$isAdmin && $deptId < 1): ?>
<div class="panel">
  <div class="alert alert-warn">Your HOD account is not linked to a department. Contact the College Admin.</div>
</div>
<?php else: ?>

<section class="hod-analytics-page">
  <div class="grid grid-2 analytics-grid">
    <div class="panel">
      <h2>Students by Year</h2>
      <?php if ((int)$studentsByYear['total'] < 1): ?>
        <div class="empty">No students available</div>
      <?php else: ?>
        <div class="chart-box chart-box-bar">
          <canvas id="studentsByYearChart" height="220" aria-label="Students by academic year"></canvas>
        </div>
        <div class="hod-analytics-legend-row">
          <?php foreach ($studentsByYear['labels'] as $i => $label): ?>
            <span class="chip"><?= e($label) ?> · <?= (int)$studentsByYear['counts'][$i] ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Combined Professor Workload</h2>
      <?php if (!$workload): ?>
        <div class="empty">No professor course assignments available</div>
      <?php elseif (!$hasAnyWorkload): ?>
        <div class="empty">No professor course assignments available</div>
      <?php else: ?>
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
              <?php foreach ($workload as $row): ?>
                <tr>
                  <td data-label="Professor"><strong><?= e($row['name']) ?></strong></td>
                  <td data-label="Theory"><?= (int)$row['theory'] ?></td>
                  <td data-label="Labs"><?= (int)$row['labs'] ?></td>
                  <td data-label="Total"><strong><?= (int)$row['total'] ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="chart-box chart-box-bar" style="margin-top:1rem">
          <canvas id="combinedWorkloadChart" height="220" aria-label="Combined professor workload"></canvas>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-2 analytics-grid" style="margin-top:1rem">
    <div class="panel">
      <h2>Professor Course Workload</h2>
      <?php if (!$hasTheoryWorkload): ?>
        <div class="empty">No professor course assignments available</div>
      <?php else: ?>
        <div class="chart-box">
          <canvas id="profTheoryChart" height="220" aria-label="Professor theory course workload"></canvas>
        </div>
      <?php endif; ?>
    </div>
    <div class="panel">
      <h2>Professor Lab Workload</h2>
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
    <div class="panel">
      <h2>Bloom distribution (dept avg)</h2>
      <div class="chart-box">
        <canvas id="deptBloom" height="200"></canvas>
      </div>
    </div>
    <div class="panel analytics-status">
      <h2>Submission status</h2>
      <?php if (!$plans): ?>
        <div class="empty">No course plans in this department yet.</div>
      <?php else: ?>
        <div class="status-bars">
          <?php foreach ($statusOrder as $key => $label):
            $c = (int)($statusCounts[$key] ?? 0);
            $pct = count($plans) ? round($c * 100 / $totalPlans) : 0;
          ?>
            <div class="status-row">
              <div class="status-row-h">
                <span><?= e($label) ?></span>
                <span><?= $c ?> · <?= $pct ?>%</span>
              </div>
              <div class="bar"><span style="width:<?= $pct ?>%"></span></div>
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
              <div class="bar"><span style="width:<?= $pct ?>%"></span></div>
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
                <td><?= e((string)$p['ai_score']) ?></td>
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
document.addEventListener('DOMContentLoaded', function () {
  PPAI.renderBloomChart('deptBloom', <?= json_encode($dist, JSON_UNESCAPED_UNICODE) ?>);
  <?php if ((int)$studentsByYear['total'] > 0): ?>
  PPAI.renderBarChart('studentsByYearChart', {
    labels: <?= json_encode($studentsByYear['labels'], JSON_UNESCAPED_UNICODE) ?>,
    values: <?= json_encode($studentsByYear['counts'], JSON_UNESCAPED_UNICODE) ?>,
    label: 'Students',
    color: '#8b5cf6'
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
    labs: <?= json_encode($labCounts, JSON_UNESCAPED_UNICODE) ?>
  });
  <?php endif; ?>
});
</script>
<?php endif; ?>
<?php render_footer(); ?>
