<?php
declare(strict_types=1);

namespace App\Services;

use Database;
use Gemini;

/**
 * Aggregates real professor-scoped academic data for dashboard widgets.
 * All queries are limited to the authenticated professor's institution and assignments.
 */
final class ProfessorDashboardInsights
{
    public const WIDGET_KEYS = [
        'today_glance',
        'weekly_digest',
        'obe_compliance',
        'at_risk',
        'dept_benchmark',
    ];

    /** Internal marks below this % of formula max count as at-risk (when no other config exists). */
    public const MARKS_RISK_PCT = 50.0;

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public static function build(array $user): array
    {
        $professorId = (int)($user['id'] ?? 0);
        $instId = (int)($user['institution_id'] ?? 0);
        $deptId = (int)($user['department_id'] ?? 0);
        $minAtt = institution_attendance_min($instId);
        $assignments = self::professorAssignments($professorId, $instId);

        $attendanceAgg = self::attendanceAggregates($assignments, $minAtt);
        $pendingGrading = self::pendingGradingCount($professorId);
        $todayClasses = self::todaysClasses($professorId, $instId);
        $weekStats = self::weekStats($professorId, $instId, $assignments, $attendanceAgg, $pendingGrading);
        $obe = self::obeCompliance($professorId, $instId);
        $atRisk = self::atRiskStudents($professorId, $instId, $assignments, $minAtt);
        $benchmark = self::departmentBenchmark($professorId, $instId, $deptId, $assignments, $minAtt);
        $digest = self::weeklyDigestText($user, $weekStats);

        return [
            'attendance_min' => $minAtt,
            'today' => [
                'classes' => $todayClasses,
                'pending_grading' => $pendingGrading,
                'low_attendance' => $attendanceAgg['low_count'],
            ],
            'week' => $weekStats,
            'digest' => $digest,
            'obe' => $obe,
            'at_risk' => $atRisk,
            'benchmark' => $benchmark,
            'widget_order' => self::widgetOrder($user),
        ];
    }

    /**
     * @param array<string,mixed> $user
     * @return list<string>
     */
    public static function widgetOrder(array $user): array
    {
        $prefs = json_decode((string)($user['preferences'] ?? '{}'), true) ?: [];
        $saved = $prefs['dashboard_widgets'] ?? [];
        if (!is_array($saved) || !$saved) {
            return self::WIDGET_KEYS;
        }
        $order = [];
        foreach ($saved as $key) {
            $key = (string)$key;
            if (in_array($key, self::WIDGET_KEYS, true) && !in_array($key, $order, true)) {
                $order[] = $key;
            }
        }
        foreach (self::WIDGET_KEYS as $key) {
            if (!in_array($key, $order, true)) {
                $order[] = $key;
            }
        }
        return $order;
    }

