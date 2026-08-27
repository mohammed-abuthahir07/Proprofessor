<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
AttendanceTools::ensureSchema();
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

// Current academic context only — same subject scope as Internal Marks / My Courses.
$currentSubjects = courses_for_student($user);
$allowedSubjectIds = array_map(static fn($s) => (int)$s['id'], $currentSubjects);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('action') === 'request_regularization') {
        $proofUrl = null;
        if (!empty($_FILES['proof']['name'])) {
            $ext = pathinfo((string)$_FILES['proof']['name'], PATHINFO_EXTENSION);
            $name = 'reg_' . $user['id'] . '_' . time() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
            $dir = dirname(__DIR__) . '/uploads/attendance';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $dest = $dir . '/' . $name;
            if (move_uploaded_file((string)$_FILES['proof']['tmp_name'], $dest)) {
                $proofUrl = '/professor/uploads/attendance/' . $name;
            }
        }
        $result = AttendanceTools::requestRegularization(
            $user,
            (int)post('session_id'),
            (string)post('requested_status'),
            (string)post('reason'),
            $proofUrl
        );
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Regularization request submitted.' : ($result['error'] ?? 'Failed.'));
        redirect('/student/attendance.php');
    }
}

$rows = [];
if ($classId && $allowedSubjectIds) {
    $placeholders = implode(',', array_fill(0, count($allowedSubjectIds), '?'));
    $params = [(int)$user['institution_id'], $classId, (int)$user['id'], $reg];
    foreach ($allowedSubjectIds as $sid) {
        $params[] = $sid;
    }
    $rows = Database::fetchAll(
        "SELECT s.name AS subject_name, r.status, sess.session_date, sess.period, sess.id AS session_id, sess.subject_id
         FROM attendance_records r
         JOIN attendance_sessions sess ON sess.id = r.session_id
           AND sess.institution_id = ?
           AND sess.class_id = ?
         JOIN subjects s ON s.id = sess.subject_id
         WHERE (r.student_id = ? OR (r.register_no <> '' AND r.register_no = ?))
           AND sess.subject_id IN ($placeholders)
         ORDER BY sess.session_date DESC, sess.period DESC",
        $params
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
<?php foreach ($bySub as $name => $a):
  $pct = $a['total'] ? round(($a['present'] ?? 0) * 100 / $a['total'], 1) : 0;
  $band = AttendanceTools::shortageBand($pct, $min);
?>
  <div class="stat">
    <div class="label"><?= e($name) ?></div>
    <div class="value"><?= e((string)$pct) ?>%</div>
    <div class="hint"><?= e($band['label']) ?></div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="panel" style="margin-top:1rem">
  <h2>Your sessions</h2>
  <p style="color:var(--muted);font-size:.88rem;margin-top:0">Only your records for <?= e($classLabel) ?>. Classmates’ attendance is not shown. Use a professor QR link to check in.</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Period</th><th>Subject</th><th>Status</th><th>Regularize</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($rows, 0, 40) as $r): ?>
      <tr>
        <td><?= e($r['session_date']) ?></td>
        <td><?= e((string)($r['period'] ?? '')) ?></td>
        <td><?= e((string)$r['subject_name']) ?></td>
        <td><?= e($r['status']) ?></td>
        <td>
          <?php if (in_array((string)$r['status'], ['absent', 'late', 'excused'], true)): ?>
          <details>
            <summary class="muted">Request</summary>
            <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:.4rem;min-width:14rem">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="request_regularization">
              <input type="hidden" name="session_id" value="<?= (int)$r['session_id'] ?>">
              <div class="form-row" style="margin:0">
                <label>Requested status</label>
                <select name="requested_status">
                  <option value="present">Present</option>
                  <option value="late">Late</option>
                  <option value="excused">Excused</option>
                </select>
              </div>
              <div class="form-row" style="margin:0"><label>Reason</label><textarea name="reason" required maxlength="1000"></textarea></div>
              <div class="form-row" style="margin:0"><label>Proof</label><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
              <button class="btn btn-sm btn-primary" type="submit">Submit</button>
            </form>
          </details>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php render_footer(); ?>
