<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
AssignmentTools::ensureSchema();

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
$templates = AssignmentTools::templatesForProfessor($user);
$viewId = (int)get('id');
$view = $viewId ? AssignmentTools::ownedAssignment($user, $viewId) : null;
if ($viewId && !$view) {
    flash('error', 'Assignment not found.');
    redirect('/professor/assignments.php');
}
if ($view) {
    $classMeta = Database::fetch(
        'SELECT c.name AS class_name, c.year, c.section, c.meta, d.code AS dept_code, d.name AS dept_name
         FROM classes c LEFT JOIN departments d ON d.id = c.department_id WHERE c.id = ?',
        [(int)($view['class_id'] ?? 0)]
    );
    if ($classMeta) {
        $view = array_merge($view, $classMeta);
    }
}
$subs = $view ? Database::fetchAll(
    'SELECT s.*, u.full_name, u.register_no
     FROM assignment_submissions s
     LEFT JOIN users u ON u.id = s.student_id
     WHERE s.assignment_id = ?',
    [$viewId]
) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');

    if ($action === 'delete') {
        $deleteId = (int)post('assignment_id');
        $result = AssignmentTools::deleteOwnedAssignment($user, $deleteId);
        flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Assignment deleted. Students in that class will no longer see it.' : ($result['error'] ?? 'Could not delete.')
        );
        redirect('/professor/assignments.php');
    }

    // Existing grade path — preserved (also marks as finalized for marks hand-off).
    if ($action === 'grade') {
        $subId = (int)post('submission_id');
        $owned = AssignmentTools::ownedSubmission($user, $subId);
        if (!$owned || (int)$owned['assignment_id'] !== $viewId) {
            flash('error', 'Submission not found.');
            redirect('/professor/assignments.php?id=' . $viewId);
        }
        $result = AssignmentTools::finalizeGrade(
            $user,
            $subId,
            (float)post('grade'),
            trim((string)post('feedback'))
        );
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Graded.' : ($result['error'] ?? 'Could not save grade.'));
        redirect('/professor/assignments.php?id=' . $viewId);
    }

    if ($action === 'save_rubric' && $view) {
        $maxMarks = (float)($view['max_marks'] ?? 25);
        $criteria = post('rubric_criterion') ?: [];
        $descs = post('rubric_description') ?: [];
        $marks = post('rubric_marks') ?: [];
        $clos = post('rubric_clo') ?: [];
        $blooms = post('rubric_bloom') ?: [];
        $rubric = [];
        foreach ($criteria as $i => $crit) {
            $crit = trim((string)$crit);
            if ($crit === '') {
                continue;
            }
            $rubric[] = [
                'criterion' => $crit,
                'description' => trim((string)($descs[$i] ?? '')),
                'marks' => (float)($marks[$i] ?? 0),
                'clo' => trim((string)($clos[$i] ?? '')) ?: null,
                'bloom' => strtoupper(trim((string)($blooms[$i] ?? ''))) ?: null,
            ];
        }
        $check = AssignmentTools::validateRubricTotal($rubric, $maxMarks);
        if (!$check['ok']) {
            flash('error', $check['error'] ?? 'Invalid rubric.');
            redirect('/professor/assignments.php?id=' . $viewId);
        }
        Database::update('assignments', [
            'rubric' => json_encode($rubric, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $viewId]);
        flash('success', 'Rubric saved.');
        redirect('/professor/assignments.php?id=' . $viewId);
    }

    if ($action === 'ai_grade' && $view) {
        $subId = (int)post('submission_id');
        $sub = AssignmentTools::ownedSubmission($user, $subId);
        if (!$sub || (int)$sub['assignment_id'] !== $viewId) {
            flash('error', 'Submission not found.');
            redirect('/professor/assignments.php?id=' . $viewId);
        }
        $result = AssignmentTools::aiGradeSubmission($user, $view, $sub);
        flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? ('AI recommendation: ' . $result['ai_score'] . ' / ' . $view['max_marks'] . ' — review and finalize below.')
            : ($result['error'] ?? 'AI grade failed.'));
        redirect('/professor/assignments.php?id=' . $viewId);
    }

    if ($action === 'save_template' && $view) {
        AssignmentTools::saveTemplate($user, $view, (string)(json_decode((string)($view['meta'] ?? '{}'), true)['context'] ?? ''));
        flash('success', 'Saved as reusable template.');
        redirect('/professor/assignments.php?id=' . $viewId);
    }

    if ($action === 'push_marks' && $view) {
        $result = AssignmentTools::pushToInternalMarks($user, $view);
        flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? ('Pushed ' . (int)$result['updated'] . ' finalized grade(s) to Internal Marks (assignment component).')
            : ($result['error'] ?? 'Push failed.'));
        redirect('/professor/assignments.php?id=' . $viewId);
    }

    if ($action === 'extension_decide' && $view) {
        $result = AssignmentTools::decideExtension(
            $user,
            (int)post('request_id'),
            (string)post('decision'),
            trim((string)post('professor_note'))
        );
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Extension updated.' : ($result['error'] ?? 'Failed.'));
        redirect('/professor/assignments.php?id=' . $viewId);
    }

    if ($action === 'run_similarity' && $view) {
        $report = AssignmentTools::similarityReport($view, $subs);
        $meta = json_decode((string)($view['meta'] ?? '{}'), true) ?: [];
        $meta['similarity_report'] = $report;
        $meta['similarity_at'] = date('c');
        Database::update('assignments', [
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $viewId]);
        flash('success', count($report) ? ('Found ' . count($report) . ' similar pair(s).') : 'No significant similarity above threshold.');
        redirect('/professor/assignments.php?id=' . $viewId);
    }
}

