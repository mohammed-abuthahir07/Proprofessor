<?php
declare(strict_types=1);

function config(?string $key = null, $default = null)
{
    static $cfg;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/config.php';
        $local = __DIR__ . '/../config/config.local.php';
        if (is_file($local)) {
            $override = require $local;
            if (is_array($override)) {
                $cfg = array_replace_recursive($cfg, $override);
            }
        }
    }
    if ($key === null) return $cfg;
    $parts = explode('.', $key);
    $val = $cfg;
    foreach ($parts as $p) {
        if (!is_array($val) || !array_key_exists($p, $val)) return $default;
        $val = $val[$p];
    }
    return $val;
}

/**
 * Web path to the app root, e.g. /professor or /demo/professor
 */
function app_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $configured = config('base_url', 'auto');
    if (is_string($configured) && $configured !== '' && strtolower($configured) !== 'auto') {
        return $base = rtrim($configured, '/');
    }

    $projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $docRoot = str_replace('\\', '/', $docRoot !== '' ? (realpath($docRoot) ?: $docRoot) : '');

    if ($docRoot !== '' && str_starts_with($projectRoot, rtrim($docRoot, '/'))) {
        $rel = substr($projectRoot, strlen(rtrim($docRoot, '/')));
        return $base = rtrim($rel, '/') ?: '';
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = dirname($script);
    // Nested legacy scripts live under /professor|/student|/hod|/admin|/auth|/api
    foreach (['/professor', '/student', '/hod', '/admin', '/auth', '/api', '/scripts'] as $leaf) {
        if (str_ends_with($dir, $leaf)) {
            $dir = substr($dir, 0, -strlen($leaf)) ?: '/';
            break;
        }
    }
    return $base = ($dir === '/' ? '' : rtrim($dir, '/'));
}

function base_url(string $path = ''): string
{
    $base = app_base_path();
    $path = '/' . ltrim($path, '/');
    if ($path === '/') {
        $path = '';
    }
    return $base . $path;
}

function redirect(string $path): void
{
    if (str_starts_with($path, 'http')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . base_url($path));
    }
    exit;
}

