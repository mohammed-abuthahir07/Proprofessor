<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use App\Models\Institution;
use Database;

final class InstitutionController extends Controller
{
    public function index(): void
    {
        require_admin_perm('manage_institution');
        $user = $this->user();
        $inst = Institution::find((int)$user['institution_id']);
        $instId = (int)$user['institution_id'];
        $depts = Department::forInstitution($instId);
        $settings = json_decode($inst['settings'] ?: '{}', true) ?: [];

        $this->view('admin/institution', [
            'title' => 'Institution Setup',
            'active' => 'institution',
            'inst' => $inst,
            'depts' => $depts,
            'classes' => Database::fetchAll(
                'SELECT c.*, d.name AS dept_name, d.code AS dept_code FROM classes c
                 LEFT JOIN departments d ON d.id = c.department_id
                 WHERE c.institution_id = ? ORDER BY d.name, c.year, c.section, c.name',
                [$instId]
            ),
            'settings' => $settings,
        ]);
    }

    public function save(): void
    {
        require_admin_perm('manage_institution');
        $this->verifyCsrf();
        $user = $this->user();
        $inst = Institution::find((int)$user['institution_id']);

        if ($this->post('action') === 'save_inst') {
            $existing = json_decode((string)($inst['settings'] ?? '{}'), true) ?: [];
            $existing['attendance_min'] = (float)$this->post('attendance_min', 75);
            foreach (['brand_primary', 'brand_secondary', 'brand_accent'] as $key) {
                $raw = trim((string)$this->post($key, ''));
                if ($raw === '') {
                    continue;
                }
                $hex = ltrim($raw, '#');
                if (preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
                    $existing[$key] = strtoupper($hex);
                }
            }
            $logo = trim((string)$this->post('logo_url', ''));
            Institution::updateById((int)$user['institution_id'], [
                'name' => $this->post('name'),
                'affiliation_university' => $this->post('affiliation_university'),
                'naac_grade' => $this->post('naac_grade'),
                'academic_year' => $this->post('academic_year'),
                'current_semester' => $this->post('current_semester'),
                'city' => $this->post('city'),
                'state' => $this->post('state'),
                'logo_url' => $logo !== '' ? $logo : null,
                'settings' => json_encode($existing, JSON_UNESCAPED_UNICODE),
            ]);
            $this->flash('success', 'Institution updated.');
        }

        if ($this->post('action') === 'add_dept') {
            Department::create([
                'institution_id' => $user['institution_id'],
                'name' => $this->post('dept_name'),
                'code' => $this->post('dept_code'),
            ]);
            $this->flash('success', 'Department added.');
        }

        if ($this->post('action') === 'add_class') {
            $deptId = (int)$this->post('department_id');
            $name = trim((string)$this->post('class_name'));
            $dept = $deptId ? Database::fetch(
                'SELECT id FROM departments WHERE id = ? AND institution_id = ?',
                [$deptId, $user['institution_id']]
            ) : null;
            if (!$dept || $name === '') {
                $this->flash('error', 'Class name and department are required.');
                $this->redirect('/admin/institution');
            }
            $year = (int)$this->post('year');
            $level = strtoupper(trim((string)$this->post('program_level')));
            if ($year < 1 || $year > 4 || !in_array($level, ['UG', 'PG'], true)) {
                $this->flash('error', 'Select UG/PG and year 1–4 for the class.');
                $this->redirect('/admin/institution');
            }
            Database::insert('classes', [
                'institution_id' => $user['institution_id'],
                'department_id' => $deptId,
                'name' => $name,
                'section' => trim((string)$this->post('section')) ?: null,
                'year' => $year,
                'semester' => trim((string)$this->post('semester')) ?: null,
                'academic_year' => $inst['academic_year'] ?? null,
                'meta' => json_encode(['level' => $level]),
                'is_active' => 1,
            ]);
            $this->flash('success', 'Class added.');
        }

        $this->redirect('/admin/institution');
    }
}
