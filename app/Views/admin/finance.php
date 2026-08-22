<?php
/** @var array $expenses */
/** @var array $byCat */
/** @var float|int $total */
/** @var array $depts */
?>
<div class="grid grid-4">
  <div class="stat"><div class="label">Total recorded</div><div class="value">₹<?= number_format((float)$total) ?></div></div>
  <?php foreach (array_slice($byCat, 0, 3) as $c): ?>
    <div class="stat"><div class="label"><?= e($c['category']) ?></div><div class="value">₹<?= number_format((float)$c['total']) ?></div></div>
  <?php endforeach; ?>
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
    <div class="table-wrap"><table>
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
  </div>
</div>
