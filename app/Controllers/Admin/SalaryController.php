<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use App\Models\User;
use Database;

final class SalaryController extends Controller
{
    private const STATUSES = [
        'paid' => 'Paid',
        'pending' => 'Pending',
        'on_hold' => 'On Hold',
    ];

    private const PAGE_SIZE = 10;

    public function index(): void
    {
        $this->requireRole('admin', 'superadmin');
        ensure_faculty_salary_schema();
        ensure_professor_qualification_schema();
        $instId = (int)$this->user()['institution_id'];
        $filters = $this->filters();
        $faculty = $this->faculty($instId);
        $monthRows = $this->rowsForMonth($instId, $filters['month']);
        $suggestions = $this->suggestions($instId, $filters['month']);
        $existing = [];
        foreach ($monthRows as $row) {
            $existing[(int)$row['faculty_id']] = (int)$row['id'];
        }
        foreach ($faculty as &$member) {
            $id = (int)$member['id'];
            $member['suggested'] = $suggestions[$id] ?? null;
            $member['existing_id'] = $existing[$id] ?? null;
        }
        unset($member);

        $this->view('admin/salary', [
            'title' => 'Salary & Payroll',
            'active' => 'salary',
            'subtitle' => 'Manage faculty salaries, monthly payroll, payment status, and salary history',
            'rows' => $this->filterRows($monthRows, $filters),
            'summary' => $this->summary($instId, $filters['month'], $monthRows),
            'departments' => $this->departmentTotals($monthRows),
            'departmentOptions' => Department::forInstitution($instId),
            'faculty' => $faculty,
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'pageSize' => self::PAGE_SIZE,
            'returnTo' => $this->listPath($filters),
            'openPayroll' => (string)$this->get('payroll', '') === '1',
        ]);
    }

    public function history(): void
    {
        $this->requireRole('admin', 'superadmin');
        ensure_faculty_salary_schema();
        ensure_professor_qualification_schema();
        $instId = (int)$this->user()['institution_id'];
        $facultyId = (int)$this->get('faculty_id', 0);
        $faculty = $this->facultyInInstitution($facultyId, $instId, false);
        if (!$faculty) {
            $this->flash('error', 'Choose a faculty member.');
            $this->redirect('/admin/salary');
        }
        $rows = $this->rowsForFaculty($instId, $facultyId);
        $month = $this->cleanMonth((string)$this->get('month', '')) ?? date('Y-m');

        $this->view('admin/salary-history', [
            'title' => 'Salary History',
            'active' => 'salary',
            'subtitle' => (string)$faculty['full_name'],
            'faculty' => $this->facultyCard($faculty),
            'rows' => $rows,
            'statuses' => self::STATUSES,
            'returnTo' => '/admin/salary/history?faculty_id=' . $facultyId,
            'back' => '/admin/salary?month=' . rawurlencode($month),
        ]);
    }

    public function store(): void
    {
        $this->requireRole('admin', 'superadmin');
        ensure_faculty_salary_schema();
        $this->verifyCsrf();
        $action = (string)$this->post('action');
        match ($action) {
            'add_salary' => $this->addSalary(),
            'update_salary' => $this->updateSalary(),
            'delete_salary' => $this->deleteSalary(),
            'run_payroll' => $this->runPayroll(),
            default => $this->flash('error', 'Unknown salary action.'),
        };
        $back = (string)$this->post('return_to', '/admin/salary');
        if (!str_starts_with($back, '/admin/salary')) {
            $back = '/admin/salary';
        }
        $this->redirect($back);
    }

    private function addSalary(): void
    {
        $actor = $this->user();
        $instId = (int)$actor['institution_id'];
        $facultyId = (int)$this->post('faculty_user_id', 0);
        $month = $this->cleanMonth((string)$this->post('salary_month', ''));
        $amount = $this->money((string)$this->post('amount', ''));
        $status = (string)$this->post('status', 'pending');
        $date = $this->cleanDate((string)$this->post('payment_date', ''));
        if (!$this->facultyInInstitution($facultyId, $instId) || $month === null || !isset(self::STATUSES[$status])) {
            $this->flash('error', 'Faculty, salary month, and payment status are required.');
            return;
        }
        if ($amount === null || $amount <= 0) {
            $this->flash('error', 'Enter a salary amount greater than zero.');
            return;
        }
        if ($status === 'paid' && $date === null) {
            $this->flash('error', 'Enter the payment date when the salary is marked Paid.');
            return;
        }
        if ($this->findMonth($facultyId, $month) !== null) {
            $this->flash('error', 'This faculty member already has a salary record for ' . $this->monthLabel($month . '-01') . '.');
            return;
        }
        Database::insert('faculty_salaries', [
            'institution_id' => $instId,
            'faculty_user_id' => $facultyId,
            'salary_month' => $month . '-01',
            'amount' => $amount,
            'payment_date' => $date,
            'status' => $status,
            'created_by' => (int)$actor['id'],
        ]);
        $message = 'Salary record added for ' . $this->monthLabel($month . '-01') . '.';
        if ($status === 'paid') {
            $this->notifyPaid($facultyId, $month, $amount, $date);
            $message .= ' The faculty member was notified.';
        }
        $this->flash('success', $message);
    }

