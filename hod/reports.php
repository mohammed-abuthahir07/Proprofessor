<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$instId = (int)($user['institution_id'] ?? 0);
$deptId = $isAdmin
    ? (int)($_GET['department_id'] ?? ($user['department_id'] ?? 0))
    : hod_department_id($user);

$inst = $instId > 0
    ? Database::fetch('SELECT * FROM institutions WHERE id = ?', [$instId])
    : null;
$dept = $deptId > 0
    ? Database::fetch('SELECT id, name, code FROM departments WHERE id = ? AND institution_id = ?', [$deptId, $instId])
    : null;

$plans = ($deptId > 0)
    ? Database::fetchAll(
        'SELECT subject_name, status, ai_score, bloom_data, plan_data, version, updated_at
         FROM course_plans
         WHERE institution_id = ? AND department_id = ?
         ORDER BY subject_name',
        [$instId, $deptId]
    )
    : [];

$perPage = 5;
$planTotal = count($plans);
$planTotalPages = max(1, (int)ceil($planTotal / $perPage));
$planPage = (int)($_GET['page'] ?? 1);
if ($planPage < 1) {
    $planPage = 1;
}
if ($planPage > $planTotalPages) {
    $planPage = $planTotalPages;
}
$planOffset = ($planPage - 1) * $perPage;
$plansPage = array_slice($plans, $planOffset, $perPage);

