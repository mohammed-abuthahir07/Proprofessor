<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use AdminHodMessageTools;
use Auth;
use ProfessorMessageTools;

/**
 * GET /api/messages/attachment?id={id}&source=professor|admin_hod
 * Secure download for message attachments.
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
        $source = strtolower((string)($_GET['source'] ?? 'professor'));
        if ($announcementId < 1) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $role = (string)($user['role'] ?? '');
        $ann = null;
        $fullPath = null;

        if ($source === 'admin_hod') {
            AdminHodMessageTools::ensureSchema();
            $ann = match ($role) {
                'hod' => AdminHodMessageTools::announcementForHodAttachment($user, $announcementId),
                'admin', 'superadmin' => AdminHodMessageTools::announcementForAdminAttachment($user, $announcementId),
                default => null,
            };
            if ($ann) {
                $fullPath = AdminHodMessageTools::attachmentAbsolutePath((string)($ann['attachment_path'] ?? ''));
            }
        } else {
            ProfessorMessageTools::ensureSchema();
            $ann = match ($role) {
                'student' => ProfessorMessageTools::announcementForStudentAttachment($user, $announcementId),
                'professor', 'admin', 'superadmin' => ProfessorMessageTools::announcementForProfessorAttachment($user, $announcementId),
                default => null,
            };
            if ($ann) {
                $fullPath = ProfessorMessageTools::attachmentAbsolutePath((string)($ann['attachment_path'] ?? ''));
            }
        }

        if (!$ann || $fullPath === null) {
            http_response_code($ann ? 404 : 403);
            echo $ann ? 'Attachment not found' : 'Forbidden';
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
