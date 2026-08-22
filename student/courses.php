<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
$courses = courses_for_student($user);
render_header('My Courses', 'courses');
?>
<div class="module-cards stagger">
<?php foreach ($courses as $c): $bloom=json_decode($c['bloom_data']?:'{}',true)?:[]; ?>
  <div class="module-card reveal">
    <div class="ico"><?= icon('book') ?></div>
    <h3><?= e($c['name']) ?></h3>
    <p><?= e($c['code']) ?> · <?= e((string)$c['credits']) ?> credits<br>Prof. <?= e((string)$c['professor_name']) ?></p>
    <?php if ($bloom): ?><div class="chip-row" style="margin-top:.6rem"><?php foreach ($bloom as $k=>$v): ?><span class="chip"><?= e("$k:$v%") ?></span><?php endforeach; ?></div><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (!$courses): ?><div class="empty reveal">No courses enrolled yet.</div><?php endif; ?>
</div>
<?php render_footer(); ?>
