<?php
declare(strict_types=1);

namespace App\Controllers\Hod;

use App\Core\Controller;
use Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireRole('hod', 'admin');
        $user = $this->user();
        $deptId = $user['department_id'];
        $this->view('hod/dashboard', [
            'title' => 'HOD Dashboard',
            'active' => 'dash',
            'subtitle' => 'Governance · approvals · compliance',
            'pending' => (int)(Database::fetch(
                'SELECT COUNT(*) c FROM course_plans WHERE institution_id = ? AND department_id = ? AND status IN ("submitted","under_review")',
                [$user['institution_id'], $deptId]
            )['c'] ?? 0),
            'approved' => (int)(Database::fetch(
                'SELECT COUNT(*) c FROM course_plans WHERE institution_id = ? AND department_id = ? AND status = "approved"',
                [$user['institution_id'], $deptId]
            )['c'] ?? 0),
            'faculty' => (int)(Database::fetch(
                'SELECT COUNT(*) c FROM users WHERE institution_id = ? AND department_id = ? AND role = "professor" AND is_active = 1',
                [$user['institution_id'], $deptId]
            )['c'] ?? 0),
            'avg' => Database::fetch(
                'SELECT AVG(ai_score) a FROM course_plans WHERE institution_id = ? AND department_id = ? AND ai_score IS NOT NULL',
                [$user['institution_id'], $deptId]
            ),
            'alerts' => Database::fetchAll(
                'SELECT * FROM compliance_alerts WHERE institution_id = ? AND department_id = ? AND is_resolved = 0 ORDER BY id DESC LIMIT 5',
                [$user['institution_id'], $deptId]
            ),
        ]);
    }
}
