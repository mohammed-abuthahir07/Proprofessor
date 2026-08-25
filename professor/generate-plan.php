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
$regenId = (int)get('plan_id');
$regenPlan = null;
if ($regenId > 0) {
    $regenPlan = Database::fetch(
        'SELECT * FROM course_plans WHERE id = ? AND professor_id = ? AND institution_id = ?',
        [$regenId, $user['id'], $user['institution_id']]
    );
}
render_header('Generate Course Plan', 'generate', ['subtitle' => 'Paste syllabus · Gemini builds OBE structure']);
?>
<?php if (!$assignments): ?>
<div class="panel empty">No courses assigned yet. Your HOD must create courses and assign you under <strong>HOD → Courses</strong>.</div>
<?php else: ?>
<div class="grid grid-2">
  <div class="panel">
    <form class="form-grid" method="post" action="<?= e(base_url('/api/ai?module=course_plan')) ?>" data-ai-form="#aiOut" id="coursePlanForm">
      <?= csrf_field() ?>
      <input type="hidden" name="module" value="course_plan">
      <?php if ($regenPlan): ?>
        <input type="hidden" name="plan_id" value="<?= (int)$regenPlan['id'] ?>">
        <div class="alert alert-info">Regenerating <strong><?= e((string)$regenPlan['title']) ?></strong> as a new version (v<?= (int)$regenPlan['version'] + 1 ?>). Previous version is kept.</div>
      <?php endif; ?>
      <div class="form-row two">
        <div>
          <label>Assigned course</label>
          <select name="assignment_key" id="assignment_key" required>
            <option value="">Select assigned course + class</option>
            <?php foreach ($assignments as $a):
              $key = (int)$a['subject_id'] . ':' . (int)$a['class_id'];
              $sel = $regenPlan
                && (int)$regenPlan['subject_id'] === (int)$a['subject_id']
                && (int)$regenPlan['class_id'] === (int)$a['class_id'];
            ?>
              <option value="<?= e($key) ?>"
                data-subject-id="<?= (int)$a['subject_id'] ?>"
                data-class-id="<?= (int)$a['class_id'] ?>"
                data-name="<?= e($a['name']) ?>"
                data-syl="<?= e($a['syllabus_text'] ?? '') ?>"
                <?= $sel ? 'selected' : '' ?>>
                <?= e($a['code'] . ' · ' . $a['name'] . ' · ' . class_batch_label($a)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="subject_id" id="subject_id" value="<?= $regenPlan ? (int)$regenPlan['subject_id'] : '' ?>">
          <input type="hidden" name="class_id" id="class_id" value="<?= $regenPlan ? (int)$regenPlan['class_id'] : '' ?>">
        </div>
        <div>
          <label>Credits</label>
          <input name="credits" value="<?= e((string)($regenPlan['credits'] ?? '4')) ?>">
        </div>
      </div>
      <div class="form-row two">
        <div>
          <label>Subject title</label>
          <input name="subject" id="subject" required placeholder="Database Management Systems" value="<?= e((string)($regenPlan['subject_name'] ?? '')) ?>">
        </div>
        <div>
          <label>Class</label>
          <input id="class_label" readonly placeholder="Selected with course above">
        </div>
      </div>
      <div class="form-row two">
        <div>
          <label>University / affiliation</label>
          <input name="university" value="<?= e((string)($regenPlan['university'] ?? ($inst['affiliation_university'] ?? ''))) ?>">
        </div>
        <div>
          <label>Curriculum template</label>
          <?php
            $metaTpl = 'standard';
            if ($regenPlan) {
              $m = json_decode((string)($regenPlan['meta'] ?? '{}'), true) ?: [];
              $metaTpl = CoursePlanTools::normalizeTemplate($m['accreditation_template'] ?? 'standard');
            }
          ?>
          <select name="accreditation_template" id="accreditation_template">
            <option value="standard" <?= $metaTpl === 'standard' ? 'selected' : '' ?>>Standard</option>
            <option value="naac" <?= $metaTpl === 'naac' ? 'selected' : '' ?>>NAAC</option>
            <option value="nba" <?= $metaTpl === 'nba' ? 'selected' : '' ?>>NBA</option>
            <option value="aicte" <?= $metaTpl === 'aicte' ? 'selected' : '' ?>>AICTE</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <label>Syllabus text</label>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.55rem">
          <label class="btn btn-ghost btn-sm" style="cursor:pointer;margin:0">
            Upload PDF
            <input type="file" id="syllabus_pdf" accept=".pdf,application/pdf" hidden>
          </label>
          <label class="btn btn-ghost btn-sm" style="cursor:pointer;margin:0">
            Upload DOCX
            <input type="file" id="syllabus_docx" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" hidden>
          </label>
          <span id="syllabus_upload_status" style="font-size:.85rem;color:var(--muted);align-self:center"></span>
        </div>
        <textarea name="syllabus" id="syllabus" required placeholder="Paste unit-wise syllabus—or upload PDF/DOCX to extract text"><?= e((string)($regenPlan['syllabus_input'] ?? '')) ?></textarea>
        <p style="margin:.35rem 0 0;font-size:.82rem;color:var(--muted)">Extracted text is editable before you generate. Max 8 MB · PDF or DOCX only.</p>
      </div>
      <button class="btn btn-accent" type="submit"><?= $regenPlan ? 'Regenerate with AI (new version)' : 'Generate with AI' ?></button>
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
      <li>Template-aware structure (Standard / NAAC / NBA / AICTE)</li>
      <li>Bloom balance checker after generation</li>
    </ul>
  </div>
</div>
<div id="aiOut" style="margin-top:1rem"></div>
<script>
(function () {
  const key = document.getElementById('assignment_key');
  function applyAssignment() {
    const opt = key?.selectedOptions?.[0];
    if (!opt || !opt.value) return;
    document.getElementById('subject_id').value = opt.dataset.subjectId || '';
    document.getElementById('class_id').value = opt.dataset.classId || '';
    const subj = document.getElementById('subject');
    if (!subj.value) subj.value = opt.dataset.name || '';
    const syl = document.getElementById('syllabus');
    if (!syl.value) syl.value = opt.dataset.syl || '';
    document.getElementById('class_label').value = opt.textContent.trim();
  }
  key?.addEventListener('change', () => {
    const opt = key.selectedOptions[0];
    if (!opt || !opt.value) return;
    document.getElementById('subject_id').value = opt.dataset.subjectId || '';
    document.getElementById('class_id').value = opt.dataset.classId || '';
    document.getElementById('subject').value = opt.dataset.name || '';
    document.getElementById('syllabus').value = opt.dataset.syl || '';
    document.getElementById('class_label').value = opt.textContent.trim();
  });
  applyAssignment();

  async function uploadSyllabus(fileInput) {
    const file = fileInput.files && fileInput.files[0];
    const status = document.getElementById('syllabus_upload_status');
    if (!file) return;
    status.textContent = 'Extracting text…';
    const fd = new FormData();
    fd.append('module', 'syllabus_extract');
    fd.append('syllabus_file', file);
    fd.append('csrf', '<?= e(csrf_token()) ?>');
    try {
      const res = await fetch('<?= e(base_url('/api/ai?module=syllabus_extract')) ?>', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': fd.get('csrf') },
        body: fd,
      });
      const raw = await res.text();
      let data = null;
      try {
        data = raw ? JSON.parse(raw) : null;
      } catch (parseErr) {
        throw new Error(raw && raw.trim() ? raw.trim().slice(0, 180) : 'Server returned an empty response. Try again or paste syllabus text.');
      }
      if (!res.ok || !data || !data.ok) {
        throw new Error((data && data.error) || 'Extraction failed');
      }
      document.getElementById('syllabus').value = data.text || '';
      status.textContent = 'Extracted — review/edit before generating.';
    } catch (err) {
      status.textContent = 'Extraction failed';
      alert(err.message || 'Upload failed');
    } finally {
      fileInput.value = '';
    }
  }
  document.getElementById('syllabus_pdf')?.addEventListener('change', function () { uploadSyllabus(this); });
  document.getElementById('syllabus_docx')?.addEventListener('change', function () { uploadSyllabus(this); });
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
