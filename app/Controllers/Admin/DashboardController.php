<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\CoursePlan;
use App\Models\Expense;
use App\Models\Institution;
use App\Models\User;
use Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin', 'superadmin');
        $user = $this->user();
        $instId = (int)$user['institution_id'];
        $inst = Institution::find($instId);

        $this->view('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'active' => 'dash',
            'subtitle' => $inst['name'] ?? 'Institution',
            'inst' => $inst,
            'stats' => [
                'users' => User::count('institution_id = ?', [$instId]),
                'plans' => CoursePlan::count('institution_id = ?', [$instId]),
                'students' => User::count('institution_id = ? AND role = "student"', [$instId]),
                'spend' => (float)(Database::fetch(
                    'SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE institution_id = ?',
                    [$instId]
                )['s'] ?? 0),
            ],
        ]);
    }
}
