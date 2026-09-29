<?php
declare(strict_types=1);

namespace App\Controllers\Hod;

use App\Core\Controller;
use CoursePlanTools;
use Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireRole('hod', 'admin');
        $user = $this->user();
        $instId = (int)$user['institution_id'];
        $deptId = $user['department_id'];

        $pending = (int)(Database::fetch(
            'SELECT COUNT(*) c FROM course_plans WHERE institution_id = ? AND department_id = ? AND status IN ("submitted","under_review")',
            [$instId, $deptId]
        )['c'] ?? 0);
        $approved = (int)(Database::fetch(
            'SELECT COUNT(*) c FROM course_plans WHERE institution_id = ? AND department_id = ? AND status = "approved"',
            [$instId, $deptId]
        )['c'] ?? 0);
        $facultyCount = (int)(Database::fetch(
            'SELECT COUNT(*) c FROM users WHERE institution_id = ? AND department_id = ? AND role = "professor" AND is_active = 1',
            [$instId, $deptId]
        )['c'] ?? 0);
        $avg = Database::fetch(
            'SELECT AVG(ai_score) a FROM course_plans WHERE institution_id = ? AND department_id = ? AND ai_score IS NOT NULL',
            [$instId, $deptId]
        );
        $alerts = Database::fetchAll(
            'SELECT * FROM compliance_alerts WHERE institution_id = ? AND department_id = ? AND is_resolved = 0 ORDER BY id DESC LIMIT 5',
            [$instId, $deptId]
        );

        $dept = ((int)$deptId > 0)
            ? Database::fetch(
                'SELECT id, name, code FROM departments WHERE id = ? AND institution_id = ?',
                [(int)$deptId, $instId]
            )
            : null;
        $inst = Database::fetch(
            'SELECT academic_year, current_semester, naac_grade FROM institutions WHERE id = ?',
            [$instId]
        ) ?: [];

        $plans = Database::fetchAll(
            'SELECT id, subject_name, ai_score, bloom_data, status FROM course_plans WHERE institution_id = ? AND department_id = ?',
            [$instId, $deptId]
        );
        $bloom = $this->bloomDistribution($plans);
        $bloomWarnings = 0;
        foreach ($plans as $plan) {
            $balance = CoursePlanTools::bloomBalance($plan);
            if (!empty($balance['warning'])) {
                $bloomWarnings++;
            }
        }

        $queue = Database::fetchAll(
            'SELECT p.id, p.title, p.subject_name, p.semester, p.academic_year, p.ai_score, p.bloom_data, p.submitted_at,
                    u.full_name AS professor_name,
                    c.name AS class_name, c.section AS class_section, c.year AS class_year
             FROM course_plans p
             JOIN users u ON u.id = p.professor_id
             LEFT JOIN classes c ON c.id = p.class_id
             WHERE p.institution_id = ? AND p.department_id = ? AND p.status IN ("submitted","under_review")
             ORDER BY p.submitted_at DESC
             LIMIT 5',
            [$instId, $deptId]
        );

        $facultySql = 'SELECT u.id, u.full_name,
                SUM(p.status="approved") approved,
                SUM(p.status IN ("submitted","under_review")) pending,
                COUNT(p.id) total,
                AVG(p.ai_score) avg_score
             FROM users u
             LEFT JOIN course_plans p ON p.professor_id=u.id
             WHERE u.institution_id=? AND u.role="professor" AND u.is_active=1';
        $facultyParams = [$instId];
        if ($deptId) {
            $facultySql .= ' AND u.department_id=?';
            $facultyParams[] = $deptId;
        }
        $facultySql .= ' GROUP BY u.id ORDER BY u.full_name';
        $facultyRows = Database::fetchAll($facultySql, $facultyParams);

        $contextLine = $this->contextLine($dept, $facultyCount, $inst);

        $this->view('hod/dashboard', [
            'title' => 'HOD Dashboard',
            'active' => 'dash',
            'subtitle' => $contextLine,
            'dashboardHero' => true,
            'pending' => $pending,
            'approved' => $approved,
            'facultyCount' => $facultyCount,
            'avgAi' => ($avg['a'] !== null && $avg['a'] !== '') ? round((float)$avg['a'], 1) : null,
            'alerts' => $alerts,
            'deptName' => trim((string)($dept['name'] ?? '')),
            'naacGrade' => trim((string)($inst['naac_grade'] ?? '')),
            'contextLine' => $contextLine,
            'periodLabel' => $this->periodLabel($inst),
            'bloom' => $bloom['dist'],
            'bloomSamples' => $bloom['samples'],
            'bloomWarnings' => $bloomWarnings,
            'planCount' => count($plans),
            'queue' => $queue,
            'facultyRows' => array_slice($facultyRows, 0, 6),
            'facultyTotal' => count($facultyRows),
            'assignmentMap' => $this->facultyAssignments($instId, $deptId),
            'attendanceByProfessor' => $this->attendanceByProfessor($instId, $deptId),
            'deptAttendance' => $this->departmentAttendance($instId, $deptId),
            'attendanceMin' => institution_attendance_min($instId),
        ]);
    }

    /**
     * Same department average as HOD analytics.
     *
     * @param list<array<string,mixed>> $plans
     * @return array{dist:array<string,float>,samples:int}
     */
    private function bloomDistribution(array $plans): array
    {
        $dist = ['K1' => 0.0, 'K2' => 0.0, 'K3' => 0.0, 'K4' => 0.0, 'K5' => 0.0, 'K6' => 0.0];
        $n = 0;
        foreach ($plans as $plan) {
            $bloom = json_decode((string)($plan['bloom_data'] ?: '{}'), true) ?: [];
            foreach ($dist as $key => $_) {
                $dist[$key] += (float)($bloom[$key] ?? 0);
            }
            if ($bloom) {
                $n++;
            }
        }
        if ($n) {
            foreach ($dist as $key => $value) {
                $dist[$key] = round($value / $n, 1);
            }
        }
        return ['dist' => $dist, 'samples' => $n];
    }

    /**
     * @param array<string,mixed>|null $dept
     * @param array<string,mixed> $inst
     */
    private function contextLine(?array $dept, int $facultyCount, array $inst): string
    {
        $parts = [];
        $name = trim((string)($dept['name'] ?? ''));
        if ($name !== '') {
            $parts[] = $name;
        }
        $parts[] = $facultyCount === 1 ? '1 Faculty member' : ($facultyCount . ' Faculty');
        $period = $this->periodLabel($inst);
        if ($period !== '') {
            $parts[] = $period;
        }
        return implode(' · ', $parts);
    }

    /**
     * @param array<string,mixed> $inst
     */
    private function periodLabel(array $inst): string
    {
        $semester = trim((string)($inst['current_semester'] ?? ''));
        $year = trim((string)($inst['academic_year'] ?? ''));
        if ($semester !== '' && $year !== '') {
            return $semester . ' ' . $year;
        }
        return $semester !== '' ? $semester : $year;
    }

    /**
     * @return array<int,list<array<string,mixed>>>
     */
    private function facultyAssignments(int $instId, mixed $deptId): array
    {
        if (!(int)$deptId) {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT sa.professor_id, s.name AS subject_name, c.name AS class_name, c.section, c.year
             FROM subject_assignments sa
             JOIN subjects s ON s.id = sa.subject_id
             JOIN classes c ON c.id = sa.class_id
             WHERE c.institution_id = ? AND c.department_id = ? AND s.institution_id = ?
             ORDER BY s.name',
            [$instId, (int)$deptId, $instId]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['professor_id']][] = $row;
        }
        return $map;
    }

    /**
     * Department attendance uses the same present/late pair average as the professor benchmark.
     */
    private function departmentAttendance(int $instId, mixed $deptId): ?float
    {
        if (!(int)$deptId) {
            return null;
        }
        $rows = Database::fetchAll(
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
            [$instId, (int)$deptId, $instId]
        );
        return $this->averagePairPercentages($rows);
    }

    /**
     * @return array<int,?float>
     */
    private function attendanceByProfessor(int $instId, mixed $deptId): array
    {
        if (!(int)$deptId) {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT sa.professor_id, s.class_id, s.subject_id,
                    SUM(r.status IN ("present","late")) AS presentish,
                    COUNT(r.id) AS total_rows
             FROM subject_assignments sa
             JOIN classes c ON c.id = sa.class_id
             JOIN attendance_sessions s ON s.class_id = sa.class_id AND s.subject_id = sa.subject_id
             JOIN attendance_records r ON r.session_id = s.id
             WHERE c.institution_id = ? AND c.department_id = ?
             GROUP BY sa.professor_id, s.class_id, s.subject_id',
            [$instId, (int)$deptId]
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['professor_id']][] = $row;
        }
        $out = [];
        foreach ($grouped as $professorId => $pairs) {
            $out[$professorId] = $this->averagePairPercentages($pairs);
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function averagePairPercentages(array $rows): ?float
    {
        $sum = 0.0;
        $n = 0;
        foreach ($rows as $row) {
            $total = (int)($row['total_rows'] ?? 0);
            if ($total < 1) {
                continue;
            }
            $sum += ((float)$row['presentish'] * 100.0) / $total;
            $n++;
        }
        return $n > 0 ? round($sum / $n, 1) : null;
    }
}
