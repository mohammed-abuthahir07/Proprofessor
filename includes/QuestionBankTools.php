<?php
declare(strict_types=1);

/**
 * Question Bank enhancements: similarity, CLO tags, paper assembly, sets, item analysis.
 * Does not replace AI question generation.
 */
final class QuestionBankTools
{
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $cols = [];
        foreach (Database::fetchAll('SHOW COLUMNS FROM questions') as $c) {
            $cols[(string)$c['Field']] = true;
        }
        if (!isset($cols['clo_code'])) {
            try {
                Database::query("ALTER TABLE questions ADD COLUMN clo_code VARCHAR(20) NULL DEFAULT NULL AFTER bloom_k_level");
            } catch (Throwable $e) {
            }
        }
        if (!isset($cols['marking_scheme'])) {
            try {
                Database::query("ALTER TABLE questions ADD COLUMN marking_scheme TEXT NULL AFTER explanation");
            } catch (Throwable $e) {
            }
        }

        Database::query(
            "CREATE TABLE IF NOT EXISTS exam_papers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                institution_id INT UNSIGNED NOT NULL,
                professor_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                total_marks DECIMAL(6,1) NOT NULL DEFAULT 50,
                config LONGTEXT NULL,
                sets_data LONGTEXT NULL,
                answer_key LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_exam_prof (professor_id),
                KEY idx_exam_inst (institution_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        Database::query(
            "CREATE TABLE IF NOT EXISTS question_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                institution_id INT UNSIGNED NOT NULL,
                question_id INT UNSIGNED NOT NULL,
                student_id INT UNSIGNED NULL,
                is_correct TINYINT(1) NOT NULL DEFAULT 0,
                score DECIMAL(6,2) NULL,
                meta LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_qa_q (question_id),
                KEY idx_qa_inst (institution_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return list<array<string,mixed>> */
    public static function professorQuestions(array $user, ?int $bankId = null): array
    {
        self::ensureSchema();
        $sql = 'SELECT q.*, qb.title AS bank_title, qb.plan_id, qb.professor_id, qb.config AS bank_config
                FROM questions q
                JOIN question_banks qb ON qb.id = q.bank_id
                JOIN users u ON u.id = qb.professor_id
                WHERE qb.professor_id = ? AND u.institution_id = ?';
        $params = [(int)$user['id'], (int)$user['institution_id']];
        if ($bankId && $bankId > 0) {
            $sql .= ' AND qb.id = ?';
            $params[] = $bankId;
        }
        $sql .= ' ORDER BY q.id DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function ownedQuestion(array $user, int $questionId): ?array
    {
        self::ensureSchema();
        $row = Database::fetch(
            'SELECT q.*, qb.professor_id, qb.plan_id, qb.title AS bank_title, u.institution_id
             FROM questions q
             JOIN question_banks qb ON qb.id = q.bank_id
             JOIN users u ON u.id = qb.professor_id
             WHERE q.id = ?',
            [$questionId]
        );
        if (!$row) {
            return null;
        }
        if ((int)$row['institution_id'] !== (int)$user['institution_id']) {
            return null;
        }
        $role = (string)($user['role'] ?? '');
        if (!in_array($role, ['admin', 'superadmin'], true)
            && (int)$row['professor_id'] !== (int)$user['id']) {
            return null;
        }
        return $row;
    }

    public static function ownedBank(array $user, int $bankId): ?array
    {
        $bank = Database::fetch('SELECT * FROM question_banks WHERE id = ?', [$bankId]);
        if (!$bank) {
            return null;
        }
        if ((int)$bank['professor_id'] !== (int)$user['id']
            && !in_array((string)($user['role'] ?? ''), ['admin', 'superadmin'], true)) {
            return null;
        }
        $owner = Database::fetch('SELECT institution_id FROM users WHERE id = ?', [(int)$bank['professor_id']]);
        if (!$owner || (int)$owner['institution_id'] !== (int)$user['institution_id']) {
            return null;
        }
        return $bank;
    }

    public static function ownedPaper(array $user, int $paperId): ?array
    {
        self::ensureSchema();
        $p = Database::fetch('SELECT * FROM exam_papers WHERE id = ?', [$paperId]);
        if (!$p) {
            return null;
        }
        if ((int)$p['institution_id'] !== (int)$user['institution_id']) {
            return null;
        }
        if ((int)$p['professor_id'] !== (int)$user['id']
            && !in_array((string)($user['role'] ?? ''), ['admin', 'superadmin'], true)) {
            return null;
        }
        return $p;
    }

    /**
     * Deterministic similarity (token Jaccard + coverage + similar_text). Returns 0–100.
     */
    public static function similarityPercent(string $a, string $b): float
    {
        $na = self::normalizeStem($a);
        $nb = self::normalizeStem($b);
        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 100.0;
        }
        $ta = self::tokens($na);
        $tb = self::tokens($nb);
        if (!$ta || !$tb) {
            return 0.0;
        }
        $inter = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));
        $jaccard = $union > 0 ? ($inter / $union) * 100.0 : 0.0;
        $cover = ($inter / min(count($ta), count($tb))) * 100.0;
        similar_text($na, $nb, $pct);
        $containBoost = 0.0;
        if (str_contains($na, $nb) || str_contains($nb, $na)) {
            $containBoost = 78.0;
        }
        return round(max($jaccard, $cover, (float)$pct, $containBoost), 1);
    }

    public static function normalizeStem(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9\s]+/', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        // Light stop-word strip — keep verbs like define/explain for meaning.
        $stop = ['a', 'an', 'the', 'in', 'of', 'to', 'for', 'and', 'or', 'is', 'are', 'with', 'on', 'at', 'by', 'into'];
        $words = array_values(array_filter(explode(' ', $s), static fn($w) => $w !== '' && !in_array($w, $stop, true)));
        return implode(' ', $words);
    }

    /** @return list<string> */
    private static function tokens(string $normalized): array
    {
        $parts = preg_split('/\s+/', $normalized) ?: [];
        return array_values(array_unique(array_filter($parts)));
    }

    /**
     * Find best similar existing question for the professor.
     *
     * @param list<array<string,mixed>> $existing
     * @return array{question:array<string,mixed>,score:float,level:string}|null
     */
    public static function findSimilar(string $stem, array $existing, float $threshold = 62.0): ?array
    {
        $best = null;
        $bestScore = 0.0;
        foreach ($existing as $q) {
            $score = self::similarityPercent($stem, (string)($q['stem'] ?? ''));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $q;
            }
        }
        if (!$best || $bestScore < $threshold) {
            return null;
        }
        $level = $bestScore >= 85 ? 'High' : ($bestScore >= 72 ? 'Medium' : 'Possible');
        return ['question' => $best, 'score' => $bestScore, 'level' => $level];
    }

