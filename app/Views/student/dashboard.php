<?php
/** @var array $courses */
/** @var array $subjects */
/** @var array $labs */
/** @var array $academic */
/** @var array $assignmentsDue */
/** @var array $ann */
$user = \Auth::user();
$firstName = explode(' ', (string)($user['full_name'] ?? 'Student'))[0];
$subjects = $subjects ?? $courses ?? [];
$labs = $labs ?? [];
$ann = $ann ?? [];
$academic = $academic ?? student_academic_context($user);

$profLabel = static function (array $c): string {
    $name = trim((string)($c['professor_name'] ?? ''));
    return $name !== '' ? $name : 'Not Assigned';
};
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

<div class="panel reveal" style="margin-top:1rem">
  <div class="panel-h"><h2>My Academic Details</h2></div>
  <div class="chip-row" style="margin-top:.35rem">
    <span class="chip"><?= e($academic['year_label'] ?: 'Year not set') ?></span>
    <span class="chip"><?= e($academic['department_code'] ?: ($academic['department_name'] ?: 'Department')) ?></span>
    <?php if ($academic['section'] !== ''): ?>
      <span class="chip">Section <?= e($academic['section']) ?></span>
    <?php endif; ?>
    <span class="chip"><?= e($academic['semester_label']) ?> Semester</span>
  </div>
  <?php if ($academic['class_label'] !== ''): ?>
    <div class="muted" style="margin-top:.45rem;font-size:.85rem"><?= e($academic['class_label']) ?></div>
  <?php endif; ?>
  <div class="muted" style="margin-top:.35rem;font-size:.8rem">Academic year and semester are managed by College Admin.</div>
</div>

<div class="grid grid-2" style="margin-top:1rem">
  <div class="panel reveal">
    <div class="panel-h"><h2>My Subjects</h2><a class="btn btn-sm btn-ghost" href="<?= e(url('/student/courses')) ?>">All</a></div>
    <?php if (!$subjects): ?><div class="empty">No matching subjects for your year and semester.</div><?php else: ?>
      <div class="plan-list">
      <?php foreach ($subjects as $c): ?>
        <div class="plan-row">
          <div class="plan-ico"><?= icon('book') ?></div>
          <div class="meta">
            <strong><?= e($c['name']) ?></strong>
            <span><?= e($c['code']) ?> · Professor: <?= e($profLabel($c)) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="panel reveal">
    <div class="panel-h"><h2>My Labs</h2></div>
    <?php if (!$labs): ?><div class="empty">No matching labs for your year and semester.</div><?php else: ?>
      <div class="plan-list">
      <?php foreach ($labs as $c): ?>
        <div class="plan-row">
          <div class="plan-ico"><?= icon('monitor') ?></div>
          <div class="meta">
            <strong><?= e($c['name']) ?></strong>
            <span><?= e($c['code']) ?> · Professor: <?= e($profLabel($c)) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="panel reveal" style="margin-top:1rem">
  <div class="panel-h"><h2>Open assignments</h2><a class="btn btn-sm btn-ghost" href="<?= e(url('/student/assignments')) ?>">All</a></div>
  <?php if (!$assignmentsDue): ?><div class="empty">No open assignments.</div><?php else: ?>
    <div class="plan-list">
    <?php foreach ($assignmentsDue as $a): ?>
      <div class="plan-row">
        <div class="plan-ico"><?= icon('edit') ?></div>
        <div class="meta">
          <strong><?= e($a['title'] ?? 'Assignment') ?></strong>
          <span><?= e((string)($a['due_at'] ?? $a['deadline'] ?? '')) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
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
