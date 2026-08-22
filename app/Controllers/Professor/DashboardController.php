<?php
declare(strict_types=1);

namespace App\Controllers\Professor;

use App\Core\Controller;
use App\Models\Assignment;
use App\Models\CoursePlan;
use Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireRole('professor', 'admin');
        $user = $this->user();
        $byStatus = CoursePlan::statusCounts((int)$user['id']);

        $this->view('professor/dashboard', [
            'title' => 'Professor Dashboard',
            'active' => 'dash',
            'subtitle' => 'Your AI academic workspace',
            'byStatus' => $byStatus,
            'assignments' => Assignment::count('professor_id = ?', [$user['id']]),
            'unread' => unread_notifications_count((int)$user['id']),
            'recent' => Database::fetchAll(
                'SELECT id, title, subject_name, status, ai_score, updated_at
                 FROM course_plans WHERE professor_id = ? ORDER BY updated_at DESC LIMIT 5',
                [$user['id']]
            ),
        ]);
    }
}
