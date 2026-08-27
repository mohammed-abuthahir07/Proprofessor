<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use App\Models\Institution;
use App\Models\User;
use Auth;
use Database;
use Permissions;

final class UserController extends Controller
{
    private const ROLES = ['professor', 'student', 'hod', 'admin'];
    private const PROTECTED_ADMIN_ID = 1;

    public function index(): void
    {
        require_admin_perm('manage_users');
        ensure_student_academic_schema();
        $user = $this->user();
        $instId = (int)$user['institution_id'];

        if ($this->get('export') === 'template') {
            $this->downloadTemplate();
        }

        $filters = [
            'role' => (string)$this->get('role', ''),
            'department_id' => $this->get('department_id', ''),
            'class_id' => $this->get('class_id', ''),
            'is_active' => $this->get('status', ''),
            'program_level' => strtoupper((string)$this->get('program_level', '')),
            'year' => $this->get('year', ''),
        ];
        $editId = (int)$this->get('edit', 0);
        $editing = $editId ? User::inInstitution($editId, $instId) : null;

        $allUsers = User::forInstitution($instId, $filters);
        $perPage = 20;
        $totalUsers = count($allUsers);
        $totalPages = max(1, (int)ceil($totalUsers / $perPage));
        $page = max(1, min($totalPages, (int)$this->get('page', 1)));
        $users = array_slice($allUsers, ($page - 1) * $perPage, $perPage);

        $this->view('admin/users', [
            'title' => 'Users & Roles',
            'active' => 'users',
            'subtitle' => 'Add, assign, import, and control access — this college only',
            'users' => $users,
            'userPage' => $page,
            'userTotalPages' => $totalPages,
            'userTotal' => $totalUsers,
            'userPerPage' => $perPage,
            'depts' => Department::forInstitution($instId),
            'classes' => Database::fetchAll(
                'SELECT c.*, d.name AS dept_name, d.code AS dept_code FROM classes c
                 LEFT JOIN departments d ON d.id = c.department_id
                 WHERE c.institution_id = ? ORDER BY d.name, c.year, c.section, c.name',
                [$instId]
            ),
            'filters' => $filters,
            'editing' => $editing,
            'permissions' => Permissions::catalog(),
            'editPerms' => $editing ? (Permissions::listed($editing) ?? []) : [],
            'editIsFull' => $editing ? Permissions::isFullAdmin($editing) : true,
        ]);
    }

    public function store(): void
    {
        require_admin_perm('manage_users');
        ensure_student_academic_schema();
        $this->verifyCsrf();
        $actor = $this->user();
        $action = (string)$this->post('action');

        match ($action) {
            'create' => $this->createUser($actor),
            'update' => $this->updateUser($actor),
            'toggle' => $this->toggleUser($actor),
            'reset_password' => $this->resetPassword($actor),
            'import' => $this->importUsers($actor),
            'add_class' => $this->addClass($actor),
            default => null,
        };

        $this->redirect('/admin/users');
    }

    private function createUser(array $actor): void
    {
        $payload = $this->validatedPayload((int)$actor['institution_id'], false);
        if ($payload === null) {
            return;
        }
        try {
            $newId = User::create($payload['row']);
        } catch (\PDOException $e) {
            $dup = isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062;
            $this->flash('error', $dup ? 'That email is already in use.' : 'Could not create the user.');
            return;
        }
        $this->syncHodLink((int)$actor['institution_id'], $newId, $payload['row']['role'], $payload['row']['department_id'], null);
        log_activity('user_create', 'user', $newId, ['role' => $payload['row']['role']]);
        $this->flash('success', 'User created in this institution.');
    }

    private function updateUser(array $actor): void
    {
        $targetId = (int)$this->post('user_id');
        $existing = User::inInstitution($targetId, (int)$actor['institution_id']);
        if (!$existing) {
            $this->flash('error', 'User not found in this institution.');
            return;
        }
        if ($this->isProtectedAdmin($existing) && $targetId !== (int)$actor['id']) {
            $this->flash('error', 'The primary College Admin account cannot be edited here.');
            return;
        }
        $payload = $this->validatedPayload((int)$actor['institution_id'], true, $existing);
        if ($payload === null) {
            return;
        }
        if ($this->isProtectedAdmin($existing)) {
            $payload['row']['role'] = 'admin';
            $payload['row']['extra'] = null;
            $payload['row']['is_active'] = 1;
        }
        try {
            User::updateById($targetId, $payload['row']);
        } catch (\PDOException $e) {
            $dup = isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062;
            $this->flash('error', $dup ? 'That email is already in use.' : 'Could not update the user.');
            return;
        }
        $this->syncHodLink(
            (int)$actor['institution_id'],
            $targetId,
            $payload['row']['role'],
            $payload['row']['department_id'],
            $existing
        );
        if ($targetId === (int)$actor['id']) {
            Auth::refresh();
        }
        log_activity('user_update', 'user', $targetId);
        $this->flash('success', 'User updated.');
    }

