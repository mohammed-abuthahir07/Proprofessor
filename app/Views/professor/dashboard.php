<?php
/** @var array $byStatus */
/** @var int $assignments */
/** @var int $unread */
/** @var array $recent */
$user = \Auth::user();
$firstName = explode(' ', (string)($user['full_name'] ?? 'Professor'))[0];
$active = (int)array_sum($byStatus);
$drafts = (int)($byStatus['draft'] ?? 0);
$submitted = (int)(($byStatus['submitted'] ?? 0) + ($byStatus['under_review'] ?? 0));
?>
<section class="welcome-banner reveal">
  <div>
    <h2>Welcome back, <?= e($firstName) ?></h2>
    <p><?= e(ucfirst((string)($user['role'] ?? 'professor'))) ?> workspace · <?= $submitted ?> plan(s) pending review</p>
  </div>
  <a class="btn btn-primary btn-shine" href="<?= e(url('/professor/generate-plan')) ?>"><?= icon('spark') ?> Generate Course Plan</a>
</section>

<div class="grid grid-3 stagger" style="margin-bottom:1rem">
  <div class="stat">
    <div class="label">Active Plans</div>
    <div class="value"><?= $active ?></div>
    <div class="hint">Across all subjects</div>
  </div>
  <div class="stat">
    <div class="label">Drafts</div>
    <div class="value"><?= $drafts ?></div>
    <div class="hint">Awaiting submission</div>
  </div>
  <div class="stat">
    <div class="label">Submitted</div>
    <div class="value"><?= $submitted ?></div>
    <div class="hint">Under HOD review</div>
  </div>
</div>

<div class="panel reveal" style="margin-bottom:1rem">
  <div class="panel-h">
    <h2><?= icon('clock', 'icon-inline') ?> Recent Course Plans</h2>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/professor/plans')) ?>">View all</a>
  </div>
  <?php if (!$recent): ?>
    <div class="empty">No plans yet. <a href="<?= e(url('/professor/generate-plan')) ?>">Generate your first course plan</a>.</div>
  <?php else: ?>
  <div class="plan-list">
    <?php foreach ($recent as $r): ?>
      <a class="plan-row" href="<?= e(url('/professor/plan-view?id=' . $r['id'])) ?>">
        <div class="plan-ico"><?= icon('file') ?></div>
        <div class="meta">
          <strong><?= e($r['title']) ?></strong>
          <span><?= e($r['subject_name']) ?> · Updated <?= e($r['updated_at']) ?></span>
        </div>
        <?= status_badge($r['status']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="panel reveal">
  <div class="panel-h"><h2><?= icon('spark', 'icon-inline') ?> Quick Actions</h2></div>
  <div class="module-cards stagger">
    <a class="qa-card" href="<?= e(url('/professor/generate-plan')) ?>"><div class="ico"><?= icon('spark') ?></div><h3>New Course Plan</h3><p>Syllabus → OBE plan</p></a>
    <a class="qa-card" href="<?= e(url('/professor/lessons')) ?>"><div class="ico"><?= icon('book') ?></div><h3>Lesson Plan</h3><p>Session-wise design</p></a>
    <a class="qa-card" href="<?= e(url('/professor/questions')) ?>"><div class="ico"><?= icon('help') ?></div><h3>Question Bank</h3><p>MCQ / short / long</p></a>
    <a class="qa-card" href="<?= e(url('/hod/dashboard')) ?>"><div class="ico"><?= icon('building') ?></div><h3>HOD View</h3><p>Approvals & analytics</p></a>
  </div>
</div>
