<?php
declare(strict_types=1);

/**
 * Multi-channel notification delivery, priority, safe actions, and digests.
 * Extends the existing in-app notifications table — does not replace it.
 */
final class NotificationService
{
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_LOW = 'low';

    public const DIGEST_IMMEDIATE = 'immediate';
    public const DIGEST_DAILY = 'daily';
    public const DIGEST_WEEKLY = 'weekly';

    /** Allowed structured action types (never arbitrary URLs/code). */
    private const ACTION_TYPES = [
        'GRADE_ASSIGNMENT',
        'VIEW_PLAN',
        'APPROVE_PLAN',
        'VIEW_ATTENDANCE',
        'UPDATE_MARKS',
        'VIEW_ASSIGNMENTS',
        'STUDENT_ATTENDANCE',
        'STUDENT_ASSIGNMENTS',
        'OPEN_NOTIFICATIONS',
    ];

    private const CATEGORIES = [
        'assignments',
        'attendance',
        'course_plans',
        'approvals',
        'system',
        'ai',
    ];

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [];
        foreach (Database::fetchAll('SHOW COLUMNS FROM notifications') as $c) {
            $cols[$c['Field']] = true;
        }
        if (!isset($cols['institution_id'])) {
            Database::query(
                "ALTER TABLE notifications
                 ADD COLUMN institution_id INT UNSIGNED NULL DEFAULT NULL
                 COMMENT 'Tenant isolation' AFTER user_id"
            );
            Database::query('ALTER TABLE notifications ADD KEY idx_notif_inst (institution_id)');
        }
        if (!isset($cols['priority'])) {
            Database::query(
                "ALTER TABLE notifications
                 ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'medium'
                 COMMENT 'high|medium|low' AFTER type"
            );
            Database::query('ALTER TABLE notifications ADD KEY idx_notif_priority (user_id, priority)');
        }
        if (!isset($cols['action_type'])) {
            Database::query(
                "ALTER TABLE notifications
                 ADD COLUMN action_type VARCHAR(60) NULL DEFAULT NULL AFTER action_url"
            );
        }
        if (!isset($cols['action_payload'])) {
            Database::query(
                "ALTER TABLE notifications
                 ADD COLUMN action_payload LONGTEXT NULL DEFAULT NULL
                 COMMENT 'JSON action metadata' AFTER action_type"
            );
        }
        if (!isset($cols['delivery_status'])) {
            Database::query(
                "ALTER TABLE notifications
                 ADD COLUMN delivery_status LONGTEXT NULL DEFAULT NULL
                 COMMENT 'JSON channel delivery statuses' AFTER action_payload"
            );
        }
    }

    /**
     * Create + deliver a notification. In-app remains the primary feed.
     *
     * @param array{
     *   priority?:string,
     *   action?:array{type:string,record_id?:int,class_id?:int,subject_id?:int,label?:string},
     *   category?:string,
     *   meta?:array<string,mixed>,
     *   skip_channels?:list<string>
     * } $options
     */
    public static function notify(
        int $userId,
        string $type,
        string $title,
        string $body = '',
        ?string $url = null,
        array $options = []
    ): int {
        self::ensureSchema();
        if ($userId < 1) {
            return 0;
        }
        $recipient = Database::fetch(
            'SELECT id, institution_id, email, phone, role, preferences FROM users WHERE id = ? AND is_active = 1',
            [$userId]
        );
        if (!$recipient) {
            return 0;
        }

        // Hard guard: Admin→HOD messages must never land on non-HOD accounts.
        $metaEarly = is_array($options['meta'] ?? null) ? $options['meta'] : [];
        if (($metaEarly['kind'] ?? '') === 'admin_hod_message' && (string)($recipient['role'] ?? '') !== 'hod') {
            return 0;
        }

        $category = self::normalizeCategory((string)($options['category'] ?? ''), $type);
        $priority = self::normalizePriority((string)($options['priority'] ?? ''), $type, $title, $body);
        $action = self::normalizeAction($options['action'] ?? null);
        $prefs = self::preferencesFromUser($recipient);

        $channelsWanted = self::channelsForCategory($prefs, $category);
        $delivery = [
            'in_app' => ['status' => 'skipped', 'at' => null],
            'email' => ['status' => 'skipped', 'at' => null],
            'whatsapp' => ['status' => 'skipped', 'at' => null],
            'sms' => ['status' => 'skipped', 'at' => null],
        ];

        $meta = is_array($options['meta'] ?? null) ? $options['meta'] : [];
        $meta['category'] = $category;
        $meta['digest_mode'] = $prefs['digest_mode'] ?? self::DIGEST_IMMEDIATE;

        $id = 0;
        if (!empty($channelsWanted['in_app'])) {
            $legacyUrl = $url;
            if ($legacyUrl !== null && $legacyUrl !== '') {
                $legacyUrl = self::sanitizeStoredUrl($legacyUrl);
            } elseif ($action !== null) {
                // Soft fallback URL for older UIs; real navigation uses ?go=id.
                $legacyUrl = self::previewPathForAction($action);
            }

            $id = (int)Database::insert('notifications', [
                'user_id' => $userId,
                'institution_id' => (int)($recipient['institution_id'] ?? 0) ?: null,
                'type' => $type,
                'priority' => $priority,
                'title' => mb_substr($title, 0, 200),
                'body' => $body,
                'action_url' => $legacyUrl,
                'action_type' => $action['type'] ?? null,
                'action_payload' => $action ? json_encode($action, JSON_UNESCAPED_UNICODE) : null,
                'delivery_status' => null,
                'is_read' => 0,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ]);
            $delivery['in_app'] = ['status' => 'delivered', 'at' => date('Y-m-d H:i:s')];
        }

        // External channels — never fake success when provider missing.
        if (!empty($channelsWanted['email'])) {
            $delivery['email'] = self::sendEmail($recipient, $title, $body, $action);
        } else {
            $delivery['email'] = ['status' => 'disabled', 'at' => null];
        }
        if (!empty($channelsWanted['whatsapp'])) {
            $delivery['whatsapp'] = self::sendWhatsApp($recipient, $title, $body);
        } else {
            $cfg = self::providerStatus('whatsapp');
            $delivery['whatsapp'] = [
                'status' => $cfg['configured'] ? 'disabled' : 'not_configured',
                'at' => null,
            ];
        }
        if (!empty($channelsWanted['sms'])) {
            $delivery['sms'] = self::sendSms($recipient, $title, $body);
        } else {
            $cfg = self::providerStatus('sms');
            $delivery['sms'] = [
                'status' => $cfg['configured'] ? 'disabled' : 'not_configured',
                'at' => null,
            ];
        }

        if ($id > 0) {
            Database::update(
                'notifications',
                ['delivery_status' => json_encode($delivery, JSON_UNESCAPED_UNICODE)],
                'id = :id AND user_id = :uid',
                ['id' => $id, 'uid' => $userId]
            );
        }

        return $id;
    }

    /** @return array{configured:bool,label:string,detail:string} */
    public static function providerStatus(string $channel): array
    {
        $channel = strtolower($channel);
        if ($channel === 'in_app') {
            return ['configured' => true, 'label' => 'Configured', 'detail' => 'In-app feed'];
        }
        if ($channel === 'email') {
            $enabled = (bool)config('notifications.email.enabled', false);
            $from = trim((string)config('notifications.email.from', ''));
            if ($enabled && $from !== '') {
                return ['configured' => true, 'label' => 'Configured', 'detail' => 'Email provider ready'];
            }
            return ['configured' => false, 'label' => 'Not configured', 'detail' => 'Set notifications.email in config'];
        }
        if ($channel === 'whatsapp') {
            $enabled = (bool)config('notifications.whatsapp.enabled', false);
            $key = trim((string)config('notifications.whatsapp.api_key', ''));
            $provider = trim((string)config('notifications.whatsapp.provider', ''));
            if ($enabled && $key !== '' && $provider !== '') {
                return ['configured' => true, 'label' => 'Configured', 'detail' => $provider];
            }
            return ['configured' => false, 'label' => 'Not configured', 'detail' => 'WhatsApp provider credentials missing'];
        }
        if ($channel === 'sms') {
            $enabled = (bool)config('notifications.sms.enabled', false);
            $key = trim((string)config('notifications.sms.api_key', ''));
            $provider = trim((string)config('notifications.sms.provider', ''));
            if ($enabled && $key !== '' && $provider !== '') {
                return ['configured' => true, 'label' => 'Configured', 'detail' => $provider];
            }
            return ['configured' => false, 'label' => 'Not configured', 'detail' => 'SMS provider credentials missing'];
        }
        return ['configured' => false, 'label' => 'Unknown', 'detail' => ''];
    }

    /** @return array<string,array{configured:bool,label:string,detail:string}> */
    public static function allProviderStatuses(): array
    {
        return [
            'in_app' => self::providerStatus('in_app'),
            'email' => self::providerStatus('email'),
            'whatsapp' => self::providerStatus('whatsapp'),
            'sms' => self::providerStatus('sms'),
        ];
    }

    /**
     * Resolve a notification action for the authenticated user.
     * Never trusts client-supplied record IDs — only the stored payload + live auth checks.
     *
     * @return array{ok:bool,path?:string,error?:string}
     */
    public static function resolveAction(array $user, int $notificationId): array
    {
        self::ensureSchema();
        $row = Database::fetch(
            'SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1',
            [$notificationId, (int)$user['id']]
        );
        if (!$row) {
            return ['ok' => false, 'error' => 'Notification not found.'];
        }
        $inst = (int)($user['institution_id'] ?? 0);
        if (!empty($row['institution_id']) && (int)$row['institution_id'] !== $inst) {
            return ['ok' => false, 'error' => 'Access denied.'];
        }

        $action = null;
        if (!empty($row['action_type'])) {
            $payload = json_decode((string)($row['action_payload'] ?? '{}'), true) ?: [];
            $payload['type'] = (string)$row['action_type'];
            $action = self::normalizeAction($payload);
        }

        if ($action) {
            $path = self::authorizeAndPath($user, $action);
            if ($path === null) {
                return ['ok' => false, 'error' => 'You are not authorized to open this item.'];
            }
            return ['ok' => true, 'path' => $path];
        }

        // Legacy action_url: only relative app paths; still enforce basic sanitization.
        $url = trim((string)($row['action_url'] ?? ''));
        $rel = self::sanitizeStoredUrl($url);
        if ($rel === null || $rel === '') {
            return ['ok' => false, 'error' => 'No actionable link.'];
        }
        if (!self::legacyUrlAllowedForUser($user, $rel)) {
            return ['ok' => false, 'error' => 'You are not authorized to open this item.'];
        }
        return ['ok' => true, 'path' => $rel];
    }

    public static function actionLabel(?string $actionType, ?string $fallback = null): string
    {
        return match ((string)$actionType) {
            'GRADE_ASSIGNMENT' => 'Grade Now',
            'VIEW_PLAN' => 'View Plan',
            'APPROVE_PLAN' => 'Approve Now',
            'VIEW_ATTENDANCE' => 'View Attendance',
            'UPDATE_MARKS' => 'Update Marks',
            'VIEW_ASSIGNMENTS' => 'Open Assignments',
            'STUDENT_ATTENDANCE' => 'View Attendance',
            'STUDENT_ASSIGNMENTS' => 'View Assignments',
            default => $fallback ?: 'Open',
        };
    }

    public static function priorityLabel(string $priority): string
    {
        return match (strtolower($priority)) {
            'high' => 'HIGH',
            'low' => 'LOW',
            default => 'MEDIUM',
        };
    }

    public static function priorityEmoji(string $priority): string
    {
        return match (strtolower($priority)) {
            'high' => '🔴',
            'low' => '🔵',
            default => '🟡',
        };
    }

    /**
     * Build digest from the user's own notifications (never other users).
     *
     * @return array{title:string,period:string,lines:list<string>,count:int,empty:bool}
     */
    public static function buildDigest(array $user, string $mode): array
    {
        self::ensureSchema();
        $mode = strtolower($mode);
        if ($mode === self::DIGEST_WEEKLY) {
            $from = date('Y-m-d 00:00:00', strtotime('monday this week'));
            $title = 'Weekly Academic Digest';
            $period = 'This Week';
        } else {
            $from = date('Y-m-d 00:00:00');
            $title = 'Daily Academic Digest';
            $period = 'Today';
            $mode = self::DIGEST_DAILY;
        }

        $rows = Database::fetchAll(
            'SELECT type, title, priority, is_read, created_at
             FROM notifications
             WHERE user_id = ?
               AND created_at >= ?
               AND (institution_id IS NULL OR institution_id = ?)
             ORDER BY created_at DESC
             LIMIT 500',
            [(int)$user['id'], $from, (int)$user['institution_id']]
        );

        if (!$rows) {
            return [
                'title' => $title,
                'period' => $period,
                'lines' => ['No notifications to summarize.'],
                'count' => 0,
                'empty' => true,
            ];
        }

        $buckets = [
            'assignments' => 0,
            'approvals' => 0,
            'attendance' => 0,
            'course_plans' => 0,
            'ai' => 0,
            'system' => 0,
            'high' => 0,
            'unread' => 0,
        ];
        foreach ($rows as $r) {
            $cat = self::normalizeCategory('', (string)$r['type']);
            if (isset($buckets[$cat])) {
                $buckets[$cat]++;
            }
            if (strtolower((string)($r['priority'] ?? 'medium')) === 'high') {
                $buckets['high']++;
            }
            if (empty($r['is_read'])) {
                $buckets['unread']++;
            }
        }

        $lines = [];
        if ($buckets['assignments'] > 0) {
            $lines[] = $buckets['assignments'] . ' assignment-related notification'
                . ($buckets['assignments'] === 1 ? '' : 's');
        }
        if ($buckets['approvals'] > 0 || $buckets['course_plans'] > 0) {
            $n = $buckets['approvals'] + $buckets['course_plans'];
            $lines[] = $n . ' course plan / approval item' . ($n === 1 ? '' : 's');
        }
        if ($buckets['attendance'] > 0) {
            $lines[] = $buckets['attendance'] . ' attendance alert' . ($buckets['attendance'] === 1 ? '' : 's');
        }
        if ($buckets['ai'] > 0) {
            $lines[] = $buckets['ai'] . ' AI update' . ($buckets['ai'] === 1 ? '' : 's');
        }
        if ($buckets['system'] > 0) {
            $lines[] = $buckets['system'] . ' system notification' . ($buckets['system'] === 1 ? '' : 's');
        }
        if ($buckets['high'] > 0) {
            $lines[] = $buckets['high'] . ' high-priority item' . ($buckets['high'] === 1 ? '' : 's');
        }
        if ($buckets['unread'] > 0) {
            $lines[] = $buckets['unread'] . ' still unread';
        }
        if (!$lines) {
            $lines[] = 'No notifications to summarize.';
        }

        return [
            'title' => $title,
            'period' => $period,
            'lines' => $lines,
            'count' => count($rows),
            'empty' => count($rows) === 0,
        ];
    }

    /**
     * Persist a digest summary as a low-priority in-app notification for the user.
     */
    public static function publishDigest(array $user, string $mode): int
    {
        $digest = self::buildDigest($user, $mode);
        $body = $digest['period'] . ":\n• " . implode("\n• ", $digest['lines']);
        return self::notify(
            (int)$user['id'],
            'system',
            $digest['title'],
            $body,
            null,
            [
                'priority' => self::PRIORITY_LOW,
                'category' => 'system',
                'action' => ['type' => 'OPEN_NOTIFICATIONS'],
                'meta' => ['digest' => true, 'digest_mode' => $mode, 'source_count' => $digest['count']],
            ]
        );
    }

    /** @return array<string,mixed> */
    public static function preferencesFromUser(array $user): array
    {
        $prefs = json_decode((string)($user['preferences'] ?? '{}'), true) ?: [];
        $digest = strtolower((string)($prefs['digest_mode'] ?? self::DIGEST_IMMEDIATE));
        if (!in_array($digest, [self::DIGEST_IMMEDIATE, self::DIGEST_DAILY, self::DIGEST_WEEKLY], true)) {
            $digest = self::DIGEST_IMMEDIATE;
        }
        $channels = is_array($prefs['notification_channels'] ?? null) ? $prefs['notification_channels'] : [];
        $normalized = [];
        foreach (self::CATEGORIES as $cat) {
            $row = is_array($channels[$cat] ?? null) ? $channels[$cat] : [];
            $emailDefault = !empty($prefs['email_notifications']);
            $normalized[$cat] = [
                'in_app' => array_key_exists('in_app', $row) ? (bool)$row['in_app'] : true,
                'email' => array_key_exists('email', $row) ? (bool)$row['email'] : $emailDefault,
                'whatsapp' => !empty($row['whatsapp']),
                'sms' => !empty($row['sms']),
            ];
        }
        return [
            'digest_mode' => $digest,
            'email_notifications' => !empty($prefs['email_notifications']),
            'notification_channels' => $normalized,
            'theme' => $prefs['theme'] ?? 'light',
        ];
    }

    /**
     * Merge notification preference fields into existing preferences JSON (preserve other keys).
     *
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $posted
     * @return array<string,mixed>
     */
    public static function mergePreferencePost(array $existing, array $posted): array
    {
        $existing['email_notifications'] = !empty($posted['email_notifications']);
        $digest = strtolower((string)($posted['digest_mode'] ?? self::DIGEST_IMMEDIATE));
        $existing['digest_mode'] = in_array($digest, [self::DIGEST_IMMEDIATE, self::DIGEST_DAILY, self::DIGEST_WEEKLY], true)
            ? $digest
            : self::DIGEST_IMMEDIATE;

        $channels = [];
        $postedCh = is_array($posted['notification_channels'] ?? null) ? $posted['notification_channels'] : [];
        foreach (self::CATEGORIES as $cat) {
            $row = is_array($postedCh[$cat] ?? null) ? $postedCh[$cat] : [];
            // Hidden 0 + checkbox 1 pattern: treat non-empty "1"/true as on.
            $channels[$cat] = [
                'in_app' => self::prefFlag($row['in_app'] ?? 0),
                'email' => self::prefFlag($row['email'] ?? 0),
                'whatsapp' => self::prefFlag($row['whatsapp'] ?? 0),
                'sms' => self::prefFlag($row['sms'] ?? 0),
            ];
        }
        $existing['notification_channels'] = $channels;
        if (isset($posted['theme'])) {
            $existing['theme'] = (string)$posted['theme'];
        }
        return $existing;
    }

    private static function prefFlag(mixed $v): bool
    {
        return $v === true || $v === 1 || $v === '1';
    }

    public static function inferPriority(string $type, string $title = '', string $body = ''): string
    {
        $hay = strtolower($type . ' ' . $title . ' ' . $body);
        if (
            str_contains($hay, 'deadline today')
            || str_contains($hay, 'overdue')
            || str_contains($hay, 'approval')
            || str_contains($hay, 'approve')
            || str_contains($hay, 'shortage')
            || str_contains($hay, 'below')
            || str_contains($hay, 'critical')
            || $type === 'approval'
            || $type === 'attendance_shortage'
        ) {
            return self::PRIORITY_HIGH;
        }
        if (
            str_contains($hay, 'ai')
            || $type === 'ai'
            || str_contains($hay, 'digest')
            || str_contains($hay, 'informational')
        ) {
            return self::PRIORITY_LOW;
        }
        return self::PRIORITY_MEDIUM;
    }

    /** @return array{in_app:bool,email:bool,whatsapp:bool,sms:bool} */
    private static function channelsForCategory(array $prefs, string $category): array
    {
        $ch = $prefs['notification_channels'][$category]
            ?? ['in_app' => true, 'email' => !empty($prefs['email_notifications']), 'whatsapp' => false, 'sms' => false];
        return [
            'in_app' => !empty($ch['in_app']),
            'email' => !empty($ch['email']),
            'whatsapp' => !empty($ch['whatsapp']),
            'sms' => !empty($ch['sms']),
        ];
    }

    private static function normalizeCategory(string $category, string $type): string
    {
        $category = strtolower(trim($category));
        if (in_array($category, self::CATEGORIES, true)) {
            return $category;
        }
        $t = strtolower($type);
        if (str_contains($t, 'assign')) {
            return 'assignments';
        }
        if (str_contains($t, 'attend')) {
            return 'attendance';
        }
        if ($t === 'approval' || str_contains($t, 'plan')) {
            return 'approvals';
        }
        if ($t === 'ai' || str_contains($t, 'ai')) {
            return 'ai';
        }
        if ($t === 'announcement' || str_contains($t, 'message') || str_contains($t, 'student_message')) {
            return 'system';
        }
        return 'system';
    }

    private static function normalizePriority(string $priority, string $type, string $title, string $body): string
    {
        $priority = strtolower(trim($priority));
        if (in_array($priority, [self::PRIORITY_HIGH, self::PRIORITY_MEDIUM, self::PRIORITY_LOW], true)) {
            return $priority;
        }
        return self::inferPriority($type, $title, $body);
    }

    /** @param mixed $action */
    private static function normalizeAction($action): ?array
    {
        if (!is_array($action)) {
            return null;
        }
        $type = strtoupper(trim((string)($action['type'] ?? '')));
        if (!in_array($type, self::ACTION_TYPES, true)) {
            return null;
        }
        $out = ['type' => $type];
        foreach (['record_id', 'class_id', 'subject_id'] as $k) {
            if (isset($action[$k]) && is_numeric($action[$k])) {
                $out[$k] = (int)$action[$k];
            }
        }
        if (!empty($action['label']) && is_string($action['label'])) {
            $out['label'] = mb_substr($action['label'], 0, 40);
        }
        return $out;
    }

    private static function previewPathForAction(array $action): ?string
    {
        return match ($action['type']) {
            'GRADE_ASSIGNMENT' => '/professor/assignments.php?id=' . (int)($action['record_id'] ?? 0),
            'VIEW_PLAN' => '/professor/plan-view.php?id=' . (int)($action['record_id'] ?? 0),
            'APPROVE_PLAN' => '/hod/approvals.php?id=' . (int)($action['record_id'] ?? 0),
            'VIEW_ATTENDANCE' => '/professor/attendance.php?class_id=' . (int)($action['class_id'] ?? 0)
                . '&subject_id=' . (int)($action['subject_id'] ?? 0),
            'UPDATE_MARKS' => '/professor/marks.php?class_id=' . (int)($action['class_id'] ?? 0)
                . '&subject_id=' . (int)($action['subject_id'] ?? 0),
            'VIEW_ASSIGNMENTS' => '/professor/assignments.php',
            'STUDENT_ATTENDANCE' => '/student/attendance.php',
            'STUDENT_ASSIGNMENTS' => '/student/assignments.php',
            'OPEN_NOTIFICATIONS' => null,
            default => null,
        };
    }

    private static function authorizeAndPath(array $user, array $action): ?string
    {
        $role = (string)($user['role'] ?? '');
        $inst = (int)($user['institution_id'] ?? 0);
        $type = $action['type'];

        if ($type === 'OPEN_NOTIFICATIONS') {
            $prefix = match ($role) {
                'student' => 'student',
                'hod' => 'hod',
                'admin', 'superadmin' => 'admin',
                default => 'professor',
            };
            return '/' . $prefix . '/notifications.php';
        }

        if ($type === 'GRADE_ASSIGNMENT' || $type === 'VIEW_ASSIGNMENTS') {
            if (!in_array($role, ['professor', 'admin', 'superadmin'], true)) {
                return null;
            }
            if ($type === 'VIEW_ASSIGNMENTS') {
                return '/professor/assignments.php';
            }
            $id = (int)($action['record_id'] ?? 0);
            if ($id < 1) {
                return null;
            }
            $asg = Database::fetch(
                'SELECT id, professor_id, institution_id, class_id, subject_id FROM assignments WHERE id = ?',
                [$id]
            );
            if (!$asg || (int)$asg['institution_id'] !== $inst) {
                return null;
            }
            if ($role === 'professor' && (int)$asg['professor_id'] !== (int)$user['id']) {
                // Also allow if teaching assignment for that class/subject.
                if (!function_exists('professor_can_manage_subject')
                    || !professor_can_manage_subject($user, (int)$asg['subject_id'], (int)$asg['class_id'])) {
                    return null;
                }
            }
            return '/professor/assignments.php?id=' . $id;
        }

        if ($type === 'VIEW_PLAN') {
            if (!in_array($role, ['professor', 'admin', 'superadmin'], true)) {
                return null;
            }
            $id = (int)($action['record_id'] ?? 0);
            if ($id < 1) {
                return null;
            }
            $plan = Database::fetch(
                'SELECT id, professor_id, institution_id FROM course_plans WHERE id = ?',
                [$id]
            );
            if (!$plan || (int)$plan['institution_id'] !== $inst) {
                return null;
            }
            if ($role === 'professor' && (int)$plan['professor_id'] !== (int)$user['id']) {
                return null;
            }
            return '/professor/plan-view.php?id=' . $id;
        }

        if ($type === 'APPROVE_PLAN') {
            if (!in_array($role, ['hod', 'admin', 'superadmin'], true)) {
                return null;
            }
            $id = (int)($action['record_id'] ?? 0);
            if ($id < 1) {
                return '/hod/approvals.php';
            }
            $plan = Database::fetch(
                'SELECT id, institution_id, department_id FROM course_plans WHERE id = ?',
                [$id]
            );
            if (!$plan || (int)$plan['institution_id'] !== $inst) {
                return null;
            }
            if ($role === 'hod') {
                $deptId = (int)($user['department_id'] ?? 0);
                if ($deptId > 0 && (int)$plan['department_id'] !== $deptId) {
                    return null;
                }
            }
            return '/hod/approvals.php?id=' . $id;
        }

        if ($type === 'VIEW_ATTENDANCE' || $type === 'UPDATE_MARKS') {
            if (!in_array($role, ['professor', 'admin', 'superadmin'], true)) {
                return null;
            }
            $classId = (int)($action['class_id'] ?? 0);
            $subjectId = (int)($action['subject_id'] ?? 0);
            if ($classId < 1 || $subjectId < 1) {
                return $type === 'UPDATE_MARKS' ? '/professor/marks.php' : '/professor/attendance.php';
            }
            if ($role === 'professor'
                && function_exists('professor_can_manage_subject')
                && !professor_can_manage_subject($user, $subjectId, $classId)) {
                return null;
            }
            $q = 'class_id=' . $classId . '&subject_id=' . $subjectId;
            return $type === 'UPDATE_MARKS'
                ? '/professor/marks.php?' . $q
                : '/professor/attendance.php?' . $q;
        }

        if ($type === 'STUDENT_ATTENDANCE') {
            return $role === 'student' ? '/student/attendance.php' : null;
        }
        if ($type === 'STUDENT_ASSIGNMENTS') {
            return $role === 'student' ? '/student/assignments.php' : null;
        }

        return null;
    }

    private static function sanitizeStoredUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        // Strip absolute app base if present.
        $base = rtrim(base_url(''), '/');
        if ($base !== '' && str_starts_with($url, $base . '/')) {
            $url = substr($url, strlen($base));
        }
        if (preg_match('#^https?://#i', $url)) {
            return null; // never store/open external absolute URLs via this path
        }
        if (!str_starts_with($url, '/')) {
            $url = '/' . ltrim($url, '/');
        }
        if (str_starts_with($url, '//') || str_contains($url, '..')) {
            return null;
        }
        return mb_substr($url, 0, 255);
    }

    private static function legacyUrlAllowedForUser(array $user, string $rel): bool
    {
        $role = (string)($user['role'] ?? '');
        $path = parse_url($rel, PHP_URL_PATH) ?: $rel;
        $query = [];
        $qs = parse_url($rel, PHP_URL_QUERY);
        if (is_string($qs)) {
            parse_str($qs, $query);
        }

        // Role prefix gate
        if (str_starts_with($path, '/professor/') && !in_array($role, ['professor', 'admin', 'superadmin'], true)) {
            return false;
        }
        if (str_starts_with($path, '/hod/') && !in_array($role, ['hod', 'admin', 'superadmin'], true)) {
            return false;
        }
        if (str_starts_with($path, '/student/') && $role !== 'student' && !in_array($role, ['admin', 'superadmin'], true)) {
            return false;
        }
        if (str_starts_with($path, '/admin/') && !in_array($role, ['admin', 'superadmin'], true)) {
            return false;
        }

        // Deep checks for known resources
        if (str_contains($path, 'assignments.php') && isset($query['id']) && in_array($role, ['professor', 'admin', 'superadmin'], true)) {
            return self::authorizeAndPath($user, [
                'type' => 'GRADE_ASSIGNMENT',
                'record_id' => (int)$query['id'],
            ]) !== null;
        }
        if (str_contains($path, 'plan-view.php') && isset($query['id'])) {
            return self::authorizeAndPath($user, [
                'type' => 'VIEW_PLAN',
                'record_id' => (int)$query['id'],
            ]) !== null;
        }
        if (str_contains($path, 'approvals.php') && isset($query['id'])) {
            return self::authorizeAndPath($user, [
                'type' => 'APPROVE_PLAN',
                'record_id' => (int)$query['id'],
            ]) !== null;
        }
        if ((str_contains($path, 'attendance.php') || str_contains($path, 'marks.php'))
            && isset($query['class_id'], $query['subject_id'])
            && in_array($role, ['professor', 'admin', 'superadmin'], true)) {
            $type = str_contains($path, 'marks.php') ? 'UPDATE_MARKS' : 'VIEW_ATTENDANCE';
            return self::authorizeAndPath($user, [
                'type' => $type,
                'class_id' => (int)$query['class_id'],
                'subject_id' => (int)$query['subject_id'],
            ]) !== null;
        }

        return true;
    }

    /** @return array{status:string,at:?string,detail?:string} */
    private static function sendEmail(array $recipient, string $title, string $body, ?array $action): array
    {
        $status = self::providerStatus('email');
        if (!$status['configured']) {
            return ['status' => 'not_configured', 'at' => null, 'detail' => $status['detail']];
        }
        $to = trim((string)($recipient['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'failed', 'at' => date('Y-m-d H:i:s'), 'detail' => 'Recipient email missing'];
        }
        $from = (string)config('notifications.email.from', '');
        $headers = 'From: ' . $from . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
        $msg = $title . "\n\n" . $body;
        if ($action) {
            $msg .= "\n\n(Open the app notifications feed to take action securely.)";
        }
        $ok = @mail($to, '[' . (string)config('app_name', 'ProProfessor') . '] ' . $title, $msg, $headers);
        return [
            'status' => $ok ? 'delivered' : 'failed',
            'at' => date('Y-m-d H:i:s'),
            'detail' => $ok ? null : 'mail() returned false',
        ];
    }

    /** @return array{status:string,at:?string,detail?:string} */
    private static function sendWhatsApp(array $recipient, string $title, string $body): array
    {
        $status = self::providerStatus('whatsapp');
        if (!$status['configured']) {
            return ['status' => 'not_configured', 'at' => null, 'detail' => $status['detail']];
        }
        // Provider hook reserved — do not pretend send succeeded without a real adapter.
        return [
            'status' => 'failed',
            'at' => date('Y-m-d H:i:s'),
            'detail' => 'WhatsApp adapter not implemented for provider',
        ];
    }

    /** @return array{status:string,at:?string,detail?:string} */
    private static function sendSms(array $recipient, string $title, string $body): array
    {
        $status = self::providerStatus('sms');
        if (!$status['configured']) {
            return ['status' => 'not_configured', 'at' => null, 'detail' => $status['detail']];
        }
        return [
            'status' => 'failed',
            'at' => date('Y-m-d H:i:s'),
            'detail' => 'SMS adapter not implemented for provider',
        ];
    }
}
