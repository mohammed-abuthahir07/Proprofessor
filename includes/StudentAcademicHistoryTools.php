<?php
declare(strict_types=1);

/**
 * Student Academic History — read-only views of past academic contexts.
 * Derives context from existing records (attendance, marks, assignments, enrollments).
 * Does not modify or delete historical data.
 */
final class StudentAcademicHistoryTools
{
    /**
     * Unique (class + year + semester) contexts where this student has academic records.
     * Excludes the student's CURRENT academic context.
     *
     * @return list<array<string,mixed>>
     */
    public static function historicalContexts(array $user): array
    {
        ensure_student_academic_schema();
        $studentId = (int)($user['id'] ?? 0);
        $instId = (int)($user['institution_id'] ?? 0);
        if ($studentId < 1 || $instId < 1) {
            return [];
        }

        $current = student_academic_context($user);
        $currentKey = self::contextKey(
            (int)$current['class_id'],
            (int)$current['year'],
            (string)$current['semester_key']
        );

        $pairs = self::collectSubjectClassPairs($user);
        $grouped = [];

        foreach ($pairs as $pair) {
            $classId = (int)$pair['class_id'];
            $subjectId = (int)$pair['subject_id'];
            if ($classId < 1 || $subjectId < 1) {
                continue;
            }
            $subject = Database::fetch(
                'SELECT id, code, name, semester, meta, institution_id, department_id
                 FROM subjects WHERE id = ? AND institution_id = ?',
                [$subjectId, $instId]
            );
            if (!$subject) {
                continue;
            }
            $year = subject_academic_year_level($subject);
            if ($year < 1) {
                $class = Database::fetch('SELECT year FROM classes WHERE id = ? AND institution_id = ?', [$classId, $instId]);
                $year = (int)($class['year'] ?? 0);
            }
            if ($year < 1) {
                continue;
            }
            $semKey = subject_semester_key((string)($subject['semester'] ?? ''));
            $key = self::contextKey($classId, $year, $semKey);
            if ($key === $currentKey) {
                continue;
            }
            if (!isset($grouped[$key])) {
                $classRow = Database::fetch(
                    'SELECT c.*, d.code AS dept_code, d.name AS dept_name
                     FROM classes c LEFT JOIN departments d ON d.id = c.department_id
                     WHERE c.id = ? AND c.institution_id = ?',
                    [$classId, $instId]
                );
                $grouped[$key] = [
                    'class_id' => $classId,
                    'year' => $year,
                    'year_label' => subject_year_label($year),
                    'semester_key' => $semKey,
                    'semester_label' => $semKey === 'even' ? 'Even' : 'Odd',
                    'semester' => subject_normalize_semester($semKey === 'even' ? 'Even' : 'Odd'),
                    'class_label' => $classRow ? class_batch_label($classRow) : '',
                    'section' => trim((string)($classRow['section'] ?? '')),
                    'subject_ids' => [],
                ];
            }
            $grouped[$key]['subject_ids'][$subjectId] = true;
        }

        $out = [];
        foreach ($grouped as $ctx) {
            $ctx['subject_count'] = count($ctx['subject_ids']);
            unset($ctx['subject_ids']);
            $out[] = $ctx;
        }

        usort($out, static function (array $a, array $b): int {
            if ($a['year'] !== $b['year']) {
                return $b['year'] <=> $a['year'];
            }
            if ($a['class_id'] !== $b['class_id']) {
                return $a['class_id'] <=> $b['class_id'];
            }
            return strcmp((string)$b['semester_key'], (string)$a['semester_key']);
        });

        return $out;
    }

