<?php
declare(strict_types=1);

/**
 * DESTRUCTIVE local-development data reset for ProProfessor AI.
 *
 * Deletes demo/application rows for a clean manual test of:
 * College Admin → Department → HOD → Professors → Students → academic modules.
 *
 * Preserves:
 *   - College Admin user(s) with role `admin` (email, password, role, institution, status)
 *   - Platform Admin user(s) with role `superadmin` if present
 *   - Institution row(s) linked to those accounts (including subscription_tier / licensed_seats)
 *   - Global catalogs: feature_flags, ai_prompt_templates
 *   - institution_features for preserved institution(s)
 *   - Global app_settings (institution_id IS NULL)
 *   - Database schema, tables, indexes, and foreign keys
 *
 * Does not change application PHP/UI code.
 *
 * Usage (CLI only):
 *   php database/reset_dev_data.php
 *   php database/reset_dev_data.php --dry-run
 *   php database/reset_dev_data.php --confirm-local-reset
 *
 * Refuses unless:
 *   - CLI invocation
 *   - config env is local/dev/development
 *   - DB host is loopback
 *   - DB name is proposofessor
 *   - at least one College Admin (role=admin) exists
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "REFUSED: this reset may only run from the command line.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$wantHelp = in_array('--help', $args, true) || in_array('-h', $args, true);
$confirmed = in_array('--confirm-local-reset', $args, true);
$dryRun = !$confirmed || in_array('--dry-run', $args, true);
if ($confirmed && in_array('--dry-run', $args, true)) {
    fwrite(STDERR, "REFUSED: pass either --dry-run or --confirm-local-reset, not both.\n");
    exit(1);
}

if ($wantHelp) {
    fwrite(STDOUT, <<<TXT
ProProfessor AI — local development test-data reset

  php database/reset_dev_data.php                  Preview counts (no deletes)
  php database/reset_dev_data.php --dry-run        Same as above
  php database/reset_dev_data.php --confirm-local-reset
                                                   Execute reset (local proposofessor only)

Preserves College Admin (role=admin) and optional Platform Admin (role=superadmin).
Does not drop tables, drop the database, or change application code.

TXT);
    exit(0);
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

/**
 * @return list<string>
 */
function ident(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException("Unsafe identifier: {$name}");
    }
    return '`' . $name . '`';
}

/**
 * @return list<string>
 */
function listBaseTables(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    )->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[] = (string)$row['TABLE_NAME'];
    }
    return $out;
}

/**
 * @param list<string> $tables
 * @return array<string, bool>
 */
function tableSet(array $tables): array
{
    $set = [];
    foreach ($tables as $t) {
        $set[$t] = true;
    }
    return $set;
}

/**
 * @return list<array{child:string,child_col:string,parent:string,parent_col:string,nullable:bool}>
 */
function listForeignKeys(PDO $pdo): array
{
    $sql = <<<SQL
SELECT
  kcu.TABLE_NAME AS child_table,
  kcu.COLUMN_NAME AS child_column,
  kcu.REFERENCED_TABLE_NAME AS parent_table,
  kcu.REFERENCED_COLUMN_NAME AS parent_column,
  c.IS_NULLABLE AS is_nullable
FROM information_schema.KEY_COLUMN_USAGE kcu
JOIN information_schema.COLUMNS c
  ON c.TABLE_SCHEMA = kcu.TABLE_SCHEMA
 AND c.TABLE_NAME = kcu.TABLE_NAME
 AND c.COLUMN_NAME = kcu.COLUMN_NAME
WHERE kcu.TABLE_SCHEMA = DATABASE()
  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY kcu.TABLE_NAME, kcu.COLUMN_NAME
SQL;
    $rows = $pdo->query($sql)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'child' => (string)$row['child_table'],
            'child_col' => (string)$row['child_column'],
            'parent' => (string)$row['parent_table'],
            'parent_col' => (string)$row['parent_column'],
            'nullable' => strtoupper((string)$row['is_nullable']) === 'YES',
        ];
    }
    return $out;
}

