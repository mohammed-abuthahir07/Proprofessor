<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use App\Models\User;
use Database;

final class StudentsController extends Controller
{
    private const PAGE_SIZE = 10;

    public function index(): void
    {
        $pack = $this->pack();
        $this->view('admin/students', [
            'title' => 'Students',
            'active' => 'students',
            'subtitle' => $pack['subtitle'],
            'rows' => $pack['rows'],
            'summary' => $pack['summary'],
            'departments' => $pack['departments'],
            'filters' => $pack['filters'],
            'pageSize' => self::PAGE_SIZE,
            'exportQuery' => $pack['exportQuery'],
        ]);
    }

    public function export(): void
    {
        $pack = $this->pack();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="students.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Name', 'Department', 'Semester', 'Roll no', 'Attendance %', 'Avg marks %']);
        foreach ($pack['rows'] as $row) {
            fputcsv($out, [
                $row['name'],
                $row['dept_code'] !== '' ? $row['dept_code'] : $row['dept_name'],
                $row['semester_label'],
                $row['roll'],
                $row['attendance'] === null ? '' : (string)$row['attendance'],
                $row['marks'] === null ? '' : (string)$row['marks'],
            ]);
        }
        fclose($out);
        exit;
    }

    /** @return array<string,mixed> */
    private function pack(): array
    {
        $this->requireRole('admin', 'superadmin');
        $instId = (int)$this->user()['institution_id'];
        $filters = [
            'q' => trim((string)$this->get('q', '')),
            'department_id' => (int)$this->get('department_id', 0),
            'semester' => strtolower(trim((string)$this->get('semester', ''))),
        ];
        if (!in_array($filters['semester'], ['odd', 'even'], true)) {
            $filters['semester'] = '';
        }

        $students = User::forInstitution($instId, ['role' => 'student', 'is_active' => 1]);
        $attendance = $this->attendanceByStudent($instId);
        $marks = $this->marksByStudent($instId);

        $all = [];
        $detention = 0;
        $low = 0;
        foreach ($students as $student) {
            $id = (int)$student['id'];
            $att = $attendance[$id] ?? null;
            $pct = $att['percent'] ?? null;
            if ($pct !== null && $pct < 65) {
                $detention++;
            }
            if ($pct !== null && $pct < 75) {
                $low++;
            }
            $year = (int)($student['academic_year_level'] ?? 0);
            if ($year < 1) {
                $year = (int)($student['class_year'] ?? 0);
            }
            $semester = student_semester($student);
            $all[] = [
                'id' => $id,
                'name' => (string)($student['full_name'] ?? ''),
                'initials' => $this->initials((string)($student['full_name'] ?? '')),
                'dept_id' => (int)($student['department_id'] ?? 0),
                'dept_name' => (string)($student['dept_name'] ?? ''),
                'dept_code' => (string)($student['dept_code'] ?? ''),
                'semester_key' => subject_semester_key($semester),
                'semester_label' => $semester,
                'year_label' => $year > 0 ? subject_year_label($year) : '',
                'roll' => trim((string)($student['register_no'] ?? '')),
                'attendance' => $pct,
                'marks' => $marks[$id] ?? null,
            ];
        }

        $q = mb_strtolower($filters['q']);
        $rows = array_values(array_filter($all, static function (array $row) use ($filters, $q): bool {
            if ($filters['department_id'] > 0 && $row['dept_id'] !== $filters['department_id']) {
                return false;
            }
            if ($filters['semester'] !== '' && $row['semester_key'] !== $filters['semester']) {
                return false;
            }
            if ($q === '') {
                return true;
            }
            $hay = mb_strtolower($row['name'] . ' ' . $row['roll']);
            return str_contains($hay, $q);
        }));

        $enrolled = count($all);
        $year = institution_academic_year($instId);
        $sem = subject_normalize_semester(institution_current_semester($instId));
        $subtitle = $enrolled . ' enrolled'
            . ($sem !== '' ? ' · ' . $sem : '')
            . ($year !== '' ? ' · ' . $year : '');

        $query = array_filter([
            'q' => $filters['q'],
            'department_id' => $filters['department_id'] > 0 ? (string)$filters['department_id'] : '',
            'semester' => $filters['semester'],
        ], static fn($v) => $v !== '');

        return [
            'rows' => $rows,
            'summary' => [
                'enrolled' => $enrolled,
                'detention' => $detention,
                'low' => $low,
                'shown' => count($rows),
            ],
            'departments' => Department::forInstitution($instId),
            'filters' => $filters,
            'subtitle' => $subtitle,
            'exportQuery' => $query ? ('?' . http_build_query($query)) : '',
        ];
    }

    /** @return array<int,array{percent:float}> */
    private function attendanceByStudent(int $instId): array
    {
        $rows = Database::fetchAll(
            'SELECT r.student_id,
                    SUM(r.status IN ("present","late")) AS presentish,
                    COUNT(r.id) AS total_rows
             FROM attendance_records r
             JOIN attendance_sessions s ON s.id = r.session_id
             WHERE s.institution_id = ? AND r.student_id > 0
             GROUP BY r.student_id',
            [$instId]
        );
        $out = [];
        foreach ($rows as $row) {
            $total = (int)$row['total_rows'];
            if ($total < 1) {
                continue;
            }
            $out[(int)$row['student_id']] = [
                'percent' => round(((float)$row['presentish'] * 100) / $total, 1),
            ];
        }
        return $out;
    }

    /** @return array<int,float> */
    private function marksByStudent(int $instId): array
    {
        $rows = Database::fetchAll(
            'SELECT m.student_id, m.computed_total, m.meta, f.total_max AS formula_total_max
             FROM internal_marks m
             LEFT JOIN marks_formulas f ON f.id = m.formula_id AND f.institution_id = m.institution_id
             WHERE m.institution_id = ? AND m.student_id > 0',
            [$instId]
        );
        $pcts = [];
        foreach ($rows as $row) {
            $meta = json_decode((string)($row['meta'] ?? '{}'), true) ?: [];
            $max = (float)($meta['total_max'] ?? $row['formula_total_max'] ?? 0);
            $total = $row['computed_total'];
            if ($total === null || $total === '' || $max <= 0) {
                continue;
            }
            $pcts[(int)$row['student_id']][] = ((float)$total * 100) / $max;
        }
        $out = [];
        foreach ($pcts as $id => $list) {
            $out[$id] = round(array_sum($list) / count($list), 1);
        }
        return $out;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr((string)($parts[0] ?? 'S'), 0, 1);
        $last = count($parts) > 1 ? mb_substr((string)$parts[count($parts) - 1], 0, 1) : '';
        $text = strtoupper($first . $last);
        return $text !== '' ? $text : 'S';
    }
}
