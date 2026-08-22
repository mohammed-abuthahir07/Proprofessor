<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class CoursePlan extends Model
{
    protected static string $table = 'course_plans';

    public static function forProfessor(int $professorId): array
    {
        return self::where('professor_id = ? ORDER BY updated_at DESC', [$professorId]);
    }

    public static function statusCounts(int $professorId): array
    {
        $rows = Database::fetchAll(
            'SELECT status, COUNT(*) c FROM course_plans WHERE professor_id = ? GROUP BY status',
            [$professorId]
        );
        return array_column($rows, 'c', 'status');
    }

    public static function forDepartment(int $departmentId, ?array $statuses = null): array
    {
        if ($statuses) {
            $in = implode(',', array_fill(0, count($statuses), '?'));
            return Database::fetchAll(
                "SELECT p.*, u.full_name AS professor_name FROM course_plans p
                 JOIN users u ON u.id = p.professor_id
                 WHERE p.department_id = ? AND p.status IN ($in)
                 ORDER BY p.updated_at DESC",
                array_merge([$departmentId], $statuses)
            );
        }
        return Database::fetchAll(
            'SELECT p.*, u.full_name AS professor_name FROM course_plans p
             JOIN users u ON u.id = p.professor_id
             WHERE p.department_id = ?
             ORDER BY p.updated_at DESC',
            [$departmentId]
        );
    }

    public static function units(int $planId): array
    {
        return Database::fetchAll(
            'SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number',
            [$planId]
        );
    }

    public static function versions(int $planId): array
    {
        return Database::fetchAll(
            'SELECT id, version, change_note, created_at FROM course_plan_versions WHERE plan_id = ? ORDER BY version DESC',
            [$planId]
        );
    }
}