    private function updateSalary(): void
    {
        $instId = (int)$this->user()['institution_id'];
        $row = $this->salaryInInstitution((int)$this->post('salary_id', 0), $instId);
        $month = $this->cleanMonth((string)$this->post('salary_month', ''));
        $amount = $this->money((string)$this->post('amount', ''));
        $status = (string)$this->post('status', '');
        $date = $this->cleanDate((string)$this->post('payment_date', ''));
        if (!$row || $month === null || !isset(self::STATUSES[$status])) {
            $this->flash('error', 'Choose a salary record, month, and status.');
            return;
        }
        if ($amount === null || $amount <= 0) {
            $this->flash('error', 'Enter a salary amount greater than zero.');
            return;
        }
        if ($status === 'paid' && $date === null) {
            $this->flash('error', 'Enter the payment date when the salary is marked Paid.');
            return;
        }
        $other = $this->findMonth((int)$row['faculty_user_id'], $month);
        if ($other !== null && (int)$other['id'] !== (int)$row['id']) {
            $this->flash('error', 'This faculty member already has a salary record for ' . $this->monthLabel($month . '-01') . '.');
            return;
        }
        Database::update('faculty_salaries', [
            'salary_month' => $month . '-01',
            'amount' => $amount,
            'payment_date' => $date,
            'status' => $status,
        ], 'id = :id AND institution_id = :institution_id', [
            'id' => (int)$row['id'],
            'institution_id' => $instId,
        ]);
        $message = 'Salary record updated.';
        if ($status === 'paid' && (string)$row['status'] !== 'paid') {
            $this->notifyPaid((int)$row['faculty_user_id'], $month, $amount, $date);
            $message .= ' The faculty member was notified.';
        }
        $this->flash('success', $message);
    }

    private function deleteSalary(): void
    {
        $instId = (int)$this->user()['institution_id'];
        $row = $this->salaryInInstitution((int)$this->post('salary_id', 0), $instId);
        if (!$row) {
            $this->flash('error', 'That salary record was not found.');
            return;
        }
        Database::query(
            'DELETE FROM faculty_salaries WHERE id = :id AND institution_id = :institution_id',
            ['id' => (int)$row['id'], 'institution_id' => $instId]
        );
        $this->flash('success', 'Salary record deleted. The faculty profile was not changed.');
    }

