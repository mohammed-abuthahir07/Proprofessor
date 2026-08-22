<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;

final class User extends Model
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        return self::firstWhere('email = ?', [$email]);
    }

    public static function forInstitution(int $institutionId, array $filters = []): array
    {
        $sql = 'SELECT u.*, d.name AS dept_name, d.code AS dept_code,
                    c.name AS class_name, c.year AS class_year, c.section AS class_section,
                    c.meta AS class_meta
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN classes c ON c.id = u.class_id
             WHERE u.institution_id = ?';
        $params = [$institutionId];
        if (!empty($filters['role'])) {
            $sql .= ' AND u.role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['department_id'])) {
            $sql .= ' AND u.department_id = ?';
            $params[] = (int)$filters['department_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $sql .= ' AND u.is_active = ?';
            $params[] = (int)$filters['is_active'];
        }
        $level = strtoupper((string)($filters['program_level'] ?? ''));
        $year = (int)($filters['year'] ?? 0);
        if (in_array($level, ['UG', 'PG'], true) || $year > 0) {
            $sql .= ' AND u.role = "student"';
            if (in_array($level, ['UG', 'PG'], true)) {
                $sql .= ' AND UPPER(JSON_UNQUOTE(JSON_EXTRACT(c.meta, "$.level"))) = ?';
                $params[] = $level;
            }
            if ($year > 0) {
                $sql .= ' AND c.year = ?';
                $params[] = $year;
            }
        }
        $sql .= ' ORDER BY u.role, u.full_name';
        return Database::fetchAll($sql, $params);
    }

    public static function inInstitution(int $id, int $institutionId): ?array
    {
        return Database::fetch(
            'SELECT * FROM users WHERE id = ? AND institution_id = ?',
            [$id, $institutionId]
        );
    }

    public static function professorsInDept(int $departmentId): array
    {
        return Database::fetchAll(
            'SELECT u.id, u.full_name, u.email,
                    SUM(p.status="approved") approved,
                    SUM(p.status IN ("submitted","under_review")) pending,
                    COUNT(p.id) total,
                    AVG(p.ai_score) avg_score
             FROM users u
             LEFT JOIN course_plans p ON p.professor_id = u.id
             WHERE u.department_id = ? AND u.role = "professor"
             GROUP BY u.id
             ORDER BY u.full_name',
            [$departmentId]
        );
    }

    /**
     * Department-scoped student list for HOD views.
     * Department is always enforced server-side; optional filters never widen scope.
     */
    public static function studentsForDepartment(int $institutionId, int $departmentId, array $filters = []): array
    {
        if ($departmentId < 1) {
            return [];
        }

        $classId = (int)($filters['class_id'] ?? 0);
        if ($classId > 0) {
            $classOk = Database::fetch(
                'SELECT id FROM classes WHERE id = ? AND institution_id = ? AND department_id = ? AND is_active = 1',
                [$classId, $institutionId, $departmentId]
            );
            if (!$classOk) {
                return [];
            }
        }

        $sql = 'SELECT u.id, u.full_name, u.email, u.register_no, u.phone, u.is_active,
                       u.department_id, u.class_id,
                       d.name AS dept_name, d.code AS dept_code,
                       c.name AS class_name, c.year AS class_year, c.section AS class_section,
                       c.meta AS class_meta
                FROM users u
                INNER JOIN departments d ON d.id = u.department_id AND d.id = ?
                LEFT JOIN classes c ON c.id = u.class_id
                WHERE u.institution_id = ?
                  AND u.role = "student"
                  AND u.department_id = ?
                  AND u.is_active = 1';
        $params = [$departmentId, $institutionId, $departmentId];

        $year = (int)($filters['year'] ?? 0);
        if ($year > 0) {
            $sql .= ' AND c.year = ?';
            $params[] = $year;
        }

        $section = trim((string)($filters['section'] ?? ''));
        if ($section !== '') {
            $sql .= ' AND c.section = ?';
            $params[] = $section;
        }

        if ($classId > 0) {
            $sql .= ' AND u.class_id = ?';
            $params[] = $classId;
        }

        $level = strtoupper((string)($filters['program_level'] ?? ''));
        if (in_array($level, ['UG', 'PG'], true)) {
            $sql .= ' AND UPPER(JSON_UNQUOTE(JSON_EXTRACT(c.meta, "$.level"))) = ?';
            $params[] = $level;
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.register_no LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY c.year ASC, c.section ASC, u.full_name ASC';
        return Database::fetchAll($sql, $params);
    }
}