/**
 * HTML-escape a scalar value for safe output.
 * Accepts string/int/float/null because DB drivers often return numeric columns as int/float.
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($data, $flags);
    if ($json === false) {
        $json = json_encode([
            'ok' => false,
            'error' => 'Could not encode response as JSON.',
        ], JSON_UNESCAPED_UNICODE);
    }
    echo $json !== false ? $json : '{"ok":false,"error":"JSON encode failed"}';
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', (string)$token)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function dashboard_path_for_role(string $role): string
{
    return match ($role) {
        'student' => '/student/dashboard',
        'hod'     => '/hod/dashboard',
        'admin', 'superadmin' => '/admin/dashboard',
        default   => '/professor/dashboard',
    };
}

function feature_enabled(string $code, ?int $institutionId = null): bool
{
    $user = Auth::user();
    $institutionId = $institutionId ?? (int)($user['institution_id'] ?? 0);
    if (!$institutionId) {
        $row = Database::fetch('SELECT is_enabled FROM feature_flags WHERE code = ?', [$code]);
        return $row ? (bool)$row['is_enabled'] : false;
    }
    $row = Database::fetch(
        'SELECT COALESCE(i.is_enabled, f.is_enabled) AS enabled
         FROM feature_flags f
         LEFT JOIN institution_features i
           ON i.feature_code = f.code AND i.institution_id = ?
         WHERE f.code = ?',
        [$institutionId, $code]
    );
    return $row ? (bool)$row['enabled'] : false;
}

function notify_user(int $userId, string $type, string $title, string $body = '', ?string $url = null): void
{
    Database::insert('notifications', [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'action_url' => $url,
        'is_read' => 0,
    ]);
}

function unread_notifications_count(int $userId): int
{
    $row = Database::fetch(
        'SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0',
        [$userId]
    );
    return (int)($row['c'] ?? 0);
}

function log_activity(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
{
    $user = Auth::user();
    Database::insert('activity_logs', [
        'institution_id' => $user['institution_id'] ?? null,
        'user_id' => $user['id'] ?? null,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'details' => $details ? json_encode($details) : null,
    ]);
}

function log_ai(string $module, array $input, array $result, ?string $refType = null, ?int $refId = null): void
{
    $user = Auth::user();
    if (!$user) return;
    Database::insert('ai_generations', [
        'institution_id' => (int)$user['institution_id'],
        'user_id' => (int)$user['id'],
        'module' => $module,
        'prompt_code' => $module,
        'input_payload' => json_encode($input),
        'output_payload' => json_encode($result['json'] ?? ['text' => $result['text'] ?? null, 'error' => $result['error'] ?? null]),
        'model' => class_exists('Gemini')
            ? Gemini::normalizeModel((string)config('gemini.model', 'gemini-2.5-flash'))
            : config('gemini.model'),
        'latency_ms' => $result['latency_ms'] ?? null,
        'status' => !empty($result['ok']) ? 'success' : 'error',
        'error_message' => $result['error'] ?? null,
        'ref_type' => $refType,
        'ref_id' => $refId,
    ]);
}

function class_program_level(?array $class): string
{
    if (!$class) {
        return '';
    }
    $meta = json_decode((string)($class['meta'] ?? ''), true);
    $level = strtoupper((string)(($meta['level'] ?? '') ?: ($class['program_level'] ?? '')));
    return in_array($level, ['UG', 'PG'], true) ? $level : '';
}

function class_batch_label(array $class): string
{
    $level = class_program_level($class);
    $year = (int)($class['year'] ?? 0);
    $section = trim((string)($class['section'] ?? ''));
    $dept = trim((string)($class['dept_code'] ?? $class['dept_name'] ?? ''));
    $name = trim((string)($class['name'] ?? ''));
    $parts = [];
    if ($level !== '') {
        $parts[] = $level;
    }
    if ($year > 0) {
        $parts[] = 'Year ' . $year;
    }
    if ($dept !== '') {
        $parts[] = $dept;
    }
    if ($section !== '') {
        $parts[] = 'Sec ' . $section;
    }
    if ($parts === []) {
        return $name !== '' ? $name : 'Class';
    }
    $label = implode(' · ', $parts);
    if ($name !== '' && strcasecmp($name, $dept) !== 0 && !in_array($name, $parts, true)) {
        $label .= ' (' . $name . ')';
    }
    return $label;
}

function student_class_id(array $user): int
{
    $cid = (int)($user['class_id'] ?? 0);
    if ($cid > 0) {
        return $cid;
    }
    $uid = (int)($user['id'] ?? 0);
    if ($uid < 1) {
        return 0;
    }
    $row = Database::fetch('SELECT class_id FROM users WHERE id = ?', [$uid]);
    return (int)($row['class_id'] ?? 0);
}

function class_belongs_to_institution(int $classId, int $institutionId): bool
{
    if ($classId < 1 || $institutionId < 1) {
        return false;
    }
    return Database::fetch(
        'SELECT id FROM classes WHERE id = ? AND institution_id = ?',
        [$classId, $institutionId]
    ) !== null;
}

function professor_can_manage_class(array $user, int $classId): bool
{
    if ($classId < 1) {
        return false;
    }
    $c = Database::fetch('SELECT institution_id, department_id FROM classes WHERE id = ?', [$classId]);
    if (!$c || (int)$c['institution_id'] !== (int)($user['institution_id'] ?? 0)) {
        return false;
    }
    $role = (string)($user['role'] ?? '');
    if (in_array($role, ['admin', 'superadmin'], true)) {
        return true;
    }
    if ($role === 'hod') {
        $dept = (int)($user['department_id'] ?? 0);
        return $dept > 0 && (int)$c['department_id'] === $dept;
    }
    return Database::fetch(
        'SELECT sa.id FROM subject_assignments sa
         WHERE sa.professor_id = ? AND sa.class_id = ?',
        [(int)$user['id'], $classId]
    ) !== null;
}

function professor_can_manage_subject(array $user, int $subjectId, int $classId): bool
{
    if ($subjectId < 1 || $classId < 1) {
        return false;
    }
    if (!professor_can_manage_class($user, $classId)) {
        return false;
    }
    $role = (string)($user['role'] ?? '');
    if (in_array($role, ['admin', 'superadmin'], true)) {
        return true;
    }
    return Database::fetch(
        'SELECT sa.id FROM subject_assignments sa
         JOIN subjects s ON s.id = sa.subject_id
         WHERE sa.professor_id = ? AND sa.subject_id = ? AND sa.class_id = ?
           AND s.institution_id = ?',
        [(int)$user['id'], $subjectId, $classId, (int)$user['institution_id']]
    ) !== null;
}

function institution_academic_year(int $institutionId): string
{
    $row = Database::fetch('SELECT academic_year FROM institutions WHERE id = ?', [$institutionId]);
    return trim((string)($row['academic_year'] ?? ''));
}

function institution_current_semester(int $institutionId): string
{
    $row = Database::fetch('SELECT current_semester FROM institutions WHERE id = ?', [$institutionId]);
    return trim((string)($row['current_semester'] ?? ''));
}

function professor_manageable_classes(array $user): array
{
    $inst = (int)($user['institution_id'] ?? 0);
    $role = (string)($user['role'] ?? '');
    if (in_array($role, ['admin', 'superadmin'], true)) {
        return academic_classes($inst);
    }
    if ($role === 'hod') {
        $dept = (int)($user['department_id'] ?? 0);
        return $dept > 0 ? academic_classes($inst, $dept) : [];
    }
    return Database::fetchAll(
        'SELECT DISTINCT c.*, d.code AS dept_code, d.name AS dept_name
         FROM subject_assignments sa
         JOIN classes c ON c.id = sa.class_id
         LEFT JOIN departments d ON d.id = c.department_id
         WHERE sa.professor_id = ? AND c.institution_id = ? AND sa.class_id IS NOT NULL AND c.is_active = 1
         ORDER BY c.year, c.section, c.name',
        [(int)$user['id'], $inst]
    );
}

function enroll_class_students_in_subject(int $institutionId, int $classId, int $subjectId, ?string $semesterOverride = null): void
{
    if ($classId < 1 || $subjectId < 1) {
        return;
    }
    $academicYear = institution_academic_year($institutionId);
    $semester = trim((string)$semesterOverride) !== ''
        ? subject_normalize_semester((string)$semesterOverride)
        : institution_current_semester($institutionId);
    $students = Database::fetchAll(
        'SELECT id FROM users WHERE institution_id = ? AND role = "student" AND class_id = ? AND is_active = 1',
        [$institutionId, $classId]
    );
    foreach ($students as $s) {
        $studentId = (int)$s['id'];
        $exists = Database::fetch(
            'SELECT id FROM enrollments
             WHERE student_id = ? AND subject_id = ?
               AND (academic_year IS NULL OR academic_year = ? OR ? = "")
             LIMIT 1',
            [$studentId, $subjectId, $academicYear, $academicYear]
        );
        if ($exists) {
            Database::query(
                'UPDATE enrollments SET status = "active", class_id = ?, academic_year = ?, semester = ? WHERE id = ?',
                [$classId, $academicYear ?: null, $semester ?: null, (int)$exists['id']]
            );
        } else {
            Database::insert('enrollments', [
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'class_id' => $classId,
                'academic_year' => $academicYear ?: null,
                'semester' => $semester ?: null,
                'status' => 'active',
            ]);
        }
    }
}

function courses_for_student(array $user): array
{
    $uid = (int)($user['id'] ?? 0);
    $inst = (int)($user['institution_id'] ?? 0);
    $classId = student_class_id($user);
    if ($classId < 1) {
        return [];
    }
    $academicYear = institution_academic_year($inst);
    $sql = 'SELECT s.*, u.full_name AS professor_name, e.semester, cp.bloom_data, cp.ai_score,
                   c.year AS class_year
            FROM enrollments e
            JOIN subjects s ON s.id = e.subject_id
            LEFT JOIN classes c ON c.id = e.class_id
            LEFT JOIN subject_assignments sa ON sa.subject_id = s.id AND sa.class_id = e.class_id
            LEFT JOIN users u ON u.id = sa.professor_id
            LEFT JOIN course_plans cp ON cp.subject_id = s.id AND cp.class_id = e.class_id AND cp.status = "approved"
            WHERE e.student_id = ? AND e.status = "active" AND s.institution_id = ?
              AND e.class_id = ?';
    $params = [$uid, $inst, $classId];
    if ($academicYear !== '') {
        $sql .= ' AND (e.academic_year IS NULL OR e.academic_year = ?)';
        $params[] = $academicYear;
    }
    $sql .= ' ORDER BY s.name';
    $rows = Database::fetchAll($sql, $params);
    $classYear = 0;
    foreach ($rows as $row) {
        $classYear = (int)($row['class_year'] ?? 0);
        if ($classYear > 0) {
            break;
        }
    }
    if ($classYear < 1) {
        $class = Database::fetch('SELECT year FROM classes WHERE id = ?', [$classId]);
        $classYear = (int)($class['year'] ?? 0);
    }
    if ($classYear < 1) {
        return $rows;
    }
    return array_values(array_filter(
        $rows,
        static function (array $row) use ($classYear): bool {
            $subjectYear = subject_academic_year_level($row);
            return $subjectYear < 1 || $subjectYear === $classYear;
        }
    ));
}

function announcements_for_user(array $user, int $limit = 0): array
{
    $sql = 'SELECT * FROM announcements
            WHERE institution_id = ?
              AND (department_id IS NULL OR department_id = ?)
            ORDER BY created_at DESC';
    $params = [(int)($user['institution_id'] ?? 0), (int)($user['department_id'] ?? 0)];
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    return Database::fetchAll($sql, $params);
}

function class_label_by_id(int $classId): string
{
    if ($classId < 1) {
        return '';
    }
    $c = Database::fetch(
        'SELECT c.*, d.code AS dept_code, d.name AS dept_name
         FROM classes c LEFT JOIN departments d ON d.id = c.department_id
         WHERE c.id = ?',
        [$classId]
    );
    return $c ? class_batch_label($c) : '';
}

function assignments_visible_to_student(array $user): array
{
    $classId = student_class_id($user);
    if ($classId < 1) {
        return [];
    }
    return Database::fetchAll(
        'SELECT a.*, s.status AS sub_status, s.grade, s.feedback
         FROM assignments a
         LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?
         WHERE a.status = "published"
           AND a.institution_id = ?
           AND a.class_id = ?
         ORDER BY a.deadline IS NULL, a.deadline, a.id DESC',
        [(int)$user['id'], (int)$user['institution_id'], $classId]
    );
}

function student_can_submit_assignment(int $assignmentId, array $user): bool
{
    $classId = student_class_id($user);
    if ($classId < 1 || $assignmentId < 1) {
        return false;
    }
    return Database::fetch(
        'SELECT id FROM assignments
         WHERE id = ? AND status = "published" AND institution_id = ? AND class_id = ?',
        [$assignmentId, (int)$user['institution_id'], $classId]
    ) !== null;
}

function academic_classes(int $institutionId, ?int $departmentId = null): array
{
    $sql = 'SELECT c.*, d.code AS dept_code, d.name AS dept_name
            FROM classes c
            LEFT JOIN departments d ON d.id = c.department_id
            WHERE c.institution_id = ?';
    $params = [$institutionId];
    if ($departmentId) {
        $sql .= ' AND c.department_id = ?';
        $params[] = $departmentId;
    }
    $sql .= ' ORDER BY c.year, c.section, c.name';
    return Database::fetchAll($sql, $params);
}

function sync_class_roster(int $institutionId, int $classId): array
{
    $students = Database::fetchAll(
        'SELECT id, full_name, email, register_no FROM users
         WHERE institution_id = ? AND role = "student" AND class_id = ? AND is_active = 1
         ORDER BY full_name',
        [$institutionId, $classId]
    );
    foreach ($students as $s) {
        $reg = trim((string)($s['register_no'] ?? ''));
        if ($reg === '') {
            $reg = 'STU' . (int)$s['id'];
        }
        Database::query(
            'INSERT INTO students_roster (institution_id, class_id, user_id, register_no, full_name, email, is_active)
             VALUES (?,?,?,?,?,?,1)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), full_name = VALUES(full_name), email = VALUES(email), is_active = 1',
            [$institutionId, $classId, (int)$s['id'], $reg, $s['full_name'], $s['email']]
        );
    }
    return Database::fetchAll(
        'SELECT * FROM students_roster WHERE class_id = ? AND institution_id = ? AND is_active = 1 ORDER BY register_no, full_name',
        [$classId, $institutionId]
    );
}

function professor_subjects(array $user, ?int $classId = null): array
{
    $uid = (int)$user['id'];
    $sql = 'SELECT s.*, sa.class_id, sa.id AS assignment_id
            FROM subject_assignments sa
            JOIN subjects s ON s.id = sa.subject_id
            WHERE sa.professor_id = ? AND s.is_active = 1';
    $params = [$uid];
    if ($classId && $classId > 0) {
        $sql .= ' AND sa.class_id = ?';
        $params[] = $classId;
    }
    $sql .= ' ORDER BY s.name, sa.class_id';
    return Database::fetchAll($sql, $params);
}

function hod_department_id(array $user): int
{
    if (($user['role'] ?? '') === 'hod') {
        return (int)($user['department_id'] ?? 0);
    }
    return 0;
}

function subject_year_label(int $year): string
{
    return match ($year) {
        1 => '1st Year',
        2 => '2nd Year',
        3 => '3rd Year',
        4 => '4th Year',
        default => $year > 0 ? ('Year ' . $year) : 'Unassigned',
    };
}

function subject_normalize_semester(?string $semester): string
{
    $raw = strtolower(trim((string)$semester));
    if ($raw === '' || str_contains($raw, 'odd')) {
        return 'Odd Semester';
    }
    if (str_contains($raw, 'even')) {
        return 'Even Semester';
    }
    return 'Odd Semester';
}

function subject_semester_key(?string $semester): string
{
    return str_contains(strtolower(subject_normalize_semester($semester)), 'even') ? 'even' : 'odd';
}

function subject_meta_array(array $subject): array
{
    $meta = json_decode((string)($subject['meta'] ?? ''), true);
    return is_array($meta) ? $meta : [];
}

function subject_academic_year_level(array $subject): int
{
    $meta = subject_meta_array($subject);
    $year = (int)($meta['year'] ?? $meta['academic_year_level'] ?? 0);
    return ($year >= 1 && $year <= 4) ? $year : 0;
}

function subject_course_type(array $subject): string
{
    $meta = subject_meta_array($subject);
    $type = strtolower(trim((string)($meta['course_type'] ?? $meta['type'] ?? 'theory')));
    return $type === 'lab' ? 'lab' : 'theory';
}

function subject_build_meta(int $year, string $courseType, ?string $existingMeta = null): string
{
    $meta = json_decode((string)$existingMeta, true);
    if (!is_array($meta)) {
        $meta = [];
    }
    $meta['year'] = ($year >= 1 && $year <= 4) ? $year : 0;
    $meta['course_type'] = strtolower(trim($courseType)) === 'lab' ? 'lab' : 'theory';
    return json_encode($meta, JSON_UNESCAPED_UNICODE);
}

function hod_save_subject(array $hodUser, string $code, string $name, array $extra = []): int
{
    $deptId = hod_department_id($hodUser);
    if ($deptId < 1) {
        throw new RuntimeException('Your HOD account is not linked to a department.');
    }
    $inst = (int)$hodUser['institution_id'];
    $code = strtoupper(trim($code));
    $name = trim($name);
    if ($code === '' || $name === '') {
        throw new RuntimeException('Subject code and name are required.');
    }
    $year = (int)($extra['year'] ?? 0);
    if ($year < 1 || $year > 4) {
        throw new RuntimeException('Select an academic year (1st–4th).');
    }
    $courseType = strtolower(trim((string)($extra['course_type'] ?? 'theory'))) === 'lab' ? 'lab' : 'theory';
    $semester = subject_normalize_semester((string)($extra['semester'] ?? 'Odd Semester'));
    $existing = Database::fetch(
        'SELECT id, department_id, meta FROM subjects WHERE institution_id = ? AND code = ?',
        [$inst, $code]
    );
    $payload = [
        'name' => $name,
        'department_id' => $deptId,
        'credits' => (float)($extra['credits'] ?? 3.0),
        'contact_hours' => (int)($extra['contact_hours'] ?? 45),
        'semester' => $semester,
        'syllabus_text' => trim((string)($extra['syllabus_text'] ?? '')) ?: null,
        'meta' => subject_build_meta($year, $courseType, $existing['meta'] ?? null),
        'is_active' => 1,
    ];
    if ($existing) {
        if ((int)$existing['department_id'] !== $deptId) {
            throw new RuntimeException('That subject code belongs to another department.');
        }
        Database::update('subjects', $payload, 'id = :id', ['id' => (int)$existing['id']]);
        return (int)$existing['id'];
    }
    $payload['institution_id'] = $inst;
    $payload['code'] = $code;
    return (int)Database::insert('subjects', $payload);
}

function hod_assign_professor_subject(array $hodUser, int $subjectId, int $professorId, int $classId): void
{
    $deptId = hod_department_id($hodUser);
    if ($deptId < 1) {
        throw new RuntimeException('Your HOD account is not linked to a department.');
    }
    $inst = (int)$hodUser['institution_id'];
    $subject = Database::fetch(
        'SELECT id, department_id, semester, meta FROM subjects WHERE id = ? AND institution_id = ? AND department_id = ? AND is_active = 1',
        [$subjectId, $inst, $deptId]
    );
    if (!$subject) {
        throw new RuntimeException('Subject not found in your department.');
    }
    $professor = Database::fetch(
        'SELECT id FROM users WHERE id = ? AND institution_id = ? AND department_id = ? AND role = "professor" AND is_active = 1',
        [$professorId, $inst, $deptId]
    );
    if (!$professor) {
        throw new RuntimeException('Professor not found in your department.');
    }
    $class = Database::fetch(
        'SELECT id, year FROM classes WHERE id = ? AND institution_id = ? AND department_id = ? AND is_active = 1',
        [$classId, $inst, $deptId]
    );
    if (!$class) {
        throw new RuntimeException('Class not found in your department.');
    }
    $subjectYear = subject_academic_year_level($subject);
    $classYear = (int)($class['year'] ?? 0);
    if ($subjectYear > 0 && $classYear > 0 && $subjectYear !== $classYear) {
        throw new RuntimeException(
            'Class year must match the course year (' . subject_year_label($subjectYear) . ').'
        );
    }
    $academicYear = institution_academic_year($inst);
    $subjectSemester = trim((string)($subject['semester'] ?? ''));
    $semester = $subjectSemester !== ''
        ? subject_normalize_semester($subjectSemester)
        : (institution_current_semester($inst) ?: null);
    $existing = Database::fetch(
        'SELECT id FROM subject_assignments
         WHERE subject_id = ? AND professor_id = ? AND class_id = ?
           AND (academic_year IS NULL OR academic_year = ? OR ? = "")',
        [$subjectId, $professorId, $classId, $academicYear, $academicYear]
    );
    if ($existing) {
        Database::query(
            'UPDATE subject_assignments SET academic_year = ?, semester = ? WHERE id = ?',
            [$academicYear ?: null, $semester ?: null, (int)$existing['id']]
        );
    } else {
        Database::insert('subject_assignments', [
            'subject_id' => $subjectId,
            'professor_id' => $professorId,
            'class_id' => $classId,
            'academic_year' => $academicYear ?: null,
            'semester' => $semester ?: null,
        ]);
    }
    enroll_class_students_in_subject($inst, $classId, $subjectId, is_string($semester) ? $semester : null);
}

function subjects_for_department(int $institutionId, int $departmentId): array
{
    if ($departmentId < 1) {
        return [];
    }
    return Database::fetchAll(
        'SELECT s.* FROM subjects s
         WHERE s.institution_id = ? AND s.department_id = ? AND s.is_active = 1
         ORDER BY s.code, s.name',
        [$institutionId, $departmentId]
    );
}

/**
 * @return list<array<string,mixed>>
 */
