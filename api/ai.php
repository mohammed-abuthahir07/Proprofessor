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

function render_plan_html(array $plan, int $planId = 0): string
{
    ob_start();
    $units = $plan['units'] ?? [];
    $bloom = $plan['bloom_distribution'] ?? [];
    ?>
    <div class="grid grid-2">
      <div class="panel">
        <div class="panel-h"><h3><?= e($plan['title'] ?? 'Course Plan') ?></h3>
          <?php if ($planId): ?><a class="btn btn-sm btn-primary" href="<?= e(base_url('/professor/plan-view.php?id='.$planId)) ?>">Open plan</a><?php endif; ?>
        </div>
        <?php if (!empty($plan['demo'])): ?><div class="alert alert-info">Demo mode (no Gemini key). Structure is editable after save.</div><?php endif; ?>
        <p><strong>Outcomes</strong></p>
        <ul><?php foreach (($plan['learning_outcomes'] ?? []) as $o): ?><li><?= e(is_string($o)?$o:json_encode($o)) ?></li><?php endforeach; ?></ul>
        <p><strong>Resources</strong></p>
        <ul><?php foreach (($plan['resources'] ?? []) as $r): ?><li><?= e(is_string($r)?$r:json_encode($r)) ?></li><?php endforeach; ?></ul>
      </div>
      <div class="panel">
        <h3>Bloom's distribution</h3>
        <canvas id="bloomPreview" height="180"></canvas>
        <script>document.addEventListener('DOMContentLoaded',()=>PPAI.renderBloomChart('bloomPreview', <?= json_encode($bloom) ?>));</script>
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
        case 'course_plan': {
            Auth::requireRole('professor', 'admin', 'hod');
            $subject = trim((string)post('subject'));
            $credits = (string)post('credits', '3');
            $university = trim((string)post('university', ''));
            $syllabus = trim((string)post('syllabus'));
            $subjectId = (int)post('subject_id', 0) ?: null;
            $classId = (int)post('class_id', 0) ?: null;
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
            $userPrompt = "Subject: $subject\nCredits: $credits\nUniversity: $university\nSyllabus:\n$syllabus\n\nReturn JSON with keys: title, learning_outcomes[], units[{unit_number,title,hours,topics[],outcomes[],bloom_k_level,teaching_methods[],assessment[]}], weekly_plan[], resources[], expert_advice[], bloom_distribution{K1..K6}, ai_score.";

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

            log_ai('course_plan', compact('subject', 'credits', 'university'), $result);

            if (empty($result['ok']) || !$plan) {
                json_response(['ok' => false, 'error' => $result['error'] ?? 'AI failed'], 500);
            }

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
                'change_note' => 'Initial AI generation',
                'created_by' => (int)$user['id'],
            ]);

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
        Database::update('course_plans', [
            'plan_data' => json_encode($data),
            'bloom_data' => json_encode($data['bloom_distribution'] ?? json_decode($plan['bloom_data'] ?: '{}', true)),
            'resources' => json_encode($data['resources'] ?? []),
            'expert_advice' => json_encode($data['expert_advice'] ?? []),
            'weekly_plan' => json_encode($data['weekly_plan'] ?? []),
            'version' => $newVersion,
            'ai_score' => $data['ai_score'] ?? $plan['ai_score'],
        ], 'id = :id', ['id'=>$planId]);
        Database::insert('course_plan_versions', [
            'plan_id' => $planId,
            'version' => $newVersion,
            'snapshot' => json_encode($data),
            'change_note' => $instruction,
            'created_by' => (int)$user['id'],
        ]);
        log_ai('improve', ['plan_id'=>$planId,'instruction'=>$instruction], $result, 'course_plan', $planId);
        json_response(['ok'=>true,'data'=>$data,'redirect'=>base_url('/professor/plan-view.php?id='.$planId)]);
    }

    if ($module === 'lesson') {
        Auth::requireRole('professor', 'admin');
        $planId = (int)post('plan_id');
        $plan = load_plan_for_user($planId, $user);
        if (!$plan) json_response(['ok'=>false,'error'=>'Plan not found'], 404);
        $fromPlan = lesson_sessions_from_course_plan($plan);
        $tpl = prompt_template('lesson_plan');
        $sessions = [];
        $result = ['ok' => true, 'json' => ['sessions' => $fromPlan], 'latency_ms' => 0];
        if ($gemini->isConfigured()) {
            $system = trim((string)($tpl['system_prompt'] ?? 'Generate session-by-session lesson plans from the course plan.'));
            $system .= "\nReturn ONLY JSON: {\"sessions\":[{\"session_number\":1,\"title\":\"specific topic\",\"duration_mins\":60,\"objectives\":[\"...\"],\"teaching_method\":\"one method\",\"activities\":[\"classroom activity\"],\"formative_assessment\":[\"check for understanding\"],\"engagement\":[\"student engagement strategy\"]}]}. Every session must include all of those fields. Do not return weekly_plan or empty titles.";
            $userPrompt = "Create 50–60 minute classroom sessions from this course plan (about 2 sessions per unit, maximum 16). Use the unit titles and topics. Subject: " . (string)($plan['subject_name'] ?? '') . "\n\n" . (string)$plan['plan_data'];
            $result = $gemini->generate($system, $userPrompt);
            $sessions = extract_ai_lesson_sessions(is_array($result['json'] ?? null) ? $result['json'] : null);
        }
        if (!lesson_sessions_are_usable($sessions)) {
            $sessions = $fromPlan;
        }
        Database::query('DELETE FROM lesson_plans WHERE plan_id = ?', [$planId]);
        foreach ($sessions as $i => $s) {
            $n = (int)($s['session_number'] ?? ($i + 1));
            Database::insert('lesson_plans', [
                'plan_id' => $planId,
                'professor_id' => (int)$user['id'],
                'unit_id' => $s['unit_id'] ?? null,
                'session_number' => $n,
                'title' => (string)($s['title'] ?? ('Session ' . $n)),
                'duration_mins' => (int)($s['duration_mins'] ?? 60),
                'objectives' => json_encode($s['objectives'] ?? []),
                'teaching_method' => $s['teaching_method'] ?: null,
                'activities' => json_encode($s['activities'] ?? []),
                'formative_assessment' => json_encode($s['formative_assessment'] ?? []),
                'engagement' => json_encode($s['engagement'] ?? []),
                'content' => json_encode($s),
            ]);
        }
        log_ai('lesson', ['plan_id'=>$planId], $result, 'course_plan', $planId);
        json_response(['ok'=>true,'data'=>['sessions'=>$sessions],'redirect'=>base_url('/professor/lessons.php?plan_id='.$planId)]);
    }

    if ($module === 'questions') {
        Auth::requireRole('professor', 'admin');
        $type = (string)post('question_type', 'mcq');
        $unit = (string)post('unit', '1');
        $klevel = (string)post('klevel', 'K2');
        $count = max(1, min(20, (int)post('count', 5)));
        $context = (string)post('context', '');
        $planId = (int)post('plan_id', 0) ?: null;
        if ($planId && !load_plan_for_user($planId, $user)) {
            json_response(['ok' => false, 'error' => 'Course plan not found.'], 404);
        }
        $tpl = prompt_template('question_bank');
        if ($gemini->isConfigured()) {
            $result = $gemini->generate(
                $tpl['system_prompt'] ?? 'Generate questions JSON.',
                "Type:$type Unit:$unit K-level:$klevel Count:$count\nContext:\n$context\nReturn {questions:[{stem,options?,correct_answer,explanation,marks,difficulty,bloom_k_level,unit_number}]}"
            );
            $questions = $result['json']['questions'] ?? [];
        } else {
            $questions = [];
            for ($i=1;$i<=$count;$i++) {
                $q = [
                    'stem' => strtoupper($type) . " Q$i ($klevel): Explain / choose concept $i from unit $unit.",
                    'bloom_k_level' => $klevel,
                    'unit_number' => (int)$unit,
                    'marks' => $type === 'mcq' ? 1 : ($type === 'short' ? 5 : 10),
                    'difficulty' => 'medium',
                    'correct_answer' => $type === 'mcq' ? 'A' : 'Model answer outline-',
                    'explanation' => 'Aligned to Bloom ' . $klevel,
                ];
                if ($type === 'mcq') {
                    $q['options'] = ['A'=>'Option A','B'=>'Option B','C'=>'Option C','D'=>'Option D'];
                }
                $questions[] = $q;
            }
            $result = ['ok'=>true,'json'=>['questions'=>$questions],'latency_ms'=>0];
        }
        $bankId = Database::insert('question_banks', [
            'plan_id' => $planId,
            'professor_id' => (int)$user['id'],
            'title' => strtoupper($type) . " · Unit $unit · $klevel",
            'config' => json_encode(compact('type','unit','klevel','count')),
        ]);
        foreach ($questions as $q) {
            Database::insert('questions', [
                'bank_id' => $bankId,
                'unit_number' => $q['unit_number'] ?? (int)$unit,
                'question_type' => in_array($type, ['mcq','short','long','essay','case'], true) ? $type : 'mcq',
                'bloom_k_level' => $q['bloom_k_level'] ?? $klevel,
                'difficulty' => $q['difficulty'] ?? 'medium',
                'marks' => $q['marks'] ?? 1,
                'stem' => (string)($q['stem'] ?? ''),
                'options' => isset($q['options']) ? json_encode($q['options']) : null,
                'correct_answer' => $q['correct_answer'] ?? null,
                'explanation' => $q['explanation'] ?? null,
            ]);
        }
        log_ai('questions', compact('type','unit','klevel','count'), $result, 'question_bank', $bankId);
        json_response(['ok'=>true,'data'=>['bank_id'=>$bankId,'questions'=>$questions],'redirect'=>base_url('/professor/questions.php?bank_id='.$bankId)]);
    }

    if ($module === 'ppt') {
        Auth::requireRole('professor', 'admin');
        $title = trim((string)post('title', 'Lecture Presentation'));
        $context = (string)post('context', '');
        $planId = (int)post('plan_id', 0) ?: null;
        $tpl = prompt_template('ppt_gen');
        if ($gemini->isConfigured()) {
            $result = $gemini->generate(
                $tpl['system_prompt'] ?? 'PPT JSON slides.',
                "Title: $title\nContext:\n$context\nReturn {slides:[{number,title,bullets[],speaker_notes,unit_tag}]}"
            );
            $slides = $result['json']['slides'] ?? [];
        } else {
            $slides = [];
            for ($i=1;$i<=12;$i++) {
                $slides[] = [
                    'number'=>$i,
                    'title'=>$i===1 ? $title : "Topic slide $i",
                    'bullets'=>["Point A$i","Point B$i","Point C$i"],
                    'speaker_notes'=>"Talking points for slide $i",
                    'unit_tag'=>'Unit '.ceil($i/3),
                ];
            }
            $result = ['ok'=>true,'json'=>['slides'=>$slides],'latency_ms'=>0];
        }
        $subjectId = null;
        if ($planId) {
            $linkedPlan = load_plan_for_user($planId, $user);
            if (!$linkedPlan) {
                json_response(['ok'=>false,'error'=>'Plan not found'], 404);
            }
            $subjectId = (int)($linkedPlan['subject_id'] ?? 0) ?: null;
        }
        $pptId = Database::insert('presentations', [
            'plan_id' => $planId,
            'professor_id' => (int)$user['id'],
            'subject_id' => $subjectId,
            'title' => $title,
            'slide_count' => count($slides),
            'slides' => json_encode($slides),
            'status' => 'ready',
        ]);
        log_ai('ppt', compact('title'), $result, 'presentation', $pptId);
        json_response(['ok'=>true,'data'=>['id'=>$pptId,'slides'=>$slides],'redirect'=>base_url('/professor/ppt-view.php?id='.$pptId)]);
    }

    if ($module === 'assignment') {
        Auth::requireRole('professor', 'admin');
        $type = (string)post('assignment_type', 'essay');
        $subject = (string)post('subject', '');
        $context = (string)post('context', '');
        $classId = (int)post('class_id', 0);
        $subjectId = (int)post('subject_id', 0) ?: null;
        $deadline = post('deadline') ?: null;
        if ($classId < 1 || !professor_can_manage_class($user, $classId)) {
            json_response(['ok' => false, 'error' => 'Select a class (year and section). Only those students will see and submit this assignment.'], 422);
        }
        if ($subjectId && !professor_can_manage_subject($user, $subjectId, $classId)) {
            json_response(['ok' => false, 'error' => 'You are not assigned to this course for the selected class.'], 422);
        }
        $tpl = prompt_template('assignment_gen');
        if ($gemini->isConfigured()) {
            $result = $gemini->generate(
                $tpl['system_prompt'] ?? 'Assignment JSON.',
                "Type:$type Subject:$subject\nContext:\n$context\nReturn {title,description,instructions[],rubric[{criterion,weight,levels}],max_marks}"
            );
            $data = $result['json'] ?? [];
        } else {
            $data = [
                'title' => ucwords(str_replace('_',' ', $type)) . ' Assignment · ' . $subject,
                'description' => "Complete a $type based on the course outcomes. Demonstrate Bloom K3-K5 skills.",
                'instructions' => ['Read the brief carefully','Cite academic sources','Submit before deadline'],
                'rubric' => [
                    ['criterion'=>'Content quality','weight'=>40,'levels'=>'Excellent/Good/Fair'],
                    ['criterion'=>'Analysis','weight'=>30,'levels'=>'Excellent/Good/Fair'],
                    ['criterion'=>'Structure & referencing','weight'=>30,'levels'=>'Excellent/Good/Fair'],
                ],
                'max_marks' => 25,
                'demo' => true,
            ];
            $result = ['ok'=>true,'json'=>$data,'latency_ms'=>0];
        }
        $id = Database::insert('assignments', [
            'institution_id' => (int)$user['institution_id'],
            'professor_id' => (int)$user['id'],
            'subject_id' => $subjectId,
            'class_id' => $classId,
            'title' => (string)($data['title'] ?? 'Assignment'),
            'assignment_type' => $type,
            'description' => (string)($data['description'] ?? ''),
            'rubric' => json_encode($data['rubric'] ?? []),
            'max_marks' => $data['max_marks'] ?? 25,
            'deadline' => $deadline,
            'instructions' => json_encode($data['instructions'] ?? []),
            'ai_generated' => 1,
            'status' => 'published',
        ]);
        if ($subjectId) {
            enroll_class_students_in_subject((int)$user['institution_id'], $classId, $subjectId);
        }
        log_ai('assignment', compact('type','subject'), $result, 'assignment', $id);
        json_response(['ok'=>true,'data'=>$data,'redirect'=>base_url('/professor/assignments.php')]);
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
        $question = trim((string)post('question'));
        if ($question === '') {
            json_response(['ok'=>false,'error'=>'Question is required.'], 422);
        }
        $subjectId = (int)post('subject_id', 0) ?: null;
        if ($subjectId) {
            if (($user['role'] ?? '') === 'student') {
                $enrolled = Database::fetch(
                    'SELECT id FROM enrollments WHERE student_id = ? AND subject_id = ? LIMIT 1',
                    [$user['id'], $subjectId]
                );
                if (!$enrolled) {
                    $subjectId = null;
                }
            } else {
                $ownedSubject = Database::fetch(
                    'SELECT id FROM subjects WHERE id = ? AND institution_id = ?',
                    [$subjectId, $user['institution_id']]
                );
                if (!$ownedSubject) {
                    $subjectId = null;
                }
            }
        }
        $materials = '';
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
                'SELECT plan_data, subject_name FROM course_plans WHERE subject_id = ? AND status = "approved" AND institution_id = ? ORDER BY id DESC LIMIT 1',
                [$subjectId, $user['institution_id']]
            );
            if ($plan) $materials .= "\n## Approved plan {$plan['subject_name']}\n" . mb_substr((string)$plan['plan_data'], 0, 3000);
        }
        if ($materials === '') {
            $materials = 'No institutional materials found. Answer carefully and state limitations.';
        }
        $tpl = prompt_template('study_assistant');
        if ($gemini->isConfigured()) {
            $result = $gemini->generate(
                $tpl['system_prompt'] ?? 'Answer from materials.',
                "Materials:\n$materials\n\nQuestion: $question\nReturn JSON {answer,citations:[]}"
            );
            $data = $result['json'] ?? ['answer' => $result['text'] ?? ''];
        } else {
            $data = [
                'answer' => "Based on available course context: focus on core definitions, examples, and Bloom-aligned practice. (Demo mode · add Gemini API key for grounded answers.)\n\nYour question: $question",
                'citations' => ['Local course materials'],
                'demo' => true,
            ];
            $result = ['ok'=>true,'json'=>$data,'latency_ms'=>0];
        }
        $chatId = (int)post('chat_id', 0);
        if ($chatId) {
            $ownChat = Database::fetch('SELECT id FROM ai_chats WHERE id = ? AND user_id = ?', [$chatId, $user['id']]);
            if (!$ownChat) {
                $chatId = 0;
            }
        }
        if (!$chatId) {
            $chatId = Database::insert('ai_chats', [
                'institution_id' => (int)$user['institution_id'],
                'user_id' => (int)$user['id'],
                'subject_id' => $subjectId,
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
