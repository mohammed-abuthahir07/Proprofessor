<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$plans = Database::fetchAll(
    'SELECT subject_name, status, ai_score, bloom_data, version, updated_at FROM course_plans WHERE institution_id=? AND department_id=?',
    [$user['institution_id'], $user['department_id']]
);
render_header('NAAC / NBA Reports', 'reports', ['subtitle' => 'Evidence snapshot from live academic data']);
?>
<div class="panel">
  <div class="panel-h">
    <h2>Criterion evidence pack</h2>
    <button class="btn btn-primary btn-sm no-print" type="button" data-print>Export / Print</button>
  </div>
  <p>Auto-compiled from approved course plans, Bloom maps, and AI review scores.</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Subject</th><th>Status</th><th>AI score</th><th>Bloom K4-K6</th><th>Version</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($plans as $p):
      $b=json_decode($p['bloom_data']?:'{}',true)?:[];
      $h=(float)($b['K4']??0)+(float)($b['K5']??0)+(float)($b['K6']??0);
    ?>
      <tr>
        <td><?= e($p['subject_name']) ?></td>
        <td><?= status_badge($p['status']) ?></td>
        <td><?= e((string)$p['ai_score']) ?></td>
        <td><?= e((string)$h) ?>%</td>
        <td>v<?= (int)$p['version'] ?></td>
        <td><?= e($p['updated_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php render_footer(); ?>
