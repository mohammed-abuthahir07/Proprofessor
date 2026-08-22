<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
$user = Auth::user();
$events = Database::fetchAll(
    'SELECT * FROM academic_events WHERE institution_id=? ORDER BY event_date',
    [$user['institution_id']]
);
$ann = announcements_for_user($user);
render_header('Academic Calendar', 'calendar');
?>
<div class="grid grid-2">
  <div class="panel">
    <h2>Calendar</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Date</th><th>Event</th><th>Type</th></tr></thead>
      <tbody>
      <?php foreach ($events as $e): ?>
        <tr><td><?= e($e['event_date']) ?></td><td><?= e($e['title']) ?></td><td><span class="chip"><?= e((string)$e['event_type']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="panel">
    <h2>Notifications & events</h2>
    <?php foreach ($ann as $a): ?>
      <div style="padding:.8rem 0;border-bottom:1px solid var(--line)">
        <span class="chip"><?= e($a['announcement_type']) ?></span>
        <strong><?= e($a['title']) ?></strong>
        <p style="margin:.3rem 0 0;color:var(--muted)"><?= e($a['body']) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php render_footer(); ?>
