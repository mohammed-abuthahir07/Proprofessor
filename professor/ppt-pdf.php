<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireRole('professor', 'admin', 'student');

$user = Auth::user();
$id = (int)get('id');
$ppt = Database::fetch('SELECT * FROM presentations WHERE id = ?', [$id]);
$home = ($user['role'] ?? '') === 'student' ? '/student/notes.php' : '/professor/ppt.php';
if (!$ppt || !presentation_accessible($user, $ppt)) {
    flash('error', 'Presentation not found.');
    redirect($home);
}
// Cross-institution guard (belt-and-suspenders).
$owner = Database::fetch('SELECT institution_id FROM users WHERE id = ?', [(int)$ppt['professor_id']]);
if (!$owner || (int)$owner['institution_id'] !== (int)($user['institution_id'] ?? 0)) {
    flash('error', 'Presentation not found.');
    redirect($home);
}

$slides = json_decode((string)($ppt['slides'] ?? '[]'), true);
if (!is_array($slides)) {
    $slides = [];
}
$title = (string)($ppt['title'] ?? 'Presentation');
$branding = PresentationTools::brandingForPresentation($user, $ppt);
$bytes = PresentationTools::buildDeckPdf($title, $slides, $branding, ($user['role'] ?? '') !== 'student');
$safe = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $title) ?: 'presentation';
$filename = trim($safe, '._-') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $bytes;
exit;
