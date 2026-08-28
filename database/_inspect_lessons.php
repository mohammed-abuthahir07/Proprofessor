<?php
declare(strict_types=1);
$cfg = require dirname(__DIR__) . '/config/config.php';
$db = $cfg['db'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
    $db['user'],
    $db['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
echo "COURSE PLANS\n";
foreach ($pdo->query('SELECT id, professor_id, institution_id, title, subject_name, status, LENGTH(plan_data) pd, LENGTH(syllabus_input) sy FROM course_plans ORDER BY id') as $r) {
    echo json_encode($r) . "\n";
}
echo "\nPLAN UNITS\n";
foreach ($pdo->query('SELECT id, plan_id, unit_number, title, hours, LEFT(topics,80) topics FROM plan_units ORDER BY plan_id, unit_number') as $r) {
    echo json_encode($r) . "\n";
}
echo "\nLESSON PLANS\n";
echo 'count=' . $pdo->query('SELECT COUNT(*) FROM lesson_plans')->fetchColumn() . "\n";
foreach ($pdo->query('SELECT id, plan_id, professor_id, session_number, title, duration_mins FROM lesson_plans ORDER BY plan_id, session_number LIMIT 20') as $r) {
    echo json_encode($r) . "\n";
}
echo "\nUSERS professors/admin\n";
foreach ($pdo->query("SELECT id, role, email, full_name FROM users WHERE role IN ('admin','professor','hod')") as $r) {
    echo json_encode($r) . "\n";
}
