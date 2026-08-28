<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_post();

$module = (string)(get('module') ?: post('module'));
$user = Auth::user();
$gemini = new Gemini();

function prompt_template(string $code): ?array
{
    return Database::fetch('SELECT * FROM ai_prompt_templates WHERE code = ? AND is_active = 1', [$code]);
}

function load_plan_for_user(int $planId, array $user): ?array
{
    $plan = Database::fetch('SELECT * FROM course_plans WHERE id = ?', [$planId]);
    if (!$plan) {
        return null;
    }
    if ((int)$plan['institution_id'] !== (int)($user['institution_id'] ?? 0)) {
        return null;
    }
    $role = (string)($user['role'] ?? '');
    if ($role === 'professor' && (int)$plan['professor_id'] !== (int)$user['id']) {
        return null;
    }
    if ($role === 'hod' && (int)$plan['department_id'] !== (int)($user['department_id'] ?? 0)) {
        return null;
    }
    return $plan;
}

/**
 * Resolve a student's selected subject against their CURRENT academic context (My Courses scope).
 *
 * @return array<string,mixed>|null
 */
function resolve_student_ask_ai_subject(array $user, int $subjectId): ?array
{
    if ($subjectId < 1) {
        return null;
    }
    foreach (courses_for_student($user) as $subject) {
        if ((int)($subject['id'] ?? 0) === $subjectId) {
            return $subject;
        }
    }
    return null;
}

