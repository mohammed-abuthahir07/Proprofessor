<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

use App\Models\MarksFormula;

Auth::requireRole('professor', 'admin');
$user = Auth::user();
$instId = (int)$user['institution_id'];
$academicYear = institution_academic_year($instId);
MarksFormula::ensureInternalMarksSchema();
MarksFormula::repairEmptyComponents((int)$user['institution_id']);

$classes = professor_manageable_classes($user);
$classId = (int)(get('class_id') ?: post('class_id'));
if ($classId > 0 && !professor_can_manage_class($user, $classId)) {
    $classId = 0;
}
$subjectId = (int)(get('subject_id') ?: post('subject_id'));
if ($subjectId > 0 && ($classId < 1 || !professor_can_manage_subject($user, $subjectId, $classId))) {
    $subjectId = 0;
}
$subjects = $classId > 0 ? professor_subjects($user, $classId) : [];

/**
 * Resolve formula for class+subject from Admin config (never from browser formula_id).
 *
 * @return array{0:?array,1:?array,2:list<array{code:string,label:string,max:float}>}
 */
$resolveContext = static function (int $classId, int $subjectId) use ($instId): array {
    if ($classId < 1 || $subjectId < 1) {
        return [null, null, []];
    }
    $subject = Database::fetch(
        'SELECT id, department_id, name, code, meta FROM subjects WHERE id = ? AND institution_id = ? AND is_active = 1',
        [$subjectId, $instId]
    );
    if (!$subject) {
        return [null, null, []];
    }
    $class = Database::fetch(
        'SELECT id, department_id FROM classes WHERE id = ? AND institution_id = ?',
        [$classId, $instId]
    );
    $deptId = (int)($subject['department_id'] ?? 0) ?: (int)($class['department_id'] ?? 0) ?: null;
    $type = MarksFormula::subjectTypeFromMeta($subject['meta'] ?? null);
    $formula = MarksFormula::resolveForContext($instId, $deptId, $subjectId, $type);
    if (!$formula) {
        $formula = MarksFormula::systemFallback();
    }
    if (!empty($formula['subject_id']) && empty($formula['subject_name'])) {
        $formula['subject_name'] = (string)$subject['name'];
        $formula['subject_code'] = (string)$subject['code'];
    }
    $components = MarksFormula::componentsForFormula($formula);
    return [$subject, $formula, $components];
};