    /**
     * Persist widget order into users.preferences (per professor).
     *
     * @param list<string> $order
     */
    public static function saveWidgetOrder(int $userId, array $order): void
    {
        $user = Database::fetch('SELECT preferences FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return;
        }
        $prefs = json_decode((string)($user['preferences'] ?? '{}'), true) ?: [];
        $clean = [];
        foreach ($order as $key) {
            $key = (string)$key;
            if (in_array($key, self::WIDGET_KEYS, true) && !in_array($key, $clean, true)) {
                $clean[] = $key;
            }
        }
        foreach (self::WIDGET_KEYS as $key) {
            if (!in_array($key, $clean, true)) {
                $clean[] = $key;
            }
        }
        $prefs['dashboard_widgets'] = $clean;
        Database::update('users', [
            'preferences' => json_encode($prefs, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $userId]);
    }

    /**
     * @return list<array{subject_id:int,class_id:int,subject_name:string,subject_code:string,class_label:string}>
     */
    private static function professorAssignments(int $professorId, int $instId): array
    {
        if ($professorId < 1) {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT sa.subject_id, sa.class_id, s.name AS subject_name, s.code AS subject_code,
                    c.name AS class_name, c.year, c.section, c.meta, d.code AS dept_code, d.name AS dept_name
             FROM subject_assignments sa
             JOIN subjects s ON s.id = sa.subject_id AND s.institution_id = ?
             JOIN classes c ON c.id = sa.class_id AND c.institution_id = ?
             LEFT JOIN departments d ON d.id = c.department_id
             WHERE sa.professor_id = ? AND sa.class_id IS NOT NULL',
            [$instId, $instId, $professorId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'subject_id' => (int)$r['subject_id'],
                'class_id' => (int)$r['class_id'],
                'subject_name' => (string)$r['subject_name'],
                'subject_code' => (string)$r['subject_code'],
                'class_label' => class_batch_label($r),
            ];
        }
        return $out;
    }

    /**
     * @param list<array{subject_id:int,class_id:int}> $assignments
     * @return array{low_count:int,student_pct:array<string,float>,pairs:int}
     */
    private static function attendanceAggregates(array $assignments, float $minAtt): array
    {
        $studentPct = []; // key class|reg => worst/avg pct across subjects
        $pairCount = 0;
        foreach ($assignments as $a) {
            $classId = (int)$a['class_id'];
            $subjectId = (int)$a['subject_id'];
            if ($classId < 1 || $subjectId < 1) {
                continue;
            }
            $pairCount++;
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
            foreach ($agg as $reg => $v) {
                $pct = !empty($v['total']) ? round(($v['present'] ?? 0) * 100 / $v['total'], 1) : 100.0;
                $key = $classId . '|' . $reg;
                if (!isset($studentPct[$key]) || $pct < $studentPct[$key]) {
                    $studentPct[$key] = $pct;
                }
            }
        }
        $low = 0;
        foreach ($studentPct as $pct) {
            if ($pct < $minAtt) {
                $low++;
            }
        }
        return ['low_count' => $low, 'student_pct' => $studentPct, 'pairs' => $pairCount];
    }

    private static function pendingGradingCount(int $professorId): int
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS c
             FROM assignment_submissions s
             JOIN assignments a ON a.id = s.assignment_id
             WHERE a.professor_id = ?
               AND (s.status IS NULL OR s.status = "" OR s.status NOT IN ("graded"))
               AND (s.grade IS NULL OR s.grade = "")',
            [$professorId]
        );
        return (int)($row['c'] ?? 0);
    }

    /**
     * @return list<array{title:string,when:string,source:string}>
     */
    private static function todaysClasses(int $professorId, int $instId): array
    {
        $today = date('Y-m-d');
        $out = [];

        $sessions = Database::fetchAll(
            'SELECT s.session_date, s.period, s.topic, sub.name AS subject_name, sub.code AS subject_code,
                    c.year, c.section, c.meta, d.code AS dept_code
             FROM attendance_sessions s
             JOIN subjects sub ON sub.id = s.subject_id AND sub.institution_id = ?
             JOIN classes c ON c.id = s.class_id AND c.institution_id = ?
             LEFT JOIN departments d ON d.id = c.department_id
             WHERE s.professor_id = ? AND s.session_date = ?
             ORDER BY s.period ASC, s.id ASC',
            [$instId, $instId, $professorId, $today]
        );
        foreach ($sessions as $s) {
            $period = trim((string)($s['period'] ?? ''));
            $when = $period !== '' ? ('Period ' . $period) : 'Today';
            $out[] = [
                'title' => (string)$s['subject_name'],
                'when' => $when,
                'source' => 'attendance',
                'meta' => trim(($s['subject_code'] ?? '') . ' · ' . class_batch_label($s), ' ·'),
            ];
        }

        $lessons = Database::fetchAll(
            'SELECT lp.title, lp.session_date, lp.session_number, lp.duration_mins,
                    cp.subject_name, cp.title AS plan_title
             FROM lesson_plans lp
             JOIN course_plans cp ON cp.id = lp.plan_id
             WHERE cp.professor_id = ?
               AND cp.institution_id = ?
               AND lp.session_date = ?
             ORDER BY lp.session_number ASC',
            [$professorId, $instId, $today]
        );
        foreach ($lessons as $lp) {
            $when = 'Session ' . (int)$lp['session_number'];
            if (!empty($lp['duration_mins'])) {
                $when .= ' · ' . (int)$lp['duration_mins'] . ' min';
            }
            $out[] = [
                'title' => (string)($lp['subject_name'] ?: $lp['title']),
                'when' => $when,
                'source' => 'lesson',
                'meta' => (string)($lp['title'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{subject_id:int,class_id:int}> $assignments
     * @param array{low_count:int} $attendanceAgg
     * @return array<string,int|float>
     */
    private static function weekStats(
        int $professorId,
        int $instId,
        array $assignments,
        array $attendanceAgg,
        int $pendingGrading
    ): array {
        $start = date('Y-m-d', strtotime('monday this week') ?: time());
        $end = date('Y-m-d', strtotime('sunday this week') ?: time());

        $classesConducted = (int)(Database::fetch(
            'SELECT COUNT(*) AS c FROM attendance_sessions
             WHERE professor_id = ? AND session_date BETWEEN ? AND ?',
            [$professorId, $start, $end]
        )['c'] ?? 0);

        $graded = (int)(Database::fetch(
            'SELECT COUNT(*) AS c
             FROM assignment_submissions s
             JOIN assignments a ON a.id = s.assignment_id
             WHERE a.professor_id = ?
               AND s.status = "graded"
               AND s.graded_at IS NOT NULL
               AND DATE(s.graded_at) BETWEEN ? AND ?',
            [$professorId, $start, $end]
        )['c'] ?? 0);

        $assignmentsCreated = (int)(Database::fetch(
            'SELECT COUNT(*) AS c FROM assignments
             WHERE professor_id = ? AND DATE(created_at) BETWEEN ? AND ?',
            [$professorId, $start, $end]
        )['c'] ?? 0);

        $marksSaved = 0;
        if ($assignments) {
            $marksSaved = (int)(Database::fetch(
                'SELECT COUNT(*) AS c FROM internal_marks
                 WHERE professor_id = ? AND institution_id = ?
                   AND DATE(updated_at) BETWEEN ? AND ?',
                [$professorId, $instId, $start, $end]
            )['c'] ?? 0);
        }

        $plansTouched = (int)(Database::fetch(
            'SELECT COUNT(*) AS c FROM course_plans
             WHERE professor_id = ? AND DATE(updated_at) BETWEEN ? AND ?',
            [$professorId, $start, $end]
        )['c'] ?? 0);

        return [
            'week_start' => $start,
            'week_end' => $end,
            'classes_conducted' => $classesConducted,
            'assignments_graded' => $graded,
            'assignments_created' => $assignmentsCreated,
            'marks_updated' => $marksSaved,
            'plans_updated' => $plansTouched,
            'low_attendance' => (int)$attendanceAgg['low_count'],
            'pending_grading' => $pendingGrading,
        ];
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,int|float|string> $week
     * @return array{lines:list<string>,source:string}
     */
    private static function weeklyDigestText(array $user, array $week): array
    {
        $lines = [
            'You conducted ' . (int)$week['classes_conducted'] . ' class session(s) this week.',
            (int)$week['assignments_graded'] . ' assignment submission(s) were graded.',
            (int)$week['assignments_created'] . ' assignment(s) were created.',
            (int)$week['marks_updated'] . ' internal mark record(s) were updated.',
            (int)$week['low_attendance'] . ' student(s) are currently at attendance risk.',
            (int)$week['pending_grading'] . ' submission(s) are pending grading.',
            (int)$week['plans_updated'] . ' course plan(s) were updated.',
        ];

        $prefs = json_decode((string)($user['preferences'] ?? '{}'), true) ?: [];
        $cacheKey = 'W' . date('o') . date('W');
        $cache = is_array($prefs['weekly_digest_cache'] ?? null) ? $prefs['weekly_digest_cache'] : [];
        if (($cache['key'] ?? '') === $cacheKey && !empty($cache['lines']) && is_array($cache['lines'])) {
            return ['lines' => array_values(array_map('strval', $cache['lines'])), 'source' => (string)($cache['source'] ?? 'cached')];
        }

        $gemini = new Gemini();
        if ($gemini->isConfigured()) {
            $prompt = "Write 4 short bullet sentences for a professor weekly teaching digest using ONLY these facts (do not invent numbers):\n"
                . implode("\n", $lines)
                . "\nReturn plain text, one sentence per line, no markdown.";
            $res = $gemini->generate(
                'You summarize teaching activity for university professors. Be concise and factual.',
                $prompt
            );
            if (!empty($res['ok']) && !empty($res['text'])) {
                $aiLines = preg_split('/\r\n|\r|\n/', trim((string)$res['text'])) ?: [];
                $aiLines = array_values(array_filter(array_map('trim', $aiLines), static fn($l) => $l !== ''));
                if ($aiLines) {
                    $prefs['weekly_digest_cache'] = [
                        'key' => $cacheKey,
                        'source' => 'gemini',
                        'lines' => array_slice($aiLines, 0, 6),
                    ];
                    Database::update('users', [
                        'preferences' => json_encode($prefs, JSON_UNESCAPED_UNICODE),
                    ], 'id = :id', ['id' => (int)$user['id']]);
                    return ['lines' => $prefs['weekly_digest_cache']['lines'], 'source' => 'gemini'];
                }
            }
        }

        $prefs['weekly_digest_cache'] = [
            'key' => $cacheKey,
            'source' => 'stats',
            'lines' => $lines,
        ];
        Database::update('users', [
            'preferences' => json_encode($prefs, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => (int)$user['id']]);

        return ['lines' => $lines, 'source' => 'stats'];
    }

    /**
     * OBE metrics from existing course-plan / bloom / unit outcomes (no invented attainment).
     *
     * @return array<string,mixed>
     */
    private static function obeCompliance(int $professorId, int $instId): array
    {
        $plans = Database::fetchAll(
            'SELECT id, bloom_data, ai_score, plan_data, status
             FROM course_plans
             WHERE professor_id = ? AND institution_id = ?
             ORDER BY updated_at DESC
             LIMIT 20',
            [$professorId, $instId]
        );

        $unitsTotal = 0;
        $unitsWithClo = 0;
        $bloomHigherSum = 0.0;
        $bloomSamples = 0;
        $ploMapped = 0;
        $ploTotal = 0;

        foreach ($plans as $p) {
            $bloom = json_decode((string)($p['bloom_data'] ?? '{}'), true) ?: [];
            if ($bloom) {
                $higher = (float)($bloom['K4'] ?? 0) + (float)($bloom['K5'] ?? 0) + (float)($bloom['K6'] ?? 0);
                $bloomHigherSum += $higher;
                $bloomSamples++;
            }
            $planData = json_decode((string)($p['plan_data'] ?? '{}'), true) ?: [];
            if (!empty($planData['learning_outcomes']) && is_array($planData['learning_outcomes'])) {
                $ploTotal += count($planData['learning_outcomes']);
                foreach ($planData['learning_outcomes'] as $lo) {
                    if (trim((string)$lo) !== '') {
                        $ploMapped++;
                    }
                }
            }

            $units = Database::fetchAll(
                'SELECT outcomes FROM plan_units WHERE plan_id = ?',
                [(int)$p['id']]
            );
            foreach ($units as $u) {
                $unitsTotal++;
                $outcomes = json_decode((string)($u['outcomes'] ?? '[]'), true);
                if (!is_array($outcomes)) {
                    $raw = trim((string)($u['outcomes'] ?? ''));
                    $outcomes = $raw !== '' ? [$raw] : [];
                }
                $has = false;
                foreach ($outcomes as $o) {
                    if (trim((string)$o) !== '') {
                        $has = true;
                        break;
                    }
                }
                if ($has) {
                    $unitsWithClo++;
                }
            }
        }

        $cloPct = $unitsTotal > 0 ? round(($unitsWithClo / $unitsTotal) * 100, 1) : null;
        $ploPct = $ploTotal > 0 ? round(($ploMapped / $ploTotal) * 100, 1) : null;
        // Higher-order Bloom share — used by HOD compliance as OBE quality signal.
        $bloomHigher = $bloomSamples > 0 ? round($bloomHigherSum / $bloomSamples, 1) : null;

        return [
            'plans' => count($plans),
            'clo_mapping_pct' => $cloPct,
            'plo_mapping_pct' => $ploPct,
            'bloom_higher_pct' => $bloomHigher,
            'units_total' => $unitsTotal,
            'units_with_clo' => $unitsWithClo,
            'available' => $plans !== [],
        ];
    }

    /**
     * @param list<array{subject_id:int,class_id:int,subject_name:string,subject_code?:string,class_label?:string}> $assignments
     * @return list<array<string,mixed>>
     */
    private static function atRiskStudents(int $professorId, int $instId, array $assignments, float $minAtt): array
    {
        if (!$assignments) {
            return [];
        }

        $classMeta = [];
        $subjectByClass = [];
        foreach ($assignments as $a) {
            $classId = (int)$a['class_id'];
            $subjectId = (int)$a['subject_id'];
            if ($classId < 1) {
                continue;
            }
            if (!isset($classMeta[$classId])) {
                $cls = Database::fetch(
                    'SELECT c.id, c.year, c.section, c.name, c.meta, d.code AS dept_code, d.name AS dept_name
                     FROM classes c
                     LEFT JOIN departments d ON d.id = c.department_id
                     WHERE c.id = ? AND c.institution_id = ?',
                    [$classId, $instId]
                );
                if ($cls) {
                    $classMeta[$classId] = [
                        'year' => (int)($cls['year'] ?? 0),
                        'section' => trim((string)($cls['section'] ?? '')),
                        'class_label' => class_batch_label($cls),
                    ];
                } else {
                    $classMeta[$classId] = [
                        'year' => 0,
                        'section' => '',
                        'class_label' => (string)($a['class_label'] ?? 'Class'),
                    ];
                }
            }
            $subjectByClass[$classId][$subjectId] = trim(
                ((string)($a['subject_code'] ?? '') !== '' ? $a['subject_code'] . ' · ' : '')
                . (string)$a['subject_name']
            );
        }

        /** @var array<string,array<string,mixed>> $risk */
        $risk = [];

        foreach ($assignments as $a) {
            $classId = (int)$a['class_id'];
            $subjectId = (int)$a['subject_id'];
            $subjectLabel = $subjectByClass[$classId][$subjectId] ?? (string)$a['subject_name'];
            $rows = Database::fetchAll(
                'SELECT r.register_no, r.status
                 FROM attendance_records r
                 JOIN attendance_sessions s ON s.id = r.session_id
                 WHERE s.class_id = ? AND s.subject_id = ? AND s.professor_id = ?',
                [$classId, $subjectId, $professorId]
            );
            $agg = [];
            foreach ($rows as $r) {
                $reg = (string)$r['register_no'];
                $agg[$reg]['total'] = ($agg[$reg]['total'] ?? 0) + 1;
                if (in_array($r['status'], ['present', 'late'], true)) {
                    $agg[$reg]['present'] = ($agg[$reg]['present'] ?? 0) + 1;
                }
            }
            foreach ($agg as $reg => $v) {
                $reg = (string)$reg;
                $pct = !empty($v['total']) ? ($v['present'] ?? 0) * 100 / $v['total'] : 100.0;
                if ($pct < $minAtt) {
                    $key = $classId . '|' . $subjectId . '|' . $reg;
                    $risk[$key] = self::riskBucket(
                        $risk[$key] ?? null,
                        $classId,
                        $reg,
                        'attendance',
                        'Below ' . $minAtt . '%',
                        $subjectId,
                        $subjectLabel,
                        $classMeta[$classId] ?? []
                    );
                }
            }
        }

        $marks = Database::fetchAll(
            'SELECT m.class_id, m.subject_id, m.register_no, m.student_name, m.computed_total, m.meta, m.formula_id,
                    f.total_max, s.code AS subject_code, s.name AS subject_name
             FROM internal_marks m
             LEFT JOIN marks_formulas f ON f.id = m.formula_id AND f.institution_id = m.institution_id
             LEFT JOIN subjects s ON s.id = m.subject_id AND s.institution_id = m.institution_id
             WHERE m.professor_id = ? AND m.institution_id = ?',
            [$professorId, $instId]
        );
        foreach ($marks as $m) {
            $meta = json_decode((string)($m['meta'] ?? '{}'), true) ?: [];
            $max = (float)($meta['total_max'] ?? $m['total_max'] ?? 25);
            if ($max <= 0) {
                $max = 25.0;
            }
            $total = $m['computed_total'];
            if ($total === null || $total === '') {
                continue;
            }
            $pct = ((float)$total / $max) * 100.0;
            if ($pct < self::MARKS_RISK_PCT) {
                $classId = (int)$m['class_id'];
                $subjectId = (int)$m['subject_id'];
                $reg = (string)$m['register_no'];
                $subjectLabel = $subjectByClass[$classId][$subjectId]
                    ?? trim(((string)($m['subject_code'] ?? '') !== '' ? $m['subject_code'] . ' · ' : '') . (string)($m['subject_name'] ?? 'Subject'));
                $key = $classId . '|' . $subjectId . '|' . $reg;
                $bucket = self::riskBucket(
                    $risk[$key] ?? null,
                    $classId,
                    $reg,
                    'marks',
                    'Below ' . self::MARKS_RISK_PCT . '% of internal max',
                    $subjectId,
                    $subjectLabel,
                    $classMeta[$classId] ?? []
                );
                if (!empty($m['student_name'])) {
                    $bucket['name'] = (string)$m['student_name'];
                }
                $risk[$key] = $bucket;
            }
        }

        $assignmentIds = Database::fetchAll(
            'SELECT a.id, a.class_id, a.subject_id, s.code AS subject_code, s.name AS subject_name
             FROM assignments a
             LEFT JOIN subjects s ON s.id = a.subject_id
             WHERE a.professor_id = ? AND a.institution_id = ?',
            [$professorId, $instId]
        );
        if (!$assignmentIds) {
            $assignmentIds = Database::fetchAll(
                'SELECT a.id, a.class_id, a.subject_id, s.code AS subject_code, s.name AS subject_name
                 FROM assignments a
                 LEFT JOIN subjects s ON s.id = a.subject_id
                 WHERE a.professor_id = ?',
                [$professorId]
            );
        }

        foreach ($assignmentIds as $asg) {
            $aid = (int)$asg['id'];
            $classId = (int)($asg['class_id'] ?? 0);
            $subjectId = (int)($asg['subject_id'] ?? 0);
            if ($classId < 1) {
                continue;
            }
            // If assignment has no subject, attribute to each subject the professor teaches in that class.
            $subjectTargets = [];
            if ($subjectId > 0) {
                $subjectTargets[$subjectId] = $subjectByClass[$classId][$subjectId]
                    ?? trim(((string)($asg['subject_code'] ?? '') !== '' ? $asg['subject_code'] . ' · ' : '') . (string)($asg['subject_name'] ?? 'Subject'));
            } elseif (!empty($subjectByClass[$classId])) {
                $subjectTargets = $subjectByClass[$classId];
            } else {
                $subjectTargets[0] = '—';
            }

            $pending = Database::fetchAll(
                'SELECT s.student_id, u.register_no, u.full_name, s.status, s.grade
                 FROM assignment_submissions s
                 JOIN users u ON u.id = s.student_id AND u.institution_id = ?
                 WHERE s.assignment_id = ?
                   AND (s.status IS NULL OR s.status = "" OR s.status IN ("submitted","pending") OR ((s.grade IS NULL OR s.grade = "") AND s.status <> "graded"))',
                [$instId, $aid]
            );
            foreach ($pending as $p) {
                $reg = (string)($p['register_no'] ?? '');
                if ($reg === '') {
                    continue;
                }
                foreach ($subjectTargets as $sid => $subjectLabel) {
                    $key = $classId . '|' . (int)$sid . '|' . $reg;
                    $bucket = self::riskBucket(
                        $risk[$key] ?? null,
                        $classId,
                        $reg,
                        'assignments',
                        'Pending / ungraded submission',
                        (int)$sid,
                        (string)$subjectLabel,
                        $classMeta[$classId] ?? []
                    );
                    if (!empty($p['full_name'])) {
                        $bucket['name'] = (string)$p['full_name'];
                    }
                    $risk[$key] = $bucket;
                }
            }

            $missing = Database::fetchAll(
                'SELECT u.register_no, u.full_name
                 FROM enrollments e
                 JOIN users u ON u.id = e.student_id
                 WHERE e.class_id = ? AND e.status = "active" AND u.institution_id = ?
                   AND NOT EXISTS (
                     SELECT 1 FROM assignment_submissions s
                     WHERE s.assignment_id = ? AND s.student_id = u.id
                   )
                 LIMIT 50',
                [$classId, $instId, $aid]
            );
            foreach ($missing as $m) {
                $reg = (string)$m['register_no'];
                foreach ($subjectTargets as $sid => $subjectLabel) {
                    $key = $classId . '|' . (int)$sid . '|' . $reg;
                    $bucket = self::riskBucket(
                        $risk[$key] ?? null,
                        $classId,
                        $reg,
                        'assignments',
                        'Missing submission',
                        (int)$sid,
                        (string)$subjectLabel,
                        $classMeta[$classId] ?? []
                    );
                    $bucket['name'] = (string)$m['full_name'];
                    $risk[$key] = $bucket;
                }
            }
        }

        foreach ($risk as $key => &$item) {
            $cid = (int)$item['class_id'];
            if (isset($classMeta[$cid])) {
                $item['year'] = $classMeta[$cid]['year'];
                $item['section'] = $classMeta[$cid]['section'];
                $item['class_label'] = $classMeta[$cid]['class_label'];
            }
            if (!empty($item['name'])) {
                continue;
            }
            $row = Database::fetch(
                'SELECT full_name FROM students_roster WHERE class_id = ? AND register_no = ? AND institution_id = ?',
                [$cid, (string)$item['register_no'], $instId]
            );
            if (!$row) {
                $row = Database::fetch(
                    'SELECT full_name FROM users WHERE institution_id = ? AND register_no = ? AND role = "student"',
                    [$instId, (string)$item['register_no']]
                );
            }
            $item['name'] = (string)($row['full_name'] ?? $item['register_no']);
        }
        unset($item);

        $list = array_values($risk);
        usort($list, static function ($a, $b) {
            $rank = ['High' => 0, 'Medium' => 1, 'Low' => 2];
            $cmp = ($rank[$a['level']] ?? 9) <=> ($rank[$b['level']] ?? 9);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return array_slice($list, 0, 20);
    }

    /**
     * @param array<string,mixed>|null $existing
     * @param array{year?:int,section?:string,class_label?:string} $classInfo
     * @return array<string,mixed>
     */
    private static function riskBucket(
        ?array $existing,
        int $classId,
        string $reg,
        string $flag,
        string $detail,
        int $subjectId = 0,
        string $subjectLabel = '',
        array $classInfo = []
    ): array {
        $item = $existing ?? [
            'class_id' => $classId,
            'register_no' => $reg,
            'name' => '',
            'flags' => [],
            'details' => [],
            'level' => 'Medium',
            'subject_id' => $subjectId,
            'subject' => $subjectLabel,
            'year' => (int)($classInfo['year'] ?? 0),
            'section' => (string)($classInfo['section'] ?? ''),
            'class_label' => (string)($classInfo['class_label'] ?? ''),
        ];
        if ($subjectLabel !== '' && empty($item['subject'])) {
            $item['subject'] = $subjectLabel;
            $item['subject_id'] = $subjectId;
        }
        if (!empty($classInfo)) {
            $item['year'] = (int)($classInfo['year'] ?? $item['year'] ?? 0);
            $item['section'] = (string)($classInfo['section'] ?? $item['section'] ?? '');
            $item['class_label'] = (string)($classInfo['class_label'] ?? $item['class_label'] ?? '');
        }
        if (!in_array($flag, $item['flags'], true)) {
            $item['flags'][] = $flag;
            $item['details'][$flag] = $detail;
        }
        $n = count($item['flags']);
        $item['level'] = $n >= 2 ? 'High' : 'Medium';
        return $item;
    }

    /**
     * Aggregate professor vs department averages (no other professor student-level data).
     *
     * @param list<array{subject_id:int,class_id:int}> $assignments
     * @return array<string,mixed>
     */
    private static function departmentBenchmark(
        int $professorId,
        int $instId,
        int $deptId,
        array $assignments,
        float $minAtt
    ): array {
        $youAtt = self::averageAttendanceForPairs($assignments);
        $youMarks = self::averageMarksPct($professorId, $instId, true);

        $deptAtt = null;
        $deptMarks = null;
        if ($deptId > 0) {
            $deptPairs = Database::fetchAll(
                'SELECT DISTINCT sa.subject_id, sa.class_id
                 FROM subject_assignments sa
                 JOIN classes c ON c.id = sa.class_id
                 JOIN subjects s ON s.id = sa.subject_id
                 WHERE c.institution_id = ? AND c.department_id = ?
                   AND s.institution_id = ?',
                [$instId, $deptId, $instId]
            );
            $deptAtt = self::averageAttendanceForPairs(array_map(static fn($r) => [
                'subject_id' => (int)$r['subject_id'],
                'class_id' => (int)$r['class_id'],
            ], $deptPairs));

            $deptMarks = self::averageMarksPct(0, $instId, false, $deptId);
        }

        return [
            'department_id' => $deptId,
            'attendance_min' => $minAtt,
            'you_attendance' => $youAtt,
            'dept_attendance' => $deptAtt,
            'you_marks' => $youMarks,
            'dept_marks' => $deptMarks,
        ];
    }

    /**
     * @param list<array{subject_id:int,class_id:int}> $pairs
     */
    private static function averageAttendanceForPairs(array $pairs): ?float
    {
        $sum = 0.0;
        $n = 0;
        foreach ($pairs as $a) {
            $classId = (int)$a['class_id'];
            $subjectId = (int)$a['subject_id'];
            $rows = Database::fetchAll(
                'SELECT r.status
                 FROM attendance_records r
                 JOIN attendance_sessions s ON s.id = r.session_id
                 WHERE s.class_id = ? AND s.subject_id = ?',
                [$classId, $subjectId]
            );
            if (!$rows) {
                continue;
            }
            $total = count($rows);
            $present = 0;
            foreach ($rows as $r) {
                if (in_array($r['status'], ['present', 'late'], true)) {
                    $present++;
                }
            }
            $sum += ($present * 100.0) / max(1, $total);
            $n++;
        }
        return $n > 0 ? round($sum / $n, 1) : null;
    }

    private static function averageMarksPct(int $professorId, int $instId, bool $byProfessor, ?int $deptId = null): ?float
    {
        if ($byProfessor) {
            $rows = Database::fetchAll(
                'SELECT m.computed_total, m.meta, f.total_max
                 FROM internal_marks m
                 LEFT JOIN marks_formulas f ON f.id = m.formula_id
                 WHERE m.institution_id = ? AND m.professor_id = ? AND m.computed_total IS NOT NULL',
                [$instId, $professorId]
            );
        } else {
            $sql = 'SELECT m.computed_total, m.meta, f.total_max
                    FROM internal_marks m
                    JOIN classes c ON c.id = m.class_id
                    LEFT JOIN marks_formulas f ON f.id = m.formula_id
                    WHERE m.institution_id = ? AND m.computed_total IS NOT NULL';
            $params = [$instId];
            if ($deptId && $deptId > 0) {
                $sql .= ' AND c.department_id = ?';
                $params[] = $deptId;
            }
            $rows = Database::fetchAll($sql, $params);
        }
        if (!$rows) {
            return null;
        }
        $sum = 0.0;
        $n = 0;
        foreach ($rows as $m) {
            $meta = json_decode((string)($m['meta'] ?? '{}'), true) ?: [];
            $max = (float)($meta['total_max'] ?? $m['total_max'] ?? 25);
            if ($max <= 0) {
                continue;
            }
            $sum += ((float)$m['computed_total'] / $max) * 100.0;
            $n++;
        }
        return $n > 0 ? round($sum / $n, 1) : null;
    }
}
