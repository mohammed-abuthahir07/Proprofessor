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

    /**
     * Normalize component JSON (list or object map) to a stable list.
     *
     * @return list<array{code:string,label:string,max:float}>
     */
    public static function normalizeComponents(mixed $components): array
    {
        if (is_string($components)) {
            $decoded = json_decode($components, true);
            $components = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($components)) {
            return [];
        }

        $out = [];
        $isList = $components === [] || array_keys($components) === range(0, count($components) - 1);
        if ($isList) {
            foreach ($components as $c) {
                if (!is_array($c) || empty($c['code'])) {
                    continue;
                }
                $code = trim((string)$c['code']);
                if ($code === '') {
                    continue;
                }
                $label = trim((string)($c['label'] ?? $code));
                $out[] = [
                    'code' => $code,
                    'label' => $label !== '' ? $label : $code,
                    'max' => (float)($c['max'] ?? 0),
                ];
            }
            return $out;
        }

        foreach ($components as $code => $cfg) {
            $code = trim((string)$code);
            if ($code === '') {
                continue;
            }
            $cfg = is_array($cfg) ? $cfg : [];
            $label = trim((string)($cfg['label'] ?? $code));
            $out[] = [
                'code' => $code,
                'label' => $label !== '' ? $label : $code,
                'max' => (float)($cfg['max'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Built-in fallback when Admin has not configured any formula yet.
     *
     * @return array<string,mixed>
     */
    public static function systemFallback(): array
    {
        return [
            'id' => 0,
            'name' => 'CBCS fallback · CIA average to 25',
            'pattern' => 'CBCS',
            'plain_english' => 'Average of CIA 1 and CIA 2 scaled to 25 marks.',
            'expression' => '((cia1+cia2)/2)*(25/50)',
            'components' => json_encode([
                ['code' => 'cia1', 'label' => 'CIA 1', 'max' => 50],
                ['code' => 'cia2', 'label' => 'CIA 2', 'max' => 50],
            ]),
            'total_max' => 25,
            'subject_id' => null,
            'department_id' => null,
            'subject_type' => null,
            'is_default' => 1,
        ];
    }

    public static function appliedTitle(array $formula): string
    {
        $name = trim((string)($formula['name'] ?? ''));
        if ($name === '') {
            $name = 'Internal formula';
        }
        if (!empty($formula['subject_id'])) {
            $subj = trim((string)($formula['subject_name'] ?? $formula['subject_code'] ?? ''));
            return $subj !== '' ? ($subj . ' Override') : ($name . ' (Subject Override)');
        }
        return $name . ' (' . self::scopeLabel($formula) . ')';
    }

    /**
     * Safe arithmetic evaluation after component substitution. No eval().
     *
     * @param array<string,float|int|string> $values keyed by component code
     */
    public static function computeTotal(string $expression, array $values, array $components = []): float
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new RuntimeException('Formula expression is empty.');
        }
        $list = $components !== [] ? self::normalizeComponents($components) : [];
        $codes = [];
        foreach ($list as $c) {
            $codes[] = $c['code'];
        }
        if (!$codes) {
            preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $expression, $m);
            $codes = array_values(array_unique($m[0] ?? []));
        }

        $normalized = $expression;
        usort($codes, static fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
        foreach ($codes as $code) {
            $key = (string)$code;
            $found = null;
            foreach ($values as $vk => $vv) {
                if (strcasecmp((string)$vk, $key) === 0) {
                    $found = $vv;
                    break;
                }
            }
            if ($found === null || $found === '' || !is_numeric($found)) {
                throw new RuntimeException('Missing or invalid mark for component: ' . $key);
            }
            $normalized = preg_replace(
                '/\b' . preg_quote($key, '/') . '\b/i',
                (string)(0 + (float)$found),
                $normalized
            ) ?? $normalized;
        }

        $normalized = trim($normalized);
        if ($normalized === '' || !preg_match('/^[0-9+\-.*\/() \t]+$/', $normalized)) {
            throw new RuntimeException('Could not evaluate formula expression safely.');
        }
        if (preg_match('/[A-Za-z_]/', $normalized)) {
            throw new RuntimeException('Formula still contains unresolved component names.');
        }

        return round(self::evalArithmetic($normalized), 4);
    }

    /**
     * Validate component values against configured maxima (server-side).
     *
     * @param list<array{code:string,label:string,max:float}> $components
     * @param array<string,mixed> $values
     * @return array<string,float>
     */
    public static function validateAndNormalizeValues(array $components, array $values): array
    {
        $out = [];
        foreach ($components as $c) {
            $code = (string)$c['code'];
            $label = (string)($c['label'] ?: $code);
            $max = (float)$c['max'];
            $raw = null;
            foreach ($values as $vk => $vv) {
                if (strcasecmp((string)$vk, $code) === 0) {
                    $raw = $vv;
                    break;
                }
            }
            if ($raw === null || $raw === '') {
                throw new RuntimeException($label . ' is required.');
            }
            if (!is_numeric($raw)) {
                throw new RuntimeException($label . ' must be a number.');
            }
            $num = (float)$raw;
            if ($num < 0) {
                throw new RuntimeException($label . ' cannot be negative.');
            }
            if ($max > 0 && $num > $max + 1e-9) {
                throw new RuntimeException($label . ' cannot exceed ' . rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.') . '.');
            }
            $out[$code] = $num;
        }
        return $out;
    }

    public static function gradeLetter(float $total, float $totalMax): string
    {
        $max = $totalMax > 0 ? $totalMax : 25.0;
        $pct = ($total / $max) * 100.0;
        if ($pct >= 90) {
            return 'O';
        }
        if ($pct >= 80) {
            return 'A';
        }
        if ($pct >= 70) {
            return 'B';
        }
        if ($pct >= 60) {
            return 'C';
        }
        if ($pct >= 50) {
            return 'D';
        }
        return 'E';
    }

    /**
     * Attendance % per register_no for class+subject (present+late), same as Attendance module.
     *
     * @return array<string,float>
     */
    public static function attendancePercentages(int $classId, int $subjectId): array
    {
        if ($classId < 1 || $subjectId < 1) {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT r.register_no, r.status
             FROM attendance_records r
             JOIN attendance_sessions s ON s.id = r.session_id
             WHERE s.class_id = ? AND s.subject_id = ?',
            [$classId, $subjectId]
        );
        $agg = [];
        foreach ($rows as $r) {
            $reg = (string)$r['register_no'];
            $agg[$reg]['total'] = ($agg[$reg]['total'] ?? 0) + 1;
            if (in_array($r['status'], ['present', 'late'], true)) {
                $agg[$reg]['present'] = ($agg[$reg]['present'] ?? 0) + 1;
            }
        }
        $out = [];
        foreach ($agg as $reg => $a) {
            $out[$reg] = !empty($a['total'])
                ? round(($a['present'] ?? 0) * 100 / $a['total'], 1)
                : 0.0;
        }
        return $out;
    }

    /** Scale attendance percentage into the formula component max (e.g. 80% of 5 = 4). */
    public static function attendanceMarkFromPercent(float $percent, float $componentMax): float
    {
        if ($componentMax <= 0) {
            return 0.0;
        }
        $pct = max(0.0, min(100.0, $percent));
        return round(($pct / 100.0) * $componentMax, 2);
    }

    public static function isAttendanceComponent(string $code, string $label = ''): bool
    {
        $hay = strtolower($code . ' ' . $label);
        return str_contains($hay, 'attendance') || $code === 'att' || str_starts_with(strtolower($code), 'att_');
    }

    public static function isAssignmentComponent(string $code, string $label = ''): bool
    {
        return stripos($code . $label, 'assign') !== false;
    }

    /**
     * Absolute/relative thresholds for "significant CIA drop" warnings (not blocking).
     * Absolute: drop of this many marks or more. Relative: drop of this fraction of the prior score.
     */
    public const CIA_DROP_ABSOLUTE = 15.0;
    public const CIA_DROP_RELATIVE = 0.40;

    /**
     * Finalized assignment marks scaled to the formula assignment component max.
     * Missing / unfinalized → key absent (do not invent 0).
     *
     * @return array<string,float> register_no => scaled mark
     */
    public static function assignmentMarksForClassSubject(
        int $institutionId,
        int $classId,
        int $subjectId,
        float $componentMax
    ): array {
        if ($classId < 1 || $subjectId < 1 || $componentMax <= 0) {
            return [];
        }
        // Latest finalized grade per student for this class+subject (across assignments).
        $rows = Database::fetchAll(
            'SELECT u.register_no, s.grade, a.max_marks, s.graded_at, s.id
             FROM assignment_submissions s
             JOIN assignments a ON a.id = s.assignment_id
             JOIN users u ON u.id = s.student_id
             WHERE a.institution_id = ?
               AND a.class_id = ?
               AND a.subject_id = ?
               AND s.grade IS NOT NULL
               AND s.status = "graded"
               AND u.register_no IS NOT NULL
               AND u.register_no <> ""
             ORDER BY s.graded_at DESC, s.id DESC',
            [$institutionId, $classId, $subjectId]
        );
        $out = [];
        foreach ($rows as $r) {
            $reg = trim((string)$r['register_no']);
            if ($reg === '' || isset($out[$reg])) {
                continue; // keep latest only
            }
            $asgMax = max(0.01, (float)($r['max_marks'] ?? 25));
            $scaled = round(((float)$r['grade'] / $asgMax) * $componentMax, 2);
            $out[$reg] = max(0.0, min($componentMax, $scaled));
        }
        return $out;
    }

    /**
     * Significant CIA drop warning when both values exist.
     *
     * @return array{flag:bool,message:string}
     */
    public static function ciaDropWarning(float $previous, float $current): array
    {
        if ($previous <= 0) {
            return ['flag' => false, 'message' => ''];
        }
        $drop = $previous - $current;
        if ($drop < self::CIA_DROP_ABSOLUTE && $drop < ($previous * self::CIA_DROP_RELATIVE)) {
            return ['flag' => false, 'message' => ''];
        }
        return [
            'flag' => true,
            'message' => 'Significant drop from previous CIA (' . rtrim(rtrim(number_format($previous, 2, '.', ''), '0'), '.')
                . ' → ' . rtrim(rtrim(number_format($current, 2, '.', ''), '0'), '.') . ')',
        ];
    }

    /**
     * Aggregate distribution for one class+subject (authorized callers only).
     *
     * @return array{students:int,average:?float,highest:?float,lowest:?float,median:?float,pass:int,fail:int}
     */
    public static function distributionForClassSubject(
        int $institutionId,
        int $classId,
        int $subjectId,
        string $academicYear,
        float $totalMax
    ): array {
        $params = [$institutionId, $classId, $subjectId];
        $sql = 'SELECT computed_total FROM internal_marks
                WHERE institution_id=? AND class_id=? AND subject_id=?
                  AND computed_total IS NOT NULL';
        if ($academicYear !== '') {
            $sql .= ' AND (academic_year = ? OR academic_year = "")';
            $params[] = $academicYear;
        }
        $rows = Database::fetchAll($sql, $params);
        $vals = [];
        foreach ($rows as $r) {
            $vals[] = (float)$r['computed_total'];
        }
        $n = count($vals);
        if ($n === 0) {
            return [
                'students' => 0,
                'average' => null,
                'highest' => null,
                'lowest' => null,
                'median' => null,
                'pass' => 0,
                'fail' => 0,
            ];
        }
        sort($vals);
        $pass = 0;
        $fail = 0;
        $threshold = $totalMax > 0 ? ($totalMax * 0.5) : 0; // D boundary ~50% of max
        foreach ($vals as $v) {
            if ($v >= $threshold) {
                $pass++;
            } else {
                $fail++;
            }
        }
        $mid = intdiv($n, 2);
        $median = ($n % 2 === 1) ? $vals[$mid] : round(($vals[$mid - 1] + $vals[$mid]) / 2, 2);
        return [
            'students' => $n,
            'average' => round(array_sum($vals) / $n, 2),
            'highest' => max($vals),
            'lowest' => min($vals),
            'median' => $median,
            'pass' => $pass,
            'fail' => $fail,
        ];
    }

    /**
     * Ensure internal_marks supports academic-year isolation without duplicates.
     */
    public static function ensureInternalMarksSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [];
        foreach (Database::fetchAll('SHOW COLUMNS FROM internal_marks') as $c) {
            $cols[$c['Field']] = true;
        }
        if (!isset($cols['academic_year'])) {
            Database::query(
                "ALTER TABLE internal_marks
                 ADD COLUMN academic_year VARCHAR(20) NOT NULL DEFAULT ''
                 COMMENT 'Institution academic year snapshot' AFTER class_id"
            );
        }
        $indexes = Database::fetchAll('SHOW INDEX FROM internal_marks WHERE Key_name = ?', ['uq_marks']);
        $hasYear = false;
        foreach ($indexes as $idx) {
            if (($idx['Column_name'] ?? '') === 'academic_year') {
                $hasYear = true;
                break;
            }
        }
        if (!$hasYear) {
            try {
                Database::query('ALTER TABLE internal_marks DROP INDEX uq_marks');
            } catch (\Throwable $e) {
                // Index may already have been replaced.
            }
            Database::query(
                'ALTER TABLE internal_marks
                 ADD UNIQUE KEY uq_marks (subject_id, class_id, register_no, academic_year)'
            );
        }
    }

    /** Recursive-descent arithmetic parser for + - * / ( ) decimals. */
    private static function evalArithmetic(string $expr): float
    {
        $expr = preg_replace('/\s+/', '', $expr) ?? '';
        if ($expr === '') {
            throw new RuntimeException('Empty arithmetic expression.');
        }
        $i = 0;
        $len = strlen($expr);

        $parseExpr = null;
        $parseTerm = null;
        $parseFactor = null;

        $parseFactor = static function () use (&$parseExpr, &$i, $expr, $len, &$parseFactor): float {
            if ($i >= $len) {
                throw new RuntimeException('Unexpected end of expression.');
            }
            $ch = $expr[$i];
            if ($ch === '+') {
                $i++;
                return $parseFactor();
            }
            if ($ch === '-') {
                $i++;
                return -$parseFactor();
            }
            if ($ch === '(') {
                $i++;
                $v = $parseExpr();
                if ($i >= $len || $expr[$i] !== ')') {
                    throw new RuntimeException('Unbalanced parentheses in formula.');
                }
                $i++;
                return $v;
            }
            $start = $i;
            while ($i < $len && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                $i++;
            }
            if ($start === $i) {
                throw new RuntimeException('Invalid number in formula.');
            }
            return (float)substr($expr, $start, $i - $start);
        };

        $parseTerm = static function () use (&$parseFactor, &$i, $expr, $len): float {
            $v = $parseFactor();
            while ($i < $len && ($expr[$i] === '*' || $expr[$i] === '/')) {
                $op = $expr[$i++];
                $r = $parseFactor();
                if ($op === '*') {
                    $v *= $r;
                } else {
                    if (abs($r) < 1e-12) {
                        throw new RuntimeException('Division by zero in formula.');
                    }
                    $v /= $r;
                }
            }
            return $v;
        };

        $parseExpr = static function () use (&$parseTerm, &$i, $expr, $len): float {
            $v = $parseTerm();
            while ($i < $len && ($expr[$i] === '+' || $expr[$i] === '-')) {
                $op = $expr[$i++];
                $r = $parseTerm();
                $v = $op === '+' ? $v + $r : $v - $r;
            }
            return $v;
        };

        $result = $parseExpr();
        if ($i !== $len) {
            throw new RuntimeException('Trailing characters in formula.');
        }
        return $result;
    }

    public static function validateExpression(string $expression, array $components): void
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new RuntimeException('Expression is required.');
        }

        // Reject clearly unsafe tokens early.
        if (preg_match('/\b(eval|exec|system|shell_exec|passthru|proc_open|popen|assert|create_function|include|require|\\$)\b/i', $expression)) {
            throw new RuntimeException('Expression contains forbidden tokens.');
        }
        if (preg_match('/[^A-Za-z0-9_+\-.*\/() \t]/', $expression)) {
            throw new RuntimeException('Expression contains unsupported characters. Use component codes and + - * / ( ) only.');
        }

        $codes = [];
        // Support both [{code,max}] and {"CIA1":{"max":30}} shapes.
        $isList = $components === [] || array_keys($components) === range(0, count($components) - 1);
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
        $codes = array_values(array_unique(array_filter(array_map('trim', $codes), static fn($c) => $c !== '')));

        // Identifiers used in the expression (e.g. cia1, CIA2, assignment).
        preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $expression, $m);
        $used = array_values(array_unique($m[0] ?? []));

        if (!$codes && !$used) {
            throw new RuntimeException('Expression must use at least one component code (example: CIA1, cia2).');
        }

        // If Component JSON is empty, allow codes declared by the expression itself
        // so formulas like ((cia1+cia2)/2)*(15/50) still validate.
        if (!$codes) {
            $codes = $used;
        }

        // Every identifier in the expression must be a known component (case-insensitive).
        $codeMap = [];
        foreach ($codes as $code) {
            $codeMap[strtolower($code)] = $code;
        }
        $missing = [];
        foreach ($used as $token) {
            if (!isset($codeMap[strtolower($token)])) {
                $missing[] = $token;
            }
        }
        if ($missing) {
            throw new RuntimeException(
                'Expression uses unknown component(s): ' . implode(', ', $missing)
                . '. Add them to Component JSON or use only defined component codes.'
            );
        }

        // Replace longest codes first (case-insensitive) so nested names don't leave letters behind.
        $normalized = $expression;
        $replaceCodes = $codes;
        usort($replaceCodes, static fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
        foreach ($replaceCodes as $code) {
            $normalized = preg_replace('/\b' . preg_quote((string)$code, '/') . '\b/i', '1', $normalized) ?? $normalized;
        }
        // Also replace any remaining used tokens (casing variants).
        foreach ($used as $token) {
            $normalized = preg_replace('/\b' . preg_quote($token, '/') . '\b/i', '1', $normalized) ?? $normalized;
        }

        $normalized = trim($normalized);
        // Allow integers/decimals and + - * / ( ) whitespace only.
        if ($normalized === '' || !preg_match('/^[0-9+\-.*\/() \t]+$/', $normalized)) {
            throw new RuntimeException('Expression contains unsupported tokens. Use component codes and + - * / ( ) only. Example: ((cia1+cia2)/2)*(15/50)');
        }
        if (preg_match('/[A-Za-z_]/', $normalized)) {
            throw new RuntimeException('Expression still contains letters after parsing component codes.');
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
