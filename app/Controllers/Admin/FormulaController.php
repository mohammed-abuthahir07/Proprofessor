<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use App\Models\MarksFormula;
use Database;
use Throwable;

final class FormulaController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin', 'superadmin', 'hod');
        if (($this->user()['role'] ?? '') === 'admin') {
            require_admin_perm('manage_formulas');
        }
        MarksFormula::ensureSchema();
        $user = $this->user();
        $instId = (int)$user['institution_id'];
        $editId = (int)$this->get('edit', 0);
        $edit = null;
        if ($editId > 0) {
            $edit = MarksFormula::findForInstitution($editId, $instId);
            if (!$edit) {
                $this->flash('error', 'Formula not found for your institution.');
                $this->redirect('/admin/formulas');
            }
        }
        $this->view('admin/formulas', [
            'title' => 'Marks Formulas',
            'active' => 'formulas',
            'subtitle' => 'Plain-English → calculation engine',
            'list' => MarksFormula::forInstitution($instId),
            'depts' => Department::forInstitution($instId),
            'subjects' => Database::fetchAll(
                'SELECT id, department_id, code, name, meta
                 FROM subjects
                 WHERE institution_id = ? AND is_active = 1
                 ORDER BY department_id, code, name',
                [$instId]
            ),
            'edit' => $edit,
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
        $instId = (int)$user['institution_id'];
        $formulaId = (int)$this->post('formula_id', 0);
        $components = json_decode((string)$this->post('components_json'), true);
        if (!is_array($components)) {
            $this->flash('error', 'Component JSON must be valid JSON.');
            $this->redirect($formulaId > 0 ? '/admin/formulas?edit=' . $formulaId : '/admin/formulas');
        }

        $payload = [
            'institution_id' => $instId,
            'department_id' => $this->post('department_id') ?: null,
            'subject_type' => $this->post('subject_type'),
            'subject_id' => $this->post('subject_id') ?: null,
            'name' => $this->post('name'),
            'pattern' => $this->post('pattern'),
            'plain_english' => $this->post('plain_english'),
            'components' => $components,
            'expression' => $this->post('expression'),
            'total_max' => $this->post('total_max', 25),
            'ai_parsed' => $this->post('ai_parsed') ?: null,
            'is_default' => $this->post('is_default') ? 1 : 0,
            'created_by' => (int)$user['id'],
        ];

        try {
            if ($formulaId > 0) {
                MarksFormula::updateScoped($formulaId, $instId, $payload);
                $this->flash('success', 'Formula updated.');
                $this->redirect('/admin/formulas?edit=' . $formulaId);
            }
            MarksFormula::createScoped($payload);
            $this->flash('success', 'Formula saved.');
            $this->redirect('/admin/formulas');
        } catch (Throwable $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect($formulaId > 0 ? '/admin/formulas?edit=' . $formulaId : '/admin/formulas');
        }
    }
}
