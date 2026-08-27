<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Institution;
use Database;
use SimplePdf;

final class NaacController extends Controller
{
    public function index(): void
    {
        require_admin_perm('manage_naac');
        $user = $this->user();
        $instId = (int)$user['institution_id'];
        $inst = Institution::find($instId);
        $plans = Database::fetchAll(
            'SELECT status, COUNT(*) c FROM course_plans WHERE institution_id = ? GROUP BY status',
            [$instId]
        );
        $faculty = Database::fetchAll(
            'SELECT u.full_name, d.name dept, COUNT(p.id) plans, AVG(p.ai_score) score
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN course_plans p ON p.professor_id = u.id
             WHERE u.institution_id = ? AND u.role = "professor" AND u.is_active = 1
             GROUP BY u.id
             ORDER BY d.name, u.full_name',
            [$instId]
        );

        if ($this->get('download') === 'pdf') {
            $this->downloadPdf($inst ?: [], $plans, $faculty);
            return;
        }

        $this->view('admin/naac', [
            'title' => 'NAAC Document Builder',
            'active' => 'naac',
            'inst' => $inst,
            'plans' => $plans,
            'faculty' => $faculty,
        ]);
    }

    /**
     * @param array<string,mixed> $inst
     * @param list<array<string,mixed>> $plans
     * @param list<array<string,mixed>> $faculty
     */
    private function downloadPdf(array $inst, array $plans, array $faculty): void
    {
        if (!$inst) {
            flash('error', 'Institution not found.');
            $this->redirect('/admin/naac');
        }

        $college = trim((string)($inst['name'] ?? 'Institution'));
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

        $ink = [20, 24, 40];
        $muted = [90, 98, 120];
        $accent = [30, 41, 79];

        $pdf = new SimplePdf();

        // Letterhead band
        $pdf->filledRect(0, 0, $pdf->pageWidth(), 78, $accent);
        $pdf->setFont(11, true);
        $pdf->textAt(42, 22, 'ProProfessor AI', [255, 255, 255]);
        $pdf->setFont(9, false);
        $pdf->textAt(42, 38, 'NAAC Document Builder · Accreditation Snapshot', [203, 213, 225]);
        $pdf->textAt(42, 52, 'Generated ' . date('d M Y, h:i A'), [148, 163, 184]);
        $pdf->moveTo(96);

        $pdf->setFont(16, true);
        $pdf->writeCenteredWrapped($college, 0, 20, $ink);
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
        $pdf->writeCentered('NAAC ACCREDITATION SNAPSHOT', $ink, 18);
        $pdf->setFont(10, false);
        $metaLeft = 'NAAC Grade: ' . ($naacGrade !== '' ? $naacGrade : 'Not set');
        $metaRight = $nba !== '' ? ('NBA: ' . $nba) : '';
        $pdf->writeTwoColumn($metaLeft, $metaRight, $ink, 14);
        $pdf->writeTwoColumn(
            'Academic Year: ' . ($academicYear !== '' ? $academicYear : '—'),
            'Semester: ' . ($semester !== '' ? $semester : '—'),
            $ink,
            14
        );
        $pdf->space(6);
        $pdf->thinRule($accent);

        // Plan compliance
        $pdf->setFont(12, true);
        $pdf->writeLine('1. Course Plan Compliance', $ink, 18);
        $pdf->setFont(9, false);
        $pdf->writeWrapped(
            'Status-wise distribution of course plans prepared for this institution (OBE / NAAC teaching-learning evidence).',
            0,
            12,
            $muted
        );
        $pdf->space(4);

        $statusOrder = ['draft', 'submitted', 'under_review', 'approved', 'returned'];
        $statusMap = [];
        $planTotal = 0;
        foreach ($plans as $p) {
            $st = strtolower((string)($p['status'] ?? ''));
            $c = (int)($p['c'] ?? 0);
            $statusMap[$st] = $c;
            $planTotal += $c;
        }
        $compRows = [];
        foreach ($statusOrder as $st) {
            if (!isset($statusMap[$st])) {
                continue;
            }
            $c = $statusMap[$st];
            $pct = $planTotal > 0 ? round(($c / $planTotal) * 100, 1) . '%' : '0%';
            $compRows[] = [ucwords(str_replace('_', ' ', $st)), (string)$c, $pct];
        }
        foreach ($statusMap as $st => $c) {
            if (in_array($st, $statusOrder, true)) {
                continue;
            }
            $pct = $planTotal > 0 ? round(($c / $planTotal) * 100, 1) . '%' : '0%';
            $compRows[] = [ucwords(str_replace('_', ' ', $st)), (string)$c, $pct];
        }
        if (!$compRows) {
            $compRows[] = ['No plans yet', '0', '—'];
        }
        $compRows[] = ['Total', (string)$planTotal, '100%'];
        $pdf->table(['Status', 'Count', 'Share'], $compRows, [2.4, 1, 1], 9.5);
        $pdf->space(10);

        // Faculty matrix
        $pdf->setFont(12, true);
        $pdf->writeLine('2. Faculty Matrix', $ink, 18);
        $pdf->setFont(9, false);
        $pdf->writeWrapped(
            'Faculty-wise course plan activity and average AI quality score for curriculum documentation readiness.',
            0,
            12,
            $muted
        );
        $pdf->space(4);

        $facRows = [];
        $facultyWithPlans = 0;
        $scoreSum = 0.0;
        $scoreN = 0;
        foreach ($faculty as $i => $f) {
            $plansN = (int)($f['plans'] ?? 0);
            if ($plansN > 0) {
                $facultyWithPlans++;
            }
            $score = $f['score'] !== null ? round((float)$f['score'], 1) : null;
            if ($score !== null) {
                $scoreSum += $score;
                $scoreN++;
            }
            $facRows[] = [
                (string)($i + 1),
                (string)($f['full_name'] ?? ''),
                (string)($f['dept'] ?? '—'),
                (string)$plansN,
                $score !== null ? (string)$score : '—',
            ];
        }
        if (!$facRows) {
            $facRows[] = ['—', 'No faculty records', '—', '0', '—'];
        }
        $pdf->table(
            ['#', 'Faculty', 'Department', 'Plans', 'Avg AI Score'],
            $facRows,
            [0.5, 2.4, 2.6, 0.9, 1.1],
            8.5
        );
        $pdf->space(10);

        // Summary insight box
        $avgScore = $scoreN > 0 ? round($scoreSum / $scoreN, 1) : null;
        $approved = (int)($statusMap['approved'] ?? 0);
        $pdf->setFont(12, true);
        $pdf->writeLine('3. Snapshot Summary', $ink, 18);
        $pdf->setFont(9, false);
        $summaryRows = [
            ['Active faculty listed', (string)count($faculty)],
            ['Faculty with course plans', (string)$facultyWithPlans],
            ['Approved course plans', (string)$approved],
            ['Average AI plan score', $avgScore !== null ? (string)$avgScore : '—'],
            ['Report date', date('d M Y')],
        ];
        $pdf->table(['Metric', 'Value'], $summaryRows, [2.2, 1.4], 9.5);
        $pdf->space(12);

        $pdf->thinRule($muted);
        $pdf->setFont(8, false);
        $pdf->writeWrapped(
            'This document is an institution-scoped NAAC readiness snapshot generated from ProProfessor AI records. '
            . 'Use it as supporting evidence for SSR / AQAR documentation reviews. '
            . 'Scores reflect AI-assisted curriculum quality checks and do not replace statutory NAAC peer-team assessment.',
            0,
            11,
            $muted
        );
        $pdf->space(8);
        $pdf->writeTwoColumn('Prepared for: College Admin', 'Confidential · Internal use', $muted, 12);

        $pdf->stampPageNumbers();
        $bytes = $pdf->output();
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $college) ?: 'Institution';
        $filename = 'NAAC_Snapshot_' . $safeName . '_' . date('Ymd') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
        exit;
    }
}