/** Build syllabus / course-plan context for student Ask AI. */
function build_student_ask_ai_materials(array $user, array $subjectRow): string
{
    $instId = (int)($user['institution_id'] ?? 0);
    $subjectId = (int)($subjectRow['id'] ?? 0);
    $classId = student_class_id($user);
    $parts = [];
    $name = trim((string)($subjectRow['name'] ?? ''));
    $code = trim((string)($subjectRow['code'] ?? ''));
    if ($name !== '') {
        $parts[] = 'Course: ' . $name . ($code !== '' ? " ($code)" : '');
    }

    $plan = null;
    if ($classId > 0) {
        $plan = Database::fetch(
            'SELECT * FROM course_plans
             WHERE subject_id = ? AND class_id = ? AND status = "approved" AND institution_id = ?
             ORDER BY id DESC LIMIT 1',
            [$subjectId, $classId, $instId]
        );
    }
    if (!$plan) {
        $plan = Database::fetch(
            'SELECT * FROM course_plans
             WHERE subject_id = ? AND status = "approved" AND institution_id = ?
             ORDER BY id DESC LIMIT 1',
            [$subjectId, $instId]
        );
    }
    if ($plan) {
        $syllabus = trim((string)($plan['syllabus_input'] ?? ''));
        if ($syllabus !== '') {
            $parts[] = "## Syllabus\n" . $syllabus;
        }
        $planData = json_decode((string)($plan['plan_data'] ?? ''), true) ?: [];
        $outcomes = $planData['learning_outcomes'] ?? [];
        if (is_array($outcomes) && $outcomes) {
            $lines = [];
            foreach ($outcomes as $o) {
                $lines[] = '- ' . trim((string)$o);
            }
            $parts[] = "## Course Outcomes\n" . implode("\n", $lines);
        }
        $units = $planData['units'] ?? [];
        if (is_array($units) && $units) {
            $unitBlock = "## Units and Topics\n";
            foreach ($units as $unit) {
                if (!is_array($unit)) {
                    continue;
                }
                $title = trim((string)($unit['title'] ?? ('Unit ' . (int)($unit['unit_number'] ?? 0))));
                $unitBlock .= "### {$title}\n";
                foreach (($unit['topics'] ?? []) as $topic) {
                    $unitBlock .= '- ' . trim((string)$topic) . "\n";
                }
            }
            $parts[] = trim($unitBlock);
        }
    }

    $docs = Database::fetchAll(
        'SELECT d.title, d.content_text FROM documents d
         JOIN subjects s ON s.id = d.subject_id
         WHERE d.subject_id = ? AND d.is_published = 1 AND s.institution_id = ?
         ORDER BY d.id DESC LIMIT 10',
        [$subjectId, $instId]
    );
    foreach ($docs as $doc) {
        $text = trim((string)($doc['content_text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $parts[] = '## ' . trim((string)($doc['title'] ?? 'Material')) . "\n" . mb_substr($text, 0, 2500);
    }

    return trim(implode("\n\n", array_filter($parts)));
}

/** @return list<string> */
function ask_ai_question_terms(string $text): array
{
    $text = strtolower(preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '');
    $terms = [];
    foreach (preg_split('/\s+/', $text) ?: [] as $word) {
        $word = trim($word);
        if (strlen($word) >= 4 && !in_array($word, ['what', 'when', 'where', 'which', 'that', 'this', 'with', 'from', 'your', 'about', 'tell', 'please'], true)) {
            $terms[] = $word;
        }
    }
    return array_values(array_unique($terms));
}

function ask_ai_subject_fit_score(string $question, string $subjectName, string $materials): int
{
    $q = strtolower($question);
    $score = 0;
    foreach (preg_split('/\s+/', strtolower(preg_replace('/[^a-z0-9\s]/', ' ', $subjectName) ?? '')) ?: [] as $word) {
        if (strlen($word) >= 4 && str_contains($q, $word)) {
            $score += 5;
        }
    }
    $mat = strtolower($materials);
    foreach (ask_ai_question_terms($question) as $term) {
        if (str_contains($mat, $term)) {
            $score += 3;
        }
    }
    return $score;
}

/**
 * Offline syllabus-grounded answer when Gemini is unavailable (student Ask AI only).
 *
 * @param list<string> $otherSubjectNames
 * @return array{answer:string,citations:list<string>}
 */
function answer_student_ask_ai_offline(
    string $question,
    string $subjectName,
    string $materials,
    array $otherSubjectNames,
    array $user
): array {
    $bestOther = null;
    $bestOtherScore = 0;
    $selectedScore = ask_ai_subject_fit_score($question, $subjectName, $materials);

    foreach ($otherSubjectNames as $otherName) {
        $otherName = trim($otherName);
        if ($otherName === '') {
            continue;
        }
        $otherRow = null;
        foreach (courses_for_student($user) as $s) {
            if (trim((string)($s['name'] ?? '')) === $otherName) {
                $otherRow = $s;
                break;
            }
        }
        $otherMaterials = $otherRow ? build_student_ask_ai_materials($user, $otherRow) : '';
        $score = ask_ai_subject_fit_score($question, $otherName, $otherMaterials);
        if ($score > $bestOtherScore) {
            $bestOtherScore = $score;
            $bestOther = $otherName;
        }
    }

    if ($bestOther !== null && $bestOtherScore >= 4 && $bestOtherScore > $selectedScore + 2) {
        return [
            'answer' => "This question is related to {$bestOther} rather than {$subjectName}. "
                . "Please select {$bestOther} from the Subject context dropdown and ask again.",
            'citations' => [],
        ];
    }

    $hasMaterials = trim($materials) !== '' && !str_contains(strtolower($materials), 'no syllabus');
    if (!$hasMaterials) {
        return [
            'answer' => "I don't have enough course material for this topic in {$subjectName}.",
            'citations' => [],
        ];
    }

    $qTerms = ask_ai_question_terms($question);
    $snippets = [];
    foreach (preg_split('/\r\n|\r|\n/', $materials) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $lineLower = strtolower($line);
        foreach ($qTerms as $term) {
            if (str_contains($lineLower, $term)) {
                $snippets[] = preg_replace('/^[\-\*]\s*/', '', $line) ?? $line;
                break;
            }
        }
    }
    $snippets = array_values(array_unique(array_slice($snippets, 0, 6)));

    if (preg_match('/\b(hard|easy|difficult|tough|simple|bro)\b/i', $question)) {
        $focus = $snippets ? implode('; ', array_slice($snippets, 0, 3)) : '';
        $answer = "For {$subjectName}, the difficulty of a project presentation or viva depends on how well you prepare the topics covered in your syllabus";
        if ($focus !== '') {
            $answer .= ", including areas such as {$focus}";
        }
        $answer .= ".\n\n"
            . "It becomes easier when you:\n"
            . "• Revise the unit outcomes and core concepts from your course plan\n"
            . "• Prepare clear slides/demos aligned with the project requirements\n"
            . "• Practice explaining your work, results, and limitations confidently\n\n"
            . "If your question is about a specific technical topic, ask about that topic directly so I can answer from the {$subjectName} syllabus.";
        return ['answer' => $answer, 'citations' => [$subjectName . ' syllabus']];
    }

    if ($snippets) {
        $body = "In {$subjectName}, based on your course syllabus:\n\n";
        foreach ($snippets as $snippet) {
            $body .= "• {$snippet}\n";
        }
        $body .= "\n";
        if (str_contains(strtolower($question), 'http')) {
            $body .= "HTTP (Hypertext Transfer Protocol) is an application-layer protocol used for communication between a web client (browser) and a web server. "
                . "The client sends a request; the server returns a response (such as an HTML page). "
                . "HTTPS adds encryption (TLS/SSL) on top of HTTP for secure communication.";
        } elseif (count($snippets) === 1) {
            $body .= "This topic is part of your {$subjectName} syllabus. Review the related unit notes and practice explaining the concept with examples from your course material.";
        } else {
            $body .= "These syllabus points are the relevant foundation for your question. Study them together and try a short example or definition in your own words.";
        }
        return ['answer' => trim($body), 'citations' => [$subjectName . ' syllabus']];
    }

    $unitHints = [];
    foreach (preg_split('/\r\n|\r|\n/', $materials) ?: [] as $line) {
        if (preg_match('/^[\-\*]\s*(.+)$/', trim($line), $m)) {
            $unitHints[] = trim($m[1]);
        }
        if (count($unitHints) >= 4) {
            break;
        }
    }
    $hint = $unitHints ? (' Topics in your syllabus include: ' . implode(', ', $unitHints) . '.') : '';

    return [
        'answer' => "I don't have enough course material for this specific topic in {$subjectName}.{$hint} "
            . 'Try asking about a syllabus topic listed in your course plan, or rephrase your question using terms from the unit outline.',
        'citations' => [$subjectName . ' syllabus'],
    ];
}

/**
 * @param list<mixed> $raw
 * @return list<array<string,mixed>>
 */
function normalize_generated_questions(array $raw, string $type, string $klevel, int $unit, int $count): array
{
    $out = [];
    foreach ($raw as $q) {
        if (!is_array($q)) {
            continue;
        }
        $stem = trim((string)($q['stem'] ?? $q['question'] ?? ''));
        if ($stem === '') {
            continue;
        }
        $item = [
            'stem' => $stem,
            'bloom_k_level' => strtoupper((string)($q['bloom_k_level'] ?? $klevel)),
            'unit_number' => (int)($q['unit_number'] ?? $unit),
            'marks' => (float)($q['marks'] ?? ($type === 'mcq' ? 1 : ($type === 'short' ? 5 : 10))),
            'difficulty' => (string)($q['difficulty'] ?? 'medium'),
            'correct_answer' => $q['correct_answer'] ?? ($q['answer'] ?? null),
            'explanation' => $q['explanation'] ?? null,
            'question_type' => $type,
        ];
        if ($type === 'mcq') {
            $options = $q['options'] ?? null;
            $normalized = [];
            if (is_array($options)) {
                $isList = array_keys($options) === range(0, count($options) - 1);
                if ($isList) {
                    $labels = ['A', 'B', 'C', 'D'];
                    foreach (array_slice(array_values($options), 0, 4) as $i => $opt) {
                        $normalized[$labels[$i]] = is_string($opt) ? trim($opt) : trim((string)json_encode($opt));
                    }
                } else {
                    foreach (['A', 'B', 'C', 'D'] as $label) {
                        foreach ($options as $k => $v) {
                            if (strtoupper((string)$k) === $label || str_starts_with(strtoupper((string)$k), $label)) {
                                $normalized[$label] = is_string($v) ? trim($v) : trim((string)json_encode($v));
                                break;
                            }
                        }
                    }
                }
            }
            if (count($normalized) === 4) {
                $item['options'] = $normalized;
                $ans = strtoupper(trim((string)($item['correct_answer'] ?? '')));
                if (!isset($normalized[$ans])) {
                    // If model returned option text, map it back to a key.
                    foreach ($normalized as $k => $v) {
                        if (strcasecmp($v, (string)$item['correct_answer']) === 0) {
                            $ans = $k;
                            break;
                        }
                    }
                }
                if (!isset($normalized[$ans])) {
                    $ans = 'A';
                }
                $item['correct_answer'] = $ans;
            }
        }
        $out[] = $item;
        if (count($out) >= $count) {
            break;
        }
    }
    return $out;
}

/**
 * @param list<array<string,mixed>> $questions
 */
function question_bank_is_usable(array $questions, string $type, int $count): bool
{
    if (count($questions) < max(1, (int)ceil($count * 0.6))) {
        return false;
    }
    $placeholderHits = 0;
    foreach ($questions as $q) {
        $stem = strtolower((string)($q['stem'] ?? ''));
        if ($stem === '' || str_contains($stem, 'explain / choose concept') || str_contains($stem, 'choose concept')) {
            $placeholderHits++;
            continue;
        }
        if ($type === 'mcq') {
            $opts = $q['options'] ?? null;
            if (!is_array($opts) || count($opts) < 4) {
                $placeholderHits++;
                continue;
            }
            $joined = strtolower(implode(' ', array_map(static fn($v) => is_string($v) ? $v : json_encode($v), $opts)));
            if (str_contains($joined, 'option a') && str_contains($joined, 'option b')) {
                $placeholderHits++;
                continue;
            }
            // Reject circular / template-garbage answers that only restate the topic.
            if (
                str_contains($joined, 'fundamental unit')
                || str_contains($joined, 'covering ')
                || str_contains($joined, 'replaces all other')
                || str_contains($joined, 'documentation only')
                || str_contains($joined, 'unrelated to')
                || str_contains($joined, 'belongs only to')
                || (str_contains($stem, 'best defines') && str_contains($joined, 'fundamental'))
                || (str_contains($stem, 'key term related') && preg_match('/\bis a key term\b/', $joined))
            ) {
                $placeholderHits++;
            }
        }
    }
    return $placeholderHits === 0;
}

/**
 * @param list<mixed> $raw
 * @return list<array<string,mixed>>
 */
function normalize_generated_slides(array $raw, int $unit): array
{
    $unitTag = 'Unit ' . max(1, $unit);
    $out = [];
    foreach ($raw as $i => $slide) {
        if (!is_array($slide)) {
            continue;
        }
        $title = trim((string)($slide['title'] ?? ''));
        $bulletsRaw = $slide['bullets'] ?? ($slide['points'] ?? []);
        $bullets = [];
        if (is_array($bulletsRaw)) {
            foreach ($bulletsRaw as $b) {
                $text = trim(is_string($b) ? $b : (string)json_encode($b));
                if ($text !== '') {
                    $bullets[] = $text;
                }
            }
        } elseif (is_string($bulletsRaw) && trim($bulletsRaw) !== '') {
            $bullets[] = trim($bulletsRaw);
        }
        if ($title === '' && !$bullets) {
            continue;
        }
        if ($title === '') {
            $title = 'Slide ' . (count($out) + 1);
        }
        // Skip unit-heading-as-topic slides
        if (class_exists('LectureSlideBuilder', false) && LectureSlideBuilder::isUnitHeading($title)) {
            continue;
        }
        $notes = trim((string)($slide['speaker_notes'] ?? $slide['notes'] ?? ''));
        $row = [
            'number' => (int)($slide['number'] ?? (count($out) + 1)),
            'title' => $title,
            'bullets' => $bullets ?: ['Key teaching point for ' . $unitTag],
            'speaker_notes' => $notes !== '' ? $notes : ('Teaching notes for ' . $title),
            'unit_tag' => $unitTag,
            'layout' => trim((string)($slide['layout'] ?? 'content')) ?: 'content',
        ];
        if (!empty($slide['comparison']) && is_array($slide['comparison'])) {
            $row['comparison'] = $slide['comparison'];
            $row['layout'] = 'comparison';
        }
        if (!empty($slide['code']) && is_string($slide['code'])) {
            $row['code'] = $slide['code'];
            $row['layout'] = 'code';
        }
        if (!empty($slide['diagram']) && is_array($slide['diagram'])) {
            $row['diagram'] = $slide['diagram'];
            if (($row['layout'] ?? '') === 'content') {
                $row['layout'] = 'diagram';
            }
        }
        $out[] = $row;
    }
    foreach ($out as $idx => &$slide) {
        $slide['number'] = $idx + 1;
        $slide['unit_tag'] = $unitTag;
    }
    unset($slide);
    return $out;
}

/**
 * @param list<array<string,mixed>> $slides
 */
function ppt_slides_are_usable(array $slides): bool
{
    if (count($slides) < 6) {
        return false;
    }
    if (class_exists('LectureSlideBuilder', false) && LectureSlideBuilder::containsPlaceholders($slides)) {
        return false;
    }
    $bad = 0;
    foreach ($slides as $slide) {
        $title = strtolower((string)($slide['title'] ?? ''));
        $bullets = $slide['bullets'] ?? [];
        $joined = strtolower(implode(' ', array_map(static fn($b) => is_string($b) ? $b : json_encode($b), (array)$bullets)));
        $notes = strtolower((string)($slide['speaker_notes'] ?? ''));
        if (
            preg_match('/^topic slide\s*\d*$/', $title)
            || str_contains($joined, 'point a')
            || str_contains($joined, 'point b')
            || str_contains($notes, 'talking points for slide')
            || $title === ''
            || count((array)$bullets) < 2
        ) {
            $bad++;
        }
    }
    return $bad === 0;
}

function render_plan_html(array $plan, int $planId = 0): string
{
    ob_start();
    $units = $plan['units'] ?? [];
    $bloom = $plan['bloom_distribution'] ?? [];
    $balance = CoursePlanTools::bloomBalance([
        'bloom_data' => json_encode($bloom),
        'plan_data' => json_encode($plan),
    ], is_array($units) ? $units : []);
    $tpl = CoursePlanTools::templateLabel((string)($plan['accreditation_template'] ?? 'standard'));
    ?>
    <div class="grid grid-2">
      <div class="panel">
        <div class="panel-h"><h3><?= e($plan['title'] ?? 'Course Plan') ?></h3>
          <?php if ($planId): ?><a class="btn btn-sm btn-primary" href="<?= e(base_url('/professor/plan-view.php?id='.$planId)) ?>">Open plan</a><?php endif; ?>
        </div>
        <?php if (!empty($plan['demo'])): ?><div class="alert alert-info">Demo mode (no Gemini key). Structure is editable after save.</div><?php endif; ?>
        <p style="font-size:.9rem;color:var(--muted)">Template: <strong><?= e($tpl) ?></strong></p>
        <p><strong>Outcomes</strong></p>
        <ul><?php foreach (($plan['learning_outcomes'] ?? []) as $o): ?><li><?= e(is_string($o)?$o:json_encode($o)) ?></li><?php endforeach; ?></ul>
        <p><strong>Resources</strong></p>
        <ul><?php foreach (($plan['resources'] ?? []) as $r): ?><li><?= e(is_string($r)?$r:json_encode($r)) ?></li><?php endforeach; ?></ul>
      </div>
      <div class="panel">
        <h3>Bloom's distribution</h3>
        <canvas id="bloomPreview" height="180"></canvas>
        <script>document.addEventListener('DOMContentLoaded',()=>PPAI.renderBloomChart('bloomPreview', <?= json_encode($balance['distribution']) ?>));</script>
        <div class="bloom-bars" style="margin-top:.8rem">
          <?php foreach ($balance['distribution'] as $k => $v): ?>
            <div class="bloom-row"><strong><?= e((string)$k) ?></strong><div class="bar"><span style="width:<?= (float)$v ?>%"></span></div><span><?= e((string)$v) ?>%</span></div>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($balance['warning'])): ?>
          <div class="alert alert-warn" style="margin-top:.8rem"><?= e((string)$balance['warning']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="panel" style="margin-top:1rem">
      <h3>Unit-wise plan</h3>
      <div class="table-wrap"><table>
        <thead><tr><th>Unit</th><th>Title</th><th>Hours</th><th>Bloom</th><th>Outcomes</th></tr></thead>
        <tbody>
        <?php foreach ($units as $u): ?>
          <tr>
            <td><?= (int)($u['unit_number'] ?? 0) ?></td>
            <td><?= e((string)($u['title'] ?? '')) ?></td>
            <td><?= e((string)($u['hours'] ?? '')) ?></td>
            <td><span class="badge badge-info"><?= e((string)($u['bloom_k_level'] ?? '')) ?></span></td>
            <td><?= e(is_array($u['outcomes'] ?? null) ? implode('; ', $u['outcomes']) : (string)($u['outcomes'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <?php
    return (string)ob_get_clean();
}

try {
    switch ($module) {
        case 'syllabus_extract': {
            // Prefer AiController::syllabusExtract; keep this as fallback. Never HTML-redirect.
            $role = (string)($user['role'] ?? '');
            if (!in_array($role, ['professor', 'admin', 'superadmin'], true)) {
                json_response(['ok' => false, 'error' => 'Permission denied.'], 403);
            }
            $file = $_FILES['syllabus_file'] ?? null;
            if (!is_array($file)) {
                json_response(['ok' => false, 'error' => 'No file uploaded.'], 422);
            }
            try {
                $text = CoursePlanTools::extractUploadedSyllabus($file);
            } catch (Throwable $e) {
                json_response(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            json_response(['ok' => true, 'text' => $text]);
        }

        case 'course_plan': {
            Auth::requireRole('professor', 'admin', 'hod');
            $subject = trim((string)post('subject'));
            $credits = (string)post('credits', '3');
            $university = trim((string)post('university', ''));
            $syllabus = trim((string)post('syllabus'));
            $subjectId = (int)post('subject_id', 0) ?: null;
            $classId = (int)post('class_id', 0) ?: null;
            $template = CoursePlanTools::normalizeTemplate(post('accreditation_template', 'standard'));
            $existingPlanId = (int)post('plan_id', 0);
            $existingPlan = null;
            if ($existingPlanId > 0) {
                $existingPlan = Database::fetch(
                    'SELECT * FROM course_plans WHERE id = ? AND professor_id = ? AND institution_id = ?',
                    [$existingPlanId, (int)$user['id'], (int)$user['institution_id']]
                );
                if (!$existingPlan) {
                    json_response(['ok' => false, 'error' => 'Plan not found for regeneration.'], 404);
                }
                if (!in_array((string)$existingPlan['status'], ['draft', 'returned'], true)) {
                    json_response(['ok' => false, 'error' => 'Only draft or returned plans can be regenerated into a new version.'], 422);
                }
            }
            if ($classId && !professor_can_manage_class($user, $classId)) {
                json_response(['ok' => false, 'error' => 'Select a class assigned to you by your HOD.'], 422);
            }
            if ($subjectId && $classId && !professor_can_manage_subject($user, $subjectId, $classId)) {
                json_response(['ok' => false, 'error' => 'You are not assigned to this course for the selected class.'], 422);
            }

            if ($subject === '' || $syllabus === '') {
                json_response(['ok' => false, 'error' => 'Subject and syllabus are required.'], 422);
            }

            $tpl = prompt_template('course_plan');
            $system = $tpl['system_prompt'] ?? 'Return JSON course plan for Indian OBE curriculum.';
            $system .= "\n" . CoursePlanTools::templatePromptHint($template);
            $userPrompt = "Subject: $subject\nCredits: $credits\nUniversity: $university\nCurriculum template: "
                . CoursePlanTools::templateLabel($template)
                . "\nSyllabus:\n$syllabus\n\nReturn JSON with keys: title, learning_outcomes[], units[{unit_number,title,hours,topics[],outcomes[],bloom_k_level,teaching_methods[],assessment[]}], weekly_plan[], resources[], expert_advice[], bloom_distribution{K1..K6}, ai_score.";

            if ($gemini->isConfigured()) {
                $result = $gemini->generate($system, $userPrompt);
                $plan = $result['json'] ?? null;
                if (!$plan) {
                    $result['ok'] = false;
                    $result['error'] = $result['error'] ?? 'Model did not return JSON.';
                }
            } else {
                $plan = Gemini::demoCoursePlan($subject, $syllabus);
                $result = ['ok' => true, 'json' => $plan, 'text' => json_encode($plan), 'latency_ms' => 0];
            }

            log_ai('course_plan', compact('subject', 'credits', 'university', 'template'), $result);

            if (empty($result['ok']) || !$plan || !is_array($plan)) {
                json_response(['ok' => false, 'error' => $result['error'] ?? 'AI failed'], 500);
            }

            // Prefer real syllabus topics over generic placeholders (Topic 1.1).
            if (!empty($plan['units']) && is_array($plan['units'])) {
                $plan['units'] = CoursePlanTools::enrichUnitsFromSyllabus($plan['units'], $syllabus);
            }

            // Ensure bloom_distribution exists for checker.
            if (empty($plan['bloom_distribution']) || !is_array($plan['bloom_distribution'])) {
                $tmpBal = CoursePlanTools::bloomBalance(['plan_data' => json_encode($plan)], $plan['units'] ?? []);
                $plan['bloom_distribution'] = $tmpBal['distribution'];
            }
            $plan['accreditation_template'] = $template;

            $meta = [
                'accreditation_template' => $template,
            ];

            if ($existingPlan) {
                $planId = (int)$existingPlan['id'];
                $newVersion = (int)$existingPlan['version'] + 1;
                $prevMeta = json_decode((string)($existingPlan['meta'] ?? '{}'), true) ?: [];
                $meta = array_merge($prevMeta, $meta);
                Database::update('course_plans', [
                    'title' => $plan['title'] ?? ($subject . ' Course Plan'),
                    'subject_name' => $subject,
                    'credits' => $credits,
                    'university' => $university,
                    'syllabus_input' => $syllabus,
                    'status' => 'draft',
                    'ai_score' => $plan['ai_score'] ?? null,
                    'bloom_data' => json_encode($plan['bloom_distribution'] ?? []),
                    'weekly_plan' => json_encode($plan['weekly_plan'] ?? []),
                    'resources' => json_encode($plan['resources'] ?? []),
                    'expert_advice' => json_encode($plan['expert_advice'] ?? []),
                    'plan_data' => json_encode($plan),
                    'version' => $newVersion,
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'subject_id' => $subjectId ?: $existingPlan['subject_id'],
                    'class_id' => $classId ?: $existingPlan['class_id'],
                ], 'id = :id AND professor_id = :pid', [
                    'id' => $planId,
                    'pid' => (int)$user['id'],
                ]);
                Database::query('DELETE FROM plan_units WHERE plan_id = ?', [$planId]);
                foreach (($plan['units'] ?? []) as $i => $u) {
                    Database::insert('plan_units', [
                        'plan_id' => $planId,
                        'unit_number' => (int)($u['unit_number'] ?? ($i + 1)),
                        'title' => (string)($u['title'] ?? 'Unit'),
                        'hours' => $u['hours'] ?? 0,
                        'topics' => json_encode($u['topics'] ?? []),
                        'outcomes' => json_encode($u['outcomes'] ?? []),
                        'bloom_k_level' => $u['bloom_k_level'] ?? null,
                        'teaching_methods' => json_encode($u['teaching_methods'] ?? []),
                        'assessment' => json_encode($u['assessment'] ?? []),
                        'sort_order' => $i,
                    ]);
                }
                Database::insert('course_plan_versions', [
                    'plan_id' => $planId,
                    'version' => $newVersion,
                    'snapshot' => json_encode($plan),
                    'change_note' => 'Regenerated with AI · template ' . CoursePlanTools::templateLabel($template),
                    'created_by' => (int)$user['id'],
                ]);
            } else {
                $planId = Database::insert('course_plans', [
                    'institution_id' => (int)$user['institution_id'],
                    'department_id' => $user['department_id'] ?? null,
                    'professor_id' => (int)$user['id'],
                    'subject_id' => $subjectId,
                    'class_id' => $classId,
                    'title' => $plan['title'] ?? ($subject . ' Course Plan'),
                    'subject_name' => $subject,
                    'credits' => $credits,
                    'university' => $university,
                    'syllabus_input' => $syllabus,
                    'status' => 'draft',
                    'ai_score' => $plan['ai_score'] ?? null,
                    'bloom_data' => json_encode($plan['bloom_distribution'] ?? []),
                    'weekly_plan' => json_encode($plan['weekly_plan'] ?? []),
                    'resources' => json_encode($plan['resources'] ?? []),
                    'expert_advice' => json_encode($plan['expert_advice'] ?? []),
                    'plan_data' => json_encode($plan),
                    'version' => 1,
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                ]);

                foreach (($plan['units'] ?? []) as $i => $u) {
                    Database::insert('plan_units', [
                        'plan_id' => $planId,
                        'unit_number' => (int)($u['unit_number'] ?? ($i + 1)),
                        'title' => (string)($u['title'] ?? 'Unit'),
                        'hours' => $u['hours'] ?? 0,
                        'topics' => json_encode($u['topics'] ?? []),
                        'outcomes' => json_encode($u['outcomes'] ?? []),
                        'bloom_k_level' => $u['bloom_k_level'] ?? null,
                        'teaching_methods' => json_encode($u['teaching_methods'] ?? []),
                        'assessment' => json_encode($u['assessment'] ?? []),
                        'sort_order' => $i,
                    ]);
                }

                Database::insert('course_plan_versions', [
                    'plan_id' => $planId,
                    'version' => 1,
                    'snapshot' => json_encode($plan),
                    'change_note' => 'Initial AI generation · template ' . CoursePlanTools::templateLabel($template),
                    'created_by' => (int)$user['id'],
                ]);
            }

            json_response([
                'ok' => true,
                'data' => $plan,
                'html' => render_plan_html($plan, $planId),
                'redirect' => base_url('/professor/plan-view.php?id=' . $planId),
            ]);
        }

        case 'bloom':
        case 'review':
        case 'improve':
        case 'lesson':
        case 'questions':
        case 'ppt':
        case 'assignment':
        case 'formula':
        case 'ask_ai': {
            // Handled in specialized endpoints below via module alias mapping
            break;
        }
        default:
            json_response(['ok' => false, 'error' => 'Unknown module'], 400);
    }

    // Secondary modules
    if ($module === 'bloom') {
        Auth::requireRole('professor', 'hod', 'admin');
        $planId = (int)post('plan_id');
        $plan = load_plan_for_user($planId, $user);
        if (!$plan) json_response(['ok'=>false,'error'=>'Plan not found'], 404);
        $tpl = prompt_template('bloom_map');
        $payload = $plan['plan_data'];
        if ($gemini->isConfigured()) {
            $result = $gemini->generate($tpl['system_prompt'] ?? 'Map Bloom levels. JSON only.', "Course plan JSON:\n$payload");
            $data = $result['json'] ?? [];
        } else {
            $data = ['bloom_distribution' => json_decode($plan['bloom_data'] ?: '{}', true) ?: ['K1'=>15,'K2'=>20,'K3'=>25,'K4'=>20,'K5'=>12,'K6'=>8], 'demo'=>true];
            $result = ['ok'=>true,'json'=>$data,'latency_ms'=>0];
        }
        log_ai('bloom', ['plan_id'=>$planId], $result, 'course_plan', $planId);
        if (!empty($data['bloom_distribution'])) {
            Database::update('course_plans', ['bloom_data' => json_encode($data['bloom_distribution'])], 'id = :id', ['id'=>$planId]);
        }
        json_response(['ok'=>true,'data'=>$data]);
    }

    if ($module === 'review') {
        Auth::requireRole('professor', 'hod', 'admin');
        $planId = (int)post('plan_id');
        $plan = load_plan_for_user($planId, $user);
        if (!$plan) json_response(['ok'=>false,'error'=>'Plan not found'], 404);
        $tpl = prompt_template('ai_review');
        if ($gemini->isConfigured()) {
            $result = $gemini->generate($tpl['system_prompt'] ?? 'Review course plan.', (string)$plan['plan_data']);
            $data = $result['json'] ?? ['score'=>70,'recommendations'=>['Add more K4-K6 outcomes']];
        } else {
            $data = [
                'score' => (float)($plan['ai_score'] ?? 76),
                'parameters' => [
                    'NAAC alignment' => 80, 'Industry relevance' => 72, 'OBE compliance' => 78,
                    'Resource adequacy' => 70, 'Contact hours' => 85, 'K-level balance' => 68,
                ],
                'recommendations' => [
                    'Increase K4-K6 coverage in Units 4-5',
                    'Add industry case references in Unit 3',
                    'Link each CLO to an assessment instrument',
                ],
                'demo' => true,
            ];
            $result = ['ok'=>true,'json'=>$data,'latency_ms'=>0];
        }
        $score = $data['score'] ?? $data['ai_score'] ?? null;
        Database::update('course_plans', [
            'ai_score' => $score,
            'ai_review' => json_encode($data),
        ], 'id = :id', ['id'=>$planId]);
        log_ai('review', ['plan_id'=>$planId], $result, 'course_plan', $planId);
        json_response(['ok'=>true,'data'=>$data]);
    }

    if ($module === 'improve') {
        Auth::requireRole('professor', 'admin');
        $planId = (int)post('plan_id');
        $instruction = trim((string)post('instruction'));
        $plan = Database::fetch('SELECT * FROM course_plans WHERE id = ? AND professor_id = ?', [$planId, $user['id']]);
        if (!$plan) json_response(['ok'=>false,'error'=>'Plan not found'], 404);
        $tpl = prompt_template('improve_plan');
        if ($gemini->isConfigured()) {
            $result = $gemini->generate(
                $tpl['system_prompt'] ?? 'Improve plan. Return JSON.',
                "Instruction: $instruction\nCurrent plan:\n" . $plan['plan_data']
            );
            $data = $result['json'] ?? null;
        } else {
            $data = json_decode((string)$plan['plan_data'], true) ?: [];
            $data['expert_advice'][] = 'Improved per instruction: ' . $instruction;
            $data['change_summary'] = 'Demo improve applied locally.';
            $result = ['ok'=>true,'json'=>$data,'latency_ms'=>0];
        }
        if (!$data) json_response(['ok'=>false,'error'=>$result['error'] ?? 'No data'], 500);
        $newVersion = (int)$plan['version'] + 1;
        $meta = json_decode((string)($plan['meta'] ?? '{}'), true) ?: [];
        if (!empty($data['accreditation_template'])) {
            $meta['accreditation_template'] = CoursePlanTools::normalizeTemplate($data['accreditation_template']);
        } elseif (!empty($meta['accreditation_template'])) {
            $data['accreditation_template'] = $meta['accreditation_template'];
        }
        Database::update('course_plans', [
            'plan_data' => json_encode($data),
            'bloom_data' => json_encode($data['bloom_distribution'] ?? json_decode($plan['bloom_data'] ?: '{}', true)),
            'resources' => json_encode($data['resources'] ?? []),
            'expert_advice' => json_encode($data['expert_advice'] ?? []),
            'weekly_plan' => json_encode($data['weekly_plan'] ?? []),
            'version' => $newVersion,
            'ai_score' => $data['ai_score'] ?? $plan['ai_score'],
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id'=>$planId]);
        Database::insert('course_plan_versions', [
            'plan_id' => $planId,
            'version' => $newVersion,
            'snapshot' => json_encode($data),
            'change_note' => $instruction,
            'created_by' => (int)$user['id'],
        ]);
        // Refresh plan_units when improve returns units
        if (!empty($data['units']) && is_array($data['units'])) {
            Database::query('DELETE FROM plan_units WHERE plan_id = ?', [$planId]);
            foreach ($data['units'] as $i => $u) {
                if (!is_array($u)) {
                    continue;
                }
                Database::insert('plan_units', [
                    'plan_id' => $planId,
                    'unit_number' => (int)($u['unit_number'] ?? ($i + 1)),
                    'title' => (string)($u['title'] ?? 'Unit'),
                    'hours' => $u['hours'] ?? 0,
                    'topics' => json_encode($u['topics'] ?? []),
                    'outcomes' => json_encode($u['outcomes'] ?? []),
                    'bloom_k_level' => $u['bloom_k_level'] ?? null,
                    'teaching_methods' => json_encode($u['teaching_methods'] ?? []),
                    'assessment' => json_encode($u['assessment'] ?? []),
                    'sort_order' => $i,
                ]);
            }
        }
        log_ai('improve', ['plan_id'=>$planId,'instruction'=>$instruction], $result, 'course_plan', $planId);
        json_response(['ok'=>true,'data'=>$data,'redirect'=>base_url('/professor/plan-view.php?id='.$planId)]);
    }

    if ($module === 'lesson') {
        Auth::requireRole('professor', 'admin');
        LessonPlanTools::ensureSchema();
        $planId = (int)post('plan_id');
        $plan = load_plan_for_user($planId, $user);
        if (!$plan) json_response(['ok'=>false,'error'=>'Plan not found'], 404);
        // Soft-repair placeholder topics from syllabus_input (no status/version change).
        $plan = CoursePlanTools::syncPlanTopicsFromSyllabus($plan);
        $fromPlan = lesson_sessions_from_course_plan($plan);
        $tpl = prompt_template('lesson_plan');
        $sessions = [];
        $result = ['ok' => true, 'json' => ['sessions' => $fromPlan], 'latency_ms' => 0];
        $sessionMins = lesson_default_session_minutes();
        $requiredCount = count($fromPlan);
        $requiredMins = 0;
        foreach ($fromPlan as $s) {
            $requiredMins += (int)($s['duration_mins'] ?? $sessionMins);
        }
        if ($gemini->isConfigured()) {
            $system = trim((string)($tpl['system_prompt'] ?? 'Generate session-by-session lesson plans from the course plan.'));
            $system .= "\nReturn ONLY JSON: {\"sessions\":[{\"session_number\":1,\"title\":\"specific topic\",\"duration_mins\":{$sessionMins},\"objectives\":[\"...\"],\"teaching_method\":\"one method\",\"activities\":[\"classroom activity\"],\"formative_assessment\":[\"check for understanding\"],\"engagement\":[\"student engagement strategy\"]}]}. Every session must include all of those fields. Do not return weekly_plan or empty titles.";
            $userPrompt = "Create exactly {$requiredCount} classroom sessions from this course plan. "
                . "Default session length is {$sessionMins} minutes. "
                . "Total session minutes must equal the unit contact hours (about {$requiredMins} minutes / "
                . round($requiredMins / 60, 1) . " hours). "
                . "Distribute each unit's topics across enough sessions to cover that unit's hours — "
                . "multiple sessions for the same topic when needed, with varied teaching method, activity, assessment, and engagement. "
                . "Subject: " . (string)($plan['subject_name'] ?? '') . "\n\n" . (string)$plan['plan_data'];
            $result = $gemini->generate($system, $userPrompt);
            $sessions = extract_ai_lesson_sessions(is_array($result['json'] ?? null) ? $result['json'] : null);
        }
        // Fall back when AI under-fills hours or returns unusable content.
        if (!lesson_sessions_are_usable($sessions) || !lesson_sessions_cover_plan_hours($sessions, $plan)) {
            $sessions = $fromPlan;
        }
        Database::query('DELETE FROM lesson_plans WHERE plan_id = ?', [$planId]);
        foreach ($sessions as $i => $s) {
            $n = (int)($s['session_number'] ?? ($i + 1));
            Database::insert('lesson_plans', [
                'plan_id' => $planId,
                'professor_id' => (int)$user['id'],
                'unit_id' => $s['unit_id'] ?? null,
                'unit_number' => ((int)($s['unit_number'] ?? 0) > 0) ? (int)$s['unit_number'] : null,
                'session_number' => $n,
                'title' => (string)($s['title'] ?? ('Session ' . $n)),
                'duration_mins' => (int)($s['duration_mins'] ?? $sessionMins),
                'objectives' => json_encode($s['objectives'] ?? []),
                'teaching_method' => $s['teaching_method'] ?: null,
                'activities' => json_encode($s['activities'] ?? []),
                'formative_assessment' => json_encode($s['formative_assessment'] ?? []),
                'engagement' => json_encode($s['engagement'] ?? []),
                'content' => json_encode($s),
            ]);
        }
        log_ai('lesson', ['plan_id'=>$planId], $result, 'course_plan', $planId);
        json_response([
            'ok' => true,
            'data' => ['count' => count($sessions)],
            'redirect' => base_url('/professor/lessons.php?plan_id=' . $planId),
        ]);
    }

    if ($module === 'questions') {
        Auth::requireRole('professor', 'admin');
        $type = strtolower(trim((string)post('question_type', 'mcq')));
        if (!in_array($type, ['mcq', 'short', 'long', 'essay', 'case'], true)) {
            $type = 'mcq';
        }
        $unit = max(1, min(20, (int)post('unit', 1)));
        $klevel = strtoupper(trim((string)post('klevel', 'K2')));
        if (!preg_match('/^K[1-6]$/', $klevel)) {
            $klevel = 'K2';
        }
        $count = max(1, min(20, (int)post('count', 5)));
        $context = trim((string)post('context', ''));
        $planId = (int)post('plan_id', 0) ?: null;
        $plan = null;
        $subjectName = '';
        $unitTopics = [];

        if ($planId) {
            $plan = load_plan_for_user($planId, $user);
            if (!$plan) {
                json_response(['ok' => false, 'error' => 'Course plan not found.'], 404);
            }
            // Soft-repair placeholder topics from syllabus (keeps approval/version intact).
            $plan = CoursePlanTools::syncPlanTopicsFromSyllabus($plan);
            $subjectName = trim((string)($plan['subject_name'] ?? ''));
            if ($subjectName === '') {
                $subjectName = trim((string)($plan['title'] ?? ''));
            }
            if ($context === '') {
                $context = trim((string)($plan['syllabus_input'] ?? ''));
            }
            $planData = json_decode((string)($plan['plan_data'] ?? ''), true) ?: [];
            foreach (($planData['units'] ?? []) as $u) {
                if ((int)($u['unit_number'] ?? 0) !== $unit) {
                    continue;
                }
                if (!empty($u['title'])) {
                    $unitTopics[] = (string)$u['title'];
                }
                foreach ((array)($u['topics'] ?? []) as $t) {
                    if (is_string($t) && trim($t) !== '' && !preg_match('/^topic\s*\d+(\.\d+)?$/i', $t)) {
                        $unitTopics[] = trim($t);
                    }
                }
            }
            $dbUnits = Database::fetchAll(
                'SELECT title, topics FROM plan_units WHERE plan_id = ? AND unit_number = ?',
                [$planId, $unit]
            );
            foreach ($dbUnits as $u) {
                if (!empty($u['title'])) {
                    $unitTopics[] = (string)$u['title'];
                }
                $topics = json_decode((string)($u['topics'] ?? ''), true);
                if (is_array($topics)) {
                    foreach ($topics as $t) {
                        if (is_string($t) && trim($t) !== '' && !preg_match('/^topic\s*\d+(\.\d+)?$/i', $t)) {
                            $unitTopics[] = trim($t);
                        }
                    }
                }
            }
        }

        if ($subjectName === '' && $context !== '') {
            $firstLine = trim((string)(preg_split('/\r\n|\r|\n/', $context)[0] ?? ''));
            if ($firstLine !== '' && !preg_match('/^unit\s*\d+/i', $firstLine) && strlen($firstLine) < 120) {
                $subjectName = $firstLine;
            }
        }
        if ($subjectName === '') {
            $subjectName = 'Course';
        }

        $bloomGuide = [
            'K1' => 'Remember/recall facts, definitions, and basic terminology',
            'K2' => 'Understand/explain concepts in own words with examples',
            'K3' => 'Apply concepts to solve straightforward problems',
            'K4' => 'Analyze by comparing, organizing, or breaking into parts',
            'K5' => 'Evaluate/justify decisions using criteria',
            'K6' => 'Create/design or synthesize a new solution',
        ];
        $unitTopicText = $unitTopics
            ? ("Unit {$unit} topics:\n- " . implode("\n- ", array_values(array_unique($unitTopics))))
            : "Unit {$unit} topics should be inferred strictly from the syllabus/context below.";

        $tpl = prompt_template('question_bank');
        $system = $tpl['system_prompt']
            ?? 'You are an expert university question-paper setter. Return ONLY valid JSON.';

        $userPrompt = "Generate {$count} academically rigorous {$type} questions for a college/university question bank.\n"
            . "Course/Subject: {$subjectName}\n"
            . "Unit number: {$unit} (ONLY this unit — do not use other units)\n"
            . "Bloom level: {$klevel} — {$bloomGuide[$klevel]}\n"
            . "Question type: {$type}\n"
            . "{$unitTopicText}\n\n"
            . "Syllabus / context:\n" . ($context !== '' ? $context : '(Use the course name and unit topics above.)') . "\n\n"
            . "Rules:\n"
            . "- Every question MUST test actual subject knowledge from the Unit {$unit} topics/syllabus above.\n"
            . "- Match Bloom {$klevel} strictly ({$bloomGuide[$klevel]}).\n"
            . "- FORBIDDEN circular items: do NOT ask \"what is X\" / \"best defines X\" where the answer is \"X is a fundamental concept covering X\".\n"
            . "- FORBIDDEN weak distractors such as: \"unrelated\", \"replaces all other topics\", \"documentation only\", \"belongs only to another topic\".\n"
            . "- Correct answers must state real facts, definitions, mechanisms, or applications — not restatements of the topic title.\n"
            . "- Distractors must be plausible misconceptions within the same subject domain.\n"
            . "- Vary stems across the batch; avoid repeating the same sentence template.\n"
            . "- Do NOT invent placeholder text like \"Explain / choose concept\", \"Option A\", \"Option B\".\n"
            . "- For mcq: exactly 4 options as {\"A\":\"...\",\"B\":\"...\",\"C\":\"...\",\"D\":\"...\"}; correct_answer is A/B/C/D; balance correct positions when possible.\n"
            . "- For short/long/essay: useful stem + model correct_answer outline with real content points.\n"
            . "- Set marks appropriately (mcq≈1, short≈5, long≈10).\n"
            . "- Set bloom_k_level to {$klevel} and unit_number to {$unit}.\n\n"
            . "Return JSON only: {\"questions\":[{\"stem\":\"\",\"options\":{\"A\":\"\",\"B\":\"\",\"C\":\"\",\"D\":\"\"},\"correct_answer\":\"A\",\"explanation\":\"\",\"marks\":1,\"difficulty\":\"medium\",\"bloom_k_level\":\"{$klevel}\",\"unit_number\":{$unit}}]}";

        $questions = [];
        $result = ['ok' => true, 'json' => null, 'latency_ms' => 0, 'demo' => false];

        if ($gemini->isConfigured()) {
            $result = $gemini->generate($system, $userPrompt);
            $rawQuestions = is_array($result['json']['questions'] ?? null) ? $result['json']['questions'] : [];
            $questions = normalize_generated_questions($rawQuestions, $type, $klevel, $unit, $count);
            if (!question_bank_is_usable($questions, $type, $count)) {
                $questions = Gemini::demoQuestionBank($subjectName, $type, $klevel, $unit, $count, $context, $unitTopics);
                $result['demo'] = true;
                $result['fallback'] = 'ai_unusable';
            }
        } else {
            $questions = Gemini::demoQuestionBank($subjectName, $type, $klevel, $unit, $count, $context, $unitTopics);
            $result = [
                'ok' => true,
                'json' => ['questions' => $questions],
                'latency_ms' => 0,
                'demo' => true,
            ];
        }

        $bankTitle = strtoupper($type) . " · {$subjectName} · Unit {$unit} · {$klevel}";
        $existingQs = QuestionBankTools::professorQuestions($user);
        $questions = QuestionBankTools::enrichGeneratedQuestions($questions, $plan, $unit, $klevel, $existingQs);
        $bankId = Database::insert('question_banks', [
            'plan_id' => $planId,
            'professor_id' => (int)$user['id'],
            'title' => mb_substr($bankTitle, 0, 180),
            'config' => json_encode([
                'type' => $type,
                'unit' => $unit,
                'klevel' => $klevel,
                'count' => $count,
                'subject' => $subjectName,
            ], JSON_UNESCAPED_UNICODE),
        ]);
        QuestionBankTools::ensureSchema();
        foreach ($questions as $q) {
            $meta = [
                'subject' => $subjectName,
                'clo_code' => $q['clo_code'] ?? null,
            ];
            if (!empty($q['similarity'])) {
                $meta['similarity'] = $q['similarity'];
                $meta['duplicate_reviewed'] = false;
            }
            $insert = [
                'bank_id' => $bankId,
                'unit_number' => $q['unit_number'] ?? $unit,
                'question_type' => in_array($type, ['mcq', 'short', 'long', 'essay', 'case'], true) ? $type : 'mcq',
                'bloom_k_level' => $q['bloom_k_level'] ?? $klevel,
                'difficulty' => $q['difficulty'] ?? 'medium',
                'marks' => $q['marks'] ?? 1,
                'stem' => (string)($q['stem'] ?? ''),
                'options' => isset($q['options']) ? json_encode($q['options'], JSON_UNESCAPED_UNICODE) : null,
                'correct_answer' => $q['correct_answer'] ?? null,
                'explanation' => $q['explanation'] ?? null,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ];
            if (!empty($q['clo_code'])) {
                $insert['clo_code'] = (string)$q['clo_code'];
            }
            if (!empty($q['marking_scheme'])) {
                $insert['marking_scheme'] = (string)$q['marking_scheme'];
            }
            Database::insert('questions', $insert);
        }
        log_ai('questions', compact('type', 'unit', 'klevel', 'count') + ['subject' => $subjectName], $result, 'question_bank', $bankId);
        // Listing-oriented response: omit answers from the client payload (still stored in DB).
        $publicQuestions = array_map(static function (array $q): array {
            unset($q['correct_answer'], $q['explanation']);
            return $q;
        }, $questions);
        json_response([
            'ok' => true,
            'data' => ['bank_id' => $bankId, 'questions' => $publicQuestions],
            'redirect' => base_url('/professor/questions.php?bank_id=' . $bankId),
        ]);
    }

    if ($module === 'ppt') {
        Auth::requireRole('professor', 'admin');
        $title = trim((string)post('title', 'Lecture Presentation'));
        $context = trim((string)post('context', ''));
        $planId = (int)post('plan_id', 0) ?: null;
        $plan = null;
        $subjectName = '';
        $unitTopics = [];
        $parsed = Gemini::parsePresentationSubjectUnit($title, $context);
        $unit = (int)$parsed['unit'];
        $subjectName = (string)$parsed['subject'];

        if ($planId) {
            $plan = load_plan_for_user($planId, $user);
            if (!$plan) {
                json_response(['ok' => false, 'error' => 'Plan not found'], 404);
            }
            try {
                $plan = CoursePlanTools::syncPlanTopicsFromSyllabus($plan);
            } catch (Throwable $e) {
                // Soft-repair failure must not block PPT generation.
            }
            $planSubject = trim((string)($plan['subject_name'] ?? ''));
            if ($planSubject !== '') {
                $subjectName = $planSubject;
            } elseif ($subjectName === '' || strcasecmp($subjectName, 'Course') === 0) {
                $subjectName = trim((string)($plan['title'] ?? 'Course'));
            }
            if ($context === '') {
                $context = trim((string)($plan['syllabus_input'] ?? ''));
            }
            // Re-parse unit from title+context after filling context.
            $parsed = Gemini::parsePresentationSubjectUnit($title, $context);
            $unit = (int)$parsed['unit'];
            if ($planSubject === '' && trim((string)$parsed['subject']) !== '' && strcasecmp((string)$parsed['subject'], 'Course') !== 0) {
                $subjectName = (string)$parsed['subject'];
            }

            $planData = json_decode((string)($plan['plan_data'] ?? ''), true) ?: [];
            foreach (($planData['units'] ?? []) as $u) {
                if ((int)($u['unit_number'] ?? 0) !== $unit) {
                    continue;
                }
                foreach ((array)($u['topics'] ?? []) as $t) {
                    if (is_string($t) && trim($t) !== '' && !preg_match('/^topic\s*\d+(\.\d+)?$/i', $t)) {
                        $unitTopics[] = trim($t);
                    }
                }
            }
            $dbUnits = Database::fetchAll(
                'SELECT title, topics FROM plan_units WHERE plan_id = ? AND unit_number = ?',
                [$planId, $unit]
            );
            foreach ($dbUnits as $u) {
                $topicsJson = json_decode((string)($u['topics'] ?? ''), true);
                if (is_array($topicsJson)) {
                    foreach ($topicsJson as $t) {
                        if (is_string($t) && trim($t) !== '' && !preg_match('/^topic\s*\d+(\.\d+)?$/i', $t)) {
                            $unitTopics[] = trim($t);
                        }
                    }
                }
            }
        }

        if ($subjectName === '') {
            $subjectName = 'Course';
        }

        $unitTopics = LectureSlideBuilder::filterTopics(array_values(array_unique($unitTopics)));
        $unitTopicText = $unitTopics
            ? ("Unit {$unit} syllabus topics (teach these — do NOT treat unit titles/hours as topics):\n- " . implode("\n- ", $unitTopics))
            : "Infer Unit {$unit} teaching topics strictly from the syllabus/context. Never use unit titles like \"… — 12 Hours\" as slide topics.";

        $branding = PresentationTools::brandingForUser($user);
        $deptName = '';
        $deptId = (int)($user['department_id'] ?? 0);
        if ($deptId > 0) {
            $deptRow = Database::fetch(
                'SELECT name FROM departments WHERE id = ? AND institution_id = ?',
                [$deptId, (int)$user['institution_id']]
            );
            $deptName = trim((string)($deptRow['name'] ?? ''));
        }
        $instRow = Database::fetch(
            'SELECT academic_year, current_semester FROM institutions WHERE id = ?',
            [(int)$user['institution_id']]
        );
        $brandMeta = [
            'institution' => (string)($branding['name'] ?? ''),
            'department' => $deptName,
            'academic_year' => trim((string)($instRow['academic_year'] ?? '')),
            'semester' => trim((string)($instRow['current_semester'] ?? '')),
            'course_code' => '',
        ];
        if ($plan && !empty($plan['subject_id'])) {
            $subRow = Database::fetch(
                'SELECT code FROM subjects WHERE id = ? AND institution_id = ?',
                [(int)$plan['subject_id'], (int)$user['institution_id']]
            );
            $brandMeta['course_code'] = trim((string)($subRow['code'] ?? ''));
        }

        $tpl = prompt_template('ppt_gen');
        $system = $tpl['system_prompt']
            ?? 'You are an expert university lecturer preparing a professional classroom PowerPoint. Return ONLY valid JSON.';

        $userPrompt = "Create a professional academic lecture presentation (real teaching content, not placeholders).\n"
            . "Institution: {$brandMeta['institution']}\n"
            . ($deptName !== '' ? "Department: {$deptName}\n" : '')
            . "Presentation title: {$title}\n"
            . "Course/Subject: {$subjectName}\n"
            . "Unit: {$unit} (ALL slides must be for this unit only)\n"
            . "{$unitTopicText}\n\n"
            . "Syllabus / context:\n" . ($context !== '' ? $context : '(Use the course and unit topics above.)') . "\n\n"
            . "Requirements:\n"
            . "- Slide count should fit the syllabus (typically 12–22). Do not pad with empty slides.\n"
            . "- Slide 1: college/institution title slide with subject + unit.\n"
            . "- Slide 2: specific learning objectives (action verbs; no generic \"understand the concepts\").\n"
            . "- Then teach EACH syllabus topic with real explanations, examples, comparisons, or code when useful.\n"
            . "- NEVER create a teaching slide whose title is only a unit heading with hours (e.g. \"WEB FUNDAMENTALS AND HTML — 12 Hours\").\n"
            . "- Do NOT invent unrelated topics outside the syllabus.\n"
            . "- Every bullet must be actual educational content (definitions, mechanisms, examples, differences).\n"
            . "- Forbidden placeholder phrases: \"Why this concept matters\", \"Key terms students must remember\", \"Short example or illustration\", \"Classroom demo or board work suggestion\", \"Quick practice prompt\", \"Core idea of\", \"Step-by-step explanation suitable for classroom teaching\".\n"
            . "- Include a concrete unit summary and specific revision questions near the end; final slide Thank You.\n"
            . "- Optional fields when useful: layout (content|comparison|code|diagram|summary|quiz|close), comparison{headers,rows}, code (string), diagram (string list).\n"
            . "- unit_tag must be exactly \"Unit {$unit}\" for every slide.\n"
            . "- speaker_notes must be useful teaching notes.\n\n"
            . "Return JSON only: {\"slides\":[{\"number\":1,\"title\":\"\",\"layout\":\"content\",\"bullets\":[\"\",\"\"],\"speaker_notes\":\"\",\"unit_tag\":\"Unit {$unit}\"}]}";

        $slides = [];
        $result = ['ok' => true, 'json' => null, 'latency_ms' => 0, 'demo' => false];

        if ($gemini->isConfigured()) {
            $result = $gemini->generate($system, $userPrompt);
            $rawSlides = is_array($result['json']['slides'] ?? null) ? $result['json']['slides'] : [];
            $slides = normalize_generated_slides($rawSlides, $unit);
            if (!ppt_slides_are_usable($slides)) {
                $slides = Gemini::demoPresentation($title, $subjectName, $unit, $context, $unitTopics, 12, $brandMeta);
                $result['demo'] = true;
                $result['fallback'] = 'ai_unusable';
            }
        } else {
            $slides = Gemini::demoPresentation($title, $subjectName, $unit, $context, $unitTopics, 12, $brandMeta);
            $result = [
                'ok' => true,
                'json' => ['slides' => $slides],
                'latency_ms' => 0,
                'demo' => true,
            ];
        }

        $subjectId = $plan ? ((int)($plan['subject_id'] ?? 0) ?: null) : null;
        $pptMeta = [
            'subject' => $subjectName,
            'unit' => $unit,
            'plan_id' => $planId,
            'branding' => $branding,
            'speaker_notes' => true,
        ];
        $pptId = Database::insert('presentations', [
            'plan_id' => $planId,
            'professor_id' => (int)$user['id'],
            'subject_id' => $subjectId,
            'title' => $title,
            'slide_count' => count($slides),
            'slides' => json_encode($slides, JSON_UNESCAPED_UNICODE),
            'status' => 'ready',
            'meta' => json_encode($pptMeta, JSON_UNESCAPED_UNICODE),
        ]);
        log_ai('ppt', [
            'title' => $title,
            'subject' => $subjectName,
            'unit' => $unit,
        ], $result, 'presentation', $pptId);
        json_response(['ok' => true, 'data' => ['id' => $pptId, 'slides' => $slides], 'redirect' => base_url('/professor/ppt-view.php?id=' . $pptId)]);
    }

    if ($module === 'assignment') {
        Auth::requireRole('professor', 'admin');
        AssignmentTools::ensureSchema();
        $type = (string)post('assignment_type', 'essay');
        $subject = (string)post('subject', '');
        $context = (string)post('context', '');
        $classId = (int)post('class_id', 0);
        $subjectId = (int)post('subject_id', 0) ?: null;
        $deadline = post('deadline') ?: null;
        $templateId = (int)post('template_id', 0);
        // Bulk: optional extra class ids (same subject). Primary class_id still required for backward compatibility.
        $bulkRaw = post('bulk_class_ids');
        $bulkIds = [];
        if (is_array($bulkRaw)) {
            foreach ($bulkRaw as $cid) {
                $cid = (int)$cid;
                if ($cid > 0) {
                    $bulkIds[] = $cid;
                }
            }
        }
        if ($classId > 0) {
            $bulkIds[] = $classId;
        }
        $bulkIds = array_values(array_unique($bulkIds));
        if ($bulkIds === []) {
            json_response(['ok' => false, 'error' => 'Select a class (year and section). Only those students will see and submit this assignment.'], 422);
        }
        foreach ($bulkIds as $cid) {
            if (!professor_can_manage_class($user, $cid)) {
                json_response(['ok' => false, 'error' => 'You cannot create assignments for one of the selected classes.'], 422);
            }
            if ($subjectId && !professor_can_manage_subject($user, $subjectId, $cid)) {
                json_response(['ok' => false, 'error' => 'You are not assigned to this course for one of the selected classes.'], 422);
            }
        }
        // Use primary class for generation context (first in list / posted class_id).
        $classId = $classId > 0 ? $classId : $bulkIds[0];

        $data = null;
        $result = ['ok' => true, 'json' => null, 'latency_ms' => 0];

        if ($templateId > 0) {
            $tplRow = AssignmentTools::getTemplate($user, $templateId);
            if (!$tplRow) {
                json_response(['ok' => false, 'error' => 'Template not found.'], 404);
            }
            $data = [
                'title' => (string)$tplRow['title'],
                'description' => (string)($tplRow['description'] ?? ''),
                'instructions' => json_decode((string)($tplRow['instructions'] ?? '[]'), true) ?: [],
                'rubric' => json_decode((string)($tplRow['rubric'] ?? '[]'), true) ?: [],
                'max_marks' => (float)($tplRow['max_marks'] ?? 25),
                'from_template' => true,
            ];
            if ($context === '' && !empty($tplRow['context_text'])) {
                $context = (string)$tplRow['context_text'];
            }
            $type = (string)($tplRow['assignment_type'] ?: $type);
            $result = ['ok' => true, 'json' => $data, 'latency_ms' => 0, 'demo' => false];
        } else {
            $plan = null;
            if ($subjectId) {
                $plan = Database::fetch(
                    'SELECT * FROM course_plans WHERE professor_id = ? AND institution_id = ? AND subject_id = ? ORDER BY id DESC LIMIT 1',
                    [(int)$user['id'], (int)$user['institution_id'], $subjectId]
                );
            }
            $closHint = AssignmentTools::closForSubject($user, (int)($subjectId ?? 0));
            $cloText = $closHint ? ('Available CLOs: ' . implode(', ', $closHint)) : 'Use CLO1, CLO2, … when appropriate.';
            $tpl = prompt_template('assignment_gen');
            if ($gemini->isConfigured()) {
                $result = $gemini->generate(
                    $tpl['system_prompt'] ?? 'Assignment JSON.',
                    "Type:$type Subject:$subject\nContext:\n$context\n{$cloText}\n"
                    . "Return {title,description,instructions[],rubric[{criterion,description,marks,clo,bloom,levels}],max_marks}. "
                    . "Rubric marks must sum exactly to max_marks. bloom like K2/K3. clo like CLO1."
                );
                $data = $result['json'] ?? [];
            } else {
                $data = [
                    'title' => ucwords(str_replace('_', ' ', $type)) . ' Assignment · ' . $subject,
                    'description' => "Complete a $type based on the course outcomes. Demonstrate Bloom K3-K5 skills.",
                    'instructions' => ['Read the brief carefully', 'Cite academic sources', 'Submit before deadline'],
                    'rubric' => [
                        ['criterion' => 'Concept understanding', 'description' => 'Definitions and core ideas', 'marks' => 8, 'clo' => $closHint[0] ?? 'CLO1', 'bloom' => 'K2', 'levels' => 'Excellent/Good/Fair'],
                        ['criterion' => 'Technical accuracy', 'description' => 'Correct methods and syntax', 'marks' => 7, 'clo' => $closHint[1] ?? 'CLO2', 'bloom' => 'K3', 'levels' => 'Excellent/Good/Fair'],
                        ['criterion' => 'Analysis', 'description' => 'Reasoning and evaluation', 'marks' => 6, 'clo' => $closHint[2] ?? 'CLO3', 'bloom' => 'K4', 'levels' => 'Excellent/Good/Fair'],
                        ['criterion' => 'Presentation', 'description' => 'Clarity and structure', 'marks' => 4, 'clo' => $closHint[0] ?? 'CLO1', 'bloom' => 'K2', 'levels' => 'Excellent/Good/Fair'],
                    ],
                    'max_marks' => 25,
                    'demo' => true,
                ];
                $result = ['ok' => true, 'json' => $data, 'latency_ms' => 0];
            }
            $maxMarks = (float)($data['max_marks'] ?? 25);
            $data['rubric'] = AssignmentTools::enrichRubricFromPlan($data['rubric'] ?? [], $plan, $maxMarks);
            // Preserve legacy weight-only rubrics by normalizing; if total still off, leave as-is for professor edit.
            $check = AssignmentTools::validateRubricTotal($data['rubric'], $maxMarks);
            if (!$check['ok'] && !empty($data['rubric'])) {
                // Soft-fix: scale marks to max_marks so published assignments stay usable.
                $sum = AssignmentTools::rubricMarksTotal($data['rubric']);
                if ($sum > 0) {
                    $scaled = [];
                    $alloc = 0.0;
                    $last = count($data['rubric']) - 1;
                    foreach ($data['rubric'] as $i => $r) {
                        if ($i === $last) {
                            $r['marks'] = round(max(0, $maxMarks - $alloc), 2);
                        } else {
                            $r['marks'] = round(((float)$r['marks'] / $sum) * $maxMarks, 2);
                            $alloc += $r['marks'];
                        }
                        $scaled[] = $r;
                    }
                    $data['rubric'] = $scaled;
                }
            }
        }

        $createdIds = [];
        foreach ($bulkIds as $cid) {
            $id = Database::insert('assignments', [
                'institution_id' => (int)$user['institution_id'],
                'professor_id' => (int)$user['id'],
                'subject_id' => $subjectId,
                'class_id' => $cid,
                'title' => (string)($data['title'] ?? 'Assignment'),
                'assignment_type' => $type,
                'description' => (string)($data['description'] ?? ''),
                'rubric' => json_encode($data['rubric'] ?? [], JSON_UNESCAPED_UNICODE),
                'max_marks' => $data['max_marks'] ?? 25,
                'deadline' => $deadline,
                'instructions' => json_encode($data['instructions'] ?? [], JSON_UNESCAPED_UNICODE),
                'ai_generated' => empty($data['from_template']) ? 1 : 0,
                'status' => 'published',
                'meta' => json_encode([
                    'bulk_group' => count($bulkIds) > 1 ? md5(json_encode($bulkIds) . microtime(true)) : null,
                    'from_template_id' => $templateId > 0 ? $templateId : null,
                    'context' => $context,
                ], JSON_UNESCAPED_UNICODE),
            ]);
            $createdIds[] = (int)$id;
            if ($subjectId) {
                enroll_class_students_in_subject((int)$user['institution_id'], $cid, $subjectId);
            }
            log_ai('assignment', compact('type', 'subject') + ['class_id' => $cid], $result, 'assignment', (int)$id);
        }
        $redirectId = $createdIds[0] ?? 0;
        json_response([
            'ok' => true,
            'data' => $data + ['created_ids' => $createdIds],
            'redirect' => base_url('/professor/assignments.php' . ($redirectId ? ('?id=' . $redirectId) : '')),
        ]);
    }

    if ($module === 'formula') {
        Auth::requireRole('admin', 'hod', 'professor');
        $text = trim((string)post('plain_english'));
        $tpl = prompt_template('formula_nlp');
        if ($gemini->isConfigured()) {
            $result = $gemini->generate(
                $tpl['system_prompt'] ?? 'Parse marks formula.',
                "Parse into JSON {name,components:[{code,label,max,weight}],expression,total_max}:\n$text"
            );
            $data = $result['json'] ?? [];
        } else {
            $data = [
                'name' => 'Parsed Formula',
                'components' => [
                    ['code'=>'cia1','label'=>'CIA 1','max'=>50,'weight'=>0.3],
                    ['code'=>'cia2','label'=>'CIA 2','max'=>50,'weight'=>0.3],
                    ['code'=>'assignment','label'=>'Assignment','max'=>5,'weight'=>0.2],
                    ['code'=>'attendance','label'=>'Attendance','max'=>5,'weight'=>0.2],
                ],
                'expression' => '((cia1+cia2)/2)*(15/50)+assignment+attendance',
                'total_max' => 25,
                'demo' => true,
            ];
            $result = ['ok'=>true,'json'=>$data,'latency_ms'=>0];
        }
        log_ai('formula', ['text'=>$text], $result);
        json_response(['ok'=>true,'data'=>$data]);
    }

    if ($module === 'ask_ai') {
        Auth::requireRole('student', 'professor', 'admin');
        Auth::refresh();
        $question = trim((string)post('question'));
        if ($question === '') {
            json_response(['ok'=>false,'error'=>'Question is required.'], 422);
        }

        $isStudent = ($user['role'] ?? '') === 'student';
        $subjectId = (int)post('subject_id', 0);
        $subjectRow = null;
        $materials = '';
        $subjectName = '';
        $otherSubjectNames = [];

        if ($isStudent) {
            if ($subjectId < 1) {
                json_response(['ok'=>false,'error'=>'Please select a subject first.'], 422);
            }
            $subjectRow = resolve_student_ask_ai_subject($user, $subjectId);
            if (!$subjectRow) {
                json_response(['ok'=>false,'error'=>'That subject is not in your current academic context.'], 403);
            }
            $subjectName = trim((string)($subjectRow['name'] ?? ''));
            $materials = build_student_ask_ai_materials($user, $subjectRow);
            foreach (courses_for_student($user) as $s) {
                if ((int)($s['id'] ?? 0) !== $subjectId) {
                    $label = trim((string)($s['name'] ?? ''));
                    if ($label !== '') {
                        $otherSubjectNames[] = $label;
                    }
                }
            }
        } else {
            $subjectId = $subjectId > 0 ? $subjectId : null;
            if ($subjectId) {
                $ownedSubject = Database::fetch(
                    'SELECT id, name FROM subjects WHERE id = ? AND institution_id = ?',
                    [$subjectId, $user['institution_id']]
                );
                if (!$ownedSubject) {
                    $subjectId = null;
                } else {
                    $subjectName = trim((string)($ownedSubject['name'] ?? ''));
                }
            }
            if ($subjectId) {
                $docs = Database::fetchAll(
                    'SELECT d.title, d.content_text FROM documents d
                     JOIN subjects s ON s.id = d.subject_id
                     WHERE d.subject_id = ? AND d.is_published = 1 AND s.institution_id = ? LIMIT 10',
                    [$subjectId, $user['institution_id']]
                );
                foreach ($docs as $d) {
                    $materials .= "\n## {$d['title']}\n" . mb_substr((string)$d['content_text'], 0, 2000) . "\n";
                }
                $plan = Database::fetch(
                    'SELECT plan_data, subject_name, syllabus_input FROM course_plans WHERE subject_id = ? AND status = "approved" AND institution_id = ? ORDER BY id DESC LIMIT 1',
                    [$subjectId, $user['institution_id']]
                );
                if ($plan) {
                    if (trim((string)($plan['syllabus_input'] ?? '')) !== '') {
                        $materials .= "\n## Syllabus {$plan['subject_name']}\n" . trim((string)$plan['syllabus_input']) . "\n";
                    }
                    $materials .= "\n## Approved plan {$plan['subject_name']}\n" . mb_substr((string)$plan['plan_data'], 0, 3000);
                }
            }
            if ($materials === '') {
                $materials = 'No institutional materials found. Answer carefully and state limitations.';
            }
        }

        if ($isStudent) {
            if ($materials === '') {
                $materials = '(No syllabus or published materials on file for this subject yet.)';
            }
            $otherList = $otherSubjectNames ? implode(', ', $otherSubjectNames) : 'none';
            $userPrompt = "SELECTED SUBJECT: {$subjectName}\n"
                . "OTHER CURRENT SUBJECTS (for redirect suggestions only): {$otherList}\n\n"
                . "COURSE MATERIALS:\n{$materials}\n\n"
                . "STUDENT QUESTION:\n{$question}\n\n"
                . 'Return JSON {"answer":"...","citations":[]}';

            if (!$gemini->isConfigured()) {
                $data = answer_student_ask_ai_offline(
                    $question,
                    $subjectName,
                    $materials,
                    $otherSubjectNames,
                    $user
                );
                $result = ['ok' => true, 'json' => $data, 'latency_ms' => 0, 'offline' => true];
            } else {
                $tpl = prompt_template('study_assistant');
                $systemPrompt = ($tpl['system_prompt'] ?? 'Answer using ONLY the provided course materials.')
                    . "\n\nRules:"
                    . "\n- Answer ONLY in the context of the selected subject."
                    . "\n- Use ONLY the provided syllabus, units, topics, outcomes, and materials."
                    . "\n- If the question is unrelated to the selected subject, do NOT answer it. Tell the student which subject they should select instead."
                    . "\n- If materials do not cover the topic, say you do not have enough course material for that topic in the selected subject."
                    . "\n- Never mention demo mode, API keys, placeholders, or generic study advice without subject-specific content."
                    . "\n- Return ONLY valid JSON: {\"answer\":\"...\",\"citations\":[]}";

                $result = $gemini->generate($systemPrompt, $userPrompt);
                $data = $result['json'] ?? ['answer' => trim((string)($result['text'] ?? ''))];
                if (empty($result['ok']) || trim((string)($data['answer'] ?? '')) === '') {
                    $data = answer_student_ask_ai_offline(
                        $question,
                        $subjectName,
                        $materials,
                        $otherSubjectNames,
                        $user,
                        $subjectId
                    );
                    $result = ['ok' => true, 'json' => $data, 'latency_ms' => (int)($result['latency_ms'] ?? 0), 'offline' => true];
                }
            }
        } else {
            if (!$gemini->isConfigured()) {
                json_response(['ok'=>false,'error'=>'The study assistant is temporarily unavailable. Please try again later.'], 503);
            }
            $tpl = prompt_template('study_assistant');
            $systemPrompt = ($tpl['system_prompt'] ?? 'Answer using ONLY the provided course materials.')
                . "\n\nRules:"
                . "\n- Answer ONLY in the context of the selected subject."
                . "\n- Use ONLY the provided syllabus, units, topics, outcomes, and materials."
                . "\n- If the question is unrelated to the selected subject, do NOT answer it. Tell the student which subject they should select instead."
                . "\n- If materials do not cover the topic, say you do not have enough course material for that topic in the selected subject."
                . "\n- Never mention demo mode, API keys, placeholders, or generic study advice without subject-specific content."
                . "\n- Return ONLY valid JSON: {\"answer\":\"...\",\"citations\":[]}";

            $userPrompt = "Materials:\n{$materials}\n\nQuestion: {$question}\nReturn JSON {answer,citations:[]}";
            $result = $gemini->generate($systemPrompt, $userPrompt);
            $data = $result['json'] ?? ['answer' => trim((string)($result['text'] ?? ''))];
            if (trim((string)($data['answer'] ?? '')) === '') {
                json_response(['ok'=>false,'error'=>'The study assistant could not generate an answer. Please try again.'], 500);
            }
        }

        $chatId = (int)post('chat_id', 0);
        if ($chatId) {
            $ownChat = Database::fetch(
                'SELECT id, subject_id FROM ai_chats WHERE id = ? AND user_id = ?',
                [$chatId, $user['id']]
            );
            // Continue only if the open chat belongs to the selected subject.
            if (!$ownChat || ($subjectId && (int)($ownChat['subject_id'] ?? 0) !== (int)$subjectId)) {
                $chatId = 0;
            }
        }
        if (!$chatId) {
            $chatId = Database::insert('ai_chats', [
                'institution_id' => (int)$user['institution_id'],
                'user_id' => (int)$user['id'],
                'subject_id' => $subjectId ?: null,
                'title' => mb_substr($question, 0, 80),
            ]);
        }
        Database::insert('ai_chat_messages', ['chat_id'=>$chatId,'role'=>'user','content'=>$question]);
        Database::insert('ai_chat_messages', [
            'chat_id'=>$chatId,
            'role'=>'assistant',
            'content'=>(string)($data['answer'] ?? ''),
            'citations'=>json_encode($data['citations'] ?? []),
        ]);
        log_ai('ask_ai', compact('question','subjectId'), $result, 'ai_chat', $chatId);
        json_response(['ok'=>true,'data'=>$data,'chat_id'=>$chatId]);
    }

    json_response(['ok'=>false,'error'=>'Unhandled module'], 400);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => config('debug') ? $e->getMessage() : 'Server error'], 500);
}