    private function toggleUser(array $actor): void
    {
        $targetId = (int)$this->post('user_id');
        $existing = User::inInstitution($targetId, (int)$actor['institution_id']);
        if (!$existing) {
            $this->flash('error', 'User not found in this institution.');
            return;
        }
        if ($targetId === (int)$actor['id'] || $this->isProtectedAdmin($existing)) {
            $this->flash('error', 'You cannot remove access from this account.');
            return;
        }
        Database::query(
            'UPDATE users SET is_active = 1 - is_active WHERE id = ? AND institution_id = ?',
            [$targetId, $actor['institution_id']]
        );
        $nowActive = !(int)$existing['is_active'];
        log_activity($nowActive ? 'user_restore' : 'user_remove', 'user', $targetId);
        $this->flash('success', $nowActive ? 'Access restored.' : 'Access removed. The user can no longer sign in.');
    }

    private function resetPassword(array $actor): void
    {
        $targetId = (int)$this->post('user_id');
        $existing = User::inInstitution($targetId, (int)$actor['institution_id']);
        if (!$existing) {
            $this->flash('error', 'User not found in this institution.');
            return;
        }
        if ($this->isProtectedAdmin($existing)) {
            $this->flash('error', 'The primary College Admin password cannot be changed here.');
            return;
        }
        $password = trim((string)$this->post('new_password'));
        if (strlen($password) < 8) {
            $this->flash('error', 'New password must be at least 8 characters.');
            return;
        }
        Database::query(
            'UPDATE users SET password_hash = ? WHERE id = ? AND institution_id = ?',
            [password_hash($password, PASSWORD_BCRYPT), $targetId, $actor['institution_id']]
        );
        log_activity('user_password_reset', 'user', $targetId);
        $this->flash('success', 'Password updated. Share it with the user securely.');
    }

    private function addClass(array $actor): void
    {
        $deptId = (int)$this->post('department_id');
        $name = trim((string)$this->post('class_name'));
        $dept = $deptId ? Database::fetch(
            'SELECT id FROM departments WHERE id = ? AND institution_id = ?',
            [$deptId, $actor['institution_id']]
        ) : null;
        if (!$dept || $name === '') {
            $this->flash('error', 'Class name and department are required.');
            return;
        }
        $inst = Institution::find((int)$actor['institution_id']);
        $year = (int)$this->post('year');
        $level = strtoupper(trim((string)$this->post('program_level')));
        if ($year < 1 || $year > 4) {
            $this->flash('error', 'Select student year 1, 2, 3, or 4.');
            return;
        }
        if (!in_array($level, ['UG', 'PG'], true)) {
            $this->flash('error', 'Select UG or PG.');
            return;
        }
        Database::insert('classes', [
            'institution_id' => $actor['institution_id'],
            'department_id' => $deptId,
            'name' => $name,
            'section' => trim((string)$this->post('section')) ?: null,
            'year' => $year,
            'academic_year' => $inst['academic_year'] ?? null,
            'meta' => json_encode(['level' => $level]),
            'is_active' => 1,
        ]);
        $this->flash('success', 'Class added. It now appears in the student Class list as Year ' . $year . ' · ' . $level . '.');
    }

