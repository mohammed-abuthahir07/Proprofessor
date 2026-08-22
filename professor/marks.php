<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
$instId = (int)$user['institution_id'];
$classes = professor_manageable_classes($user);
$formulas = Database::fetchAll(
    'SELECT * FROM marks_formulas WHERE institution_id=? ORDER BY is_default DESC, id DESC',
    [$instId]
);
$classId = (int)(get('class_id') ?: post('class_id'));
if ($classId > 0 && !professor_can_manage_class($user, $classId)) {
    $classId = 0;
}
$subjectId = (int)(get('subject_id') ?: post('subject_id'));
if ($subjectId > 0 && ($classId < 1 || !professor_can_manage_subject($user, $subjectId, $classId))) {
    $subjectId = 0;
}
$subjects = $classId > 0 ? professor_subjects($user, $classId) : [];
$formulaId = (int)(get('formula_id') ?: post('formula_id'));
$formula = $formulaId
    ? Database::fetch('SELECT * FROM marks_formulas WHERE id=? AND institution_id=?', [$formulaId, $instId])
    : ($formulas[0] ?? null);
if ($formula && !$formulaId) {
    $formulaId = (int)$formula['id'];
}
if (!$formula) {
    $formula = [
        'id' => 0,
        'name' => 'CBCS fallback · CIA average to 25',
        'pattern' => 'CBCS',
        'plain_english' => 'Average of CIA 1 and CIA 2 scaled to 25 marks.',
        'expression' => '((cia1+cia2)/2)*(25/50)',
        'components' => json_encode([
            ['code' => 'cia1', 'label' => 'CIA 1', 'max' => 50],
            ['code' => 'cia2', 'label' => 'CIA 2', 'max' => 50],
        ]),
    ];
}
$components = json_decode($formula['components'] ?: '[]', true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('action') === 'save_marks') {
        $classId = (int)post('class_id');
        $subjectId = (int)post('subject_id');
        $formulaId = (int)post('formula_id') ?: null;
        $classOk = professor_can_manage_class($user, $classId);
        if (!$classOk) {
            flash('error', 'Class not found.');
            redirect('/professor/marks.php');
        }
        if (!professor_can_manage_subject($user, $subjectId, $classId)) {
            flash('error', 'You are not assigned to this course for the selected class.');
            redirect('/professor/marks.php?class_id=' . $classId);
        }
        if ($subjectId < 1) {
            flash('error', 'Select a subject first.');
            redirect('/professor/marks.php?class_id=' . $classId);
        }
        $rows = post('marks') ?: [];
        $names = post('name') ?: [];
        foreach ($rows as $reg => $compVals) {
            $data = [];
            foreach ($compVals as $code => $val) {
                $data[$code] = is_numeric($val) ? (float)$val : null;
            }
            $total = null;
            if ($formula && !empty($formula['expression'])) {
                $expr = $formula['expression'];
                $ok = true;
                foreach ($data as $code => $val) {
                    if ($val === null) {
                        $ok = false;
                        break;
                    }
                    $expr = preg_replace('/\b' . preg_quote((string)$code, '/') . '\b/', (string)$val, $expr);
                }
                if ($ok && preg_match('/^[0-9+\-.*\/() ]+$/', $expr)) {
                    try {
                        eval('$total = (float)(' . $expr . ');');
                    } catch (Throwable $e) {
                        $total = null;
                    }
                }
            }
            if ($total === null) {
                $vals = array_filter($data, fn($v) => $v !== null);
                $total = $vals ? array_sum($vals) / max(1, count($vals)) : null;
            }
            $letter = null;
            if ($total !== null) {
                $letter = $total >= 22 ? 'O' : ($total >= 18 ? 'A' : ($total >= 15 ? 'B' : ($total >= 12 ? 'C' : 'D')));
            }
            $stuRow = Database::fetch(
                'SELECT user_id FROM students_roster WHERE class_id=? AND register_no=? AND institution_id=?',
                [$classId, $reg, $instId]
            );
            Database::query(
                'INSERT INTO internal_marks
                  (institution_id, professor_id, subject_id, class_id, formula_id, student_id, register_no, student_name, marks_data, computed_total, grade_letter,
                   attendance_pct, assignment_total)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE marks_data=VALUES(marks_data), computed_total=VALUES(computed_total),
                   grade_letter=VALUES(grade_letter), formula_id=VALUES(formula_id), student_name=VALUES(student_name),
                   student_id=VALUES(student_id)',
                [
                    $instId, $user['id'], $subjectId, $classId, $formulaId,
                    $stuRow['user_id'] ?? null, $reg, $names[$reg] ?? $reg, json_encode($data), $total, $letter,
                    $data['attendance'] ?? null, $data['assignment'] ?? null,
                ]
            );
        }
        flash('success', 'Marks saved.');
        redirect('/professor/marks.php?class_id=' . $classId . '&subject_id=' . $subjectId . '&formula_id=' . (int)$formulaId);
    }
}

