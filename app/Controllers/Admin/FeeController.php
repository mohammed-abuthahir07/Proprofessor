<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use App\Models\User;
use Database;

final class FeeController extends Controller
{
    private const TYPES = [
        'tuition' => 'Tuition Fee',
        'bus' => 'College Bus Fee',
        'hostel' => 'Hostel Fee',
    ];

    private const CHIP = [
        'tuition' => 'Tuition',
        'bus' => 'Bus',
        'hostel' => 'Hostel',
    ];

    public function index(): void
    {
        $this->requireRole('admin', 'superadmin');
        ensure_fee_collection_schema();
        $instId = (int)$this->user()['institution_id'];
        $filters = $this->filters();
        $years = $this->academicYears($instId);
        if ($filters['academic_year'] === '' && $years !== []) {
            $filters['academic_year'] = $years[0];
        }
        $built = $this->build($instId, $filters['academic_year']);
        $rows = $this->filterRows($built['rows'], $filters);

        $this->view('admin/fees', [
            'title' => 'Fee Collection',
            'active' => 'fees',
            'subtitle' => 'Manage student fees, payments and reminders',
            'rows' => $rows,
            'summary' => $built['summary'],
            'departments' => Department::forInstitution($instId),
            'students' => $built['students'],
            'years' => $years,
            'filters' => $filters,
            'types' => self::TYPES,
            'pageSize' => 10,
        ]);
    }

    public function student(): void
    {
        $this->requireRole('admin', 'superadmin');
        ensure_fee_collection_schema();
        $instId = (int)$this->user()['institution_id'];
        $studentId = (int)$this->get('student_id', 0);
        $year = $this->cleanYear((string)$this->get('academic_year', ''));
        $student = $this->studentInInstitution($studentId, $instId);
        if (!$student || $year === null) {
            $this->flash('error', 'Choose a student and academic year.');
            $this->redirect('/admin/fee-collection');
        }
        $fees = $this->feesFor($instId, $studentId, $year);
        $outstanding = array_values(array_filter($fees, static fn(array $fee): bool => $fee['status'] !== 'paid'));

        $this->view('admin/fee-student', [
            'title' => 'Student Fee Details',
            'active' => 'fees',
            'subtitle' => (string)$student['full_name'] . ' · ' . $year,
            'student' => $student,
            'academicYear' => $year,
            'fees' => $fees,
            'overall' => $this->overall($fees),
            'outstanding' => $outstanding,
            'years' => $this->academicYears($instId),
            'types' => self::TYPES,
        ]);
    }

    public function store(): void
    {
        $this->requireRole('admin', 'superadmin');
        ensure_fee_collection_schema();
        $this->verifyCsrf();
        $action = (string)$this->post('action');
        match ($action) {
            'add_fee' => $this->addFee(),
            'update_fee' => $this->updateFee(),
            'delete_fee' => $this->deleteFee(),
            'record_payment' => $this->recordPayment(),
            'remind' => $this->remind(false),
            'remind_all' => $this->remind(true),
            default => $this->flash('error', 'Unknown fee action.'),
        };
        $back = (string)$this->post('return_to', '/admin/fee-collection');
        if (!str_starts_with($back, '/admin/fee-collection')) {
            $back = '/admin/fee-collection';
        }
        $this->redirect($back);
    }

    private function addFee(): void
    {
        $actor = $this->user();
        $instId = (int)$actor['institution_id'];
        $studentId = (int)$this->post('student_id', 0);
        $year = $this->cleanYear((string)$this->post('academic_year', ''));
        $type = (string)$this->post('fee_type', '');
        $amount = $this->money((string)$this->post('total_amount', ''));
        $due = $this->cleanDate((string)$this->post('due_date', ''));
        $notes = trim((string)$this->post('notes', ''));
        if (!$this->studentInInstitution($studentId, $instId) || $year === null || !isset(self::TYPES[$type])) {
            $this->flash('error', 'Student, academic year, and fee type are required.');
            return;
        }
        if ($amount === null || $amount <= 0) {
            $this->flash('error', 'Enter a total amount greater than zero.');
            return;
        }
        $existing = Database::fetch(
            'SELECT id FROM fee_records WHERE student_id = ? AND academic_year = ? AND fee_type = ?',
            [$studentId, $year, $type]
        );
        if ($existing) {
            $this->flash('error', 'This student already has a ' . self::TYPES[$type] . ' record for ' . $year . '.');
            return;
        }
        Database::insert('fee_records', [
            'institution_id' => $instId,
            'student_id' => $studentId,
            'academic_year' => $year,
            'fee_type' => $type,
            'total_amount' => $amount,
            'due_date' => $due,
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => (int)$actor['id'],
        ]);
        $this->flash('success', self::TYPES[$type] . ' added for ' . $year . '.');
    }

