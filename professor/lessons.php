<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
$planId = (int)get('plan_id');
if (in_array((string)$user['role'], ['admin', 'superadmin'], true)) {
    $plans = Database::fetchAll(
        'SELECT id, title, subject_name FROM course_plans WHERE institution_id=? ORDER BY id DESC',
        [$user['institution_id']]
    );
} else {
    $plans = Database::fetchAll(
        'SELECT id, title, subject_name FROM course_plans WHERE professor_id=? ORDER BY id DESC',
        [$user['id']]
    );
}
$lessons = $planId ? Database::fetchAll('SELECT * FROM lesson_plans WHERE plan_id=? ORDER BY session_number', [$planId]) : [];
$selectedTitle = '';
foreach ($plans as $p) {
    if ((int)$p['id'] === $planId) {
        $selectedTitle = (string)($p['title'] ?: $p['subject_name']);
        break;
    }
}

render_header('AI Lesson Planner', 'lessons', ['subtitle' => 'Session-by-session from the course plan']);
?>
<div class="panel">
  <form class="form-grid" method="post" action="<?= e(base_url('/api/ai?module=lesson')) ?>" data-ai-form="#out">
    <?= csrf_field() ?>
    <input type="hidden" name="module" value="lesson">
    <div class="form-row two">
      <div>
        <label>Course plan</label>
        <select name="plan_id" id="lessonPlan" required>
          <option value="">Select a course plan</option>
          <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $planId === (int)$p['id'] ? 'selected' : '' ?>>
              <?= e(trim(($p['title'] ?: $p['subject_name'] ?: 'Untitled'))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;align-items:end"><button class="btn btn-accent" type="submit" <?= $plans ? '' : 'disabled' ?>>Generate lesson plans</button></div>
    </div>
  </form>
  <?php if (!$plans): ?>
    <div class="empty" style="margin-top:1rem">Create a course plan first (<a href="<?= e(base_url('/professor/generate-plan.php')) ?>">New Course Plan</a>), then generate sessions here.</div>
  <?php endif; ?>
</div>
<div id="out"></div>
<?php if ($planId && !$lessons): ?>
  <div class="panel empty" style="margin-top:1rem">No sessions yet. Click <strong>Generate lesson plans</strong> to build method, activity, assessment, and engagement for each class from this course plan.</div>
<?php endif; ?>
<?php if ($lessons): ?>
<div class="panel" style="margin-top:1rem">
  <h2>Sessions<?= $selectedTitle !== '' ? ' · ' . e($selectedTitle) : '' ?></h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Mins</th>
          <th>Teaching method</th>
          <th>Classroom activity</th>
          <th>Formative assessment</th>
          <th>Engagement</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($lessons as $l):
        $acts = lesson_as_list($l['activities'] ?? []);
        $assess = lesson_as_list($l['formative_assessment'] ?? []);
        $eng = lesson_as_list($l['engagement'] ?? []);
      ?>
        <tr>
          <td><?= (int)$l['session_number'] ?></td>
          <td><?= e($l['title']) ?></td>
          <td><?= (int)$l['duration_mins'] ?></td>
          <td><?= e((string)$l['teaching_method']) ?></td>
          <td><?= e(implode('; ', $acts)) ?></td>
          <td><?= e(implode('; ', $assess)) ?></td>
          <td><?= e(implode('; ', $eng)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="lesson-grid">
  <?php foreach ($lessons as $l):
    $acts = lesson_as_list($l['activities'] ?? []);
    $assess = lesson_as_list($l['formative_assessment'] ?? []);
    $eng = lesson_as_list($l['engagement'] ?? []);
    $objs = lesson_as_list($l['objectives'] ?? []);
  ?>
    <article class="panel lesson-card">
      <header>
        <span class="chip">Session <?= (int)$l['session_number'] ?></span>
        <span class="chip"><?= (int)$l['duration_mins'] ?> min</span>
      </header>
      <h3><?= e($l['title']) ?></h3>
      <?php if ($objs): ?>
        <p class="lesson-obj"><?= e(implode(' · ', $objs)) ?></p>
      <?php endif; ?>
      <dl>
        <div><dt>Teaching method</dt><dd><?= e((string)($l['teaching_method'] ?: '—')) ?></dd></div>
        <div><dt>Classroom activity</dt><dd><?= e($acts ? implode('; ', $acts) : '—') ?></dd></div>
        <div><dt>Formative assessment</dt><dd><?= e($assess ? implode('; ', $assess) : '—') ?></dd></div>
        <div><dt>Student engagement</dt><dd><?= e($eng ? implode('; ', $eng) : '—') ?></dd></div>
      </dl>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<script>
document.getElementById('lessonPlan')?.addEventListener('change', (e) => {
  const id = e.target.value;
  window.location = id ? ('?plan_id=' + encodeURIComponent(id)) : window.location.pathname;
});
</script>
<?php render_footer(); ?>
