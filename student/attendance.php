<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
$classId = student_class_id($user);
$classLabel = $classId ? class_label_by_id($classId) : '';
if ($classId) {
    sync_class_roster((int)$user['institution_id'], $classId);
}
$roster = Database::fetch(
    'SELECT register_no FROM students_roster WHERE user_id=? AND class_id=?',
    [$user['id'], $classId]
);
$reg = trim((string)($user['register_no'] ?: ($roster['register_no'] ?? '')));
$min = institution_attendance_min((int)$user['institution_id']);
$rows = [];
if ($classId) {
    $rows = Database::fetchAll(
        'SELECT s.name AS subject_name, r.status, sess.session_date, sess.period
         FROM attendance_records r
         JOIN attendance_sessions sess ON sess.id = r.session_id
           AND sess.institution_id = ?
           AND sess.class_id = ?
         LEFT JOIN subjects s ON s.id = sess.subject_id
         WHERE r.student_id = ? OR (r.register_no <> "" AND r.register_no = ?)
         ORDER BY sess.session_date DESC, sess.period DESC',
        [$user['institution_id'], $classId, $user['id'], $reg]
    );
}
$bySub = [];
foreach ($rows as $r) {
    $k = $r['subject_name'] ?: 'General';
    $bySub[$k]['total'] = ($bySub[$k]['total'] ?? 0) + 1;
    if (in_array($r['status'], ['present', 'late'], true)) {
        $bySub[$k]['present'] = ($bySub[$k]['present'] ?? 0) + 1;
    }
}
render_header('Attendance Tracker', 'attendance', [
    'subtitle' => $classLabel !== '' ? $classLabel . ' · your attendance only' : 'Your class attendance',
]);
?>
<?php if ($classId < 1): ?>
  <div class="empty">Your account is not assigned to a class. Ask College Admin to put you in the correct year and section.</div>
<?php else: ?>
<?php if (!$bySub): ?>
  <div class="empty">No attendance marked for <?= e($classLabel) ?> yet.</div>
<?php else: ?>
<div class="grid grid-3">
<?php foreach ($bySub as $name => $a): $pct = $a['total'] ? round(($a['present'] ?? 0) * 100 / $a['total'], 1) : 0; ?>
  <div class="stat">
    <div class="label"><?= e($name) ?></div>
    <div class="value"><?= e((string)$pct) ?>%</div>
    <div class="hint"><?= ($pct < $min) ? 'Below AICTE ' . (int)$min . '%' : 'On track' ?></div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="panel" style="margin-top:1rem">
  <h2>Your sessions</h2>
  <p style="color:var(--muted);font-size:.88rem;margin-top:0">Only your records for <?= e($classLabel) ?>. Classmates’ attendance is not shown.</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Period</th><th>Subject</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($rows, 0, 40) as $r): ?>
      <tr>
        <td><?= e($r['session_date']) ?></td>
        <td><?= e((string)($r['period'] ?? '')) ?></td>
        <td><?= e((string)$r['subject_name']) ?></td>
        <td><?= e($r['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php render_footer(); ?>