$rubricRows = $view ? AssignmentTools::normalizeRubric($view['rubric'] ?? [], (float)($view['max_marks'] ?? 25)) : [];
$extensions = $view ? AssignmentTools::pendingExtensions($viewId) : [];
$viewMeta = $view ? (json_decode((string)($view['meta'] ?? '{}'), true) ?: []) : [];
$similarityReport = is_array($viewMeta['similarity_report'] ?? null) ? $viewMeta['similarity_report'] : [];
$rosterCount = 0;
$analytics = null;
if ($view && !empty($view['class_id']) && !empty($view['subject_id'])) {
    $rosterCount = count(students_for_current_course_context(
        (int)$user['institution_id'],
        (int)$view['class_id'],
        (int)$view['subject_id'],
        (int)$user['id']
    ));
    $analytics = AssignmentTools::analytics($view, $subs, $rosterCount);
}
$aiDetectOk = AssignmentTools::aiContentDetectionConfigured();

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
      <?php if ($templates): ?>
      <div class="form-row">
        <label>Use template (optional)</label>
        <select name="template_id">
          <option value="">— Generate with AI —</option>
          <?php foreach ($templates as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= e($t['title']) ?> (<?= e((string)$t['max_marks']) ?> marks)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
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
      <div class="form-row">
        <label>Also create for other sections (same course)</label>
        <div id="bulkClasses" class="chip-row" style="flex-wrap:wrap;gap:.35rem"></div>
        <p class="muted" style="margin:0;font-size:.8rem">Each section gets its own assignment. Students only see their section’s copy.</p>
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
          <td class="ppt-row-actions">
            <a class="btn btn-sm btn-ghost" href="?id=<?= (int)$a['id'] ?>">Open</a>
            <form method="post" style="margin:0" onsubmit="return confirm('Delete this assignment? Students in this class will no longer see it.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
              <button class="btn btn-sm btn-ghost" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>
<?php if ($view): ?>
<div class="panel" style="margin-top:1rem">
  <div class="panel-h" style="align-items:flex-start">
    <div>
      <h2 style="margin:0"><?= e($view['title']) ?></h2>
      <?php if (!empty($view['class_id'])): ?>
        <p class="chip-row"><span class="chip"><?= e(class_batch_label($view)) ?></span><span class="chip">Only this class can submit</span></p>
      <?php endif; ?>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_template"><button class="btn btn-sm btn-ghost" type="submit">Save as Template</button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="run_similarity"><button class="btn btn-sm btn-ghost" type="submit">Run Similarity</button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="push_marks"><button class="btn btn-sm btn-accent" type="submit">Push to Internal Marks</button></form>
    </div>
  </div>
  <p><?= nl2br(e((string)$view['description'])) ?></p>

  <?php if ($analytics): ?>
  <h3>Class performance</h3>
  <div class="chip-row" style="margin-bottom:.75rem">
    <span class="chip">Students <?= (int)$analytics['roster'] ?></span>
    <span class="chip">Submitted <?= (int)$analytics['submitted'] ?></span>
    <span class="chip">Not submitted <?= (int)$analytics['not_submitted'] ?></span>
    <span class="chip">Avg <?= $analytics['average'] !== null ? e((string)$analytics['average']) : '—' ?></span>
    <span class="chip">High <?= $analytics['highest'] !== null ? e((string)$analytics['highest']) : '—' ?></span>
    <span class="chip">Low <?= $analytics['lowest'] !== null ? e((string)$analytics['lowest']) : '—' ?></span>
    <span class="chip">Avg % <?= $analytics['avg_percent'] !== null ? e((string)$analytics['avg_percent']) . '%' : '—' ?></span>
  </div>
  <?php if (!empty($analytics['criterion_performance'])): ?>
  <div class="table-wrap" style="margin-bottom:1rem"><table>
    <thead><tr><th>Rubric criterion</th><th>Avg performance</th></tr></thead>
    <tbody>
    <?php foreach ($analytics['criterion_performance'] as $cp): ?>
      <tr>
        <td><?= e($cp['criterion']) ?><?= !empty($cp['weak']) ? ' <span class="badge badge-warn">Weak</span>' : '' ?></td>
        <td><?= $cp['avg_percent'] !== null ? e((string)$cp['avg_percent']) . '%' : '— (grade to populate)' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php endif; ?>

  <h3>Rubric</h3>
  <p class="muted">Total must equal <?= e((string)$view['max_marks']) ?> marks. Edit CLO / Bloom as needed.</p>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_rubric">
    <div class="table-wrap"><table>
      <thead><tr><th>Criterion</th><th>Description</th><th>Marks</th><th>CLO</th><th>Bloom</th></tr></thead>
      <tbody>
      <?php
      $editRows = $rubricRows ?: [['criterion'=>'','description'=>'','marks'=>'','clo'=>'','bloom'=>'']];
      foreach ($editRows as $r):
      ?>
        <tr>
          <td><input name="rubric_criterion[]" value="<?= e((string)$r['criterion']) ?>" required></td>
          <td><input name="rubric_description[]" value="<?= e((string)($r['description'] ?? '')) ?>"></td>
          <td><input name="rubric_marks[]" type="number" step="0.5" min="0" value="<?= e((string)$r['marks']) ?>" style="width:5rem"></td>
          <td><input name="rubric_clo[]" value="<?= e((string)($r['clo'] ?? '')) ?>" style="width:5rem" placeholder="CLO1"></td>
          <td><input name="rubric_bloom[]" value="<?= e((string)($r['bloom'] ?? '')) ?>" style="width:4rem" placeholder="K3"></td>
        </tr>
      <?php endforeach; ?>
        <tr>
          <td><input name="rubric_criterion[]" placeholder="Add criterion…"></td>
          <td><input name="rubric_description[]"></td>
          <td><input name="rubric_marks[]" type="number" step="0.5" min="0" style="width:5rem"></td>
          <td><input name="rubric_clo[]" style="width:5rem"></td>
          <td><input name="rubric_bloom[]" style="width:4rem"></td>
        </tr>
      </tbody>
    </table></div>
    <button class="btn btn-primary" type="submit">Save rubric</button>
  </form>

  <h3 style="margin-top:1.25rem">Similarity</h3>
  <?php if ($similarityReport): ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Student A</th><th>Student B</th><th>Similarity</th></tr></thead>
    <tbody>
    <?php foreach ($similarityReport as $pair): ?>
      <tr>
        <td><?= e($pair['student_a_name']) ?></td>
        <td><?= e($pair['student_b_name']) ?></td>
        <td><strong><?= e((string)$pair['percent']) ?>%</strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
    <p class="muted">Run similarity to compare text submissions in this assignment (token/Jaccard + similar_text).</p>
  <?php endif; ?>
  <p class="muted"><?= $aiDetectOk
    ? 'AI-content detection provider is marked configured.'
    : 'AI-content detection unavailable — provider configuration required. No invented detection scores are shown.' ?></p>

  <?php if ($extensions): ?>
  <h3>Extension requests</h3>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Reason</th><th>Requested</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($extensions as $ex): ?>
      <tr>
        <td><?= e($ex['full_name'] ?? '') ?><?= !empty($ex['register_no']) ? ' · ' . e((string)$ex['register_no']) : '' ?></td>
        <td><?= e((string)$ex['reason']) ?></td>
        <td><?= e((string)$ex['requested_deadline']) ?></td>
        <td><?= status_badge($ex['status']) ?></td>
        <td>
          <?php if ($ex['status'] === 'pending'): ?>
          <form method="post" style="display:flex;gap:.35rem;flex-wrap:wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="extension_decide">
            <input type="hidden" name="request_id" value="<?= (int)$ex['id'] ?>">
            <input name="professor_note" placeholder="Note (optional)" style="min-width:8rem">
            <button class="btn btn-sm btn-primary" name="decision" value="approved" type="submit">Approve</button>
            <button class="btn btn-sm btn-ghost" name="decision" value="rejected" type="submit">Reject</button>
          </form>
          <?php elseif ($ex['status'] === 'approved'): ?>
            Until <?= e((string)$ex['approved_deadline']) ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <h3>Submissions</h3>
  <?php if (!$subs): ?><div class="empty">No submissions yet.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Status</th><th>AI / Final</th><th>Mark</th></tr></thead>
    <tbody>
    <?php foreach ($subs as $s):
      $sMeta = json_decode((string)($s['meta'] ?? '{}'), true) ?: [];
      $aiG = is_array($sMeta['ai_grade'] ?? null) ? $sMeta['ai_grade'] : null;
      $fin = is_array($sMeta['final_grade'] ?? null) ? $sMeta['final_grade'] : null;
      $sim = is_array($sMeta['similarity'] ?? null) ? $sMeta['similarity'] : [];
      $maxSim = 0.0;
      foreach ($sim as $sv) {
          $maxSim = max($maxSim, (float)($sv['percent'] ?? 0));
      }
    ?>
      <tr>
        <td>
          <?= e($s['full_name'] ?? ('#' . $s['student_id'])) ?><?= !empty($s['register_no']) ? ' · ' . e((string)$s['register_no']) : '' ?>
          <?php if ($maxSim >= 35): ?><br><span class="badge badge-warn">Similarity <?= e((string)$maxSim) ?>%</span><?php endif; ?>
          <?php if (!empty($s['content_text'])): ?>
            <details style="margin-top:.35rem"><summary class="muted">View text</summary><pre style="white-space:pre-wrap;max-height:12rem;overflow:auto"><?= e(mb_substr((string)$s['content_text'], 0, 4000)) ?></pre></details>
          <?php endif; ?>
        </td>
        <td><?= status_badge($s['status']) ?></td>
        <td style="font-size:.85rem">
          <?php if ($aiG): ?>
            AI: <?= e((string)$aiG['score']) ?> / <?= e((string)$view['max_marks']) ?>
            <div class="muted"><?= e(mb_substr((string)($aiG['feedback'] ?? ''), 0, 160)) ?></div>
          <?php else: ?>
            <span class="muted">No AI recommendation</span>
          <?php endif; ?>
          <?php if ($fin): ?>
            <div><strong>Final:</strong> <?= e((string)$fin['score']) ?><?= !empty($fin['override']) ? ' (override)' : '' ?></div>
          <?php endif; ?>
          <form method="post" style="margin-top:.35rem"><?= csrf_field() ?>
            <input type="hidden" name="action" value="ai_grade">
            <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-sm btn-ghost" type="submit">AI first-pass</button>
          </form>
        </td>
        <td>
          <form method="post" class="form-grid" style="grid-template-columns:80px 1fr auto;gap:.4rem">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="grade">
            <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
            <input name="grade" placeholder="Marks" value="<?= e((string)($s['grade'] ?? ($aiG['score'] ?? ''))) ?>">
            <input name="feedback" placeholder="Feedback" value="<?= e((string)($s['feedback'] ?? ($aiG['feedback'] ?? ''))) ?>">
            <button class="btn btn-sm btn-primary" type="submit">Finalize</button>
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
  const bulk = document.getElementById('bulkClasses');
  const pairs = <?= json_encode(array_map(static function ($a) {
      return [
          'subject_id' => (int)$a['subject_id'],
          'class_id' => (int)$a['class_id'],
          'label' => trim(($a['code'] ?? '') . ' · ' . class_batch_label($a)),
      ];
  }, $assignments), JSON_UNESCAPED_UNICODE) ?>;

  function sync() {
    const opt = key?.selectedOptions[0];
    if (!opt || !opt.value) {
      if (bulk) bulk.innerHTML = '';
      return;
    }
    sid.value = opt.dataset.subjectId || '';
    cls.value = opt.dataset.classId || '';
    sname.value = opt.dataset.name || '';
    const subj = String(sid.value);
    const primary = String(cls.value);
    if (!bulk) return;
    bulk.innerHTML = '';
    pairs.filter(p => String(p.subject_id) === subj && String(p.class_id) !== primary).forEach(p => {
      const lab = document.createElement('label');
      lab.style.cssText = 'display:inline-flex;align-items:center;gap:.3rem;font-size:.82rem';
      lab.innerHTML = '<input type="checkbox" name="bulk_class_ids[]" value="' + p.class_id + '"> ' + p.label;
      bulk.appendChild(lab);
    });
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
