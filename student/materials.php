<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();

$materials = StudyMaterialTools::forStudent($user);
$classId = student_class_id($user);
$subjects = [];
if ($classId > 0) {
    foreach (courses_for_student($user) as $course) {
        $sid = (int)($course['id'] ?? 0);
        if ($sid < 1) {
            continue;
        }
        $code = trim((string)($course['code'] ?? ''));
        $subjects[$sid] = [
            'id' => $sid,
            'name' => (string)($course['name'] ?? 'Course'),
            'code' => $code !== '' ? $code : (string)($course['name'] ?? 'Course'),
        ];
    }
}

$weekAgo = strtotime('-7 days');
$newCount = 0;
$groups = [];
foreach ($materials as $item) {
    $created = strtotime((string)($item['created_at'] ?? ''));
    $item['is_new'] = $created !== false && $created >= $weekAgo;
    if ($item['is_new']) {
        $newCount++;
    }
    $ext = strtoupper((string)pathinfo((string)($item['file_original_name'] ?? ''), PATHINFO_EXTENSION));
    $item['ext_label'] = $ext !== '' ? $ext : (string)($item['type_label'] ?? 'FILE');
    $item['file_icon'] = in_array(strtolower($ext), ['ppt', 'pptx'], true) ? 'monitor' : 'file';
    $sid = (int)($item['subject_id'] ?? 0);
    if (!isset($groups[$sid])) {
        $code = trim((string)($item['subject_code'] ?? ''));
        $groups[$sid] = [
            'id' => $sid,
            'name' => (string)($item['subject_name'] ?? 'Course'),
            'code' => $code !== '' ? $code : (string)($item['subject_name'] ?? 'Course'),
            'professors' => [],
            'items' => [],
        ];
    }
    $prof = trim((string)($item['professor_name'] ?? ''));
    if ($prof !== '') {
        $groups[$sid]['professors'][$prof] = true;
    }
    $groups[$sid]['items'][] = $item;
}
if (!$subjects) {
    foreach ($groups as $sid => $group) {
        $subjects[$sid] = [
            'id' => (int)$group['id'],
            'name' => (string)$group['name'],
            'code' => (string)$group['code'],
        ];
    }
}

render_header('Study Materials', 'materials', ['compactTitle' => true]);
?>
<div class="stu-mat">
<?php if ($classId < 1): ?>
  <div class="empty">Your account is not assigned to a class. Ask College Admin to put you in the correct year and section.</div>
<?php elseif (!$materials): ?>
  <section class="stu-mat-head">
    <h2><?= icon('folder', 'icon-inline') ?> Study Materials</h2>
    <p>Class notes, PPTs &amp; exam prep uploaded by your professors</p>
  </section>
  <div class="panel empty">
    <strong>No materials shared yet</strong>
    <p>When your professor sends notes or a presentation to your class, they will appear here.</p>
  </div>
<?php else: ?>
  <section class="stu-mat-head">
    <h2><?= icon('folder', 'icon-inline') ?> Study Materials</h2>
    <p>Class notes, PPTs &amp; exam prep uploaded by your professors</p>
  </section>
  <?php if ($newCount > 0): ?>
    <div class="stu-mat-banner">
      <?= icon('bell', 'icon-inline') ?>
      <span><?= (int)$newCount ?> new file<?= $newCount === 1 ? '' : 's' ?> added this week by your professors — check NEW badges below.</span>
    </div>
  <?php endif; ?>
  <div class="stu-mat-filters" role="tablist" aria-label="Filter by subject">
    <button type="button" class="is-active" data-subject="all">All</button>
    <?php foreach ($subjects as $subject): ?>
      <button type="button" data-subject="<?= (int)$subject['id'] ?>" title="<?= e((string)$subject['name']) ?>"><?= e((string)$subject['code']) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="stu-mat-groups">
    <?php foreach ($groups as $group):
      $profs = array_keys($group['professors']);
      $count = count($group['items']);
      $profLine = $profs !== [] ? implode(', ', $profs) : 'Professor';
    ?>
      <section class="stu-mat-group panel" data-subject="<?= (int)$group['id'] ?>">
        <header class="stu-mat-group-h">
          <h3><?= e((string)$group['name']) ?></h3>
          <p><?= e($profLine) ?> · <?= (int)$count ?> file<?= $count === 1 ? '' : 's' ?></p>
        </header>
        <div class="stu-mat-rows">
          <?php foreach ($group['items'] as $item): ?>
            <article class="stu-mat-row">
              <div class="stu-mat-ico is-<?= e(strtolower((string)$item['ext_label'])) ?>"><?= icon((string)$item['file_icon'], 'icon') ?></div>
              <div class="stu-mat-main">
                <div class="stu-mat-title-line">
                  <strong><?= e((string)$item['title']) ?></strong>
                  <?php if (!empty($item['is_new'])): ?><span class="stu-mat-new">NEW</span><?php endif; ?>
                </div>
                <p>
                  <?php if (trim((string)($item['professor_name'] ?? '')) !== ''): ?>
                    Uploaded by <?= e((string)$item['professor_name']) ?>
                  <?php else: ?>
                    Uploaded by your professor
                  <?php endif; ?>
                  · <?= e(StudyMaterialTools::formatBytes((int)($item['file_size'] ?? 0))) ?>
                </p>
              </div>
              <span class="stu-mat-ext is-<?= e(strtolower((string)$item['ext_label'])) ?>"><?= e((string)$item['ext_label']) ?></span>
              <a class="btn btn-sm btn-primary stu-mat-dl" href="<?= e(base_url('/api/study-materials/file?id=' . (int)$item['id'])) ?>"><?= icon('download', 'icon-inline') ?> Download</a>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
  <div class="panel empty" id="stuMatFilterEmpty" hidden>
    <strong>No materials for this subject</strong>
    <p>Choose another subject, or All, to see the files shared with your class.</p>
  </div>
<?php endif; ?>
</div>
<?php if ($materials): ?>
<script>
(function () {
  var buttons = document.querySelectorAll('.stu-mat-filters button');
  var groups = document.querySelectorAll('.stu-mat-group');
  var empty = document.getElementById('stuMatFilterEmpty');
  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      var id = button.getAttribute('data-subject');
      buttons.forEach(function (other) { other.classList.toggle('is-active', other === button); });
      var shown = 0;
      groups.forEach(function (group) {
        var match = id === 'all' || group.getAttribute('data-subject') === id;
        group.hidden = !match;
        if (match) shown++;
      });
      if (empty) empty.hidden = shown > 0;
    });
  });
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
