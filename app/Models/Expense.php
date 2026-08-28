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

    public static function totalForMonth(int $institutionId, int $year, int $month): float
    {
        $row = Database::fetch(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM expenses
             WHERE institution_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?',
            [$institutionId, $year, $month]
        );
        return (float)($row['total'] ?? 0);
    }
}