    /**
     * Subjects with historical records in a given context.
     * Validates the student actually has records in that scope.
     *
     * @return list<array<string,mixed>>
     */
    public static function historicalSubjects(array $user, int $classId, int $year, string $semesterKey): array
    {
        ensure_student_academic_schema();
        $instId = (int)($user['institution_id'] ?? 0);
        $semesterKey = $semesterKey === 'even' ? 'even' : 'odd';
        if ($instId < 1 || $classId < 1 || $year < 1) {
            return [];
        }
        if (!self::studentOwnsContext($user, $classId, $year, $semesterKey)) {
            return [];
        }

        $pairs = self::collectSubjectClassPairs($user);
        $subjects = [];
        foreach ($pairs as $pair) {
            if ((int)$pair['class_id'] !== $classId) {
                continue;
            }
            $subjectId = (int)$pair['subject_id'];
            $subject = Database::fetch(
                'SELECT * FROM subjects WHERE id = ? AND institution_id = ?',
                [$subjectId, $instId]
            );
            if (!$subject) {
                continue;
            }
            $subYear = subject_academic_year_level($subject);
            if ($subYear < 1) {
                $subYear = $year;
            }
            if ($subYear !== $year) {
                continue;
            }
            if (subject_semester_key((string)($subject['semester'] ?? '')) !== $semesterKey) {
                continue;
            }
            $type = subject_course_type($subject);
            $subjects[$subjectId] = [
                'id' => $subjectId,
                'code' => (string)$subject['code'],
                'name' => (string)$subject['name'],
                'course_type' => $type,
                'professor_name' => self::historicalProfessorName($user, $classId, $subjectId),
            ];
        }

        $list = array_values($subjects);
        usort($list, static function (array $a, array $b): int {
            $ta = ($a['course_type'] ?? '') === 'lab' ? 1 : 0;
            $tb = ($b['course_type'] ?? '') === 'lab' ? 1 : 0;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }
            return strcasecmp((string)$a['name'], (string)$b['name']);
        });
        return $list;
    }

    /**
     * Read-only historical academic summary for one subject in one context.
     *
     * @return array<string,mixed>|null
     */
    public static function historicalSubjectDetail(array $user, int $classId, int $subjectId, int $year, string $semesterKey): ?array
    {
        ensure_student_academic_schema();
        $instId = (int)($user['institution_id'] ?? 0);
        $semesterKey = $semesterKey === 'even' ? 'even' : 'odd';
        if ($instId < 1 || $classId < 1 || $subjectId < 1 || $year < 1) {
            return null;
        }
        if (!self::studentOwnsContext($user, $classId, $year, $semesterKey)) {
            return null;
        }
        if (!self::studentHasRecordsForSubject($user, $classId, $subjectId)) {
            return null;
        }

        $subject = Database::fetch(
            'SELECT * FROM subjects WHERE id = ? AND institution_id = ?',
            [$subjectId, $instId]
        );
        if (!$subject) {
            return null;
        }
        $subYear = subject_academic_year_level($subject);
        if ($subYear > 0 && $subYear !== $year) {
            return null;
        }
        if (subject_semester_key((string)($subject['semester'] ?? '')) !== $semesterKey) {
            return null;
        }

        $class = Database::fetch(
            'SELECT c.*, d.code AS dept_code, d.name AS dept_name
             FROM classes c LEFT JOIN departments d ON d.id = c.department_id
             WHERE c.id = ? AND c.institution_id = ?',
            [$classId, $instId]
        );

        return [
            'subject' => $subject,
            'class' => $class,
            'year' => $year,
            'year_label' => subject_year_label($year),
            'semester_key' => $semesterKey,
            'semester_label' => $semesterKey === 'even' ? 'Even' : 'Odd',
            'class_label' => $class ? class_batch_label($class) : '',
            'professor_name' => self::historicalProfessorName($user, $classId, $subjectId),
            'attendance_pct' => self::historicalAttendancePct($user, $classId, $subjectId),
            'assignments' => self::historicalAssignmentStats($user, $classId, $subjectId),
            'internal_marks' => self::historicalInternalMarks($user, $classId, $subjectId),
            'tests' => self::historicalTestStats($user, $classId, $subjectId),
        ];
    }

    /** Group contexts by year for UI cards. */
    public static function historicalContextsByYear(array $user): array
    {
        $byYear = [];
        foreach (self::historicalContexts($user) as $ctx) {
            $y = (int)$ctx['year'];
            if (!isset($byYear[$y])) {
                $byYear[$y] = [
                    'year' => $y,
                    'year_label' => (string)$ctx['year_label'],
                    'contexts' => [],
                ];
            }
            $byYear[$y]['contexts'][] = $ctx;
        }
        krsort($byYear);
        return array_values($byYear);
    }

    private static function contextKey(int $classId, int $year, string $semesterKey): string
    {
        return $classId . '|' . $year . '|' . ($semesterKey === 'even' ? 'even' : 'odd');
    }

    /** @return list<array{class_id:int,subject_id:int}> */
    private static function collectSubjectClassPairs(array $user): array
    {
        $studentId = (int)$user['id'];
        $instId = (int)$user['institution_id'];
        $reg = trim((string)($user['register_no'] ?? ''));

        $pairs = [];

        $att = Database::fetchAll(
            'SELECT DISTINCT sess.class_id, sess.subject_id
             FROM attendance_records r
             JOIN attendance_sessions sess ON sess.id = r.session_id
             WHERE sess.institution_id = ?
               AND sess.subject_id IS NOT NULL
               AND (r.student_id = ? OR (? <> "" AND r.register_no = ?))',
            [$instId, $studentId, $reg, $reg]
        );
        foreach ($att as $row) {
            $pairs[] = ['class_id' => (int)$row['class_id'], 'subject_id' => (int)$row['subject_id']];
        }

        $marks = Database::fetchAll(
            'SELECT DISTINCT class_id, subject_id FROM internal_marks
             WHERE institution_id = ?
               AND (student_id = ? OR (? <> "" AND register_no = ?))',
            [$instId, $studentId, $reg, $reg]
        );
        foreach ($marks as $row) {
            $pairs[] = ['class_id' => (int)$row['class_id'], 'subject_id' => (int)$row['subject_id']];
        }

        $asg = Database::fetchAll(
            'SELECT DISTINCT a.class_id, a.subject_id
             FROM assignment_submissions s
             JOIN assignments a ON a.id = s.assignment_id
             WHERE s.student_id = ? AND a.institution_id = ? AND a.subject_id IS NOT NULL',
            [$studentId, $instId]
        );
        foreach ($asg as $row) {
            $pairs[] = ['class_id' => (int)$row['class_id'], 'subject_id' => (int)$row['subject_id']];
        }

        // Dedupe — only attendance, internal marks, and assignment submissions (real activity).
        $seen = [];
        $out = [];
        foreach ($pairs as $p) {
            $k = $p['class_id'] . '|' . $p['subject_id'];
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $p;
        }
        return $out;
    }

    private static function studentHasRecordsForSubject(array $user, int $classId, int $subjectId): bool
    {
        foreach (self::collectSubjectClassPairs($user) as $p) {
            if ((int)$p['class_id'] === $classId && (int)$p['subject_id'] === $subjectId) {
                return true;
            }
        }
        return false;
    }

    private static function studentOwnsContext(array $user, int $classId, int $year, string $semesterKey): bool
    {
        foreach (self::historicalContexts($user) as $ctx) {
            if ((int)$ctx['class_id'] === $classId
                && (int)$ctx['year'] === $year
                && (string)$ctx['semester_key'] === $semesterKey) {
                return true;
            }
        }
        return false;
    }

    private static function historicalProfessorName(array $user, int $classId, int $subjectId): ?string
    {
        $instId = (int)$user['institution_id'];
        $studentId = (int)$user['id'];
        $reg = trim((string)($user['register_no'] ?? ''));

        $fromMarks = Database::fetch(
            'SELECT u.full_name FROM internal_marks m
             JOIN users u ON u.id = m.professor_id
             WHERE m.institution_id = ? AND m.class_id = ? AND m.subject_id = ?
               AND (m.student_id = ? OR (? <> "" AND m.register_no = ?))
             ORDER BY m.updated_at DESC LIMIT 1',
            [$instId, $classId, $subjectId, $studentId, $reg, $reg]
        );
        if ($fromMarks && trim((string)$fromMarks['full_name']) !== '') {
            return (string)$fromMarks['full_name'];
        }

        $fromAtt = Database::fetch(
            'SELECT u.full_name, COUNT(*) AS cnt
             FROM attendance_records r
             JOIN attendance_sessions sess ON sess.id = r.session_id
             JOIN users u ON u.id = sess.professor_id
             WHERE sess.institution_id = ? AND sess.class_id = ? AND sess.subject_id = ?
               AND (r.student_id = ? OR (? <> "" AND r.register_no = ?))
             GROUP BY u.id, u.full_name
             ORDER BY cnt DESC LIMIT 1',
            [$instId, $classId, $subjectId, $studentId, $reg, $reg]
        );
        if ($fromAtt && trim((string)$fromAtt['full_name']) !== '') {
            return (string)$fromAtt['full_name'];
        }

        $fromAsg = Database::fetch(
            'SELECT u.full_name FROM assignments a
             JOIN users u ON u.id = a.professor_id
             WHERE a.institution_id = ? AND a.class_id = ? AND a.subject_id = ?
             ORDER BY a.id DESC LIMIT 1',
            [$instId, $classId, $subjectId]
        );
        if ($fromAsg && trim((string)$fromAsg['full_name']) !== '') {
            return (string)$fromAsg['full_name'];
        }

        $sa = Database::fetch(
            'SELECT u.full_name FROM subject_assignments sa
             JOIN users u ON u.id = sa.professor_id
             WHERE sa.class_id = ? AND sa.subject_id = ?
             ORDER BY sa.id DESC LIMIT 1',
            [$classId, $subjectId]
        );
        return $sa ? (string)$sa['full_name'] : null;
    }

    private static function historicalAttendancePct(array $user, int $classId, int $subjectId): ?float
    {
        $instId = (int)$user['institution_id'];
        $studentId = (int)$user['id'];
        $reg = trim((string)($user['register_no'] ?? ''));

        $rows = Database::fetchAll(
            'SELECT r.status
             FROM attendance_records r
             JOIN attendance_sessions sess ON sess.id = r.session_id
             WHERE sess.institution_id = ? AND sess.class_id = ? AND sess.subject_id = ?
               AND (r.student_id = ? OR (? <> "" AND r.register_no = ?))',
            [$instId, $classId, $subjectId, $studentId, $reg, $reg]
        );
        if (!$rows) {
            return null;
        }
        $total = count($rows);
        $present = 0;
        foreach ($rows as $r) {
            if (in_array($r['status'], ['present', 'late'], true)) {
                $present++;
            }
        }
        return $total > 0 ? round($present * 100 / $total, 1) : null;
    }

    /** @return array{submitted:int,graded:int,total:int}|null */
    private static function historicalAssignmentStats(array $user, int $classId, int $subjectId): ?array
    {
        $instId = (int)$user['institution_id'];
        $studentId = (int)$user['id'];

        $assignments = Database::fetchAll(
            'SELECT id FROM assignments
             WHERE institution_id = ? AND class_id = ? AND subject_id = ? AND status = "published"',
            [$instId, $classId, $subjectId]
        );
        if (!$assignments) {
            return null;
        }
        $ids = array_map(static fn($a) => (int)$a['id'], $assignments);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$studentId], $ids);
        $subs = Database::fetchAll(
            "SELECT id, status, grade FROM assignment_submissions
             WHERE student_id = ? AND assignment_id IN ($placeholders)",
            $params
        );
        $submitted = 0;
        $graded = 0;
        foreach ($subs as $s) {
            $st = (string)($s['status'] ?? '');
            if (in_array($st, ['submitted', 'late', 'graded', 'returned'], true)) {
                $submitted++;
            }
            if ($st === 'graded' || ($s['grade'] !== null && $s['grade'] !== '')) {
                $graded++;
            }
        }
        return [
            'submitted' => $submitted,
            'graded' => $graded,
            'total' => count($ids),
        ];
    }

    /** @return array<string,mixed>|null */
    private static function historicalInternalMarks(array $user, int $classId, int $subjectId): ?array
    {
        $instId = (int)$user['institution_id'];
        $studentId = (int)$user['id'];
        $reg = trim((string)($user['register_no'] ?? ''));

        $row = Database::fetch(
            'SELECT m.*, f.total_max AS formula_total_max
             FROM internal_marks m
             LEFT JOIN marks_formulas f ON f.id = m.formula_id
             WHERE m.institution_id = ? AND m.class_id = ? AND m.subject_id = ?
               AND (m.student_id = ? OR (? <> "" AND m.register_no = ?))
             ORDER BY m.updated_at DESC LIMIT 1',
            [$instId, $classId, $subjectId, $studentId, $reg, $reg]
        );
        if (!$row) {
            return null;
        }
        $max = (float)($row['formula_total_max'] ?? 25);
        $meta = json_decode((string)($row['meta'] ?? '{}'), true) ?: [];
        if (!empty($meta['total_max'])) {
            $max = (float)$meta['total_max'];
        }
        return [
            'computed_total' => $row['computed_total'] !== null ? (float)$row['computed_total'] : null,
            'grade_letter' => (string)($row['grade_letter'] ?? ''),
            'total_max' => $max,
            'marks_data' => json_decode((string)($row['marks_data'] ?? '{}'), true) ?: [],
        ];
    }

    /** @return array{attempts:int,correct:int,score_sum:float}|null */
    private static function historicalTestStats(array $user, int $classId, int $subjectId): ?array
    {
        if (!class_exists('QuestionBankTools', false)) {
            return null;
        }
        QuestionBankTools::ensureSchema();
        $instId = (int)$user['institution_id'];
        $studentId = (int)$user['id'];

        // Link attempts via question banks tied to course plans for this subject+class.
        $rows = Database::fetchAll(
            'SELECT qa.is_correct, qa.score
             FROM question_attempts qa
             JOIN questions q ON q.id = qa.question_id
             JOIN question_banks qb ON qb.id = q.bank_id
             LEFT JOIN course_plans cp ON cp.id = qb.plan_id
             WHERE qa.student_id = ? AND qa.institution_id = ?
               AND (cp.subject_id = ? OR cp.class_id = ?)',
            [$studentId, $instId, $subjectId, $classId]
        );
        if (!$rows) {
            return null;
        }
        $correct = 0;
        $scoreSum = 0.0;
        foreach ($rows as $r) {
            if ((int)($r['is_correct'] ?? 0)) {
                $correct++;
            }
            $scoreSum += (float)($r['score'] ?? 0);
        }
        return [
            'attempts' => count($rows),
            'correct' => $correct,
            'score_sum' => round($scoreSum, 1),
        ];
    }
}