/**
 * @return array<string, true>
 */
function columnSet(PDO $pdo, string $table): array
{
    $set = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM ' . ident($table));
    foreach ($stmt as $col) {
        $set[(string)$col['Field']] = true;
    }
    return $set;
}

function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $cache[$table] = columnSet($pdo, $table);
    }
    return isset($cache[$table][$column]);
}

/**
 * Preferred child-before-parent order for tables that have no DB-level FK.
 *
 * @return list<string>
 */
function preferredDeleteOrder(): array
{
    return [
        'ai_chat_messages',
        'question_attempts',
        'assignment_submissions',
        'assignment_extension_requests',
        'attendance_records',
        'attendance_regularization_requests',
        'attendance_qr_tokens',
        'document_chunks',
        'questions',
        'course_plan_versions',
        'plan_units',
        'plan_reviews',
        'lesson_plans',
        'presentations',
        'exam_papers',
        'assignment_templates',
        'professor_announcements',
        'admin_hod_announcements',
        'professor_hod_messages',
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
}

/**
 * @param list<string> $tables
 * @param list<array{child:string,parent:string}> $edges child must be deleted before parent
 * @return list<string>
 */
function topologicalDeleteOrder(array $tables, array $edges): array
{
    $set = tableSet($tables);
    $adj = [];
    $indegree = [];
    foreach ($tables as $t) {
        $adj[$t] = [];
        $indegree[$t] = 0;
    }
    $seen = [];
    foreach ($edges as $e) {
        $child = $e['child'];
        $parent = $e['parent'];
        if ($child === $parent) {
            continue;
        }
        if (!isset($set[$child], $set[$parent])) {
            continue;
        }
        $key = $child . '>' . $parent;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $adj[$child][] = $parent;
        $indegree[$parent]++;
    }

    $prefRank = array_flip(preferredDeleteOrder());
    $cmp = static function (string $a, string $b) use ($prefRank): int {
        $ra = $prefRank[$a] ?? 1000;
        $rb = $prefRank[$b] ?? 1000;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        return strcmp($a, $b);
    };

    $queue = [];
    foreach ($tables as $t) {
        if ($indegree[$t] === 0) {
            $queue[] = $t;
        }
    }
    usort($queue, $cmp);

    $ordered = [];
    while ($queue !== []) {
        $n = array_shift($queue);
        $ordered[] = $n;
        foreach ($adj[$n] as $parent) {
            $indegree[$parent]--;
            if ($indegree[$parent] === 0) {
                $queue[] = $parent;
                usort($queue, $cmp);
            }
        }
    }

    if (count($ordered) !== count($tables)) {
        $leftover = [];
        foreach ($tables as $t) {
            if (!in_array($t, $ordered, true)) {
                $leftover[] = $t;
            }
        }
        usort($leftover, $cmp);
        foreach ($leftover as $t) {
            $ordered[] = $t;
        }
        return ['__cycle__' => $leftover, 'order' => $ordered];
    }

    return ['__cycle__' => [], 'order' => $ordered];
}

/**
 * @param list<string> $tables
 * @return array<string,int>
 */
function countRows(PDO $pdo, array $tables): array
{
    $out = [];
    foreach ($tables as $table) {
        $out[$table] = (int)$pdo->query('SELECT COUNT(*) FROM ' . ident($table))->fetchColumn();
    }
    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function fetchPreservedUsers(PDO $pdo): array
{
    return $pdo->query(
        "SELECT * FROM `users`
         WHERE `role` IN ('admin', 'superadmin')
         ORDER BY FIELD(`role`, 'admin', 'superadmin'), `id`"
    )->fetchAll();
}

/**
 * @param list<array<string,mixed>> $users
 */
function pickCollegeAdmin(array $users): ?array
{
    $admins = array_values(array_filter($users, static fn(array $u): bool => (string)$u['role'] === 'admin'));
    if ($admins === []) {
        return null;
    }
    foreach ($admins as $u) {
        if ((string)$u['email'] === 'admin@proprofessor.local') {
            return $u;
        }
    }
    foreach ($admins as $u) {
        if ((string)$u['full_name'] === 'College Admin') {
            return $u;
        }
    }
    return $admins[0];
}

/**
 * @return list<string>
 */
function uploadDirs(): array
{
    $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    return [
        $root . DIRECTORY_SEPARATOR . 'assignments',
        $root . DIRECTORY_SEPARATOR . 'attendance',
        $root . DIRECTORY_SEPARATOR . 'professor-messages',
        $root . DIRECTORY_SEPARATOR . 'admin-hod-messages',
        $root . DIRECTORY_SEPARATOR . 'professor-hod-messages',
    ];
}

function isProtectedUploadName(string $name): bool
{
    $lower = strtolower($name);
    return in_array($lower, ['.htaccess', 'index.html', 'index.htm', '.gitkeep'], true);
}

/**
 * @return list<array{path:string,bytes:int}>
 */
function listUploadFiles(): array
{
    $files = [];
    foreach (uploadDirs() as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            if (isProtectedUploadName($file->getFilename())) {
                continue;
            }
            $files[] = [
                'path' => $file->getPathname(),
                'bytes' => (int)$file->getSize(),
            ];
        }
    }
    return $files;
}

function deleteUploadFiles(array $files): int
{
    $n = 0;
    foreach ($files as $f) {
        $path = (string)$f['path'];
        if ($path === '' || !is_file($path)) {
            continue;
        }
        if (isProtectedUploadName(basename($path))) {
            continue;
        }
        $root = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
        $real = realpath($path);
        if ($root === false || $real === false) {
            continue;
        }
        if (!str_starts_with($real, $root)) {
            continue;
        }
        if (@unlink($real)) {
            $n++;
        }
    }
    return $n;
}

$allTables = listBaseTables($pdo);
$tableLookup = tableSet($allTables);
$fks = listForeignKeys($pdo);
$schemaTableCount = count($allTables);

$preserveAll = [];
foreach (['feature_flags', 'ai_prompt_templates'] as $t) {
    if (isset($tableLookup[$t])) {
        $preserveAll[] = $t;
    }
}

$partialTables = [];
foreach (['users', 'institutions', 'institution_features', 'app_settings'] as $t) {
    if (isset($tableLookup[$t])) {
        $partialTables[] = $t;
    }
}

if (!isset($tableLookup['users'], $tableLookup['institutions'])) {
    fwrite(STDERR, "REFUSED: required tables users/institutions are missing.\n");
    exit(1);
}

$preservedUsers = fetchPreservedUsers($pdo);
$collegeAdmin = pickCollegeAdmin($preservedUsers);
if ($collegeAdmin === null) {
    fwrite(STDERR, "REFUSED: no College Admin (users.role = 'admin') was found. Nothing was deleted.\n");
    exit(1);
}

$preservedUserIds = [];
$preservedInstitutionIds = [];
$platformAdmins = [];
foreach ($preservedUsers as $u) {
    $preservedUserIds[] = (int)$u['id'];
    $instId = (int)($u['institution_id'] ?? 0);
    if ($instId > 0) {
        $preservedInstitutionIds[$instId] = $instId;
    }
    if ((string)$u['role'] === 'superadmin') {
        $platformAdmins[] = $u;
    }
}
$preservedInstitutionIds = array_values($preservedInstitutionIds);
if ($preservedInstitutionIds === []) {
    fwrite(STDERR, "REFUSED: College Admin has no institution association. Nothing was deleted.\n");
    exit(1);
}

$adminSnapshotSql = 'SELECT * FROM `users` WHERE `id` = ' . (int)$collegeAdmin['id'] . ' LIMIT 1';
$adminBefore = $pdo->query($adminSnapshotSql)->fetch();
$hashBefore = (string)($adminBefore['password_hash'] ?? '');

$clearTables = [];
foreach ($allTables as $t) {
    if (in_array($t, $preserveAll, true) || in_array($t, $partialTables, true)) {
        continue;
    }
    $clearTables[] = $t;
}

$fkEdges = [];
foreach ($fks as $fk) {
    $fkEdges[] = ['child' => $fk['child'], 'parent' => $fk['parent']];
}
$sorted = topologicalDeleteOrder($clearTables, $fkEdges);
$deleteOrder = $sorted['order'];
$cycleTables = $sorted['__cycle__'];

$nullableFkNulls = [];
foreach ($fks as $fk) {
    if (!$fk['nullable']) {
        continue;
    }
    if ($fk['child'] === $fk['parent']) {
        $nullableFkNulls[] = $fk;
        continue;
    }
    $childCleared = in_array($fk['child'], $clearTables, true);
    $parentCleared = in_array($fk['parent'], $clearTables, true)
        || ($fk['parent'] === 'users')
        || ($fk['parent'] === 'departments')
        || ($fk['parent'] === 'classes');
    if ($childCleared && $parentCleared) {
        $nullableFkNulls[] = $fk;
    }
}

$allCounts = countRows($pdo, $allTables);

$roleCounts = [
    'hod' => 0,
    'professor' => 0,
    'student' => 0,
    'admin' => 0,
    'superadmin' => 0,
    'other' => 0,
];
foreach ($pdo->query('SELECT `role`, COUNT(*) AS c FROM `users` GROUP BY `role`') as $row) {
    $role = (string)$row['role'];
    $c = (int)$row['c'];
    if (isset($roleCounts[$role])) {
        $roleCounts[$role] = $c;
    } else {
        $roleCounts['other'] += $c;
    }
}

$usersToDelete = (int)$pdo->query(
    'SELECT COUNT(*) FROM `users` WHERE `id` NOT IN (' . implode(',', array_map('intval', $preservedUserIds)) . ')'
)->fetchColumn();

$instToDelete = (int)$pdo->query(
    'SELECT COUNT(*) FROM `institutions` WHERE `id` NOT IN (' . implode(',', array_map('intval', $preservedInstitutionIds)) . ')'
)->fetchColumn();

$ifeatKeep = 0;
$ifeatDelete = 0;
if (isset($tableLookup['institution_features'])) {
    $ifeatKeep = (int)$pdo->query(
        'SELECT COUNT(*) FROM `institution_features` WHERE `institution_id` IN (' . implode(',', array_map('intval', $preservedInstitutionIds)) . ')'
    )->fetchColumn();
    $ifeatDelete = (int)$pdo->query(
        'SELECT COUNT(*) FROM `institution_features` WHERE `institution_id` NOT IN (' . implode(',', array_map('intval', $preservedInstitutionIds)) . ')'
    )->fetchColumn();
}

$appSettingsInst = 0;
$appSettingsGlobal = 0;
if (isset($tableLookup['app_settings'])) {
    $appSettingsInst = (int)$pdo->query('SELECT COUNT(*) FROM `app_settings` WHERE `institution_id` IS NOT NULL')->fetchColumn();
    $appSettingsGlobal = (int)$pdo->query('SELECT COUNT(*) FROM `app_settings` WHERE `institution_id` IS NULL')->fetchColumn();
}

$uploadFiles = listUploadFiles();
$uploadBytes = 0;
foreach ($uploadFiles as $f) {
    $uploadBytes += (int)$f['bytes'];
}

$willDelete = [];
$willRemain = [];
foreach ($allTables as $t) {
    $before = $allCounts[$t] ?? 0;
    if (in_array($t, $preserveAll, true)) {
        $willDelete[$t] = 0;
        $willRemain[$t] = $before;
        continue;
    }
    if ($t === 'users') {
        $willDelete[$t] = $usersToDelete;
        $willRemain[$t] = $before - $usersToDelete;
        continue;
    }
    if ($t === 'institutions') {
        $willDelete[$t] = $instToDelete;
        $willRemain[$t] = $before - $instToDelete;
        continue;
    }
    if ($t === 'institution_features') {
        $willDelete[$t] = $ifeatDelete;
        $willRemain[$t] = $ifeatKeep;
        continue;
    }
    if ($t === 'app_settings') {
        $willDelete[$t] = $appSettingsInst;
        $willRemain[$t] = $appSettingsGlobal;
        continue;
    }
    $willDelete[$t] = $before;
    $willRemain[$t] = 0;
}

$sumDelete = array_sum($willDelete);
$sumRemain = array_sum($willRemain);

$instRows = $pdo->query(
    'SELECT `id`, `name`, `subscription_tier`, `licensed_seats`, `is_active`
     FROM `institutions`
     WHERE `id` IN (' . implode(',', array_map('intval', $preservedInstitutionIds)) . ')
     ORDER BY `id`'
)->fetchAll();

echo "========================================\n";
echo "TEST DATA RESET — " . ($dryRun ? "DRY RUN (no deletes)" : "EXECUTING") . "\n";
echo "========================================\n\n";
echo "1. Database name: {$actualDb}\n";
echo "   Host: {$db['host']}  Port: {$db['port']}  Env: {$env}\n";
echo "   Schema tables: {$schemaTableCount}\n\n";

echo "2. College Admin account that will be PRESERVED:\n";
echo "   id={$collegeAdmin['id']}\n";
echo "   email={$collegeAdmin['email']}\n";
echo "   role={$collegeAdmin['role']}\n";
echo "   full_name={$collegeAdmin['full_name']}\n";
echo "   institution_id={$collegeAdmin['institution_id']}\n";
echo "   is_active={$collegeAdmin['is_active']}\n";
echo "   password_hash prefix=" . substr($hashBefore, 0, 12) . "... (unchanged)\n";
if ($platformAdmins !== []) {
    echo "\n   Platform Admin also preserved:\n";
    foreach ($platformAdmins as $pa) {
        echo "   - id={$pa['id']} email={$pa['email']} role={$pa['role']} institution_id={$pa['institution_id']}\n";
    }
} else {
    echo "   Platform Admin: none present (role=superadmin)\n";
}
echo "\n   Institution row(s) preserved (subscription catalog lives on this row):\n";
foreach ($instRows as $ir) {
    echo "   - id={$ir['id']} name={$ir['name']} tier={$ir['subscription_tier']} seats={$ir['licensed_seats']} active={$ir['is_active']}\n";
}

echo "\n3. Records that WILL BE DELETED per table:\n";
foreach ($allTables as $t) {
    $d = $willDelete[$t];
    if ($d < 1) {
        continue;
    }
    echo sprintf("   %-36s %d\n", $t, $d);
}
echo "   TOTAL rows to delete: {$sumDelete}\n";
echo "   Users by role to delete: hod={$roleCounts['hod']} professor={$roleCounts['professor']} student={$roleCounts['student']}\n";
echo "   Upload files to delete: " . count($uploadFiles) . " (" . $uploadBytes . " bytes)\n";

echo "\n4. Records that WILL REMAIN per table:\n";
foreach ($allTables as $t) {
    $r = $willRemain[$t];
    if ($r < 1) {
        continue;
    }
    echo sprintf("   %-36s %d\n", $t, $r);
}
echo "   TOTAL rows remaining (approx): {$sumRemain}\n";

echo "\n5. Tables / files affected:\n";
echo "   Full-clear tables (" . count($clearTables) . "):\n     - " . implode("\n     - ", $clearTables) . "\n";
echo "   Partial-clear: users (keep admin/superadmin), institutions (keep linked), ";
echo "institution_features (keep linked), app_settings (keep global)\n";
echo "   Fully preserved catalogs: " . implode(', ', $preserveAll) . "\n";
echo "   FK constraints discovered: " . count($fks) . "\n";
foreach ($fks as $fk) {
    echo "     {$fk['child']}.{$fk['child_col']} -> {$fk['parent']}.{$fk['parent_col']}" . ($fk['nullable'] ? ' (nullable)' : '') . "\n";
}
echo "   Delete order (child → parent):\n     " . implode(" → ", $deleteOrder) . "\n";
if ($cycleTables !== []) {
    echo "   NOTE: FK cycle among: " . implode(', ', $cycleTables) . " — nullable FKs will be nulled first.\n";
}
echo "   Upload dirs:\n     - " . implode("\n     - ", uploadDirs()) . "\n";

echo "\nIntentionally preserved:\n";
echo "   - College Admin authentication row (email, password_hash, role, institution_id, is_active)\n";
echo "   - Institution association + subscription_tier / licensed_seats (not a separate catalog table)\n";
echo "   - feature_flags, ai_prompt_templates\n";
echo "   - institution_features for the College Admin institution\n";
echo "   - global app_settings\n";
echo "   - schema / tables / indexes / foreign keys\n";
echo "   - application source and config files\n";

if ($dryRun) {
    echo "\nDRY RUN complete. No data was deleted.\n";
    echo "To execute against this local database:\n";
    echo "  php database/reset_dev_data.php --confirm-local-reset\n";
    exit(0);
}

echo "\nExecuting local reset inside a transaction...\n";

$idList = implode(',', array_map('intval', $preservedUserIds));
$instList = implode(',', array_map('intval', $preservedInstitutionIds));

$deleted = [];
$filesDeleted = 0;

try {
    $pdo->beginTransaction();

    foreach ($nullableFkNulls as $fk) {
        $pdo->exec('UPDATE ' . ident($fk['child']) . ' SET ' . ident($fk['child_col']) . ' = NULL');
    }

    if (isset($tableLookup['departments']) && tableHasColumn($pdo, 'departments', 'hod_user_id')) {
        $pdo->exec('UPDATE `departments` SET `hod_user_id` = NULL');
    }
    if (isset($tableLookup['professor_hod_messages']) && tableHasColumn($pdo, 'professor_hod_messages', 'thread_id')) {
        $pdo->exec('UPDATE `professor_hod_messages` SET `thread_id` = NULL');
    }
    if (tableHasColumn($pdo, 'users', 'department_id')) {
        $pdo->exec('UPDATE `users` SET `department_id` = NULL WHERE `id` IN (' . $idList . ') AND `department_id` IS NOT NULL');
    }
    if (tableHasColumn($pdo, 'users', 'class_id')) {
        $pdo->exec('UPDATE `users` SET `class_id` = NULL WHERE `id` IN (' . $idList . ') AND `class_id` IS NOT NULL');
    }

    foreach ($deleteOrder as $table) {
        $before = $allCounts[$table] ?? 0;
        $pdo->exec('DELETE FROM ' . ident($table));
        $deleted[$table] = $before;
    }

    if (isset($tableLookup['app_settings'])) {
        $pdo->exec('DELETE FROM `app_settings` WHERE `institution_id` IS NOT NULL');
        $deleted['app_settings'] = $appSettingsInst;
    }
    if (isset($tableLookup['institution_features'])) {
        $pdo->exec('DELETE FROM `institution_features` WHERE `institution_id` NOT IN (' . $instList . ')');
        $deleted['institution_features'] = $ifeatDelete;
    }

    $pdo->exec('DELETE FROM `users` WHERE `id` NOT IN (' . $idList . ')');
    $deleted['users'] = $usersToDelete;

    $pdo->exec('DELETE FROM `institutions` WHERE `id` NOT IN (' . $instList . ')');
    $deleted['institutions'] = $instToDelete;

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'RESET FAILED and was rolled back: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

try {
    foreach ($deleteOrder as $table) {
        $pdo->exec('ALTER TABLE ' . ident($table) . ' AUTO_INCREMENT = 1');
    }
    $maxUser = (int)$pdo->query('SELECT COALESCE(MAX(`id`), 0) FROM `users`')->fetchColumn();
    $pdo->exec('ALTER TABLE `users` AUTO_INCREMENT = ' . ($maxUser + 1));
    $maxInst = (int)$pdo->query('SELECT COALESCE(MAX(`id`), 0) FROM `institutions`')->fetchColumn();
    $pdo->exec('ALTER TABLE `institutions` AUTO_INCREMENT = ' . ($maxInst + 1));
    if (isset($tableLookup['app_settings'])) {
        $maxSet = (int)$pdo->query('SELECT COALESCE(MAX(`id`), 0) FROM `app_settings`')->fetchColumn();
        $pdo->exec('ALTER TABLE `app_settings` AUTO_INCREMENT = ' . ($maxSet + 1));
    }
    if (isset($tableLookup['institution_features'])) {
        $maxFeat = (int)$pdo->query('SELECT COALESCE(MAX(`id`), 0) FROM `institution_features`')->fetchColumn();
        $pdo->exec('ALTER TABLE `institution_features` AUTO_INCREMENT = ' . ($maxFeat + 1));
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Warning: AUTO_INCREMENT reset failed (data already committed): {$e->getMessage()}\n");
}

$filesDeleted = deleteUploadFiles($uploadFiles);

$adminAfter = $pdo->query($adminSnapshotSql)->fetch();
$schemaAfter = (int)$pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchColumn();
$fkAfter = (int)$pdo->query(
    "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"
)->fetchColumn();
$fkChecks = (int)$pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();

$hodLeft = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'hod'")->fetchColumn();
$profLeft = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'professor'")->fetchColumn();
$studLeft = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'student'")->fetchColumn();
$usersLeft = $pdo->query('SELECT `id`, `role`, `email`, `institution_id`, `is_active` FROM `users` ORDER BY `id`')->fetchAll();

$c = static function (array $deleted, string $table): int {
    return (int)($deleted[$table] ?? 0);
};
$sum = static function (array $deleted, array $tables): int {
    $n = 0;
    foreach ($tables as $t) {
        $n += (int)($deleted[$t] ?? 0);
    }
    return $n;
};

$otherTables = array_keys($deleted);
$named = [
    'departments', 'users', 'subjects', 'assignments', 'assignment_submissions',
    'attendance_records', 'attendance_sessions', 'internal_marks', 'notifications',
    'question_banks', 'questions', 'exam_papers', 'course_plans', 'lesson_plans',
    'announcements', 'professor_announcements', 'admin_hod_announcements',
    'professor_hod_messages',
];
$otherCount = 0;
foreach ($deleted as $t => $n) {
    if (!in_array($t, $named, true)) {
        $otherCount += (int)$n;
    }
}

$adminOk = $adminAfter
    && (int)$adminAfter['id'] === (int)$adminBefore['id']
    && (string)$adminAfter['email'] === (string)$adminBefore['email']
    && (string)$adminAfter['role'] === (string)$adminBefore['role']
    && (string)$adminAfter['password_hash'] === $hashBefore
    && (int)$adminAfter['institution_id'] === (int)$adminBefore['institution_id']
    && (int)$adminAfter['is_active'] === (int)$adminBefore['is_active'];

$flagsLeft = isset($tableLookup['feature_flags'])
    ? (int)$pdo->query('SELECT COUNT(*) FROM `feature_flags`')->fetchColumn()
    : 0;
$promptsLeft = isset($tableLookup['ai_prompt_templates'])
    ? (int)$pdo->query('SELECT COUNT(*) FROM `ai_prompt_templates`')->fetchColumn()
    : 0;
$ifeatLeft = isset($tableLookup['institution_features'])
    ? (int)$pdo->query('SELECT COUNT(*) FROM `institution_features` WHERE `institution_id` IN (' . $instList . ')')->fetchColumn()
    : 0;

echo "\n========================================\n";
echo "TEST DATA RESET COMPLETED\n";
echo "========================================\n\n";
echo "College Admin preserved:\n";
echo "  {$adminAfter['email']}\n";
echo "  id={$adminAfter['id']} role={$adminAfter['role']} institution_id={$adminAfter['institution_id']} is_active={$adminAfter['is_active']}\n\n";

echo 'Departments deleted: ' . $c($deleted, 'departments') . "\n";
echo "HODs deleted: {$roleCounts['hod']}\n";
echo "Professors deleted: {$roleCounts['professor']}\n";
echo "Students deleted: {$roleCounts['student']}\n";
echo 'Courses deleted: ' . $c($deleted, 'subjects') . "\n";
echo 'Assignments deleted: ' . ($c($deleted, 'assignments') + $c($deleted, 'assignment_submissions') + $c($deleted, 'assignment_templates') + $c($deleted, 'assignment_extension_requests')) . "\n";
echo 'Attendance records deleted: ' . ($c($deleted, 'attendance_records') + $c($deleted, 'attendance_sessions') + $c($deleted, 'attendance_qr_tokens') + $c($deleted, 'attendance_regularization_requests')) . "\n";
echo 'Internal marks deleted: ' . ($c($deleted, 'internal_marks') + $c($deleted, 'marks_formulas')) . "\n";
echo 'Messages deleted: ' . $sum($deleted, ['announcements', 'professor_announcements', 'admin_hod_announcements', 'professor_hod_messages']) . "\n";
echo 'Notifications deleted: ' . $c($deleted, 'notifications') . "\n";
echo 'Question bank records deleted: ' . $sum($deleted, ['question_banks', 'questions', 'exam_papers', 'question_attempts']) . "\n";
echo 'Course plans deleted: ' . $sum($deleted, ['course_plans', 'course_plan_versions', 'plan_units', 'plan_reviews']) . "\n";
echo 'Lesson plans deleted: ' . $c($deleted, 'lesson_plans') . "\n";
echo "Other test records deleted: {$otherCount}\n";
echo "Upload files deleted: {$filesDeleted}\n\n";

echo 'College Admin: ' . ($adminOk ? 'PRESERVED' : 'VERIFICATION FAILED') . "\n";
echo 'Database schema: ' . ($schemaAfter === $schemaTableCount ? 'PRESERVED' : 'CHANGED') . "\n";
echo 'Foreign keys: ' . ($fkAfter === count($fks) && $fkChecks === 1 ? 'PRESERVED' : 'CHECK FAILED') . "\n";
echo "Remaining users: " . json_encode($usersLeft) . "\n";
echo "HOD/professor/student remaining: hod={$hodLeft} professor={$profLeft} student={$studLeft}\n";
echo "Catalogs remaining: feature_flags={$flagsLeft} ai_prompt_templates={$promptsLeft} institution_features={$ifeatLeft}\n";
echo "========================================\n";

if (!$adminOk || $schemaAfter !== $schemaTableCount || $fkAfter !== count($fks) || $fkChecks !== 1) {
    fwrite(STDERR, "Reset finished with verification failures.\n");
    exit(1);
}
if ($hodLeft !== 0 || $profLeft !== 0 || $studLeft !== 0) {
    fwrite(STDERR, "Reset finished but leftover HOD/professor/student accounts remain.\n");
    exit(1);
}

echo "\nDone. Log in with the existing College Admin email and password (unchanged).\n";
exit(0);
