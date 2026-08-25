<?php
declare(strict_types=1);

/**
 * Assignment module extensions: rubric, AI grade, similarity, templates,
 * bulk create, reminders, extensions, Internal Marks hand-off.
 * Does not replace existing assignment create/submit/grade flows.
 */
final class AssignmentTools
{
    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS assignment_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                institution_id INT UNSIGNED NOT NULL,
                professor_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                assignment_type VARCHAR(40) NOT NULL DEFAULT 'essay',
                description LONGTEXT NULL,
                rubric LONGTEXT NULL,
                max_marks DECIMAL(6,2) NOT NULL DEFAULT 25,
                instructions LONGTEXT NULL,
                context_text LONGTEXT NULL,
                meta LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_asg_tpl_prof (professor_id),
                INDEX idx_asg_tpl_inst (institution_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS assignment_extension_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                assignment_id INT UNSIGNED NOT NULL,
                student_id INT UNSIGNED NOT NULL,
                reason TEXT NOT NULL,
                requested_deadline DATETIME NOT NULL,
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                professor_note TEXT NULL,
                decided_by INT UNSIGNED NULL,
                decided_at DATETIME NULL,
                approved_deadline DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_asg_ext_asg (assignment_id),
                INDEX idx_asg_ext_stu (student_id),
                INDEX idx_asg_ext_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaReady = true;
    }

