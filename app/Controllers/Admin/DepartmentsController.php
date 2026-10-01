<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use Database;

final class DepartmentsController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin', 'superadmin');
        $instId = (int)$this->user()['institution_id'];

        $departments = Database::fetchAll(
            'SELECT d.id, d.name, d.code, d.is_active, u.full_name AS hod_name
             FROM departments d
             LEFT JOIN users u ON u.id = d.hod_user_id AND u.institution_id = d.institution_id
             WHERE d.institution_id = ?
             ORDER BY d.name',
            [$instId]
        );

        $peopleRows = Database::fetchAll(
            'SELECT department_id,
                    SUM(role = "student" AND is_active = 1) AS students,
                    SUM(role = "professor" AND is_active = 1) AS faculty
             FROM users
             WHERE institution_id = ? AND department_id IS NOT NULL
             GROUP BY department_id',
            [$instId]
        );
        $people = [];
        foreach ($peopleRows as $row) {
            $people[(int)$row['department_id']] = [
                'students' => (int)$row['students'],
                'faculty' => (int)$row['faculty'],
            ];
        }

        $planRows = Database::fetchAll(
            'SELECT department_id,
                    SUM(status = "approved") AS approved,
                    COUNT(*) AS total
             FROM course_plans
             WHERE institution_id = ? AND department_id IS NOT NULL
             GROUP BY department_id',
            [$instId]
        );
        $plans = [];
        $approvedTotal = 0;
        $planTotal = 0;
        foreach ($planRows as $row) {
            $approved = (int)$row['approved'];
            $total = (int)$row['total'];
            $plans[(int)$row['department_id']] = ['approved' => $approved, 'total' => $total];
            $approvedTotal += $approved;
            $planTotal += $total;
        }

        $budgetRows = Database::fetchAll(
            'SELECT department_id, fiscal_year, SUM(allocated) AS allocated
             FROM budgets
             WHERE institution_id = ? AND department_id IS NOT NULL
             GROUP BY department_id, fiscal_year',
            [$instId]
        );
        $budgets = [];
        foreach ($budgetRows as $row) {
            $deptId = (int)$row['department_id'];
            $year = (string)$row['fiscal_year'];
            if (!isset($budgets[$deptId]) || strcmp($year, $budgets[$deptId]['year']) > 0) {
                $budgets[$deptId] = ['year' => $year, 'amount' => (float)$row['allocated']];
            }
        }

        $attendanceRows = Database::fetchAll(
            'SELECT c.department_id, s.class_id, s.subject_id,
                    SUM(r.status IN ("present","late")) AS presentish,
                    COUNT(r.id) AS total_rows
             FROM attendance_sessions s
             JOIN attendance_records r ON r.session_id = s.id
             JOIN classes c ON c.id = s.class_id
             WHERE c.institution_id = ?
             GROUP BY c.department_id, s.class_id, s.subject_id',
            [$instId]
        );
        $pairsByDept = [];
        foreach ($attendanceRows as $row) {
            $pairsByDept[(int)$row['department_id']][] = $row;
        }

        $studentTotal = 0;
        $facultyTotal = 0;
        $attendanceValues = [];
        $cards = [];
        foreach ($departments as $index => $dept) {
            $id = (int)$dept['id'];
            $counts = $people[$id] ?? ['students' => 0, 'faculty' => 0];
            $studentTotal += $counts['students'];
            $facultyTotal += $counts['faculty'];
            $plan = $plans[$id] ?? null;
            $planPct = ($plan && $plan['total'] > 0)
                ? round($plan['approved'] * 100 / $plan['total'], 1)
                : null;
            $attendance = $this->averagePairPercentages($pairsByDept[$id] ?? []);
            if ($attendance !== null) {
                $attendanceValues[] = $attendance;
            }
            $code = trim((string)($dept['code'] ?? ''));
            $cards[] = [
                'id' => $id,
                'name' => (string)$dept['name'],
                'code' => $code,
                'mark' => $this->mark($code, (string)$dept['name']),
                'tone' => $index % 5,
                'active' => (int)($dept['is_active'] ?? 1) === 1,
                'students' => $counts['students'],
                'faculty' => $counts['faculty'],
                'attendance' => $attendance,
                'plans' => $planPct,
                'budget' => $budgets[$id]['amount'] ?? null,
                'budget_year' => $budgets[$id]['year'] ?? '',
                'hod' => trim((string)($dept['hod_name'] ?? '')),
            ];
        }

        $deptCount = count($cards);
        $subtitle = $deptCount . ' ' . ($deptCount === 1 ? 'department' : 'departments')
            . ' · ' . $studentTotal . ' ' . ($studentTotal === 1 ? 'student' : 'students')
            . ' · ' . $facultyTotal . ' faculty';

        $this->view('admin/departments', [
            'title' => 'Departments',
            'active' => 'departments',
            'subtitle' => $subtitle,
            'cards' => $cards,
            'summary' => [
                'attendance' => $attendanceValues === []
                    ? null
                    : round(array_sum($attendanceValues) / count($attendanceValues), 1),
                'plans' => $planTotal > 0 ? round($approvedTotal * 100 / $planTotal, 1) : null,
            ],
        ]);
    }

    /** @param list<array<string,mixed>> $rows */
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

    private function mark(string $code, string $name): string
    {
        $source = preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '';
        if ($source === '') {
            $letters = '';
            foreach (preg_split('/\s+/', $name) ?: [] as $word) {
                $letters .= mb_substr($word, 0, 1);
                if (mb_strlen($letters) >= 2) {
                    break;
                }
            }
            $source = $letters;
        }
        $source = strtoupper(mb_substr($source, 0, 2));
        return $source !== '' ? $source : 'DP';
    }
}
