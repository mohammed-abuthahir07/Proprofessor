<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use App\Models\MarksFormula;

final class FormulaController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin', 'superadmin', 'hod');
        if (($this->user()['role'] ?? '') === 'admin') {
            require_admin_perm('manage_formulas');
        }
        $user = $this->user();
        $this->view('admin/formulas', [
            'title' => 'Marks Formulas',
            'active' => 'formulas',
            'subtitle' => 'Plain-English → calculation engine',
            'list' => MarksFormula::forInstitution((int)$user['institution_id']),
            'depts' => Department::forInstitution((int)$user['institution_id']),
        ]);
    }

    public function store(): void
    {
        $this->requireRole('admin', 'superadmin', 'hod');
        if (($this->user()['role'] ?? '') === 'admin') {
            require_admin_perm('manage_formulas');
        }
        $this->verifyCsrf();
        $user = $this->user();
        $components = json_decode((string)$this->post('components_json'), true);
        if (!is_array($components)) {
            $components = [];
        }
        MarksFormula::create([
            'institution_id' => $user['institution_id'],
            'department_id' => $this->post('department_id') ?: null,
            'name' => $this->post('name'),
            'pattern' => $this->post('pattern'),
            'plain_english' => $this->post('plain_english'),
            'components' => json_encode($components),
            'expression' => $this->post('expression'),
            'total_max' => $this->post('total_max', 25),
            'ai_parsed' => $this->post('ai_parsed') ?: null,
            'is_default' => $this->post('is_default') ? 1 : 0,
            'created_by' => $user['id'],
        ]);
        $this->flash('success', 'Formula saved.');
        $this->redirect('/admin/formulas');
    }
}