    private function updateFee(): void
    {
        $fee = $this->ownedFee((int)$this->post('fee_id', 0));
        if (!$fee) {
            $this->flash('error', 'Fee record not found.');
            return;
        }
        $amount = $this->money((string)$this->post('total_amount', ''));
        $due = $this->cleanDate((string)$this->post('due_date', ''));
        $notes = trim((string)$this->post('notes', ''));
        if ($amount === null || $amount <= 0) {
            $this->flash('error', 'Enter a total amount greater than zero.');
            return;
        }
        $paid = $this->paidAmount((int)$fee['id']);
        if ($amount + 0.001 < $paid) {
            $this->flash('error', 'Total amount cannot be less than the ' . fee_money($paid) . ' already recorded.');
            return;
        }
        Database::update('fee_records', [
            'total_amount' => $amount,
            'due_date' => $due,
            'notes' => $notes !== '' ? $notes : null,
        ], 'id = :id', ['id' => (int)$fee['id']]);
        $this->flash('success', 'Fee record updated.');
    }

    private function deleteFee(): void
    {
        $fee = $this->ownedFee((int)$this->post('fee_id', 0));
        if (!$fee) {
            $this->flash('error', 'Fee record not found.');
            return;
        }
        Database::query('DELETE FROM fee_records WHERE id = ?', [(int)$fee['id']]);
        $this->flash('success', self::TYPES[(string)$fee['fee_type']] . ' was removed. It is no longer an applicable fee.');
    }

    private function recordPayment(): void
    {
        $actor = $this->user();
        $fee = $this->ownedFee((int)$this->post('fee_id', 0));
        if (!$fee) {
            $this->flash('error', 'Fee record not found.');
            return;
        }
        $amount = $this->money((string)$this->post('amount', ''));
        $date = $this->cleanDate((string)$this->post('payment_date', ''));
        $reference = trim((string)$this->post('payment_reference', ''));
        $notes = trim((string)$this->post('notes', ''));
        if ($amount === null || $amount <= 0) {
            $this->flash('error', 'Enter a payment amount greater than zero.');
            return;
        }
        if ($date === null) {
            $this->flash('error', 'Select the date the student paid.');
            return;
        }
        $paid = $this->paidAmount((int)$fee['id']);
        $remaining = round((float)$fee['total_amount'] - $paid, 2);
        if ($amount - $remaining > 0.001) {
            $this->flash('error', 'Payment exceeds the remaining balance of ' . fee_money($remaining) . '.');
            return;
        }
        Database::insert('fee_payments', [
            'fee_record_id' => (int)$fee['id'],
            'amount' => $amount,
            'payment_date' => $date,
            'payment_reference' => $reference !== '' ? $reference : null,
            'notes' => $notes !== '' ? $notes : null,
            'recorded_by' => (int)$actor['id'],
        ]);
        $this->flash('success', 'Payment of ' . fee_money($amount) . ' recorded.');
    }

    private function remind(bool $all): void
    {
        $instId = (int)$this->user()['institution_id'];
        $studentId = (int)$this->post('student_id', 0);
        $year = $this->cleanYear((string)$this->post('academic_year', ''));
        $student = $this->studentInInstitution($studentId, $instId);
        if (!$student || $year === null) {
            $this->flash('error', 'Student not found for this reminder.');
            return;
        }
        $fees = $this->feesFor($instId, $studentId, $year);
        if (!$all) {
            $fee = $this->ownedFee((int)$this->post('fee_id', 0));
            $fees = $fee ? array_values(array_filter($fees, static fn(array $row): bool => (int)$row['id'] === (int)$fee['id'])) : [];
        }
        $due = array_values(array_filter($fees, static fn(array $fee): bool => $fee['status'] !== 'paid' && $fee['remaining'] > 0));
        if ($due === []) {
            $this->flash('error', 'There is no outstanding applicable fee to remind about.');
            return;
        }
        $lines = [];
        foreach ($due as $fee) {
            $lines[] = 'Your ' . self::TYPES[$fee['type']] . ' has an outstanding balance of ' . fee_money($fee['remaining']) . ' for the ' . $year . ' academic year.';
        }
        notify_user(
            $studentId,
            'system',
            'Fee Payment Reminder',
            implode("\n", $lines),
            '/student/notifications',
            ['priority' => 'medium', 'category' => 'system']
        );
        $this->flash('success', 'Reminder sent to ' . (string)$student['full_name'] . '.');
    }

