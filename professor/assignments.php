<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
$types = [
  'essay' => 'Essay / Long answer',
  'case_study' => 'Case study',
  'research_review' => 'Research review',
  'problem_solving' => 'Problem solving',
  'mini_project' => 'Mini project',
  'mixed' => 'Mixed / Comprehensive',
  'lab' => 'Lab experiment',
  'reflection' => 'Reflection journal',
  'group_presentation' => 'Group presentation',
];
$assignments = Database::fetchAll(
    'SELECT sa.subject_id, sa.class_id, s.code, s.name, c.year, c.section, c.meta, d.code AS dept_code, d.name AS dept_name, c.name AS class_name
     FROM subject_assignments sa
     JOIN subjects s ON s.id = sa.subject_id
     LEFT JOIN classes c ON c.id = sa.class_id
     LEFT JOIN departments d ON d.id = c.department_id
     WHERE sa.professor_id = ?
     ORDER BY s.name, c.year, c.section',
    [$user['id']]
);
$list = Database::fetchAll(
    'SELECT a.*, c.name AS class_name, c.year, c.section, c.meta, d.code AS dept_code, d.name AS dept_name
     FROM assignments a
     LEFT JOIN classes c ON c.id = a.class_id
     LEFT JOIN departments d ON d.id = c.department_id
     WHERE a.professor_id=? ORDER BY a.id DESC',
    [$user['id']]
);
$viewId = (int)get('id');
$view = $viewId ? Database::fetch(
    'SELECT a.*, c.name AS class_name, c.year, c.section, c.meta, d.code AS dept_code, d.name AS dept_name
     FROM assignments a
     LEFT JOIN classes c ON c.id = a.class_id
     LEFT JOIN departments d ON d.id = c.department_id
     WHERE a.id=? AND a.professor_id=?',
    [$viewId, $user['id']]
) : null;
$subs = $view ? Database::fetchAll(
    'SELECT s.*, u.full_name, u.register_no
     FROM assignment_submissions s
     LEFT JOIN users u ON u.id = s.student_id
     WHERE s.assignment_id = ?',
    [$viewId]
) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('action') === 'grade') {
        $subId = (int)post('submission_id');
        $owned = Database::fetch(
            'SELECT s.id FROM assignment_submissions s
             JOIN assignments a ON a.id = s.assignment_id
             WHERE s.id = ? AND a.professor_id = ?',
            [$subId, $user['id']]
        );
        if (!$owned) {
            flash('error', 'Submission not found.');
            redirect('/professor/assignments.php?id=' . $viewId);
        }
        Database::update('assignment_submissions', [
            'grade' => post('grade'),
            'feedback' => post('feedback'),
            'graded_by' => $user['id'],
            'graded_at' => date('Y-m-d H:i:s'),
            'status' => 'graded',
        ], 'id = :id', ['id' => $subId]);
        flash('success', 'Graded.');
        redirect('/professor/assignments.php?id=' . $viewId);
    }
}

