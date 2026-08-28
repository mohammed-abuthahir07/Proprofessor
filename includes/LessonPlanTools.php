<?php
declare(strict_types=1);

/**
 * Lesson Planner enhancements (calendar, methodology, status, resources, QB/PPT context).
 * Does not replace AI lesson generation — only extends stored sessions safely.
 */
final class LessonPlanTools
{
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [];
        foreach (Database::fetchAll('SHOW COLUMNS FROM lesson_plans') as $c) {
            $cols[(string)$c['Field']] = true;
        }
        $alters = [
            'session_status' => "ADD COLUMN session_status VARCHAR(20) NOT NULL DEFAULT 'planned' COMMENT 'planned|completed|delayed' AFTER session_date",
            'planned_date' => "ADD COLUMN planned_date DATE NULL AFTER session_status",
            'actual_date' => "ADD COLUMN actual_date DATE NULL AFTER planned_date",
            'bloom_k_level' => "ADD COLUMN bloom_k_level VARCHAR(10) NULL AFTER actual_date",
            'unit_number' => "ADD COLUMN unit_number INT UNSIGNED NULL AFTER bloom_k_level",
            'suggested_method' => "ADD COLUMN suggested_method VARCHAR(200) NULL AFTER unit_number",
            'resources' => "ADD COLUMN resources LONGTEXT NULL COMMENT 'JSON resource suggestions' AFTER suggested_method",
            'calendar_event_id' => "ADD COLUMN calendar_event_id INT UNSIGNED NULL AFTER resources",
            'period_label' => "ADD COLUMN period_label VARCHAR(40) NULL AFTER calendar_event_id",
        ];
        foreach ($alters as $col => $sql) {
            if (!isset($cols[$col])) {
                try {
                    Database::query('ALTER TABLE lesson_plans ' . $sql);
                } catch (Throwable $e) {
                    // Column may already exist under race / partial migrate.
                }
            }
        }
    }

    /**
     * Load a course plan the user is allowed to use for Lesson Planner.
     */
    public static function loadOwnedPlan(array $user, int $planId): ?array
    {
        if ($planId < 1) {
            return null;
        }
        $plan = Database::fetch('SELECT * FROM course_plans WHERE id = ?', [$planId]);
        if (!$plan) {
            return null;
        }
        if ((int)($plan['institution_id'] ?? 0) !== (int)($user['institution_id'] ?? 0)) {
            return null;
        }
        $role = (string)($user['role'] ?? '');
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return $plan;
        }
        if ((int)($plan['professor_id'] ?? 0) !== (int)($user['id'] ?? 0)) {
            return null;
        }
        return $plan;
    }

    public static function loadOwnedSession(array $user, int $lessonId): ?array
    {
        self::ensureSchema();
        if ($lessonId < 1) {
            return null;
        }
        $row = Database::fetch(
            'SELECT lp.*, cp.institution_id, cp.department_id, cp.subject_name, cp.title AS plan_title,
                    cp.class_id, cp.subject_id, cp.professor_id AS plan_professor_id, cp.syllabus_input, cp.plan_data
             FROM lesson_plans lp
             JOIN course_plans cp ON cp.id = lp.plan_id
             WHERE lp.id = ?',
            [$lessonId]
        );
        if (!$row) {
            return null;
        }
        if ((int)($row['institution_id'] ?? 0) !== (int)($user['institution_id'] ?? 0)) {
            return null;
        }
        $role = (string)($user['role'] ?? '');
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return $row;
        }
        if ((int)($row['professor_id'] ?? 0) !== (int)($user['id'] ?? 0)
            && (int)($row['plan_professor_id'] ?? 0) !== (int)($user['id'] ?? 0)) {
            return null;
        }
        return $row;
    }

    public static function sessionUnitNumber(array $lesson): int
    {
        if (preg_match('/\bunit\s*(\d+)/i', (string)($lesson['title'] ?? ''), $m)) {
            return (int)$m[1];
        }
        $n = (int)($lesson['unit_number'] ?? 0);
        if ($n > 0) {
            return $n;
        }
        $content = json_decode((string)($lesson['content'] ?? '{}'), true) ?: [];
        return (int)($content['unit_number'] ?? $content['unit'] ?? 0);
    }

    /**
     * Backfill bloom/unit/methodology/resources without overwriting professor edits.
     *
     * @param list<array<string,mixed>> $units
     */
    public static function enrichSession(array $lesson, array $plan, array $units = [], bool $persist = true): array
    {
        self::ensureSchema();
        $updates = [];
        $content = json_decode((string)($lesson['content'] ?? '{}'), true) ?: [];

        $unitNum = (int)($lesson['unit_number'] ?? 0);
        if ($unitNum < 1) {
            $unitNum = (int)($content['unit_number'] ?? $content['unit'] ?? 0);
        }
        if ($unitNum < 1 && $units) {
            // Heuristic: map session order onto units (≈2 sessions per unit).
            $idx = max(0, (int)$lesson['session_number'] - 1);
            $u = $units[(int)floor($idx / 2)] ?? $units[$idx % count($units)] ?? null;
            if ($u) {
                $unitNum = (int)($u['unit_number'] ?? 0);
            }
        }
        if ($unitNum > 0 && empty($lesson['unit_number'])) {
            $updates['unit_number'] = $unitNum;
            $lesson['unit_number'] = $unitNum;
        }

        $bloom = strtoupper(trim((string)($lesson['bloom_k_level'] ?? '')));
        if ($bloom === '' || !preg_match('/^K[1-6]$/', $bloom)) {
            $bloom = strtoupper(trim((string)($content['bloom_k_level'] ?? $content['klevel'] ?? '')));
        }
        if (($bloom === '' || !preg_match('/^K[1-6]$/', $bloom)) && $unitNum > 0) {
            foreach ($units as $u) {
                if ((int)($u['unit_number'] ?? 0) === $unitNum) {
                    $bloom = strtoupper(trim((string)($u['bloom_k_level'] ?? '')));
                    break;
                }
            }
        }
        if ($bloom === '' || !preg_match('/^K[1-6]$/', $bloom)) {
            $bloom = 'K2';
        }
        if (empty($lesson['bloom_k_level'])) {
            $updates['bloom_k_level'] = $bloom;
            $lesson['bloom_k_level'] = $bloom;
        } else {
            $bloom = strtoupper(trim((string)$lesson['bloom_k_level']));
        }

        if (trim((string)($lesson['suggested_method'] ?? '')) === '') {
            $suggested = self::suggestMethodology($bloom, (string)$lesson['title'], (string)($plan['subject_name'] ?? ''));
            $updates['suggested_method'] = $suggested;
            $lesson['suggested_method'] = $suggested;
        }

        $resources = json_decode((string)($lesson['resources'] ?? 'null'), true);
        if (!is_array($resources) || $resources === []) {
            if ($persist) {
                $resources = self::suggestResources(
                    (string)($plan['subject_name'] ?? ''),
                    (string)$lesson['title'],
                    $bloom,
                    $unitNum
                );
                $updates['resources'] = json_encode($resources, JSON_UNESCAPED_UNICODE);
                $lesson['resources'] = $updates['resources'];
            } else {
                $resources = [];
                $lesson['resources'] = '[]';
            }
        }

        if (empty($lesson['session_status'])) {
            $updates['session_status'] = 'planned';
            $lesson['session_status'] = 'planned';
        }
        if (empty($lesson['planned_date']) && !empty($lesson['session_date'])) {
            $updates['planned_date'] = $lesson['session_date'];
            $lesson['planned_date'] = $lesson['session_date'];
        }

        if ($persist && $updates && !empty($lesson['id'])) {
            Database::update('lesson_plans', $updates, 'id = :id', [
                'id' => (int)$lesson['id'],
            ]);
        }

        return $lesson;
    }

    public static function suggestMethodology(string $bloom, string $topic = '', string $subject = ''): string
    {
        $bloom = strtoupper(trim($bloom));
        $topicL = strtolower($topic . ' ' . $subject);
        $coding = (bool)preg_match('/\b(c|java|python|code|program|pointer|array|function|loop|algorithm|lab)\b/i', $topicL);

        return match ($bloom) {
            'K1' => 'Lecture / Concept explanation with recall checks',
            'K2' => 'Interactive lecture + guided discussion',
            'K3' => $coding
                ? 'Hands-on coding / Demonstrated problem solving'
                : 'Demonstration solving / Demonstration with guided practice',
            'K4' => 'Case study / Comparative analysis / Group discussion',
            'K5' => 'Debate / Peer review / Evaluation against a rubric',
            'K6' => 'Project-based learning / Design studio / Creation task',
            default => 'Interactive lecture with formative checks',
        };
    }

    /**
     * Suggestion-only resources — titles/notes, no unverified claim URLs.
     *
     * @return list<array{type:string,title:string,note:string,url:?string}>
     */
    public static function suggestResources(string $subject, string $topic, string $bloom, int $unitNumber = 0): array
    {
        $subject = trim($subject) !== '' ? trim($subject) : 'the course';
        $topic = trim($topic) !== '' ? trim($topic) : 'this session';
        $unitLabel = $unitNumber > 0 ? ('Unit ' . $unitNumber . ' — ') : '';

        $out = [
            [
                'type' => 'reading',
                'title' => $unitLabel . 'Reading: ' . $topic,
                'note' => 'Suggested textbook/chapter review for ' . $subject . ' (verify against your syllabus).',
                'url' => null,
            ],
            [
                'type' => 'video',
                'title' => 'Video walkthrough: ' . $topic,
                'note' => 'Search your LMS or an institutional library for a short explainer matching Bloom ' . strtoupper($bloom) . '.',
                'url' => null,
            ],
            [
                'type' => 'reference',
                'title' => 'Reference notes — ' . $subject,
                'note' => 'Use department-approved notes / standard documentation for this topic.',
                'url' => null,
            ],
        ];

        $bloom = strtoupper($bloom);
        if (in_array($bloom, ['K3', 'K4', 'K5', 'K6'], true)) {
            $out[] = [
                'type' => 'practice',
                'title' => 'Practice set: ' . $topic,
                'note' => 'Assign 2–3 applied exercises aligned to ' . $bloom . ' before the next session.',
                'url' => null,
            ];
        }
        if (in_array($bloom, ['K5', 'K6'], true)) {
            $out[] = [
                'type' => 'project',
                'title' => 'Mini-project seed: ' . $topic,
                'note' => 'Optional extension task for higher-order creation/evaluation.',
                'url' => null,
            ];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $lessons
     * @return array{total:int,planned:int,completed:int,delayed:int,remaining:int,completion_pct:float}
     */
    public static function progressStats(array $lessons): array
    {
        $total = count($lessons);
        $planned = 0;
        $completed = 0;
        $delayed = 0;
        foreach ($lessons as $l) {
            $st = strtolower(trim((string)($l['session_status'] ?? 'planned')));
            if ($st === 'completed') {
                $completed++;
            } elseif ($st === 'delayed') {
                $delayed++;
            } else {
                $planned++;
            }
        }
        $remaining = max(0, $total - $completed);
        $pct = $total > 0 ? round(($completed * 100) / $total, 1) : 0.0;
        return [
            'total' => $total,
            'planned' => $planned,
            'completed' => $completed,
            'delayed' => $delayed,
            'remaining' => $remaining,
            'completion_pct' => $pct,
        ];
    }

    public static function sanitizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['planned', 'completed', 'delayed'], true) ? $status : 'planned';
    }

    /** @param list<array<string,mixed>> $resources */
    public static function sanitizeResources(array $resources): array
    {
        $allowedTypes = ['reading', 'video', 'reference', 'practice', 'project', 'online', 'documentation'];
        $out = [];
        foreach (array_slice($resources, 0, 12) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $type = strtolower(trim((string)($r['type'] ?? 'reading')));
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'reading';
            }
            $title = trim((string)($r['title'] ?? ''));
            if ($title === '' || strlen($title) > 200) {
                continue;
            }
            $note = trim((string)($r['note'] ?? ''));
            if (strlen($note) > 400) {
                $note = substr($note, 0, 400);
            }
            $url = trim((string)($r['url'] ?? ''));
            $safeUrl = null;
            if ($url !== '' && self::isSafeHttpUrl($url)) {
                $safeUrl = $url;
            }
            $out[] = [
                'type' => $type,
                'title' => $title,
                'note' => $note,
                'url' => $safeUrl,
            ];
        }
        return $out;
    }

    public static function isSafeHttpUrl(string $url): bool
    {
        if (strlen($url) > 300) {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_starts_with($host, '127.') || str_starts_with($host, '0.')) {
            return false;
        }
        return true;
    }

    /**
     * Sync to academic_events (institution calendar) + return ICS for personal calendar apps.
     * Idempotent: reuses calendar_event_id when present.
     *
     * @return array{event_id:int,ics:string,title:string}
     */
    public static function syncToCalendar(array $user, array $lesson, array $plan, ?array $classRow = null): array
    {
        self::ensureSchema();
        $instId = (int)($plan['institution_id'] ?? $user['institution_id'] ?? 0);
        if ($instId < 1) {
            throw new RuntimeException('Missing institution for calendar sync.');
        }

        $date = (string)($lesson['planned_date'] ?? $lesson['session_date'] ?? '');
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d', strtotime('+1 day') ?: time());
        }
        $mins = max(30, min(180, (int)($lesson['duration_mins'] ?? 60)));
        $period = trim((string)($lesson['period_label'] ?? ''));
        $section = '';
        if ($classRow) {
            $section = class_batch_label($classRow);
        }
        $subject = (string)($plan['subject_name'] ?? 'Course');
        $title = $subject . ' · Session ' . (int)$lesson['session_number'] . ' — ' . (string)$lesson['title'];
        $desc = "Course: {$subject}\n"
            . 'Topic: ' . (string)$lesson['title'] . "\n"
            . 'Professor: ' . (string)($user['full_name'] ?? '') . "\n"
            . ($period !== '' ? "Period: {$period}\n" : '')
            . ($section !== '' ? "Class/Section: {$section}\n" : '')
            . 'Duration: ' . $mins . " minutes\n"
            . 'Reminder: Arrive 5 minutes early; review session objectives.';

        $meta = json_encode([
            'lesson_plan_id' => (int)$lesson['id'],
            'plan_id' => (int)$plan['id'],
            'professor_id' => (int)$user['id'],
            'period' => $period,
            'duration_mins' => $mins,
            'source' => 'lesson_planner',
        ], JSON_UNESCAPED_UNICODE);

        $eventId = (int)($lesson['calendar_event_id'] ?? 0);
        if ($eventId > 0) {
            $existing = Database::fetch(
                'SELECT id FROM academic_events WHERE id = ? AND institution_id = ?',
                [$eventId, $instId]
            );
            if ($existing) {
                Database::update('academic_events', [
                    'title' => mb_substr($title, 0, 200),
                    'event_type' => 'lesson_session',
                    'event_date' => $date,
                    'end_date' => $date,
                    'description' => $desc,
                    'meta' => $meta,
                ], 'id = :id AND institution_id = :iid', [
                    'id' => $eventId,
                    'iid' => $instId,
                ]);
            } else {
                $eventId = 0;
            }
        }
        if ($eventId < 1) {
            // Avoid duplicates for same lesson in this institution (portable meta scan).
            $candidates = Database::fetchAll(
                "SELECT id, meta FROM academic_events
                 WHERE institution_id = ? AND event_type = 'lesson_session'
                 ORDER BY id DESC LIMIT 200",
                [$instId]
            );
            foreach ($candidates as $ev) {
                $m = json_decode((string)($ev['meta'] ?? ''), true) ?: [];
                if ((int)($m['lesson_plan_id'] ?? 0) === (int)$lesson['id']) {
                    $eventId = (int)$ev['id'];
                    Database::update('academic_events', [
                        'title' => mb_substr($title, 0, 200),
                        'event_date' => $date,
                        'end_date' => $date,
                        'description' => $desc,
                        'meta' => $meta,
                    ], 'id = :id AND institution_id = :iid', [
                        'id' => $eventId,
                        'iid' => $instId,
                    ]);
                    break;
                }
            }
        }
        if ($eventId < 1) {
            $eventId = Database::insert('academic_events', [
                'institution_id' => $instId,
                'title' => mb_substr($title, 0, 200),
                'event_type' => 'lesson_session',
                'event_date' => $date,
                'end_date' => $date,
                'description' => $desc,
                'meta' => $meta,
            ]);
        }

        Database::update('lesson_plans', [
            'calendar_event_id' => $eventId,
            'planned_date' => $date,
            'session_date' => $date,
        ], 'id = :id', [
            'id' => (int)$lesson['id'],
        ]);

        $ics = self::buildIcsEvent($title, $desc, $date, $mins, (string)($user['full_name'] ?? 'Professor'));
        return ['event_id' => $eventId, 'ics' => $ics, 'title' => $title];
    }

    public static function buildIcsEvent(string $title, string $description, string $dateYmd, int $durationMins, string $organizer): string
    {
        $uid = 'lesson-' . bin2hex(random_bytes(8)) . '@proprofessor';
        $dtStart = str_replace('-', '', $dateYmd) . 'T090000';
        $endTs = strtotime($dateYmd . ' 09:00:00') ?: time();
        $dtEnd = date('Ymd\THis', $endTs + ($durationMins * 60));
        $now = gmdate('Ymd\THis\Z');
        $esc = static function (string $s): string {
            $s = str_replace(["\r\n", "\n", "\r"], '\\n', $s);
            return addcslashes($s, ',;\\');
        };
        return "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//ProProfessor AI//Lesson Planner//EN\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "METHOD:PUBLISH\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:{$uid}\r\n"
            . "DTSTAMP:{$now}\r\n"
            . "DTSTART:{$dtStart}\r\n"
            . "DTEND:{$dtEnd}\r\n"
            . 'SUMMARY:' . $esc($title) . "\r\n"
            . 'DESCRIPTION:' . $esc($description) . "\r\n"
            . 'ORGANIZER:CN=' . $esc($organizer) . "\r\n"
            . "BEGIN:VALARM\r\n"
            . "TRIGGER:-PT30M\r\n"
            . "ACTION:DISPLAY\r\n"
            . "DESCRIPTION:Lesson reminder\r\n"
            . "END:VALARM\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n";
    }

    public static function classContextForPlan(array $plan): ?array
    {
        $classId = (int)($plan['class_id'] ?? 0);
        if ($classId < 1) {
            return null;
        }
        return Database::fetch(
            'SELECT c.*, d.code AS dept_code, d.name AS dept_name
             FROM classes c
             LEFT JOIN departments d ON d.id = c.department_id
             WHERE c.id = ? AND c.institution_id = ?',
            [$classId, (int)$plan['institution_id']]
        ) ?: null;
    }

    /** Build Question Bank deep-link query (existing module). */
    public static function questionBankUrl(array $lesson, array $plan): string
    {
        $params = [
            'plan_id' => (int)$plan['id'],
            'unit' => max(1, (int)($lesson['unit_number'] ?? 1)),
            'klevel' => strtoupper((string)($lesson['bloom_k_level'] ?? 'K2')),
            'topic' => (string)$lesson['title'],
        ];
        $objs = lesson_as_list($lesson['objectives'] ?? []);
        if ($objs) {
            $params['context'] = (string)$plan['subject_name'] . "\nUnit " . $params['unit'] . ': ' . $lesson['title']
                . "\nBloom: " . $params['klevel']
                . "\nOutcomes:\n- " . implode("\n- ", array_slice($objs, 0, 5));
        } else {
            $params['context'] = (string)$plan['subject_name'] . ' — ' . (string)$lesson['title']
                . ' (Bloom ' . $params['klevel'] . ')';
        }
        return base_url('/professor/questions.php?' . http_build_query($params));
    }

    /** Build PPT Generator deep-link query (existing module). */
    public static function pptUrl(array $lesson, array $plan): string
    {
        $unit = max(1, (int)($lesson['unit_number'] ?? 1));
        $title = trim((string)$plan['subject_name'] . ' · Unit ' . $unit . ' · ' . (string)$lesson['title']);
        $objs = lesson_as_list($lesson['objectives'] ?? []);
        $context = "Subject: {$plan['subject_name']}\n"
            . "Session: {$lesson['title']}\n"
            . 'Bloom: ' . (string)($lesson['bloom_k_level'] ?? 'K2') . "\n"
            . 'Unit: ' . $unit . "\n";
        if ($objs) {
            $context .= "Learning outcomes:\n- " . implode("\n- ", array_slice($objs, 0, 6)) . "\n";
        }
        $syl = trim((string)($plan['syllabus_input'] ?? ''));
        if ($syl !== '') {
            $context .= "\nSyllabus excerpt:\n" . mb_substr($syl, 0, 1200);
        }
        return base_url('/professor/ppt.php?' . http_build_query([
            'plan_id' => (int)$plan['id'],
            'title' => $title,
            'context' => $context,
        ]));
    }
}
