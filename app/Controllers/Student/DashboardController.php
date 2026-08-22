<?php
declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Controller;
use App\Models\Subject;
use Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireRole('student');
        $user = $this->user();
        $courses = Subject::enrolledForStudent((int)$user['id']);
        $this->view('student/dashboard', [
            'title' => 'Student Dashboard',
            'active' => 'dash',
            'subtitle' => 'Courses · materials · Ask AI',
            'courses' => $courses,
            'assignmentsDue' => array_slice(assignments_visible_to_student($user), 0, 5),
            'ann' => announcements_for_user($user, 5),
        ]);
    }
}
