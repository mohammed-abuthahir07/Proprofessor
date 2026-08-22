<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'submit') {
    verify_csrf();
    $id = (int)post('plan_id');
    Database::update('course_plans', [
        'status' => 'submitted',
        'submitted_at' => date('Y-m-d H:i:s'),
    ], 'id = :id AND professor_id = :pid AND institution_id = :iid', ['id'=>$id,'pid'=>$user['id'],'iid'=>$user['institution_id']]);
    $hod = Database::fetch(
        'SELECT hod_user_id FROM departments WHERE id = ?',
        [$user['department_id']]
    );
    $hodUserId = (int)($hod['hod_user_id'] ?? 0);
    if ($hodUserId < 1 && !empty($user['department_id'])) {
        $fallbackHod = Database::fetch(
            'SELECT id FROM users WHERE department_id = ? AND role = "hod" AND is_active = 1 ORDER BY id ASC LIMIT 1',
            [$user['department_id']]
        );
        $hodUserId = (int)($fallbackHod['id'] ?? 0);
    }
    if ($hodUserId > 0) {
        notify_user($hodUserId, 'approval', 'Course plan submitted', 'A faculty plan awaits review.', '/hod/approvals.php');
    }
    flash('success', 'Plan submitted to HOD.');
    redirect('/professor/plans.php');
}

$plans = Database::fetchAll(
    'SELECT * FROM course_plans WHERE professor_id = ? AND institution_id = ? ORDER BY updated_at DESC',
    [$user['id'], $user['institution_id']]
);
render_header('My Course Plans', 'plans', ['subtitle' => 'Draft ? Submitted ? Approved']);
?>
<div class="panel">
  <div class="panel-h">
    <h2>All plans</h2>
    <a class="btn btn-primary btn-sm" href="<?= e(base_url('/professor/generate-plan.php')) ?>">+ New</a>
  </div>
  <?php if (!$plans): ?>
    <div class="empty">No course plans yet.</div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Title</th><th>Subject</th><th>Version</th><th>Status</th><th>AI</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($plans as $p): ?>
      <tr>
        <td><a href="<?= e(base_url('/professor/plan-view.php?id='.$p['id'])) ?>"><?= e($p['title']) ?></a></td>
        <td><?= e($p['subject_name']) ?></td>
        <td>v<?= (int)$p['version'] ?></td>
        <td><?= status_badge($p['status']) ?></td>
        <td><?= e((string)($p['ai_score'] ?? '-')) ?></td>
        <td>
          <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/professor/plan-view.php?id='.$p['id'])) ?>">Open</a>
          <?php if (in_array($p['status'], ['draft', 'returned', 'under_review'], true)): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-sm btn-primary" type="submit">Submit</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
