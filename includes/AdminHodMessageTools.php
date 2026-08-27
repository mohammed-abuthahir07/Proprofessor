<?php
declare(strict_types=1);

/**
 * College Admin → all department HODs announcements (message + optional PDF/DOCX).
 */
final class AdminHodMessageTools
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
            "CREATE TABLE IF NOT EXISTS admin_hod_announcements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                institution_id INT UNSIGNED NOT NULL,
                admin_id INT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                body TEXT NOT NULL,
                recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
                attachment_path VARCHAR(255) NULL,
                attachment_original_name VARCHAR(255) NULL,
                attachment_mime_type VARCHAR(100) NULL,
                attachment_size INT UNSIGNED NULL,
                meta LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_aha_inst (institution_id, created_at),
                INDEX idx_aha_admin (admin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Active HODs in this institution (all departments).
     *
     * @return list<array{id:int,full_name:string,department_id:int,dept_name:string}>
     */
    public static function findHodRecipients(array $admin): array
    {
        $instId = (int)($admin['institution_id'] ?? 0);
        if ($instId < 1) {
            return [];
        }
        return Database::fetchAll(
            'SELECT u.id, u.full_name, u.department_id, d.name AS dept_name, d.code AS dept_code
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.institution_id = ?
               AND u.role = "hod"
               AND u.is_active = 1
             ORDER BY d.name, u.full_name',
            [$instId]
        );
    }

    /**
     * @param array<string,mixed>|null $file $_FILES['attachment']
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
        $dir = dirname(__DIR__) . '/uploads/admin-hod-messages';
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
            'path' => 'admin-hod-messages/' . $safeName,
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
        if ($relativePath === '' || str_contains($relativePath, '..') || !str_starts_with($relativePath, 'admin-hod-messages/')) {
            return null;
        }
        $full = dirname(__DIR__) . '/uploads/' . $relativePath;
        return is_file($full) ? $full : null;
    }

    /**
     * @param array<string,mixed>|null $uploadFile
     * @return array{ok:bool,error?:string,recipient_count?:int,announcement_id?:int}
     */
    public static function send(array $admin, string $message, string $title = '', ?array $uploadFile = null): array
    {
        self::ensureSchema();
        $role = (string)($admin['role'] ?? '');
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            return ['ok' => false, 'error' => 'Only College Admin can message HODs.'];
        }
        $instId = (int)($admin['institution_id'] ?? 0);
        if ($instId < 1) {
            return ['ok' => false, 'error' => 'Institution context missing.'];
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
            $title = 'Message from College Admin';
        }
        $title = mb_substr($title, 0, 200);

        $recipients = self::findHodRecipients($admin);
        if (!$recipients) {
            return ['ok' => false, 'error' => 'No active HODs found in this institution.'];
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

        $senderName = trim((string)($admin['full_name'] ?? 'College Admin'));
        $body = $message . "\n\nFrom: " . $senderName . " (College Admin)";

        $annId = (int)Database::insert('admin_hod_announcements', [
            'institution_id' => $instId,
            'admin_id' => (int)$admin['id'],
            'title' => $title,
            'body' => $message,
            'recipient_count' => count($recipients),
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
            'attachment_mime_type' => $attachment['mime_type'] ?? null,
            'attachment_size' => isset($attachment['size']) ? (int)$attachment['size'] : null,
            'meta' => json_encode([
                'sender_name' => $senderName,
                'has_attachment' => !empty($attachment['path']),
                'attachment_original_name' => $attachment['original_name'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $sent = 0;
        foreach ($recipients as $hod) {
            $uid = (int)($hod['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $check = Database::fetch(
                'SELECT id FROM users WHERE id = ? AND institution_id = ? AND role = "hod" AND is_active = 1',
                [$uid, $instId]
            );
            if (!$check) {
                continue;
            }
            notify_user(
                $uid,
                'announcement',
                $title,
                $body,
                '/hod/notifications',
                [
                    'priority' => NotificationService::PRIORITY_MEDIUM,
                    'category' => 'system',
                    'action' => ['type' => 'OPEN_NOTIFICATIONS'],
                    'meta' => [
                        'announcement_id' => $annId,
                        'admin_id' => (int)$admin['id'],
                        'kind' => 'admin_hod_message',
                        'has_attachment' => !empty($attachment['path']),
                        'attachment_original_name' => $attachment['original_name'] ?? null,
                        'sender_name' => $senderName,
                    ],
                ]
            );
            $sent++;
        }

        if ($sent !== count($recipients)) {
            Database::update('admin_hod_announcements', [
                'recipient_count' => $sent,
            ], 'id = :id', ['id' => $annId]);
        }

        if ($sent < 1) {
            if (!empty($attachment['path'])) {
                self::deleteAttachmentFile((string)$attachment['path']);
            }
            return ['ok' => false, 'error' => 'Could not deliver to any HODs.'];
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
    public static function sentHistory(array $admin, int $limit = 20): array
    {
        self::ensureSchema();
        $limit = max(1, min(50, $limit));
        return Database::fetchAll(
            "SELECT * FROM admin_hod_announcements
             WHERE institution_id = ? AND admin_id = ?
             ORDER BY id DESC
             LIMIT {$limit}",
            [(int)$admin['institution_id'], (int)$admin['id']]
        );
    }

    /** @return array<string,mixed>|null */
    public static function getAnnouncement(int $id, int $institutionId): ?array
    {
        self::ensureSchema();
        $row = Database::fetch(
            'SELECT * FROM admin_hod_announcements WHERE id = ? AND institution_id = ?',
            [$id, $institutionId]
        );
        return $row ?: null;
    }

    /** HOD may download if they belong to the same institution as the announcement. */
    public static function announcementForHodAttachment(array $user, int $announcementId): ?array
    {
        if (($user['role'] ?? '') !== 'hod') {
            return null;
        }
        $instId = (int)($user['institution_id'] ?? 0);
        $ann = self::getAnnouncement($announcementId, $instId);
        if (!$ann || trim((string)($ann['attachment_path'] ?? '')) === '') {
            return null;
        }
        $hod = Database::fetch(
            'SELECT id FROM users WHERE id = ? AND institution_id = ? AND role = "hod" AND is_active = 1',
            [(int)$user['id'], $instId]
        );
        return $hod ? $ann : null;
    }

    /** Admin may download their own institution's announcement attachment. */
    public static function announcementForAdminAttachment(array $user, int $announcementId): ?array
    {
        if (!in_array((string)($user['role'] ?? ''), ['admin', 'superadmin'], true)) {
            return null;
        }
        $ann = self::getAnnouncement($announcementId, (int)$user['institution_id']);
        if (!$ann || trim((string)($ann['attachment_path'] ?? '')) === '') {
            return null;
        }
        return $ann;
    }

    /**
     * Delete announcement + matching HOD inbox notifications + attachment file.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function delete(array $admin, int $announcementId): array
    {
        self::ensureSchema();
        $role = (string)($admin['role'] ?? '');
        if (!in_array($role, ['admin', 'superadmin'], true)) {
            return ['ok' => false, 'error' => 'Only College Admin can delete HOD messages.'];
        }
        $instId = (int)($admin['institution_id'] ?? 0);
        if ($instId < 1 || $announcementId < 1) {
            return ['ok' => false, 'error' => 'Invalid message.'];
        }

        $ann = self::getAnnouncement($announcementId, $instId);
        if (!$ann) {
            return ['ok' => false, 'error' => 'Message not found.'];
        }

        // Remove from every HOD notification feed that references this announcement.
        Database::query(
            'DELETE FROM notifications
             WHERE type = ?
               AND JSON_UNQUOTE(JSON_EXTRACT(meta, "$.kind")) = ?
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.announcement_id")) AS UNSIGNED) = ?',
            ['announcement', 'admin_hod_message', $announcementId]
        );

        self::deleteAttachmentFile((string)($ann['attachment_path'] ?? ''));

        Database::query(
            'DELETE FROM admin_hod_announcements WHERE id = ? AND institution_id = ?',
            [$announcementId, $instId]
        );

        return ['ok' => true];
    }
}
