<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole('professor', 'admin', 'hod');
$user = Auth::user();
$id = (int)get('id');
$format = strtolower((string)get('format', 'naac')) === 'nba' ? 'nba' : 'naac';

$plan = Database::fetch('SELECT * FROM course_plans WHERE id = ?', [$id]);
if (!$plan || !CoursePlanTools::canViewPlan($user, $plan)) {
    http_response_code(404);
    echo 'Plan not found.';
    exit;
}

$isOwner = (int)$plan['professor_id'] === (int)$user['id']
    || in_array((string)$user['role'], ['admin', 'superadmin', 'hod'], true);
if ((string)$plan['status'] !== 'approved' && !in_array((string)$user['role'], ['admin', 'superadmin', 'hod'], true)) {
    flash('error', 'Accreditation export is available after the plan is approved.');
    redirect('/professor/plan-view.php?id=' . $id);
}

$units = Database::fetchAll(
    'SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number',
    [$id]
);
$html = CoursePlanTools::exportHtml($plan, $units, $format);
$filename = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string)$plan['subject_name']) . '_' . strtoupper($format) . '_v' . (int)$plan['version'] . '.html';

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="' . $filename . '"');
echo $html;
exit;
