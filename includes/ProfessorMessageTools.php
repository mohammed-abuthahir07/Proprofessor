<?php
declare(strict_types=1);

/**
 * Professor → Student announcements.
 * Additive: reuses NotificationService per-student notify; does not replace existing feeds.
 */
final class ProfessorMessageTools
{
    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        self::$schemaReady = true;
        Database::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS professor_announcements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                institution_id INT UNSIGNED NOT NULL,
                department_id INT UNSIGNED NULL,
                professor_id INT UNSIGNED NOT NULL,
                subject_id INT UNSIGNED NOT NULL,
                class_id INT UNSIGNED NOT NULL,
                year TINYINT UNSIGNED NOT NULL DEFAULT 0,
                title VARCHAR(200) NOT NULL,
                body TEXT NOT NULL,
                recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
                attachment_path VARCHAR(255) NULL,
                attachment_original_name VARCHAR(255) NULL,
                attachment_mime_type VARCHAR(100) NULL,
                attachment_size INT UNSIGNED NULL,
                meta LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ann_prof (professor_id, created_at),
                INDEX idx_ann_inst (institution_id),
                INDEX idx_ann_scope (subject_id, class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $cols = [];
        foreach (Database::fetchAll('SHOW COLUMNS FROM professor_announcements') as $c) {
            $cols[$c['Field']] = true;
        }
        if (!isset($cols['attachment_path'])) {
            Database::query('ALTER TABLE professor_announcements ADD COLUMN attachment_path VARCHAR(255) NULL AFTER recipient_count');
        }
        if (!isset($cols['attachment_original_name'])) {
            Database::query('ALTER TABLE professor_announcements ADD COLUMN attachment_original_name VARCHAR(255) NULL AFTER attachment_path');
        }
        if (!isset($cols['attachment_mime_type'])) {
            Database::query('ALTER TABLE professor_announcements ADD COLUMN attachment_mime_type VARCHAR(100) NULL AFTER attachment_original_name');
        }
        if (!isset($cols['attachment_size'])) {
            Database::query('ALTER TABLE professor_announcements ADD COLUMN attachment_size INT UNSIGNED NULL AFTER attachment_mime_type');
        }
    }

    public const ATTACHMENT_MAX_BYTES = 10485760; // 10 MB

