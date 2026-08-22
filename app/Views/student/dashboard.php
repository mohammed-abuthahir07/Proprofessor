<?php
/** @var array $courses */
/** @var array $assignmentsDue */
/** @var array $ann */
$user = \Auth::user();
$firstName = explode(' ', (string)($user['full_name'] ?? 'Student'))[0];
?>
<section class="welcome-banner reveal">
  <div>
    <h2>Welcome back, <?= e($firstName) ?></h2>
    <p>Your courses, assignments, and study assistant in one place</p>
  </div>
  <a class="btn btn-primary btn-shine" href="<?= e(url('/student/ask-ai')) ?>"><?= icon('ai') ?> Ask AI</a>
</section>
<div class="grid grid-4 stagger">
  <div class="stat"><div class="label">Courses</div><div class="value"><?= count($courses) ?></div></div>
  <div class="stat"><div class="label">Open assignments</div><div class="value"><?= count($assignmentsDue) ?></div></div>
  <div class="stat"><div class="label">Announcements</div><div class="value"><?= count($ann) ?></div></div>
  <div class="stat"><div class="label">Ask AI</div><div class="value"><?= icon('spark', 'icon-lg') ?></div><div class="hint">Study assistant</div></div>
</div>
<div class="grid grid-2" style="margin-top:1rem">
  <div class="panel reveal">
    <div class="panel-h"><h2>My courses</h2><a class="btn btn-sm btn-ghost" href="<?= e(url('/student/courses')) ?>">All</a></div>
    <?php if (!$courses): ?><div class="empty">No enrollments yet.</div><?php else: ?>
      <div class="plan-list">
      <?php foreach ($courses as $c): ?>
        <div class="plan-row">
          <div class="plan-ico"><?= icon('book') ?></div>
          <div class="meta">
            <strong><?= e($c['name']) ?></strong>
            <span><?= e($c['code']) ?> · <?= e((string)$c['professor_name']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="panel reveal">
    <h2>College feed</h2>
    <?php foreach ($ann as $a): ?>
      <div style="padding:.7rem 0;border-bottom:1px solid var(--line)">
        <span class="chip"><?= e($a['announcement_type']) ?></span>
        <strong><?= e($a['title']) ?></strong>
        <div style="color:var(--muted);font-size:.85rem"><?= e(mb_substr($a['body'], 0, 120)) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<div class="panel reveal" style="margin-top:1rem">
  <div class="panel-h"><h2><?= icon('spark', 'icon-inline') ?> Quick Actions</h2></div>
  <div class="module-cards stagger">
    <a class="qa-card" href="<?= e(url('/student/notes')) ?>"><div class="ico"><?= icon('folder') ?></div><h3>Notes & PPT</h3><p>Unit-wise materials</p></a>
    <a class="qa-card" href="<?= e(url('/student/assignments')) ?>"><div class="ico"><?= icon('edit') ?></div><h3>Assignments</h3><p>Submit & track feedback</p></a>
    <a class="qa-card" href="<?= e(url('/student/attendance')) ?>"><div class="ico"><?= icon('calendar') ?></div><h3>Attendance</h3><p>Live % with alerts</p></a>
    <a class="qa-card" href="<?= e(url('/student/ask-ai')) ?>"><div class="ico"><?= icon('ai') ?></div><h3>Ask AI</h3><p>Grounded study help</p></a>
  </div>
</div>
