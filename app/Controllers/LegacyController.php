<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * Serves remaining feature pages through the MVC front controller
 * while those modules are fully ported to Controllers/Views.
 */
final class LegacyController extends Controller
{
    public function professor(string $page): void
    {
        $this->requireRole('professor', 'admin');
        $this->serve('professor', $page, [
            'generate-plan', 'plans', 'plan-view', 'plan-compare', 'plan-export',
            'lessons', 'questions', 'question-paper',
            'ppt', 'ppt-view', 'ppt-download', 'ppt-pdf', 'ppt-handout', 'assignments', 'attendance', 'marks', 'messages', 'message-hod', 'settings',
        ]);
    }

    public function student(string $page): void
    {
        $this->requireRole('student');
        $this->serve('student', $page, [
            'courses', 'notes', 'assignments', 'attendance', 'attendance-qr', 'marks', 'academic-history', 'calendar', 'ask-ai',
        ]);
    }

    public function hod(string $page): void
    {
        $this->requireRole('hod', 'admin');
        $this->serve('hod', $page, [
            'approvals', 'faculty', 'students', 'subjects', 'analytics', 'compliance', 'reports', 'timeline',
        ]);
    }

    private function serve(string $area, string $page, array $allowed): void
    {
        $page = str_replace(['..', '/', '\\'], '', $page);
        if (!in_array($page, $allowed, true)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $area . DIRECTORY_SEPARATOR . $page . '.php';
        if (!is_file($file)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        // Legacy scripts render a full page (bootstrap + layout).
        require $file;
        exit;
    }
}