    /**
     * @param array<string,mixed>|null $file $_FILES['attachment'] shape
     * @return array{ok:bool,error?:string,path?:string,original_name?:string,mime_type?:string,size?:int}
     */
    public static function processAttachmentUpload(?array $file): array
    {
        if ($file === null || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true];
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Attachment upload failed. Please try again.'];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > self::ATTACHMENT_MAX_BYTES) {
            return ['ok' => false, 'error' => 'File size must be 10 MB or less.'];
        }
        $original = trim((string)($file['name'] ?? ''));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'docx'], true)) {
            return ['ok' => false, 'error' => 'Only PDF and DOCX files are allowed.'];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Attachment upload failed. Please try again.'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo ? (string)finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowedMimes = [
            'pdf' => ['application/pdf'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/x-zip-compressed',
            ],
        ];
        if ($detected === '' || !in_array($detected, $allowedMimes[$ext], true)) {
            return ['ok' => false, 'error' => 'Only PDF and DOCX files are allowed.'];
        }
        if ($ext === 'pdf') {
            $head = @file_get_contents($tmp, false, null, 0, 5);
            if ($head === false || !str_starts_with($head, '%PDF-')) {
                return ['ok' => false, 'error' => 'Only PDF and DOCX files are allowed.'];
            }
        }
        if ($ext === 'docx') {
            if (class_exists('ZipArchive', false)) {
                $zip = new ZipArchive();
                if ($zip->open($tmp) !== true || $zip->locateName('word/document.xml') === false) {
                    if ($zip->open($tmp) === true) {
                        $zip->close();
                    }
                    return ['ok' => false, 'error' => 'Only PDF and DOCX files are allowed.'];
                }
                $zip->close();
            } elseif ($detected === 'application/zip' || $detected === 'application/x-zip-compressed') {
                return ['ok' => false, 'error' => 'Only PDF and DOCX files are allowed.'];
            }
        }
        $dir = dirname(__DIR__) . '/uploads/professor-messages';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|js|html?)$\">\n  Require all denied\n</FilesMatch>\n");
        }
        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $safeName;
        if (!move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'error' => 'Attachment upload failed. Please try again.'];
        }
        $mime = $ext === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        return [
            'ok' => true,
            'path' => 'professor-messages/' . $safeName,
            'original_name' => mb_substr($original !== '' ? $original : $safeName, 0, 255),
            'mime_type' => $mime,
            'size' => $size,
        ];
    }

    public static function deleteAttachmentFile(?string $relativePath): void
    {
        $relativePath = trim((string)$relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }
        $full = dirname(__DIR__) . '/uploads/' . $relativePath;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public static function attachmentAbsolutePath(?string $relativePath): ?string
    {
        $relativePath = trim((string)$relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..') || !str_starts_with($relativePath, 'professor-messages/')) {
            return null;
        }
        $full = dirname(__DIR__) . '/uploads/' . $relativePath;
        return is_file($full) ? $full : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function getAnnouncement(int $announcementId, int $institutionId): ?array
    {
        self::ensureSchema();
        $row = Database::fetch(
            'SELECT * FROM professor_announcements WHERE id = ? AND institution_id = ?',
            [$announcementId, $institutionId]
        );
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null Announcement row if student may download attachment
     */
    public static function announcementForStudentAttachment(array $user, int $announcementId): ?array
    {
        if (($user['role'] ?? '') !== 'student') {
            return null;
        }
        $instId = (int)($user['institution_id'] ?? 0);
        $ann = self::getAnnouncement($announcementId, $instId);
        if (!$ann || trim((string)($ann['attachment_path'] ?? '')) === '') {
            return null;
        }
        $prof = Database::fetch(
            'SELECT * FROM users WHERE id = ? AND institution_id = ? AND role IN ("professor","admin")',
            [(int)$ann['professor_id'], $instId]
        );
        if (!$prof) {
            return null;
        }
        foreach (self::findRecipients($prof, (int)$ann['year'], (int)$ann['subject_id'], (int)$ann['class_id']) as $stu) {
            if ((int)($stu['id'] ?? 0) === (int)$user['id']) {
                return $ann;
            }
        }
        return null;
    }

    /**
     * @return array<string,mixed>|null Announcement row if professor may download attachment
     */
    public static function announcementForProfessorAttachment(array $user, int $announcementId): ?array
    {
        if (!in_array((string)($user['role'] ?? ''), ['professor', 'admin'], true)) {
            return null;
        }
        $instId = (int)($user['institution_id'] ?? 0);
        $ann = self::getAnnouncement($announcementId, $instId);
        if (!$ann || trim((string)($ann['attachment_path'] ?? '')) === '') {
            return null;
        }
        if (($user['role'] ?? '') === 'professor' && (int)$ann['professor_id'] !== (int)$user['id']) {
            return null;
        }
        return $ann;
    }

    /**
     * Academic years (1–4) present in the professor's assigned classes.
     *
     * @return list<int>
     */
    public static function yearsForProfessor(array $user): array
    {
        $rows = Database::fetchAll(
            'SELECT DISTINCT c.year
             FROM subject_assignments sa
             JOIN classes c ON c.id = sa.class_id
             WHERE sa.professor_id = ?
               AND c.institution_id = ?
               AND c.is_active = 1
               AND c.year BETWEEN 1 AND 4
             ORDER BY c.year',
            [(int)$user['id'], (int)$user['institution_id']]
        );
        $years = [];
        foreach ($rows as $r) {
            $y = (int)($r['year'] ?? 0);
            if ($y >= 1 && $y <= 4) {
                $years[] = $y;
            }
        }
        return array_values(array_unique($years));
    }

    /**
     * Courses assigned to this professor for classes of the given year.
     *
     * @return list<array<string,mixed>>
     */
    public static function coursesForProfessorYear(array $user, int $year): array
    {
        $year = max(1, min(4, $year));
        return Database::fetchAll(
            'SELECT DISTINCT s.id, s.code, s.name, s.department_id
             FROM subject_assignments sa
             JOIN subjects s ON s.id = sa.subject_id
             JOIN classes c ON c.id = sa.class_id
             WHERE sa.professor_id = ?
               AND c.institution_id = ?
               AND s.institution_id = ?
               AND s.is_active = 1
               AND c.is_active = 1
               AND c.year = ?
             ORDER BY s.name',
            [(int)$user['id'], (int)$user['institution_id'], (int)$user['institution_id'], $year]
        );
    }

    /**
     * Classes/sections for professor + course + year.
     *
     * @return list<array<string,mixed>>
     */
    public static function classesForProfessorCourseYear(array $user, int $subjectId, int $year): array
    {
        $year = max(1, min(4, $year));
        if ($subjectId < 1) {
            return [];
        }
        return Database::fetchAll(
            'SELECT DISTINCT c.id, c.name, c.year, c.section, c.department_id, c.meta, d.code AS dept_code, d.name AS dept_name
             FROM subject_assignments sa
             JOIN classes c ON c.id = sa.class_id
             LEFT JOIN departments d ON d.id = c.department_id
             WHERE sa.professor_id = ?
               AND sa.subject_id = ?
               AND c.institution_id = ?
               AND c.is_active = 1
               AND c.year = ?
             ORDER BY c.section, c.name',
            [(int)$user['id'], $subjectId, (int)$user['institution_id'], $year]
        );
    }

    /**
     * @return array{ok:bool,error?:string,class?:array,subject?:array}
     */
    public static function assertAuthorizedScope(array $user, int $year, int $subjectId, int $classId): array
    {
        if (!in_array((string)($user['role'] ?? ''), ['professor', 'admin'], true)) {
            return ['ok' => false, 'error' => 'Only professors can send student messages.'];
        }
        $instId = (int)($user['institution_id'] ?? 0);
        if ($instId < 1) {
            return ['ok' => false, 'error' => 'Institution context missing.'];
        }
        $year = max(0, min(4, $year));
        if ($year < 1) {
            return ['ok' => false, 'error' => 'Select a valid academic year.'];
        }
        if ($subjectId < 1 || $classId < 1) {
            return ['ok' => false, 'error' => 'Select course and class/section.'];
        }
        if (!professor_can_manage_subject($user, $subjectId, $classId)) {
            return ['ok' => false, 'error' => 'You are not assigned to this course for the selected class.'];
        }

        $subject = Database::fetch(
            'SELECT id, code, name, department_id, institution_id FROM subjects
             WHERE id = ? AND institution_id = ? AND is_active = 1',
            [$subjectId, $instId]
        );
        if (!$subject) {
            return ['ok' => false, 'error' => 'Course not found in your institution.'];
        }

        $class = Database::fetch(
            'SELECT id, name, year, section, department_id, institution_id, meta
             FROM classes WHERE id = ? AND institution_id = ? AND is_active = 1',
            [$classId, $instId]
        );
        if (!$class) {
            return ['ok' => false, 'error' => 'Class not found in your institution.'];
        }
        if ((int)$class['year'] !== $year) {
            return ['ok' => false, 'error' => 'Selected class does not match the chosen year.'];
        }

        $profDept = (int)($user['department_id'] ?? 0);
        if ($profDept > 0) {
            $classDept = (int)($class['department_id'] ?? 0);
            $subjDept = (int)($subject['department_id'] ?? 0);
            if ($classDept > 0 && $classDept !== $profDept && $subjDept !== $profDept) {
                return ['ok' => false, 'error' => 'Class/course is outside your department.'];
            }
        }

        // Confirm assignment row exists for this exact trio.
        $asg = Database::fetch(
            'SELECT sa.id FROM subject_assignments sa
             JOIN classes c ON c.id = sa.class_id
             WHERE sa.professor_id = ? AND sa.subject_id = ? AND sa.class_id = ? AND c.year = ?
             LIMIT 1',
            [(int)$user['id'], $subjectId, $classId, $year]
        );
        if (!$asg && ($user['role'] ?? '') !== 'admin') {
            return ['ok' => false, 'error' => 'No teaching assignment for this year/course/class.'];
        }

        return ['ok' => true, 'class' => $class, 'subject' => $subject];
    }

    /**
     * Students who will receive the announcement (institution + class + course scoped).
     *
     * @return list<array{id:int,register_no:string,full_name:string}>
     */
    public static function findRecipients(array $user, int $year, int $subjectId, int $classId): array
    {
        $auth = self::assertAuthorizedScope($user, $year, $subjectId, $classId);
        if (!$auth['ok']) {
            return [];
        }
        $instId = (int)$user['institution_id'];
        // CURRENT academic-context recipients only (same rules as attendance/marks).
        $roster = students_for_current_course_context(
            $instId,
            $classId,
            $subjectId,
            (($user['role'] ?? '') === 'professor') ? (int)$user['id'] : null
        );
        $out = [];
        foreach ($roster as $row) {
            $out[] = [
                'id' => (int)($row['user_id'] ?? $row['id'] ?? 0),
                'register_no' => (string)($row['register_no'] ?? ''),
                'full_name' => (string)($row['full_name'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed>|null $uploadFile $_FILES['attachment'] when present
     * @return array{ok:bool,error?:string,recipient_count?:int,announcement_id?:int}
     */
    public static function send(array $user, int $year, int $subjectId, int $classId, string $message, string $title = '', ?array $uploadFile = null): array
    {
        self::ensureSchema();
        $auth = self::assertAuthorizedScope($user, $year, $subjectId, $classId);
        if (!$auth['ok']) {
            return ['ok' => false, 'error' => $auth['error'] ?? 'Not authorized.'];
        }

        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'Message cannot be empty.'];
        }
        if (mb_strlen($message) > 4000) {
            return ['ok' => false, 'error' => 'Message is too long (max 4000 characters).'];
        }

        $title = trim($title);
        if ($title === '') {
            $title = 'Announcement from Professor';
        }
        $title = mb_substr($title, 0, 200);

        $recipients = self::findRecipients($user, $year, $subjectId, $classId);
        if (!$recipients) {
            return ['ok' => false, 'error' => 'No matching students found for this year/course/class.'];
        }

        $attachment = null;
        if ($uploadFile !== null) {
            $upload = self::processAttachmentUpload($uploadFile);
            if (!$upload['ok']) {
                return ['ok' => false, 'error' => $upload['error'] ?? 'Attachment upload failed.'];
            }
            if (!empty($upload['path'])) {
                $attachment = $upload;
            }
        }

        /** @var array $subject */
        $subject = $auth['subject'];
        /** @var array $class */
        $class = $auth['class'];
        $classLabel = class_batch_label($class);
        $yearLabel = subject_year_label($year);
        $courseLabel = trim((string)($subject['code'] ?? '') . ' · ' . (string)($subject['name'] ?? ''));
        $senderName = trim((string)($user['full_name'] ?? 'Professor'));

        $body = $message . "\n\n"
            . $courseLabel . "\n"
            . $yearLabel . ' · ' . $classLabel . "\n"
            . 'From: ' . $senderName;

        $annId = (int)Database::insert('professor_announcements', [
            'institution_id' => (int)$user['institution_id'],
            'department_id' => (($dept = (int)($user['department_id'] ?? 0) ?: (int)($subject['department_id'] ?? 0)) > 0 ? $dept : null),
            'professor_id' => (int)$user['id'],
            'subject_id' => $subjectId,
            'class_id' => $classId,
            'year' => $year,
            'title' => $title,
            'body' => $message,
            'recipient_count' => count($recipients),
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
            'attachment_mime_type' => $attachment['mime_type'] ?? null,
            'attachment_size' => isset($attachment['size']) ? (int)$attachment['size'] : null,
            'meta' => json_encode([
                'course_label' => $courseLabel,
                'class_label' => $classLabel,
                'year_label' => $yearLabel,
                'sender_name' => $senderName,
                'has_attachment' => !empty($attachment['path']),
                'attachment_original_name' => $attachment['original_name'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $sent = 0;
        foreach ($recipients as $stu) {
            $uid = (int)($stu['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            // Double-check institution on each notify target.
            $check = Database::fetch(
                'SELECT id FROM users WHERE id = ? AND institution_id = ? AND role = "student" AND is_active = 1',
                [$uid, (int)$user['institution_id']]
            );
            if (!$check) {
                continue;
            }
            notify_user(
                $uid,
                'announcement',
                $title,
                $body,
                '/student/notifications.php',
                [
                    'priority' => NotificationService::PRIORITY_MEDIUM,
                    'category' => 'system',
                    'action' => ['type' => 'OPEN_NOTIFICATIONS'],
                    'meta' => [
                        'announcement_id' => $annId,
                        'professor_id' => (int)$user['id'],
                        'subject_id' => $subjectId,
                        'class_id' => $classId,
                        'year' => $year,
                        'course_label' => $courseLabel,
                        'class_label' => $classLabel,
                        'kind' => 'professor_student_message',
                        'has_attachment' => !empty($attachment['path']),
                        'attachment_original_name' => $attachment['original_name'] ?? null,
                    ],
                ]
            );
            $sent++;
        }

        if ($sent !== count($recipients)) {
            Database::update('professor_announcements', [
                'recipient_count' => $sent,
            ], 'id = :id', ['id' => $annId]);
        }

        if ($sent < 1) {
            if (!empty($attachment['path'])) {
                self::deleteAttachmentFile((string)$attachment['path']);
                Database::update('professor_announcements', [
                    'attachment_path' => null,
                    'attachment_original_name' => null,
                    'attachment_mime_type' => null,
                    'attachment_size' => null,
                ], 'id = :id', ['id' => $annId]);
            }
            return ['ok' => false, 'error' => 'Could not deliver to any students.'];
        }

        return [
            'ok' => true,
            'recipient_count' => $sent,
            'announcement_id' => $annId,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function sentHistory(array $user, int $limit = 30): array
    {
        self::ensureSchema();
        $limit = max(1, min(100, $limit));
        return Database::fetchAll(
            "SELECT a.*, s.code AS subject_code, s.name AS subject_name,
                    c.name AS class_name, c.section, c.year AS class_year
             FROM professor_announcements a
             LEFT JOIN subjects s ON s.id = a.subject_id
             LEFT JOIN classes c ON c.id = a.class_id
             WHERE a.professor_id = ? AND a.institution_id = ?
             ORDER BY a.id DESC
             LIMIT {$limit}",
            [(int)$user['id'], (int)$user['institution_id']]
        );
    }

    public static function previewRecipientCount(array $user, int $year, int $subjectId, int $classId): int
    {
        return count(self::findRecipients($user, $year, $subjectId, $classId));
    }

    /**
     * Delete professor→student announcement + matching student notifications + attachment.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function delete(array $user, int $announcementId): array
    {
        self::ensureSchema();
        $role = (string)($user['role'] ?? '');
        if (!in_array($role, ['professor', 'admin', 'superadmin'], true)) {
            return ['ok' => false, 'error' => 'Only professors can delete student messages.'];
        }
        $profId = (int)($user['id'] ?? 0);
        $instId = (int)($user['institution_id'] ?? 0);
        if ($profId < 1 || $instId < 1 || $announcementId < 1) {
            return ['ok' => false, 'error' => 'Invalid message.'];
        }

        $ann = Database::fetch(
            'SELECT * FROM professor_announcements WHERE id = ? AND institution_id = ? AND professor_id = ?',
            [$announcementId, $instId, $profId]
        );
        if (!$ann && in_array($role, ['admin', 'superadmin'], true)) {
            $ann = Database::fetch(
                'SELECT * FROM professor_announcements WHERE id = ? AND institution_id = ?',
                [$announcementId, $instId]
            );
        }
        if (!$ann) {
            return ['ok' => false, 'error' => 'Message not found.'];
        }

        Database::query(
            'DELETE FROM notifications
             WHERE type = ?
               AND JSON_UNQUOTE(JSON_EXTRACT(meta, "$.kind")) = ?
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.announcement_id")) AS UNSIGNED) = ?',
            ['announcement', 'professor_student_message', $announcementId]
        );

        self::deleteAttachmentFile((string)($ann['attachment_path'] ?? ''));

        Database::query(
            'DELETE FROM professor_announcements WHERE id = ? AND institution_id = ?',
            [$announcementId, $instId]
        );

        return ['ok' => true];
    }
}