    private function importUsers(array $actor): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            $this->flash('error', 'Choose an Excel/CSV file to import.');
            return;
        }
        $name = (string)($_FILES['import_file']['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'tsv'], true)) {
            $this->flash('error', 'Upload a .csv exported from Excel (File → Save As → CSV).');
            return;
        }
        $fh = fopen($_FILES['import_file']['tmp_name'], 'r');
        if (!$fh) {
            $this->flash('error', 'Could not read the import file.');
            return;
        }
        $created = 0;
        $skipped = 0;
        $errors = 0;
        $rowNum = 0;
        $depts = [];
        foreach (Department::forInstitution((int)$actor['institution_id']) as $d) {
            $depts[strtoupper((string)$d['code'])] = (int)$d['id'];
            $depts[strtoupper((string)$d['name'])] = (int)$d['id'];
        }
        $classes = [];
        foreach (Database::fetchAll('SELECT id, name FROM classes WHERE institution_id = ?', [$actor['institution_id']]) as $c) {
            $classes[strtoupper((string)$c['name'])] = (int)$c['id'];
        }

        while (($row = fgetcsv($fh)) !== false) {
            $rowNum++;
            if ($row === [null] || $row === false) {
                continue;
            }
            $cells = array_map(static fn($v) => trim((string)$v), $row);
            if ($rowNum === 1 && $this->isHeaderRow($cells)) {
                continue;
            }
            if ($cells === [] || ($cells[0] ?? '') === '') {
                continue;
            }
            $fullName = $cells[0] ?? '';
            $email = $cells[1] ?? '';
            $role = strtolower($cells[2] ?? '');
            if ($role === 'sub-admin' || $role === 'subadmin' || $role === 'college_admin') {
                $role = 'admin';
            }
            $deptKey = strtoupper($cells[3] ?? '');
            $classKey = strtoupper($cells[4] ?? '');
            $employeeId = $cells[5] ?? '';
            $registerNo = $cells[6] ?? '';
            $password = ($cells[7] ?? '') !== '' ? $cells[7] : 'Password@123';
            $phone = $cells[8] ?? '';
            if ($fullName === '' || $email === '' || !in_array($role, self::ROLES, true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors++;
                continue;
            }
            $deptId = $deptKey !== '' ? ($depts[$deptKey] ?? null) : null;
            $classId = $classKey !== '' ? ($classes[$classKey] ?? null) : null;
            if (in_array($role, ['hod', 'professor', 'student'], true) && !$deptId) {
                $errors++;
                continue;
            }
            try {
                $newId = User::create([
                    'institution_id' => $actor['institution_id'],
                    'department_id' => $deptId,
                    'role' => $role,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'full_name' => $fullName,
                    'class_id' => $role === 'student' ? $classId : null,
                    'register_no' => $registerNo !== '' ? $registerNo : null,
                    'employee_id' => $employeeId !== '' ? $employeeId : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'is_active' => 1,
                ]);
                $this->syncHodLink((int)$actor['institution_id'], $newId, $role, $deptId, null);
                $created++;
            } catch (\PDOException $e) {
                $skipped++;
            }
        }
        fclose($fh);
        log_activity('user_import', 'user', null, compact('created', 'skipped', 'errors'));
        $this->flash('success', "Imported {$created} user(s). Skipped {$skipped} duplicate(s). Invalid rows: {$errors}.");
    }

    /** @return array{row: array<string,mixed>}|null */
    private function validatedPayload(int $institutionId, bool $isUpdate, ?array $existing = null): ?array
    {
        $email = trim((string)$this->post('email'));
        $fullName = trim((string)$this->post('full_name'));
        $role = (string)$this->post('role');
        if ($fullName === '' || $email === '' || !in_array($role, self::ROLES, true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Name, valid email, and a role are required.');
            return null;
        }
        $deptId = $this->resolveDept($institutionId, (int)$this->post('department_id'));
        $classId = $this->resolveClass($institutionId, (int)$this->post('class_id'), $deptId);
        if (in_array($role, ['hod', 'professor', 'student'], true) && !$deptId) {
            $this->flash('error', 'Department is required for HOD, Professor, and Student.');
            return null;
        }

        $academicYearLevel = null;
        $semester = null;
        if ($role === 'student') {
            if (!$classId) {
                $this->flash('error', 'Class / section is required for students.');
                return null;
            }
            $academicYearLevel = (int)$this->post('academic_year_level');
            if ($academicYearLevel < 1 || $academicYearLevel > 4) {
                $this->flash('error', 'Select a valid academic year (1st–4th).');
                return null;
            }
            $semesterKey = strtolower(trim((string)$this->post('semester')));
            if (!in_array($semesterKey, ['odd', 'even'], true)) {
                $this->flash('error', 'Select semester: Odd or Even.');
                return null;
            }
            $semester = subject_normalize_semester($semesterKey);
            $class = Database::fetch(
                'SELECT id, year, department_id FROM classes WHERE id = ? AND institution_id = ?',
                [$classId, $institutionId]
            );
            if (!$class) {
                $this->flash('error', 'Class not found in this institution.');
                return null;
            }
            if ((int)$class['year'] !== $academicYearLevel) {
                $this->flash('error', 'Selected class year must match the student’s academic year.');
                return null;
            }
            if ($deptId && (int)$class['department_id'] > 0 && (int)$class['department_id'] !== $deptId) {
                $this->flash('error', 'Selected class must belong to the student’s department.');
                return null;
            }
        }

        $perms = $this->post('permissions');
        $extra = null;
        if ($role === 'admin' && is_array($perms) && $perms !== []) {
            $extra = Permissions::encode(array_map('strval', $perms));
        }
        $row = [
            'institution_id' => $institutionId,
            'department_id' => $deptId,
            'role' => $role,
            'email' => $email,
            'full_name' => $fullName,
            'class_id' => $role === 'student' ? $classId : null,
            'academic_year_level' => $role === 'student' ? $academicYearLevel : null,
            'semester' => $role === 'student' ? $semester : null,
            'register_no' => trim((string)$this->post('register_no')) ?: null,
            'employee_id' => trim((string)$this->post('employee_id')) ?: null,
            'phone' => trim((string)$this->post('phone')) ?: null,
            'extra' => $extra,
        ];
        if (!$isUpdate) {
            $password = (string)$this->post('password', 'Password@123');
            if ($password === '') {
                $password = 'Password@123';
            }
            $row['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
            $row['is_active'] = 1;
        } elseif ($existing) {
            unset($row['institution_id']);
        }
        return ['row' => $row];
    }

    private function resolveDept(int $institutionId, int $deptId): ?int
    {
        if ($deptId < 1) {
            return null;
        }
        $row = Database::fetch(
            'SELECT id FROM departments WHERE id = ? AND institution_id = ?',
            [$deptId, $institutionId]
        );
        return $row ? (int)$row['id'] : null;
    }

    private function resolveClass(int $institutionId, int $classId, ?int $departmentId = null): ?int
    {
        if ($classId < 1) {
            return null;
        }
        $sql = 'SELECT id, department_id FROM classes WHERE id = ? AND institution_id = ?';
        $params = [$classId, $institutionId];
        $row = Database::fetch($sql, $params);
        if (!$row) {
            return null;
        }
        if ($departmentId && (int)$row['department_id'] > 0 && (int)$row['department_id'] !== $departmentId) {
            return null;
        }
        return (int)$row['id'];
    }

    private function syncHodLink(int $institutionId, int $userId, string $role, ?int $deptId, ?array $previous): void
    {
        if ($previous && ($previous['role'] ?? '') === 'hod') {
            Database::query(
                'UPDATE departments SET hod_user_id = NULL WHERE hod_user_id = ? AND institution_id = ?',
                [$userId, $institutionId]
            );
        }
        if ($role === 'hod' && $deptId) {
            Database::query(
                'UPDATE departments SET hod_user_id = ? WHERE id = ? AND institution_id = ?',
                [$userId, $deptId, $institutionId]
            );
        }
    }

    private function isProtectedAdmin(array $user): bool
    {
        return (int)$user['id'] === self::PROTECTED_ADMIN_ID
            || strcasecmp((string)$user['email'], 'admin@proprofessor.local') === 0;
    }

    /** @param list<string> $cells */
    private function isHeaderRow(array $cells): bool
    {
        $first = strtolower($cells[0] ?? '');
        return in_array($first, ['full_name', 'name', 'fullname'], true);
    }

    private function downloadTemplate(): never
    {
        $lines = [
            ['full_name', 'email', 'role', 'department_code', 'class_name', 'employee_id', 'register_no', 'password', 'phone'],
            ['Priya Sharma', 'priya@college.edu', 'professor', 'CSE', '', 'EMP102', '', 'Password@123', ''],
            ['Arun Kumar', 'arun.s@college.edu', 'student', 'CSE', 'CSE-A', '', '224301', 'Password@123', ''],
            ['Meena Iyer', 'meena@college.edu', 'hod', 'ECE', '', 'EMP088', '', 'Password@123', ''],
            ['Office Admin', 'office@college.edu', 'admin', '', '', 'ADM002', '', 'Password@123', ''],
        ];
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="proprofessor-users-import.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        foreach ($lines as $line) {
            fputcsv($out, $line);
        }
        exit;
    }
}