function subjects_for_department_context(
    int $institutionId,
    int $departmentId,
    int $year,
    string $semesterKey,
    ?string $courseType = null
): array {
    $semesterKey = $semesterKey === 'even' ? 'even' : 'odd';
    $typeFilter = $courseType !== null
        ? (strtolower($courseType) === 'lab' ? 'lab' : 'theory')
        : null;
    $out = [];
    foreach (subjects_for_department($institutionId, $departmentId) as $subject) {
        if ($year > 0 && subject_academic_year_level($subject) !== $year) {
            continue;
        }
        if (subject_semester_key((string)($subject['semester'] ?? '')) !== $semesterKey) {
            continue;
        }
        if ($typeFilter !== null && subject_course_type($subject) !== $typeFilter) {
            continue;
        }
        $out[] = $subject;
    }
    return $out;
}

function subject_assignments_for_department(int $institutionId, int $departmentId): array
{
    if ($departmentId < 1) {
        return [];
    }
    return Database::fetchAll(
        'SELECT sa.*, s.code AS subject_code, s.name AS subject_name,
                u.full_name AS professor_name, u.email AS professor_email,
                c.name AS class_name, c.year AS class_year, c.section AS class_section, c.meta AS class_meta
         FROM subject_assignments sa
         JOIN subjects s ON s.id = sa.subject_id
         JOIN users u ON u.id = sa.professor_id
         LEFT JOIN classes c ON c.id = sa.class_id
         WHERE s.institution_id = ? AND s.department_id = ?
         ORDER BY s.name, c.year, c.section, u.full_name',
        [$institutionId, $departmentId]
    );
}

