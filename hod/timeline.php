<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$events = Database::fetchAll(
    'SELECT * FROM academic_events WHERE institution_id=? ORDER BY event_date',
    [$user['institution_id']]
);
$plans = Database::fetchAll(
    'SELECT title, status, submitted_at, reviewed_at FROM course_plans WHERE institution_id=? AND department_id=? ORDER BY submitted_at DESC',
    [$user['institution_id'], $user['department_id']]
);
render_header('Timeline Tracker', 'timeline');
?>
<div class="grid grid-2">
  <div class="panel">
    <h2>Semester milestones</h2>
    <?php foreach ($events as $e): ?>
      <div style="display:flex;gap:1rem;padding:.7rem 0;border-bottom:1px solid var(--line)">
        <strong style="min-width:110px"><?= e($e['event_date']) ?></strong>
        <div><?= e($e['title']) ?><div style="color:var(--muted);font-size:.85rem"><?= e((string)$e['event_type']) ?></div></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="panel">
    <h2>Review cycle</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Plan</th><th>Status</th><th>Submitted</th><th>Reviewed</th></tr></thead>
      <tbody>
      <?php foreach ($plans as $p): ?>
        <tr>
          <td><?= e($p['title']) ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td><?= e((string)$p['submitted_at']) ?></td>
          <td><?= e((string)$p['reviewed_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php render_footer(); ?>
