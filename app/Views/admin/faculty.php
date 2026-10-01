<?php
/** @var list<array<string,mixed>> $rows */
/** @var array{total:int,phd:int,plans:?float,shown:int} $summary */
/** @var list<array<string,mixed>> $departments */
/** @var array{q:string,department_id:int} $filters */
/** @var int $pageSize */
/** @var string $exportQuery */
$rows = $rows ?? [];
$summary = $summary ?? ['total' => 0, 'phd' => 0, 'plans' => null, 'shown' => 0];
$departments = $departments ?? [];
$filters = $filters ?? ['q' => '', 'department_id' => 0];
$pageSize = max(1, (int)($pageSize ?? 10));
$exportQuery = (string)($exportQuery ?? '');
$shown = count($rows);
$first = min($pageSize, $shown);

$fmtPct = static function (?float $n): string {
    if ($n === null) {
        return '—';
    }
    $text = rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    return ($text === '' ? '0' : $text) . '%';
};
$tone = static function (?float $n): string {
    if ($n === null) {
        return 'is-muted';
    }
    if ($n >= 75) {
        return 'is-ok';
    }
    if ($n >= 50) {
        return 'is-warn';
    }
    return 'is-bad';
};
?>
<div class="adm-fac">
  <div class="adm-fac-kpis">
    <div class="adm-fac-kpi is-total">
      <strong><?= (int)$summary['total'] ?></strong>
      <span>Total faculty</span>
    </div>
    <div class="adm-fac-kpi is-phd">
      <strong><?= (int)$summary['phd'] ?></strong>
      <span>PhD holders</span>
    </div>
    <div class="adm-fac-kpi <?= e($tone($summary['plans'])) ?>">
      <strong><?= e($fmtPct($summary['plans'])) ?></strong>
      <span>Avg plan completion</span>
    </div>
  </div>

  <form class="adm-fac-bar" method="get" action="<?= e(url('/admin/faculty')) ?>">
    <label class="adm-fac-search">
      <span class="sr-only">Search faculty</span>
      <input type="search" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Search faculty by name or subject">
    </label>
    <select name="department_id" aria-label="Department">
      <option value="">All departments</option>
      <?php foreach ($departments as $dept): ?>
        <option value="<?= (int)$dept['id'] ?>" <?= (int)$filters['department_id'] === (int)$dept['id'] ? 'selected' : '' ?>><?= e((string)$dept['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-ghost" type="submit">Search</button>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/faculty/export' . $exportQuery)) ?>">Export</a>
    <a class="btn btn-sm btn-primary" href="<?= e(url('/admin/users')) ?>">Add Faculty</a>
  </form>

  <div class="panel adm-fac-table">
    <?php if (!$rows): ?>
      <div class="empty">No faculty match this search.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Faculty</th>
              <th>Dept</th>
              <th>Employee ID</th>
              <th>Qualification</th>
              <th>Plans done</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $row): ?>
            <tr class="adm-fac-row<?= $i >= $pageSize ? ' is-extra' : '' ?>"<?= $i >= $pageSize ? ' hidden' : '' ?>>
              <td>
                <div class="adm-fac-person">
                  <span class="adm-fac-avatar"><?= e((string)$row['initials']) ?></span>
                  <span>
                    <strong><?= e((string)$row['name']) ?></strong>
                    <?php if ($row['designation'] !== ''): ?><span class="adm-fac-sub"><?= e((string)$row['designation']) ?></span><?php endif; ?>
                  </span>
                </div>
              </td>
              <td><?= e($row['dept_code'] !== '' ? (string)$row['dept_code'] : ((string)$row['dept_name'] !== '' ? (string)$row['dept_name'] : '—')) ?></td>
              <td><?= e($row['employee_id'] !== '' ? (string)$row['employee_id'] : '—') ?></td>
              <td><?= e($row['qualification'] !== '' ? (string)$row['qualification'] : '—') ?></td>
              <td class="<?= e($tone($row['plans'])) ?>"><?= e($fmtPct($row['plans'])) ?></td>
              <td><a class="adm-fac-view" href="<?= e(url('/admin/users?edit=' . (int)$row['id'])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="adm-fac-foot">
        <span id="admFacCount">Showing <?= (int)$first ?> of <?= (int)$shown ?> faculty</span>
        <?php if ($shown > $pageSize): ?>
          <button class="btn btn-sm btn-ghost" type="button" id="admFacMore">Load more</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php if ($shown > $pageSize): ?>
<script>
document.getElementById('admFacMore')?.addEventListener('click', function () {
  document.querySelectorAll('.adm-fac-row.is-extra').forEach(function (row) { row.hidden = false; });
  this.hidden = true;
  var total = document.querySelectorAll('.adm-fac-row').length;
  var label = document.getElementById('admFacCount');
  if (label) label.textContent = 'Showing ' + total + ' of ' + total + ' faculty';
});
</script>
<?php endif; ?>