/**
 * Active students in a department grouped by class year (1–4).
 *
 * @return array{labels: list<string>, counts: list<int>, total: int}
 */
function hod_analytics_students_by_year(int $institutionId, int $departmentId): array
{
    $labels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
    $counts = [0, 0, 0, 0];
    if ($departmentId < 1 || $institutionId < 1) {
        return ['labels' => $labels, 'counts' => $counts, 'total' => 0];
    }
    $rows = Database::fetchAll(
        'SELECT COALESCE(c.year, 0) AS class_year, COUNT(*) AS student_count
         FROM users u
         LEFT JOIN classes c ON c.id = u.class_id
         WHERE u.institution_id = ?
           AND u.department_id = ?
           AND u.role = "student"
           AND u.is_active = 1
         GROUP BY COALESCE(c.year, 0)',
        [$institutionId, $departmentId]
    );
    $total = 0;
    foreach ($rows as $row) {
        $year = (int)($row['class_year'] ?? 0);
        $count = (int)($row['student_count'] ?? 0);
        $total += $count;
        if ($year >= 1 && $year <= 4) {
            $counts[$year - 1] = $count;
        }
    }
    return ['labels' => $labels, 'counts' => $counts, 'total' => $total];
}

/**
 * Professor theory/lab workload for a department (distinct assigned subjects).
 *
 * @return list<array{professor_id:int, name:string, theory:int, labs:int, total:int}>
 */
