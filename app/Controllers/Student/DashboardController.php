<?php
declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Controller;
use App\Models\MarksFormula;
use App\Models\Notification;
use Auth;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireRole('student');
        Auth::refresh();
        $user = $this->user();
        ensure_student_academic_schema();
        require_once dirname(__DIR__, 3) . '/includes/AttendanceTools.php';
        MarksFormula::ensureInternalMarksSchema();

        $tz = new \DateTimeZone('Asia/Kolkata');
        $today = new \DateTime('today', $tz);
        $weekEnd = (clone $today)->modify('+6 days');

        $fmtNum = static function ($n): string {
            $text = rtrim(rtrim(number_format((float)$n, 1, '.', ''), '0'), '.');
            return $text === '' ? '0' : $text;
        };

        $cards = \AttendanceTools::studentCurrentSubjectAttendance($user);
        $sumPresent = 0;
        $sumTotal = 0;
        $minPct = 75.0;
        foreach ($cards as $card) {
            $sumPresent += (int)$card['present'];
            $sumTotal += (int)$card['total'];
            $minPct = (float)($card['band']['min'] ?? $minPct);
        }
        $overallPct = $sumTotal > 0 ? round($sumPresent * 100 / $sumTotal, 1) : null;
        if ($sumTotal < 1) {
            $attendance = ['percent' => null, 'hint' => 'No classes yet', 'tone' => 'none'];
        } else {
            $band = \AttendanceTools::shortageBand((float)$overallPct, $minPct);
            $attendance = [
                'percent' => $fmtNum($overallPct) . '%',
                'hint' => (string)$band['label'],
                'tone' => ($band['band'] ?? '') === 'below' ? 'low' : 'ok',
            ];
        }

        $subjectNames = [];
        foreach (courses_for_student($user) as $course) {
            $subjectNames[(int)$course['id']] = (string)($course['name'] ?? '');
        }

        $pending = [];
        foreach (assignments_visible_to_student($user) as $assignment) {
            $grade = $assignment['grade'] ?? null;
            $status = strtolower((string)($assignment['sub_status'] ?? ''));
            if (($grade !== null && $grade !== '') || $status === 'graded') {
                continue;
            }
            if (in_array($status, ['submitted', 'late'], true)) {
                continue;
            }
            $pending[] = $assignment;
        }

        $nearest = null;
        $nearestDays = null;
        foreach ($pending as $assignment) {
            $raw = trim((string)($assignment['deadline'] ?? ''));
            if ($raw === '') {
                continue;
            }
            try {
                $due = new \DateTime($raw, $tz);
            } catch (\Throwable) {
                continue;
            }
            $dueDay = (clone $due)->setTime(0, 0);
            $days = (int)$today->diff($dueDay)->format('%r%a');
            if ($nearestDays === null || $days < $nearestDays) {
                $nearestDays = $days;
                $nearest = $assignment;
            }
        }
        if ($pending === []) {
            $pendingHint = 'None due';
        } elseif ($nearestDays === null) {
            $pendingHint = count($pending) . ' open';
        } elseif ($nearestDays < 0) {
            $pendingHint = abs($nearestDays) === 1 ? '1 overdue' : abs($nearestDays) . ' days overdue';
        } elseif ($nearestDays === 0) {
            $pendingHint = '1 due today';
        } elseif ($nearestDays === 1) {
            $pendingHint = '1 due tomorrow';
        } else {
            $pendingHint = '1 due in ' . $nearestDays . ' days';
        }

        $marks = $this->marksSummary($user, $fmtNum);
        $exam = $this->nextExam($user, $today);
        $upcoming = $this->upcoming($user, $pending, $subjectNames, $today, $weekEnd, $tz);
        $notices = [];
        foreach (array_slice(Notification::forUser((int)$user['id'], null, 4), 0, 4) as $notice) {
            $body = trim((string)($notice['body'] ?? ''));
            $firstLine = trim((string)strtok($body, "\r\n"));
            $firstLine = trim((string)preg_replace('/\s*\[[a-z0-9-]+\]/', '', $firstLine));
            if (strlen($firstLine) > 90) {
                $firstLine = rtrim(substr($firstLine, 0, 87)) . '…';
            }
            $created = trim((string)($notice['created_at'] ?? ''));
            $when = $created;
            try {
                if ($created !== '') {
                    $when = (new \DateTime($created, $tz))->format('M j, Y');
                }
            } catch (\Throwable) {
            }
            $notices[] = [
                'title' => (string)($notice['title'] ?? 'Notice'),
                'detail' => $firstLine,
                'when' => $when,
            ];
        }

        $this->view('student/dashboard', [
            'title' => 'Student Dashboard',
            'active' => 'dash',
            'subtitle' => 'Courses · materials · Ask AI',
            'attendance' => $attendance,
            'pendingCount' => count($pending),
            'pendingHint' => $pendingHint,
            'marks' => $marks,
            'exam' => $exam,
            'fees' => student_fee_snapshot($user, institution_academic_year((int)($user['institution_id'] ?? 0))),
            'upcoming' => $upcoming,
            'notices' => $notices,
        ]);
    }

    /** @param callable(mixed):string $fmtNum */
    private function marksSummary(array $user, callable $fmtNum): array
    {
        $instId = (int)($user['institution_id'] ?? 0);
        $classId = student_class_id($user);
        $courses = courses_for_student($user);
        $empty = ['percent' => null, 'hint' => 'Not published yet'];
        if ($classId < 1 || !$courses) {
            return $empty;
        }
        $academicYear = institution_academic_year($instId);
        $reg = trim((string)($user['register_no'] ?? ''));
        $allowed = array_map(static fn($s) => (int)$s['id'], $courses);
        $params = [$instId, $classId, (int)$user['id'], $reg];
        $sql = 'SELECT m.computed_total, m.meta, f.total_max AS formula_total_max
                FROM internal_marks m
                LEFT JOIN marks_formulas f ON f.id = m.formula_id AND f.institution_id = m.institution_id
                WHERE m.institution_id = ?
                  AND m.class_id = ?
                  AND (m.student_id = ? OR (m.register_no <> "" AND m.register_no = ?))';
        if ($academicYear !== '') {
            $sql .= ' AND (m.academic_year = ? OR m.academic_year = "")';
            $params[] = $academicYear;
        }
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $sql .= " AND m.subject_id IN ($placeholders)";
        foreach ($allowed as $sid) {
            $params[] = $sid;
        }
        $pcts = [];
        foreach (\Database::fetchAll($sql, $params) as $row) {
            $meta = json_decode((string)($row['meta'] ?? '{}'), true) ?: [];
            $totalMax = (float)($meta['total_max'] ?? $row['formula_total_max'] ?? 0);
            $total = $row['computed_total'];
            if ($total === null || $total === '' || $totalMax <= 0) {
                continue;
            }
            $pcts[] = ((float)$total * 100) / $totalMax;
        }
        if ($pcts === []) {
            return $empty;
        }
        $n = count($pcts);
        return [
            'percent' => $fmtNum(round(array_sum($pcts) / $n, 1)) . '%',
            'hint' => 'Across ' . $n . ' ' . ($n === 1 ? 'subject' : 'subjects'),
        ];
    }

    /** @return array{days:?int,label:string,hint:string} */
    private function nextExam(array $user, \DateTime $today): array
    {
        $empty = ['days' => null, 'label' => '—', 'hint' => 'No exam date set'];
        $instId = (int)($user['institution_id'] ?? 0);
        if ($instId < 1) {
            return $empty;
        }
        // Same timetable rows the student calendar shows, already scoped to this
        // student's level, year, department, section and semester.
        foreach (exam_timetable_for_student($user) as $exam) {
            $when = \DateTime::createFromFormat('Y-m-d', (string)$exam['exam_date'], $today->getTimezone());
            if (!$when) {
                continue;
            }
            $when->setTime(0, 0);
            $days = (int)$today->diff($when)->format('%r%a');
            if ($days < 0) {
                continue;
            }
            return $this->examCard($days, (string)$exam['subject_name'], $when);
        }
        $row = \Database::fetch(
            'SELECT title, event_date FROM academic_events
             WHERE institution_id = ? AND event_date >= ? AND event_type = ?
             ORDER BY event_date ASC LIMIT 1',
            [$instId, $today->format('Y-m-d'), 'exam']
        );
        if (!$row) {
            return $empty;
        }
        try {
            $when = new \DateTime((string)$row['event_date'], $today->getTimezone());
        } catch (\Throwable) {
            return $empty;
        }
        $days = (int)$today->diff($when)->format('%r%a');
        if ($days < 0) {
            return $empty;
        }
        return $this->examCard($days, (string)$row['title'], $when);
    }

    /** @return array{days:?int,label:string,hint:string} */
    private function examCard(int $days, string $title, \DateTime $when): array
    {
        $title = trim($title);
        $whenLabel = match (true) {
            $days === 0 => 'Today',
            $days === 1 => 'Tomorrow',
            default => $when->format('M j'),
        };
        return [
            'days' => $days,
            'label' => $days === 0 ? 'Today' : (string)$days,
            'hint' => $title !== '' ? $title . ' · ' . $whenLabel : $whenLabel,
        ];
    }

    /**
     * @param list<array<string,mixed>> $pending
     * @param array<int,string> $subjectNames
     * @return list<array{title:string,meta:string,when:string,tone:string}>
     */
    private function upcoming(array $user, array $pending, array $subjectNames, \DateTime $today, \DateTime $weekEnd, \DateTimeZone $tz): array
    {
        $items = [];
        $start = $today->format('Y-m-d');
        $end = $weekEnd->format('Y-m-d');
        foreach ($pending as $assignment) {
            $raw = trim((string)($assignment['deadline'] ?? ''));
            if ($raw === '') {
                continue;
            }
            try {
                $due = new \DateTime($raw, $tz);
            } catch (\Throwable) {
                continue;
            }
            $day = $due->format('Y-m-d');
            if ($day < $start || $day > $end) {
                continue;
            }
            $sid = (int)($assignment['subject_id'] ?? 0);
            $items[] = [
                'sort' => $day,
                'title' => (string)($assignment['title'] ?? 'Assignment'),
                'meta' => $subjectNames[$sid] ?? '',
                'when' => $this->whenLabel($due, $today),
                'tone' => 'warn',
            ];
        }

        $instId = (int)($user['institution_id'] ?? 0);
        if ($instId > 0) {
            $events = \Database::fetchAll(
                'SELECT title, event_type, event_date FROM academic_events
                 WHERE institution_id = ? AND event_date BETWEEN ? AND ? AND event_type <> ?
                 ORDER BY event_date, id',
                [$instId, $start, $end, 'lesson_session']
            );
            foreach ($events as $event) {
                try {
                    $due = new \DateTime((string)$event['event_date'], $tz);
                } catch (\Throwable) {
                    continue;
                }
                $type = strtolower((string)($event['event_type'] ?? ''));
                $items[] = [
                    'sort' => $due->format('Y-m-d'),
                    'title' => (string)($event['title'] ?? 'Event'),
                    'meta' => $type === 'exam' ? 'Exam' : 'Calendar',
                    'when' => $this->whenLabel($due, $today),
                    'tone' => $type === 'exam' ? 'exam' : 'info',
                ];
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp($a['sort'], $b['sort']));
        foreach ($items as &$item) {
            unset($item['sort']);
        }
        unset($item);
        return $items;
    }

    private function whenLabel(\DateTime $due, \DateTime $today): string
    {
        $days = (int)$today->diff((clone $due)->setTime(0, 0))->format('%r%a');
        if ($days === 0) {
            return 'Today';
        }
        if ($days === 1) {
            return 'Tomorrow';
        }
        if ($days === -1) {
            return 'Yesterday';
        }
        return $due->format('M j');
    }
}
