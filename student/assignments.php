<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
$classId = student_class_id($user);
$classLabel = $classId ? class_label_by_id($classId) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $aid = (int)post('assignment_id');
    if (!student_can_submit_assignment($aid, $user)) {
        flash('error', 'This assignment is not for your class.');
        redirect('/student/assignments.php');
    }
    $text = trim((string)post('content_text'));
    $fileUrl = null;
    if (!empty($_FILES['file']['name'])) {
        $ext = pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION);
        $name = 'asg_' . $user['id'] . '_' . time() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
        $dir = dirname(__DIR__) . '/uploads/assignments';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $dest = $dir . '/' . $name;
        if (move_uploaded_file((string)$_FILES['file']['tmp_name'], $dest)) {
            $fileUrl = '/professor/uploads/assignments/' . $name;
        }
    }
    Database::query(
        'INSERT INTO assignment_submissions (assignment_id, student_id, content_text, file_url, submitted_at, status)
         VALUES (?,?,?,?,NOW(),"submitted")
         ON DUPLICATE KEY UPDATE content_text=VALUES(content_text), file_url=COALESCE(VALUES(file_url), file_url),
           submitted_at=NOW(), status="submitted"',
        [$aid, $user['id'], $text, $fileUrl]
    );
    flash('success', 'Submitted.');
    redirect('/student/assignments.php');
}

$list = assignments_visible_to_student($user);
render_header('Assignments', 'assignments', [
    'subtitle' => $classLabel !== '' ? $classLabel : 'Your class assignments',
]);
?>
<?php if ($classId < 1): ?>
  <div class="empty">Your account is not assigned to a class. Ask College Admin to put you in the correct year and section.</div>
<?php else: ?>
<?php foreach ($list as $a): ?>
<div class="panel" style="margin-bottom:1rem">
  <div class="panel-h">
    <div>
      <h3><?= e($a['title']) ?></h3>
      <div class="chip-row">
        <span class="chip"><?= e($a['assignment_type']) ?></span>
        <span class="chip"><?= e($classLabel) ?></span>
        <span class="chip">Due <?= e((string)$a['deadline']) ?></span>
      </div>
    </div>
    <div><?= $a['sub_status'] ? status_badge($a['sub_status']) : '<span class="badge badge-warn">Pending</span>' ?></div>
  </div>
  <p><?= nl2br(e((string)$a['description'])) ?></p>
  <?php if ($a['grade'] !== null): ?>
    <div class="alert alert-success">Grade: <?= e((string)$a['grade']) ?> · <?= e((string)$a['feedback']) ?></div>
  <?php else: ?>
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
    <div class="form-row"><label>Your answer</label><textarea name="content_text" required></textarea></div>
    <div class="form-row"><label>File (optional)</label><input type="file" name="file"></div>
    <button class="btn btn-primary" type="submit">Submit</button>
  </form>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!$list): ?>
  <div class="empty">No assignments for <?= e($classLabel ?: 'your class') ?> yet.</div>
<?php endif; ?>
<?php endif; ?>
<?php render_footer(); ?>
