<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use App\Models\User;
use Database;

final class FacultyController extends Controller
{
    private const PAGE_SIZE = 10;

    public function index(): void
    {
        $pack = $this->pack();
        $this->view('admin/faculty', [
            'title' => 'Faculty',
            'active' => 'faculty',
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
        header('Content-Disposition: attachment; filename="faculty.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Name', 'Department', 'Employee ID', 'Qualification', 'Plans done %']);
        foreach ($pack['rows'] as $row) {
            fputcsv($out, [
                $row['name'],
                $row['dept_code'] !== '' ? $row['dept_code'] : $row['dept_name'],
                $row['employee_id'],
                $row['qualification'],
                $row['plans'] === null ? '' : (string)$row['plans'],
            ]);
        }
        fclose($out);
        exit;
    }

    /** @return array<string,mixed> */
    private function pack(): array
    {
        $this->requireRole('admin', 'superadmin');
        ensure_professor_qualification_schema();
        $instId = (int)$this->user()['institution_id'];
        $filters = [
            'q' => trim((string)$this->get('q', '')),
            'department_id' => (int)$this->get('department_id', 0),
        ];

        $faculty = User::forInstitution($instId, ['role' => 'professor', 'is_active' => 1]);
        $plans = $this->plansByProfessor($instId);
        $subjects = $this->subjectsByProfessor($instId);

        $all = [];
        $phd = 0;
        $approvedTotal = 0;
        $planTotal = 0;
        foreach ($faculty as $member) {
            $id = (int)$member['id'];
            $plan = $plans[$id] ?? ['approved' => 0, 'total' => 0];
            $approvedTotal += $plan['approved'];
            $planTotal += $plan['total'];
            $qualification = trim((string)($member['qualification'] ?? ''));
            if ($qualification !== '' && preg_match('/ph\s*\.?\s*d/i', $qualification) === 1) {
                $phd++;
            }
            $all[] = [
                'id' => $id,
                'name' => (string)($member['full_name'] ?? ''),
                'initials' => $this->initials((string)($member['full_name'] ?? '')),
                'designation' => trim((string)($member['designation'] ?? '')),
                'dept_id' => (int)($member['department_id'] ?? 0),
                'dept_name' => (string)($member['dept_name'] ?? ''),
                'dept_code' => (string)($member['dept_code'] ?? ''),
                'employee_id' => trim((string)($member['employee_id'] ?? '')),
                'qualification' => $qualification,
                'subjects' => $subjects[$id] ?? [],
                'plans' => $plan['total'] > 0
                    ? round(($plan['approved'] * 100) / $plan['total'], 1)
                    : null,
            ];
        }

        $q = mb_strtolower($filters['q']);
        $rows = array_values(array_filter($all, static function (array $row) use ($filters, $q): bool {
            if ($filters['department_id'] > 0 && $row['dept_id'] !== $filters['department_id']) {
                return false;
            }
            if ($q === '') {
                return true;
            }
            $hay = mb_strtolower(implode(' ', [
                $row['name'],
                $row['employee_id'],
                $row['qualification'],
                $row['dept_name'],
                $row['dept_code'],
                implode(' ', $row['subjects']),
            ]));
            return str_contains($hay, $q);
        }));

        $total = count($all);
        $year = institution_academic_year($instId);
        $subtitle = $total . ($total === 1 ? ' faculty member' : ' faculty members')
            . ($year !== '' ? ' · ' . $year : '');

        $query = array_filter([
            'q' => $filters['q'],
            'department_id' => $filters['department_id'] > 0 ? (string)$filters['department_id'] : '',
        ], static fn($v) => $v !== '');

        return [
            'rows' => $rows,
            'summary' => [
                'total' => $total,
                'phd' => $phd,
                'plans' => $planTotal > 0 ? round(($approvedTotal * 100) / $planTotal, 1) : null,
                'shown' => count($rows),
            ],
            'departments' => Department::forInstitution($instId),
            'filters' => $filters,
            'subtitle' => $subtitle,
            'exportQuery' => $query ? ('?' . http_build_query($query)) : '',
        ];
    }

    /** @return array<int,array{approved:int,total:int}> */
    private function plansByProfessor(int $instId): array
    {
        $rows = Database::fetchAll(
            'SELECT professor_id,
                    SUM(status = "approved") AS approved,
                    COUNT(*) AS total
             FROM course_plans
             WHERE institution_id = ?
             GROUP BY professor_id',
            [$instId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['professor_id']] = [
                'approved' => (int)$row['approved'],
                'total' => (int)$row['total'],
            ];
        }
        return $out;
    }

    /** @return array<int,list<string>> */
    private function subjectsByProfessor(int $instId): array
    {
        $rows = Database::fetchAll(
            'SELECT sa.professor_id, s.name
             FROM subject_assignments sa
             JOIN subjects s ON s.id = sa.subject_id
             WHERE s.institution_id = ?
             ORDER BY s.name',
            [$instId]
        );
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $id = (int)$row['professor_id'];
            if (!in_array($name, $out[$id] ?? [], true)) {
                $out[$id][] = $name;
            }
        }
        return $out;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }
        return $letters !== '' ? $letters : 'F';
    }
}