/** @return list<array<string,mixed>> */
$loadStudents = static function (int $classId, int $subjectId) use ($instId, $user): array {
    // CURRENT academic-context roster only (year + semester + class + dept + assignment).
    return students_for_current_course_context(
        $instId,
        $classId,
        $subjectId,
        (($user['role'] ?? '') === 'professor') ? (int)$user['id'] : null
    );
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('action') === 'save_marks') {
        $classId = (int)post('class_id');
        $subjectId = (int)post('subject_id');

        if (!professor_can_manage_class($user, $classId)) {
            flash('error', 'Class not found or not assigned to you.');
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

        [, $formula, $components] = $resolveContext($classId, $subjectId);
        if (!$formula || !$components) {
            flash('error', 'No applicable marks formula found. Ask College Admin to configure Marks Formulas.');
            redirect('/professor/marks.php?class_id=' . $classId . '&subject_id=' . $subjectId);
        }

        $formulaId = (int)($formula['id'] ?? 0) ?: null;
        $totalMax = (float)($formula['total_max'] ?? 25);
        $expression = (string)($formula['expression'] ?? '');
        $students = $loadStudents($classId, $subjectId);
        $allowedRegs = [];
        foreach ($students as $st) {
            $allowedRegs[(string)$st['register_no']] = $st;
        }

        $posted = post('marks') ?: [];
        $names = post('name') ?: [];
        $attPctMap = MarksFormula::attendancePercentages($classId, $subjectId);
        $asnPull = [];
        foreach ($components as $c) {
            if (MarksFormula::isAssignmentComponent($c['code'], $c['label'])) {
                $asnPull = MarksFormula::assignmentMarksForClassSubject(
                    $instId,
                    $classId,
                    $subjectId,
                    (float)$c['max']
                );
                break;
            }
        }
        $saved = 0;
        $errors = [];

        foreach ($posted as $reg => $compVals) {
            $reg = (string)$reg;
            if (!isset($allowedRegs[$reg])) {
                $errors[] = "Register $reg is not in this class/subject.";
                continue;
            }
            $compVals = is_array($compVals) ? $compVals : [];

            // Auto-fill attendance from Attendance module when formula requires it and field left empty.
            // Auto-fill assignment from finalized Assignment grades when available (never invent 0).
            foreach ($components as $c) {
                $code = $c['code'];
                $raw = $compVals[$code] ?? null;
                foreach ($compVals as $vk => $vv) {
                    if (strcasecmp((string)$vk, $code) === 0) {
                        $raw = $vv;
                        break;
                    }
                }
                if (($raw === null || $raw === '') && MarksFormula::isAttendanceComponent($code, $c['label'])) {
                    // Existing behavior: scale attendance % (0 when no sessions recorded for this student).
                    $pct = (float)($attPctMap[$reg] ?? 0.0);
                    $compVals[$code] = MarksFormula::attendanceMarkFromPercent($pct, (float)$c['max']);
                }
                if (($raw === null || $raw === '') && MarksFormula::isAssignmentComponent($code, $c['label']) && isset($asnPull[$reg])) {
                    $compVals[$code] = $asnPull[$reg];
                }
            }

            try {
                $data = MarksFormula::validateAndNormalizeValues($components, $compVals);
                $total = MarksFormula::computeTotal($expression, $data, $components);
                if ($totalMax > 0 && $total > $totalMax + 0.05) {
                    // Soft cap display; still store computed value but warn if wildly over.
                    // Do not reject — scaling formulas may produce values within total_max by design.
                }
                $letter = MarksFormula::gradeLetter($total, $totalMax);
            } catch (Throwable $e) {
                $errors[] = $reg . ': ' . $e->getMessage();
                continue;
            }

            $stu = $allowedRegs[$reg];
            $stuId = isset($stu['user_id']) ? (int)$stu['user_id'] : null;
            if (!$stuId) {
                $stuRow = Database::fetch(
                    'SELECT user_id FROM students_roster WHERE class_id=? AND register_no=? AND institution_id=?',
                    [$classId, $reg, $instId]
                );
                $stuId = $stuRow['user_id'] ?? null;
            }

            $attCode = null;
            $asnCode = null;
            foreach ($components as $c) {
                if ($attCode === null && MarksFormula::isAttendanceComponent($c['code'], $c['label'])) {
                    $attCode = $c['code'];
                }
                if ($asnCode === null && MarksFormula::isAssignmentComponent($c['code'], $c['label'])) {
                    $asnCode = $c['code'];
                }
            }

            $meta = json_encode([
                'academic_year' => $academicYear,
                'formula_name' => $formula['name'] ?? null,
                'formula_expression' => $expression,
                'total_max' => $totalMax,
                'components' => $components,
            ], JSON_UNESCAPED_UNICODE);

            Database::query(
                'INSERT INTO internal_marks
                  (institution_id, professor_id, subject_id, class_id, academic_year, formula_id, student_id, register_no, student_name,
                   marks_data, computed_total, grade_letter, attendance_pct, assignment_total, meta)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   marks_data=VALUES(marks_data),
                   computed_total=VALUES(computed_total),
                   grade_letter=VALUES(grade_letter),
                   formula_id=VALUES(formula_id),
                   student_name=VALUES(student_name),
                   student_id=VALUES(student_id),
                   professor_id=VALUES(professor_id),
                   attendance_pct=VALUES(attendance_pct),
                   assignment_total=VALUES(assignment_total),
                   meta=VALUES(meta),
                   academic_year=VALUES(academic_year)',
                [
                    $instId,
                    (int)$user['id'],
                    $subjectId,
                    $classId,
                    $academicYear,
                    $formulaId,
                    $stuId,
                    $reg,
                    $names[$reg] ?? ($stu['full_name'] ?? $reg),
                    json_encode($data, JSON_UNESCAPED_UNICODE),
                    $total,
                    $letter,
                    $attCode !== null ? ($data[$attCode] ?? null) : ($attPctMap[$reg] ?? null),
                    $asnCode !== null ? ($data[$asnCode] ?? null) : null,
                    $meta,
                ]
            );
            $saved++;
        }

        if ($errors) {
            flash('error', 'Some rows failed: ' . implode(' | ', array_slice($errors, 0, 5))
                . (count($errors) > 5 ? ' …' : ''));
        }
        if ($saved > 0) {
            flash('success', $saved === 1 ? 'Marks saved for 1 student.' : "Marks saved for $saved students.");
        } elseif (!$errors) {
            flash('error', 'No marks to save.');
        }
        redirect('/professor/marks.php?class_id=' . $classId . '&subject_id=' . $subjectId);
    }
}

