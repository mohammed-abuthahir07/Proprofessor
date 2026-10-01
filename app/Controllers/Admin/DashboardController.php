<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\CoursePlan;
use App\Models\Expense;
use App\Models\Institution;
use App\Models\User;
use Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin', 'superadmin');
        $user = $this->user();
        $instId = (int)$user['institution_id'];
        $inst = Institution::find($instId);
        ensure_fee_collection_schema();
        ensure_faculty_salary_schema();

        $tz = new \DateTimeZone('Asia/Kolkata');
        $today = new \DateTime('today', $tz);
        $academicYear = institution_academic_year($instId);
        $departments = $this->departmentStats($instId, $academicYear);
        $fees = $this->feeTotals($instId, $academicYear);
        $plans = $this->planTotals($instId);

        $this->view('admin/dashboard', [
            'title' => 'Institution Overview',
            'active' => 'dash',
            'subtitle' => $this->overviewLine($inst, $academicYear),
            'dashboardHero' => true,
            'inst' => $inst,
            'stats' => [
                'users' => User::count('institution_id = ?', [$instId]),
                'plans' => CoursePlan::count('institution_id = ?', [$instId]),
                'students' => User::count('institution_id = ? AND role = "student"', [$instId]),
                'spend' => (float)(Database::fetch(
                    'SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE institution_id = ?',
                    [$instId]
                )['s'] ?? 0),
            ],
            'overview' => [
                'faculty' => User::count('institution_id = ? AND role = "professor"', [$instId]),
                'departments' => count($departments),
                'attendance' => $this->averagePercent(array_column($departments, 'attendance')),
                'fees' => $fees,
                'plans' => $plans,
                'exam' => $this->nextExam($instId, $today),
                'academic_year' => $academicYear,
                'as_of' => $today->format('M j, Y'),
            ],
            'week' => $this->attendanceWeek($instId, $today, $departments),
            'actions' => $this->actions($instId, $academicYear, $fees, $today),
            'departments' => $departments,
        ]);
    }

    /** @param array<string,mixed>|null $inst */
    private function overviewLine(?array $inst, string $academicYear): string
    {
        $parts = [];
        $name = trim((string)($inst['name'] ?? ''));
        if ($name !== '') {
            $parts[] = $name;
        }
        if ($academicYear !== '') {
            $parts[] = 'Academic Year ' . $academicYear;
        }
        $semester = trim((string)($inst['current_semester'] ?? ''));
        if ($semester !== '') {
            $parts[] = $semester;
        }
        return $parts === [] ? 'Institution' : implode(' · ', $parts);
    }

    /**
     * Same per-department figures the Departments page shows, plus fee dues.
     *
     * @return list<array<string,mixed>>
     */
    private function departmentStats(int $instId, string $academicYear): array
    {
        $departments = Database::fetchAll(
            'SELECT id, name, code FROM departments WHERE institution_id = ? ORDER BY name',
            [$instId]
        );

        $people = [];
        foreach (Database::fetchAll(
            'SELECT department_id,
                    SUM(role = "student" AND is_active = 1) AS students,
                    SUM(role = "professor" AND is_active = 1) AS faculty
             FROM users
             WHERE institution_id = ? AND department_id IS NOT NULL
             GROUP BY department_id',
            [$instId]
        ) as $row) {
            $people[(int)$row['department_id']] = [
                'students' => (int)$row['students'],
                'faculty' => (int)$row['faculty'],
            ];
        }

        $plans = [];
        foreach (Database::fetchAll(
            'SELECT department_id,
                    SUM(status = "approved") AS approved,
                    COUNT(*) AS total
             FROM course_plans
             WHERE institution_id = ? AND department_id IS NOT NULL
             GROUP BY department_id',
            [$instId]
        ) as $row) {
            $plans[(int)$row['department_id']] = [
                'approved' => (int)$row['approved'],
                'total' => (int)$row['total'],
            ];
        }

        $pairsByDept = [];
        foreach (Database::fetchAll(
            'SELECT c.department_id, s.class_id, s.subject_id,
                    SUM(r.status IN ("present","late")) AS presentish,
                    COUNT(r.id) AS total_rows
             FROM attendance_sessions s
             JOIN attendance_records r ON r.session_id = s.id
             JOIN classes c ON c.id = s.class_id
             WHERE c.institution_id = ?
             GROUP BY c.department_id, s.class_id, s.subject_id',
            [$instId]
        ) as $row) {
            $pairsByDept[(int)$row['department_id']][] = $row;
        }

        $dues = $this->feeDuesByDepartment($instId, $academicYear);

        $cards = [];
        foreach ($departments as $dept) {
            $id = (int)$dept['id'];
            $counts = $people[$id] ?? ['students' => 0, 'faculty' => 0];
            $plan = $plans[$id] ?? null;
            $code = trim((string)($dept['code'] ?? ''));
            $cards[] = [
                'id' => $id,
                'name' => (string)$dept['name'],
                'code' => $code,
                'label' => $code !== '' ? $code : (string)$dept['name'],
                'students' => $counts['students'],
                'faculty' => $counts['faculty'],
                'attendance' => $this->averagePairPercentages($pairsByDept[$id] ?? []),
                'plans' => ($plan && $plan['total'] > 0)
                    ? round($plan['approved'] * 100 / $plan['total'], 1)
                    : null,
                'fee_pending' => $dues[$id] ?? null,
            ];
        }
        return $cards;
    }

    /** @return array<int,float> */
    private function feeDuesByDepartment(int $instId, string $academicYear): array
    {
        if ($academicYear === '') {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT u.department_id,
                    SUM(GREATEST(f.total_amount - COALESCE(p.paid, 0), 0)) AS pending
             FROM fee_records f
             JOIN users u ON u.id = f.student_id
             LEFT JOIN (
                 SELECT fee_record_id, SUM(amount) AS paid FROM fee_payments GROUP BY fee_record_id
             ) p ON p.fee_record_id = f.id
             WHERE f.institution_id = ? AND f.academic_year = ? AND u.department_id IS NOT NULL
             GROUP BY u.department_id',
            [$instId, $academicYear]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['department_id']] = (float)$row['pending'];
        }
        return $out;
    }

    /** @return array{collected:float,pending:float,students:int,overdue:int} */
    private function feeTotals(int $instId, string $academicYear): array
    {
        $empty = ['collected' => 0.0, 'pending' => 0.0, 'students' => 0, 'overdue' => 0];
        if ($academicYear === '') {
            return $empty;
        }
        $row = Database::fetch(
            'SELECT COALESCE(SUM(COALESCE(p.paid, 0)), 0) AS collected,
                    COALESCE(SUM(GREATEST(f.total_amount - COALESCE(p.paid, 0), 0)), 0) AS pending,
                    COUNT(DISTINCT f.student_id) AS students,
                    COUNT(DISTINCT CASE
                        WHEN f.total_amount - COALESCE(p.paid, 0) > 0 THEN f.student_id
                    END) AS overdue
             FROM fee_records f
             LEFT JOIN (
                 SELECT fee_record_id, SUM(amount) AS paid FROM fee_payments GROUP BY fee_record_id
             ) p ON p.fee_record_id = f.id
             WHERE f.institution_id = ? AND f.academic_year = ?',
            [$instId, $academicYear]
        );
        if (!$row) {
            return $empty;
        }
        return [
            'collected' => (float)$row['collected'],
            'pending' => (float)$row['pending'],
            'students' => (int)$row['students'],
            'overdue' => (int)$row['overdue'],
        ];
    }

    /** @return array{total:int,approved:int,percent:?float} */
    private function planTotals(int $instId): array
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS total, SUM(status = "approved") AS approved
             FROM course_plans WHERE institution_id = ?',
            [$instId]
        );
        $total = (int)($row['total'] ?? 0);
        $approved = (int)($row['approved'] ?? 0);
        return [
            'total' => $total,
            'approved' => $approved,
            'percent' => $total > 0 ? round($approved * 100 / $total, 1) : null,
        ];
    }

    /** @return array{days:?int,label:string,hint:string} */
    private function nextExam(int $instId, \DateTime $today): array
    {
        $row = Database::fetch(
            'SELECT title, event_date FROM academic_events
             WHERE institution_id = ? AND event_date >= ? AND event_type = ?
             ORDER BY event_date ASC LIMIT 1',
            [$instId, $today->format('Y-m-d'), 'exam']
        );
        if (!$row) {
            return ['days' => null, 'label' => '—', 'hint' => 'No exam date set'];
        }
        try {
            $when = new \DateTime((string)$row['event_date'], $today->getTimezone());
        } catch (\Throwable) {
            return ['days' => null, 'label' => '—', 'hint' => 'No exam date set'];
        }
        $days = (int)$today->diff($when)->format('%r%a');
        return [
            'days' => $days,
            'label' => $when->format('M j'),
            'hint' => $days === 0
                ? 'Starts today'
                : ($days === 1 ? 'Tomorrow' : $days . ' days away'),
        ];
    }

    /**
     * Present+late share for every taught day of the current week.
     *
     * @param list<array<string,mixed>> $departments
     * @return array{days:list<array{label:string,percent:?float}>,low:?array{label:string,percent:float}}
     */
    private function attendanceWeek(int $instId, \DateTime $today, array $departments): array
    {
        $monday = (clone $today)->modify('monday this week');
        $sunday = (clone $monday)->modify('+6 days');
        $rows = Database::fetchAll(
            'SELECT s.session_date,
                    SUM(r.status IN ("present","late")) AS presentish,
                    COUNT(r.id) AS total_rows
             FROM attendance_sessions s
             JOIN attendance_records r ON r.session_id = s.id
             WHERE s.institution_id = ? AND s.session_date BETWEEN ? AND ?
             GROUP BY s.session_date',
            [$instId, $monday->format('Y-m-d'), $sunday->format('Y-m-d')]
        );
        $byDate = [];
        foreach ($rows as $row) {
            $total = (int)$row['total_rows'];
            if ($total < 1) {
                continue;
            }
            $byDate[(string)$row['session_date']] = round((float)$row['presentish'] * 100 / $total, 1);
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = (clone $monday)->modify('+' . $i . ' days');
            $key = $day->format('Y-m-d');
            $days[] = [
                'label' => $day->format('D'),
                'percent' => $byDate[$key] ?? null,
            ];
        }

        $low = null;
        foreach ($departments as $dept) {
            if ($dept['attendance'] === null) {
                continue;
            }
            if ($low === null || $dept['attendance'] < $low['percent']) {
                $low = ['label' => (string)$dept['label'], 'percent' => (float)$dept['attendance']];
            }
        }
        return ['days' => $days, 'low' => $low];
    }

    /**
     * @param array{collected:float,pending:float,students:int,overdue:int} $fees
     * @return list<array{tone:string,text:string,meta:string,href:string}>
     */
    private function actions(int $instId, string $academicYear, array $fees, \DateTime $today): array
    {
        $items = [];

        if ($fees['overdue'] > 0) {
            $items[] = [
                'tone' => 'is-bad',
                'text' => $fees['overdue'] . ' ' . ($fees['overdue'] === 1 ? 'student has' : 'students have')
                    . ' outstanding fee dues of ' . fee_money($fees['pending']),
                'meta' => $academicYear !== '' ? $academicYear : '',
                'href' => '/admin/fee-collection',
            ];
        }

        $noPlan = (int)(Database::fetch(
            'SELECT COUNT(*) AS c FROM users u
             WHERE u.institution_id = ? AND u.role = "professor" AND u.is_active = 1
               AND NOT EXISTS (
                   SELECT 1 FROM course_plans p
                   WHERE p.professor_id = u.id AND p.institution_id = u.institution_id
               )',
            [$instId]
        )['c'] ?? 0);
        if ($noPlan > 0) {
            $items[] = [
                'tone' => 'is-warn',
                'text' => $noPlan . ' ' . ($noPlan === 1 ? 'faculty member has' : 'faculty have')
                    . ' not submitted a course plan yet',
                'meta' => 'Course plans',
                'href' => '/admin/faculty',
            ];
        }

        $lowAttendance = (int)(Database::fetch(
            'SELECT COUNT(*) AS c FROM (
                 SELECT r.student_id,
                        SUM(r.status IN ("present","late")) * 100 / COUNT(r.id) AS pct
                 FROM attendance_records r
                 JOIN attendance_sessions s ON s.id = r.session_id
                 JOIN users u ON u.id = r.student_id
                 WHERE s.institution_id = ? AND u.is_active = 1 AND u.role = "student"
                 GROUP BY r.student_id
                 HAVING pct < 65
             ) low',
            [$instId]
        )['c'] ?? 0);
        if ($lowAttendance > 0) {
            $items[] = [
                'tone' => 'is-bad',
                'text' => $lowAttendance . ' ' . ($lowAttendance === 1 ? 'student is' : 'students are')
                    . ' below 65% attendance — risk of detention',
                'meta' => 'Attendance',
                'href' => '/admin/students',
            ];
        }

        $salary = (int)(Database::fetch(
            'SELECT COUNT(*) AS c FROM faculty_salaries
             WHERE institution_id = ? AND salary_month = ? AND status <> "paid"',
            [$instId, $today->format('Y-m-01')]
        )['c'] ?? 0);
        if ($salary > 0) {
            $items[] = [
                'tone' => 'is-warn',
                'text' => $salary . ' ' . ($salary === 1 ? 'salary record is' : 'salary records are')
                    . ' pending or on hold for ' . $today->format('F Y'),
                'meta' => 'Payroll',
                'href' => '/admin/salary',
            ];
        }

        $exam = $this->nextExam($instId, $today);
        if ($exam['days'] !== null) {
            $items[] = [
                'tone' => 'is-info',
                'text' => 'Exams start ' . $exam['label'] . ' — ' . $exam['hint'],
                'meta' => 'Calendar',
                'href' => '/admin/analytics',
            ];
        }

        return $items;
    }

    /** @param list<?float> $values */
    private function averagePercent(array $values): ?float
    {
        $clean = array_values(array_filter($values, static fn($v) => $v !== null));
        if ($clean === []) {
            return null;
        }
        return round(array_sum($clean) / count($clean), 1);
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
}
