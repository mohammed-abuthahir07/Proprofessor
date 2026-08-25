<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireRole('professor', 'admin');

$user = Auth::user();
$id = (int)get('id');
$ppt = PresentationTools::ownedPresentation($user, $id);
if (!$ppt) {
    flash('error', 'Presentation not found.');
    redirect('/professor/ppt.php');
}

$slides = json_decode((string)($ppt['slides'] ?? '[]'), true);
if (!is_array($slides)) {
    $slides = [];
}
$title = (string)($ppt['title'] ?? 'Presentation');
$branding = PresentationTools::brandingForPresentation($user, $ppt);
$bytes = PresentationTools::buildHandoutPdf($title, $slides, $branding);

$meta = json_decode((string)($ppt['meta'] ?? '{}'), true) ?: [];
$meta['handout'] = [
    'generated_at' => date('c'),
    'bytes' => strlen($bytes),
];
Database::update('presentations', [
    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
], 'id = :id', ['id' => $id]);

$safe = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $title) ?: 'handout';
$filename = trim($safe, '._-') . '_handout.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $bytes;
exit;
