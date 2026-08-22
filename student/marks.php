<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
$classId = student_class_id($user);
$classLabel = $classId ? class_label_by_id($classId) : '';
$reg = trim((string)($user['register_no'] ?? ''));
$marks = [];
if ($classId) {
    $marks = Database::fetchAll(
        'SELECT m.*, s.name AS subject_name FROM internal_marks m
         JOIN subjects s ON s.id = m.subject_id
         WHERE m.institution_id = ? AND m.class_id = ?
           AND (m.student_id = ? OR (m.register_no <> "" AND m.register_no = ?))
         ORDER BY s.name',
        [$user['institution_id'], $classId, $user['id'], $reg]
    );
}
render_header('Internal Marks', 'marks', [
    'subtitle' => $classLabel !== '' ? $classLabel : 'Your class marks',
]);
?>
<div class="panel">
  <?php if ($classId < 1): ?>
    <div class="empty">Your account is not assigned to a class. Ask College Admin to put you in the correct year and section.</div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Subject</th><th>Components</th><th>Total</th><th>Grade</th></tr></thead>
    <tbody>
    <?php foreach ($marks as $m): $md = json_decode($m['marks_data'] ?: '{}', true) ?: []; ?>
      <tr>
        <td><?= e($m['subject_name']) ?></td>
        <td><?php foreach ($md as $k => $v): ?><span class="chip"><?= e("$k: $v") ?></span> <?php endforeach; ?></td>
        <td><?= e((string)$m['computed_total']) ?></td>
        <td><strong><?= e((string)$m['grade_letter']) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (!$marks): ?><div class="empty">Marks for <?= e($classLabel) ?> are not published yet.</div><?php endif; ?>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