$reportsPageQuery = static function (int $page) use ($isAdmin, $deptId): string {
    $params = [];
    if ($isAdmin && $deptId > 0) {
        $params['department_id'] = $deptId;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    $query = http_build_query($params);
    return url('/hod/reports' . ($query !== '' ? '?' . $query : ''));
};
/**
 * @param array<string,mixed>|null $inst
 * @param array<string,mixed>|null $dept
 * @param list<array<string,mixed>> $plans
 * @param array<string,mixed> $user
 */
function hod_naac_download_pdf(?array $inst, ?array $dept, array $plans, array $user): void
{
    if (!$inst) {
        flash('error', 'Institution not found.');
        redirect('/hod/reports');
    }
    if (!$dept) {
        flash('error', 'Department not linked. Contact College Admin.');
        redirect('/hod/reports');
    }

    $college = trim((string)($inst['name'] ?? 'Institution'));
    $deptName = trim((string)($dept['name'] ?? 'Department'));
    $deptCode = trim((string)($dept['code'] ?? ''));
    $deptLabel = $deptCode !== '' ? ($deptCode . ' — ' . $deptName) : $deptName;

    $addrParts = array_filter([
        trim((string)($inst['address'] ?? '')),
        trim((string)($inst['city'] ?? '')),
        trim((string)($inst['state'] ?? '')),
        trim((string)($inst['pincode'] ?? '')),
    ], static fn($v) => $v !== '');
    $addressLine = implode(', ', $addrParts);
    $naacGrade = trim((string)($inst['naac_grade'] ?? ''));
    $affiliation = trim((string)($inst['affiliation_university'] ?? ''));
    $academicYear = trim((string)($inst['academic_year'] ?? ''));
    $semester = trim((string)($inst['current_semester'] ?? ''));
    $nba = trim((string)($inst['nba_status'] ?? ''));
    $hodName = trim((string)($user['full_name'] ?? 'HOD'));

    $ink = [20, 24, 40];
    $muted = [90, 98, 120];
    $accent = [76, 29, 149]; // deep purple brand
    $band = [30, 27, 75];

    $statusCounts = [];
    $scoreSum = 0.0;
    $scoreN = 0;
    $bloomSum = 0.0;
    $bloomN = 0;
    $approved = 0;
    $rows = [];
    foreach ($plans as $i => $p) {
        $st = strtolower((string)($p['status'] ?? 'draft'));
        $statusCounts[$st] = ($statusCounts[$st] ?? 0) + 1;
        if ($st === 'approved') {
            $approved++;
        }
        $score = $p['ai_score'];
        if ($score !== null && $score !== '') {
            $scoreSum += (float)$score;
            $scoreN++;
        }
        $b = json_decode((string)($p['bloom_data'] ?? '{}'), true) ?: [];
        $higher = (float)($b['K4'] ?? 0) + (float)($b['K5'] ?? 0) + (float)($b['K6'] ?? 0);
        $bloomSum += $higher;
        $bloomN++;
        $updated = (string)($p['updated_at'] ?? '');
        if ($updated !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $updated)) {
            $ts = strtotime($updated);
            $updated = $ts ? date('d M Y', $ts) : $updated;
        }
        $rows[] = [
            (string)($i + 1),
            (string)($p['subject_name'] ?? ''),
            ucwords(str_replace('_', ' ', $st)),
            ($score !== null && $score !== '') ? number_format((float)$score, 1) : '—',
            round($higher, 1) . '%',
            'v' . (int)($p['version'] ?? 1),
            $updated !== '' ? $updated : '—',
        ];
    }
    if (!$rows) {
        $rows[] = ['—', 'No course plans yet', '—', '—', '—', '—', '—'];
    }

    $avgScore = $scoreN > 0 ? round($scoreSum / $scoreN, 1) : null;
    $avgBloom = $bloomN > 0 ? round($bloomSum / $bloomN, 1) : null;
    $planTotal = count($plans);

    $pdf = new SimplePdf();

    // Brand letterhead
    $pdf->filledRect(0, 0, $pdf->pageWidth(), 82, $band);
    $pdf->filledRect(0, 82, $pdf->pageWidth(), 4, $accent);
    $pdf->setFont(12, true);
    $pdf->textAt(42, 22, 'ProProfessor AI', [255, 255, 255]);
    $pdf->setFont(9, false);
    $pdf->textAt(42, 38, 'NAAC / NBA Criterion Evidence Pack', [216, 180, 254]);
    $pdf->textAt(42, 52, 'Department accreditation snapshot · Generated ' . date('d M Y, h:i A'), [167, 139, 250]);
    $pdf->moveTo(102);

    $pdf->setFont(16, true);
    $pdf->writeCenteredWrapped($college, 0, 20, $ink);
    $pdf->setFont(10, true);
    $pdf->writeCentered($deptLabel, $accent, 14);
    $pdf->setFont(9, false);
    if ($addressLine !== '') {
        $pdf->writeCenteredWrapped($addressLine, 0, 12, $muted);
    }
    if ($affiliation !== '') {
        $pdf->writeCentered('Affiliated to ' . $affiliation, $muted, 12);
    }
    $pdf->space(4);
    $pdf->doubleRule($accent);

    $pdf->setFont(13, true);
    $pdf->writeCentered('CRITERION EVIDENCE PACK', $ink, 18);
    $pdf->setFont(9, false);
    $pdf->writeCentered('Teaching–Learning & Curriculum documentation readiness', $muted, 12);
    $pdf->space(4);
    $pdf->writeTwoColumn(
        'NAAC Grade: ' . ($naacGrade !== '' ? $naacGrade : 'Not set'),
        $nba !== '' ? ('NBA: ' . $nba) : '',
        $ink,
        13
    );
    $pdf->writeTwoColumn(
        'Academic Year: ' . ($academicYear !== '' ? $academicYear : '—'),
        'Semester: ' . ($semester !== '' ? $semester : '—'),
        $ink,
        13
    );
    $pdf->writeTwoColumn('Prepared by (HOD): ' . $hodName, 'Department: ' . $deptLabel, $ink, 13);
    $pdf->space(6);
    $pdf->thinRule($accent);

    // Summary metrics
    $pdf->setFont(12, true);
    $pdf->writeLine('1. Department Snapshot', $ink, 18);
    $pdf->setFont(9, false);
    $pdf->writeWrapped(
        'Live summary compiled from course plans, AI quality scores, and Bloom higher-order (K4–K6) coverage for this department.',
        0,
        12,
        $muted
    );
    $pdf->space(4);
    $summaryRows = [
        ['Course plans listed', (string)$planTotal],
        ['Approved plans', (string)$approved],
        ['Average AI score', $avgScore !== null ? (string)$avgScore : '—'],
        ['Avg Bloom K4–K6', $avgBloom !== null ? ($avgBloom . '%') : '—'],
        ['Report date', date('d M Y')],
    ];
    $pdf->table(['Metric', 'Value'], $summaryRows, [2.6, 1.6], 9.5);
    $pdf->space(10);

    // Status distribution
    $pdf->setFont(12, true);
    $pdf->writeLine('2. Plan Status Distribution', $ink, 18);
    $pdf->setFont(9, false);
    $statusOrder = ['draft', 'submitted', 'under_review', 'approved', 'returned'];
    $statusRows = [];
    foreach ($statusOrder as $st) {
        if (!isset($statusCounts[$st])) {
            continue;
        }
        $c = (int)$statusCounts[$st];
        $pct = $planTotal > 0 ? round(($c / $planTotal) * 100, 1) . '%' : '0%';
        $statusRows[] = [ucwords(str_replace('_', ' ', $st)), (string)$c, $pct];
    }
    foreach ($statusCounts as $st => $c) {
        if (in_array($st, $statusOrder, true)) {
            continue;
        }
        $pct = $planTotal > 0 ? round(((int)$c / $planTotal) * 100, 1) . '%' : '0%';
        $statusRows[] = [ucwords(str_replace('_', ' ', (string)$st)), (string)$c, $pct];
    }
    if (!$statusRows) {
        $statusRows[] = ['No plans yet', '0', '—'];
    }
    $statusRows[] = ['Total', (string)$planTotal, $planTotal > 0 ? '100%' : '—'];
    $pdf->table(['Status', 'Count', 'Share'], $statusRows, [2.4, 1, 1], 9.5);
    $pdf->space(10);

    // Evidence table
    $pdf->setFont(12, true);
    $pdf->writeLine('3. Criterion Evidence Register', $ink, 18);
    $pdf->setFont(9, false);
    $pdf->writeWrapped(
        'Subject-wise evidence from approved and in-progress course plans, including AI review score and Bloom higher-order thinking share (K4+K5+K6).',
        0,
        12,
        $muted
    );
    $pdf->space(4);
    $pdf->table(
        ['#', 'Subject', 'Status', 'AI', 'K4–K6', 'Ver', 'Updated'],
        $rows,
        [0.45, 2.55, 1.15, 0.7, 0.75, 0.5, 1.0],
        8.2
    );
    $pdf->space(12);

    $pdf->thinRule($muted);
    $pdf->setFont(8, false);
    $pdf->writeWrapped(
        'This department-scoped NAAC / NBA evidence pack is generated from ProProfessor AI academic records. '
        . 'Use it as supporting documentation for SSR / AQAR / NBA compliance reviews. '
        . 'AI scores assist curriculum quality checks and do not replace statutory peer-team assessment.',
        0,
        11,
        $muted
    );
    $pdf->space(8);
    $pdf->writeTwoColumn('Prepared for: ' . $deptLabel, 'Confidential · Internal use', $muted, 12);

    $pdf->stampPageNumbers();
    $bytes = $pdf->output();
    $safeDept = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $deptCode !== '' ? $deptCode : $deptName) ?: 'Department';
    $filename = 'NAAC_Evidence_' . $safeDept . '_' . date('Ymd') . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
    exit;
}

