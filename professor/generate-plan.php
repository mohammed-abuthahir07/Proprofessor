<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
$classes = professor_manageable_classes($user);
$assignments = Database::fetchAll(
    'SELECT sa.subject_id, sa.class_id, s.code, s.name, s.syllabus_text, c.year, c.section, c.meta, d.code AS dept_code, d.name AS dept_name, c.name AS class_name
     FROM subject_assignments sa
     JOIN subjects s ON s.id = sa.subject_id
     LEFT JOIN classes c ON c.id = sa.class_id
     LEFT JOIN departments d ON d.id = c.department_id
     WHERE sa.professor_id = ?
     ORDER BY s.name, c.year, c.section',
    [$user['id']]
);
$inst = Database::fetch('SELECT * FROM institutions WHERE id = ?', [$user['institution_id']]);
render_header('Generate Course Plan', 'generate', ['subtitle' => 'Paste syllabus · Gemini builds OBE structure']);
?>
<?php if (!$assignments): ?>
<div class="panel empty">No courses assigned yet. Your HOD must create courses and assign you under <strong>HOD → Courses</strong>.</div>
<?php else: ?>
<div class="grid grid-2">
  <div class="panel">
    <form class="form-grid" method="post" action="<?= e(base_url('/api/ai?module=course_plan')) ?>" data-ai-form="#aiOut">
      <?= csrf_field() ?>
      <input type="hidden" name="module" value="course_plan">
      <div class="form-row two">
        <div>
          <label>Assigned course</label>
          <select name="assignment_key" id="assignment_key" required>
            <option value="">Select assigned course + class</option>
            <?php foreach ($assignments as $a):
              $key = (int)$a['subject_id'] . ':' . (int)$a['class_id'];
              $classRow = $a;
            ?>
              <option value="<?= e($key) ?>"
                data-subject-id="<?= (int)$a['subject_id'] ?>"
                data-class-id="<?= (int)$a['class_id'] ?>"
                data-name="<?= e($a['name']) ?>"
                data-syl="<?= e($a['syllabus_text'] ?? '') ?>">
                <?= e($a['code'] . ' · ' . $a['name'] . ' · ' . class_batch_label($classRow)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="subject_id" id="subject_id">
          <input type="hidden" name="class_id" id="class_id">
        </div>
        <div>
          <label>Credits</label>
          <input name="credits" value="4">
        </div>
      </div>
      <div class="form-row two">
        <div>
          <label>Subject title</label>
          <input name="subject" id="subject" required placeholder="Database Management Systems">
        </div>
        <div>
          <label>Class</label>
          <input id="class_label" readonly placeholder="Selected with course above">
        </div>
      </div>
      <div class="form-row">
        <label>University / affiliation</label>
        <input name="university" value="<?= e($inst['affiliation_university'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label>Syllabus text</label>
        <textarea name="syllabus" id="syllabus" required placeholder="Paste unit-wise syllabus-"></textarea>
      </div>
      <button class="btn btn-accent" type="submit">? Generate with AI</button>
    </form>
  </div>
  <div class="panel">
    <h3>What you get</h3>
    <ul>
      <li>Unit-wise plan & hours</li>
      <li>Learning outcomes</li>
      <li>Bloom's K1-K6 mapping</li>
      <li>Weekly plan & resources</li>
      <li>Expert advice for NAAC/NBA</li>
    </ul>
    <div class="alert alert-info" style="margin-top:1rem">Works without an API key in demo mode. Add Gemini key in <code>config/config.php</code> for live generation.</div>
  </div>
</div>
<div id="aiOut" style="margin-top:1rem"></div>
<script>
document.getElementById('assignment_key')?.addEventListener('change', (e) => {
  const opt = e.target.selectedOptions[0];
  if (!opt || !opt.value) return;
  document.getElementById('subject_id').value = opt.dataset.subjectId || '';
  document.getElementById('class_id').value = opt.dataset.classId || '';
  document.getElementById('subject').value = opt.dataset.name || '';
  document.getElementById('syllabus').value = opt.dataset.syl || '';
  document.getElementById('class_label').value = opt.textContent.trim();
});
</script>
<?php endif; ?>
<?php render_footer(); ?>
