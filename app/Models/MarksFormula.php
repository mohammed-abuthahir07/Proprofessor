<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Database;
use RuntimeException;

final class MarksFormula extends Model
{
    protected static string $table = 'marks_formulas';

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [];
        foreach (Database::fetchAll('SHOW COLUMNS FROM marks_formulas') as $c) {
            $cols[$c['Field']] = true;
        }
        if (!isset($cols['subject_type'])) {
            Database::query(
                "ALTER TABLE marks_formulas
                 ADD COLUMN subject_type VARCHAR(20) NULL DEFAULT NULL
                 COMMENT 'theory|lab|NULL=all types' AFTER department_id"
            );
        }
        if (!isset($cols['subject_id'])) {
            Database::query(
                "ALTER TABLE marks_formulas
                 ADD COLUMN subject_id INT UNSIGNED NULL DEFAULT NULL
                 COMMENT 'NULL=department/type default; set=subject override' AFTER subject_type"
            );
        }
    }

    public static function forInstitution(int $institutionId): array
    {
        self::ensureSchema();
        return Database::fetchAll(
            'SELECT f.*,
                    d.name AS department_name, d.code AS department_code,
                    s.code AS subject_code, s.name AS subject_name
             FROM marks_formulas f
             LEFT JOIN departments d ON d.id = f.department_id
             LEFT JOIN subjects s ON s.id = f.subject_id
             WHERE f.institution_id = ?
             ORDER BY f.is_default DESC, f.department_id IS NULL ASC, f.subject_id IS NULL ASC, f.id DESC',
            [$institutionId]
        );
    }

    /**
     * Resolve applicable formula for marks entry (server-side priority).
     * Priority:
     * 1) Subject override
     * 2) Department + subject type
     * 3) Department default (all types)
     * 4) Institution default (is_default / no department)
     */
    public static function resolveForContext(
        int $institutionId,
        ?int $departmentId,
        ?int $subjectId,
        ?string $subjectType = null
    ): ?array {
        self::ensureSchema();
        $type = self::normalizeSubjectType($subjectType);

        if ($subjectId && $subjectId > 0) {
            $override = Database::fetch(
                'SELECT f.* FROM marks_formulas f
                 INNER JOIN subjects s ON s.id = f.subject_id AND s.institution_id = f.institution_id
                 WHERE f.institution_id = ?
                   AND f.subject_id = ?
                   AND s.is_active = 1
                 ORDER BY f.id DESC
                 LIMIT 1',
                [$institutionId, $subjectId]
            );
            if ($override) {
                // Enforce department isolation: override subject must match requested department when provided.
                if ($departmentId && $departmentId > 0) {
                    $subjDept = Database::fetch(
                        'SELECT department_id FROM subjects WHERE id = ? AND institution_id = ?',
                        [$subjectId, $institutionId]
                    );
                    if ($subjDept && (int)$subjDept['department_id'] === $departmentId) {
                        return $override;
                    }
                    // Wrong department context — ignore this override.
                } else {
                    return $override;
                }
            }
            if ($departmentId === null || $departmentId < 1) {
                $subj = Database::fetch(
                    'SELECT department_id, meta FROM subjects WHERE id = ? AND institution_id = ?',
                    [$subjectId, $institutionId]
                );
                if ($subj) {
                    $departmentId = (int)($subj['department_id'] ?? 0) ?: null;
                    if ($type === null) {
                        $type = self::subjectTypeFromMeta($subj['meta'] ?? null);
                    }
                }
            } elseif ($type === null) {
                $subj = Database::fetch('SELECT meta FROM subjects WHERE id = ? AND institution_id = ?', [$subjectId, $institutionId]);
                $type = self::subjectTypeFromMeta($subj['meta'] ?? null);
            }
        }

        if ($departmentId && $departmentId > 0 && $type !== null) {
            $byType = Database::fetch(
                'SELECT * FROM marks_formulas
                 WHERE institution_id = ?
                   AND department_id = ?
                   AND subject_id IS NULL
                   AND subject_type = ?
                 ORDER BY id DESC
                 LIMIT 1',
                [$institutionId, $departmentId, $type]
            );
            if ($byType) {
                return $byType;
            }
        }

        if ($departmentId && $departmentId > 0) {
            $deptDefault = Database::fetch(
                'SELECT * FROM marks_formulas
                 WHERE institution_id = ?
                   AND department_id = ?
                   AND subject_id IS NULL
                   AND (subject_type IS NULL OR subject_type = "")
                 ORDER BY is_default DESC, id DESC
                 LIMIT 1',
                [$institutionId, $departmentId]
            );
            if ($deptDefault) {
                return $deptDefault;
            }
        }

        return Database::fetch(
            'SELECT * FROM marks_formulas
             WHERE institution_id = ?
               AND (is_default = 1 OR department_id IS NULL)
               AND subject_id IS NULL
             ORDER BY is_default DESC, id DESC
             LIMIT 1',
            [$institutionId]
        );
    }

    public static function normalizeSubjectType(mixed $type): ?string
    {
        $t = strtolower(trim((string)$type));
        if ($t === '' || $t === 'all' || $t === 'any') {
            return null;
        }
        if (in_array($t, ['theory', 'lab'], true)) {
            return $t;
        }
        return null;
    }

    public static function subjectTypeFromMeta(mixed $meta): ?string
    {
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($meta)) {
            return null;
        }
        return self::normalizeSubjectType($meta['course_type'] ?? $meta['type'] ?? null);
    }

    public static function scopeLabel(array $formula): string
    {
        if (!empty($formula['subject_id'])) {
            return 'Subject Override';
        }
        $type = self::normalizeSubjectType($formula['subject_type'] ?? null);
        if (!empty($formula['department_id']) && $type !== null) {
            return 'Department + Type';
        }
        if (!empty($formula['department_id'])) {
            return 'Department';
        }
        if (!empty($formula['is_default'])) {
            return 'Institution Default';
        }
        return 'Institution';
    }

    public static function validateExpression(string $expression, array $components): void
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new RuntimeException('Expression is required.');
        }

        $codes = [];
        // Support both [{code,max}] and {"CIA1":{"max":30}} shapes.
        $isList = array_keys($components) === range(0, count($components) - 1);
        if ($isList) {
            foreach ($components as $c) {
                if (is_array($c) && !empty($c['code'])) {
                    $codes[] = (string)$c['code'];
                }
            }
        } else {
            foreach ($components as $code => $cfg) {
                $codes[] = (string)$code;
            }
        }
        $codes = array_values(array_unique(array_filter($codes, static fn($c) => $c !== '')));
        if (!$codes) {
            throw new RuntimeException('Component JSON must include at least one component code.');
        }

        $normalized = $expression;
        foreach ($codes as $code) {
            $normalized = preg_replace('/\b' . preg_quote($code, '/') . '\b/i', '1', $normalized) ?? $normalized;
        }
        if (!preg_match('/^[0-9+\-.*\/() \t]+$/', $normalized)) {
            throw new RuntimeException('Expression contains unsupported tokens. Use component codes and + - * / ( ) only.');
        }
        // Balanced parentheses check
        $depth = 0;
        foreach (str_split($expression) as $ch) {
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth < 0) {
                    throw new RuntimeException('Expression has unbalanced parentheses.');
                }
            }
        }
        if ($depth !== 0) {
            throw new RuntimeException('Expression has unbalanced parentheses.');
        }
    }

    public static function createScoped(array $data): int
    {
        $payload = self::prepareScopedPayload($data, false);
        return self::create($payload);
    }

    public static function findForInstitution(int $id, int $institutionId): ?array
    {
        self::ensureSchema();
        if ($id < 1 || $institutionId < 1) {
            return null;
        }
        return Database::fetch(
            'SELECT f.*,
                    d.name AS department_name, d.code AS department_code,
                    s.code AS subject_code, s.name AS subject_name
             FROM marks_formulas f
             LEFT JOIN departments d ON d.id = f.department_id
             LEFT JOIN subjects s ON s.id = f.subject_id
             WHERE f.id = ? AND f.institution_id = ?
             LIMIT 1',
            [$id, $institutionId]
        );
    }

    /**
     * Update an existing formula in-place (same ID). Tenant-scoped.
     */
    public static function updateScoped(int $id, int $institutionId, array $data): void
    {
        self::ensureSchema();
        $existing = self::findForInstitution($id, $institutionId);
        if (!$existing) {
            throw new RuntimeException('Formula not found for this institution.');
        }
        $data['institution_id'] = $institutionId;
        $payload = self::prepareScopedPayload($data, true);
        // Never change id / institution_id / created_at / created_by on update.
        unset($payload['institution_id'], $payload['created_by']);
        self::updateById($id, $payload);
    }

    /**
     * @return array<string,mixed>
     */
    private static function prepareScopedPayload(array $data, bool $forUpdate): array
    {
        self::ensureSchema();
        $institutionId = (int)($data['institution_id'] ?? 0);
        $departmentId = (int)($data['department_id'] ?? 0) ?: null;
        $subjectId = (int)($data['subject_id'] ?? 0) ?: null;
        $subjectType = self::normalizeSubjectType($data['subject_type'] ?? null);
        $name = trim((string)($data['name'] ?? ''));
        $expression = trim((string)($data['expression'] ?? ''));
        $componentsRaw = $data['components'] ?? '[]';
        $components = is_array($componentsRaw) ? $componentsRaw : (json_decode((string)$componentsRaw, true) ?: []);

        if ($institutionId < 1) {
            throw new RuntimeException('Institution is required.');
        }
        if ($name === '') {
            throw new RuntimeException('Formula name is required.');
        }
        self::validateExpression($expression, is_array($components) ? $components : []);

        if ($subjectId) {
            $subject = Database::fetch(
                'SELECT id, department_id FROM subjects WHERE id = ? AND institution_id = ? AND is_active = 1',
                [$subjectId, $institutionId]
            );
            if (!$subject) {
                throw new RuntimeException('Selected subject was not found in this institution.');
            }
            $subjectDept = (int)($subject['department_id'] ?? 0);
            if ($departmentId && $subjectDept && $departmentId !== $subjectDept) {
                throw new RuntimeException('Subject override must belong to the selected department.');
            }
            if (!$departmentId && $subjectDept) {
                $departmentId = $subjectDept;
            }
            if ($subjectType === null) {
                $meta = Database::fetch('SELECT meta FROM subjects WHERE id = ?', [$subjectId]);
                $subjectType = self::subjectTypeFromMeta($meta['meta'] ?? null) ?? 'theory';
            }
        }

        if ($departmentId) {
            $dept = Database::fetch(
                'SELECT id FROM departments WHERE id = ? AND institution_id = ?',
                [$departmentId, $institutionId]
            );
            if (!$dept) {
                throw new RuntimeException('Selected department was not found.');
            }
        }

        $payload = [
            'institution_id' => $institutionId,
            'department_id' => $departmentId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'name' => $name,
            'pattern' => trim((string)($data['pattern'] ?? '')) ?: null,
            'plain_english' => trim((string)($data['plain_english'] ?? '')) ?: $name,
            'components' => json_encode($components, JSON_UNESCAPED_UNICODE),
            'expression' => $expression,
            'total_max' => (float)($data['total_max'] ?? 25),
            'ai_parsed' => $data['ai_parsed'] ?? null,
            'is_default' => !empty($data['is_default']) ? 1 : 0,
        ];
        if (!$forUpdate) {
            $payload['created_by'] = (int)($data['created_by'] ?? 0) ?: null;
        }
        return $payload;
    }
}