function hod_analytics_professor_workload(int $institutionId, int $departmentId): array
{
    if ($departmentId < 1 || $institutionId < 1) {
        return [];
    }
    $professors = Database::fetchAll(
        'SELECT id, full_name FROM users
         WHERE institution_id = ? AND department_id = ? AND role = "professor" AND is_active = 1
         ORDER BY full_name',
        [$institutionId, $departmentId]
    );
    $assignments = Database::fetchAll(
        'SELECT sa.professor_id, sa.subject_id, s.meta
         FROM subject_assignments sa
         JOIN subjects s ON s.id = sa.subject_id
         JOIN users u ON u.id = sa.professor_id
         WHERE s.institution_id = ?
           AND s.department_id = ?
           AND s.is_active = 1
           AND u.department_id = ?
           AND u.role = "professor"
           AND u.is_active = 1',
        [$institutionId, $departmentId, $departmentId]
    );

    /** @var array<int, array{theory: array<int, true>, labs: array<int, true>}> $byProf */
    $byProf = [];
    foreach ($assignments as $row) {
        $pid = (int)$row['professor_id'];
        $sid = (int)$row['subject_id'];
        if ($pid < 1 || $sid < 1) {
            continue;
        }
        if (!isset($byProf[$pid])) {
            $byProf[$pid] = ['theory' => [], 'labs' => []];
        }
        $type = subject_course_type(['meta' => $row['meta'] ?? null]);
        if ($type === 'lab') {
            $byProf[$pid]['labs'][$sid] = true;
        } else {
            $byProf[$pid]['theory'][$sid] = true;
        }
    }

    $out = [];
    foreach ($professors as $p) {
        $pid = (int)$p['id'];
        $theory = isset($byProf[$pid]) ? count($byProf[$pid]['theory']) : 0;
        $labs = isset($byProf[$pid]) ? count($byProf[$pid]['labs']) : 0;
        $out[] = [
            'professor_id' => $pid,
            'name' => (string)$p['full_name'],
            'theory' => $theory,
            'labs' => $labs,
            'total' => $theory + $labs,
        ];
    }
    return $out;
}