if (isset($_GET['download']) && (string)$_GET['download'] === 'pdf') {
    hod_naac_download_pdf($inst, $dept, $plans, $user);
}

$naacGrade = trim((string)($inst['naac_grade'] ?? ''));
$nbaStatus = trim((string)($inst['nba_status'] ?? ''));
$academicYear = trim((string)($inst['academic_year'] ?? ''));
$semester = trim((string)($inst['current_semester'] ?? ''));
$period = trim($semester . ($semester !== '' && $academicYear !== '' ? ' ' : '') . $academicYear);

$approvedPlans = 0;
$pendingPlans = 0;
$scoreSum = 0.0;
$scoreN = 0;
$bloomSum = 0.0;
$bloomN = 0;
$plansWithResources = 0;
$outcomeCount = 0;
$subjectNames = [];
$latestUpdate = '';
$poScores = ['PO1' => null, 'PO2' => null, 'PO3' => null, 'PO4' => null];

$readPo = static function (mixed $node) use (&$readPo, &$poScores): void {
    if (!is_array($node)) {
        return;
    }
    foreach ($node as $key => $value) {
        if (is_string($key) && preg_match('/^PO\s*([1-4])$/i', trim($key), $match) && is_numeric($value)) {
            $poScores['PO' . $match[1]] = (float)$value;
        } elseif (is_array($value)) {
            $readPo($value);
        }
    }
};

