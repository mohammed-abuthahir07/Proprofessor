<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/PptxExporter.php';

Auth::requireRole('professor', 'admin', 'student');

$user = Auth::user();
$id = (int)get('id');
$ppt = Database::fetch('SELECT * FROM presentations WHERE id = ?', [$id]);
$home = ($user['role'] ?? '') === 'student' ? '/student/notes' : '/professor/ppt.php';
if (!$ppt || !presentation_accessible($user, $ppt)) {
    flash('error', 'Presentation not found.');
    redirect($home);
}

$slides = json_decode((string)($ppt['slides'] ?? '[]'), true);
if (!is_array($slides)) {
    $slides = [];
}

$title = (string)($ppt['title'] ?? 'Presentation');
$branding = PresentationTools::brandingForPresentation($user, $ppt);
$filename = PptxExporter::filename($title);
$viewPath = ($user['role'] ?? '') === 'student'
    ? '/student/ppt-view?id=' . $id
    : '/professor/ppt-view.php?id=' . $id;
$tmp = tempnam(sys_get_temp_dir(), 'ppai_ppt_');
if ($tmp === false) {
    flash('error', 'Could not create a temporary file for the PPT.');
    redirect($viewPath);
}
$pptx = $tmp . '.pptx';
@unlink($tmp);

try {
    PptxExporter::saveToFile($pptx, $title, $slides, $branding);
} catch (Throwable $e) {
    @unlink($pptx);
    flash('error', 'Could not save this PPT: ' . $e->getMessage());
    redirect($viewPath);
}

$size = filesize($pptx);
if ($size === false) {
    @unlink($pptx);
    flash('error', 'Saved PPT file is empty.');
    redirect($viewPath);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
readfile($pptx);
@unlink($pptx);
exit;
