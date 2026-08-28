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
/** @var list<array{year:int,stored:bool,total:float,months:array<int,array{total:float,entries:int}>,topCategoryName:string,topCategoryTotal:float}> $yearArchives */
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
$yearArchives = is_array($yearArchives ?? null) ? $yearArchives : [];
$monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
$monthFullNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
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
    <div class="finance-pdf-actions">
      <a class="btn btn-ghost" href="<?= e(url('/admin/finance/pdf?scope=month&month=' . $expenseMonth . '&year=' . $expenseYear)) ?>">
        <?= e($monthNames[$expenseMonth] ?? 'Month') ?> expense PDF
      </a>
      <a class="btn btn-primary" href="<?= e(url('/admin/finance/pdf?scope=year&year=' . $expenseYear)) ?>">
        Yearly expense PDF
      </a>
    </div>
  </div>
</div>
<?php foreach ($yearArchives as $block): ?>
  <?php
    $blockYear = (int)($block['year'] ?? 0);
    $blockStored = !empty($block['stored']);
    $blockTotal = (float)($block['total'] ?? 0);
    $blockMonths = is_array($block['months'] ?? null) ? $block['months'] : [];
    $blockTopName = trim((string)($block['topCategoryName'] ?? ''));
    $blockTopTotal = (float)($block['topCategoryTotal'] ?? 0);
    $isLiveYear = $blockYear === $expenseYear;
  ?>
<div class="panel finance-year-archive">
  <div class="panel-h" style="align-items:flex-start">
    <div>
      <h3 style="margin:0"><?= $blockYear ?> month expenses</h3>
      <p class="muted" style="margin:.35rem 0 0;font-size:.85rem">
        <?php if ($isLiveYear && !$blockStored): ?>
          This year starts at zero. Saved years stay stored below and are not cleared.
        <?php elseif ($isLiveYear): ?>
          Live <?= $blockYear ?> totals. On 1 January the cards and ledger above reset; this <?= $blockYear ?> block stays stored.
        <?php else: ?>
          Permanently stored <?= $blockYear ?> record. Not reset when a new year starts.
        <?php endif; ?>
      </p>
    </div>
    <span class="chip"><?= $blockStored ? 'Stored' : 'This year' ?> · ₹<?= number_format($blockTotal) ?></span>
  </div>
  <div class="finance-year-summary">
    <div class="finance-month-total<?= $blockTotal > 0 ? ' has-amount' : '' ?>">
      <div class="label">Year total</div>
      <div class="value">₹<?= number_format($blockTotal) ?></div>
      <div class="hint"><?= $blockYear ?></div>
    </div>
    <div class="finance-month-total<?= $blockTopName !== '' ? ' has-amount' : '' ?>">
      <div class="label">Highest category</div>
      <div class="value"><?= $blockTopName !== '' ? e($blockTopName) : '—' ?></div>
      <div class="hint"><?= $blockTopName !== '' ? '₹' . number_format($blockTopTotal) . ' · ' . $blockYear : 'No expenses yet' ?></div>
    </div>
  </div>
  <div class="finance-month-totals">
    <?php foreach ($monthFullNames as $num => $full): ?>
      <?php
        $cell = $blockMonths[$num] ?? ['total' => 0.0, 'entries' => 0];
        $cellTotal = (float)$cell['total'];
        $cellEntries = (int)$cell['entries'];
      ?>
      <div class="finance-month-total<?= $cellTotal > 0 ? ' has-amount' : '' ?>">
        <div class="label"><?= e($monthNames[$num]) ?></div>
        <div class="value">₹<?= number_format($cellTotal) ?></div>
        <div class="hint"><?= $cellEntries ?> entr<?= $cellEntries === 1 ? 'y' : 'ies' ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="finance-pdf-actions">
    <a class="btn btn-primary" href="<?= e(url('/admin/finance/pdf?scope=year&year=' . $blockYear)) ?>">
      Generate <?= $blockYear ?> PDF
    </a>
  </div>
</div>
<?php endforeach; ?>
