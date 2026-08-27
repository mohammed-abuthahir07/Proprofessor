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
        'SELECT subject_name, status, ai_score, bloom_data, version, updated_at
         FROM course_plans
         WHERE institution_id = ? AND department_id = ?
         ORDER BY subject_name',
        [$instId, $deptId]
    )
    : [];

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

render_header('NAAC / NBA Reports', 'reports', ['subtitle' => 'Evidence snapshot from live academic data']);
?>
<?php if (!$isAdmin && $deptId < 1): ?>
<div class="panel">
  <div class="alert alert-warn">Your HOD account is not linked to a department. Contact the College Admin.</div>
</div>
<?php else: ?>
<div class="panel">
  <div class="panel-h">
    <div>
      <h2 style="margin:0">Criterion evidence pack</h2>
      <?php if ($dept): ?>
        <p class="muted" style="margin:.35rem 0 0;font-size:.85rem"><?= e((string)$dept['code']) ?> — <?= e((string)$dept['name']) ?></p>
      <?php endif; ?>
    </div>
    <a class="btn btn-primary btn-sm no-print" href="<?= e(url('/hod/reports?download=pdf' . ($isAdmin && $deptId > 0 ? '&department_id=' . $deptId : ''))) ?>">Export / Print</a>
  </div>
  <p>Auto-compiled from approved course plans, Bloom maps, and AI review scores. Click <strong>Export / Print</strong> to download a NAAC PDF.</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Subject</th><th>Status</th><th>AI score</th><th>Bloom K4-K6</th><th>Version</th><th>Updated</th></tr></thead>
    <tbody>
    <?php if (!$plans): ?>
      <tr><td colspan="6" class="muted">No course plans in this department yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($plans as $p):
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
</div>
<?php endif; ?>
<?php render_footer(); ?>
