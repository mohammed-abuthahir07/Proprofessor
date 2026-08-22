<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
$courseIds = array_column(courses_for_student($user), 'id');
$docs = [];
$ppts = [];
if ($courseIds) {
    $in = implode(',', array_fill(0, count($courseIds), '?'));
    $docs = Database::fetchAll(
        "SELECT d.*, s.name AS subject_name FROM documents d
         LEFT JOIN subjects s ON s.id=d.subject_id
         WHERE d.is_published=1 AND d.subject_id IN ($in)
         ORDER BY d.created_at DESC",
        $courseIds
    );
    $ppts = Database::fetchAll(
        "SELECT p.*, s.name AS subject_name FROM presentations p
         LEFT JOIN course_plans cp ON cp.id = p.plan_id
         LEFT JOIN subjects s ON s.id = COALESCE(p.subject_id, cp.subject_id)
         WHERE p.status IN (\"ready\",\"published\")
           AND COALESCE(p.subject_id, cp.subject_id) IN ($in)
         ORDER BY p.id DESC",
        $courseIds
    );
}
render_header('Notes & PPT', 'notes');
?>
<div class="grid grid-2">
  <div class="panel">
    <h2>Notes</h2>
    <?php if (!$docs): ?><div class="empty">No published notes yet.</div><?php endif; ?>
    <?php foreach ($docs as $d): ?>
      <div style="padding:.7rem 0;border-bottom:1px solid var(--line)">
        <strong><?= e($d['title']) ?></strong>
        <div style="color:var(--muted);font-size:.85rem"><?= e((string)$d['subject_name']) ?> · Unit <?= e((string)$d['unit_number']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="panel">
    <h2>Presentations</h2>
    <?php if (!$ppts): ?><div class="empty">No PPTs shared yet.</div><?php endif; ?>
    <?php foreach ($ppts as $p): ?>
      <div style="padding:.7rem 0;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:1rem">
        <div><strong><?= e($p['title']) ?></strong><div style="color:var(--muted);font-size:.85rem"><?= (int)$p['slide_count'] ?> slides</div></div>
        <a class="btn btn-sm btn-primary" href="<?= e(base_url('/professor/ppt-view.php?id='.$p['id'])) ?>">View</a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php render_footer(); ?>