foreach ($plans as $plan) {
    $status = strtolower((string)($plan['status'] ?? 'draft'));
    if ($status === 'approved') {
        $approvedPlans++;
    }
    if (in_array($status, ['submitted', 'under_review'], true)) {
        $pendingPlans++;
    }
    if ($plan['ai_score'] !== null && $plan['ai_score'] !== '') {
        $scoreSum += (float)$plan['ai_score'];
        $scoreN++;
    }
    $bloom = json_decode((string)($plan['bloom_data'] ?? ''), true);
    if (is_array($bloom) && $bloom !== []) {
        $bloomSum += (float)($bloom['K4'] ?? 0) + (float)($bloom['K5'] ?? 0) + (float)($bloom['K6'] ?? 0);
        $bloomN++;
    }
    $planData = json_decode((string)($plan['plan_data'] ?? ''), true);
    if (is_array($planData)) {
        $resources = $planData['resources'] ?? null;
        if (is_array($resources) && $resources !== []) {
            $plansWithResources++;
        }
        $outcomes = $planData['learning_outcomes'] ?? null;
        if (is_array($outcomes)) {
            foreach ($outcomes as $outcome) {
                if (trim((string)$outcome) !== '') {
                    $outcomeCount++;
                }
            }
        }
        $readPo($planData);
    }
    $subjectName = trim((string)($plan['subject_name'] ?? ''));
    if ($subjectName !== '') {
        $subjectNames[$subjectName] = true;
    }
    $updated = trim((string)($plan['updated_at'] ?? ''));
    if ($updated !== '' && ($latestUpdate === '' || strcmp($updated, $latestUpdate) > 0)) {
        $latestUpdate = $updated;
    }
}

$subjectTotal = 0;
if ($deptId > 0) {
    $subjectTotal = (int)(Database::fetch(
        'SELECT COUNT(*) c FROM subjects WHERE institution_id = ? AND department_id = ?',
        [$instId, $deptId]
    )['c'] ?? 0);
}

$attendance = null;
if ($deptId > 0) {
    $attendanceRows = Database::fetchAll(
        'SELECT s.class_id, s.subject_id,
                SUM(r.status IN ("present","late")) AS presentish,
                COUNT(r.id) AS total_rows
         FROM attendance_sessions s
         JOIN attendance_records r ON r.session_id = s.id
         JOIN classes c ON c.id = s.class_id
         WHERE c.institution_id = ? AND c.department_id = ?
           AND EXISTS (
             SELECT 1 FROM subject_assignments sa
             JOIN subjects sub ON sub.id = sa.subject_id
             WHERE sa.class_id = s.class_id AND sa.subject_id = s.subject_id
               AND sub.institution_id = ?
           )
         GROUP BY s.class_id, s.subject_id',
        [$instId, $deptId, $instId]
    );
    $pairSum = 0.0;
    $pairN = 0;
    foreach ($attendanceRows as $row) {
        $total = (int)($row['total_rows'] ?? 0);
        if ($total < 1) {
            continue;
        }
        $pairSum += ((float)$row['presentish'] * 100.0) / $total;
        $pairN++;
    }
    $attendance = $pairN > 0 ? round($pairSum / $pairN, 1) : null;
}
$attendanceMin = $instId > 0 ? institution_attendance_min($instId) : 75.0;

$fmtNum = static function (?float $n): string {
    if ($n === null) {
        return '—';
    }
    $rounded = round($n, 1);
    if (abs($rounded - round($rounded)) < 0.05) {
        return (string)(int)round($rounded);
    }
    return number_format($rounded, 1);
};
$fmtWhen = static function (string $value): string {
    $ts = strtotime($value);
    return $ts ? date('d M Y', $ts) : $value;
};

$avgAi = $scoreN > 0 ? round($scoreSum / $scoreN, 1) : null;
$avgK46 = $bloomN > 0 ? round($bloomSum / $bloomN, 1) : null;
$coverage = null;
if ($subjectTotal > 0) {
    $coverage = round(min($subjectTotal, count($subjectNames)) * 100 / $subjectTotal, 1);
}
$resourcePct = $planTotal > 0 ? round($plansWithResources * 100 / $planTotal, 1) : null;
$approvalPct = $planTotal > 0 ? round($approvedPlans * 100 / $planTotal, 1) : null;

