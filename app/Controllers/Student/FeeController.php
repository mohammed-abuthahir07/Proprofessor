<?php
declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Controller;
use Auth;

/**
 * Read-only fee history for the authenticated student. Identity always comes
 * from the session — request student_id values are ignored.
 */
final class FeeController extends Controller
{
    public function index(): void
    {
        $this->requireRole('student');
        Auth::refresh();
        $user = $this->user();
        ensure_student_academic_schema();
        ensure_fee_collection_schema();

        $snapshot = student_fee_snapshot($user);
        $ctx = student_academic_context($user);
        $level = (int)($ctx['year'] ?? 0);
        $dept = trim((string)($ctx['department_code'] ?? ''));
        if ($dept === '') {
            $dept = trim((string)($ctx['department_name'] ?? ''));
        }

        $this->view('student/fees', [
            'title' => 'Fee History',
            'active' => 'fees',
            'subtitle' => 'Your fee records from College Admin',
            'studentName' => (string)($user['full_name'] ?? ''),
            'registerNo' => trim((string)($user['register_no'] ?? '')),
            'department' => $dept,
            'yearLabel' => $level > 0 ? subject_year_label($level) : '',
            'academicYear' => institution_academic_year((int)$user['institution_id']),
            'snapshot' => $snapshot,
        ]);
    }
}
