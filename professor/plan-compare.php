<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();

$id = (int)get('id');
$plan = Database::fetch(
    'SELECT * FROM course_plans WHERE id = ? AND professor_id = ? AND institution_id = ?',
    [$id, (int)$user['id'], (int)$user['institution_id']]
);
if (!$plan) {
    flash('error', 'Plan not found.');
    redirect('/professor/plans.php');
}
if (!CoursePlanTools::canViewPlan($user, $plan)) {
    flash('error', 'Access denied.');
    redirect('/professor/plans.php');
}

$versions = Database::fetchAll(
    'SELECT id, version, change_note, created_at, snapshot FROM course_plan_versions WHERE plan_id = ? ORDER BY version ASC',
    [$id]
);
if (count($versions) < 2) {
    flash('error', 'This plan needs at least two saved versions to compare.');
    redirect('/professor/plan-view.php?id=' . $id . '&tab=versions');
}

$compareA = (int)get('v1');
$compareB = (int)get('v2');
if ($compareA < 1) {
    $compareA = (int)$versions[0]['version'];
}
if ($compareB < 1) {
    $compareB = (int)$versions[count($versions) - 1]['version'];
}

$diff = null;
$labelA = '';
$labelB = '';
$snapA = null;
$snapB = null;
foreach ($versions as $v) {
    $ver = (int)$v['version'];
    $ay = '';
    $snap = json_decode((string)$v['snapshot'], true) ?: [];
    if (is_array($snap)) {
        $ay = trim((string)($snap['academic_year'] ?? $plan['academic_year'] ?? ''));
    }
    $label = 'Version ' . $ver . ($ay !== '' ? ' → ' . $ay : '') . ($v['change_note'] ? ' · ' . $v['change_note'] : '');
    if ($ver === $compareA) {
        $snapA = $snap;
        $labelA = $label;
    }
    if ($ver === $compareB) {
        $snapB = $snap;
        $labelB = $label;
    }
}
if ($compareA !== $compareB && is_array($snapA) && is_array($snapB) && $snapA && $snapB) {
    $diff = CoursePlanTools::diffSnapshots($snapA, $snapB);
}

render_header('Compare Versions', 'plans', [
    'subtitle' => (string)$plan['subject_name'] . ' · v' . (int)$plan['version'],
]);
?>
<div class="panel">
  <div class="panel-h">
    <h2><?= e((string)$plan['title']) ?></h2>
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/professor/plans.php')) ?>">← My Plans</a>
  </div>
  <p style="color:var(--muted);margin:0 0 1rem">Select two saved versions from <code>course_plan_versions</code>. Diff uses stored snapshots only.</p>

  <form method="get" class="form-grid" style="grid-template-columns:1fr 1fr auto;gap:.65rem;align-items:end">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-row">
      <label>Version A</label>
      <select name="v1">
        <?php foreach ($versions as $v):
          $ver = (int)$v['version'];
          $snap = json_decode((string)$v['snapshot'], true) ?: [];
          $ay = trim((string)($snap['academic_year'] ?? $plan['academic_year'] ?? ''));
          $opt = 'Version ' . $ver . ($ay !== '' ? ' → ' . $ay : '');
        ?>
          <option value="<?= $ver ?>" <?= $compareA === $ver ? 'selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label>Version B</label>
      <select name="v2">
        <?php foreach ($versions as $v):
          $ver = (int)$v['version'];
          $snap = json_decode((string)$v['snapshot'], true) ?: [];
          $ay = trim((string)($snap['academic_year'] ?? $plan['academic_year'] ?? ''));
          $opt = 'Version ' . $ver . ($ay !== '' ? ' → ' . $ay : '');
        ?>
          <option value="<?= $ver ?>" <?= $compareB === $ver ? 'selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Compare</button>
  </form>
</div>

<?php if ($compareA === $compareB): ?>
  <div class="panel"><div class="empty">Choose two different versions.</div></div>
<?php elseif (!$diff): ?>
  <div class="panel"><div class="empty">Could not load one of the version snapshots.</div></div>
<?php else: ?>
  <div class="panel">
    <p style="margin:0 0 .75rem"><strong><?= e($labelA) ?></strong> → <strong><?= e($labelB) ?></strong></p>
    <div class="grid grid-2">
      <div class="panel" style="margin:0">
        <h3>Units / topics added</h3>
        <?php if (!$diff['added_topics'] && !array_filter($diff['changed_units'], static fn($c) => str_starts_with((string)($c['detail'] ?? ''), 'Added:'))): ?>
          <div class="empty">None</div>
        <?php else: ?>
          <ul>
            <?php foreach ($diff['changed_units'] as $c):
              if (!str_starts_with((string)$c['detail'], 'Added:')) continue;
            ?>
              <li><strong><?= e($c['label']) ?>:</strong> <?= e($c['detail']) ?></li>
            <?php endforeach; ?>
            <?php foreach ($diff['added_topics'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="panel" style="margin:0">
        <h3>Units / topics removed</h3>
        <?php if (!$diff['removed_topics'] && !array_filter($diff['changed_units'], static fn($c) => str_starts_with((string)($c['detail'] ?? ''), 'Removed:'))): ?>
          <div class="empty">None</div>
        <?php else: ?>
          <ul>
            <?php foreach ($diff['changed_units'] as $c):
              if (!str_starts_with((string)$c['detail'], 'Removed:')) continue;
            ?>
              <li><strong><?= e($c['label']) ?>:</strong> <?= e($c['detail']) ?></li>
            <?php endforeach; ?>
            <?php foreach ($diff['removed_topics'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="panel">
    <h3>Unit title changes</h3>
    <ul>
      <?php foreach ($diff['changed_units'] as $c):
        if (str_starts_with((string)$c['detail'], 'Added:') || str_starts_with((string)$c['detail'], 'Removed:')) continue;
      ?>
        <li><strong><?= e($c['label']) ?>:</strong> <?= e($c['detail']) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php
      $titleChanges = array_filter($diff['changed_units'], static function ($c) {
          $d = (string)($c['detail'] ?? '');
          return !str_starts_with($d, 'Added:') && !str_starts_with($d, 'Removed:');
      });
    ?>
    <?php if (!$titleChanges): ?><div class="empty">No title changes.</div><?php endif; ?>
  </div>
  <div class="grid grid-2">
    <div class="panel">
      <h3>Learning outcomes</h3>
      <?php if (!$diff['changed_outcomes']): ?><div class="empty">No outcome changes.</div>
      <?php else: ?><ul><?php foreach ($diff['changed_outcomes'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="panel">
      <h3>Hours</h3>
      <?php if (!$diff['changed_hours']): ?><div class="empty">No hours changes.</div>
      <?php else: ?><ul><?php foreach ($diff['changed_hours'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
  <div class="grid grid-2">
    <div class="panel">
      <h3>Bloom levels</h3>
      <?php if (!$diff['changed_bloom']): ?><div class="empty">No Bloom changes.</div>
      <?php else: ?><ul><?php foreach ($diff['changed_bloom'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="panel">
      <h3>CLO / PLO mapping</h3>
      <?php if (empty($diff['changed_clo_plo'])): ?><div class="empty">No CLO/PLO mapping changes.</div>
      <?php else: ?><ul><?php foreach ($diff['changed_clo_plo'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<?php render_footer(); ?>
