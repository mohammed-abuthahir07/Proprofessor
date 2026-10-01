<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Expense;
use Database;

/**
 * Read-only roll-up of Fee Collection, Expense, and Salary & Payroll.
 * Nothing here writes: each module stays the only place to edit its records.
 */
final class FinanceOverviewController extends Controller
{
    private const FEE_LABELS = [
        'tuition' => 'Tuition Fees',
        'bus' => 'Bus / Transport Fees',
        'hostel' => 'Hostel Fees',
    ];

    public function index(): void
    {
        require_admin_perm('manage_finance');
        $instId = (int)$this->user()['institution_id'];
        ensure_fee_collection_schema();
        ensure_faculty_salary_schema();

        $tz = new \DateTimeZone('Asia/Kolkata');
        $today = new \DateTime('today', $tz);
        $academicYear = institution_academic_year($instId);
        $window = $this->window($academicYear, $today);

        $fees = $this->feeTotals($instId, $academicYear);
        $revenue = $this->revenueByType($instId, $academicYear);
        $expenses = $this->expenseByCategory($instId, $window);
        $payroll = $this->payroll($instId, $window);

        $expenditure = $payroll['paid'];
        foreach ($expenses as $row) {
            $expenditure += (float)$row['spent'];
        }

        $this->view('admin/finance-overview', [
            'title' => 'Finance Overview',
            'active' => 'finance_overview',
            'subtitle' => ($academicYear !== '' ? 'Academic Year ' . $academicYear . ' · ' : '') . 'All figures in INR',
            'dashboardHero' => true,
            'academicYear' => $academicYear,
            'window' => $window,
            'summary' => [
                'revenue' => $fees['collected'],
                'billed' => $fees['billed'],
                'expenditure' => $expenditure,
                'arrears' => $fees['pending'],
                'arrears_students' => $fees['overdue'],
            ],
            'months' => $this->monthlyCollection($instId, $academicYear, $window, $fees['billed']),
            'revenueBreakdown' => $revenue,
            'yearArchives' => $this->yearArchives($instId),
            'payroll' => $payroll,
        ]);
    }

