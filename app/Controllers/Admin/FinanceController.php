<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Department;
use App\Models\Expense;

final class FinanceController extends Controller
{
    public function index(): void
    {
        require_admin_perm('manage_finance');
        $user = $this->user();
        $instId = (int)$user['institution_id'];
        $byCat = Expense::totalsByCategory($instId);

        $this->view('admin/finance', [
            'title' => 'Finance & Expenses',
            'active' => 'finance',
            'expenses' => Expense::forInstitution($instId),
            'byCat' => $byCat,
            'total' => array_sum(array_column($byCat, 'total')),
            'depts' => Department::forInstitution($instId),
        ]);
    }

    public function store(): void
    {
        require_admin_perm('manage_finance');
        $this->verifyCsrf();
        $user = $this->user();
        Expense::create([
            'institution_id' => $user['institution_id'],
            'department_id' => $this->post('department_id') ?: null,
            'category' => $this->post('category'),
            'title' => $this->post('title'),
            'amount' => $this->post('amount'),
            'expense_date' => $this->post('expense_date'),
            'vendor' => $this->post('vendor'),
            'payment_mode' => $this->post('payment_mode'),
            'added_by' => $user['id'],
        ]);
        $this->flash('success', 'Expense recorded.');
        $this->redirect('/admin/finance');
    }
}