    public static function ownedAssignment(array $user, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $row = Database::fetch('SELECT * FROM assignments WHERE id = ?', [$id]);
        if (!$row) {
            return null;
        }
        if ((int)$row['institution_id'] !== (int)($user['institution_id'] ?? 0)) {
            return null;
        }
        $role = (string)($user['role'] ?? '');
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return $row;
        }
        if ($role === 'professor' && (int)$row['professor_id'] === (int)$user['id']) {
            return $row;
        }
        return null;
    }

    public static function ownedSubmission(array $user, int $submissionId): ?array
    {
        if ($submissionId < 1) {
            return null;
        }
        $row = Database::fetch(
            'SELECT s.*, a.professor_id, a.institution_id AS asg_institution_id, a.class_id AS asg_class_id,
                    a.subject_id AS asg_subject_id, a.max_marks, a.title AS assignment_title, a.rubric AS assignment_rubric
             FROM assignment_submissions s
             JOIN assignments a ON a.id = s.assignment_id
             WHERE s.id = ?',
            [$submissionId]
        );
        if (!$row) {
            return null;
        }
        if ((int)$row['asg_institution_id'] !== (int)($user['institution_id'] ?? 0)) {
            return null;
        }
        $role = (string)($user['role'] ?? '');
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return $row;
        }
        if ($role === 'professor' && (int)$row['professor_id'] === (int)$user['id']) {
            return $row;
        }
        return null;
    }

    /**
     * Normalize rubric rows. Accepts legacy weight% or absolute marks.
     *
     * @param mixed $raw
     * @return list<array{criterion:string,description:string,marks:float,clo:?string,bloom:?string,levels:?string,weight:?float}>
     */
    public static function normalizeRubric(mixed $raw, float $maxMarks): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $rows = [];
        $weightSum = 0.0;
        $hasAbsolute = false;
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $criterion = trim((string)($item['criterion'] ?? $item['name'] ?? ''));
            if ($criterion === '') {
                continue;
            }
            $marks = isset($item['marks']) ? (float)$item['marks'] : null;
            $weight = isset($item['weight']) ? (float)$item['weight'] : null;
            if ($marks !== null && $marks > 0) {
                $hasAbsolute = true;
            }
            if ($weight !== null) {
                $weightSum += $weight;
            }
            $clo = trim((string)($item['clo'] ?? $item['clo_code'] ?? ''));
            $bloom = strtoupper(trim((string)($item['bloom'] ?? $item['bloom_k_level'] ?? '')));
            if ($bloom !== '' && !preg_match('/^K[1-6]$/', $bloom)) {
                if (preg_match('/([1-6])/', $bloom, $m)) {
                    $bloom = 'K' . $m[1];
                } else {
                    $bloom = null;
                }
            }
            $rows[] = [
                'criterion' => $criterion,
                'description' => trim((string)($item['description'] ?? $item['levels'] ?? '')),
                'marks' => $marks ?? 0.0,
                'clo' => $clo !== '' ? $clo : null,
                'bloom' => $bloom !== '' ? $bloom : null,
                'levels' => isset($item['levels']) ? (string)$item['levels'] : null,
                'weight' => $weight,
            ];
        }
        if ($rows === []) {
            return [];
        }
        // Convert weight% → marks when absolute marks missing.
        if (!$hasAbsolute && $weightSum > 0 && $maxMarks > 0) {
            $allocated = 0.0;
            $last = count($rows) - 1;
            foreach ($rows as $i => &$r) {
                $w = (float)($r['weight'] ?? 0);
                if ($i === $last) {
                    $r['marks'] = round(max(0, $maxMarks - $allocated), 2);
                } else {
                    $r['marks'] = round(($w / $weightSum) * $maxMarks, 2);
                    $allocated += $r['marks'];
                }
            }
            unset($r);
        }
        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rubric
     */
    public static function rubricMarksTotal(array $rubric): float
    {
        $sum = 0.0;
        foreach ($rubric as $r) {
            $sum += (float)($r['marks'] ?? 0);
        }
        return round($sum, 2);
    }

    /**
     * @param list<array<string,mixed>> $rubric
     * @return array{ok:bool,error?:string,rubric:list<array<string,mixed>>}
     */
    public static function validateRubricTotal(array $rubric, float $maxMarks): array
    {
        $total = self::rubricMarksTotal($rubric);
        if (abs($total - $maxMarks) > 0.05) {
            return [
                'ok' => false,
                'error' => "Rubric total ({$total}) must equal assignment marks ({$maxMarks}).",
                'rubric' => $rubric,
            ];
        }
        return ['ok' => true, 'rubric' => $rubric];
    }

    /**
     * Enrich AI rubric with CLO/Bloom from plan when available.
     *
     * @param list<array<string,mixed>> $rubric
     * @return list<array<string,mixed>>
     */
    public static function enrichRubricFromPlan(array $rubric, ?array $plan, float $maxMarks): array
    {
        $rubric = self::normalizeRubric($rubric, $maxMarks);
        $clos = [];
        if ($plan && class_exists('QuestionBankTools')) {
            for ($u = 1; $u <= 5; $u++) {
                $clo = QuestionBankTools::resolveClo($plan, $u);
                if ($clo) {
                    $clos[] = $clo;
                }
            }
        }
        if ($clos === []) {
            $clos = ['CLO1', 'CLO2', 'CLO3'];
        }
        $blooms = ['K2', 'K3', 'K4', 'K2', 'K3'];
        foreach ($rubric as $i => &$r) {
            if (empty($r['clo'])) {
                $r['clo'] = $clos[$i % count($clos)];
            }
            if (empty($r['bloom'])) {
                $r['bloom'] = $blooms[$i % count($blooms)];
            }
            if (empty($r['description']) && !empty($r['levels'])) {
                $r['description'] = (string)$r['levels'];
            }
        }
        unset($r);
        return $rubric;
    }

    /**
     * @return array{ok:bool,error?:string,ai_score?:float,ai_feedback?:string,criterion_scores?:list<array<string,mixed>>}
     */
    public static function aiGradeSubmission(array $user, array $assignment, array $submission): array
    {
        $owned = self::ownedAssignment($user, (int)$assignment['id']);
        if (!$owned) {
            return ['ok' => false, 'error' => 'Access denied.'];
        }
        $max = (float)($assignment['max_marks'] ?? 25);
        $rubric = self::normalizeRubric($assignment['rubric'] ?? [], $max);
        $text = trim((string)($submission['content_text'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'error' => 'Submission has no text content to grade.'];
        }

        $aiScore = null;
        $aiFeedback = null;
        $criterionScores = [];
        $gemini = class_exists('Gemini') ? new Gemini() : null;

        if ($gemini && $gemini->isConfigured()) {
            try {
                $system = 'You are an academic grader assistant. Return ONLY valid JSON. Do not finalize grades — recommend only.';
                $prompt = "Assignment: {$assignment['title']}\nMax marks: {$max}\n"
                    . "Rubric: " . json_encode($rubric, JSON_UNESCAPED_UNICODE) . "\n"
                    . "Student submission:\n" . mb_substr($text, 0, 6000) . "\n\n"
                    . "Return {\"ai_score\":number,\"ai_feedback\":\"\",\"criterion_scores\":[{\"criterion\":\"\",\"score\":number,\"comment\":\"\"}]}";
                $result = $gemini->generate($system, $prompt);
                $json = is_array($result['json'] ?? null) ? $result['json'] : null;
                if (is_array($json)) {
                    $aiScore = isset($json['ai_score']) ? (float)$json['ai_score'] : null;
                    $aiFeedback = trim((string)($json['ai_feedback'] ?? $json['feedback'] ?? ''));
                    if (is_array($json['criterion_scores'] ?? null)) {
                        $criterionScores = $json['criterion_scores'];
                    }
                }
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => 'AI grading failed. Manual grade unchanged.'];
            }
        }

        if ($aiScore === null) {
            // Deterministic heuristic when Gemini is not configured (demo-safe, clearly labeled).
            $len = mb_strlen($text);
            $base = min($max, max(0, round($max * min(1, $len / 800), 1)));
            $aiScore = $base;
            $aiFeedback = 'Heuristic first-pass (no Gemini key configured). Review carefully before finalizing. '
                . 'Length-based estimate only — not a real academic grade.';
            foreach ($rubric as $r) {
                $criterionScores[] = [
                    'criterion' => $r['criterion'],
                    'score' => round(((float)$r['marks'] / max(1, $max)) * $aiScore, 1),
                    'comment' => 'Heuristic share of estimated total.',
                ];
            }
        }

        $aiScore = max(0, min($max, round((float)$aiScore, 2)));
        $meta = json_decode((string)($submission['meta'] ?? '{}'), true) ?: [];
        $meta['ai_grade'] = [
            'score' => $aiScore,
            'feedback' => $aiFeedback,
            'criterion_scores' => $criterionScores,
            'at' => date('c'),
            'by' => (int)$user['id'],
            'provisional' => true,
        ];
        // Do NOT set grade/status — professor must finalize.
        Database::update('assignment_submissions', [
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => (int)$submission['id']]);

        return [
            'ok' => true,
            'ai_score' => $aiScore,
            'ai_feedback' => (string)$aiFeedback,
            'criterion_scores' => $criterionScores,
        ];
    }

    /**
     * Finalize professor grade (source of truth). Keeps AI recommendation in meta.
     */
    public static function finalizeGrade(array $user, int $submissionId, float $grade, string $feedback): array
    {
        $sub = self::ownedSubmission($user, $submissionId);
        if (!$sub) {
            return ['ok' => false, 'error' => 'Submission not found.'];
        }
        $max = (float)($sub['max_marks'] ?? 25);
        if ($grade < 0 || $grade > $max + 0.01) {
            return ['ok' => false, 'error' => "Grade must be between 0 and {$max}."];
        }
        $meta = json_decode((string)($sub['meta'] ?? '{}'), true) ?: [];
        $ai = is_array($meta['ai_grade'] ?? null) ? $meta['ai_grade'] : null;
        $override = $ai && isset($ai['score']) && abs((float)$ai['score'] - $grade) > 0.01;
        $meta['final_grade'] = [
            'score' => $grade,
            'feedback' => $feedback,
            'at' => date('c'),
            'by' => (int)$user['id'],
            'override' => $override,
            'ai_score' => $ai['score'] ?? null,
        ];
        $meta['finalized'] = true;
        // Clear sync flag so Internal Marks can be re-pushed after change.
        unset($meta['marks_synced_at']);

        Database::update('assignment_submissions', [
            'grade' => $grade,
            'feedback' => $feedback,
            'graded_by' => (int)$user['id'],
            'graded_at' => date('Y-m-d H:i:s'),
            'status' => 'graded',
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $submissionId]);

        return ['ok' => true];
    }

    /**
     * @return list<array{other_student_id:int,other_name:string,percent:float}>
     */
    public static function similarityReport(array $assignment, array $submissions): array
    {
        $out = [];
        $texts = [];
        foreach ($submissions as $s) {
            $t = trim((string)($s['content_text'] ?? ''));
            if ($t === '') {
                continue;
            }
            $texts[] = $s;
        }
        $n = count($texts);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = (string)$texts[$i]['content_text'];
                $b = (string)$texts[$j]['content_text'];
                $pct = QuestionBankTools::similarityPercent($a, $b);
                if ($pct < 35) {
                    continue;
                }
                $out[] = [
                    'student_a_id' => (int)$texts[$i]['student_id'],
                    'student_a_name' => (string)($texts[$i]['full_name'] ?? ('#' . $texts[$i]['student_id'])),
                    'student_b_id' => (int)$texts[$j]['student_id'],
                    'student_b_name' => (string)($texts[$j]['full_name'] ?? ('#' . $texts[$j]['student_id'])),
                    'percent' => $pct,
                ];
                // Store on both metas (best effort).
                self::storeSimilarityMeta((int)$texts[$i]['id'], (int)$texts[$j]['student_id'], $pct);
                self::storeSimilarityMeta((int)$texts[$j]['id'], (int)$texts[$i]['student_id'], $pct);
            }
        }
        usort($out, static fn($x, $y) => $y['percent'] <=> $x['percent']);
        return $out;
    }

    private static function storeSimilarityMeta(int $submissionId, int $otherStudentId, float $pct): void
    {
        $row = Database::fetch('SELECT meta FROM assignment_submissions WHERE id = ?', [$submissionId]);
        if (!$row) {
            return;
        }
        $meta = json_decode((string)($row['meta'] ?? '{}'), true) ?: [];
        $list = is_array($meta['similarity'] ?? null) ? $meta['similarity'] : [];
        $list[(string)$otherStudentId] = ['percent' => $pct, 'at' => date('c')];
        $meta['similarity'] = $list;
        Database::update('assignment_submissions', [
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $submissionId]);
    }

    public static function aiContentDetectionConfigured(): bool
    {
        return (bool)config('ai_content_detection.enabled', false)
            && trim((string)config('ai_content_detection.provider', '')) !== '';
    }

    /**
     * @return array<string,mixed>
     */
    public static function analytics(array $assignment, array $submissions, int $rosterCount): array
    {
        $submitted = 0;
        $graded = 0;
        $scores = [];
        foreach ($submissions as $s) {
            if (in_array((string)($s['status'] ?? ''), ['submitted', 'late', 'graded', 'returned'], true)
                || trim((string)($s['content_text'] ?? '')) !== ''
                || !empty($s['file_url'])
            ) {
                $submitted++;
            }
            if ($s['grade'] !== null && $s['grade'] !== '') {
                $graded++;
                $scores[] = (float)$s['grade'];
            }
        }
        $max = (float)($assignment['max_marks'] ?? 25);
        $avg = $scores ? array_sum($scores) / count($scores) : null;
        $rubric = self::normalizeRubric($assignment['rubric'] ?? [], $max);
        $criterionPerf = [];
        foreach ($rubric as $r) {
            $crit = (string)$r['criterion'];
            $critMax = (float)$r['marks'];
            $sum = 0.0;
            $n = 0;
            foreach ($submissions as $s) {
                $meta = json_decode((string)($s['meta'] ?? '{}'), true) ?: [];
                $cs = $meta['ai_grade']['criterion_scores'] ?? ($meta['final_grade']['criterion_scores'] ?? null);
                if (!is_array($cs)) {
                    continue;
                }
                foreach ($cs as $c) {
                    if (!is_array($c)) {
                        continue;
                    }
                    if (strcasecmp((string)($c['criterion'] ?? ''), $crit) === 0 && isset($c['score'])) {
                        $sum += (float)$c['score'];
                        $n++;
                    }
                }
            }
            $pct = ($n > 0 && $critMax > 0) ? round(($sum / $n) / $critMax * 100, 1) : null;
            $criterionPerf[] = [
                'criterion' => $crit,
                'avg_percent' => $pct,
                'weak' => $pct !== null && $pct < 60,
            ];
        }
        return [
            'roster' => $rosterCount,
            'submitted' => $submitted,
            'not_submitted' => max(0, $rosterCount - $submitted),
            'graded' => $graded,
            'average' => $avg !== null ? round($avg, 2) : null,
            'highest' => $scores ? max($scores) : null,
            'lowest' => $scores ? min($scores) : null,
            'avg_percent' => ($avg !== null && $max > 0) ? round($avg / $max * 100, 1) : null,
            'criterion_performance' => $criterionPerf,
        ];
    }

    public static function saveTemplate(array $user, array $assignment, string $context = ''): int
    {
        self::ensureSchema();
        return (int)Database::insert('assignment_templates', [
            'institution_id' => (int)$user['institution_id'],
            'professor_id' => (int)$user['id'],
            'title' => (string)$assignment['title'],
            'assignment_type' => (string)$assignment['assignment_type'],
            'description' => (string)($assignment['description'] ?? ''),
            'rubric' => is_string($assignment['rubric'] ?? null)
                ? (string)$assignment['rubric']
                : json_encode($assignment['rubric'] ?? [], JSON_UNESCAPED_UNICODE),
            'max_marks' => (float)($assignment['max_marks'] ?? 25),
            'instructions' => is_string($assignment['instructions'] ?? null)
                ? (string)$assignment['instructions']
                : json_encode($assignment['instructions'] ?? [], JSON_UNESCAPED_UNICODE),
            'context_text' => $context,
            'meta' => json_encode(['source_assignment_id' => (int)$assignment['id']], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function templatesForProfessor(array $user): array
    {
        self::ensureSchema();
        return Database::fetchAll(
            'SELECT * FROM assignment_templates
             WHERE professor_id = ? AND institution_id = ?
             ORDER BY id DESC',
            [(int)$user['id'], (int)$user['institution_id']]
        );
    }

    public static function getTemplate(array $user, int $id): ?array
    {
        self::ensureSchema();
        $row = Database::fetch(
            'SELECT * FROM assignment_templates WHERE id = ? AND professor_id = ? AND institution_id = ?',
            [$id, (int)$user['id'], (int)$user['institution_id']]
        );
        return $row ?: null;
    }

    /**
     * Create assignment from template for one class (reuses insert shape of AI create).
     */
    public static function createFromTemplate(array $user, array $template, int $classId, int $subjectId, ?string $deadline): int
    {
        if (!professor_can_manage_class($user, $classId)) {
            throw new RuntimeException('Class not assigned to you.');
        }
        if ($subjectId > 0 && !professor_can_manage_subject($user, $subjectId, $classId)) {
            throw new RuntimeException('Subject not assigned for this class.');
        }
        $id = (int)Database::insert('assignments', [
            'institution_id' => (int)$user['institution_id'],
            'professor_id' => (int)$user['id'],
            'subject_id' => $subjectId ?: null,
            'class_id' => $classId,
            'title' => (string)$template['title'],
            'assignment_type' => (string)$template['assignment_type'],
            'description' => (string)($template['description'] ?? ''),
            'rubric' => (string)($template['rubric'] ?? '[]'),
            'max_marks' => (float)($template['max_marks'] ?? 25),
            'deadline' => $deadline,
            'instructions' => (string)($template['instructions'] ?? '[]'),
            'ai_generated' => 0,
            'status' => 'published',
            'meta' => json_encode(['from_template_id' => (int)$template['id']], JSON_UNESCAPED_UNICODE),
        ]);
        if ($subjectId > 0) {
            enroll_class_students_in_subject((int)$user['institution_id'], $classId, $subjectId);
        }
        return $id;
    }

    public static function studentEffectiveDeadline(array $assignment, array $user): ?string
    {
        self::ensureSchema();
        $approved = Database::fetch(
            'SELECT approved_deadline FROM assignment_extension_requests
             WHERE assignment_id = ? AND student_id = ? AND status = "approved"
             ORDER BY id DESC LIMIT 1',
            [(int)$assignment['id'], (int)$user['id']]
        );
        if ($approved && !empty($approved['approved_deadline'])) {
            return (string)$approved['approved_deadline'];
        }
        $d = $assignment['deadline'] ?? null;
        return $d !== null && $d !== '' ? (string)$d : null;
    }

    public static function requestExtension(array $user, int $assignmentId, string $reason, string $requestedDeadline): array
    {
        self::ensureSchema();
        if (!student_can_submit_assignment($assignmentId, $user)) {
            return ['ok' => false, 'error' => 'Assignment not available for your class.'];
        }
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 1000) {
            return ['ok' => false, 'error' => 'Provide a short reason (required).'];
        }
        $ts = strtotime($requestedDeadline);
        if ($ts === false) {
            return ['ok' => false, 'error' => 'Invalid requested deadline.'];
        }
        $pending = Database::fetch(
            'SELECT id FROM assignment_extension_requests
             WHERE assignment_id = ? AND student_id = ? AND status = "pending"',
            [$assignmentId, (int)$user['id']]
        );
        if ($pending) {
            return ['ok' => false, 'error' => 'You already have a pending extension request.'];
        }
        $asg = Database::fetch('SELECT professor_id, title FROM assignments WHERE id = ?', [$assignmentId]);
        $id = (int)Database::insert('assignment_extension_requests', [
            'assignment_id' => $assignmentId,
            'student_id' => (int)$user['id'],
            'reason' => $reason,
            'requested_deadline' => date('Y-m-d H:i:s', $ts),
            'status' => 'pending',
        ]);
        if ($asg) {
            notify_user(
                (int)$asg['professor_id'],
                'assignment_extension',
                'Extension request: ' . (string)$asg['title'],
                (string)($user['full_name'] ?? 'Student') . ' requested an extension.',
                base_url('/professor/assignments.php?id=' . $assignmentId)
            );
        }
        return ['ok' => true, 'id' => $id];
    }

    public static function decideExtension(array $user, int $requestId, string $decision, string $note = ''): array
    {
        self::ensureSchema();
        $req = Database::fetch('SELECT * FROM assignment_extension_requests WHERE id = ?', [$requestId]);
        if (!$req || (string)$req['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Request not found or already decided.'];
        }
        $asg = self::ownedAssignment($user, (int)$req['assignment_id']);
        if (!$asg) {
            return ['ok' => false, 'error' => 'Access denied.'];
        }
        $decision = strtolower($decision);
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Invalid decision.'];
        }
        $approvedDeadline = null;
        if ($decision === 'approved') {
            $approvedDeadline = (string)$req['requested_deadline'];
        }
        Database::update('assignment_extension_requests', [
            'status' => $decision,
            'professor_note' => $note !== '' ? $note : null,
            'decided_by' => (int)$user['id'],
            'decided_at' => date('Y-m-d H:i:s'),
            'approved_deadline' => $approvedDeadline,
        ], 'id = :id', ['id' => $requestId]);

        notify_user(
            (int)$req['student_id'],
            'assignment_extension',
            'Extension ' . $decision . ': ' . (string)$asg['title'],
            $decision === 'approved'
                ? ('Your new deadline is ' . $approvedDeadline)
                : ('Your extension request was rejected.' . ($note !== '' ? ' ' . $note : '')),
            base_url('/student/assignments.php')
        );
        return ['ok' => true];
    }

    /** @return list<array<string,mixed>> */
    public static function pendingExtensions(int $assignmentId): array
    {
        self::ensureSchema();
        return Database::fetchAll(
            'SELECT r.*, u.full_name, u.register_no
             FROM assignment_extension_requests r
             JOIN users u ON u.id = r.student_id
             WHERE r.assignment_id = ?
             ORDER BY FIELD(r.status,"pending","approved","rejected"), r.id DESC',
            [$assignmentId]
        );
    }

    /**
     * Push finalized grades into Internal Marks assignment component (upsert, no duplicates).
     *
     * @return array{ok:bool,error?:string,updated?:int}
     */
    public static function pushToInternalMarks(array $user, array $assignment): array
    {
        if (!class_exists('App\\Models\\MarksFormula', false)) {
            require_once dirname(__DIR__) . '/app/Models/MarksFormula.php';
        }
        $asg = self::ownedAssignment($user, (int)$assignment['id']);
        if (!$asg) {
            return ['ok' => false, 'error' => 'Access denied.'];
        }
        $classId = (int)($asg['class_id'] ?? 0);
        $subjectId = (int)($asg['subject_id'] ?? 0);
        if ($classId < 1 || $subjectId < 1) {
            return ['ok' => false, 'error' => 'Assignment needs both class and subject to push marks.'];
        }
        if (!professor_can_manage_subject($user, $subjectId, $classId)) {
            return ['ok' => false, 'error' => 'Not authorized for this subject/class.'];
        }

        \App\Models\MarksFormula::ensureInternalMarksSchema();
        $instId = (int)$user['institution_id'];
        $academicYear = institution_academic_year($instId);
        $subject = Database::fetch(
            'SELECT id, department_id, name, code, meta FROM subjects WHERE id = ? AND institution_id = ?',
            [$subjectId, $instId]
        );
        if (!$subject) {
            return ['ok' => false, 'error' => 'Subject not found.'];
        }
        $deptId = (int)($subject['department_id'] ?? 0) ?: null;
        $type = \App\Models\MarksFormula::subjectTypeFromMeta($subject['meta'] ?? null);
        $formula = \App\Models\MarksFormula::resolveForContext($instId, $deptId, $subjectId, $type)
            ?: \App\Models\MarksFormula::systemFallback();
        $components = \App\Models\MarksFormula::normalizeComponents($formula['components'] ?? []);
        $asnCode = null;
        $asnMax = 5.0;
        foreach ($components as $c) {
            if (\App\Models\MarksFormula::isAssignmentComponent($c['code'], $c['label'])) {
                $asnCode = $c['code'];
                $asnMax = (float)$c['max'];
                break;
            }
        }
        if ($asnCode === null) {
            return ['ok' => false, 'error' => 'Configured formula has no assignment component. Ask College Admin to add one.'];
        }

        $asgMax = max(0.01, (float)($asg['max_marks'] ?? 25));
        $subs = Database::fetchAll(
            'SELECT s.*, u.register_no, u.full_name
             FROM assignment_submissions s
             JOIN users u ON u.id = s.student_id
             WHERE s.assignment_id = ? AND s.grade IS NOT NULL AND s.status = "graded"',
            [(int)$asg['id']]
        );
        if (!$subs) {
            return ['ok' => false, 'error' => 'No finalized (professor-graded) submissions to push.'];
        }

        $expression = (string)($formula['expression'] ?? '');
        $totalMax = (float)($formula['total_max'] ?? 25);
        $formulaId = (int)($formula['id'] ?? 0) ?: null;
        $updated = 0;

        foreach ($subs as $s) {
            $meta = json_decode((string)($s['meta'] ?? '{}'), true) ?: [];
            if (empty($meta['finalized']) && (string)($s['status'] ?? '') !== 'graded') {
                continue;
            }
            // Only professor-finalized grades — skip pure AI provisional without grade field.
            if ($s['grade'] === null || $s['grade'] === '') {
                continue;
            }
            $reg = trim((string)($s['register_no'] ?? ''));
            if ($reg === '') {
                continue;
            }
            $scaled = round(((float)$s['grade'] / $asgMax) * $asnMax, 2);
            $scaled = max(0, min($asnMax, $scaled));

            $existing = Database::fetch(
                'SELECT * FROM internal_marks
                 WHERE subject_id = ? AND class_id = ? AND register_no = ? AND academic_year = ?',
                [$subjectId, $classId, $reg, $academicYear]
            );
            $data = [];
            if ($existing) {
                $data = json_decode((string)($existing['marks_data'] ?? '{}'), true) ?: [];
            }
            // Preserve other components; only update assignment. Missing components default to 0 for formula eval.
            $data[$asnCode] = $scaled;
            foreach ($components as $c) {
                $code = $c['code'];
                $found = false;
                foreach ($data as $vk => $vv) {
                    if (strcasecmp((string)$vk, $code) === 0 && $vv !== null && $vv !== '') {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $data[$code] = 0;
                }
            }

            try {
                $norm = \App\Models\MarksFormula::validateAndNormalizeValues($components, $data);
                $total = \App\Models\MarksFormula::computeTotal($expression, $norm, $components);
                $letter = \App\Models\MarksFormula::gradeLetter($total, $totalMax);
            } catch (Throwable $e) {
                continue;
            }

            $attCode = null;
            foreach ($components as $c) {
                if (\App\Models\MarksFormula::isAttendanceComponent($c['code'], $c['label'])) {
                    $attCode = $c['code'];
                    break;
                }
            }
            $attPct = null;
            if ($existing && isset($existing['attendance_pct'])) {
                $attPct = $existing['attendance_pct'];
            }

            $rowMeta = [
                'academic_year' => $academicYear,
                'formula_name' => $formula['name'] ?? null,
                'formula_expression' => $expression,
                'total_max' => $totalMax,
                'components' => $components,
                'from_assignment_id' => (int)$asg['id'],
                'assignment_raw_grade' => (float)$s['grade'],
                'assignment_scaled' => $scaled,
            ];

            Database::query(
                'INSERT INTO internal_marks
                  (institution_id, professor_id, subject_id, class_id, academic_year, formula_id, student_id, register_no, student_name,
                   marks_data, computed_total, grade_letter, attendance_pct, assignment_total, meta)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   marks_data=VALUES(marks_data),
                   computed_total=VALUES(computed_total),
                   grade_letter=VALUES(grade_letter),
                   formula_id=VALUES(formula_id),
                   student_name=VALUES(student_name),
                   student_id=VALUES(student_id),
                   professor_id=VALUES(professor_id),
                   assignment_total=VALUES(assignment_total),
                   meta=VALUES(meta),
                   academic_year=VALUES(academic_year)',
                [
                    $instId,
                    (int)$user['id'],
                    $subjectId,
                    $classId,
                    $academicYear,
                    $formulaId,
                    (int)$s['student_id'],
                    $reg,
                    (string)($s['full_name'] ?? $reg),
                    json_encode($norm, JSON_UNESCAPED_UNICODE),
                    $total,
                    $letter,
                    $attPct,
                    $scaled,
                    json_encode($rowMeta, JSON_UNESCAPED_UNICODE),
                ]
            );

            $meta['marks_synced_at'] = date('c');
            $meta['marks_synced_value'] = $scaled;
            Database::update('assignment_submissions', [
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ], 'id = :id', ['id' => (int)$s['id']]);
            $updated++;
        }

        $asgMeta = json_decode((string)($asg['meta'] ?? '{}'), true) ?: [];
        $asgMeta['marks_pushed_at'] = date('c');
        $asgMeta['marks_pushed_count'] = $updated;
        Database::update('assignments', [
            'meta' => json_encode($asgMeta, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => (int)$asg['id']]);

        return ['ok' => true, 'updated' => $updated];
    }

    /**
     * Deadline reminders via existing notifications table (idempotent per bucket).
     */
    public static function dispatchDeadlineReminders(array $user): void
    {
        if (($user['role'] ?? '') !== 'student') {
            return;
        }
        $list = assignments_visible_to_student($user);
        foreach ($list as $a) {
            $deadline = self::studentEffectiveDeadline($a, $user);
            if (!$deadline) {
                continue;
            }
            $ts = strtotime($deadline);
            if ($ts === false) {
                continue;
            }
            $days = (int)floor(($ts - time()) / 86400);
            $bucket = null;
            if ($days === 7) {
                $bucket = '7d';
            } elseif ($days === 3) {
                $bucket = '3d';
            } elseif ($days === 1) {
                $bucket = '1d';
            } elseif ($days === 0) {
                $bucket = 'today';
            }
            if ($bucket === null) {
                continue;
            }
            $marker = 'asg-deadline-' . (int)$a['id'] . '-' . $bucket;
            $exists = Database::fetch(
                'SELECT id FROM notifications WHERE user_id = ? AND type = ? AND JSON_UNQUOTE(JSON_EXTRACT(meta, "$.marker")) = ? LIMIT 1',
                [(int)$user['id'], 'assignment_deadline', $marker]
            );
            if (!$exists) {
                // Fallback if meta JSON functions unavailable / null meta rows.
                $exists = Database::fetch(
                    'SELECT id FROM notifications WHERE user_id = ? AND type = ? AND body LIKE ? LIMIT 1',
                    [(int)$user['id'], 'assignment_deadline', '%[' . $marker . ']%']
                );
            }
            if ($exists) {
                continue;
            }
            $labels = [
                '7d' => '7 days remaining',
                '3d' => '3 days remaining',
                '1d' => '1 day remaining',
                'today' => 'Deadline today',
            ];
            Database::insert('notifications', [
                'user_id' => (int)$user['id'],
                'type' => 'assignment_deadline',
                'title' => (string)$a['title'] . ' — ' . $labels[$bucket],
                'body' => $labels[$bucket] . ' [' . $marker . ']',
                'action_url' => base_url('/student/assignments.php'),
                'is_read' => 0,
                'meta' => json_encode(['marker' => $marker, 'assignment_id' => (int)$a['id'], 'bucket' => $bucket], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * Clos from subject course plans for professor context.
     *
     * @return list<string>
     */
    public static function closForSubject(array $user, int $subjectId): array
    {
        if ($subjectId < 1) {
            return [];
        }
        $plan = Database::fetch(
            'SELECT * FROM course_plans
             WHERE professor_id = ? AND institution_id = ? AND subject_id = ?
             ORDER BY id DESC LIMIT 1',
            [(int)$user['id'], (int)$user['institution_id'], $subjectId]
        );
        if (!$plan) {
            return [];
        }
        $out = [];
        for ($u = 1; $u <= 6; $u++) {
            $clo = QuestionBankTools::resolveClo($plan, $u);
            if ($clo && !in_array($clo, $out, true)) {
                $out[] = $clo;
            }
        }
        return $out;
    }
}