    /**
     * Academic year runs June → May, matching the Salary & Payroll YTD window.
     *
     * @return array{start:string,end:string,label:string,months:list<array{key:string,label:string}>}
     */
    private function window(string $academicYear, \DateTime $today): array
    {
        $startYear = (int)$today->format('Y');
        if ((int)$today->format('n') < 6) {
            $startYear--;
        }
        if (preg_match('/^(\d{4})/', $academicYear, $match) === 1) {
            $startYear = (int)$match[1];
        }
        $start = new \DateTime(sprintf('%04d-06-01', $startYear), $today->getTimezone());
        $end = (clone $start)->modify('+11 months');
        $cursor = clone $start;
        $last = $today < $end ? $today : $end;
        $months = [];
        while ($cursor <= $last) {
            $months[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M'),
            ];
            $cursor->modify('+1 month');
        }
        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-t'),
            'label' => $start->format('M Y') . ' – ' . $last->format('M Y'),
            'months' => $months,
        ];
    }

    /** @return array{collected:float,billed:float,pending:float,overdue:int} */
    private function feeTotals(int $instId, string $academicYear): array
    {
        $empty = ['collected' => 0.0, 'billed' => 0.0, 'pending' => 0.0, 'overdue' => 0];
        if ($academicYear === '') {
            return $empty;
        }
        $row = Database::fetch(
            'SELECT COALESCE(SUM(f.total_amount), 0) AS billed,
                    COALESCE(SUM(COALESCE(p.paid, 0)), 0) AS collected,
                    COALESCE(SUM(GREATEST(f.total_amount - COALESCE(p.paid, 0), 0)), 0) AS pending,
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
            'billed' => (float)$row['billed'],
            'pending' => (float)$row['pending'],
            'overdue' => (int)$row['overdue'],
        ];
    }

    /** @return list<array{key:string,label:string,collected:float,pending:float,share:?float}> */
    private function revenueByType(int $instId, string $academicYear): array
    {
        if ($academicYear === '') {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT f.fee_type,
                    COALESCE(SUM(COALESCE(p.paid, 0)), 0) AS collected,
                    COALESCE(SUM(GREATEST(f.total_amount - COALESCE(p.paid, 0), 0)), 0) AS pending
             FROM fee_records f
             LEFT JOIN (
                 SELECT fee_record_id, SUM(amount) AS paid FROM fee_payments GROUP BY fee_record_id
             ) p ON p.fee_record_id = f.id
             WHERE f.institution_id = ? AND f.academic_year = ?
             GROUP BY f.fee_type',
            [$instId, $academicYear]
        );
        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float)$row['collected'];
        }
        $out = [];
        foreach ($rows as $row) {
            $type = (string)$row['fee_type'];
            $collected = (float)$row['collected'];
            $out[] = [
                'key' => $type,
                'label' => self::FEE_LABELS[$type] ?? ucfirst($type) . ' Fees',
                'collected' => $collected,
                'pending' => (float)$row['pending'],
                'share' => $total > 0 ? round($collected * 100 / $total, 1) : null,
            ];
        }
        usort($out, static fn(array $a, array $b): int => $b['collected'] <=> $a['collected']);
        return $out;
    }

    /**
     * @param array{start:string,end:string} $window
     * @return list<array{category:string,spent:float,entries:int}>
     */
    private function expenseByCategory(int $instId, array $window): array
    {
        $rows = Database::fetchAll(
            'SELECT category, SUM(amount) AS spent, COUNT(*) AS entries
             FROM expenses
             WHERE institution_id = ? AND expense_date BETWEEN ? AND ?
             GROUP BY category
             ORDER BY spent DESC, category ASC',
            [$instId, $window['start'], $window['end']]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'category' => trim((string)$row['category']) !== '' ? (string)$row['category'] : 'Uncategorised',
                'spent' => (float)$row['spent'],
                'entries' => (int)$row['entries'],
            ];
        }
        return $out;
    }

    /**
     * @param array{start:string,end:string} $window
     * @return array{paid:float,outstanding:float,records:int}
     */
    private function payroll(int $instId, array $window): array
    {
        $row = Database::fetch(
            'SELECT COALESCE(SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN status <> "paid" THEN amount ELSE 0 END), 0) AS outstanding,
                    COUNT(*) AS records
             FROM faculty_salaries
             WHERE institution_id = ? AND salary_month BETWEEN ? AND ?',
            [$instId, $window['start'], $window['end']]
        );
        return [
            'paid' => (float)($row['paid'] ?? 0),
            'outstanding' => (float)($row['outstanding'] ?? 0),
            'records' => (int)($row['records'] ?? 0),
        ];
    }

    /**
     * Same year blocks the Expense page builds, read through the Expense model.
     *
     * @return list<array{year:int,stored:bool,total:float,months:array<int,array{total:float,entries:int}>,topCategoryName:string,topCategoryTotal:float}>
     */
    private function yearArchives(int $instId): array
    {
        $thisYear = (int)date('Y');
        $storedYears = Expense::yearsWithExpenses($instId);
        $years = $storedYears;
        if (!in_array($thisYear, $years, true)) {
            array_unshift($years, $thisYear);
        }
        $out = [];
        foreach ($years as $year) {
            $year = (int)$year;
            $top = Expense::topCategoryForYear($instId, $year);
            $out[] = [
                'year' => $year,
                'stored' => in_array($year, $storedYears, true),
                'total' => Expense::totalForYear($instId, $year),
                'months' => Expense::totalsByMonthForYear($instId, $year),
                'topCategoryName' => (string)($top['category'] ?? ''),
                'topCategoryTotal' => (float)($top['total'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Target is the year's billed fees spread evenly across the window. It is a
     * display reference only — no target is stored anywhere in the project.
     *
     * @param array{months:list<array{key:string,label:string}>} $window
     * @return array{target:float,target_percent:float,months:int,rows:list<array{label:string,collected:float,percent:float}>}
     */
    private function monthlyCollection(int $instId, string $academicYear, array $window, float $billed): array
    {
        $byMonth = [];
        if ($academicYear !== '') {
            foreach (Database::fetchAll(
                'SELECT DATE_FORMAT(p.payment_date, "%Y-%m") AS ym, SUM(p.amount) AS total
                 FROM fee_payments p
                 JOIN fee_records f ON f.id = p.fee_record_id
                 WHERE f.institution_id = ? AND f.academic_year = ?
                 GROUP BY ym',
                [$instId, $academicYear]
            ) as $row) {
                $byMonth[(string)$row['ym']] = (float)$row['total'];
            }
        }
        $count = max(1, count($window['months']));
        $target = $billed > 0 ? round($billed / 12, 2) : 0.0;
        $peak = $target;
        foreach ($window['months'] as $month) {
            $peak = max($peak, $byMonth[$month['key']] ?? 0.0);
        }
        $rows = [];
        foreach ($window['months'] as $month) {
            $collected = $byMonth[$month['key']] ?? 0.0;
            $rows[] = [
                'label' => $month['label'],
                'collected' => $collected,
                'percent' => $peak > 0 ? round($collected * 100 / $peak, 1) : 0.0,
            ];
        }
        return [
            'target' => $target,
            'target_percent' => $peak > 0 ? round($target * 100 / $peak, 1) : 0.0,
            'rows' => $rows,
            'months' => $count,
        ];
    }
}