$mark = static function (string $label, string $tone): array {
    return ['label' => $label, 'tone' => $tone];
};
$fromPct = static function (?float $pct, float $onTrackAt) use ($mark): array {
    if ($pct === null) {
        return $mark('Not recorded', 'none');
    }
    if ($pct >= $onTrackAt) {
        return $mark('On Track', 'ok');
    }
    if ($pct > 0) {
        return $mark('Needs Attention', 'warn');
    }
    return $mark('Action Required', 'bad');
};

$c1 = $fromPct($coverage, 100);
$c2 = $avgK46 === null
    ? $mark('Not recorded', 'none')
    : ($avgK46 >= 30 ? $mark('On Track', 'ok') : $mark('Needs Attention', 'warn'));
$c3 = $mark('Not recorded', 'none');
$c4 = $fromPct($resourcePct, 100);
$c5 = $attendance === null
    ? $mark('Not recorded', 'none')
    : ($attendance >= $attendanceMin ? $mark('On Track', 'ok') : $mark('Needs Attention', 'warn'));
if ($approvalPct === null) {
    $c6 = $mark('Not recorded', 'none');
} elseif ($approvedPlans === $planTotal) {
    $c6 = $mark('On Track', 'ok');
} elseif ($pendingPlans > 0) {
    $c6 = $mark('Needs Attention', 'warn');
} else {
    $c6 = $mark('Action Required', 'bad');
}
$c7 = $mark('Not recorded', 'none');

$criteria = [
    [
        'code' => 'C1',
        'title' => 'Curricular Aspects',
        'pct' => $coverage,
        'status' => $c1,
        'text' => $coverage === null
            ? 'No department subjects or course plans are on file yet.'
            : ((int)min($subjectTotal, count($subjectNames)) . ' of ' . (int)$subjectTotal . ' department subjects have a course plan.'),
    ],
    [
        'code' => 'C2',
        'title' => 'Teaching-Learning & Evaluation',
        'pct' => $avgK46,
        'status' => $c2,
        'text' => $avgK46 === null
            ? 'Bloom coverage is not stored on the department course plans.'
            : ('Average higher-order Bloom coverage (K4–K6) across ' . (int)$bloomN . ' plans'
                . ($avgAi !== null ? ('. Average AI score is ' . $fmtNum($avgAi) . '.') : '.')),
    ],
    [
        'code' => 'C3',
        'title' => 'Research, Innovations & Extension',
        'pct' => null,
        'status' => $c3,
        'text' => 'Research and extension activity is not recorded for this department.',
    ],
    [
        'code' => 'C4',
        'title' => 'Infrastructure & Learning Resources',
        'pct' => $resourcePct,
        'status' => $c4,
        'text' => $resourcePct === null
            ? 'No course plans are on file yet.'
            : ((int)$plansWithResources . ' of ' . (int)$planTotal . ' course plans list a textbook or learning resource.'),
    ],
    [
        'code' => 'C5',
        'title' => 'Student Support & Progression',
        'pct' => $attendance,
        'status' => $c5,
        'text' => $attendance === null
            ? 'No attendance is recorded for this department.'
            : ('Department attendance from class sessions. Minimum is ' . $fmtNum($attendanceMin) . '%.'),
    ],
    [
        'code' => 'C6',
        'title' => 'Governance, Leadership & Management',
        'pct' => $approvalPct,
        'status' => $c6,
        'text' => $approvalPct === null
            ? 'No course plans are waiting for department review.'
            : ((int)$approvedPlans . ' of ' . (int)$planTotal . ' course plans approved.'),
    ],
    [
        'code' => 'C7',
        'title' => 'Institutional Values & Best Practices',
        'pct' => null,
        'status' => $c7,
        'text' => 'Institutional values and best-practice evidence is not recorded.',
    ],
];

$poCards = [];
foreach ($poScores as $code => $score) {
    $poCards[] = [
        'code' => $code,
        'score' => $score,
        'status' => $score === null ? $mark('Not recorded', 'none') : $mark('Recorded', 'ok'),
    ];
}
$poRecorded = count(array_filter($poScores, static fn($score): bool => $score !== null));

