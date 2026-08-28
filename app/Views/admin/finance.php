<?php
/** @var array $expenses */
/** @var float|int $yearlyTotal */
/** @var float|int $monthlyTotal */
/** @var string $topCategoryName */
/** @var float|int $topCategoryTotal */
/** @var int $expenseYear */
/** @var int $expenseMonth */
/** @var string $expenseMonthLabel */
/** @var int $expensePage */
/** @var int $expensePerPage */
/** @var int $expenseTotal */
/** @var int $expenseTotalPages */
/** @var array $depts */
$yearlyTotal = (float)($yearlyTotal ?? 0);
$monthlyTotal = (float)($monthlyTotal ?? 0);
$topCategoryName = trim((string)($topCategoryName ?? ''));
$topCategoryTotal = (float)($topCategoryTotal ?? 0);
$expenseYear = (int)($expenseYear ?? date('Y'));
$expenseMonth = (int)($expenseMonth ?? date('n'));
$expenseMonthLabel = (string)($expenseMonthLabel ?? date('F Y'));
$expensePage = max(1, (int)($expensePage ?? 1));
$expensePerPage = max(1, (int)($expensePerPage ?? 4));
$expenseTotal = (int)($expenseTotal ?? count($expenses ?? []));
$expenseTotalPages = max(1, (int)($expenseTotalPages ?? 1));
$monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
$ledgerUrl = static function (int $month, int $page = 1): string {
    $path = '/admin/finance?month=' . $month;
    if ($page > 1) {
        $path .= '&page=' . $page;
    }
    return url($path);
};
$showingFrom = $expenseTotal > 0 ? (($expensePage - 1) * $expensePerPage) + 1 : 0;
$showingTo = min($expensePage * $expensePerPage, $expenseTotal);
?>
<div class="grid grid-3">
  <div class="stat">
    <div class="label">Yearly expense</div>
    <div class="value">₹<?= number_format($yearlyTotal) ?></div>
    <div class="hint"><?= (int)$expenseYear ?></div>
  </div>
  <div class="stat">
    <div class="label">Monthly expenses</div>
    <div class="value">₹<?= number_format($monthlyTotal) ?></div>
    <div class="hint"><?= e($expenseMonthLabel) ?></div>
  </div>
  <div class="stat">
    <div class="label">Highest this year</div>
    <div class="value"><?= $topCategoryName !== '' ? e($topCategoryName) : '—' ?></div>
    <div class="hint"><?= $topCategoryName !== '' ? '₹' . number_format($topCategoryTotal) . ' · ' . (int)$expenseYear : 'No expenses yet' ?></div>
  </div>
</div>
<div class="grid grid-2" style="margin-top:1rem">
  <div class="panel">
    <h3>Add expense</h3>
    <form method="post" action="<?= e(url('/admin/finance')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <div class="form-row"><label>Title</label><input name="title" required></div>
      <div class="form-row two">
        <div><label>Category</label>
          <select name="category">
            <option>Salaries</option><option>Lab & Library</option><option>Infrastructure</option>
            <option>Events</option><option>Utilities</option><option>Other</option>
          </select>
        </div>
        <div><label>Amount</label><input name="amount" type="number" step="0.01" required></div>
      </div>
      <div class="form-row two">
        <div><label>Date</label><input type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>"></div>
        <div><label>Department</label>
          <select name="department_id"><option value="">Institution</option>
            <?php foreach ($depts as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row two">
        <div><label>Vendor</label><input name="vendor"></div>
        <div><label>Payment mode</label><input name="payment_mode" placeholder="NEFT / Cash"></div>
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </div>
  <div class="panel">
    <h3>Ledger</h3>
    <div class="chip-row finance-month-row" role="navigation" aria-label="Filter ledger by month">
      <?php foreach ($monthNames as $num => $label): ?>
        <a class="chip<?= $expenseMonth === $num ? ' active' : '' ?>" href="<?= e($ledgerUrl($num)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (!$expenses): ?>
      <div class="empty" style="margin-top:.75rem">No expenses in <?= e($expenseMonthLabel) ?>.</div>
    <?php else: ?>
    <div class="table-wrap" style="margin-top:.75rem"><table>
      <thead><tr><th>Date</th><th>Title</th><th>Category</th><th>Amount</th></tr></thead>
      <tbody>
      <?php foreach ($expenses as $e): ?>
        <tr>
          <td><?= e($e['expense_date']) ?></td>
          <td><?= e($e['title']) ?><div style="font-size:.75rem;color:var(--muted)"><?= e((string)$e['dept_name']) ?></div></td>
          <td><?= e($e['category']) ?></td>
          <td>₹<?= number_format((float)$e['amount'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="finance-ledger-pager">
      <div class="muted" style="font-size:.85rem">
        Showing <?= (int)$showingFrom ?>–<?= (int)$showingTo ?> of <?= (int)$expenseTotal ?>
      </div>
      <div class="chip-row" style="margin:0">
        <?php if ($expensePage > 1): ?>
          <a class="btn btn-sm btn-ghost" href="<?= e($ledgerUrl($expenseMonth, $expensePage - 1)) ?>">Previous</a>
        <?php else: ?>
          <button class="btn btn-sm btn-ghost" type="button" disabled>Previous</button>
        <?php endif; ?>
        <span class="chip">Page <?= (int)$expensePage ?> / <?= (int)$expenseTotalPages ?></span>
        <?php if ($expensePage < $expenseTotalPages): ?>
          <a class="btn btn-sm btn-primary" href="<?= e($ledgerUrl($expenseMonth, $expensePage + 1)) ?>">Next</a>
        <?php else: ?>
          <button class="btn btn-sm btn-ghost" type="button" disabled>Next</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
