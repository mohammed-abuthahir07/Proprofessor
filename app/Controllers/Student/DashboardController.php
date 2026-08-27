<?php
declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Controller;
use App\Models\Subject;
use Auth;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireRole('student');
        // Reload user so College Admin year/semester edits apply without re-login.
        Auth::refresh();
        $user = $this->user();
        ensure_student_academic_schema();

        $courses = Subject::enrolledForStudent((int)$user['id']);
        $subjects = [];
        $labs = [];
        foreach ($courses as $c) {
            if (subject_course_type($c) === 'lab') {
                $labs[] = $c;
            } else {
                $subjects[] = $c;
            }
        }

        $this->view('student/dashboard', [
            'title' => 'Student Dashboard',
            'active' => 'dash',
            'subtitle' => 'Courses · materials · Ask AI',
            'courses' => $courses,
            'subjects' => $subjects,
            'labs' => $labs,
            'academic' => student_academic_context($user),
            'assignmentsDue' => array_slice(assignments_visible_to_student($user), 0, 5),
            'ann' => announcements_for_user($user, 5),
        ]);
    }
}
