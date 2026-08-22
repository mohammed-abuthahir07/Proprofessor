<?php
declare(strict_types=1);

/**
 * DESTRUCTIVE local-development data reset for ToProProfessor.
 *
 * Deletes demo/application rows. Preserves Admin users.id = 1 and
 * required master/system catalogs. Does not change schema or PHP code.
 *
 * Usage (CLI only):
 *   php database/reset_dev_data.php --confirm-local-reset
 *
 * Refuses to run unless:
 *   - invoked from CLI with --confirm-local-reset
 *   - config env is local/dev
 *   - DB host is loopback
 *   - DB name is proposofessor
 *   - users.id = 1 is admin@proprofessor.local / role admin
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "REFUSED: this reset may only run from the command line.\n");
    exit(1);
}

$confirmed = in_array('--confirm-local-reset', $argv, true);
if (!$confirmed) {
    fwrite(STDERR, <<<TXT
DESTRUCTIVE local reset — refused.

This script deletes HOD/professor/student users and all demo application
data. The existing Admin (users.id = 1 / admin@proprofessor.local) is kept.

Re-run with:
  php database/reset_dev_data.php --confirm-local-reset

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
    fwrite(STDERR, "REFUSED: config env '{$env}' is not local/dev. Won't reset.\n");
    exit(1);
}
if (!in_array($host, $allowedHost, true)) {
    fwrite(STDERR, "REFUSED: database host '{$host}' is not loopback. Won't reset.\n");
    exit(1);
}
if ($name !== 'proprofessor') {
    fwrite(STDERR, "REFUSED: database name '{$name}' is not proposofessor. Won't reset.\n");
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

$hashBefore = $adminBefore['password_hash'];
$schemaBefore = (int)$pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchColumn();

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

$masterTables = [
    'feature_flags',
    'ai_prompt_templates',
    'institution_features',
];

echo "=== PLAN ===\n";
echo "A. Application/demo tables to clear:\n  - " . implode("\n  - ", $applicationTables) . "\n";
echo "    plus users WHERE id <> 1, app_settings WHERE institution_id IS NOT NULL\n";
echo "B. Required system/master data to preserve:\n  - " . implode("\n  - ", $masterTables) . "\n";
echo "    plus institutions.id = 1 (minimal shell), global app_settings, schema/FKs\n";
echo "C. Admin preserved: id={$adminBefore['id']} email={$adminBefore['email']} role={$adminBefore['role']} hash-prefix=" . substr($hashBefore, 0, 12) . "...\n";
echo "D. Order: child rows -> academic structure -> non-admin users -> neutralize institution 1 -> AUTO_INCREMENT -> FK=1\n\n";
echo "Executing local reset...\n";

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($applicationTables as $table) {
        $pdo->exec("DELETE FROM `{$table}`");
    }
    $pdo->exec('DELETE FROM `app_settings` WHERE `institution_id` IS NOT NULL');
    $pdo->exec('DELETE FROM `users` WHERE `id` <> 1');

    $pdo->exec(<<<SQL
UPDATE `institutions`
SET
  `name` = 'Institution',
  `code` = NULL,
  `affiliation_university` = NULL,
  `naac_grade` = NULL,
  `nba_status` = NULL,
  `address` = NULL,
  `city` = NULL,
  `state` = NULL,
  `pincode` = NULL,
  `phone` = NULL,
  `email` = NULL,
  `logo_url` = NULL,
  `subscription_tier` = 'trial',
  `licensed_seats` = 60,
  `academic_year` = NULL,
  `current_semester` = NULL,
  `settings` = '{"attendance_min": 75}',
  `is_active` = 1
WHERE `id` = 1
SQL);

    // ALTER TABLE implicitly commits in MySQL/MariaDB — run after DELETEs, not inside a transaction.
    foreach ($applicationTables as $table) {
        $pdo->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
    }
    $pdo->exec('ALTER TABLE `app_settings` AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE `users` AUTO_INCREMENT = 2');
} catch (Throwable $e) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDERR, 'RESET FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$adminAfter = $pdo->query($adminSql)->fetch();
$fk = (int)$pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();
$schemaAfter = (int)$pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchColumn();

$users = $pdo->query('SELECT id, role, email FROM users ORDER BY id')->fetchAll();
$roles = $pdo->query('SELECT role, COUNT(*) c FROM users GROUP BY role')->fetchAll();
$hod = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'hod'")->fetchColumn();
$prof = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'professor'")->fetchColumn();
$stud = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$instCount = (int)$pdo->query('SELECT COUNT(*) FROM institutions')->fetchColumn();
$inst = $pdo->query('SELECT id, name, city, affiliation_university FROM institutions WHERE id = 1')->fetch();
$flags = (int)$pdo->query('SELECT COUNT(*) FROM feature_flags')->fetchColumn();
$prompts = (int)$pdo->query('SELECT COUNT(*) FROM ai_prompt_templates')->fetchColumn();
$ifeat = (int)$pdo->query('SELECT COUNT(*) FROM institution_features WHERE institution_id = 1')->fetchColumn();

$appCounts = [];
foreach ($applicationTables as $table) {
    $appCounts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

echo "\n=== VERIFICATION ===\n";
echo 'users: ' . json_encode($users) . PHP_EOL;
echo 'roles: ' . json_encode($roles) . PHP_EOL;
echo 'institution: ' . json_encode($inst) . PHP_EOL;
echo "feature_flags={$flags} ai_prompt_templates={$prompts} institution_features={$ifeat}\n";
echo 'application row counts: ' . json_encode($appCounts) . PHP_EOL;
echo 'FOREIGN_KEY_CHECKS=' . $fk . PHP_EOL;
echo "schema table count before={$schemaBefore} after={$schemaAfter}\n";

$ok = static function (bool $v): string {
    return $v ? 'YES' : 'NO';
};

$appEmpty = array_sum($appCounts) === 0;
$adminPreserved = $adminAfter
    && (int)$adminAfter['id'] === 1
    && $adminAfter['email'] === 'admin@proprofessor.local'
    && $adminAfter['role'] === 'admin'
    && $adminAfter['full_name'] === 'College Admin'
    && (int)$adminAfter['is_active'] === 1
    && (string)$adminAfter['employee_id'] === (string)$adminBefore['employee_id'];

echo "\n=== SUMMARY ===\n";
echo 'Admin preserved: ' . $ok($adminPreserved) . PHP_EOL;
echo 'Admin email preserved: ' . $ok(($adminAfter['email'] ?? '') === 'admin@proprofessor.local') . PHP_EOL;
echo 'Admin password hash preserved: ' . $ok(($adminAfter['password_hash'] ?? '') === $hashBefore) . PHP_EOL;
echo 'Other users deleted: ' . $ok(count($users) === 1 && $hod === 0 && $prof === 0 && $stud === 0) . PHP_EOL;
echo 'Demo data deleted: ' . $ok($appEmpty && $instCount === 1 && ($inst['name'] ?? '') === 'Institution') . PHP_EOL;
echo 'Required system data preserved: ' . $ok($flags === 20 && $prompts === 10 && $ifeat === 20) . PHP_EOL;
echo 'Schema changed: ' . $ok($schemaBefore !== $schemaAfter) . PHP_EOL;
echo 'Foreign keys restored: ' . $ok($fk === 1) . PHP_EOL;

if (!$adminPreserved || ($adminAfter['password_hash'] ?? '') !== $hashBefore || $fk !== 1) {
    fwrite(STDERR, "Reset finished with verification failures.\n");
    exit(1);
}

echo "\nDone. Admin login is unchanged: admin@proprofessor.local (existing password).\n";
exit(0);
