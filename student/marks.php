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

render_header('Internal Marks', 'marks', [
    'subtitle' => $classLabel !== '' ? $classLabel : 'Your subject-wise marks',
]);
?>
<div class="panel">
  <?php if ($classId < 1): ?>
    <div class="empty">Your account is not assigned to a class. Ask College Admin to put you in the correct year and section.</div>
  <?php elseif (!$marks): ?>
    <div class="empty">Marks for <?= e($classLabel !== '' ? $classLabel : 'your class') ?> are not published yet<?= $academicYear !== '' ? ' for ' . e($academicYear) : '' ?>.</div>
  <?php else: ?>
    <div class="form-grid" style="gap:1rem">
    <?php foreach ($marks as $m):
      $md = json_decode((string)($m['marks_data'] ?? '{}'), true) ?: [];
      $meta = json_decode((string)($m['meta'] ?? '{}'), true) ?: [];
      $compDefs = MarksFormula::normalizeComponents($meta['components'] ?? ($m['formula_components'] ?? []));
      $totalMax = (float)($meta['total_max'] ?? $m['formula_total_max'] ?? 25);
      $total = $m['computed_total'];
      $labels = [];
      foreach ($compDefs as $c) {
          $labels[strtolower($c['code'])] = $c['label'];
      }
    ?>
      <article class="panel" style="margin:0;padding:1rem 1.1rem">
        <h3 style="margin:0 0 .75rem;font-size:1.05rem">
          <?= e((string)$m['subject_name']) ?>
          <?php if (!empty($m['subject_code'])): ?>
            <span style="opacity:.65;font-weight:500;font-size:.9rem"> · <?= e((string)$m['subject_code']) ?></span>
          <?php endif; ?>
        </h3>
        <div class="table-wrap"><table>
          <thead><tr><th>Component</th><th style="text-align:right">Marks</th></tr></thead>
          <tbody>
          <?php if ($compDefs): ?>
            <?php foreach ($compDefs as $c):
              $val = null;
              foreach ($md as $k => $v) {
                  if (strcasecmp((string)$k, $c['code']) === 0) {
                      $val = $v;
                      break;
                  }
              }
              $max = (float)$c['max'];
            ?>
              <tr>
                <td><?= e($c['label']) ?></td>
                <td style="text-align:right">
                  <?= $val === null || $val === '' ? '—' : e((string)$val) ?>
                  <?php if ($max > 0 && $val !== null && $val !== ''): ?>
                    <span style="opacity:.6"> / <?= e(rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.')) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <?php foreach ($md as $k => $v): ?>
              <tr>
                <td><?= e($labels[strtolower((string)$k)] ?? (string)$k) ?></td>
                <td style="text-align:right"><?= e((string)$v) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
            <tr>
              <td><strong>Internal</strong></td>
              <td style="text-align:right"><strong>
                <?= $total === null || $total === '' ? '—' : e(rtrim(rtrim(number_format((float)$total, 2, '.', ''), '0'), '.')) ?>
                <?php if ($total !== null && $total !== '' && $totalMax > 0): ?>
                  / <?= e(rtrim(rtrim(number_format($totalMax, 2, '.', ''), '0'), '.')) ?>
                <?php endif; ?>
              </strong></td>
            </tr>
            <?php if (!empty($m['grade_letter'])): ?>
            <tr>
              <td>Grade</td>
              <td style="text-align:right"><strong><?= e((string)$m['grade_letter']) ?></strong></td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table></div>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
