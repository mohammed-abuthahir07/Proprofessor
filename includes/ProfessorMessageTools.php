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
                meta LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ann_prof (professor_id, created_at),
                INDEX idx_ann_inst (institution_id),
                INDEX idx_ann_scope (subject_id, class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
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
     * @return array{ok:bool,error?:string,recipient_count?:int,announcement_id?:int}
     */
    public static function send(array $user, int $year, int $subjectId, int $classId, string $message, string $title = ''): array
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
            'meta' => json_encode([
                'course_label' => $courseLabel,
                'class_label' => $classLabel,
                'year_label' => $yearLabel,
                'sender_name' => $senderName,
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
}
