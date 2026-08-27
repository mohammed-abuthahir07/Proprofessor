<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod');
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
    if ($deptId < 1) {
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
        notify_user((int)$p['id'], 'system', $title, $body, '/professor/notifications.php', [
            'priority' => 'medium',
            'category' => 'system',
            'action' => ['type' => 'OPEN_NOTIFICATIONS'],
        ]);
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

$perPage = 10;
$facultyTotal = count($faculty);
$facultyTotalPages = max(1, (int)ceil($facultyTotal / $perPage));
$facultyPage = (int)($_GET['page'] ?? 1);
if ($facultyPage < 1) {
    $facultyPage = 1;
}
if ($facultyPage > $facultyTotalPages) {
    $facultyPage = $facultyTotalPages;
}
$facultyOffset = ($facultyPage - 1) * $perPage;
$facultyPageRows = $facultyTotal > 0 ? array_slice($faculty, $facultyOffset, $perPage) : [];
$showingFrom = $facultyTotal > 0 ? ($facultyOffset + 1) : 0;
$showingTo = min($facultyOffset + $perPage, $facultyTotal);

$facultyPageQuery = static function (int $page): string {
    $params = [];
    if ($page > 1) {
        $params['page'] = $page;
    }
    $query = http_build_query($params);
    return url('/hod/faculty' . ($query !== '' ? '?' . $query : ''));
};

render_header('Faculty Management', 'faculty');
?>
<div class="panel">
  <h3>Department circular</h3>
  <p style="color:var(--muted);font-size:.85rem;margin-top:0">Sends to <strong>professors in this department only</strong> (not College Admin → HOD messages). Students in the same department may also see it on their calendar.</p>
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
<div class="panel" style="margin-top:1.25rem">
  <div class="panel-h" style="align-items:center;margin-bottom:.65rem">
    <strong>Faculty</strong>
    <?php if ($facultyTotal > 0): ?>
      <span class="chip"><?= (int)$showingFrom ?>–<?= (int)$showingTo ?> of <?= (int)$facultyTotal ?> · 10 per page</span>
    <?php endif; ?>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Faculty</th><th>Plans</th><th>Approved</th><th>Pending</th><th>Avg AI</th></tr></thead>
    <tbody>
    <?php if (!$facultyPageRows): ?>
      <tr><td colspan="5" class="muted">No faculty in this department yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($facultyPageRows as $f): ?>
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
  <?php if ($facultyTotalPages > 1): ?>
  <div class="panel-h" style="margin-top:1rem;align-items:center">
    <span class="chip">Page <?= (int)$facultyPage ?> / <?= (int)$facultyTotalPages ?></span>
    <div style="display:flex;gap:.4rem">
      <?php if ($facultyPage > 1): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e($facultyPageQuery($facultyPage - 1)) ?>">Previous</a>
      <?php else: ?>
        <button class="btn btn-sm btn-ghost" type="button" disabled>Previous</button>
      <?php endif; ?>
      <?php if ($facultyPage < $facultyTotalPages): ?>
        <a class="btn btn-sm btn-primary" href="<?= e($facultyPageQuery($facultyPage + 1)) ?>">Next</a>
      <?php else: ?>
        <button class="btn btn-sm btn-ghost" type="button" disabled>Next</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
