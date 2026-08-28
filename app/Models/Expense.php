<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class Expense extends Model
{
    protected static string $table = 'expenses';

    public static function forInstitution(int $institutionId, int $limit = 100, ?int $year = null, ?int $month = null, int $offset = 0): array
    {
        $sql = 'SELECT e.*, d.name AS dept_name FROM expenses e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.institution_id = ?';
        $params = [$institutionId];
        if ($year !== null && $year > 0) {
            $sql .= ' AND YEAR(e.expense_date) = ?';
            $params[] = $year;
        }
        if ($month !== null && $month >= 1 && $month <= 12) {
            $sql .= ' AND MONTH(e.expense_date) = ?';
            $params[] = $month;
        }
        $sql .= ' ORDER BY e.expense_date DESC, e.id DESC LIMIT ' . (int)max(1, $limit)
            . ' OFFSET ' . (int)max(0, $offset);
        return Database::fetchAll($sql, $params);
    }

    public static function countForInstitution(int $institutionId, ?int $year = null, ?int $month = null): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM expenses e WHERE e.institution_id = ?';
        $params = [$institutionId];
        if ($year !== null && $year > 0) {
            $sql .= ' AND YEAR(e.expense_date) = ?';
            $params[] = $year;
        }
        if ($month !== null && $month >= 1 && $month <= 12) {
            $sql .= ' AND MONTH(e.expense_date) = ?';
            $params[] = $month;
        }
        $row = Database::fetch($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    public static function totalsByCategory(int $institutionId): array
    {
        return Database::fetchAll(
            'SELECT category, SUM(amount) total FROM expenses WHERE institution_id = ? GROUP BY category',
            [$institutionId]
        );
    }

    /** @return array{category: string, total: float}|null */
    public static function topCategoryForYear(int $institutionId, int $year): ?array
    {
        $row = Database::fetch(
            'SELECT category, SUM(amount) AS total FROM expenses
             WHERE institution_id = ? AND YEAR(expense_date) = ?
             GROUP BY category
             ORDER BY total DESC, category ASC
             LIMIT 1',
            [$institutionId, $year]
        );
        if (!$row || (string)($row['category'] ?? '') === '') {
            return null;
        }
        return [
            'category' => (string)$row['category'],
            'total' => (float)$row['total'],
        ];
    }

    public static function totalForYear(int $institutionId, int $year): float
    {
        $row = Database::fetch(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM expenses
             WHERE institution_id = ? AND YEAR(expense_date) = ?',
            [$institutionId, $year]
        );
        return (float)($row['total'] ?? 0);
    }

    /** @return list<int> */
    public static function yearsWithExpenses(int $institutionId): array
    {
        $rows = Database::fetchAll(
            'SELECT DISTINCT YEAR(expense_date) AS y FROM expenses
             WHERE institution_id = ?
             ORDER BY y DESC',
            [$institutionId]
        );
        $years = [];
        foreach ($rows as $row) {
            $year = (int)($row['y'] ?? 0);
            if ($year > 0) {
                $years[] = $year;
            }
        }
        return $years;
    }

    public static function totalForMonth(int $institutionId, int $year, int $month): float
    {
        $row = Database::fetch(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM expenses
             WHERE institution_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?',
            [$institutionId, $year, $month]
        );
        return (float)($row['total'] ?? 0);
    }

    /** @return list<array<string,mixed>> */
    public static function listForStatement(int $institutionId, int $year, ?int $month = null): array
    {
        $sql = 'SELECT e.*, d.name AS dept_name FROM expenses e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.institution_id = ? AND YEAR(e.expense_date) = ?';
        $params = [$institutionId, $year];
        if ($month !== null && $month >= 1 && $month <= 12) {
            $sql .= ' AND MONTH(e.expense_date) = ?';
            $params[] = $month;
        }
        $sql .= ' ORDER BY e.expense_date ASC, e.id ASC';
        return Database::fetchAll($sql, $params);
    }

    /** @return list<array{category:string,total:float|int}> */
    public static function totalsByCategoryForPeriod(int $institutionId, int $year, ?int $month = null): array
    {
        $sql = 'SELECT category, SUM(amount) AS total FROM expenses
             WHERE institution_id = ? AND YEAR(expense_date) = ?';
        $params = [$institutionId, $year];
        if ($month !== null && $month >= 1 && $month <= 12) {
            $sql .= ' AND MONTH(expense_date) = ?';
            $params[] = $month;
        }
        $sql .= ' GROUP BY category ORDER BY total DESC, category ASC';
        return Database::fetchAll($sql, $params);
    }

    /**
     * @return array<int, array{total:float,entries:int}>
     */
    public static function totalsByMonthForYear(int $institutionId, int $year): array
    {
        $rows = Database::fetchAll(
            'SELECT MONTH(expense_date) AS month_num, SUM(amount) AS total, COUNT(*) AS entries
             FROM expenses
             WHERE institution_id = ? AND YEAR(expense_date) = ?
             GROUP BY MONTH(expense_date)',
            [$institutionId, $year]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['month_num']] = [
                'total' => (float)$row['total'],
                'entries' => (int)$row['entries'],
            ];
        }
        $out = [];
        for ($month = 1; $month <= 12; $month++) {
            $out[$month] = $map[$month] ?? ['total' => 0.0, 'entries' => 0];
        }
        return $out;
    }

    /** @return array{category: string, total: float}|null */
    public static function topCategoryForPeriod(int $institutionId, int $year, ?int $month = null): ?array
    {
        $rows = self::totalsByCategoryForPeriod($institutionId, $year, $month);
        if (!$rows || (string)($rows[0]['category'] ?? '') === '') {
            return null;
        }
        return [
            'category' => (string)$rows[0]['category'],
            'total' => (float)$rows[0]['total'],
        ];
    }
}