render_header('Assignment Module', 'assignments', ['subtitle' => 'AI briefs + rubrics · all assignment types']);
?>
<div class="grid grid-2">
  <div class="panel">
    <h3>Generate assignment</h3>
    <?php if (!$assignments): ?>
      <div class="empty" style="margin-bottom:1rem">No courses assigned yet. Ask your HOD to assign courses under HOD → Courses.</div>
    <?php endif; ?>
    <form class="form-grid" method="post" action="<?= e(base_url('/api/ai?module=assignment')) ?>" data-ai-form="#aOut">
      <?= csrf_field() ?>
      <input type="hidden" name="module" value="assignment">
      <div class="form-row">
        <label>Type</label>
        <select name="assignment_type">
          <?php foreach ($types as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>Assigned course + class</label>
        <select name="assignment_key" id="assignment_key" required>
          <option value="">Select assignment</option>
          <?php foreach ($assignments as $a):
            $classRow = $a;
          ?>
            <option value="<?= (int)$a['subject_id'] ?>:<?= (int)$a['class_id'] ?>"
              data-subject-id="<?= (int)$a['subject_id'] ?>"
              data-class-id="<?= (int)$a['class_id'] ?>"
              data-name="<?= e($a['name']) ?>">
              <?= e($a['code'] . ' · ' . $a['name'] . ' · ' . class_batch_label($classRow)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="subject_id" id="sid">
        <input type="hidden" name="class_id" id="asgClass">
        <input type="hidden" name="subject" id="sname">
      </div>
      <div class="form-row"><label>Deadline</label><input type="datetime-local" name="deadline"></div>
      <div class="form-row"><label>Context</label><textarea name="context" placeholder="Unit focus, CLO, constraints"></textarea></div>
      <button class="btn btn-accent" type="submit">Generate with AI</button>
    </form>
    <div id="aOut"></div>
  </div>
  <div class="panel">
    <h3>Published assignments</h3>
    <?php if (!$list): ?>
      <div class="empty">None yet. Generate one from the left.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Title</th><th>Class</th><th>Type</th><th>Marks</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $a): ?>
        <tr>
          <td><?= e($a['title']) ?></td>
          <td><?= e(!empty($a['class_id']) ? class_batch_label($a) : '—') ?></td>
          <td><span class="chip"><?= e($a['assignment_type']) ?></span></td>
          <td><?= e((string)$a['max_marks']) ?></td>
          <td><a class="btn btn-sm btn-ghost" href="?id=<?= (int)$a['id'] ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>
<?php if ($view): ?>
<div class="panel" style="margin-top:1rem">
  <h2><?= e($view['title']) ?></h2>
  <?php if (!empty($view['class_id'])): ?>
    <p class="chip-row"><span class="chip"><?= e(class_batch_label($view)) ?></span><span class="chip">Only this class can submit</span></p>
  <?php endif; ?>
  <p><?= nl2br(e((string)$view['description'])) ?></p>
  <h3>Rubric</h3>
  <pre class="rubric-box"><?= e(json_encode(json_decode($view['rubric'] ?: '[]'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
  <h3>Submissions</h3>
  <?php if (!$subs): ?><div class="empty">No submissions yet.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Status</th><th>Grade</th><th>Mark</th></tr></thead>
    <tbody>
    <?php foreach ($subs as $s): ?>
      <tr>
        <td><?= e($s['full_name'] ?? ('#' . $s['student_id'])) ?><?= !empty($s['register_no']) ? ' · ' . e((string)$s['register_no']) : '' ?></td>
        <td><?= status_badge($s['status']) ?></td>
        <td><?= e((string)($s['grade'] ?? '-')) ?></td>
        <td>
          <form method="post" class="form-grid" style="grid-template-columns:80px 1fr auto;gap:.4rem">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="grade">
            <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
            <input name="grade" placeholder="Marks" value="<?= e((string)($s['grade'] ?? '')) ?>">
            <input name="feedback" placeholder="Feedback" value="<?= e((string)($s['feedback'] ?? '')) ?>">
            <button class="btn btn-sm btn-primary" type="submit">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<script>
(function () {
  const key = document.getElementById('assignment_key');
  const sid = document.getElementById('sid');
  const cls = document.getElementById('asgClass');
  const sname = document.getElementById('sname');
  function sync() {
    const opt = key?.selectedOptions[0];
    if (!opt || !opt.value) return;
    sid.value = opt.dataset.subjectId || '';
    cls.value = opt.dataset.classId || '';
    sname.value = opt.dataset.name || '';
  }
  key?.addEventListener('change', sync);
  document.querySelector('[data-ai-form="#aOut"]')?.addEventListener('submit', (ev) => {
    sync();
    if (cls && !cls.value) {
      ev.preventDefault();
      ev.stopImmediatePropagation();
      alert('Select an assigned course and class.');
    }
  }, true);
  sync();
})();
</script>
<?php render_footer(); ?>