$roster = $classId ? sync_class_roster($instId, $classId) : [];
$existing = [];
if ($classId && $subjectId) {
    foreach (Database::fetchAll('SELECT * FROM internal_marks WHERE class_id=? AND subject_id=?', [$classId, $subjectId]) as $m) {
        $existing[$m['register_no']] = $m;
    }
}

render_header('Internal Marks', 'marks', ['subtitle' => 'CIA · Assignment · Attendance · Configurable formula']);
?>
<div class="panel">
  <form method="get" class="form-grid" style="grid-template-columns:1fr 1fr 1fr auto;gap:.8rem;align-items:end">
    <div class="form-row"><label>Class</label>
      <select name="class_id">
        <option value="">Select class</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>><?= e(class_batch_label($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>Subject</label>
      <select name="subject_id">
        <option value="">Select subject</option>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['code'] . ' · ' . $s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>Formula</label>
      <select name="formula_id">
        <?php if (!$formulas): ?>
          <option value="0">CBCS fallback (CIA avg → 25)</option>
        <?php endif; ?>
        <?php foreach ($formulas as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= $formulaId === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-ghost" type="submit">Load</button>
  </form>
  <?php if ($formula): ?>
    <div class="alert alert-info" style="margin-top:1rem">
      <strong><?= e($formula['name']) ?>:</strong> <?= e((string)$formula['plain_english']) ?>
      <div><code><?= e((string)$formula['expression']) ?></code></div>
      <?php if (!$formulas): ?>
        <div style="margin-top:.4rem;font-size:.85rem">College Admin can replace this with Anna / Madurai / Bharath / Madras / CBCS on the Formulas page.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!$classId || !$subjectId): ?>
  <div class="panel empty" style="margin-top:1rem">Select class and subject, then enter CIA / component marks. Student lists come from the class roster (same as Attendance).</div>
<?php elseif (!$roster): ?>
  <div class="panel empty" style="margin-top:1rem">No students in this class. Import them on Attendance, or ask College Admin to add students to this class.</div>
<?php else: ?>
<form method="post" class="panel" style="margin-top:1rem">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_marks">
  <input type="hidden" name="class_id" value="<?= $classId ?>">
  <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
  <input type="hidden" name="formula_id" value="<?= $formulaId ?>">
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>Reg</th><th>Name</th>
        <?php foreach ($components as $c): ?><th><?= e($c['label'] ?? $c['code']) ?></th><?php endforeach; ?>
        <th>Total</th><th>Grade</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($roster as $st):
      $ex = $existing[$st['register_no']] ?? null;
      $md = $ex ? (json_decode($ex['marks_data'] ?: '{}', true) ?: []) : [];
    ?>
      <tr>
        <td><?= e($st['register_no']) ?></td>
        <td>
          <?= e($st['full_name']) ?>
          <input type="hidden" name="name[<?= e($st['register_no']) ?>]" value="<?= e($st['full_name']) ?>">
        </td>
        <?php foreach ($components as $c): $code = $c['code']; ?>
          <td><input name="marks[<?= e($st['register_no']) ?>][<?= e($code) ?>]" value="<?= e((string)($md[$code] ?? '')) ?>" style="min-width:70px"></td>
        <?php endforeach; ?>
        <td><?= e((string)($ex['computed_total'] ?? '-')) ?></td>
        <td><?= e((string)($ex['grade_letter'] ?? '-')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <button class="btn btn-primary" type="submit" style="margin-top:1rem">Save & compute</button>
</form>
<?php endif; ?>
<?php render_footer(); ?>
