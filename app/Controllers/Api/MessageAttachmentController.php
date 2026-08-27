<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use Auth;
use ProfessorMessageTools;

/**
 * GET /api/messages/attachment?id={announcement_id}
 * Secure download for professor message attachments (professor owner or eligible student).
 */
final class MessageAttachmentController extends Controller
{
    public function download(): void
    {
        if (!Auth::check()) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }

        $user = Auth::user();
        $announcementId = (int)($_GET['id'] ?? 0);
        if ($announcementId < 1) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        ProfessorMessageTools::ensureSchema();
        $role = (string)($user['role'] ?? '');
        $ann = match ($role) {
            'student' => ProfessorMessageTools::announcementForStudentAttachment($user, $announcementId),
            'professor', 'admin' => ProfessorMessageTools::announcementForProfessorAttachment($user, $announcementId),
            default => null,
        };

        if (!$ann) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $fullPath = ProfessorMessageTools::attachmentAbsolutePath((string)($ann['attachment_path'] ?? ''));
        if ($fullPath === null) {
            http_response_code(404);
            echo 'Attachment not found';
            return;
        }

        $original = (string)($ann['attachment_original_name'] ?? 'attachment');
        $mime = (string)($ann['attachment_mime_type'] ?? 'application/octet-stream');
        $safeOriginal = preg_replace('/[^\w.\- ()]/', '_', $original) ?: 'attachment';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $safeOriginal . '"');
        header('Content-Length: ' . (string)filesize($fullPath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($fullPath);
        exit;
    }
}