if ($classId > 0 && $subjectId > 0) {
    // Keep enrollments in sync so Student → My Marks can resolve subject-wise access.
    enroll_class_students_in_subject($instId, $classId, $subjectId);
}
$roster = ($classId && $subjectId) ? $loadStudents($classId, $subjectId) : [];
[$subjectRow, $formula, $components] = $resolveContext($classId, $subjectId);
$totalMax = (float)($formula['total_max'] ?? 25);
$attPctMap = ($classId && $subjectId) ? MarksFormula::attendancePercentages($classId, $subjectId) : [];
$asnMax = 5.0;
$asnCode = null;
foreach ($components as $c) {
    if (MarksFormula::isAssignmentComponent($c['code'], $c['label'])) {
        $asnCode = $c['code'];
        $asnMax = (float)$c['max'];
        break;
    }
}
$asnPull = ($classId && $subjectId && $asnCode)
    ? MarksFormula::assignmentMarksForClassSubject($instId, $classId, $subjectId, $asnMax)
    : [];

$classLabel = '';
foreach ($classes as $c) {
    if ((int)$c['id'] === $classId) {
        $classLabel = class_batch_label($c);
        break;
    }
}

if (get('download') === 'mark_statement' && $classId && $subjectId) {
    if (!professor_can_manage_subject($user, $subjectId, $classId)) {
        flash('error', 'Access denied.');
        redirect('/professor/marks.php');
    }
    require_once dirname(__DIR__) . '/includes/SimplePdf.php';
    $inst = Database::fetch('SELECT name, affiliation_university, academic_year, current_semester FROM institutions WHERE id=?', [$instId]);
    $subj = Database::fetch('SELECT name, code, department_id FROM subjects WHERE id=? AND institution_id=?', [$subjectId, $instId]);
    $dept = $subj ? Database::fetch('SELECT name FROM departments WHERE id=?', [(int)($subj['department_id'] ?? 0)]) : null;
    $pdf = new SimplePdf();
    $pdf->setFont(14, true);
    $pdf->writeLine((string)($inst['name'] ?? 'Institution'));
    $pdf->setFont(11, false);
    $pdf->writeLine('Internal Mark Statement');
    $pdf->writeLine('Department: ' . (string)($dept['name'] ?? '—'));
    $pdf->writeLine('Class: ' . ($classLabel !== '' ? $classLabel : '—') . ' · Subject: ' . (string)($subj['code'] ?? '') . ' ' . (string)($subj['name'] ?? ''));
    $pdf->writeLine('Academic year: ' . ($academicYear !== '' ? $academicYear : (string)($inst['academic_year'] ?? '—')) . ' · Semester: ' . (string)($inst['current_semester'] ?? '—'));
    $pdf->writeLine('Professor: ' . (string)($user['full_name'] ?? ''));
    $pdf->writeLine('Formula: ' . (string)($formula['expression'] ?? ''));
    $pdf->hRule();
    $headers = ['Reg No', 'Student'];
    foreach ($components as $c) {
        $headers[] = (string)$c['label'];
    }
    $headers[] = 'Final';
    $headers[] = 'Grade';
    $weights = array_fill(0, count($headers), 1.0);
    $weights[0] = 1.2;
    $weights[1] = 1.8;
    $tableRows = [];
    $rosterPdf = $loadStudents($classId, $subjectId);
    $existingPdf = [];
    $params = [$instId, $classId, $subjectId];
    $sql = 'SELECT * FROM internal_marks WHERE institution_id=? AND class_id=? AND subject_id=?';
    if ($academicYear !== '') {
        $sql .= ' AND (academic_year = ? OR academic_year = "")';
        $params[] = $academicYear;
    }
    foreach (Database::fetchAll($sql, $params) as $m) {
        $existingPdf[(string)$m['register_no']] = $m;
    }
    foreach ($rosterPdf as $st) {
        $reg = (string)$st['register_no'];
        $ex = $existingPdf[$reg] ?? null;
        $md = $ex ? (json_decode((string)($ex['marks_data'] ?? '{}'), true) ?: []) : [];
        $row = [$reg, (string)$st['full_name']];
        foreach ($components as $c) {
            $v = '';
            foreach ($md as $k => $vv) {
                if (strcasecmp((string)$k, $c['code']) === 0) {
                    $v = (string)$vv;
                    break;
                }
            }
            $row[] = $v !== '' ? $v : '—';
        }
        $row[] = $ex && $ex['computed_total'] !== null ? (string)$ex['computed_total'] : '—';
        $row[] = (string)($ex['grade_letter'] ?? '—');
        $tableRows[] = $row;
    }
    $pdf->table($headers, $tableRows, $weights, 8.0);
    $pdf->space(10);
    $pdf->setFont(8, false);
    $pdf->writeLine('Generated ' . date('Y-m-d H:i') . ' · ProProfessor AI');
    $bytes = $pdf->output();
    $safe = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', ($subj['code'] ?? 'marks') . '_' . $classLabel) ?: 'mark_statement';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . trim($safe, '._-') . '_statement.pdf"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

$existing = [];
if ($classId && $subjectId) {
    $params = [$instId, $classId, $subjectId];
    $sql = 'SELECT * FROM internal_marks WHERE institution_id=? AND class_id=? AND subject_id=?';
    if ($academicYear !== '') {
        $sql .= ' AND (academic_year = ? OR academic_year = "")';
        $params[] = $academicYear;
    }
    foreach (Database::fetchAll($sql, $params) as $m) {
        $existing[(string)$m['register_no']] = $m;
    }
}

$whatIfResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'what_if') {
    // Preview only — never writes to DB. CSRF already verified in the POST gate above.
    if ($classId && $subjectId && professor_can_manage_subject($user, $subjectId, $classId) && $formula && $components) {
        $reg = (string)post('whatif_reg');
        $ex = $existing[$reg] ?? null;
        $base = $ex ? (json_decode((string)($ex['marks_data'] ?? '{}'), true) ?: []) : [];
        foreach ($components as $c) {
            $has = false;
            foreach ($base as $k => $_) {
                if (strcasecmp((string)$k, $c['code']) === 0 && $base[$k] !== '' && $base[$k] !== null) {
                    $has = true;
                    break;
                }
            }
            if (!$has && MarksFormula::isAttendanceComponent($c['code'], $c['label'])) {
                $base[$c['code']] = MarksFormula::attendanceMarkFromPercent((float)($attPctMap[$reg] ?? 0), (float)$c['max']);
            }
            if (!$has && MarksFormula::isAssignmentComponent($c['code'], $c['label']) && isset($asnPull[$reg])) {
                $base[$c['code']] = $asnPull[$reg];
            }
        }
        $hypCode = (string)post('whatif_code');
        $hypVal = post('whatif_value');
        $projected = $base;
        if ($hypCode !== '' && $hypVal !== null && $hypVal !== '' && is_numeric($hypVal)) {
            $projected[$hypCode] = (float)$hypVal;
        }
        try {
            $baseN = MarksFormula::validateAndNormalizeValues($components, $base);
            $projN = MarksFormula::validateAndNormalizeValues($components, $projected);
            $expr = (string)($formula['expression'] ?? '');
            $curTotal = MarksFormula::computeTotal($expr, $baseN, $components);
            $projTotal = MarksFormula::computeTotal($expr, $projN, $components);
            $whatIfResult = [
                'reg' => $reg,
                'current' => $curTotal,
                'projected' => $projTotal,
                'diff' => round($projTotal - $curTotal, 2),
                'code' => $hypCode,
                'value' => (float)$hypVal,
            ];
        } catch (Throwable $e) {
            flash('error', 'What-If: ' . $e->getMessage());
            redirect('/professor/marks.php?class_id=' . $classId . '&subject_id=' . $subjectId);
        }
    }
}