function save_professor_subject(array $user, string $code, string $name, ?int $classId = null): int
{
    throw new RuntimeException('Courses are created by your HOD. Contact the department HOD to add or assign subjects.');
}

function institution_attendance_min(int $institutionId): float
{
    $row = Database::fetch('SELECT settings FROM institutions WHERE id = ?', [$institutionId]);
    $settings = json_decode((string)($row['settings'] ?? ''), true) ?: [];
    $v = (float)($settings['attendance_min'] ?? config('attendance_min_pct', 75));
    return $v > 0 ? $v : 75.0;
}

function spreadsheet_to_csv_text(string $path, string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($ext, ['csv', 'txt', ''], true)) {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Could not read the uploaded file.');
        }
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }
        return $raw;
    }
    if ($ext !== 'xlsx' || !class_exists('ZipArchive')) {
        throw new RuntimeException('Upload an Excel .xlsx or .csv file.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Could not read the Excel file.');
    }
    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false) {
        $sx = @simplexml_load_string($ss);
        if ($sx) {
            foreach ($sx->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string)$si->t;
                } else {
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                }
                $shared[] = trim($text);
            }
        }
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet === false) {
        throw new RuntimeException('Excel file has no first sheet.');
    }
    $xml = @simplexml_load_string($sheet);
    if (!$xml || !isset($xml->sheetData)) {
        throw new RuntimeException('Could not parse the Excel sheet.');
    }
    $out = '';
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $col = 0;
            if (preg_match('/^([A-Z]+)/', $ref, $m)) {
                foreach (str_split($m[1]) as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $col--;
            }
            $v = (string)$c->v;
            if ((string)$c['t'] === 's') {
                $v = $shared[(int)$v] ?? $v;
            }
            $cells[$col] = $v;
        }
        if (!$cells) {
            continue;
        }
        ksort($cells);
        $max = max(array_keys($cells));
        $line = [];
        for ($i = 0; $i <= $max; $i++) {
            $val = str_replace('"', '""', (string)($cells[$i] ?? ''));
            $line[] = '"' . $val . '"';
        }
        $out .= implode(',', $line) . "\n";
    }
    return $out;
}

function import_roster_rows(int $institutionId, int $classId, array $rows): int
{
    $n = 0;
    foreach ($rows as $row) {
        $reg = trim((string)($row['register_no'] ?? ''));
        $name = trim((string)($row['full_name'] ?? ''));
        $email = trim((string)($row['email'] ?? ''));
        if ($reg === '' || $name === '') {
            continue;
        }
        $userId = null;
        if ($email !== '') {
            $u = Database::fetch(
                'SELECT id FROM users WHERE institution_id = ? AND role = "student" AND email = ? LIMIT 1',
                [$institutionId, $email]
            );
            $userId = $u ? (int)$u['id'] : null;
        }
        if (!$userId) {
            $u = Database::fetch(
                'SELECT id FROM users WHERE institution_id = ? AND role = "student" AND register_no = ? LIMIT 1',
                [$institutionId, $reg]
            );
            $userId = $u ? (int)$u['id'] : null;
        }
        Database::query(
            'INSERT INTO students_roster (institution_id, class_id, user_id, register_no, full_name, email, is_active)
             VALUES (?,?,?,?,?,?,1)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), full_name = VALUES(full_name), email = VALUES(email), is_active = 1',
            [$institutionId, $classId, $userId, $reg, $name, $email ?: null]
        );
        $n++;
    }
    return $n;
}

function parse_roster_csv(string $text): array
{
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '' || preg_match('/^register/i', $line)) {
            continue;
        }
        $parts = str_getcsv($line);
        if (count($parts) < 2) {
            continue;
        }
        $rows[] = [
            'register_no' => trim((string)$parts[0]),
            'full_name' => trim((string)$parts[1]),
            'email' => trim((string)($parts[2] ?? '')),
        ];
    }
    return $rows;
}

function presentation_accessible(array $user, array $ppt): bool
{
    $role = (string)($user['role'] ?? '');
    $uid = (int)($user['id'] ?? 0);
    $instId = (int)($user['institution_id'] ?? 0);

    if (in_array($role, ['admin', 'superadmin'], true)) {
        $owner = Database::fetch('SELECT institution_id FROM users WHERE id = ?', [(int)$ppt['professor_id']]);
        return $owner !== null && (int)$owner['institution_id'] === $instId;
    }
    if ($role === 'professor') {
        if ((int)$ppt['professor_id'] !== $uid) {
            return false;
        }
        // Institution isolation: professor must belong to same institution as deck owner.
        $owner = Database::fetch('SELECT institution_id FROM users WHERE id = ?', [(int)$ppt['professor_id']]);
        return $owner !== null && (int)$owner['institution_id'] === $instId;
    }
    if ($role === 'student') {
        $owner = Database::fetch('SELECT institution_id FROM users WHERE id = ?', [(int)$ppt['professor_id']]);
        if (!$owner || (int)$owner['institution_id'] !== $instId) {
            return false;
        }
        $subjectId = (int)($ppt['subject_id'] ?? 0);
        if ($subjectId < 1 && !empty($ppt['plan_id'])) {
            $plan = Database::fetch('SELECT subject_id FROM course_plans WHERE id = ?', [(int)$ppt['plan_id']]);
            $subjectId = (int)($plan['subject_id'] ?? 0);
        }
        if ($subjectId < 1) {
            return false;
        }
        return (bool)Database::fetch(
            'SELECT id FROM enrollments WHERE student_id = ? AND subject_id = ? LIMIT 1',
            [$uid, $subjectId]
        );
    }
    return false;
}

