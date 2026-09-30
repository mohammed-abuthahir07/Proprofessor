<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

use App\Models\MarksFormula;

Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
$instId = (int)$user['institution_id'];
$classId = student_class_id($user);
$classLabel = $classId ? class_label_by_id($classId) : '';
$reg = trim((string)($user['register_no'] ?? ''));
$academicYear = institution_academic_year($instId);
MarksFormula::ensureInternalMarksSchema();

$enrolledSubjects = courses_for_student($user);
$allowedSubjectIds = array_map(static fn($s) => (int)$s['id'], $enrolledSubjects);

$marks = [];
if ($classId > 0) {
    $params = [$instId, $classId, (int)$user['id'], $reg];
    $sql = 'SELECT m.*, s.name AS subject_name, s.code AS subject_code,
                   f.name AS formula_name, f.total_max AS formula_total_max, f.components AS formula_components
            FROM internal_marks m
            JOIN subjects s ON s.id = m.subject_id AND s.institution_id = m.institution_id
            LEFT JOIN marks_formulas f ON f.id = m.formula_id AND f.institution_id = m.institution_id
            WHERE m.institution_id = ?
              AND m.class_id = ?
              AND (m.student_id = ? OR (m.register_no <> "" AND m.register_no = ?))';
    if ($academicYear !== '') {
        $sql .= ' AND (m.academic_year = ? OR m.academic_year = "")';
        $params[] = $academicYear;
    }
    if ($allowedSubjectIds) {
        $placeholders = implode(',', array_fill(0, count($allowedSubjectIds), '?'));
        $sql .= " AND m.subject_id IN ($placeholders)";
        foreach ($allowedSubjectIds as $sid) {
            $params[] = $sid;
        }
    } else {
        // No enrollments → show nothing (privacy / isolation).
        $sql .= ' AND 1=0';
    }
    $sql .= ' ORDER BY s.name';
    $marks = Database::fetchAll($sql, $params);
}

$fmtNum = static function ($n): string {
    return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
};
$rows = [];
$columns = [];
$scoreSum = 0.0;
$maxSum = 0.0;
$hasScore = false;
$pcts = [];
$formulaNames = [];
$showGrade = false;
foreach ($marks as $m) {
    $md = json_decode((string)($m['marks_data'] ?? '{}'), true) ?: [];
    $meta = json_decode((string)($m['meta'] ?? '{}'), true) ?: [];
    $compDefs = MarksFormula::normalizeComponents($meta['components'] ?? ($m['formula_components'] ?? []));
    $totalMax = (float)($meta['total_max'] ?? $m['formula_total_max'] ?? 0);
    $total = $m['computed_total'];
    $cells = [];
    if ($compDefs) {
        foreach ($compDefs as $c) {
            $code = strtolower((string)$c['code']);
            if (!isset($columns[$code])) {
                $columns[$code] = (string)$c['label'];
            }
            $val = null;
            foreach ($md as $k => $v) {
                if (strcasecmp((string)$k, (string)$c['code']) === 0) {
                    $val = $v;
                    break;
                }
            }
            $cells[$code] = [
                'value' => $val,
                'max' => (float)$c['max'],
            ];
        }
    } else {
        foreach ($md as $k => $v) {
            $code = strtolower((string)$k);
            if (!isset($columns[$code])) {
                $columns[$code] = (string)$k;
            }
            $cells[$code] = ['value' => $v, 'max' => 0.0];
        }
    }
    $pct = null;
    if ($total !== null && $total !== '' && $totalMax > 0) {
        $pct = round(((float)$total * 100) / $totalMax, 1);
        $pcts[] = $pct;
        $scoreSum += (float)$total;
        $maxSum += $totalMax;
        $hasScore = true;
    }
    $formula = trim((string)($meta['formula_name'] ?? $m['formula_name'] ?? ''));
    if ($formula !== '') {
        $formulaNames[$formula] = true;
    }
    if (!empty($m['grade_letter'])) {
        $showGrade = true;
    }
    $rows[] = [
        'name' => (string)$m['subject_name'],
        'code' => (string)($m['subject_code'] ?? ''),
        'cells' => $cells,
        'total' => $total,
        'total_max' => $totalMax,
        'pct' => $pct,
        'grade' => (string)($m['grade_letter'] ?? ''),
    ];
}
$avgPct = $pcts !== [] ? round(array_sum($pcts) / count($pcts), 1) : null;
$formulaNote = count($formulaNames) === 1 ? (string)array_key_first($formulaNames) : '';