    /** @return array{q:string,department_id:int,year:int,academic_year:string,status:string,fee_type:string} */
    private function filters(): array
    {
        $status = (string)$this->get('status', '');
        $type = (string)$this->get('fee_type', '');
        $year = $this->cleanYear((string)$this->get('academic_year', ''));
        $level = (int)$this->get('year', 0);
        return [
            'q' => trim((string)$this->get('q', '')),
            'department_id' => (int)$this->get('department_id', 0),
            'year' => ($level >= 1 && $level <= 4) ? $level : 0,
            'academic_year' => $year ?? '',
            'status' => in_array($status, ['paid', 'partial', 'pending', 'none'], true) ? $status : '',
            'fee_type' => isset(self::TYPES[$type]) ? $type : '',
        ];
    }

    /**
     * @param array{q:string,department_id:int,year:int,academic_year:string,status:string,fee_type:string} $filters
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function filterRows(array $rows, array $filters): array
    {
        $q = mb_strtolower($filters['q']);
        return array_values(array_filter($rows, static function (array $row) use ($filters, $q): bool {
            if ($filters['department_id'] > 0 && (int)$row['dept_id'] !== $filters['department_id']) {
                return false;
            }
            if ($filters['year'] > 0 && (int)$row['level'] !== $filters['year']) {
                return false;
            }
            if ($filters['status'] !== '' && $row['overall'] !== $filters['status']) {
                return false;
            }
            if ($filters['fee_type'] !== '') {
                $types = array_column($row['fees'], 'type');
                if (!in_array($filters['fee_type'], $types, true)) {
                    return false;
                }
            }
            if ($q === '') {
                return true;
            }
            $hay = mb_strtolower($row['name'] . ' ' . $row['register_no'] . ' ' . $row['email']);
            return str_contains($hay, $q);
        }));
    }

    /** @return array{rows:list<array<string,mixed>>,students:list<array<string,mixed>>,summary:array<string,mixed>} */
    private function build(int $instId, string $year): array
    {
        $people = User::forInstitution($instId, ['role' => 'student', 'is_active' => 1]);
        $feeMap = [];
        if ($year !== '') {
            foreach ($this->loadFees($instId, $year) as $fee) {
                $feeMap[(int)$fee['student_id']][] = $fee;
            }
        }
        $rows = [];
        $students = [];
        $withFees = 0;
        $fully = 0;
        $partial = 0;
        $pending = 0;
        $collected = 0.0;
        $outstanding = 0.0;
        foreach ($people as $person) {
            $id = (int)$person['id'];
            $level = (int)($person['academic_year_level'] ?? 0);
            if ($level < 1) {
                $level = (int)($person['class_year'] ?? 0);
            }
            $fees = $feeMap[$id] ?? [];
            $overall = $this->overall($fees);
            $paid = 0.0;
            $due = 0.0;
            foreach ($fees as $fee) {
                $paid += $fee['paid'];
                $due += $fee['remaining'];
            }
            if ($fees !== []) {
                $withFees++;
                $collected += $paid;
                $outstanding += $due;
                if ($overall === 'paid') {
                    $fully++;
                } elseif ($overall === 'partial') {
                    $partial++;
                } elseif ($overall === 'pending') {
                    $pending++;
                }
            }
            $students[] = [
                'id' => $id,
                'name' => (string)$person['full_name'],
                'register_no' => trim((string)($person['register_no'] ?? '')),
                'email' => (string)($person['email'] ?? ''),
            ];
            $rows[] = [
                'id' => $id,
                'name' => (string)$person['full_name'],
                'initials' => $this->initials((string)$person['full_name']),
                'register_no' => trim((string)($person['register_no'] ?? '')),
                'email' => (string)($person['email'] ?? ''),
                'dept_id' => (int)($person['department_id'] ?? 0),
                'dept' => (string)($person['dept_code'] ?? '') !== '' ? (string)$person['dept_code'] : (string)($person['dept_name'] ?? ''),
                'level' => $level,
                'year_label' => $level > 0 ? subject_year_label($level) : '',
                'fees' => $fees,
                'paid' => round($paid, 2),
                'pending' => round($due, 2),
                'overall' => $overall,
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        usort($students, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return [
            'rows' => $rows,
            'students' => $students,
            'summary' => [
                'students' => $withFees,
                'fully' => $fully,
                'partial' => $partial,
                'pending' => $pending,
                'collected' => round($collected, 2),
                'outstanding' => round($outstanding, 2),
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function feesFor(int $instId, int $studentId, string $year): array
    {
        return array_values(array_filter(
            $this->loadFees($instId, $year),
            static fn(array $fee): bool => (int)$fee['student_id'] === $studentId
        ));
    }

    /** @return list<array<string,mixed>> */
    private function loadFees(int $instId, string $year): array
    {
        $records = Database::fetchAll(
            'SELECT * FROM fee_records WHERE institution_id = ? AND academic_year = ? ORDER BY FIELD(fee_type, "tuition", "bus", "hostel"), id',
            [$instId, $year]
        );
        if ($records === []) {
            return [];
        }
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $records);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $payments = Database::fetchAll(
            "SELECT p.*, u.full_name AS recorder
             FROM fee_payments p
             LEFT JOIN users u ON u.id = p.recorded_by
             WHERE p.fee_record_id IN ($placeholders)
             ORDER BY p.payment_date, p.id",
            $ids
        );
        $byFee = [];
        foreach ($payments as $payment) {
            $byFee[(int)$payment['fee_record_id']][] = $payment;
        }
        $out = [];
        foreach ($records as $record) {
            $id = (int)$record['id'];
            $history = $byFee[$id] ?? [];
            $paid = 0.0;
            foreach ($history as $payment) {
                $paid += (float)$payment['amount'];
            }
            $paid = round($paid, 2);
            $total = round((float)$record['total_amount'], 2);
            $remaining = round(max(0, $total - $paid), 2);
            $type = (string)$record['fee_type'];
            $out[] = [
                'id' => $id,
                'student_id' => (int)$record['student_id'],
                'type' => $type,
                'label' => self::TYPES[$type] ?? $type,
                'chip' => self::CHIP[$type] ?? $type,
                'total' => $total,
                'paid' => $paid,
                'remaining' => $remaining,
                'status' => $this->feeStatus($total, $paid),
                'due_date' => (string)($record['due_date'] ?? ''),
                'notes' => (string)($record['notes'] ?? ''),
                'payments' => $history,
            ];
        }
        return $out;
    }

    /** @param list<array<string,mixed>> $fees */
    private function overall(array $fees): string
    {
        if ($fees === []) {
            return 'none';
        }
        $anyPay = false;
        $anyDue = false;
        foreach ($fees as $fee) {
            if ((float)$fee['paid'] > 0) {
                $anyPay = true;
            }
            if ((float)$fee['remaining'] > 0) {
                $anyDue = true;
            }
        }
        if (!$anyDue) {
            return 'paid';
        }
        return $anyPay ? 'partial' : 'pending';
    }

    private function feeStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'pending';
        }
        if ($paid + 0.001 < $total) {
            return 'partial';
        }
        return 'paid';
    }

    private function paidAmount(int $feeId): float
    {
        $row = Database::fetch('SELECT COALESCE(SUM(amount), 0) AS paid FROM fee_payments WHERE fee_record_id = ?', [$feeId]);
        return round((float)($row['paid'] ?? 0), 2);
    }

    private function ownedFee(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        return Database::fetch(
            'SELECT * FROM fee_records WHERE id = ? AND institution_id = ?',
            [$id, (int)$this->user()['institution_id']]
        );
    }

    private function studentInInstitution(int $id, int $instId): ?array
    {
        if ($id < 1) {
            return null;
        }
        $row = Database::fetch(
            'SELECT u.*, d.name AS dept_name, d.code AS dept_code, c.section AS class_section, c.year AS class_year
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN classes c ON c.id = u.class_id
             WHERE u.id = ? AND u.institution_id = ? AND u.role = "student"',
            [$id, $instId]
        );
        return $row ?: null;
    }

    /** @return list<string> */
    private function academicYears(int $instId): array
    {
        $years = [];
        $current = institution_academic_year($instId);
        if ($this->cleanYear($current) !== null) {
            $years[$current] = true;
            $prev = $this->shiftYear($current, -1);
            if ($prev !== null) {
                $years[$prev] = true;
            }
        }
        foreach (Database::fetchAll(
            'SELECT DISTINCT academic_year FROM fee_records WHERE institution_id = ? AND academic_year <> ""',
            [$instId]
        ) as $row) {
            $year = $this->cleanYear((string)$row['academic_year']);
            if ($year !== null) {
                $years[$year] = true;
            }
        }
        $list = array_keys($years);
        rsort($list, SORT_STRING);
        return $list;
    }

    private function shiftYear(string $year, int $delta): ?string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $year, $m)) {
            return null;
        }
        $start = (int)$m[1] + $delta;
        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }

    private function cleanYear(string $year): ?string
    {
        $year = trim($year);
        if (!preg_match('/^(\d{4})-(\d{2})$/', $year, $m)) {
            return null;
        }
        $end = ((int)$m[1] + 1) % 100;
        if ((int)$m[2] !== $end) {
            return null;
        }
        return $year;
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
        return $letters !== '' ? $letters : 'S';
    }
}
