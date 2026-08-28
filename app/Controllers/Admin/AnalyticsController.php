<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use Database;

final class AnalyticsController extends Controller
{
    public function index(): void
    {
        require_admin_perm('view_analytics');
        $instId = (int)$this->user()['institution_id'];

        $roleRows = Database::fetchAll(
            'SELECT role, COUNT(*) AS c
             FROM users
             WHERE institution_id = ? AND is_active = 1 AND role IN ("student","professor","hod")
             GROUP BY role',
            [$instId]
        );
        $roles = ['student' => 0, 'professor' => 0, 'hod' => 0];
        foreach ($roleRows as $row) {
            $key = (string)($row['role'] ?? '');
            if (isset($roles[$key])) {
                $roles[$key] = (int)($row['c'] ?? 0);
            }
        }

        $aiByDept = Database::fetchAll(
            'SELECT d.id, d.code, d.name,
                    COUNT(g.id) AS ai_count
             FROM ai_generations g
             JOIN users u ON u.id = g.user_id AND u.institution_id = g.institution_id
             JOIN departments d ON d.id = u.department_id AND d.institution_id = g.institution_id
             WHERE g.institution_id = ?
               AND u.department_id IS NOT NULL
             GROUP BY d.id, d.code, d.name
             ORDER BY ai_count DESC, d.name ASC',
            [$instId]
        );
        $aiTotal = 0;
        foreach ($aiByDept as $row) {
            $aiTotal += (int)($row['ai_count'] ?? 0);
        }
        $topDept = $aiByDept[0] ?? null;

        $deptPeople = Database::fetchAll(
            'SELECT d.id, d.code, d.name,
                    SUM(CASE WHEN u.role = "professor" AND u.is_active = 1 THEN 1 ELSE 0 END) AS professors,
                    SUM(CASE WHEN u.role = "student" AND u.is_active = 1 THEN 1 ELSE 0 END) AS students,
                    SUM(CASE WHEN u.role = "hod" AND u.is_active = 1 THEN 1 ELSE 0 END) AS hods
             FROM departments d
             LEFT JOIN users u ON u.department_id = d.id AND u.institution_id = d.institution_id
             WHERE d.institution_id = ?
             GROUP BY d.id, d.code, d.name
             ORDER BY d.name',
            [$instId]
        );

        $expenseYear = (int)date('Y');
        $expenseMonth = (int)date('n');
        $expenseMonthLabel = date('F Y');

        $deptExpenses = Database::fetchAll(
            'SELECT d.id, d.code, d.name,
                    COALESCE(SUM(CASE WHEN YEAR(e.expense_date) = ? THEN e.amount ELSE 0 END), 0) AS year_total,
                    COALESCE(SUM(CASE WHEN YEAR(e.expense_date) = ? AND MONTH(e.expense_date) = ? THEN e.amount ELSE 0 END), 0) AS month_total
             FROM departments d
             LEFT JOIN expenses e ON e.department_id = d.id AND e.institution_id = d.institution_id
             WHERE d.institution_id = ?
             GROUP BY d.id, d.code, d.name
             ORDER BY year_total DESC, d.name ASC',
            [$expenseYear, $expenseYear, $expenseMonth, $instId]
        );

        $expenseYearTotal = 0.0;
        $expenseMonthTotal = 0.0;
        foreach ($deptExpenses as $row) {
            $expenseYearTotal += (float)($row['year_total'] ?? 0);
            $expenseMonthTotal += (float)($row['month_total'] ?? 0);
        }

        // Institution expenses with no department (still count in totals).
        $unassigned = Database::fetch(
            'SELECT
                COALESCE(SUM(CASE WHEN YEAR(expense_date) = ? THEN amount ELSE 0 END), 0) AS year_total,
                COALESCE(SUM(CASE WHEN YEAR(expense_date) = ? AND MONTH(expense_date) = ? THEN amount ELSE 0 END), 0) AS month_total
             FROM expenses
             WHERE institution_id = ? AND (department_id IS NULL OR department_id = 0)',
            [$expenseYear, $expenseYear, $expenseMonth, $instId]
        );
        $unassignedYear = (float)($unassigned['year_total'] ?? 0);
        $unassignedMonth = (float)($unassigned['month_total'] ?? 0);
        $expenseYearTotal += $unassignedYear;
        $expenseMonthTotal += $unassignedMonth;

        $topExpenseDept = null;
        foreach ($deptExpenses as $row) {
            if ((float)($row['year_total'] ?? 0) > 0) {
                $topExpenseDept = $row;
                break;
            }
        }

        $this->view('admin/analytics', [
            'title' => 'Institution Analytics',
            'active' => 'analytics',
            'metrics' => [
                'ai_calls' => (int)(Database::fetch(
                    'SELECT COUNT(*) c FROM ai_generations WHERE institution_id = ?',
                    [$instId]
                )['c'] ?? 0),
                'avg_score' => Database::fetch(
                    'SELECT AVG(ai_score) a FROM course_plans WHERE institution_id = ?',
                    [$instId]
                )['a'],
            ],
            'roles' => $roles,
            'aiByDept' => $aiByDept,
            'aiDeptTotal' => $aiTotal,
            'topAiDept' => $topDept,
            'deptPeople' => $deptPeople,
            'deptExpenses' => $deptExpenses,
            'expenseYear' => $expenseYear,
            'expenseMonthLabel' => $expenseMonthLabel,
            'expenseYearTotal' => $expenseYearTotal,
            'expenseMonthTotal' => $expenseMonthTotal,
            'expenseUnassignedYear' => $unassignedYear,
            'expenseUnassignedMonth' => $unassignedMonth,
            'topExpenseDept' => $topExpenseDept,
        ]);
    }
}
