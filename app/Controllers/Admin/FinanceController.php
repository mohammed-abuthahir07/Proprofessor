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
        $year = (int)date('Y');
        $month = (int)$this->get('month', date('n'));
        if ($month < 1 || $month > 12) {
            $month = (int)date('n');
        }
        $topCategory = Expense::topCategoryForYear($instId, $year);
        $perPage = 4;
        $expenseTotal = Expense::countForInstitution($instId, $year, $month);
        $totalPages = max(1, (int)ceil($expenseTotal / $perPage));
        $page = (int)$this->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $this->view('admin/finance', [
            'title' => 'Finance & Expenses',
            'active' => 'finance',
            'expenses' => Expense::forInstitution($instId, $perPage, $year, $month, ($page - 1) * $perPage),
            'yearlyTotal' => Expense::totalForYear($instId, $year),
            'monthlyTotal' => Expense::totalForMonth($instId, $year, $month),
            'topCategoryName' => $topCategory['category'] ?? '',
            'topCategoryTotal' => (float)($topCategory['total'] ?? 0),
            'expenseYear' => $year,
            'expenseMonth' => $month,
            'expenseMonthLabel' => date('F Y', mktime(0, 0, 0, $month, 1, $year)),
            'expensePage' => $page,
            'expensePerPage' => $perPage,
            'expenseTotal' => $expenseTotal,
            'expenseTotalPages' => $totalPages,
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
        $savedDate = strtotime((string)$this->post('expense_date')) ?: time();
        $this->redirect('/admin/finance?month=' . (int)date('n', $savedDate));
    }
}