render_header('Internal Marks', 'marks', ['compactTitle' => true]);
?>
<div class="stu-marks">
  <section class="stu-marks-head">
    <h2><?= icon('chart', 'icon-inline') ?> My Marks</h2>
    <p>Internal marks<?= $classLabel !== '' ? ' · ' . e($classLabel) : '' ?><?= $academicYear !== '' ? ' · ' . e($academicYear) : '' ?></p>
  </section>
  <?php if ($classId < 1): ?>
    <div class="empty">Your account is not assigned to a class. Ask College Admin to put you in the correct year and section.</div>
  <?php elseif (!$marks): ?>
    <div class="empty">Marks for <?= e($classLabel !== '' ? $classLabel : 'your class') ?> are not published yet<?= $academicYear !== '' ? ' for ' . e($academicYear) : '' ?>.</div>
  <?php else: ?>
    <div class="stu-marks-kpis">
      <div class="stu-marks-kpi is-avg">
        <strong><?= $avgPct === null ? '—' : e($fmtNum($avgPct)) . '%' ?></strong>
        <span>Average score</span>
      </div>
      <div class="stu-marks-kpi is-scored">
        <strong><?= $hasScore ? e($fmtNum($scoreSum)) : '—' ?></strong>
        <span>Total marks scored</span>
      </div>
      <div class="stu-marks-kpi is-max">
        <strong><?= $maxSum > 0 ? e($fmtNum($maxSum)) : '—' ?></strong>
        <span>Max internal marks</span>
      </div>
    </div>
    <div class="panel stu-marks-table">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Subject</th>
              <?php foreach ($columns as $label): ?>
                <th><?= e($label) ?></th>
              <?php endforeach; ?>
              <th>Total</th>
              <th>%</th>
              <?php if ($showGrade): ?><th>Grade</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td>
                <strong><?= e($row['name']) ?></strong>
                <?php if ($row['code'] !== ''): ?><span class="stu-marks-code"><?= e($row['code']) ?></span><?php endif; ?>
              </td>
              <?php foreach ($columns as $code => $label):
                $cell = $row['cells'][$code] ?? null;
                $val = $cell['value'] ?? null;
                $max = (float)($cell['max'] ?? 0);
              ?>
                <td>
                  <?php if ($val === null || $val === ''): ?>
                    —
                  <?php else: ?>
                    <?= e($fmtNum($val)) ?><?php if ($max > 0): ?><span class="stu-marks-of">/<?= e($fmtNum($max)) ?></span><?php endif; ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td>
                <?php if ($row['total'] === null || $row['total'] === ''): ?>
                  —
                <?php else: ?>
                  <strong><?= e($fmtNum($row['total'])) ?></strong><?php if ($row['total_max'] > 0): ?><span class="stu-marks-of">/<?= e($fmtNum($row['total_max'])) ?></span><?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row['pct'] === null): ?>
                  —
                <?php else: ?>
                  <span class="stu-marks-pct"><?= e($fmtNum($row['pct'])) ?>%</span>
                <?php endif; ?>
              </td>
              <?php if ($showGrade): ?>
                <td><?= $row['grade'] !== '' ? e($row['grade']) : '—' ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($formulaNote !== ''): ?>
        <p class="stu-marks-note"><?= e($formulaNote) ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
