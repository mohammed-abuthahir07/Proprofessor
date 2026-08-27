<?php
declare(strict_types=1);

/**
 * Professor ↔ department HOD messaging (complaints / messages + optional PDF/DOCX).
 * Scoped strictly: CSE professors ↔ CSE HOD only (same department_id).
 */
final class ProfessorHodMessageTools
{
    private static bool $schemaReady = false;

    public const ATTACHMENT_MAX_BYTES = 10485760; // 10 MB

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        self::$schemaReady = true;
        Database::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS professor_hod_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                institution_id INT UNSIGNED NOT NULL,
                department_id INT UNSIGNED NOT NULL,
                thread_id INT UNSIGNED NULL,
                professor_id INT UNSIGNED NOT NULL,
                hod_id INT UNSIGNED NOT NULL,
                sender_role ENUM('professor','hod') NOT NULL,
                title VARCHAR(200) NOT NULL,
                body TEXT NOT NULL,
                attachment_path VARCHAR(255) NULL,
                attachment_original_name VARCHAR(255) NULL,
                attachment_mime_type VARCHAR(100) NULL,
                attachment_size INT UNSIGNED NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                meta LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_phm_dept (department_id, created_at),
                INDEX idx_phm_prof (professor_id, created_at),
                INDEX idx_phm_hod (hod_id, created_at),
                INDEX idx_phm_thread (thread_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** Active HOD for professor's department (same institution). */
    public static function findDepartmentHod(array $professor): ?array
    {
        $instId = (int)($professor['institution_id'] ?? 0);
        $deptId = (int)($professor['department_id'] ?? 0);
        if ($instId < 1 || $deptId < 1) {
            return null;
        }
        $row = Database::fetch(
            'SELECT id, full_name, email, department_id, institution_id
             FROM users
             WHERE institution_id = ? AND department_id = ? AND role = "hod" AND is_active = 1
             ORDER BY id ASC
             LIMIT 1',
            [$instId, $deptId]
        );
        return $row ?: null;
    }

    public static function departmentLabel(int $departmentId): string
    {
        if ($departmentId < 1) {
            return 'Department';
        }
        $d = Database::fetch('SELECT code, name FROM departments WHERE id = ?', [$departmentId]);
        if (!$d) {
            return 'Department';
        }
        $code = trim((string)($d['code'] ?? ''));
        $name = trim((string)($d['name'] ?? ''));
        return $code !== '' ? ($code . ' — ' . $name) : ($name !== '' ? $name : 'Department');
    }

    /**
     * @param array<string,mixed>|null $file
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
        if ($ext === 'docx' && class_exists('ZipArchive', false)) {
            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true || $zip->locateName('word/document.xml') === false) {
                if ($zip->open($tmp) === true) {
                    $zip->close();
                }
                return ['ok' => false, 'error' => 'Only PDF and DOCX files are allowed.'];
            }
            $zip->close();
        }
        $dir = dirname(__DIR__) . '/uploads/professor-hod-messages';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents(
                $htaccess,
                "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|js|html?)$\">\n  Require all denied\n</FilesMatch>\n"
            );
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
            'path' => 'professor-hod-messages/' . $safeName,
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
        if ($relativePath === '' || str_contains($relativePath, '..') || !str_starts_with($relativePath, 'professor-hod-messages/')) {
            return null;
        }
        $full = dirname(__DIR__) . '/uploads/' . $relativePath;
        return is_file($full) ? $full : null;
    }

    /**
     * Professor sends complaint/message to own department HOD.
     *
     * @param array<string,mixed>|null $uploadFile
     * @return array{ok:bool,error?:string,message_id?:int}
     */
    public static function professorSend(array $professor, string $message, string $title = '', ?array $uploadFile = null): array
    {
        self::ensureSchema();
        if (($professor['role'] ?? '') !== 'professor') {
            return ['ok' => false, 'error' => 'Only professors can message their HOD here.'];
        }
        $instId = (int)($professor['institution_id'] ?? 0);
        $deptId = (int)($professor['department_id'] ?? 0);
        if ($instId < 1 || $deptId < 1) {
            return ['ok' => false, 'error' => 'Your account is not linked to a department. Contact College Admin.'];
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
            $title = 'Message to HOD';
        }
        $title = mb_substr($title, 0, 200);

        $hod = self::findDepartmentHod($professor);
        if (!$hod) {
            return ['ok' => false, 'error' => 'No active HOD found for your department.'];
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

        $senderName = trim((string)($professor['full_name'] ?? 'Professor'));
        $msgId = (int)Database::insert('professor_hod_messages', [
            'institution_id' => $instId,
            'department_id' => $deptId,
            'thread_id' => null,
            'professor_id' => (int)$professor['id'],
            'hod_id' => (int)$hod['id'],
            'sender_role' => 'professor',
            'title' => $title,
            'body' => $message,
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
            'attachment_mime_type' => $attachment['mime_type'] ?? null,
            'attachment_size' => isset($attachment['size']) ? (int)$attachment['size'] : null,
            'is_read' => 0,
            'meta' => json_encode([
                'sender_name' => $senderName,
                'kind' => 'professor_hod_message',
                'has_attachment' => !empty($attachment['path']),
            ], JSON_UNESCAPED_UNICODE),
        ]);
        Database::update('professor_hod_messages', ['thread_id' => $msgId], 'id = :id', ['id' => $msgId]);

        $deptLabel = self::departmentLabel($deptId);
        $notifyBody = $message . "\n\nFrom: " . $senderName . ' (' . $deptLabel . ')';
        notify_user(
            (int)$hod['id'],
            'announcement',
            $title,
            $notifyBody,
            '/hod/compliance',
            [
                'priority' => NotificationService::PRIORITY_MEDIUM,
                'category' => 'system',
                'action' => ['type' => 'OPEN_NOTIFICATIONS'],
                'meta' => [
                    'kind' => 'professor_hod_message',
                    'message_id' => $msgId,
                    'thread_id' => $msgId,
                    'professor_id' => (int)$professor['id'],
                    'has_attachment' => !empty($attachment['path']),
                    'attachment_original_name' => $attachment['original_name'] ?? null,
                    'sender_name' => $senderName,
                ],
            ]
        );

        return ['ok' => true, 'message_id' => $msgId];
    }

    /**
     * HOD replies to a professor thread in their own department.
     *
     * @param array<string,mixed>|null $uploadFile
     * @return array{ok:bool,error?:string,message_id?:int}
     */
    public static function hodReply(array $hod, int $threadId, string $message, ?array $uploadFile = null): array
    {
        self::ensureSchema();
        if (($hod['role'] ?? '') !== 'hod' && ($hod['role'] ?? '') !== 'admin') {
            return ['ok' => false, 'error' => 'Only HOD can reply.'];
        }
        $instId = (int)($hod['institution_id'] ?? 0);
        $deptId = ($hod['role'] ?? '') === 'hod'
            ? hod_department_id($hod)
            : (int)($hod['department_id'] ?? 0);
        if ($instId < 1 || $deptId < 1 || $threadId < 1) {
            return ['ok' => false, 'error' => 'Invalid reply context.'];
        }

        $root = Database::fetch(
            'SELECT * FROM professor_hod_messages
             WHERE id = ? AND institution_id = ? AND department_id = ? AND sender_role = "professor"
               AND (thread_id IS NULL OR thread_id = id)',
            [$threadId, $instId, $deptId]
        );
        if (!$root) {
            // Also accept thread_id lookup for root row
            $root = Database::fetch(
                'SELECT * FROM professor_hod_messages
                 WHERE thread_id = ? AND institution_id = ? AND department_id = ? AND sender_role = "professor"
                 ORDER BY id ASC LIMIT 1',
                [$threadId, $instId, $deptId]
            );
        }
        if (!$root) {
            return ['ok' => false, 'error' => 'Message thread not found in your department.'];
        }

        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'Reply cannot be empty.'];
        }
        if (mb_strlen($message) > 4000) {
            return ['ok' => false, 'error' => 'Reply is too long (max 4000 characters).'];
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

        $threadRootId = (int)($root['thread_id'] ?? $root['id']);
        if ($threadRootId < 1) {
            $threadRootId = (int)$root['id'];
        }
        $hodName = trim((string)($hod['full_name'] ?? 'HOD'));
        $replyTitle = 'Re: ' . mb_substr((string)$root['title'], 0, 190);

        $msgId = (int)Database::insert('professor_hod_messages', [
            'institution_id' => $instId,
            'department_id' => $deptId,
            'thread_id' => $threadRootId,
            'professor_id' => (int)$root['professor_id'],
            'hod_id' => (int)$hod['id'],
            'sender_role' => 'hod',
            'title' => $replyTitle,
            'body' => $message,
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
            'attachment_mime_type' => $attachment['mime_type'] ?? null,
            'attachment_size' => isset($attachment['size']) ? (int)$attachment['size'] : null,
            'is_read' => 0,
            'meta' => json_encode([
                'sender_name' => $hodName,
                'kind' => 'hod_professor_reply',
                'has_attachment' => !empty($attachment['path']),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        // Mark original thread messages as read for HOD when replying
        Database::query(
            'UPDATE professor_hod_messages SET is_read = 1
             WHERE thread_id = ? AND department_id = ? AND sender_role = "professor"',
            [$threadRootId, $deptId]
        );

        notify_user(
            (int)$root['professor_id'],
            'announcement',
            $replyTitle,
            $message . "\n\nFrom: " . $hodName . ' (HOD)',
            '/professor/message-hod',
            [
                'priority' => NotificationService::PRIORITY_MEDIUM,
                'category' => 'system',
                'action' => ['type' => 'OPEN_NOTIFICATIONS'],
                'meta' => [
                    'kind' => 'hod_professor_reply',
                    'message_id' => $msgId,
                    'thread_id' => $threadRootId,
                    'hod_id' => (int)$hod['id'],
                    'has_attachment' => !empty($attachment['path']),
                    'attachment_original_name' => $attachment['original_name'] ?? null,
                    'sender_name' => $hodName,
                ],
            ]
        );

        return ['ok' => true, 'message_id' => $msgId];
    }

    /**
     * Threads for professor (own messages + HOD replies).
     *
     * @return list<array{root:array<string,mixed>,replies:list<array<string,mixed>>}>
     */
    public static function threadsForProfessor(array $professor, int $limit = 30): array
    {
        self::ensureSchema();
        $profId = (int)($professor['id'] ?? 0);
        $instId = (int)($professor['institution_id'] ?? 0);
        if ($profId < 1 || $instId < 1) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $roots = Database::fetchAll(
            "SELECT m.*, u.full_name AS hod_name
             FROM professor_hod_messages m
             LEFT JOIN users u ON u.id = m.hod_id
             WHERE m.institution_id = ? AND m.professor_id = ? AND m.sender_role = 'professor'
               AND (m.thread_id IS NULL OR m.thread_id = m.id)
             ORDER BY m.id DESC
             LIMIT {$limit}",
            [$instId, $profId]
        );
        return self::attachReplies($roots);
    }

    /**
     * Threads for HOD department inbox.
     *
     * @return list<array{root:array<string,mixed>,replies:list<array<string,mixed>>}>
     */
    public static function threadsForHod(array $hod, int $limit = 40): array
    {
        self::ensureSchema();
        $instId = (int)($hod['institution_id'] ?? 0);
        $deptId = ($hod['role'] ?? '') === 'hod'
            ? hod_department_id($hod)
            : (int)($hod['department_id'] ?? 0);
        if ($instId < 1 || $deptId < 1) {
            return [];
        }
        $limit = max(1, min(80, $limit));
        $roots = Database::fetchAll(
            "SELECT m.*, u.full_name AS professor_name, u.email AS professor_email
             FROM professor_hod_messages m
             LEFT JOIN users u ON u.id = m.professor_id
             WHERE m.institution_id = ? AND m.department_id = ? AND m.sender_role = 'professor'
               AND (m.thread_id IS NULL OR m.thread_id = m.id)
             ORDER BY m.is_read ASC, m.id DESC
             LIMIT {$limit}",
            [$instId, $deptId]
        );
        return self::attachReplies($roots);
    }

    /**
     * @param list<array<string,mixed>> $roots
     * @return list<array{root:array<string,mixed>,replies:list<array<string,mixed>>}>
     */
    private static function attachReplies(array $roots): array
    {
        $out = [];
        foreach ($roots as $root) {
            $tid = (int)($root['thread_id'] ?? $root['id']);
            $replies = Database::fetchAll(
                'SELECT * FROM professor_hod_messages
                 WHERE thread_id = ? AND id <> ? AND sender_role = "hod"
                 ORDER BY id ASC',
                [$tid, (int)$root['id']]
            );
            $out[] = ['root' => $root, 'replies' => $replies];
        }
        return $out;
    }

    public static function markThreadReadForHod(array $hod, int $threadId): void
    {
        self::ensureSchema();
        $deptId = ($hod['role'] ?? '') === 'hod'
            ? hod_department_id($hod)
            : (int)($hod['department_id'] ?? 0);
        if ($deptId < 1 || $threadId < 1) {
            return;
        }
        Database::query(
            'UPDATE professor_hod_messages SET is_read = 1
             WHERE thread_id = ? AND department_id = ? AND sender_role = "professor"',
            [$threadId, $deptId]
        );
    }

    public static function markThreadReadForProfessor(array $professor, int $threadId): void
    {
        self::ensureSchema();
        $profId = (int)($professor['id'] ?? 0);
        if ($profId < 1 || $threadId < 1) {
            return;
        }
        Database::query(
            'UPDATE professor_hod_messages SET is_read = 1
             WHERE thread_id = ? AND professor_id = ? AND sender_role = "hod"',
            [$threadId, $profId]
        );
    }

    public static function unreadCountForHod(array $hod): int
    {
        self::ensureSchema();
        $deptId = ($hod['role'] ?? '') === 'hod'
            ? hod_department_id($hod)
            : (int)($hod['department_id'] ?? 0);
        if ($deptId < 1) {
            return 0;
        }
        $row = Database::fetch(
            'SELECT COUNT(*) AS c FROM professor_hod_messages
             WHERE department_id = ? AND sender_role = "professor" AND is_read = 0
               AND (thread_id IS NULL OR thread_id = id)',
            [$deptId]
        );
        return (int)($row['c'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    public static function messageForAttachment(array $user, int $messageId): ?array
    {
        self::ensureSchema();
        $msg = Database::fetch('SELECT * FROM professor_hod_messages WHERE id = ?', [$messageId]);
        if (!$msg || trim((string)($msg['attachment_path'] ?? '')) === '') {
            return null;
        }
        $role = (string)($user['role'] ?? '');
        $uid = (int)($user['id'] ?? 0);
        $instId = (int)($user['institution_id'] ?? 0);
        if ((int)$msg['institution_id'] !== $instId) {
            return null;
        }
        if ($role === 'professor' && (int)$msg['professor_id'] === $uid) {
            return $msg;
        }
        if ($role === 'hod') {
            $deptId = hod_department_id($user);
            if ($deptId > 0 && (int)$msg['department_id'] === $deptId) {
                return $msg;
            }
        }
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return $msg;
        }
        return null;
    }

    /**
     * Professor deletes their thread (root + HOD replies) and related notifications.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function professorDeleteThread(array $professor, int $threadId): array
    {
        self::ensureSchema();
        if (($professor['role'] ?? '') !== 'professor') {
            return ['ok' => false, 'error' => 'Only professors can delete their HOD messages.'];
        }
        $profId = (int)($professor['id'] ?? 0);
        $instId = (int)($professor['institution_id'] ?? 0);
        if ($profId < 1 || $instId < 1 || $threadId < 1) {
            return ['ok' => false, 'error' => 'Invalid message.'];
        }

        $root = Database::fetch(
            'SELECT * FROM professor_hod_messages
             WHERE institution_id = ? AND professor_id = ? AND sender_role = "professor"
               AND (id = ? OR thread_id = ?)
             ORDER BY id ASC
             LIMIT 1',
            [$instId, $profId, $threadId, $threadId]
        );
        if (!$root) {
            return ['ok' => false, 'error' => 'Message not found.'];
        }

        $tid = (int)($root['thread_id'] ?? $root['id']);
        if ($tid < 1) {
            $tid = (int)$root['id'];
        }

        $rows = Database::fetchAll(
            'SELECT id, attachment_path FROM professor_hod_messages
             WHERE professor_id = ? AND (id = ? OR thread_id = ?)',
            [$profId, $tid, $tid]
        );
        $messageIds = [];
        foreach ($rows as $row) {
            $messageIds[] = (int)$row['id'];
            self::deleteAttachmentFile((string)($row['attachment_path'] ?? ''));
        }

        // Remove related inbox notifications for this thread.
        Database::query(
            'DELETE FROM notifications
             WHERE type = ?
               AND (
                 CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.thread_id")) AS UNSIGNED) = ?
                 OR CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.message_id")) AS UNSIGNED) IN ('
            . (count($messageIds) ? implode(',', array_fill(0, count($messageIds), '?')) : '0')
            . ')
               )
               AND JSON_UNQUOTE(JSON_EXTRACT(meta, "$.kind")) IN ("professor_hod_message", "hod_professor_reply")',
            array_merge(['announcement', $tid], $messageIds ?: [])
        );

        Database::query(
            'DELETE FROM professor_hod_messages WHERE professor_id = ? AND (id = ? OR thread_id = ?)',
            [$profId, $tid, $tid]
        );

        return ['ok' => true];
    }
}