// Distribution across professor-authorized classes for the same subject.
$distribution = [];
if ($subjectId > 0) {
    foreach ($classes as $c) {
        $cid = (int)$c['id'];
        if (!professor_can_manage_subject($user, $subjectId, $cid)) {
            continue;
        }
        $distribution[] = [
            'class' => class_batch_label($c),
            'class_id' => $cid,
            'stats' => MarksFormula::distributionForClassSubject($instId, $cid, $subjectId, $academicYear, $totalMax),
        ];
    }
}

$hasSavedMarks = $existing !== [];
$editMode = !$hasSavedMarks || (string)get('edit') === '1';
$marksListUrl = '/professor/marks.php?class_id=' . $classId . '&subject_id=' . $subjectId;
$marksEditUrl = $marksListUrl . '&edit=1';
$statementUrl = $marksListUrl . '&download=mark_statement';

render_header('Internal Marks', 'marks', [
    'subtitle' => 'Class-wise · Admin formula · auto total · subject isolation',
]);
?>
<div class="panel">
  <form method="get" class="form-grid" style="grid-template-columns:1fr 1fr auto;gap:.8rem;align-items:end">
    <?php if ($academicYear !== ''): ?>
      <div class="form-row" style="grid-column:1/-1">
        <label>Academic Year</label>
        <input type="text" value="<?= e($academicYear) ?>" readonly>
      </div>
    <?php endif; ?>
    <div class="form-row"><label>Class</label>
      <select name="class_id" onchange="this.form.submit()">
        <option value="">Select class (UG/PG · year)</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>><?= e(class_batch_label($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>Subject</label>
      <select name="subject_id" onchange="this.form.submit()">
        <option value="">Select subject</option>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['code'] . ' · ' . $s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-ghost" type="submit">Load Students</button>
  </form>
  <?php if (!$classes): ?>
    <div class="empty" style="margin-top:1rem">No assigned classes yet. Your HOD must assign you to a course and class under HOD → Courses.</div>
  <?php endif; ?>
</div>

<?php if ($classId && $subjectId && $formula): ?>
<div class="panel" style="margin-top:1rem">
  <strong>Applied Formula</strong>
  <div style="margin-top:.35rem"><?= e(MarksFormula::appliedTitle($formula)) ?></div>
  <?php if (!empty($formula['plain_english'])): ?>
    <div style="margin-top:.35rem;color:var(--muted);font-size:.9rem"><?= e((string)$formula['plain_english']) ?></div>
  <?php endif; ?>
  <pre style="margin:.6rem 0 0;white-space:pre-wrap;font-size:.85rem"><?= e((string)($formula['expression'] ?? '')) ?></pre>
  <div style="margin-top:.5rem;font-size:.85rem;color:var(--muted)">Final internal max: <?= e(rtrim(rtrim(number_format($totalMax, 2, '.', ''), '0'), '.')) ?> · Formula is set by College Admin (read-only). Attendance &amp; Assignment auto-fill from existing modules when available.</div>
</div>
<?php endif; ?>

<?php if (!$classId): ?>
  <div class="panel empty" style="margin-top:1rem">Select a class (UG/PG and year) to load students.</div>
<?php elseif (!$subjectId): ?>
  <div class="panel empty" style="margin-top:1rem">Select a course assigned to you for this class. Ask your HOD to assign courses under HOD → Courses.</div>
<?php elseif (!$components): ?>
  <div class="panel empty" style="margin-top:1rem">The applied formula has no components. Ask College Admin to configure Marks Formulas.</div>
<?php elseif (!$roster): ?>
  <div class="panel empty" style="margin-top:1rem">No students found for <?= e($classLabel !== '' ? $classLabel : 'this class') ?>. Import them on Attendance, or ask College Admin to enroll students.</div>
<?php else: ?>
<form method="post" class="panel" style="margin-top:1rem" id="marks-form"
      data-expression="<?= e((string)($formula['expression'] ?? '')) ?>"
      data-total-max="<?= e((string)$totalMax) ?>"
      data-components="<?= e(json_encode($components, JSON_UNESCAPED_UNICODE)) ?>"
      data-edit-mode="<?= $editMode ? '1' : '0' ?>"
      data-cia-drop-abs="<?= e((string)MarksFormula::CIA_DROP_ABSOLUTE) ?>"
      data-cia-drop-rel="<?= e((string)MarksFormula::CIA_DROP_RELATIVE) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_marks">
  <input type="hidden" name="class_id" value="<?= $classId ?>">
  <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
  <?php if ($hasSavedMarks && !$editMode): ?>
    <div class="alert alert-info" style="margin-bottom:1rem">Marks are saved for this class/subject. Click <strong>Edit Marks</strong> to change values, then Save.</div>
  <?php elseif ($hasSavedMarks && $editMode): ?>
    <div class="alert alert-info" style="margin-bottom:1rem">Editing saved marks. Update the fields and click <strong>Save Marks</strong> to update (no duplicates).</div>
  <?php endif; ?>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>Reg No</th>
        <th>Student</th>
        <?php foreach ($components as $c): ?>
          <th><?= e($c['label']) ?><?php if ((float)$c['max'] > 0): ?> <span style="opacity:.65;font-weight:400">/ <?= e(rtrim(rtrim(number_format((float)$c['max'], 2, '.', ''), '0'), '.')) ?></span><?php endif; ?></th>
        <?php endforeach; ?>
        <th>Final Internal</th>
        <th>Grade</th>
        <th>Alerts</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($roster as $st):
      $reg = (string)$st['register_no'];
      $ex = $existing[$reg] ?? null;
      $md = $ex ? (json_decode((string)($ex['marks_data'] ?? '{}'), true) ?: []) : [];
      foreach ($components as $c) {
          $has = false;
          foreach ($md as $k => $_) {
              if (strcasecmp((string)$k, $c['code']) === 0 && $md[$k] !== '' && $md[$k] !== null) {
                  $has = true;
                  break;
              }
          }
          if (!$has && MarksFormula::isAttendanceComponent($c['code'], $c['label'])) {
              $md[$c['code']] = MarksFormula::attendanceMarkFromPercent((float)($attPctMap[$reg] ?? 0), (float)$c['max']);
          }
          if (!$has && MarksFormula::isAssignmentComponent($c['code'], $c['label']) && isset($asnPull[$reg])) {
              $md[$c['code']] = $asnPull[$reg];
          }
      }
      $cia1 = null;
      $cia2 = null;
      foreach ($md as $k => $v) {
          if (stripos((string)$k, 'cia1') !== false) {
              $cia1 = is_numeric($v) ? (float)$v : null;
          }
          if (stripos((string)$k, 'cia2') !== false) {
              $cia2 = is_numeric($v) ? (float)$v : null;
          }
      }
      $drop = ($cia1 !== null && $cia2 !== null) ? MarksFormula::ciaDropWarning($cia1, $cia2) : ['flag' => false, 'message' => ''];
      $alerts = [];
      if ($drop['flag']) {
          $alerts[] = $drop['message'];
      }
    ?>
      <tr data-reg="<?= e($reg) ?>">
        <td><?= e($reg) ?></td>
        <td>
          <?= e((string)$st['full_name']) ?>
          <input type="hidden" name="name[<?= e($reg) ?>]" value="<?= e((string)$st['full_name']) ?>">
        </td>
        <?php foreach ($components as $c):
          $code = $c['code'];
          $val = '';
          $hasVal = false;
          foreach ($md as $k => $v) {
              if (strcasecmp((string)$k, $code) === 0) {
                  $hasVal = $v !== null && $v !== '';
                  $val = $hasVal ? (string)$v : '';
                  break;
              }
          }
          $isAtt = MarksFormula::isAttendanceComponent($code, $c['label']);
          $isAsn = MarksFormula::isAssignmentComponent($code, $c['label']);
          $asnMissing = $isAsn && !$hasVal && !isset($asnPull[$reg]);
        ?>
          <td>
            <input
              class="mark-input"
              name="marks[<?= e($reg) ?>][<?= e($code) ?>]"
              value="<?= e($val) ?>"
              inputmode="decimal"
              data-code="<?= e($code) ?>"
              data-max="<?= e((string)$c['max']) ?>"
              data-cia="<?= (stripos($code, 'cia') !== false) ? '1' : '0' ?>"
              <?= $isAtt ? 'title="Prefills from Attendance % × component max; you may adjust."' : '' ?>
              <?= $isAsn ? 'title="Prefills from finalized Assignment marks when available."' : '' ?>
              <?= $editMode ? '' : 'readonly' ?>
              style="min-width:70px<?= $editMode ? '' : ';opacity:.85;cursor:default' ?>">
            <?php if ($asnMissing): ?>
              <div class="muted" style="font-size:.72rem">Not available</div>
            <?php elseif ($isAsn && isset($asnPull[$reg])): ?>
              <div class="muted" style="font-size:.72rem">From assignments</div>
            <?php elseif ($isAtt && isset($attPctMap[$reg])): ?>
              <div class="muted" style="font-size:.72rem">From attendance (<?= e((string)$attPctMap[$reg]) ?>%)</div>
            <?php elseif ($isAtt): ?>
              <div class="muted" style="font-size:.72rem">No attendance data</div>
            <?php endif; ?>
            <div class="mark-err muted" style="font-size:.72rem;color:var(--danger);display:none"></div>
          </td>
        <?php endforeach; ?>
        <td class="final-cell"><?php
          if ($ex && $ex['computed_total'] !== null && $ex['computed_total'] !== '') {
              echo e(rtrim(rtrim(number_format((float)$ex['computed_total'], 2, '.', ''), '0'), '.'));
              if ($totalMax > 0) {
                  echo ' <span style="opacity:.65">/ ' . e(rtrim(rtrim(number_format($totalMax, 2, '.', ''), '0'), '.')) . '</span>';
              }
          } else {
              echo '<span class="muted">AUTO</span>';
          }
        ?></td>
        <td class="grade-cell"><?= e((string)($ex['grade_letter'] ?? '—')) ?></td>
        <td class="alerts-cell" style="font-size:.78rem;max-width:11rem">
          <?php if ($alerts): ?>
            <?php foreach ($alerts as $al): ?><div class="badge badge-warn"><?= e($al) ?></div><?php endforeach; ?>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="filters" style="margin-top:1rem;gap:.6rem;display:flex;flex-wrap:wrap;align-items:center">
    <?php if ($editMode): ?>
      <button class="btn btn-primary" type="submit"><?= $hasSavedMarks ? 'Update Marks' : 'Save Marks' ?></button>
      <?php if ($hasSavedMarks): ?>
        <a class="btn btn-ghost" href="<?= e(base_url($marksListUrl)) ?>">Cancel</a>
      <?php endif; ?>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= e(base_url($marksEditUrl)) ?>">Edit Marks</a>
      <span style="font-size:.85rem;color:var(--muted)">View only — click Edit Marks to change values.</span>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(base_url($statementUrl)) ?>">Generate Mark Statement</a>
  </div>
</form>

<div class="panel" style="margin-top:1rem" id="whatif-panel">
  <h3 style="margin-top:0">What-If Simulator</h3>
  <p class="muted" style="margin-top:0">Preview only — does not save or change student marks. Uses the same Admin formula.</p>
  <form method="post" class="form-grid" style="grid-template-columns:1.4fr 1fr 1fr auto;gap:.6rem;align-items:end">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="what_if">
    <input type="hidden" name="class_id" value="<?= $classId ?>">
    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
    <div class="form-row" style="margin:0">
      <label>Student</label>
      <select name="whatif_reg" required>
        <?php foreach ($roster as $st): ?>
          <option value="<?= e((string)$st['register_no']) ?>" <?= ($whatIfResult['reg'] ?? '') === (string)$st['register_no'] ? 'selected' : '' ?>>
            <?= e((string)$st['register_no'] . ' · ' . $st['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row" style="margin:0">
      <label>Component</label>
      <select name="whatif_code" required>
        <?php foreach ($components as $c): ?>
          <option value="<?= e($c['code']) ?>" <?= ($whatIfResult['code'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= e($c['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row" style="margin:0">
      <label>Hypothetical value</label>
      <input name="whatif_value" type="number" step="0.01" required value="<?= e(isset($whatIfResult['value']) ? (string)$whatIfResult['value'] : '') ?>">
    </div>
    <button class="btn btn-accent" type="submit">Simulate</button>
  </form>
  <?php if ($whatIfResult): ?>
    <div class="chip-row" style="margin-top:1rem">
      <span class="chip">Current Final: <?= e((string)$whatIfResult['current']) ?></span>
      <span class="chip">Projected Final: <?= e((string)$whatIfResult['projected']) ?></span>
      <span class="chip">Difference: <?= ($whatIfResult['diff'] >= 0 ? '+' : '') . e((string)$whatIfResult['diff']) ?></span>
    </div>
  <?php endif; ?>
</div>

<?php if ($distribution): ?>
<div class="panel" style="margin-top:1rem">
  <h3 style="margin-top:0">Distribution / Moderation</h3>
  <p class="muted" style="margin-top:0">Aggregate stats for sections you are authorized to teach for this subject. Student-level detail is not shown.</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Section</th><th>Students</th><th>Average</th><th>Highest</th><th>Lowest</th><th>Median</th><th>≥50%</th><th>&lt;50%</th></tr></thead>
    <tbody>
    <?php foreach ($distribution as $d): $s = $d['stats']; ?>
      <tr<?= (int)$d['class_id'] === $classId ? ' style="outline:1px solid var(--brand)"' : '' ?>>
        <td><?= e($d['class']) ?></td>
        <td><?= (int)$s['students'] ?></td>
        <td><?= $s['average'] === null ? '—' : e((string)$s['average']) ?></td>
        <td><?= $s['highest'] === null ? '—' : e((string)$s['highest']) ?></td>
        <td><?= $s['lowest'] === null ? '—' : e((string)$s['lowest']) ?></td>
        <td><?= $s['median'] === null ? '—' : e((string)$s['median']) ?></td>
        <td><?= (int)$s['pass'] ?></td>
        <td><?= (int)$s['fail'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<script>
(function () {
  var form = document.getElementById('marks-form');
  if (!form) return;
  var editMode = form.getAttribute('data-edit-mode') === '1';
  var expression = form.getAttribute('data-expression') || '';
  var totalMax = parseFloat(form.getAttribute('data-total-max') || '25') || 25;
  var dropAbs = parseFloat(form.getAttribute('data-cia-drop-abs') || '15') || 15;
  var dropRel = parseFloat(form.getAttribute('data-cia-drop-rel') || '0.4') || 0.4;
  var components = [];
  try { components = JSON.parse(form.getAttribute('data-components') || '[]'); } catch (e) { components = []; }

  function safeEval(expr) {
    if (!/^[0-9+\-.*\/() \t]+$/.test(expr)) return null;
    try {
      return Function('"use strict"; return (' + expr + ');')();
    } catch (e) {
      return null;
    }
  }

  function gradeLetter(total) {
    var pct = totalMax > 0 ? (total / totalMax) * 100 : 0;
    if (pct >= 90) return 'O';
    if (pct >= 80) return 'A';
    if (pct >= 70) return 'B';
    if (pct >= 60) return 'C';
    if (pct >= 50) return 'D';
    return 'E';
  }

  function fmt(n) {
    if (n == null || isNaN(n)) return 'AUTO';
    var s = (Math.round(n * 100) / 100).toFixed(2).replace(/\.?0+$/, '');
    return s + (totalMax > 0 ? ' / ' + String(totalMax).replace(/\.0$/, '') : '');
  }

  function recomputeRow(tr) {
    var values = {};
    var ok = true;
    var ciaVals = {};
    tr.querySelectorAll('.mark-input').forEach(function (inp) {
      var code = inp.getAttribute('data-code');
      var max = parseFloat(inp.getAttribute('data-max') || '0');
      var raw = (inp.value || '').trim();
      var err = inp.parentNode.querySelector('.mark-err');
      if (err) { err.style.display = 'none'; err.textContent = ''; }
      if (raw === '' || isNaN(Number(raw))) { ok = false; return; }
      var num = Number(raw);
      if (num < 0) {
        ok = false;
        if (err) { err.style.display = ''; err.textContent = 'Cannot be negative'; }
        return;
      }
      if (max > 0 && num > max) {
        ok = false;
        if (err) { err.style.display = ''; err.textContent = 'Cannot exceed ' + max; }
        return;
      }
      values[code] = num;
      if ((inp.getAttribute('data-cia') === '1') || /cia/i.test(code)) {
        ciaVals[code.toLowerCase()] = num;
      }
    });
    var finalCell = tr.querySelector('.final-cell');
    var gradeCell = tr.querySelector('.grade-cell');
    var alertsCell = tr.querySelector('.alerts-cell');
    if (alertsCell) {
      var c1 = null, c2 = null;
      Object.keys(ciaVals).forEach(function (k) {
        if (k.indexOf('cia1') !== -1) c1 = ciaVals[k];
        if (k.indexOf('cia2') !== -1) c2 = ciaVals[k];
      });
      if (c1 != null && c2 != null && c1 > 0) {
        var drop = c1 - c2;
        if (drop >= dropAbs || drop >= c1 * dropRel) {
          alertsCell.innerHTML = '<div class="badge badge-warn">Significant drop from previous CIA</div>';
        } else if (!alertsCell.querySelector('.badge-warn')) {
          alertsCell.innerHTML = '<span class="muted">—</span>';
        }
      }
    }
    if (!ok || !expression) {
      if (finalCell) finalCell.innerHTML = '<span class="muted">AUTO</span>';
      if (gradeCell) gradeCell.textContent = '—';
      return;
    }
    var expr = expression;
    var codes = components.map(function (c) { return c.code; }).sort(function (a, b) { return b.length - a.length; });
    codes.forEach(function (code) {
      var re = new RegExp('\\b' + code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'gi');
      expr = expr.replace(re, String(values[code]));
    });
    var total = safeEval(expr);
    if (finalCell) finalCell.textContent = total == null ? 'AUTO' : fmt(total);
    if (gradeCell) gradeCell.textContent = total == null ? '—' : gradeLetter(total);
  }

  form.addEventListener('submit', function (ev) {
    var bad = false;
    form.querySelectorAll('.mark-input').forEach(function (inp) {
      var max = parseFloat(inp.getAttribute('data-max') || '0');
      var raw = (inp.value || '').trim();
      var err = inp.parentNode.querySelector('.mark-err');
      if (raw === '' || isNaN(Number(raw))) return;
      var num = Number(raw);
      if (num < 0 || (max > 0 && num > max)) {
        bad = true;
        if (err) {
          err.style.display = '';
          err.textContent = num < 0 ? 'Cannot be negative' : ('Cannot exceed ' + max);
        }
      }
    });
    if (bad) {
      ev.preventDefault();
      alert('Fix invalid scores before saving. Values cannot be negative or exceed component maximums.');
    }
  });

  form.querySelectorAll('tr[data-reg]').forEach(function (tr) {
    tr.querySelectorAll('.mark-input').forEach(function (inp) {
      if (editMode) {
        inp.addEventListener('input', function () { recomputeRow(tr); });
      }
    });
    recomputeRow(tr);
  });
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
