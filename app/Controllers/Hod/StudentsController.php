<?php
declare(strict_types=1);

namespace App\Controllers\Hod;

use App\Core\Controller;
use App\Models\User;

final class StudentsController extends Controller
{
    public function list(): void
    {
        $this->requireRole('hod', 'admin');
        $user = $this->user();
        if (!$user) {
            $this->json(['error' => 'Unauthorized'], 401);
            return;
        }

        $deptId = $this->resolvedDepartmentId($user);
        if ($deptId < 1) {
            $this->json(['error' => 'Your HOD account is not linked to a department.'], 403);
            return;
        }

        $instId = (int)$user['institution_id'];
        $filters = $this->parseFilters();
        $students = User::studentsForDepartment($instId, $deptId, $filters);

        $this->json([
            'department_id' => $deptId,
            'filters' => $filters,
            'count' => count($students),
            'students' => array_map(static function (array $row): array {
                $classMeta = json_decode((string)($row['class_meta'] ?? ''), true) ?: [];
                return [
                    'id' => (int)$row['id'],
                    'full_name' => (string)$row['full_name'],
                    'email' => (string)$row['email'],
                    'register_no' => (string)($row['register_no'] ?? ''),
                    'department' => [
                        'id' => (int)$row['department_id'],
                        'name' => (string)($row['dept_name'] ?? ''),
                        'code' => (string)($row['dept_code'] ?? ''),
                    ],
                    'class' => [
                        'id' => (int)($row['class_id'] ?? 0),
                        'name' => (string)($row['class_name'] ?? ''),
                        'year' => (int)($row['class_year'] ?? 0),
                        'section' => (string)($row['class_section'] ?? ''),
                        'program_level' => strtoupper((string)($classMeta['level'] ?? '')),
                    ],
                ];
            }, $students),
        ]);
    }

    private function resolvedDepartmentId(array $user): int
    {
        if (($user['role'] ?? '') === 'hod') {
            return (int)($user['department_id'] ?? 0);
        }

        $requested = (int)$this->get('department_id', 0);
        if ($requested > 0) {
            return $requested;
        }

        return (int)($user['department_id'] ?? 0);
    }

    private function parseFilters(): array
    {
        $year = (int)$this->get('year', 0);
        if ($year < 0 || $year > 4) {
            $year = 0;
        }

        $level = strtoupper(trim((string)$this->get('program_level', '')));
        if (!in_array($level, ['UG', 'PG'], true)) {
            $level = '';
        }

        return [
            'year' => $year,
            'section' => trim((string)$this->get('section', '')),
            'class_id' => (int)$this->get('class_id', 0),
            'program_level' => $level,
            'q' => trim((string)$this->get('q', '')),
        ];
    }
}
