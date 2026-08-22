<?php
/**
 * One-click installer: creates DB schema, seeds features, demo users.
 * Visit: http://localhost/professor/install.php
 * Delete/rename this file after install in production.
 */
declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';
$local = __DIR__ . '/config/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}
$db = $config['db'];
$messages = [];
$ok = false;

// Resolve web base for asset links (works under /demo/professor etc.)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/install.php'));
$webBase = ($scriptDir === '/' ? '' : rtrim($scriptDir, '/'));
$asset = static fn(string $path) => $webBase . '/assets/' . ltrim($path, '/');
$loginUrl = $webBase . '/login';


function run_sql_file(PDO $pdo, string $path, array &$messages): void
{
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: $path");
    }
    $sql = file_get_contents($path);
    // Strip comments carefully enough for our scripts
    $pdo->exec($sql);
    $messages[] = 'Executed ' . basename($path);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$db['name']) ?: 'proprofessor';
        $hostDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);
        $pdo = new PDO($hostDsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Shared hosting often forbids CREATE DATABASE — use the DB already created in cPanel.
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $messages[] = "Database `{$dbName}` ready.";
        } catch (Throwable $e) {
            $messages[] = "Using existing database `{$dbName}` (create skipped: " . $e->getMessage() . ')';
        }
        $pdo->exec("USE `{$dbName}`");

        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        // Strip CREATE DATABASE / USE so hosting DB name from config is respected
        $schema = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`?[^`;]+`?[^;]*;/i', '', $schema);
        $schema = preg_replace('/USE\s+`?[^`;]+`?\s*;/i', '', $schema);
        $pdo->exec($schema);
        $messages[] = "Schema created in `{$dbName}`.";

        $seed = file_get_contents(__DIR__ . '/database/seed.sql');
        $seed = preg_replace('/USE\s+`?[^`;]+`?\s*;/i', '', $seed);
        // Remove broken password placeholders; users created below
        $pdo->exec($seed);
        $messages[] = 'Seed data loaded.';

        $pdo->exec("USE `{$dbName}`");
        $hash = password_hash('Password@123', PASSWORD_BCRYPT);
        $inst = (int)$pdo->query("SELECT id FROM institutions ORDER BY id LIMIT 1")->fetchColumn();
        $dept = (int)$pdo->query("SELECT id FROM departments WHERE code='CS' AND institution_id=$inst LIMIT 1")->fetchColumn();
        $class = (int)$pdo->query("SELECT id FROM classes WHERE institution_id=$inst ORDER BY id LIMIT 1")->fetchColumn();
        $subj = (int)$pdo->query("SELECT id FROM subjects WHERE institution_id=$inst ORDER BY id LIMIT 1")->fetchColumn();

        $users = [
            ['admin@proprofessor.local', 'admin', 'College Admin', null, null],
            ['hod@proprofessor.local', 'hod', 'Dr. HOD Computer Science', $dept, null],
            ['professor@proprofessor.local', 'professor', 'Prof. Anita Sharma', $dept, null],
            ['student@proprofessor.local', 'student', 'Rahul Kumar', $dept, $class],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO users (institution_id, department_id, role, email, password_hash, full_name, class_id, employee_id, register_no, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,1)
             ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), full_name=VALUES(full_name), role=VALUES(role), is_active=1'
        );

        foreach ($users as $u) {
            $emp = $u[1] === 'student' ? null : strtoupper(substr($u[1], 0, 3)) . '001';
            $reg = $u[1] === 'student' ? 'CS2024001' : null;
            $stmt->execute([$inst, $u[3], $u[1], $u[0], $hash, $u[2], $u[4], $emp, $reg]);
        }
        $messages[] = 'Demo users created (password: Password@123).';

        $profId = (int)$pdo->query("SELECT id FROM users WHERE email='professor@proprofessor.local'")->fetchColumn();
        $hodId = (int)$pdo->query("SELECT id FROM users WHERE email='hod@proprofessor.local'")->fetchColumn();
        $stuId = (int)$pdo->query("SELECT id FROM users WHERE email='student@proprofessor.local'")->fetchColumn();
        $adminId = (int)$pdo->query("SELECT id FROM users WHERE email='admin@proprofessor.local'")->fetchColumn();

        $pdo->prepare('UPDATE departments SET hod_user_id=? WHERE id=?')->execute([$hodId, $dept]);
        $pdo->prepare(
            'INSERT IGNORE INTO subject_assignments (subject_id, professor_id, class_id, academic_year, semester)
             VALUES (?,?,?,?,?)'
        )->execute([$subj, $profId, $class, '2025-26', 'Odd Semester']);
        $pdo->prepare(
            'INSERT IGNORE INTO enrollments (student_id, subject_id, class_id, academic_year, semester, status)
             VALUES (?,?,?,?,?,"active")'
        )->execute([$stuId, $subj, $class, '2025-26', 'Odd Semester']);
        $pdo->prepare(
            'INSERT IGNORE INTO students_roster (institution_id, class_id, user_id, register_no, full_name, email)
             VALUES (?,?,?,?,?,?)'
        )->execute([$inst, $class, $stuId, 'CS2024001', 'Rahul Kumar', 'student@proprofessor.local']);

        // Extra roster students for attendance demos
        $extra = [
            ['CS2024002', 'Priya S'],
            ['CS2024003', 'Arun V'],
            ['CS2024004', 'Meena R'],
            ['CS2024005', 'Karthik M'],
        ];
        $rs = $pdo->prepare(
            'INSERT IGNORE INTO students_roster (institution_id, class_id, register_no, full_name) VALUES (?,?,?,?)'
        );
        foreach ($extra as $ex) {
            $rs->execute([$inst, $class, $ex[0], $ex[1]]);
        }

        $pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, body, action_url, is_read)
             VALUES (?,?,?,?,?,0),(?,?,?,?,?,0),(?,?,?,?,?,0)'
        )->execute([
            $profId, 'system', 'Welcome Professor', 'Generate your first AI course plan today.', '/professor/generate-plan.php',
            $hodId, 'system', 'Approval queue ready', 'Review faculty course plans from Approvals.', '/hod/approvals.php',
            $stuId, 'system', 'Portal active', 'Check courses, attendance and Ask AI.', '/student/dashboard.php',
        ]);

        // Fix announcement created_by
        $pdo->exec("UPDATE announcements SET created_by = $adminId WHERE created_by = 1 OR created_by IS NULL OR created_by=0");

        // Save API key if provided
        $apiKey = trim($_POST['gemini_key'] ?? '');
        if ($apiKey !== '') {
            $cfgPath = __DIR__ . '/config/config.php';
            $cfgText = file_get_contents($cfgPath);
            $cfgText = preg_replace(
                "/'api_key'\\s*=>\\s*getenv\\('GEMINI_API_KEY'\\)\\s*\\?:\\s*'[^']*'/",
                "'api_key' => getenv('GEMINI_API_KEY') ?: '" . addslashes($apiKey) . "'",
                $cfgText
            );
            file_put_contents($cfgPath, $cfgText);
            $messages[] = 'Gemini API key saved to config.';
        }

        $ok = true;
        $messages[] = 'Install complete.';
    } catch (Throwable $e) {
        $messages[] = 'ERROR: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install - ProProfessor AI</title>
  <link rel="icon" href="<?= htmlspecialchars($asset('img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($asset('img/logo.svg')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($asset('css/app.css')) ?>">
</head>
<body data-effects="on">
<div class="page-glow" aria-hidden="true"></div>
<div class="auth-panel" style="min-height:100vh">
  <div class="auth-card" style="width:min(560px,100%)">
    <img class="auth-logo-img" src="<?= htmlspecialchars($asset('img/logo.svg')) ?>" width="64" height="64" alt="ProProfessor AI">
    <h2>Install ProProfessor AI</h2>
    <p style="color:var(--muted)">Creates MySQL database, expandable schema, feature flags, and demo accounts.</p>
    <?php foreach ($messages as $m): ?>
      <div class="alert <?= str_starts_with($m, 'ERROR') ? 'alert-error' : 'alert-success' ?>"><?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>
    <?php if ($ok): ?>
      <a class="btn btn-primary btn-block" href="<?= htmlspecialchars($loginUrl) ?>">Go to Login</a>
      <div class="demo-box">
        <div><strong>Demo logins</strong> (password <code>Password@123</code>)</div>
        <div>Admin: admin@proprofessor.local</div>
        <div>HOD: hod@proprofessor.local</div>
        <div>Professor: professor@proprofessor.local</div>
        <div>Student: student@proprofessor.local</div>
      </div>
    <?php else: ?>
      <form method="post" class="form-grid">
        <div class="form-row">
          <label>Gemini API Key (optional · can set later)</label>
          <input type="text" name="gemini_key" placeholder="AIza...">
        </div>
        <button class="btn btn-primary" type="submit">Create Database & Seed</button>
      </form>
      <div class="demo-box">
        On hosting: create a MySQL database, then copy <code>config/config.local.php.example</code>
        to <code>config/config.local.php</code> and set DB name / user / password.
        Local XAMPP defaults: root with empty password.
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="<?= htmlspecialchars($asset('js/app.js')) ?>"></script>
</body>
</html>
