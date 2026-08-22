<?php
/** @var int $pending */
/** @var int $approved */
/** @var int $faculty */
/** @var array $avg */
/** @var array $alerts */
$user = \Auth::user();
$firstName = explode(' ', (string)($user['full_name'] ?? 'HOD'))[0];
?>
<section class="welcome-banner reveal">
  <div>
    <h2>Welcome back, <?= e($firstName) ?></h2>
    <p>Department governance · <?= (int)$pending ?> plan(s) awaiting your review</p>
  </div>
  <a class="btn btn-primary btn-shine" href="<?= e(url('/hod/approvals')) ?>"><?= icon('check') ?> Open Approvals</a>
</section>
<div class="grid grid-4 stagger">
  <div class="stat"><div class="label">Pending approvals</div><div class="value"><?= (int)$pending ?></div></div>
  <div class="stat"><div class="label">Approved plans</div><div class="value"><?= (int)$approved ?></div></div>
  <div class="stat"><div class="label">Faculty</div><div class="value"><?= (int)$faculty ?></div></div>
  <div class="stat"><div class="label">Avg AI score</div><div class="value"><?= $avg['a'] !== null ? round((float)$avg['a'], 1) : '—' ?></div></div>
</div>
<div class="panel reveal" style="margin-top:1rem">
  <div class="panel-h"><h2><?= icon('spark', 'icon-inline') ?> Quick Actions</h2></div>
  <div class="module-cards stagger">
    <a class="qa-card" href="<?= e(url('/hod/approvals')) ?>"><div class="ico"><?= icon('check') ?></div><h3>Approvals queue</h3><p><?= (int)$pending ?> waiting</p></a>
    <a class="qa-card" href="<?= e(url('/hod/analytics')) ?>"><div class="ico"><?= icon('trend') ?></div><h3>Department analytics</h3><p>Bloom & quality trends</p></a>
    <a class="qa-card" href="<?= e(url('/hod/compliance')) ?>"><div class="ico"><?= icon('alert') ?></div><h3>Compliance alerts</h3><p>NBA / deadline risks</p></a>
    <a class="qa-card" href="<?= e(url('/hod/reports')) ?>"><div class="ico"><?= icon('file') ?></div><h3>NAAC reports</h3><p>Evidence packs</p></a>
  </div>
</div>
<?php if ($alerts): ?>
<div class="panel reveal" style="margin-top:1rem">
  <h2>Open alerts</h2>
  <?php foreach ($alerts as $a): ?>
    <div class="alert alert-warn"><?= e($a['message']) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