function lesson_as_list(mixed $value): array
{
    if (is_string($value)) {
        $trim = trim($value);
        if ($trim === '') {
            return [];
        }
        $decoded = json_decode($trim, true);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            return array_values(array_filter(array_map('trim', preg_split('/[;|]+/', $trim) ?: [$trim])));
        }
    }
    if (!is_array($value)) {
        return [];
    }
    $out = [];
    foreach ($value as $item) {
        if (is_string($item) && trim($item) !== '') {
            $out[] = trim($item);
        } elseif (is_array($item)) {
            $text = trim((string)($item['text'] ?? $item['title'] ?? $item['name'] ?? $item['activity'] ?? ''));
            if ($text !== '') {
                $out[] = $text;
            }
        }
    }
    return $out;
}

function lesson_pedagogy_for_k(string $k): array
{
    return match (strtoupper(trim($k))) {
        'K1' => [
            'method' => 'Direct instruction with recap',
            'activity' => 'Guided notes and oral recall of key terms',
            'assess' => 'Closed-book 5-minute recall quiz',
            'engage' => 'Cold-call plus choral response for definitions',
        ],
        'K3' => [
            'method' => 'Worked example then guided practice',
            'activity' => 'Paired problem set on the session topic',
            'assess' => 'Mini whiteboard: one applied problem',
            'engage' => 'Live poll on the next solution step',
        ],
        'K4' => [
            'method' => 'Case / problem discussion',
            'activity' => 'Compare-and-contrast analysis in small groups',
            'assess' => 'One-minute paper: what pattern did you find?',
            'engage' => 'Structured debate with rotating roles',
        ],
        'K5' => [
            'method' => 'Design studio / critique',
            'activity' => 'Draft-and-improve a solution against a rubric',
            'assess' => 'Peer review with 2 stars and 1 wish',
            'engage' => 'Gallery walk of student artefacts',
        ],
        'K6' => [
            'method' => 'Student-led seminar',
            'activity' => 'Defend a design choice or research stance',
            'assess' => 'Reflective exit slip: what would you change?',
            'engage' => 'Hot-seat Q&A facilitated by peers',
        ],
        default => [
            'method' => 'Interactive lecture',
            'activity' => 'Concept map or worked walkthrough',
            'assess' => 'Exit ticket: explain the idea in 3 sentences',
            'engage' => 'Think-pair-share on a common misconception',
        ],
    };
}

function normalize_lesson_session(array $s, int $n): array
{
    $title = $s['title'] ?? $s['session_title'] ?? $s['topic'] ?? $s['name'] ?? $s['focus'] ?? $s['unit_title'] ?? '';
    if (is_array($title)) {
        $title = (string)($title['text'] ?? $title['title'] ?? reset($title) ?: '');
    }
    $title = trim((string)$title);
    if ($title === '') {
        $title = 'Session ' . $n;
    }

    $mins = $s['duration_mins'] ?? $s['duration'] ?? $s['mins'] ?? $s['minutes'] ?? $s['time_allocation'] ?? null;
    if ($mins === null && isset($s['hours']) && (float)$s['hours'] > 0 && (float)$s['hours'] <= 3) {
        $mins = (int)round((float)$s['hours'] * 60);
    }
    $mins = max(30, min(120, (int)($mins ?: 60)));

    $method = $s['teaching_method'] ?? $s['method'] ?? $s['pedagogy'] ?? '';
    if (is_array($method)) {
        $method = implode(', ', lesson_as_list($method));
    }
    if (trim((string)$method) === '') {
        $methods = lesson_as_list($s['teaching_methods'] ?? []);
        $method = $methods[0] ?? '';
    }
    $method = trim((string)$method);

    $activities = lesson_as_list($s['activities'] ?? $s['activity'] ?? $s['classroom_activity'] ?? $s['classroom_activities'] ?? []);
    $assessment = lesson_as_list($s['formative_assessment'] ?? $s['assessment'] ?? $s['formative'] ?? $s['checks'] ?? []);
    $engagement = lesson_as_list($s['engagement'] ?? $s['engagement_strategy'] ?? $s['student_engagement'] ?? $s['engagement_strategies'] ?? []);
    $objectives = lesson_as_list($s['objectives'] ?? $s['outcomes'] ?? $s['learning_outcomes'] ?? []);

    return [
        'session_number' => (int)($s['session_number'] ?? $s['number'] ?? $s['week'] ?? $n),
        'unit_id' => isset($s['unit_id']) ? (int)$s['unit_id'] : null,
        'title' => $title,
        'duration_mins' => $mins,
        'objectives' => $objectives,
        'teaching_method' => $method,
        'activities' => $activities,
        'formative_assessment' => $assessment,
        'engagement' => $engagement,
    ];
}

function extract_ai_lesson_sessions(?array $json): array
{
    if (!$json) {
        return [];
    }
    $list = null;
    foreach (['sessions', 'lesson_plans', 'lessons'] as $key) {
        if (isset($json[$key]) && is_array($json[$key])) {
            $list = $json[$key];
            break;
        }
    }
    $isList = $json === [] || array_keys($json) === range(0, count($json) - 1);
    if ($list === null && $isList && isset($json[0]) && is_array($json[0])) {
        $first = $json[0];
        $looksLikeUnit = isset($first['unit_number']) && !isset($first['session_number']) && !isset($first['teaching_method']) && !isset($first['activities']);
        $list = $looksLikeUnit ? [] : $json;
    }
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $i => $s) {
        if (!is_array($s)) {
            continue;
        }
        $out[] = normalize_lesson_session($s, $i + 1);
    }
    return $out;
}

