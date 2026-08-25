<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use Auth;
use CoursePlanTools;
use Throwable;

final class AiController extends Controller
{
    public function handle(): void
    {
        // Always JSON for this API — never HTML redirects (those produce empty bodies → frontend JSON parse errors).
        if (!Auth::check()) {
            json_response(['ok' => false, 'error' => 'Please sign in again.'], 401);
        }

        $module = (string)($_GET['module'] ?? $_POST['module'] ?? '');
        if ($module === 'syllabus_extract') {
            $this->syllabusExtract();
            return;
        }

        // Existing AI orchestration lives in api/ai.php (service-style script).
        require dirname(__DIR__, 3) . '/api/ai.php';
        exit;
    }

    private function syllabusExtract(): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
            }
            $user = Auth::user();
            $role = (string)($user['role'] ?? '');
            if (!in_array($role, ['professor', 'admin', 'superadmin'], true)) {
                json_response(['ok' => false, 'error' => 'Permission denied.'], 403);
            }
            $file = $_FILES['syllabus_file'] ?? null;
            if (!is_array($file)) {
                json_response(['ok' => false, 'error' => 'No file uploaded. Use Upload PDF or Upload DOCX.'], 422);
            }
            $text = CoursePlanTools::extractUploadedSyllabus($file);
            json_response(['ok' => true, 'text' => $text]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'error' => $e->getMessage() ?: 'Extraction failed.'], 422);
        }
    }
}