    private function runPayroll(): void
    {
        $actor = $this->user();
        $instId = (int)$actor['institution_id'];
        $month = $this->cleanMonth((string)$this->post('salary_month', ''));
        if ($month === null) {
            $this->flash('error', 'Choose a salary month.');
            return;
        }
        $include = $this->post('include', []);
        $amounts = $this->post('amount', []);
        $statuses = $this->post('status', []);
        if (!is_array($include) || $include === []) {
            $this->flash('error', 'Select at least one faculty member.');
            return;
        }
        if (!is_array($amounts)) {
            $amounts = [];
        }
        if (!is_array($statuses)) {
            $statuses = [];
        }
        $payDate = $this->cleanDate((string)$this->post('payment_date', ''));
        $plans = [];
        $needsDate = false;
        foreach ($include as $rawId) {
            $facultyId = (int)$rawId;
            if (!$this->facultyInInstitution($facultyId, $instId)) {
                continue;
            }
            $amount = $this->money((string)($amounts[$facultyId] ?? $amounts[(string)$facultyId] ?? ''));
            $status = (string)($statuses[$facultyId] ?? $statuses[(string)$facultyId] ?? 'pending');
            if (!isset(self::STATUSES[$status])) {
                $status = 'pending';
            }
            if ($amount === null || $amount <= 0) {
                $this->flash('error', 'Enter a salary amount greater than zero for each selected faculty member.');
                return;
            }
            if ($status === 'paid') {
                $needsDate = true;
            }
            $plans[] = [
                'faculty_id' => $facultyId,
                'amount' => $amount,
                'status' => $status,
            ];
        }
        if ($plans === []) {
            $this->flash('error', 'Select at least one faculty member.');
            return;
        }
        if ($needsDate && $payDate === null) {
            $this->flash('error', 'Enter the payment date for salaries marked Paid.');
            return;
        }
        $created = 0;
        $skipped = 0;
        $notified = 0;
        foreach ($plans as $plan) {
            if ($this->findMonth($plan['faculty_id'], $month) !== null) {
                $skipped++;
                continue;
            }
            Database::insert('faculty_salaries', [
                'institution_id' => $instId,
                'faculty_user_id' => $plan['faculty_id'],
                'salary_month' => $month . '-01',
                'amount' => $plan['amount'],
                'payment_date' => $plan['status'] === 'paid' ? $payDate : null,
                'status' => $plan['status'],
                'created_by' => (int)$actor['id'],
            ]);
            $created++;
            if ($plan['status'] === 'paid') {
                $this->notifyPaid($plan['faculty_id'], $month, $plan['amount'], $payDate);
                $notified++;
            }
        }
        $label = $this->monthLabel($month . '-01');
        if ($created === 0) {
            $this->flash('error', 'No new salary records were created for ' . $label . '. Existing months were left unchanged.');
            return;
        }
        $message = 'Payroll created for ' . $created . ' faculty in ' . $label . '.';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' already had a record and were skipped.';
        }
        if ($notified > 0) {
            $message .= ' ' . $notified . ' ' . ($notified === 1 ? 'faculty member was' : 'faculty members were') . ' notified.';
        }
        $this->flash('success', $message);
    }

    /**
     * Salary credited notice for the faculty member, through the existing
     * notification infrastructure. Only fires when a record becomes Paid.
     */
    private function notifyPaid(int $facultyId, string $month, float $amount, ?string $paymentDate): void
    {
        $body = 'Your salary of ' . fee_money($amount) . ' for ' . $this->monthLabel($month . '-01') . ' has been paid.';
        if ($paymentDate !== null) {
            $body .= ' Payment date: ' . $this->dateLabel($paymentDate) . '.';
        }
        notify_user(
            $facultyId,
            'system',
            'Salary Credited',
            $body,
            '/professor/notifications',
            ['priority' => 'medium', 'category' => 'system']
        );
    }

    /** @return array{month:string,department_id:int,status:string,faculty_id:int,q:string} */
    private function filters(): array
    {
        $status = (string)$this->get('status', '');
        if (!isset(self::STATUSES[$status])) {
            $status = '';
        }
        return [
            'month' => $this->cleanMonth((string)$this->get('month', '')) ?? date('Y-m'),
            'department_id' => max(0, (int)$this->get('department_id', 0)),
            'status' => $status,
            'faculty_id' => max(0, (int)$this->get('faculty_id', 0)),
            'q' => trim((string)$this->get('q', '')),
        ];
    }

    /** @param array{month:string,department_id:int,status:string,faculty_id:int,q:string} $filters */
    private function listPath(array $filters): string
    {
        $query = array_filter([
            'month' => $filters['month'],
            'department_id' => $filters['department_id'] > 0 ? (string)$filters['department_id'] : '',
            'status' => $filters['status'],
            'faculty_id' => $filters['faculty_id'] > 0 ? (string)$filters['faculty_id'] : '',
            'q' => $filters['q'],
        ], static fn($value) => $value !== '');
        return '/admin/salary' . ($query ? '?' . http_build_query($query) : '');
    }

    /** @return list<array<string,mixed>> */
    private function faculty(int $instId): array
    {
        $out = [];
        foreach (User::forInstitution($instId, ['role' => 'professor', 'is_active' => 1]) as $member) {
            $out[] = $this->facultyCard($member);
        }
        usort($out, static fn(array $a, array $b): int => strcasecmp((string)$a['name'], (string)$b['name']));
        return $out;
    }

    /** @param array<string,mixed> $member */
    private function facultyCard(array $member): array
    {
        $name = (string)($member['full_name'] ?? '');
        return [
            'id' => (int)$member['id'],
            'name' => $name,
            'initials' => $this->initials($name),
            'employee_id' => trim((string)($member['employee_id'] ?? '')),
            'qualification' => trim((string)($member['qualification'] ?? '')),
            'dept_id' => (int)($member['department_id'] ?? 0),
            'dept_name' => (string)($member['dept_name'] ?? ''),
            'dept_code' => (string)($member['dept_code'] ?? ''),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function rowsForMonth(int $instId, string $month): array
    {
        $rows = Database::fetchAll(
            'SELECT s.*, u.full_name, u.employee_id, u.qualification, u.department_id,
                    d.name AS dept_name, d.code AS dept_code
             FROM faculty_salaries s
             JOIN users u ON u.id = s.faculty_user_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE s.institution_id = :institution_id AND s.salary_month = :salary_month
             ORDER BY u.full_name',
            ['institution_id' => $instId, 'salary_month' => $month . '-01']
        );
        return array_map(fn(array $row): array => $this->present($row), $rows);
    }

    /** @return list<array<string,mixed>> */
    private function rowsForFaculty(int $instId, int $facultyId): array
    {
        $rows = Database::fetchAll(
            'SELECT s.*, u.full_name, u.employee_id, u.qualification, u.department_id,
                    d.name AS dept_name, d.code AS dept_code
             FROM faculty_salaries s
             JOIN users u ON u.id = s.faculty_user_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE s.institution_id = :institution_id AND s.faculty_user_id = :faculty_user_id
             ORDER BY s.salary_month DESC',
            ['institution_id' => $instId, 'faculty_user_id' => $facultyId]
        );
        return array_map(fn(array $row): array => $this->present($row), $rows);
    }

    /** @param array<string,mixed> $row */
    private function present(array $row): array
    {
        $name = (string)($row['full_name'] ?? '');
        $date = $row['payment_date'] !== null ? (string)$row['payment_date'] : '';
        $monthDate = (string)$row['salary_month'];
        return [
            'id' => (int)$row['id'],
            'faculty_id' => (int)$row['faculty_user_id'],
            'name' => $name,
            'initials' => $this->initials($name),
            'employee_id' => trim((string)($row['employee_id'] ?? '')),
            'qualification' => trim((string)($row['qualification'] ?? '')),
            'dept_id' => (int)($row['department_id'] ?? 0),
            'dept_name' => (string)($row['dept_name'] ?? ''),
            'dept_code' => (string)($row['dept_code'] ?? ''),
            'amount' => (float)$row['amount'],
            'month' => substr($monthDate, 0, 7),
            'month_label' => $this->monthLabel($monthDate),
            'payment_date' => $date !== '' ? $date : null,
            'payment_label' => $date !== '' ? $this->dateLabel($date) : '—',
            'status' => (string)$row['status'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{month:string,department_id:int,status:string,faculty_id:int,q:string} $filters
     * @return list<array<string,mixed>>
     */
    private function filterRows(array $rows, array $filters): array
    {
        $q = mb_strtolower($filters['q']);
        return array_values(array_filter($rows, static function (array $row) use ($filters, $q): bool {
            if ($filters['department_id'] > 0 && $row['dept_id'] !== $filters['department_id']) {
                return false;
            }
            if ($filters['status'] !== '' && $row['status'] !== $filters['status']) {
                return false;
            }
            if ($filters['faculty_id'] > 0 && $row['faculty_id'] !== $filters['faculty_id']) {
                return false;
            }
            if ($q === '') {
                return true;
            }
            $hay = mb_strtolower($row['name'] . ' ' . $row['employee_id']);
            return str_contains($hay, $q);
        }));
    }

    /**
     * @param list<array<string,mixed>> $monthRows
     * @return array{total:float,processed:int,waiting:int,ytd:float,ytd_label:string}
     */
    private function summary(int $instId, string $month, array $monthRows): array
    {
        $total = 0.0;
        $processed = 0;
        $waiting = 0;
        foreach ($monthRows as $row) {
            $total += (float)$row['amount'];
            if ($row['status'] === 'paid') {
                $processed++;
            } else {
                $waiting++;
            }
        }
        $window = $this->academicWindow($month);
        $ytd = Database::fetch(
            'SELECT COALESCE(SUM(amount), 0) AS total
             FROM faculty_salaries
             WHERE institution_id = :institution_id
               AND status = "paid"
               AND salary_month >= :start_month
               AND salary_month <= :end_month',
            [
                'institution_id' => $instId,
                'start_month' => $window['start'],
                'end_month' => $window['end'],
            ]
        );
        return [
            'total' => $total,
            'processed' => $processed,
            'waiting' => $waiting,
            'ytd' => (float)($ytd['total'] ?? 0),
            'ytd_label' => $window['label'],
        ];
    }

    /** @return array{start:string,end:string,label:string} */
    private function academicWindow(string $month): array
    {
        [$year, $part] = array_map('intval', explode('-', $month));
        $startYear = $part >= 6 ? $year : $year - 1;
        $start = sprintf('%04d-06-01', $startYear);
        $end = $month . '-01';
        if ($end < $start) {
            $end = $start;
        }
        $label = sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
        $startLabel = $this->monthLabel($start);
        $endLabel = $this->monthLabel($end);
        return [
            'start' => $start,
            'end' => $end,
            'label' => $startLabel . ' – ' . $endLabel . ' · ' . $label,
        ];
    }

    /**
     * @param list<array<string,mixed>> $monthRows
     * @return list<array{label:string,total:float,width:float}>
     */
    private function departmentTotals(array $monthRows): array
    {
        $groups = [];
        foreach ($monthRows as $row) {
            $key = (int)$row['dept_id'];
            $label = $row['dept_code'] !== '' ? (string)$row['dept_code'] : ((string)$row['dept_name'] !== '' ? (string)$row['dept_name'] : 'Unassigned');
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $label, 'total' => 0.0];
            }
            $groups[$key]['total'] += (float)$row['amount'];
        }
        $rows = array_values($groups);
        usort($rows, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);
        $max = 0.0;
        foreach ($rows as $row) {
            $max = max($max, $row['total']);
        }
        foreach ($rows as &$row) {
            $row['width'] = $max > 0 ? round(($row['total'] / $max) * 100, 1) : 0;
        }
        unset($row);
        return $rows;
    }

    /** @return array<int,float> */
    private function suggestions(int $instId, string $month): array
    {
        $rows = Database::fetchAll(
            'SELECT faculty_user_id, amount
             FROM faculty_salaries
             WHERE institution_id = :institution_id AND salary_month < :salary_month
             ORDER BY salary_month DESC, id DESC',
            ['institution_id' => $instId, 'salary_month' => $month . '-01']
        );
        $out = [];
        foreach ($rows as $row) {
            $id = (int)$row['faculty_user_id'];
            if (!isset($out[$id])) {
                $out[$id] = (float)$row['amount'];
            }
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    private function facultyInInstitution(int $id, int $instId, bool $activeOnly = true): ?array
    {
        if ($id < 1) {
            return null;
        }
        $sql = 'SELECT u.*, d.name AS dept_name, d.code AS dept_code
                FROM users u
                LEFT JOIN departments d ON d.id = u.department_id
                WHERE u.id = :id AND u.institution_id = :institution_id AND u.role = "professor"';
        $params = ['id' => $id, 'institution_id' => $instId];
        if ($activeOnly) {
            $sql .= ' AND u.is_active = 1';
        }
        return Database::fetch($sql, $params);
    }

    /** @return array<string,mixed>|null */
    private function salaryInInstitution(int $id, int $instId): ?array
    {
        if ($id < 1) {
            return null;
        }
        return Database::fetch(
            'SELECT * FROM faculty_salaries WHERE id = :id AND institution_id = :institution_id',
            ['id' => $id, 'institution_id' => $instId]
        );
    }

    /** @return array<string,mixed>|null */
    private function findMonth(int $facultyId, string $month): ?array
    {
        return Database::fetch(
            'SELECT id FROM faculty_salaries WHERE faculty_user_id = :faculty_user_id AND salary_month = :salary_month',
            ['faculty_user_id' => $facultyId, 'salary_month' => $month . '-01']
        );
    }

    private function cleanMonth(string $value): ?string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', trim($value), $match)) {
            return null;
        }
        $year = (int)$match[1];
        $month = (int)$match[2];
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            return null;
        }
        return sprintf('%04d-%02d', $year, $month);
    }

    private function cleanDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            return null;
        }
        return $value;
    }

    private function money(string $raw): ?float
    {
        $raw = trim(str_replace(['₹', ',', ' '], '', $raw));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        return round((float)$raw, 2);
    }

    private function monthLabel(string $ymd): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', substr($ymd, 0, 10));
        return $dt ? $dt->format('F Y') : $ymd;
    }

    private function dateLabel(string $ymd): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);
        return $dt ? $dt->format('d-m-Y') : $ymd;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }
        return $letters !== '' ? $letters : 'F';
    }
}