function lesson_sessions_are_usable(array $sessions): bool
{
    if ($sessions === []) {
        return false;
    }
    $good = 0;
    foreach ($sessions as $s) {
        $title = strtolower(trim((string)($s['title'] ?? '')));
        $generic = $title === '' || $title === 'session' || (bool)preg_match('/^session\s+\d+$/i', $title);
        $hasBody = lesson_as_list($s['activities'] ?? []) !== []
            && (trim((string)($s['teaching_method'] ?? '')) !== '' || lesson_as_list($s['formative_assessment'] ?? []) !== []);
        if ($hasBody && !$generic) {
            $good++;
        } elseif ($hasBody && lesson_as_list($s['formative_assessment'] ?? []) !== [] && lesson_as_list($s['engagement'] ?? []) !== []) {
            $good++;
        }
    }
    return $good >= 1 && $good * 3 >= count($sessions);
}

function lesson_sessions_from_course_plan(array $planRow): array
{
    $data = json_decode((string)($planRow['plan_data'] ?? ''), true) ?: [];
    $units = Database::fetchAll(
        'SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number',
        [(int)($planRow['id'] ?? 0)]
    );
    if (!$units && !empty($data['units']) && is_array($data['units'])) {
        $units = $data['units'];
    }
    $weekly = $data['weekly_plan'] ?? [];
    if ((!$weekly || !is_array($weekly)) && !empty($planRow['weekly_plan'])) {
        $weekly = json_decode((string)$planRow['weekly_plan'], true) ?: [];
    }
    $subject = trim((string)($planRow['subject_name'] ?? $data['subject'] ?? $data['title'] ?? 'this course'));

    $sessions = [];
    if (is_array($weekly) && count($weekly) >= 4) {
        foreach ($weekly as $i => $w) {
            if (!is_array($w)) {
                continue;
            }
            $unit = $units[$i % max(1, count($units))] ?? [];
            $k = (string)($unit['bloom_k_level'] ?? 'K2');
            $ped = lesson_pedagogy_for_k($k);
            $n = $i + 1;
            $title = trim((string)($w['title'] ?? $w['focus'] ?? $w['topic'] ?? $w['name'] ?? ''));
            if ($title === '') {
                $title = 'Week ' . (int)($w['week'] ?? $n) . (isset($unit['title']) ? ' · ' . $unit['title'] : '');
            }
            $sessions[] = [
                'session_number' => $n,
                'unit_id' => isset($unit['id']) ? (int)$unit['id'] : null,
                'title' => $title,
                'duration_mins' => 60,
                'objectives' => lesson_as_list($unit['outcomes'] ?? []) ?: ['Connect this week\'s topic to ' . $subject . ' course outcomes'],
                'teaching_method' => (lesson_as_list($unit['teaching_methods'] ?? [])[0] ?? $ped['method']),
                'activities' => [$ped['activity'] . ' — ' . $title],
                'formative_assessment' => lesson_as_list($unit['assessment'] ?? []) ?: [$ped['assess']],
                'engagement' => [$ped['engage']],
            ];
        }
    } elseif ($units) {
        $per = count($units) >= 8 ? 1 : (count($units) >= 5 ? 2 : 3);
        $n = 0;
        foreach ($units as $u) {
            $topics = lesson_as_list($u['topics'] ?? []);
            if (!$topics) {
                $topics = [trim((string)($u['title'] ?? 'Core topic')) ?: 'Core topic'];
            }
            $methods = lesson_as_list($u['teaching_methods'] ?? []);
            $outcomes = lesson_as_list($u['outcomes'] ?? []);
            $assess = lesson_as_list($u['assessment'] ?? []);
            $k = (string)($u['bloom_k_level'] ?? 'K2');
            $ped = lesson_pedagogy_for_k($k);
            $unitTitle = trim((string)($u['title'] ?? 'Unit'));
            for ($i = 0; $i < $per; $i++) {
                $n++;
                $topic = $topics[$i % count($topics)];
                $title = $per === 1 ? ($unitTitle ?: $topic) : ($unitTitle . ' · ' . $topic);
                $sessions[] = [
                    'session_number' => $n,
                    'unit_id' => isset($u['id']) ? (int)$u['id'] : null,
                    'title' => $title,
                    'duration_mins' => 60,
                    'objectives' => $outcomes ? [ $outcomes[$i % count($outcomes)] ] : ['By the end, students can work with ' . $topic . ' in ' . $subject],
                    'teaching_method' => $methods[$i % max(1, count($methods))] ?? $ped['method'],
                    'activities' => [$ped['activity'] . ' — ' . $topic],
                    'formative_assessment' => $assess ? [ $assess[$i % count($assess)] ] : [$ped['assess']],
                    'engagement' => [$ped['engage']],
                ];
            }
        }
    }

    if (!$sessions) {
        for ($i = 1; $i <= 6; $i++) {
            $ped = lesson_pedagogy_for_k('K' . min(6, $i));
            $sessions[] = [
                'session_number' => $i,
                'unit_id' => null,
                'title' => $subject . ' · Session ' . $i,
                'duration_mins' => 60,
                'objectives' => ['Progress toward the course outcomes for ' . $subject],
                'teaching_method' => $ped['method'],
                'activities' => [$ped['activity']],
                'formative_assessment' => [$ped['assess']],
                'engagement' => [$ped['engage']],
            ];
        }
    }

    return array_slice($sessions, 0, 24);
}

function status_badge(string $status): string
{
    $map = [
        'draft' => 'badge-muted',
        'submitted' => 'badge-info',
        'under_review' => 'badge-warn',
        'approved' => 'badge-success',
        'returned' => 'badge-danger',
        'published' => 'badge-success',
        'closed' => 'badge-muted',
    ];
    $cls = $map[$status] ?? 'badge-muted';
    return '<span class="badge ' . $cls . '">' . e(str_replace('_', ' ', ucfirst($status))) . '</span>';
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
}

if (!function_exists('icon')) {
    function icon(string $name, string $class = 'icon'): string
    {
        if (!class_exists('Icons', false)) {
            require_once __DIR__ . '/Icons.php';
        }
        return Icons::svg($name, $class);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('/assets/' . ltrim($path, '/'));
    }
}