$deptLine = '';
if ($dept) {
    $deptCode = trim((string)($dept['code'] ?? ''));
    $deptName = trim((string)($dept['name'] ?? ''));
    $deptLine = $deptCode !== '' ? ($deptCode . ' — ' . $deptName) : $deptName;
}
$heroMeta = array_values(array_filter([
    $deptLine,
    $period,
], static fn(string $part): bool => $part !== ''));

$pdfHref = url('/hod/reports?download=pdf' . ($isAdmin && $deptId > 0 ? '&department_id=' . $deptId : ''));
$barWidth = static function (?float $pct): string {
    if ($pct === null) {
        return '0';
    }
    return (string)max(0, min(100, round($pct, 1)));
};

render_header('NAAC & NBA Compliance', 'reports', [
    'compactTitle' => true,
    'subtitle' => 'Department accreditation readiness, criteria progress and program outcome attainment.',
]);
?>
<?php if (!$isAdmin && $deptId < 1): ?>
<div class="panel">
  <div class="alert alert-warn">Your HOD account is not linked to a department. Contact the College Admin.</div>
</div>
<?php else: ?>
<div class="hod-comp">
  <section class="hod-hero">
    <div class="hod-hero-copy">
      <h2><?= icon('file', 'icon-inline') ?> NAAC &amp; NBA Compliance</h2>
      <p>Department accreditation readiness, criteria progress and program outcome attainment.</p>
      <?php if ($heroMeta): ?><p class="hod-comp-meta"><?= e(implode(' · ', $heroMeta)) ?></p><?php endif; ?>
    </div>
    <a class="btn btn-primary btn-shine no-print" href="<?= e($pdfHref) ?>"><?= icon('file') ?> Export / Print</a>
  </section>

  <section class="hod-comp-summary" aria-label="Accreditation summary">
    <article class="hod-panel hod-comp-score">
      <div class="hod-comp-score-h">
        <h2>NAAC Compliance</h2>
        <?php if ($naacGrade !== ''): ?>
          <span class="hod-status is-ok">Grade on file</span>
        <?php else: ?>
          <span class="hod-status is-warn">Not recorded</span>
        <?php endif; ?>
      </div>
      <p class="hod-comp-figure<?= $naacGrade === '' ? ' is-empty' : '' ?>"><?= $naacGrade !== '' ? e($naacGrade) : '—' ?></p>
      <p class="hod-comp-hint">Institution NAAC grade<?= $academicYear !== '' ? (' · ' . e($academicYear)) : '' ?></p>
      <?php if ($latestUpdate !== ''): ?>
        <p class="hod-comp-hint">Latest course-plan update <?= e($fmtWhen($latestUpdate)) ?></p>
      <?php endif; ?>
    </article>

    <article class="hod-panel hod-comp-score">
      <div class="hod-comp-score-h">
        <h2>NBA Accreditation</h2>
        <?php if ($nbaStatus !== ''): ?>
          <span class="hod-status is-warn"><?= e($nbaStatus) ?></span>
        <?php else: ?>
          <span class="hod-status is-warn">Not recorded</span>
        <?php endif; ?>
      </div>
      <p class="hod-comp-figure<?= $nbaStatus === '' ? ' is-empty' : '' ?>"><?= $nbaStatus !== '' ? e($nbaStatus) : '—' ?></p>
      <p class="hod-comp-hint">
        <?php if ($nbaStatus !== ''): ?>
          Institution NBA status
        <?php else: ?>
          NBA status is not set for this institution.
        <?php endif; ?>
      </p>
      <?php if ($outcomeCount > 0): ?>
        <p class="hod-comp-hint"><?= (int)$outcomeCount ?> course outcomes are written across <?= (int)$planTotal ?> plans.</p>
      <?php endif; ?>
    </article>
  </section>

  <section class="hod-panel" aria-label="NAAC criteria checklist">
    <div class="hod-panel-h">
      <h2><?= icon('check', 'icon-inline') ?> NAAC Criteria Checklist</h2>
    </div>
    <div class="hod-comp-list">
      <?php foreach ($criteria as $item):
        $tone = (string)$item['status']['tone'];
        $barTone = $tone === 'none' ? '' : (' is-' . $tone);
      ?>
        <article class="hod-comp-row">
          <span class="hod-comp-ico is-<?= e($tone) ?>" aria-hidden="true">
            <?= icon($tone === 'ok' ? 'check' : ($tone === 'none' ? 'file' : 'alert')) ?>
          </span>
          <div class="hod-comp-row-body">
            <div class="hod-comp-row-h">
              <strong><?= e($item['code']) ?> — <?= e($item['title']) ?></strong>
              <span class="hod-comp-row-meta">
                <span class="hod-comp-pct"><?= $item['pct'] === null ? '—' : e($fmtNum((float)$item['pct']) . '%') ?></span>
                <span class="hod-status is-<?= e($tone === 'none' ? 'none' : $tone) ?>"><?= e($item['status']['label']) ?></span>
              </span>
            </div>
            <p><?= e($item['text']) ?></p>
            <div class="hod-bar<?= e($barTone) ?>"><span style="width:<?= e($barWidth($item['pct'])) ?>%"></span></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="hod-panel" aria-label="NBA program outcome attainment">
    <div class="hod-panel-h">
      <h2><?= icon('chart', 'icon-inline') ?> NBA Program Outcome Attainment</h2>
    </div>
    <p class="hod-comp-note">
      <?php if ($poRecorded > 0): ?>
        <?= (int)$poRecorded ?> of 4 program outcomes have a stored attainment value.
      <?php else: ?>
        Program-outcome attainment is not stored. These cards stay empty until PO1–PO4 results are recorded.
      <?php endif; ?>
    </p>
    <div class="hod-comp-pos">
      <?php foreach ($poCards as $po):
        $tone = (string)$po['status']['tone'];
      ?>
        <article class="hod-comp-po">
          <span class="hod-comp-po-code"><?= e($po['code']) ?></span>
          <strong><?= $po['score'] === null ? '—' : e($fmtNum((float)$po['score'])) ?></strong>
          <span class="hod-status is-<?= e($tone === 'none' ? 'none' : $tone) ?>"><?= e($po['status']['label']) ?></span>
          <div class="hod-bar"><span style="width:0%"></span></div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="hod-panel">
    <div class="hod-panel-h">
      <h2><?= icon('file', 'icon-inline') ?> Criterion evidence pack</h2>
    </div>
    <p class="hod-comp-note">Compiled from this department’s course plans, Bloom maps, and AI review scores. Export / Print downloads the NAAC PDF.</p>
    <div class="table-wrap"><table>
      <thead><tr><th>Subject</th><th>Status</th><th>AI score</th><th>Bloom K4-K6</th><th>Version</th><th>Updated</th></tr></thead>
      <tbody>
      <?php if (!$plansPage): ?>
        <tr><td colspan="6" class="muted">No course plans in this department yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($plansPage as $p):
        $b = json_decode($p['bloom_data'] ?: '{}', true) ?: [];
        $h = (float)($b['K4'] ?? 0) + (float)($b['K5'] ?? 0) + (float)($b['K6'] ?? 0);
      ?>
        <tr>
          <td><?= e($p['subject_name']) ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td><?= e((string)$p['ai_score']) ?></td>
          <td><?= e((string)$h) ?>%</td>
          <td>v<?= (int)$p['version'] ?></td>
          <td><?= e($p['updated_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($planTotal > 0): ?>
    <div class="hod-comp-pager">
      <span class="chip"><?= (int)$planTotal ?> total · page <?= (int)$planPage ?> / <?= (int)$planTotalPages ?> · 5 per page</span>
      <div class="hod-comp-pager-actions">
        <?php if ($planPage > 1): ?>
          <a class="btn btn-sm btn-ghost" href="<?= e($reportsPageQuery($planPage - 1)) ?>">Previous</a>
        <?php else: ?>
          <button class="btn btn-sm btn-ghost" type="button" disabled>Previous</button>
        <?php endif; ?>
        <?php if ($planPage < $planTotalPages): ?>
          <a class="btn btn-sm btn-primary" href="<?= e($reportsPageQuery($planPage + 1)) ?>">Next</a>
        <?php else: ?>
          <button class="btn btn-sm btn-ghost" type="button" disabled>Next</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
<?php render_footer(); ?>