    /**
     * Map CLO from course-plan learning outcomes (CLO1..n). No invented CLOs.
     */
    public static function resolveClo(?array $plan, int $unit): ?string
    {
        if (!$plan) {
            return null;
        }
        $data = json_decode((string)($plan['plan_data'] ?? '{}'), true) ?: [];
        $outcomes = [];
        if (!empty($data['learning_outcomes']) && is_array($data['learning_outcomes'])) {
            foreach ($data['learning_outcomes'] as $lo) {
                $t = trim(is_string($lo) ? $lo : (string)json_encode($lo));
                if ($t !== '') {
                    $outcomes[] = $t;
                }
            }
        }
        if (!$outcomes) {
            return null;
        }
        $idx = max(0, min(count($outcomes) - 1, $unit - 1));
        return 'CLO' . ($idx + 1);
    }

    /** Concise marking scheme from marks/type — does not overwrite existing scheme. */
    public static function buildMarkingScheme(array $q): string
    {
        $marks = max(1.0, (float)($q['marks'] ?? 1));
        $type = strtolower((string)($q['question_type'] ?? 'short'));
        $stem = (string)($q['stem'] ?? 'Question');

        if ($type === 'mcq') {
            return "Correct option selected → {$marks} mark(s)\nIncorrect / blank → 0";
        }

        $parts = max(2, min(5, (int)round($marks)));
        $each = round($marks / $parts, 1);
        $labels = [
            'Key concept / definition',
            'Correct explanation / reasoning',
            'Example or application',
            'Structure / completeness',
            'Conclusion / accuracy',
        ];
        $lines = ["Question: " . mb_substr($stem, 0, 120) . " ({$marks} marks)", '', 'Marking Scheme:'];
        $allocated = 0.0;
        for ($i = 0; $i < $parts; $i++) {
            $m = ($i === $parts - 1) ? round($marks - $allocated, 1) : $each;
            $allocated += $m;
            $lines[] = ($labels[$i] ?? ('Component ' . ($i + 1))) . " → {$m} mark(s)";
        }
        return implode("\n", $lines);
    }

