<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use Database;

final class AnalyticsController extends Controller
{
    public function index(): void
    {
        require_admin_perm('view_analytics');
        $instId = (int)$this->user()['institution_id'];
        $this->view('admin/analytics', [
            'title' => 'Institution Analytics',
            'active' => 'analytics',
            'metrics' => [
                'attendance_sessions' => (int)(Database::fetch(
                    'SELECT COUNT(*) c FROM attendance_sessions WHERE institution_id = ?',
                    [$instId]
                )['c'] ?? 0),
                'assignments' => (int)(Database::fetch(
                    'SELECT COUNT(*) c FROM assignments WHERE institution_id = ?',
                    [$instId]
                )['c'] ?? 0),
                'ai_calls' => (int)(Database::fetch(
                    'SELECT COUNT(*) c FROM ai_generations WHERE institution_id = ?',
                    [$instId]
                )['c'] ?? 0),
                'avg_score' => Database::fetch(
                    'SELECT AVG(ai_score) a FROM course_plans WHERE institution_id = ?',
                    [$instId]
                )['a'],
            ],
        ]);
    }
}
