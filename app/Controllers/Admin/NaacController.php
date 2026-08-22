<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Institution;
use App\Models\User;
use Database;

final class NaacController extends Controller
{
    public function index(): void
    {
        require_admin_perm('manage_naac');
        $user = $this->user();
        $instId = (int)$user['institution_id'];
        $this->view('admin/naac', [
            'title' => 'NAAC Document Builder',
            'active' => 'naac',
            'inst' => Institution::find($instId),
            'plans' => Database::fetchAll(
                'SELECT status, COUNT(*) c FROM course_plans WHERE institution_id = ? GROUP BY status',
                [$instId]
            ),
            'faculty' => Database::fetchAll(
                'SELECT u.full_name, d.name dept, COUNT(p.id) plans, AVG(p.ai_score) score
                 FROM users u
                 LEFT JOIN departments d ON d.id = u.department_id
                 LEFT JOIN course_plans p ON p.professor_id = u.id
                 WHERE u.institution_id = ? AND u.role = "professor"
                 GROUP BY u.id',
                [$instId]
            ),
        ]);
    }
}