    /**
     * Enrich generated questions with CLO, scheme, similarity (before/during save).
     *
     * @param list<array<string,mixed>> $questions
     * @param list<array<string,mixed>> $existing
     * @return list<array<string,mixed>>
     */
    public static function enrichGeneratedQuestions(array $questions, ?array $plan, int $unit, string $klevel, array $existing): array
    {
        $clo = self::resolveClo($plan, $unit);
        $out = [];
        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }
            $q['bloom_k_level'] = strtoupper((string)($q['bloom_k_level'] ?? $klevel));
            if (!preg_match('/^K[1-6]$/', $q['bloom_k_level'])) {
                $q['bloom_k_level'] = $klevel;
            }
            $q['unit_number'] = (int)($q['unit_number'] ?? $unit);
            if ($clo && empty($q['clo_code'])) {
                $q['clo_code'] = $clo;
            }
            if (empty($q['marking_scheme'])) {
                $q['marking_scheme'] = self::buildMarkingScheme($q);
            }
            $sim = self::findSimilar((string)($q['stem'] ?? ''), $existing);
            if ($sim) {
                $q['similarity'] = [
                    'score' => $sim['score'],
                    'level' => $sim['level'],
                    'existing_id' => (int)($sim['question']['id'] ?? 0),
                    'existing_stem' => (string)($sim['question']['stem'] ?? ''),
                ];
            }
            $out[] = $q;
        }
        return $out;
    }

    /**
     * Build a paper from bank questions according to parts + bloom %.
     *
     * @param list<array{count:int,marks:float}> $parts
     * @param array<string,float> $bloomPct e.g. ['K1'=>20,...]
     * @param list<array<string,mixed>> $pool
     * @return array{ok:bool,error?:string,shortage?:array,questions?:list<array>,total_marks?:float}
     */
    public static function assemblePaper(array $pool, array $parts, array $bloomPct, int $totalMarks): array
    {
        $neededByBloom = [];
        $totalQ = 0;
        foreach ($parts as $p) {
            $totalQ += max(0, (int)$p['count']);
        }
        if ($totalQ < 1) {
            return ['ok' => false, 'error' => 'Add at least one part with question count > 0.'];
        }
        $sumPct = array_sum($bloomPct) ?: 100;
        foreach ($bloomPct as $k => $pct) {
            $neededByBloom[strtoupper($k)] = (int)round(($pct / $sumPct) * $totalQ);
        }
        // Fix rounding so sum equals totalQ
        $diff = $totalQ - array_sum($neededByBloom);
        if ($diff !== 0) {
            $first = array_key_first($neededByBloom);
            if ($first !== null) {
                $neededByBloom[$first] = max(0, $neededByBloom[$first] + $diff);
            }
        }

        // Availability check by bloom
        $avail = [];
        foreach ($pool as $q) {
            $k = strtoupper((string)($q['bloom_k_level'] ?? 'K2'));
            $avail[$k] = ($avail[$k] ?? 0) + 1;
        }
        $shortage = [];
        foreach ($neededByBloom as $k => $need) {
            $have = (int)($avail[$k] ?? 0);
            if ($need > $have) {
                $shortage[] = ['bloom' => $k, 'required' => $need, 'available' => $have];
            }
        }
        if ($shortage) {
            $msg = "Not enough suitable questions in the bank.\n";
            foreach ($shortage as $s) {
                $msg .= "{$s['bloom']} required: {$s['required']}\n{$s['bloom']} available: {$s['available']}\n";
            }
            $msg .= 'Please generate/add more questions.';
            return ['ok' => false, 'error' => trim($msg), 'shortage' => $shortage];
        }

        // Group pool by marks then bloom
        $byMarks = [];
        foreach ($pool as $q) {
            $m = (string)(float)($q['marks'] ?? 1);
            $byMarks[$m][] = $q;
        }
        foreach ($byMarks as &$list) {
            shuffle($list);
        }
        unset($list);

        $selected = [];
        $usedIds = [];
        $bloomLeft = $neededByBloom;

        foreach ($parts as $part) {
            $count = (int)$part['count'];
            $marks = (float)$part['marks'];
            $bucket = $byMarks[(string)$marks] ?? [];
            // Prefer matching bloom quotas
            $picked = 0;
            // Pass 1: match bloom needs
            foreach ($bucket as $q) {
                if ($picked >= $count) {
                    break;
                }
                $id = (int)$q['id'];
                if (isset($usedIds[$id])) {
                    continue;
                }
                $k = strtoupper((string)($q['bloom_k_level'] ?? 'K2'));
                if (($bloomLeft[$k] ?? 0) <= 0) {
                    continue;
                }
                $selected[] = $q;
                $usedIds[$id] = true;
                $bloomLeft[$k]--;
                $picked++;
            }
            // Pass 2: fill remaining for this part
            foreach ($bucket as $q) {
                if ($picked >= $count) {
                    break;
                }
                $id = (int)$q['id'];
                if (isset($usedIds[$id])) {
                    continue;
                }
                $k = strtoupper((string)($q['bloom_k_level'] ?? 'K2'));
                $selected[] = $q;
                $usedIds[$id] = true;
                if (($bloomLeft[$k] ?? 0) > 0) {
                    $bloomLeft[$k]--;
                }
                $picked++;
            }
            if ($picked < $count) {
                return [
                    'ok' => false,
                    'error' => "Not enough {$marks}-mark questions. Need {$count}, found {$picked}.",
                    'shortage' => [['marks' => $marks, 'required' => $count, 'available' => $picked]],
                ];
            }
        }

        $sum = 0.0;
        foreach ($selected as $q) {
            $sum += (float)$q['marks'];
        }
        if (abs($sum - $totalMarks) > 0.6) {
            // Soft warning but allow if parts intentionally define total
            // Prefer hard check against configured total
            return [
                'ok' => false,
                'error' => "Selected questions total {$sum} marks, but requested total is {$totalMarks}. Adjust parts to match.",
            ];
        }

        return ['ok' => true, 'questions' => $selected, 'total_marks' => $sum];
    }

    /**
     * Build equivalent Set A/B/C with controlled randomization.
     *
     * @param list<array{count:int,marks:float}> $parts
     * @param array<string,float> $bloomPct
     * @return array{ok:bool,error?:string,sets?:array<string,list<array>>,note?:string}
     */
    public static function generateEquivalentSets(array $pool, array $parts, array $bloomPct, int $totalMarks, int $setCount = 3): array
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];
        $setCount = max(2, min(5, $setCount));
        $sets = [];
        $usedAcross = [];
        $notes = [];

        for ($i = 0; $i < $setCount; $i++) {
            $label = $labels[$i];
            // Prefer unused questions first
            $preferred = [];
            $rest = [];
            foreach ($pool as $q) {
                if (isset($usedAcross[(int)$q['id']])) {
                    $rest[] = $q;
                } else {
                    $preferred[] = $q;
                }
            }
            shuffle($preferred);
            shuffle($rest);
            $tryPool = array_merge($preferred, $rest);
            $built = self::assemblePaper($tryPool, $parts, $bloomPct, $totalMarks);
            if (!$built['ok']) {
                // Fallback: allow reuse of full pool
                $built = self::assemblePaper($pool, $parts, $bloomPct, $totalMarks);
                if (!$built['ok']) {
                    return $built;
                }
                $notes[] = "Set {$label}: reused some questions because the bank is limited.";
            }
            $sets[$label] = $built['questions'];
            foreach ($built['questions'] as $q) {
                $usedAcross[(int)$q['id']] = true;
            }
        }

        // Check identical sequences
        $hashes = [];
        foreach ($sets as $label => $qs) {
            $hashes[$label] = implode(',', array_map(static fn($q) => (int)$q['id'], $qs));
        }
        if (count(array_unique($hashes)) === 1 && count($pool) <= count($sets[$labels[0]] ?? [])) {
            $notes[] = 'Bank is too small for distinct sets — all sets use the same questions (order may still differ).';
            foreach ($sets as $label => &$qs) {
                $copy = $qs;
                shuffle($copy);
                $qs = $copy;
            }
            unset($qs);
        }

        return [
            'ok' => true,
            'sets' => $sets,
            'note' => $notes ? implode(' ', $notes) : null,
        ];
    }

    /** @param list<array<string,mixed>> $questions */
    public static function buildAnswerKey(array $questions): array
    {
        $key = [];
        $n = 1;
        foreach ($questions as $q) {
            $type = strtolower((string)($q['question_type'] ?? ''));
            $ans = (string)($q['correct_answer'] ?? '');
            $key[] = [
                'q' => $n,
                'id' => (int)($q['id'] ?? 0),
                'type' => $type,
                'answer' => $ans,
                'marks' => (float)($q['marks'] ?? 0),
                'marking_scheme' => (string)($q['marking_scheme'] ?? self::buildMarkingScheme($q)),
            ];
            $n++;
        }
        return $key;
    }

    /**
     * Item analysis from real question_attempts only.
     *
     * @return array{available:bool,reason?:string,attempts?:int,correct?:int,difficulty_pct?:float,difficulty_label?:string,discrimination?:?float,discrimination_label?:?string}
     */
    public static function itemAnalysis(array $user, int $questionId): array
    {
        self::ensureSchema();
        $q = self::ownedQuestion($user, $questionId);
        if (!$q) {
            return ['available' => false, 'reason' => 'Question not found.'];
        }
        $rows = Database::fetchAll(
            'SELECT is_correct, score, student_id FROM question_attempts
             WHERE question_id = ? AND institution_id = ?',
            [$questionId, (int)$user['institution_id']]
        );
        $attempts = count($rows);
        if ($attempts < 10) {
            return [
                'available' => false,
                'reason' => 'Not enough student responses.',
                'attempts' => $attempts,
            ];
        }
        $correct = 0;
        foreach ($rows as $r) {
            if ((int)$r['is_correct'] === 1) {
                $correct++;
            }
        }
        $diffPct = round(($correct / $attempts) * 100, 1);
        $diffLabel = $diffPct >= 80 ? 'Easy' : ($diffPct >= 40 ? 'Moderate' : 'Hard');

        // Discrimination: top 27% vs bottom 27% by score (if scores present)
        $scored = array_values(array_filter($rows, static fn($r) => $r['score'] !== null));
        $disc = null;
        $discLabel = null;
        if (count($scored) >= 10) {
            usort($scored, static fn($a, $b) => (float)$b['score'] <=> (float)$a['score']);
            $n = count($scored);
            $cut = max(1, (int)floor($n * 0.27));
            $top = array_slice($scored, 0, $cut);
            $bottom = array_slice($scored, -$cut);
            $topCorrect = 0;
            $botCorrect = 0;
            foreach ($top as $r) {
                if ((int)$r['is_correct'] === 1) {
                    $topCorrect++;
                }
            }
            foreach ($bottom as $r) {
                if ((int)$r['is_correct'] === 1) {
                    $botCorrect++;
                }
            }
            $disc = round(($topCorrect / $cut) - ($botCorrect / $cut), 2);
            $discLabel = $disc >= 0.3 ? 'Good' : ($disc >= 0.15 ? 'Acceptable' : 'Weak');
        }

        return [
            'available' => true,
            'attempts' => $attempts,
            'correct' => $correct,
            'difficulty_pct' => $diffPct,
            'difficulty_label' => $diffLabel,
            'discrimination' => $disc,
            'discrimination_label' => $discLabel,
        ];
    }

    public static function defaultBloomPct(): array
    {
        return ['K1' => 20, 'K2' => 20, 'K3' => 30, 'K4' => 20, 'K5' => 10, 'K6' => 0];
    }
}
