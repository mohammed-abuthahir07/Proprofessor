<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use Auth;
use ProfessorMessageTools;

/**
 * POST /api/professor/messages — send scoped student announcement.
 */
final class ProfessorMessagesController extends Controller
{
    public function store(): void
    {
        if (!Auth::check()) {
            json_response(['success' => false, 'message' => 'Please sign in again.', 'recipient_count' => 0], 401);
        }

        $user = Auth::user();
        $role = (string)($user['role'] ?? '');
        if (!in_array($role, ['professor', 'admin'], true)) {
            json_response(['success' => false, 'message' => 'Only professors can send student messages.', 'recipient_count' => 0], 403);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_response(['success' => false, 'message' => 'Method not allowed', 'recipient_count' => 0], 405);
        }

        // Prefer JSON body; fall back to form fields.
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $json = [];
        }

        $csrf = (string)($json['csrf'] ?? $json['_csrf'] ?? $_POST['csrf'] ?? $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($csrf === '' || !hash_equals((string)csrf_token(), $csrf)) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.', 'recipient_count' => 0], 419);
        }

        $year = (int)($json['year'] ?? $_POST['year'] ?? 0);
        $courseId = (int)($json['course_id'] ?? $json['subject_id'] ?? $_POST['course_id'] ?? $_POST['subject_id'] ?? 0);
        $classId = (int)($json['class_id'] ?? $_POST['class_id'] ?? 0);
        $message = (string)($json['message'] ?? $_POST['message'] ?? '');
        $title = (string)($json['title'] ?? $_POST['title'] ?? '');

        ProfessorMessageTools::ensureSchema();
        $result = ProfessorMessageTools::send($user, $year, $courseId, $classId, $message, $title);

        if (!$result['ok']) {
            json_response([
                'success' => false,
                'message' => $result['error'] ?? 'Unable to send message.',
                'recipient_count' => 0,
            ], 422);
        }

        json_response([
            'success' => true,
            'message' => 'Message sent successfully',
            'recipient_count' => (int)($result['recipient_count'] ?? 0),
            'announcement_id' => (int)($result['announcement_id'] ?? 0),
        ]);
    }
}
