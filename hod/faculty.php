<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$deptId = (int)($user['department_id'] ?? 0);
$instId = (int)$user['institution_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'announce') {
    verify_csrf();
    $title = trim((string)post('title'));
    $body = trim((string)post('body'));
    if ($title === '' || $body === '') {
        flash('error', 'Title and message are required.');
        redirect('/hod/faculty.php');
    }
    if ($deptId < 1 && ($user['role'] ?? '') === 'hod') {
        flash('error', 'Your HOD account is not linked to a department.');
        redirect('/hod/faculty.php');
    }
    Database::insert('announcements', [
        'institution_id' => $instId,
        'department_id' => $deptId ?: null,
        'created_by' => (int)$user['id'],
        'title' => $title,
        'body' => $body,
        'announcement_type' => in_array((string)post('announcement_type', 'circular'), ['circular', 'deadline', 'exam', 'general'], true)
            ? (string)post('announcement_type', 'circular')
            : 'circular',
    ]);
    $profs = Database::fetchAll(
        'SELECT id FROM users WHERE institution_id=? AND role="professor" AND is_active=1'
        . ($deptId ? ' AND department_id=?' : ''),
        $deptId ? [$instId, $deptId] : [$instId]
    );
    foreach ($profs as $p) {
        notify_user((int)$p['id'], 'system', $title, $body, '/professor/notifications.php');
    }
    flash('success', 'Circular sent to department faculty.');
    redirect('/hod/faculty.php');
}

$facultySql = 'SELECT u.id, u.full_name, u.email,
            SUM(p.status="approved") approved,
            SUM(p.status IN ("submitted","under_review")) pending,
            COUNT(p.id) total,
            AVG(p.ai_score) avg_score
     FROM users u
     LEFT JOIN course_plans p ON p.professor_id=u.id
     WHERE u.institution_id=? AND u.role="professor" AND u.is_active=1';
$facultyParams = [$instId];
if ($deptId) {
    $facultySql .= ' AND u.department_id=?';
    $facultyParams[] = $deptId;
}
$facultySql .= ' GROUP BY u.id ORDER BY u.full_name';
$faculty = Database::fetchAll($facultySql, $facultyParams);
render_header('Faculty Management', 'faculty');
?>
<div class="panel">
  <h3>Department circular</h3>
  <p style="color:var(--muted);font-size:.85rem;margin-top:0">Sent to professors in this department. Students in the same department see it on their calendar. Other departments do not.</p>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="announce">
    <div class="form-row two">
      <div><label>Title</label><input name="title" required placeholder="CIA 1 schedule"></div>
      <div>
        <label>Type</label>
        <select name="announcement_type">
          <option value="circular">Circular</option>
          <option value="deadline">Deadline</option>
          <option value="exam">Exam</option>
          <option value="general">General</option>
        </select>
      </div>
    </div>
    <div class="form-row"><label>Message</label><textarea name="body" required placeholder="Message for department faculty…"></textarea></div>
    <button class="btn btn-primary" type="submit">Send to department</button>
  </form>
</div>
<div class="panel" style="margin-top:1rem">
  <div class="table-wrap"><table>
    <thead><tr><th>Faculty</th><th>Plans</th><th>Approved</th><th>Pending</th><th>Avg AI</th></tr></thead>
    <tbody>
    <?php foreach ($faculty as $f): ?>
      <tr>
        <td><strong><?= e($f['full_name']) ?></strong><div style="color:var(--muted);font-size:.8rem"><?= e($f['email']) ?></div></td>
        <td><?= (int)$f['total'] ?></td>
        <td><?= (int)$f['approved'] ?></td>
        <td><?= (int)$f['pending'] ?></td>
        <td><?= $f['avg_score']!==null?round((float)$f['avg_score'],1):'-' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php render_footer(); ?>
