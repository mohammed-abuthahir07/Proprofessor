<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class Expense extends Model
{
    protected static string $table = 'expenses';

    public static function forInstitution(int $institutionId, int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT e.*, d.name AS dept_name FROM expenses e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.institution_id = ?
             ORDER BY e.expense_date DESC
             LIMIT ' . (int)$limit,
            [$institutionId]
        );
    }

    public static function totalsByCategory(int $institutionId): array
    {
        return Database::fetchAll(
            'SELECT category, SUM(amount) total FROM expenses WHERE institution_id = ? GROUP BY category',
            [$institutionId]
        );
    }
}
