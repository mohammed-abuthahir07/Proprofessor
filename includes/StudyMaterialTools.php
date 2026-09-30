<?php
declare(strict_types=1);

/**
 * Professor study materials (notes and presentations) sent to an assigned class.
 * Visibility is checked at read time from the existing subject assignment and the student's class.
 */
final class StudyMaterialTools
{
    public const MAX_BYTES = 20971520; // 20 MB

    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        self::$schemaReady = true;
        Database::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS study_materials (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                institution_id INT UNSIGNED NOT NULL,
                professor_id INT UNSIGNED NOT NULL,
                subject_id INT UNSIGNED NOT NULL,
                class_id INT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT NULL,
                material_type ENUM('notes','ppt') NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_original_name VARCHAR(255) NOT NULL,
                file_mime_type VARCHAR(120) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_sm_prof (professor_id, created_at),
                INDEX idx_sm_scope (institution_id, class_id, subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Teaching combinations from subject_assignments for this professor only.
     *
     * @return list<array<string,mixed>>
     */
    public static function scopesForProfessor(array $user): array
    {
        if (!self::isProfessor($user)) {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT sa.subject_id, sa.class_id,
                    s.name AS subject_name, s.code AS subject_code,
                    c.year, c.section, c.name AS class_name, c.meta,
                    d.code AS dept_code, d.name AS dept_name
             FROM subject_assignments sa
             JOIN subjects s ON s.id = sa.subject_id
             JOIN classes c ON c.id = sa.class_id
             LEFT JOIN departments d ON d.id = c.department_id
             WHERE sa.professor_id = ?
               AND s.institution_id = ?
               AND c.institution_id = ?
               AND s.is_active = 1
               AND c.is_active = 1
             ORDER BY s.name, c.year, c.section, c.name',
            [(int)$user['id'], (int)$user['institution_id'], (int)$user['institution_id']]
        );
        $out = [];
        foreach ($rows as $row) {
            $row['class_line'] = self::classLine($row);
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function assignmentForProfessor(array $user, int $subjectId, int $classId): ?array
    {
        if (!self::isProfessor($user) || $subjectId < 1 || $classId < 1) {
            return null;
        }
        $row = Database::fetch(
            'SELECT sa.subject_id, sa.class_id,
                    s.name AS subject_name, s.code AS subject_code,
                    c.year, c.section, c.name AS class_name, c.meta,
                    d.code AS dept_code, d.name AS dept_name
             FROM subject_assignments sa
             JOIN subjects s ON s.id = sa.subject_id
             JOIN classes c ON c.id = sa.class_id
             LEFT JOIN departments d ON d.id = c.department_id
             WHERE sa.professor_id = ?
               AND sa.subject_id = ?
               AND sa.class_id = ?
               AND s.institution_id = ?
               AND c.institution_id = ?
               AND s.is_active = 1
               AND c.is_active = 1
             LIMIT 1',
            [(int)$user['id'], $subjectId, $classId, (int)$user['institution_id'], (int)$user['institution_id']]
        );
        if (!$row) {
            return null;
        }
        $row['class_line'] = self::classLine($row);
        return $row;
    }

    /**
     * @param array<string,mixed>|null $file
     * @return array{ok:bool,error?:string,id?:int,title?:string,class_line?:string}
     */
    public static function create(array $user, int $subjectId, int $classId, string $type, string $title, string $description, ?array $file): array
    {
        self::ensureSchema();
        if (!self::isProfessor($user)) {
            return ['ok' => false, 'error' => 'Only a professor can send study materials.'];
        }
        $assignment = self::assignmentForProfessor($user, $subjectId, $classId);
        if (!$assignment) {
            return ['ok' => false, 'error' => 'You are not assigned to this course and class.'];
        }
        $type = strtolower(trim($type));
        if (!in_array($type, ['notes', 'ppt'], true)) {
            return ['ok' => false, 'error' => 'Select a material type: Notes or PPT.'];
        }
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'error' => 'Enter a title for this material.'];
        }
        if (mb_strlen($title) > 200) {
            return ['ok' => false, 'error' => 'Title must be 200 characters or less.'];
        }
        $description = trim($description);
        if (mb_strlen($description) > 2000) {
            return ['ok' => false, 'error' => 'Description must be 2000 characters or less.'];
        }

        $upload = self::storeUpload($file, $type);
        if (!$upload['ok']) {
            return ['ok' => false, 'error' => $upload['error'] ?? 'Upload failed.'];
        }

        try {
            $id = Database::insert('study_materials', [
                'institution_id' => (int)$user['institution_id'],
                'professor_id' => (int)$user['id'],
                'subject_id' => $subjectId,
                'class_id' => $classId,
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'material_type' => $type,
                'file_path' => $upload['path'],
                'file_original_name' => $upload['original_name'],
                'file_mime_type' => $upload['mime_type'],
                'file_size' => $upload['size'],
            ]);
        } catch (Throwable $e) {
            self::deleteStoredFile($upload['path']);
            return ['ok' => false, 'error' => 'The material could not be saved. Please try again.'];
        }

        return [
            'ok' => true,
            'id' => $id,
            'title' => $title,
            'class_line' => (string)$assignment['class_line'],
        ];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function delete(array $user, int $id): array
    {
        self::ensureSchema();
        if (!self::isProfessor($user) || $id < 1) {
            return ['ok' => false, 'error' => 'You can only delete materials you uploaded.'];
        }
        $row = Database::fetch(
            'SELECT id, file_path FROM study_materials
             WHERE id = ? AND professor_id = ? AND institution_id = ?',
            [$id, (int)$user['id'], (int)$user['institution_id']]
        );
        if (!$row) {
            return ['ok' => false, 'error' => 'You can only delete materials you uploaded.'];
        }
        Database::query(
            'DELETE FROM study_materials WHERE id = ? AND professor_id = ? AND institution_id = ?',
            [$id, (int)$user['id'], (int)$user['institution_id']]
        );
        self::deleteStoredFile((string)$row['file_path']);
        return ['ok' => true];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function forProfessor(array $user, string $type = '', int $subjectId = 0, int $classId = 0): array
    {
        self::ensureSchema();
        if (!self::isProfessor($user)) {
            return [];
        }
        $sql = 'SELECT m.*,
                       s.name AS subject_name, s.code AS subject_code,
                       c.year, c.section, c.name AS class_name, c.meta,
                       d.code AS dept_code, d.name AS dept_name,
                       u.full_name AS professor_name
                FROM study_materials m
                JOIN subjects s ON s.id = m.subject_id
                JOIN classes c ON c.id = m.class_id
                JOIN users u ON u.id = m.professor_id
                LEFT JOIN departments d ON d.id = c.department_id
                WHERE m.professor_id = ? AND m.institution_id = ?';
        $params = [(int)$user['id'], (int)$user['institution_id']];
        $type = strtolower(trim($type));
        if (in_array($type, ['notes', 'ppt'], true)) {
            $sql .= ' AND m.material_type = ?';
            $params[] = $type;
        }
        if ($subjectId > 0) {
            $sql .= ' AND m.subject_id = ?';
            $params[] = $subjectId;
        }
        if ($classId > 0) {
            $sql .= ' AND m.class_id = ?';
            $params[] = $classId;
        }
        $sql .= ' ORDER BY m.created_at DESC, m.id DESC';
        return self::decorate(Database::fetchAll($sql, $params));
    }

    /**
     * Materials for the logged-in student's current class and courses only.
     *
     * @return list<array<string,mixed>>
     */
    public static function forStudent(array $user): array
    {
        self::ensureSchema();
        if (($user['role'] ?? '') !== 'student') {
            return [];
        }
        $classId = student_class_id($user);
        $instId = (int)($user['institution_id'] ?? 0);
        if ($classId < 1 || $instId < 1) {
            return [];
        }
        $subjectIds = [];
        foreach (courses_for_student($user) as $subject) {
            $sid = (int)($subject['id'] ?? 0);
            if ($sid > 0) {
                $subjectIds[$sid] = true;
            }
        }
        if (!$subjectIds) {
            return [];
        }
        $ids = array_keys($subjectIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$instId, $classId], $ids);
        $rows = Database::fetchAll(
            "SELECT m.*,
                    s.name AS subject_name, s.code AS subject_code,
                    c.year, c.section, c.name AS class_name, c.meta,
                    d.code AS dept_code, d.name AS dept_name,
                    u.full_name AS professor_name
             FROM study_materials m
             JOIN subjects s ON s.id = m.subject_id
             JOIN classes c ON c.id = m.class_id
             JOIN users u ON u.id = m.professor_id
             LEFT JOIN departments d ON d.id = c.department_id
             WHERE m.institution_id = ?
               AND m.class_id = ?
               AND m.subject_id IN ($placeholders)
             ORDER BY m.created_at DESC, m.id DESC",
            $params
        );
        $visible = [];
        foreach ($rows as $row) {
            $ctx = course_academic_context($instId, (int)$row['subject_id'], (int)$row['class_id']);
            if ($ctx && student_matches_course_context($user, $ctx)) {
                $visible[] = $row;
            }
        }
        return self::decorate($visible);
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function accessibleTo(array $user, int $id): ?array
    {
        self::ensureSchema();
        if ($id < 1) {
            return null;
        }
        $role = (string)($user['role'] ?? '');
        $instId = (int)($user['institution_id'] ?? 0);
        if ($instId < 1) {
            return null;
        }
        $row = Database::fetch(
            'SELECT * FROM study_materials WHERE id = ? AND institution_id = ?',
            [$id, $instId]
        );
        if (!$row || !self::safeRelativePath((string)$row['file_path'])) {
            return null;
        }
        if (self::isProfessor($user)) {
            return (int)$row['professor_id'] === (int)$user['id'] ? $row : null;
        }
        if ($role !== 'student') {
            return null;
        }
        if ((int)$row['class_id'] !== student_class_id($user)) {
            return null;
        }
        $allowed = false;
        foreach (courses_for_student($user) as $subject) {
            if ((int)($subject['id'] ?? 0) === (int)$row['subject_id']) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return null;
        }
        $ctx = course_academic_context($instId, (int)$row['subject_id'], (int)$row['class_id']);
        if (!$ctx || !student_matches_course_context($user, $ctx)) {
            return null;
        }
        return $row;
    }

    public static function absolutePath(string $relativePath): ?string
    {
        if (!self::safeRelativePath($relativePath)) {
            return null;
        }
        $full = dirname(__DIR__) . '/uploads/' . $relativePath;
        return is_file($full) ? $full : null;
    }

    public static function stream(array $user, int $id): void
    {
        $row = self::accessibleTo($user, $id);
        $full = $row ? self::absolutePath((string)$row['file_path']) : null;
        if (!$row || $full === null) {
            http_response_code($row ? 404 : 403);
            echo $row ? 'File not found' : 'Forbidden';
            return;
        }
        $original = (string)($row['file_original_name'] ?? 'material');
        $safe = str_replace(["\"", "\r", "\n"], '', $original);
        if ($safe === '') {
            $safe = 'material';
        }
        $mime = (string)($row['file_mime_type'] ?? 'application/octet-stream');
        $inline = $mime === 'application/pdf';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safe . '"');
        header('Content-Length: ' . (string)filesize($full));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($full);
    }

    public static function classLine(array $class): string
    {
        $year = (int)($class['year'] ?? 0);
        $parts = [];
        if ($year >= 1 && $year <= 4) {
            $parts[] = subject_year_label($year);
        }
        $name = trim((string)($class['class_name'] ?? $class['name'] ?? ''));
        $dept = trim((string)($class['dept_code'] ?? ''));
        if ($dept !== '') {
            $parts[] = $dept;
        } elseif ($name !== '') {
            $parts[] = $name;
        }
        $section = trim((string)($class['section'] ?? ''));
        if ($section !== '') {
            $parts[] = 'Section ' . $section;
        }
        return $parts !== [] ? implode(' · ', $parts) : 'Class';
    }

    public static function typeLabel(string $type): string
    {
        return $type === 'ppt' ? 'PPT' : 'Notes';
    }

    public static function uploadedLabel(string $createdAt): string
    {
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return $createdAt;
        }
        $day = date('Y-m-d', $ts);
        if ($day === date('Y-m-d')) {
            return 'Today';
        }
        if ($day === date('Y-m-d', strtotime('-1 day'))) {
            return 'Yesterday';
        }
        return date('M j, Y', $ts);
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return rtrim(rtrim(number_format($bytes / 1024, 1, '.', ''), '0'), '.') . ' KB';
        }
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' MB';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function decorate(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['class_line'] = self::classLine($row);
            $row['type_label'] = self::typeLabel((string)$row['material_type']);
            $row['uploaded_label'] = self::uploadedLabel((string)($row['created_at'] ?? ''));
        }
        unset($row);
        return $rows;
    }

    private static function isProfessor(array $user): bool
    {
        return in_array((string)($user['role'] ?? ''), ['professor', 'admin'], true)
            && (int)($user['id'] ?? 0) > 0
            && (int)($user['institution_id'] ?? 0) > 0;
    }

    private static function safeRelativePath(string $path): bool
    {
        return (bool)preg_match('#^study-materials/[a-f0-9]{32}\.(pdf|doc|docx|ppt|pptx)$#', $path);
    }

    /**
     * @param array<string,mixed>|null $file
     * @return array{ok:bool,error?:string,path?:string,original_name?:string,mime_type?:string,size?:int}
     */
    private static function storeUpload(?array $file, string $type): array
    {
        if ($file === null || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Choose a file to upload.'];
        }
        $err = (int)$file['error'];
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'File is too large. Maximum size is 20 MB.'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File is too large. Maximum size is 20 MB.'];
        }
        $original = trim((string)($file['name'] ?? ''));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = $type === 'ppt' ? ['ppt', 'pptx', 'pdf'] : ['pdf', 'doc', 'docx'];
        if (!in_array($ext, $allowed, true)) {
            $label = $type === 'ppt' ? 'PPT, PPTX, or PDF' : 'PDF, DOC, or DOCX';
            return ['ok' => false, 'error' => 'This material type accepts ' . $label . ' files.'];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }
        if (!self::signatureMatches($tmp, $ext)) {
            $label = $type === 'ppt' ? 'PPT, PPTX, or PDF' : 'PDF, DOC, or DOCX';
            return ['ok' => false, 'error' => 'This file does not look like a valid ' . $label . ' document.'];
        }

        $dir = dirname(__DIR__) . '/uploads/study-materials';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|js|html?)$\">\n  Require all denied\n</FilesMatch>\n");
        }
        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $safeName;
        if (!move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }
        $mimes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        return [
            'ok' => true,
            'path' => 'study-materials/' . $safeName,
            'original_name' => mb_substr($original !== '' ? $original : $safeName, 0, 255),
            'mime_type' => $mimes[$ext],
            'size' => $size,
        ];
    }

    private static function signatureMatches(string $tmp, string $ext): bool
    {
        $head = @file_get_contents($tmp, false, null, 0, 8);
        if ($head === false || $head === '') {
            return false;
        }
        if ($ext === 'pdf') {
            return str_starts_with($head, '%PDF-');
        }
        if ($ext === 'doc' || $ext === 'ppt') {
            return str_starts_with($head, "\xD0\xCF\x11\xE0");
        }
        if (!class_exists('ZipArchive', false)) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            return false;
        }
        $needed = $ext === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
        $ok = $zip->locateName($needed) !== false;
        $zip->close();
        return $ok;
    }

    private static function deleteStoredFile(string $relativePath): void
    {
        $full = self::absolutePath($relativePath);
        if ($full !== null) {
            @unlink($full);
        }
    }
}
