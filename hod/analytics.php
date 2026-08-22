<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$deptId = (int)($user['department_id'] ?? 0);
$plans = $deptId
    ? Database::fetchAll(
        'SELECT subject_name, ai_score, bloom_data, status FROM course_plans WHERE institution_id=? AND department_id=? ORDER BY subject_name',
        [$user['institution_id'], $deptId]
    )
    : Database::fetchAll(
        'SELECT subject_name, ai_score, bloom_data, status FROM course_plans WHERE institution_id=? ORDER BY subject_name',
        [$user['institution_id']]
    );
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

render_header('Department Analytics', 'analytics');
?>
<div class="grid grid-2 analytics-grid">
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
<script>document.addEventListener('DOMContentLoaded',()=>PPAI.renderBloomChart('deptBloom', <?= json_encode($dist) ?>));</script>
<?php render_footer(); ?>
