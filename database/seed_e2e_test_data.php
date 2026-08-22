<?php
declare(strict_types=1);

/**
 * DESTRUCTIVE local-dev reset + E2E test seed for ProProfessor AI.
 *
 * 1) Clears all demo/application data (same safety gates as reset_dev_data.php)
 * 2) Preserves Admin users.id = 1 (email, password_hash, role unchanged)
 * 3) Preserves feature_flags, ai_prompt_templates, institution_features
 * 4) Seeds a clean academic dataset for Admin → HOD → Professor → Student testing
 *
 * Usage (CLI only):
 *   php database/seed_e2e_test_data.php --confirm-local-reset
 *
 * Dummy account password: Test@12345 (bcrypt, app-native)
 * Admin password: UNCHANGED
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "REFUSED: this script may only run from the command line.\n");
    exit(1);
}

$confirmed = in_array('--confirm-local-reset', $argv, true);
if (!$confirmed) {
    fwrite(STDERR, <<<TXT
DESTRUCTIVE local reset + E2E seed — refused.

Re-run with:
  php database/seed_e2e_test_data.php --confirm-local-reset

TXT);
    exit(1);
}

$configFile = dirname(__DIR__) . '/config/config.php';
$localFile = dirname(__DIR__) . '/config/config.local.php';
$config = require $configFile;
if (is_file($localFile)) {
    $override = require $localFile;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

$env = strtolower((string)($config['env'] ?? ''));
$db = $config['db'] ?? [];
$host = strtolower((string)($db['host'] ?? ''));
$name = (string)($db['name'] ?? '');

$allowedEnv = ['local', 'dev', 'development'];
$allowedHost = ['127.0.0.1', 'localhost', '::1'];

if (!in_array($env, $allowedEnv, true)) {
    fwrite(STDERR, "REFUSED: config env '{$env}' is not local/dev.\n");
    exit(1);
}
if (!in_array($host, $allowedHost, true)) {
    fwrite(STDERR, "REFUSED: database host '{$host}' is not loopback.\n");
    exit(1);
}
if ($name !== 'proprofessor') {
    fwrite(STDERR, "REFUSED: database name '{$name}' is not proposofessor.\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    (int)$db['port'],
    $db['name'],
    $db['charset'] ?? 'utf8mb4'
);
$pdo = new PDO($dsn, (string)$db['user'], (string)($db['pass'] ?? ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$actualDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($actualDb !== 'proprofessor') {
    fwrite(STDERR, "REFUSED: connected database is '{$actualDb}', not proposofessor.\n");
    exit(1);
}

$adminSql = <<<SQL
SELECT `id`, `institution_id`, `department_id`, `role`, `email`, `password_hash`,
       `full_name`, `employee_id`, `register_no`, `phone`, `avatar_url`, `class_id`,
       `designation`, `preferences`, `extra`, `is_active`, `last_login_at`, `created_at`
FROM `users` WHERE `id` = 1 LIMIT 1
SQL;
$adminBefore = $pdo->query($adminSql)->fetch();
if (
    !$adminBefore
    || $adminBefore['email'] !== 'admin@proprofessor.local'
    || $adminBefore['role'] !== 'admin'
    || $adminBefore['full_name'] !== 'College Admin'
) {
    fwrite(STDERR, "REFUSED: protected Admin users.id=1 / admin@proprofessor.local was not found.\n");
    exit(1);
}

$hashBefore = (string)$adminBefore['password_hash'];
$instId = (int)$adminBefore['institution_id'];
if ($instId < 1) {
    $instId = 1;
}

$applicationTables = [
    'ai_chat_messages',
    'assignment_submissions',
    'attendance_records',
    'document_chunks',
    'questions',
    'course_plan_versions',
    'plan_units',
    'plan_reviews',
    'lesson_plans',
    'presentations',
    'question_banks',
    'assignments',
    'attendance_sessions',
    'internal_marks',
    'enrollments',
    'subject_assignments',
    'students_roster',
    'notifications',
    'password_resets',
    'activity_logs',
    'ai_chats',
    'ai_generations',
    'announcements',
    'academic_events',
    'documents',
    'expenses',
    'budgets',
    'expense_categories',
    'compliance_alerts',
    'course_plans',
    'marks_formulas',
    'subjects',
    'classes',
    'programs',
    'departments',
];

$countBefore = [];
foreach ($applicationTables as $t) {
    $countBefore[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
}
$usersBefore = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$deletedEstimate = array_sum($countBefore) + max(0, $usersBefore - 1);

echo "=== RESET + E2E SEED ===\n";
echo "Admin preserved: id=1 email=admin@proprofessor.local hash-prefix=" . substr($hashBefore, 0, 12) . "...\n";
echo "Estimated rows to clear (app tables + non-admin users): {$deletedEstimate}\n";
echo "Executing...\n";

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($applicationTables as $table) {
        $pdo->exec("DELETE FROM `{$table}`");
    }
    $pdo->exec('DELETE FROM `app_settings` WHERE `institution_id` IS NOT NULL');
    $pdo->exec('DELETE FROM `users` WHERE `id` <> 1');
    $pdo->exec('DELETE FROM `institutions` WHERE `id` <> ' . (int)$instId);

    foreach ($applicationTables as $table) {
        $pdo->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
    }
    $pdo->exec('ALTER TABLE `app_settings` AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE `users` AUTO_INCREMENT = 2');

    // ---- Institution ----
    $pdo->prepare(
        'UPDATE institutions SET
            name = ?, code = ?, affiliation_university = ?, naac_grade = ?, nba_status = ?,
            address = ?, city = ?, state = ?, pincode = ?, phone = ?, email = ?,
            subscription_tier = ?, licensed_seats = ?, academic_year = ?, current_semester = ?,
            settings = ?, is_active = 1
         WHERE id = ?'
    )->execute([
        'ProProfessor Demo College',
        'PPC001',
        'Anna University',
        'A+',
        'Accredited',
        'Demo Campus, Academic Road',
        'Chennai',
        'Tamil Nadu',
        '600001',
        '044-40000000',
        'office@ppcdemo.local',
        'professional',
        200,
        '2025-26',
        'Odd Semester',
        json_encode(['attendance_min' => 75], JSON_UNESCAPED_UNICODE),
        $instId,
    ]);
    $pdo->prepare('UPDATE users SET institution_id = ?, department_id = NULL, class_id = NULL WHERE id = 1')
        ->execute([$instId]);

    $passHash = password_hash('Test@12345', PASSWORD_BCRYPT);
    $ay = '2025-26';
    $sem = 'Odd Semester';

    $insUser = $pdo->prepare(
        'INSERT INTO users
         (institution_id, department_id, role, email, password_hash, full_name, employee_id, register_no, phone, class_id, designation, is_active)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,1)'
    );
    $insDept = $pdo->prepare(
        'INSERT INTO departments (institution_id, name, code, is_active) VALUES (?,?,?,1)'
    );
    $insProg = $pdo->prepare(
        'INSERT INTO programs (institution_id, department_id, name, code, level, duration_years, is_active)
         VALUES (?,?,?,?,?,?,1)'
    );
    $insClass = $pdo->prepare(
        'INSERT INTO classes (institution_id, department_id, program_id, name, section, year, semester, academic_year, meta, is_active)
         VALUES (?,?,?,?,?,?,?,?,?,1)'
    );
    $insSubj = $pdo->prepare(
        'INSERT INTO subjects (institution_id, department_id, code, name, credits, contact_hours, semester, syllabus_text, is_active)
         VALUES (?,?,?,?,?,?,?,?,1)'
    );
    $insAssign = $pdo->prepare(
        'INSERT INTO subject_assignments (subject_id, professor_id, class_id, academic_year, semester)
         VALUES (?,?,?,?,?)'
    );
    $insEnroll = $pdo->prepare(
        'INSERT INTO enrollments (student_id, subject_id, class_id, academic_year, semester, status)
         VALUES (?,?,?,?,?,"active")'
    );
    $insRoster = $pdo->prepare(
        'INSERT INTO students_roster (institution_id, class_id, user_id, register_no, full_name, email, is_active)
         VALUES (?,?,?,?,?,?,1)'
    );

    // ---- Departments ----
    $deptDefs = [
        'CSE' => 'Computer Science and Engineering',
        'ECE' => 'Electronics and Communication Engineering',
        'EEE' => 'Electrical and Electronics Engineering',
        'IT' => 'Information Technology',
        'MECH' => 'Mechanical Engineering',
    ];
    $depts = [];
    foreach ($deptDefs as $code => $dname) {
        $insDept->execute([$instId, $dname, $code]);
        $depts[$code] = (int)$pdo->lastInsertId();
    }

    // ---- Programs (UG/PG where schema supports) ----
    $programs = [];
    foreach (['CSE', 'ECE', 'EEE', 'IT', 'MECH'] as $code) {
        $insProg->execute([$instId, $depts[$code], "B.E. {$deptDefs[$code]}", "BE-{$code}", 'UG', 4.0]);
        $programs[$code]['UG'] = (int)$pdo->lastInsertId();
    }
    $insProg->execute([$instId, $depts['CSE'], 'M.E. Computer Science and Engineering', 'ME-CSE', 'PG', 2.0]);
    $programs['CSE']['PG'] = (int)$pdo->lastInsertId();

    // ---- Classes ----
    $classMap = []; // key: CODE|level|year|section
    $cseClassSpecs = [
        ['UG', 1, 'A'], ['UG', 1, 'B'],
        ['UG', 2, 'A'], ['UG', 2, 'B'],
        ['UG', 3, 'A'], ['UG', 4, 'A'],
        ['PG', 1, 'A'],
    ];
    foreach ($cseClassSpecs as [$level, $year, $sec]) {
        $progId = $programs['CSE'][$level];
        $label = $level === 'PG' ? 'CSE-PG' : 'CSE';
        $meta = json_encode(['level' => $level], JSON_UNESCAPED_UNICODE);
        $insClass->execute([$instId, $depts['CSE'], $progId, $label, $sec, $year, $sem, $ay, $meta]);
        $classMap["CSE|{$level}|{$year}|{$sec}"] = (int)$pdo->lastInsertId();
    }
    // Isolation classes for other depts
    foreach (['ECE', 'EEE', 'IT', 'MECH'] as $code) {
        $meta = json_encode(['level' => 'UG'], JSON_UNESCAPED_UNICODE);
        $insClass->execute([$instId, $depts[$code], $programs[$code]['UG'], $code, 'A', 1, $sem, $ay, $meta]);
        $classMap["{$code}|UG|1|A"] = (int)$pdo->lastInsertId();
    }

    // ---- HODs ----
    $hodEmails = [
        'CSE' => ['CSE HOD', 'csehod@test.com', 'HODCSE01'],
        'ECE' => ['ECE HOD', 'ecehod@test.com', 'HODECE01'],
        'EEE' => ['EEE HOD', 'eeehod@test.com', 'HODEEE01'],
        'IT' => ['IT HOD', 'ithod@test.com', 'HODIT01'],
        'MECH' => ['MECH HOD', 'mechhod@test.com', 'HODMECH01'],
    ];
    $hods = [];
    foreach ($hodEmails as $code => [$fname, $email, $emp]) {
        $insUser->execute([$instId, $depts[$code], 'hod', $email, $passHash, $fname, $emp, null, '9000000001', null, 'Head of Department']);
        $hods[$code] = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE departments SET hod_user_id = ? WHERE id = ?')->execute([$hods[$code], $depts[$code]]);
    }

    // ---- CSE Professors ----
    $profDefs = [
        ['Arun Kumar', 'arun.kumar@test.com', 'PROFCS01', 'Database Management Systems', 'CS301', 'Unit 1: Intro to DBMS\nUnit 2: ER Model\nUnit 3: Normalization\nUnit 4: SQL\nUnit 5: Transactions'],
        ['Priya Kumar', 'priya.kumar@test.com', 'PROFCS02', 'Operating Systems', 'CS302', 'Unit 1: OS Concepts\nUnit 2: Processes\nUnit 3: Scheduling\nUnit 4: Memory\nUnit 5: File Systems'],
        ['Rahul Kumar', 'rahul.kumar@test.com', 'PROFCS03', 'Computer Networks', 'CS303', 'Unit 1: Network Models\nUnit 2: Data Link\nUnit 3: Network Layer\nUnit 4: Transport\nUnit 5: Application'],
        ['Divya Kumar', 'divya.kumar@test.com', 'PROFCS04', 'Java Programming', 'CS304', 'Unit 1: Java Basics\nUnit 2: OOP\nUnit 3: Collections\nUnit 4: Exception Handling\nUnit 5: JDBC'],
        ['Karthik Kumar', 'karthik.kumar@test.com', 'PROFCS05', 'Software Engineering', 'CS305', 'Unit 1: SDLC\nUnit 2: Requirements\nUnit 3: Design\nUnit 4: Testing\nUnit 5: Maintenance'],
    ];
    $profs = [];
    $subjects = [];
    foreach ($profDefs as $i => [$fname, $email, $emp, $subjName, $subjCode, $syllabus]) {
        $insUser->execute([$instId, $depts['CSE'], 'professor', $email, $passHash, $fname, $emp, null, '900000010' . ($i + 1), null, 'Assistant Professor']);
        $profs[$subjCode] = (int)$pdo->lastInsertId();
        $insSubj->execute([$instId, $depts['CSE'], $subjCode, $subjName, 4.0, 60, $sem, $syllabus]);
        $subjects[$subjCode] = (int)$pdo->lastInsertId();
    }

    // Isolation professors + subjects
    $isoProfs = [
        'ECE' => ['Anita ECE', 'anita.ece@test.com', 'PROFECE01', 'EC201', 'Digital Electronics'],
        'EEE' => ['Bala EEE', 'bala.eee@test.com', 'PROFEEE01', 'EE201', 'Circuit Theory'],
        'IT' => ['Chitra IT', 'chitra.it@test.com', 'PROFIT01', 'IT201', 'Web Technologies'],
        'MECH' => ['Deepak MECH', 'deepak.mech@test.com', 'PROFMECH01', 'ME201', 'Engineering Mechanics'],
    ];
    foreach ($isoProfs as $code => [$fname, $email, $emp, $scode, $sname]) {
        $insUser->execute([$instId, $depts[$code], 'professor', $email, $passHash, $fname, $emp, null, '9000000200', null, 'Assistant Professor']);
        $profs[$scode] = (int)$pdo->lastInsertId();
        $insSubj->execute([$instId, $depts[$code], $scode, $sname, 3.0, 45, $sem, "Unit 1: Foundations of {$sname}"]);
        $subjects[$scode] = (int)$pdo->lastInsertId();
        $cls = $classMap["{$code}|UG|1|A"];
        $insAssign->execute([$subjects[$scode], $profs[$scode], $cls, $ay, $sem]);
    }

    // Assign CSE subjects to Year-1 A (primary) + also OS to Year-2 A for isolation tests
    $y1a = $classMap['CSE|UG|1|A'];
    $y1b = $classMap['CSE|UG|1|B'];
    $y2a = $classMap['CSE|UG|2|A'];
    foreach (['CS301', 'CS302', 'CS303', 'CS304', 'CS305'] as $scode) {
        $insAssign->execute([$subjects[$scode], $profs[$scode], $y1a, $ay, $sem]);
    }
    $insAssign->execute([$subjects['CS302'], $profs['CS302'], $y2a, $ay, $sem]);
    $insAssign->execute([$subjects['CS301'], $profs['CS301'], $y1b, $ay, $sem]);

    // ---- Students ----
    $studentDefs = [
        // CSE UG 1A
        ['Mohammed Abuthahir', 'mohammed@test.com', 'CSE24001', 'CSE|UG|1|A'],
        ['Ananya Sharma', 'ananya@test.com', 'CSE24002', 'CSE|UG|1|A'],
        ['Rohan Das', 'rohan@test.com', 'CSE24003', 'CSE|UG|1|A'],
        // CSE UG 1B
        ['Arjun Kumar', 'arjun@test.com', 'CSE24011', 'CSE|UG|1|B'],
        ['Sneha Ravi', 'sneha@test.com', 'CSE24012', 'CSE|UG|1|B'],
        // CSE UG 2A
        ['Hari Prasad', 'hari@test.com', 'CSE23001', 'CSE|UG|2|A'],
        ['Nisha Kumar', 'nisha@test.com', 'CSE23002', 'CSE|UG|2|A'],
        // CSE UG 2B
        ['Vijay Kumar', 'vijay@test.com', 'CSE23011', 'CSE|UG|2|B'],
        ['Divya Raj', 'divyaraj@test.com', 'CSE23012', 'CSE|UG|2|B'],
        // CSE UG 3A
        ['Sanjay Kumar', 'sanjay@test.com', 'CSE22001', 'CSE|UG|3|A'],
        ['Meena Devi', 'meena@test.com', 'CSE22002', 'CSE|UG|3|A'],
        // CSE UG 4A
        ['Akash Kumar', 'akash@test.com', 'CSE21001', 'CSE|UG|4|A'],
        ['Priyanka S', 'priyanka@test.com', 'CSE21002', 'CSE|UG|4|A'],
        // CSE PG 1A
        ['Lakshmi PG', 'lakshmi.pg@test.com', 'CSEP25001', 'CSE|PG|1|A'],
        // Other depts (isolation)
        ['Ece Student', 'ece.student@test.com', 'ECE24001', 'ECE|UG|1|A'],
        ['Eee Student', 'eee.student@test.com', 'EEE24001', 'EEE|UG|1|A'],
        ['It Student', 'it.student@test.com', 'IT24001', 'IT|UG|1|A'],
        ['Mech Student', 'mech.student@test.com', 'MECH24001', 'MECH|UG|1|A'],
    ];

    $students = []; // register => [id, classKey, classId, name, email, deptCode]
    $n = 0;
    foreach ($studentDefs as [$fname, $email, $reg, $ckey]) {
        $n++;
        [$dcode] = explode('|', $ckey);
        $clsId = $classMap[$ckey];
        $insUser->execute([
            $instId, $depts[$dcode], 'student', $email, $passHash, $fname,
            null, $reg, '91' . str_pad((string)(8000000000 + $n), 10, '0', STR_PAD_LEFT),
            $clsId, null,
        ]);
        $sid = (int)$pdo->lastInsertId();
        $students[$reg] = [
            'id' => $sid,
            'class_key' => $ckey,
            'class_id' => $clsId,
            'name' => $fname,
            'email' => $email,
            'dept' => $dcode,
        ];
        $insRoster->execute([$instId, $clsId, $sid, $reg, $fname, $email]);
    }

    // Enroll CSE 1A into all CSE subjects; 1B into DBMS; 2A into OS
    $cse1aRegs = ['CSE24001', 'CSE24002', 'CSE24003'];
    $cse1bRegs = ['CSE24011', 'CSE24012'];
    $cse2aRegs = ['CSE23001', 'CSE23002'];
    foreach ($cse1aRegs as $reg) {
        foreach (['CS301', 'CS302', 'CS303', 'CS304', 'CS305'] as $scode) {
            $insEnroll->execute([$students[$reg]['id'], $subjects[$scode], $y1a, $ay, $sem]);
        }
    }
    foreach ($cse1bRegs as $reg) {
        $insEnroll->execute([$students[$reg]['id'], $subjects['CS301'], $y1b, $ay, $sem]);
    }
    foreach ($cse2aRegs as $reg) {
        $insEnroll->execute([$students[$reg]['id'], $subjects['CS302'], $y2a, $ay, $sem]);
    }
    // Isolation enrollments
    $insEnroll->execute([$students['ECE24001']['id'], $subjects['EC201'], $classMap['ECE|UG|1|A'], $ay, $sem]);
    $insEnroll->execute([$students['EEE24001']['id'], $subjects['EE201'], $classMap['EEE|UG|1|A'], $ay, $sem]);
    $insEnroll->execute([$students['IT24001']['id'], $subjects['IT201'], $classMap['IT|UG|1|A'], $ay, $sem]);
    $insEnroll->execute([$students['MECH24001']['id'], $subjects['ME201'], $classMap['MECH|UG|1|A'], $ay, $sem]);

    // ---- Marks formula ----
    $components = json_encode([
        ['code' => 'cia1', 'label' => 'CIA 1', 'max' => 50, 'weight' => 0.3],
        ['code' => 'cia2', 'label' => 'CIA 2', 'max' => 50, 'weight' => 0.3],
        ['code' => 'assignment', 'label' => 'Assignment', 'max' => 5, 'weight' => 0.2],
        ['code' => 'attendance', 'label' => 'Attendance', 'max' => 5, 'weight' => 0.2],
    ], JSON_UNESCAPED_UNICODE);
    $pdo->prepare(
        'INSERT INTO marks_formulas
         (institution_id, department_id, name, pattern, plain_english, components, expression, total_max, is_default, created_by)
         VALUES (?,?,?,?,?,?,?,?,1,?)'
    )->execute([
        $instId,
        $depts['CSE'],
        'CBCS Internal 25',
        'CBCS',
        'Average of CIA 1 and CIA 2 scaled to 15, plus assignment and attendance to 25.',
        $components,
        '((cia1+cia2)/2)*(15/50)+assignment+attendance',
        25,
        1,
    ]);
    $formulaId = (int)$pdo->lastInsertId();

    // ---- Course plans (statuses: draft/submitted/under_review/approved/returned) ----
    $bloom = json_encode(['K1' => 15, 'K2' => 20, 'K3' => 25, 'K4' => 20, 'K5' => 12, 'K6' => 8], JSON_UNESCAPED_UNICODE);
    $weekly = json_encode([
        ['week' => 1, 'focus' => 'Orientation & Unit 1'],
        ['week' => 2, 'focus' => 'Unit 1 continued'],
        ['week' => 3, 'focus' => 'Unit 2'],
    ], JSON_UNESCAPED_UNICODE);
    $resources = json_encode(['Textbook', 'NPTEL modules', 'Lab manual'], JSON_UNESCAPED_UNICODE);
    $advice = json_encode(['Balance K1-K3 with K4-K6 activities.', 'Map CLOs to assessments.'], JSON_UNESCAPED_UNICODE);

    $planInsert = $pdo->prepare(
        'INSERT INTO course_plans
         (institution_id, department_id, professor_id, subject_id, class_id, title, subject_name, subject_code,
          credits, semester, academic_year, university, syllabus_input, status, ai_score, bloom_data,
          weekly_plan, resources, expert_advice, plan_data, version, submitted_at, reviewed_at, reviewed_by, hod_comments)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $unitInsert = $pdo->prepare(
        'INSERT INTO plan_units (plan_id, unit_number, title, hours, topics, outcomes, bloom_k_level, teaching_methods, assessment, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $verInsert = $pdo->prepare(
        'INSERT INTO course_plan_versions (plan_id, version, snapshot, change_note, created_by) VALUES (?,?,?,?,?)'
    );

    $planSpecs = [
        ['CS301', 'submitted', null, null, null],
        ['CS302', 'submitted', null, null, null],
        ['CS303', 'under_review', null, null, null],
        ['CS304', 'approved', $hods['CSE'], 'Looks good for Odd Semester. Approved.', date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['CS305', 'returned', $hods['CSE'], 'Please expand Unit 4 testing examples and resubmit.', date('Y-m-d H:i:s', strtotime('-1 day'))],
    ];
    $plans = [];
    foreach ($planSpecs as [$scode, $status, $reviewedBy, $hodComment, $reviewedAt]) {
        $subjName = null;
        foreach ($profDefs as $pd) {
            if ($pd[4] === $scode) {
                $subjName = $pd[3];
                break;
            }
        }
        $unitsPayload = [];
        for ($u = 1; $u <= 5; $u++) {
            $unitsPayload[] = [
                'unit_number' => $u,
                'title' => "Unit {$u}",
                'hours' => 12,
                'topics' => ["Topic {$u}.1", "Topic {$u}.2"],
                'outcomes' => ["Explain concepts of Unit {$u}", "Apply Unit {$u} techniques"],
                'bloom_k_level' => 'K' . min(6, $u + 1),
                'teaching_methods' => ['Lecture', 'Discussion'],
                'assessment' => ['Quiz', 'Assignment'],
            ];
        }
        $planData = json_encode([
            'title' => "{$subjName} · Course Plan",
            'subject' => $subjName,
            'learning_outcomes' => [
                "Understand foundational concepts of {$subjName}",
                "Apply {$subjName} skills to academic problems",
                'Analyze problems using higher-order thinking',
            ],
            'units' => $unitsPayload,
            'weekly_plan' => json_decode($weekly, true),
            'resources' => json_decode($resources, true),
            'expert_advice' => json_decode($advice, true),
            'bloom_distribution' => json_decode($bloom, true),
            'ai_score' => 82,
        ], JSON_UNESCAPED_UNICODE);

        $submittedAt = in_array($status, ['submitted', 'under_review', 'approved', 'returned'], true)
            ? date('Y-m-d H:i:s', strtotime('-5 days'))
            : null;

        $planInsert->execute([
            $instId, $depts['CSE'], $profs[$scode], $subjects[$scode], $y1a,
            "{$subjName} · Course Plan", $subjName, $scode,
            4.0, $sem, $ay, 'Anna University',
            "Syllabus for {$subjName}",
            $status, 82.0, $bloom, $weekly, $resources, $advice, $planData, 1,
            $submittedAt, $reviewedAt, $reviewedBy, $hodComment,
        ]);
        $planId = (int)$pdo->lastInsertId();
        $plans[$scode] = $planId;
        foreach ($unitsPayload as $i => $u) {
            $unitInsert->execute([
                $planId, $u['unit_number'], $u['title'], $u['hours'],
                json_encode($u['topics']), json_encode($u['outcomes']), $u['bloom_k_level'],
                json_encode($u['teaching_methods']), json_encode($u['assessment']), $i,
            ]);
        }
        $verInsert->execute([$planId, 1, $planData, 'Initial version', $profs[$scode]]);
    }
    // Notify CSE HOD about pending plans
    $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, body, action_url, is_read) VALUES (?,?,?,?,?,0)'
    )->execute([
        $hods['CSE'], 'approval', 'Course plans awaiting review',
        'DBMS, OS, and Computer Networks plans are in the Approvals queue.',
        '/hod/approvals.php',
    ]);
    $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, body, action_url, is_read) VALUES (?,?,?,?,?,0)'
    )->execute([
        $profs['CS304'], 'approval', 'Plan approved',
        'Your Java Programming course plan was approved by CSE HOD.',
        '/professor/plan-view.php?id=' . $plans['CS304'],
    ]);
    $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, body, action_url, is_read) VALUES (?,?,?,?,?,0)'
    )->execute([
        $profs['CS305'], 'approval', 'Plan returned',
        'Please expand Unit 4 testing examples and resubmit.',
        '/professor/plan-view.php?id=' . $plans['CS305'],
    ]);

    // ---- Attendance (DBMS · CSE 1A) ----
    $attSessions = [
        [date('Y-m-d', strtotime('-7 days')), '1', 'ER Model intro', ['CSE24001' => 'present', 'CSE24002' => 'present', 'CSE24003' => 'absent']],
        [date('Y-m-d', strtotime('-5 days')), '1', 'Normalization', ['CSE24001' => 'present', 'CSE24002' => 'late', 'CSE24003' => 'present']],
        [date('Y-m-d', strtotime('-2 days')), '1', 'SQL basics', ['CSE24001' => 'present', 'CSE24002' => 'present', 'CSE24003' => 'absent']],
    ];
    $sessIns = $pdo->prepare(
        'INSERT INTO attendance_sessions
         (institution_id, professor_id, subject_id, class_id, session_date, period, topic, records, present_count, absent_count)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $recIns = $pdo->prepare(
        'INSERT INTO attendance_records (session_id, student_id, register_no, status) VALUES (?,?,?,?)'
    );
    foreach ($attSessions as [$date, $period, $topic, $map]) {
        $records = [];
        $present = 0;
        $absent = 0;
        foreach ($map as $reg => $st) {
            $records[] = ['register_no' => $reg, 'status' => $st];
            if (in_array($st, ['present', 'late'], true)) {
                $present++;
            } else {
                $absent++;
            }
        }
        $sessIns->execute([
            $instId, $profs['CS301'], $subjects['CS301'], $y1a, $date, $period, $topic,
            json_encode($records, JSON_UNESCAPED_UNICODE), $present, $absent,
        ]);
        $sid = (int)$pdo->lastInsertId();
        foreach ($map as $reg => $st) {
            $recIns->execute([$sid, $students[$reg]['id'], $reg, $st]);
        }
    }

    // ---- Internal marks (DBMS · CSE 1A) ----
    $marksRows = [
        'CSE24001' => ['cia1' => 8, 'cia2' => 9, 'assignment' => 4, 'attendance' => 5],
        'CSE24002' => ['cia1' => 7, 'cia2' => 8, 'assignment' => 4, 'attendance' => 4],
        'CSE24003' => ['cia1' => 6, 'cia2' => 7, 'assignment' => 3, 'attendance' => 3],
    ];
    $marksIns = $pdo->prepare(
        'INSERT INTO internal_marks
         (institution_id, professor_id, subject_id, class_id, formula_id, student_id, register_no, student_name,
          marks_data, attendance_pct, assignment_total, computed_total, grade_letter)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    foreach ($marksRows as $reg => $md) {
        $expr = '((cia1+cia2)/2)*(15/50)+assignment+attendance';
        foreach ($md as $k => $v) {
            $expr = preg_replace('/\b' . preg_quote($k, '/') . '\b/', (string)$v, $expr);
        }
        $total = null;
        if (preg_match('/^[0-9+\-.*\/() ]+$/', $expr)) {
            eval('$total = (float)(' . $expr . ');');
        }
        $letter = $total >= 22 ? 'O' : ($total >= 18 ? 'A' : ($total >= 15 ? 'B' : ($total >= 12 ? 'C' : 'D')));
        $marksIns->execute([
            $instId, $profs['CS301'], $subjects['CS301'], $y1a, $formulaId,
            $students[$reg]['id'], $reg, $students[$reg]['name'],
            json_encode($md, JSON_UNESCAPED_UNICODE),
            (float)$md['attendance'] * 20, // rough pct display helper
            $md['assignment'], round((float)$total, 2), $letter,
        ]);
    }

    // ---- Assignments ----
    $asgIns = $pdo->prepare(
        'INSERT INTO assignments
         (institution_id, plan_id, professor_id, subject_id, class_id, title, assignment_type, description,
          rubric, max_marks, deadline, instructions, ai_generated, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,"published")'
    );
    $asgDefs = [
        ['CS301', $y1a, 'Normalization Assignment', 'problem_solving', 'Normalize the given relation to 3NF. Show functional dependencies and decomposition steps.'],
        ['CS302', $y1a, 'Process Scheduling Assignment', 'problem_solving', 'Compare FCFS, SJF, and Round Robin with a worked example and Gantt charts.'],
        ['CS303', $y1a, 'TCP/IP Assignment', 'essay', 'Explain TCP/IP layers with real-world examples and a short case on packet flow.'],
    ];
    $rubric = json_encode([
        ['criterion' => 'Content quality', 'weight' => 40, 'levels' => 'Excellent/Good/Fair'],
        ['criterion' => 'Analysis', 'weight' => 30, 'levels' => 'Excellent/Good/Fair'],
        ['criterion' => 'Structure & referencing', 'weight' => 30, 'levels' => 'Excellent/Good/Fair'],
    ], JSON_UNESCAPED_UNICODE);
    $instr = json_encode(['Read the brief carefully', 'Cite academic sources', 'Submit before deadline'], JSON_UNESCAPED_UNICODE);
    $assignmentIds = [];
    foreach ($asgDefs as [$scode, $cls, $title, $type, $desc]) {
        $asgIns->execute([
            $instId, $plans[$scode] ?? null, $profs[$scode], $subjects[$scode], $cls,
            $title, $type, $desc, $rubric, 25,
            date('Y-m-d H:i:s', strtotime('+14 days')), $instr,
        ]);
        $assignmentIds[] = (int)$pdo->lastInsertId();
    }
    // One submitted assignment from Mohammed
    $pdo->prepare(
        'INSERT INTO assignment_submissions (assignment_id, student_id, content_text, submitted_at, status)
         VALUES (?,?,?,NOW(),"submitted")'
    )->execute([
        $assignmentIds[0],
        $students['CSE24001']['id'],
        "Normalization to 3NF:\n1) Identify FDs\n2) Remove partial dependencies\n3) Remove transitive dependencies.",
    ]);

    // ---- Notes / PPT ----
    $docIns = $pdo->prepare(
        'INSERT INTO documents (institution_id, owner_id, plan_id, subject_id, doc_type, title, unit_number, content_text, is_published)
         VALUES (?,?,?,?,?,?,?,?,1)'
    );
    $docIns->execute([$instId, $profs['CS301'], $plans['CS301'], $subjects['CS301'], 'note', 'DBMS Unit 1 Notes', 1, 'Introduction to DBMS, data models, and architecture.']);
    $docIns->execute([$instId, $profs['CS301'], $plans['CS301'], $subjects['CS301'], 'ppt', 'DBMS Unit 1 PPT Outline', 1, 'Slides: What is DBMS? Advantages over file systems.']);
    $docIns->execute([$instId, $profs['CS301'], $plans['CS301'], $subjects['CS301'], 'note', 'DBMS Unit 2 Notes', 2, 'ER Model, entities, relationships, cardinality.']);
    $docIns->execute([$instId, $profs['CS301'], $plans['CS301'], $subjects['CS301'], 'ppt', 'DBMS Unit 2 PPT Outline', 2, 'Slides: ER diagrams and mapping to tables.']);
    $docIns->execute([$instId, $profs['CS302'], $plans['CS302'], $subjects['CS302'], 'note', 'OS Unit 1 Notes', 1, 'Operating system concepts and types.']);
    $docIns->execute([$instId, $profs['CS302'], $plans['CS302'], $subjects['CS302'], 'ppt', 'OS Unit 1 PPT Outline', 1, 'Slides: OS services and system calls.']);

    $pptIns = $pdo->prepare(
        'INSERT INTO presentations (plan_id, professor_id, subject_id, title, slide_count, slides, status)
         VALUES (?,?,?,?,?,?,"published")'
    );
    $slides = json_encode([
        ['number' => 1, 'title' => 'Introduction', 'bullets' => ['Course overview', 'Learning outcomes'], 'speaker_notes' => 'Welcome students', 'unit_tag' => 'Unit 1'],
        ['number' => 2, 'title' => 'Core concepts', 'bullets' => ['Concept A', 'Concept B'], 'speaker_notes' => 'Explain with example', 'unit_tag' => 'Unit 1'],
        ['number' => 3, 'title' => 'Summary', 'bullets' => ['Recap', 'Next class preview'], 'speaker_notes' => 'Ask exit questions', 'unit_tag' => 'Unit 1'],
    ], JSON_UNESCAPED_UNICODE);
    $pptIns->execute([$plans['CS301'], $profs['CS301'], $subjects['CS301'], 'DBMS Unit 1 Presentation', 3, $slides]);
    $pptIns->execute([$plans['CS302'], $profs['CS302'], $subjects['CS302'], 'OS Unit 1 Presentation', 3, $slides]);

    // ---- Academic calendar ----
    $evIns = $pdo->prepare(
        'INSERT INTO academic_events (institution_id, title, event_type, event_date, end_date, description)
         VALUES (?,?,?,?,?,?)'
    );
    $evIns->execute([$instId, 'Semester Begins', 'academic', '2025-07-15', null, 'Odd semester classes begin.']);
    $evIns->execute([$instId, 'Internal Assessment 1', 'exam', '2025-09-10', '2025-09-15', 'CIA 1 for all UG programs.']);
    $evIns->execute([$instId, 'Internal Assessment 2', 'exam', '2025-10-20', '2025-10-25', 'CIA 2 for all UG programs.']);
    $evIns->execute([$instId, 'Project Review', 'academic', '2025-11-05', null, 'Final-year project mid review.']);
    $evIns->execute([$instId, 'Semester Examination', 'exam', '2025-12-01', '2025-12-20', 'End-semester university examinations.']);

    // ---- Announcements ----
    $annIns = $pdo->prepare(
        'INSERT INTO announcements (institution_id, department_id, created_by, title, body, announcement_type)
         VALUES (?,?,?,?,?,?)'
    );
    $annIns->execute([
        $instId, null, 1,
        'Internal Assessment 1 schedule published',
        'CIA 1 will be held from 10–15 September. Check the academic calendar for department-wise slots.',
        'exam',
    ]);
    $annIns->execute([
        $instId, $depts['CSE'], $hods['CSE'],
        'DBMS assignment submission deadline updated',
        'CSE Year 1 Section A: Normalization Assignment deadline extended by 3 days. Contact Prof. Arun Kumar for doubts.',
        'deadline',
    ]);
    $annIns->execute([
        $instId, $depts['CSE'], $hods['CSE'],
        'Semester examination timetable released',
        'CSE Odd Semester exam timetable is available with the HOD office and student portal calendar.',
        'exam',
    ]);
    $annIns->execute([
        $instId, $depts['ECE'], $hods['ECE'],
        'ECE lab safety briefing',
        'All ECE Year 1 students must attend the lab safety briefing this Friday.',
        'circular',
    ]);

    // Welcome notifications for sample student
    $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, body, action_url, is_read) VALUES (?,?,?,?,?,0)'
    )->execute([
        $students['CSE24001']['id'], 'system', 'Welcome to student portal',
        'Your CSE UG Year 1 Section A account is ready. Check courses, attendance, and assignments.',
        '/student/dashboard',
    ]);

} catch (Throwable $e) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDERR, 'SEED FAILED: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ---- Verification ----
$adminAfter = $pdo->query($adminSql)->fetch();
$fk = (int)$pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();

$c = static function (PDO $pdo, string $sql): int {
    return (int)$pdo->query($sql)->fetchColumn();
};

$report = [
    'institutions' => $c($pdo, 'SELECT COUNT(*) FROM institutions'),
    'departments' => $c($pdo, 'SELECT COUNT(*) FROM departments'),
    'programs' => $c($pdo, 'SELECT COUNT(*) FROM programs'),
    'classes' => $c($pdo, 'SELECT COUNT(*) FROM classes'),
    'subjects' => $c($pdo, 'SELECT COUNT(*) FROM subjects'),
    'hods' => $c($pdo, "SELECT COUNT(*) FROM users WHERE role='hod'"),
    'professors' => $c($pdo, "SELECT COUNT(*) FROM users WHERE role='professor'"),
    'students' => $c($pdo, "SELECT COUNT(*) FROM users WHERE role='student'"),
    'subject_assignments' => $c($pdo, 'SELECT COUNT(*) FROM subject_assignments'),
    'enrollments' => $c($pdo, 'SELECT COUNT(*) FROM enrollments'),
    'roster' => $c($pdo, 'SELECT COUNT(*) FROM students_roster'),
    'course_plans' => $c($pdo, 'SELECT COUNT(*) FROM course_plans'),
    'plan_units' => $c($pdo, 'SELECT COUNT(*) FROM plan_units'),
    'attendance_sessions' => $c($pdo, 'SELECT COUNT(*) FROM attendance_sessions'),
    'attendance_records' => $c($pdo, 'SELECT COUNT(*) FROM attendance_records'),
    'marks_formulas' => $c($pdo, 'SELECT COUNT(*) FROM marks_formulas'),
    'internal_marks' => $c($pdo, 'SELECT COUNT(*) FROM internal_marks'),
    'assignments' => $c($pdo, 'SELECT COUNT(*) FROM assignments'),
    'assignment_submissions' => $c($pdo, 'SELECT COUNT(*) FROM assignment_submissions'),
    'documents' => $c($pdo, 'SELECT COUNT(*) FROM documents'),
    'presentations' => $c($pdo, 'SELECT COUNT(*) FROM presentations'),
    'academic_events' => $c($pdo, 'SELECT COUNT(*) FROM academic_events'),
    'announcements' => $c($pdo, 'SELECT COUNT(*) FROM announcements'),
    'notifications' => $c($pdo, 'SELECT COUNT(*) FROM notifications'),
    'feature_flags' => $c($pdo, 'SELECT COUNT(*) FROM feature_flags'),
    'ai_prompt_templates' => $c($pdo, 'SELECT COUNT(*) FROM ai_prompt_templates'),
];

$adminOk = $adminAfter
    && (int)$adminAfter['id'] === 1
    && $adminAfter['email'] === 'admin@proprofessor.local'
    && $adminAfter['role'] === 'admin'
    && $adminAfter['full_name'] === 'College Admin'
    && (string)$adminAfter['password_hash'] === $hashBefore
    && (int)$adminAfter['is_active'] === 1;

$loginCheck = password_verify('Test@12345', (string)$pdo->query("SELECT password_hash FROM users WHERE email='csehod@test.com'")->fetchColumn());

echo "\n=== FINAL REPORT ===\n";
echo "1. Approx records cleared (pre-seed): {$deletedEstimate}\n";
echo '2. Admin account preserved: ' . ($adminOk ? 'YES' : 'NO') . "\n";
echo "   - email: {$adminAfter['email']}\n";
echo "   - password hash unchanged: " . (($adminAfter['password_hash'] ?? '') === $hashBefore ? 'YES' : 'NO') . "\n";
echo "3. Institution created: {$report['institutions']} (ProProfessor Demo College / PPC001)\n";
echo "4. Departments created: {$report['departments']}\n";
echo "5. HOD accounts created: {$report['hods']}\n";
echo "6. Professor accounts created: {$report['professors']}\n";
echo "7. Student accounts created: {$report['students']}\n";
echo "8. Courses/subjects created: {$report['subjects']}\n";
echo "9. Classes/sections created: {$report['classes']} (programs={$report['programs']})\n";
echo "10. Course plans created: {$report['course_plans']} (units={$report['plan_units']})\n";
echo "11. Attendance: sessions={$report['attendance_sessions']} records={$report['attendance_records']}\n";
echo "12. Internal marks: formulas={$report['marks_formulas']} marks={$report['internal_marks']}\n";
echo "13. Assignments: {$report['assignments']} (submissions={$report['assignment_submissions']})\n";
echo "14. Notes/PPT: documents={$report['documents']} presentations={$report['presentations']}\n";
echo "15. Calendar events: {$report['academic_events']}\n";
echo "16. Announcements: {$report['announcements']} (notifications={$report['notifications']})\n";
echo "Subject assignments: {$report['subject_assignments']} | Enrollments: {$report['enrollments']} | Roster: {$report['roster']}\n";
echo 'FOREIGN_KEY_CHECKS=' . $fk . " | Dummy password verify: " . ($loginCheck ? 'OK' : 'FAIL') . "\n";
echo "Master preserved: feature_flags={$report['feature_flags']} prompts={$report['ai_prompt_templates']}\n";

echo "\n=== TEST LOGINS (password for all dummy accounts: Test@12345) ===\n";
echo "Admin (UNCHANGED password): admin@proprofessor.local\n";
echo "CSE HOD: csehod@test.com\n";
echo "ECE HOD: ecehod@test.com\n";
echo "EEE HOD: eeehod@test.com\n";
echo "IT HOD:  ithod@test.com\n";
echo "MECH HOD: mechhod@test.com\n";
echo "Prof DBMS: arun.kumar@test.com\n";
echo "Prof OS: priya.kumar@test.com\n";
echo "Prof CN: rahul.kumar@test.com\n";
echo "Prof Java: divya.kumar@test.com\n";
echo "Prof SE: karthik.kumar@test.com\n";
echo "Student CSE 1A: mohammed@test.com\n";
echo "Student CSE 1A: ananya@test.com\n";
echo "Student CSE 1B: arjun@test.com\n";
echo "Student ECE: ece.student@test.com\n";

$planStatuses = $pdo->query('SELECT status, COUNT(*) c FROM course_plans GROUP BY status')->fetchAll();
echo "\nCourse plan statuses: " . json_encode($planStatuses) . "\n";

if (!$adminOk || $fk !== 1 || !$loginCheck) {
    fwrite(STDERR, "Seed finished with verification failures.\n");
    exit(1);
}

echo "\nDone. Ready for end-to-end manual testing.\n";
exit(0);
