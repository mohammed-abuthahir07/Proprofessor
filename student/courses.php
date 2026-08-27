<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
ensure_student_academic_schema();
$courses = courses_for_student($user);
$academic = student_academic_context($user);
render_header('My Courses', 'courses');
?>
<div class="panel" style="margin-bottom:1rem">
  <strong>My Academic Details</strong>
  <div class="chip-row" style="margin-top:.4rem">
    <span class="chip"><?= e($academic['year_label'] ?: 'Year not set') ?></span>
    <span class="chip"><?= e($academic['department_code'] ?: ($academic['department_name'] ?: 'Department')) ?></span>
    <?php if ($academic['section'] !== ''): ?>
      <span class="chip">Section <?= e($academic['section']) ?></span>
    <?php endif; ?>
    <span class="chip"><?= e($academic['semester_label']) ?> Semester</span>
  </div>
  <div class="muted" style="margin-top:.35rem;font-size:.8rem">Read-only — College Admin manages year and semester.</div>
</div>
<div class="module-cards stagger">
<?php foreach ($courses as $c):
  $bloom = json_decode($c['bloom_data'] ?? '{}', true) ?: [];
  $prof = trim((string)($c['professor_name'] ?? ''));
  $profLabel = $prof !== '' ? $prof : 'Not Assigned';
  $type = subject_course_type($c);
?>
  <div class="module-card reveal">
    <div class="ico"><?= icon($type === 'lab' ? 'monitor' : 'book') ?></div>
    <h3><?= e($c['name']) ?></h3>
    <p><?= e($c['code']) ?> · <?= e($type === 'lab' ? 'Lab' : 'Subject') ?> · <?= e((string)($c['credits'] ?? '')) ?> credits<br>
      Professor: <?= e($profLabel) ?></p>
    <?php if ($bloom): ?><div class="chip-row" style="margin-top:.6rem"><?php foreach ($bloom as $k => $v): ?><span class="chip"><?= e("$k:$v%") ?></span><?php endforeach; ?></div><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (!$courses): ?><div class="empty reveal">No matching subjects/labs for your year and semester.</div><?php endif; ?>
</div>
<?php render_footer(); ?>
