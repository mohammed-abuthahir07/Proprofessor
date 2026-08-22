<?php
/** @var array $stats */
$user = \Auth::user();
$firstName = explode(' ', (string)($user['full_name'] ?? 'Admin'))[0];
?>
<section class="welcome-banner reveal">
  <div>
    <h2>Welcome back, <?= e($firstName) ?></h2>
    <p>Institution control center · seats, academics & finance</p>
  </div>
  <a class="btn btn-primary btn-shine" href="<?= e(url('/admin/users')) ?>"><?= icon('users') ?> Manage Users</a>
</section>
<div class="grid grid-4 stagger">
  <div class="stat"><div class="label">Users</div><div class="value"><?= (int)$stats['users'] ?></div><div class="hint">Active accounts</div></div>
  <div class="stat"><div class="label">Course plans</div><div class="value"><?= (int)$stats['plans'] ?></div><div class="hint">Across departments</div></div>
  <div class="stat"><div class="label">Students</div><div class="value"><?= (int)$stats['students'] ?></div><div class="hint">Enrolled portal users</div></div>
  <div class="stat"><div class="label">Expenses</div><div class="value">$<?= number_format((float)$stats['spend']) ?></div><div class="hint">Recorded spend</div></div>
</div>
<div class="panel reveal" style="margin-top:1rem">
  <div class="panel-h"><h2><?= icon('spark', 'icon-inline') ?> Quick Actions</h2></div>
  <div class="module-cards stagger">
    <a class="qa-card" href="<?= e(url('/admin/users')) ?>"><div class="ico"><?= icon('users') ?></div><h3>Users & roles</h3><p>Professors, HOD, students</p></a>
    <a class="qa-card" href="<?= e(url('/admin/features')) ?>"><div class="ico"><?= icon('puzzle') ?></div><h3>Feature flags</h3><p>Expand modules per college</p></a>
    <a class="qa-card" href="<?= e(url('/admin/finance')) ?>"><div class="ico"><?= icon('finance') ?></div><h3>Finance</h3><p>Expenses & budgets</p></a>
    <a class="qa-card" href="<?= e(url('/admin/formulas')) ?>"><div class="ico"><?= icon('formula') ?></div><h3>Marks formulas</h3><p>NLP + patterns</p></a>
    <a class="qa-card" href="<?= e(url('/admin/naac')) ?>"><div class="ico"><?= icon('file') ?></div><h3>NAAC builder</h3><p>SSR / AQAR snapshots</p></a>
    <a class="qa-card" href="<?= e(url('/admin/billing')) ?>"><div class="ico"><?= icon('card') ?></div><h3>Subscription</h3><p>Licenses & tiers</p></a>
  </div>
</div>
