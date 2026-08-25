<?php
/** @var array $list */
/** @var array $depts */
/** @var array $subjects */
/** @var array|null $edit */
use App\Models\MarksFormula;

$subjects = $subjects ?? [];
$edit = $edit ?? null;
$isEdit = is_array($edit) && !empty($edit['id']);
$editType = $isEdit ? MarksFormula::normalizeSubjectType($edit['subject_type'] ?? null) : null;
$editComponents = '';
if ($isEdit) {
    $decoded = json_decode((string)($edit['components'] ?? ''), true);
    $editComponents = json_encode(is_array($decoded) ? $decoded : [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
<div class="grid grid-2">
  <div class="panel">
    <div class="panel-h" style="margin-bottom:.75rem">
      <h3 style="margin:0"><?= $isEdit ? 'Edit formula' : 'Create formula' ?></h3>
      <?php if ($isEdit): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/formulas')) ?>">Cancel edit</a>
      <?php endif; ?>
    </div>
    <form id="formulaForm" class="form-grid">
      <?= csrf_field() ?>
      <div class="form-row">
        <label>Describe formula in plain English</label>
        <textarea name="plain_english" id="plain" placeholder="Average of CIA 1 and CIA 2 scaled to 15..."><?= $isEdit ? e((string)($edit['plain_english'] ?? '')) : '' ?></textarea>
      </div>
      <button class="btn btn-accent" type="button" id="parseBtn">Parse with AI</button>
    </form>
    <form method="post" action="<?= e(url('/admin/formulas')) ?>" class="form-grid" style="margin-top:1rem" id="formulaSaveForm">
      <?= csrf_field() ?>
      <input type="hidden" name="formula_id" id="fformula_id" value="<?= $isEdit ? (int)$edit['id'] : '' ?>">
      <input type="hidden" name="ai_parsed" id="ai_parsed" value="<?= $isEdit ? e((string)($edit['ai_parsed'] ?? '')) : '' ?>">
      <div class="form-row"><label>Name</label><input name="name" id="fname" required placeholder="Average of CIA 1 and CIA 2 scaled to 15" value="<?= $isEdit ? e((string)$edit['name']) : '' ?>"></div>
      <div class="form-row two">
        <div><label>Pattern</label><input name="pattern" id="fpattern" placeholder="Madurai / Anna / CBCS" value="<?= $isEdit ? e((string)($edit['pattern'] ?? '')) : '' ?>"></div>
        <div><label>Total max</label><input name="total_max" id="ftotal" value="<?= $isEdit ? e((string)($edit['total_max'] ?? '25')) : '25' ?>"></div>
      </div>
      <div class="form-row two">
        <div>
          <label>Department</label>
          <select name="department_id" id="fdept">
            <option value="">Institution default (all departments)</option>
            <?php foreach ($depts as $d): ?>
              <option value="<?= (int)$d['id'] ?>"<?= $isEdit && (int)($edit['department_id'] ?? 0) === (int)$d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?><?= !empty($d['code']) ? ' (' . e($d['code']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Subject Type</label>
          <select name="subject_type" id="ftype">
            <option value=""<?= $editType === null ? ' selected' : '' ?>>All types (department default)</option>
            <option value="theory"<?= $editType === 'theory' ? ' selected' : '' ?>>Theory</option>
            <option value="lab"<?= $editType === 'lab' ? ' selected' : '' ?>>Lab</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <label>Subject Override</label>
        <select name="subject_id" id="fsubject">
          <option value="">All subjects / Department default</option>
          <?php foreach ($subjects as $s):
            $stype = MarksFormula::subjectTypeFromMeta($s['meta'] ?? null) ?? 'theory';
            $sel = $isEdit && (int)($edit['subject_id'] ?? 0) === (int)$s['id'];
          ?>
            <option
              value="<?= (int)$s['id'] ?>"
              data-department-id="<?= (int)$s['department_id'] ?>"
              data-subject-type="<?= e($stype) ?>"
              <?= $sel ? 'selected' : '' ?>
            ><?= e($s['code'] . ' · ' . $s['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <p style="margin:.35rem 0 0;font-size:.82rem;color:var(--muted)">Leave as “All subjects” for department / type formulas. Pick one subject to create an override.</p>
      </div>
      <div class="form-row"><label>Plain English</label><textarea name="plain_english" id="fplain" required><?= $isEdit ? e((string)($edit['plain_english'] ?? '')) : '' ?></textarea></div>
      <div class="form-row"><label>Expression</label><input name="expression" id="fexpr" required placeholder="(CIA1 + CIA2) / 2" value="<?= $isEdit ? e((string)($edit['expression'] ?? '')) : '' ?>"></div>
      <div class="form-row"><label>Components JSON</label><textarea name="components_json" id="fcomp" required placeholder='{"CIA1":{"max":30},"CIA2":{"max":30}}'><?= $isEdit ? e($editComponents !== '' ? $editComponents : '[]') : '[]' ?></textarea></div>
      <label><input type="checkbox" name="is_default" value="1"<?= $isEdit && !empty($edit['is_default']) ? ' checked' : '' ?>> Institution default formula</label>
      <div class="filters" style="margin-top:.35rem">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Formula' : 'Save formula' ?></button>
        <?php if ($isEdit): ?>
          <a class="btn btn-ghost" href="<?= e(url('/admin/formulas')) ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <div class="panel">
    <h3>Configured formulas</h3>
    <?php if (!$list): ?>
      <div class="empty">No formulas configured yet. Create a department, type, or subject-override formula.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Formula</th>
              <th>Department</th>
              <th>Subject Type</th>
              <th>Subject</th>
              <th>Scope</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($list as $f):
            $type = MarksFormula::normalizeSubjectType($f['subject_type'] ?? null);
            $scope = MarksFormula::scopeLabel($f);
            $rowActive = $isEdit && (int)$edit['id'] === (int)$f['id'];
          ?>
            <tr<?= $rowActive ? ' class="is-selected"' : '' ?>>
              <td>
                <strong><?= e($f['name']) ?></strong>
                <?php if (!empty($f['is_default'])): ?><span class="badge badge-success">Default</span><?php endif; ?>
                <div class="cell-sub"><?= e((string)$f['plain_english']) ?></div>
                <code><?= e((string)$f['expression']) ?></code>
              </td>
              <td><?= e((string)($f['department_name'] ?? 'All departments')) ?></td>
              <td><?= $type ? e(ucfirst($type)) : 'All types' ?></td>
              <td>
                <?php if (!empty($f['subject_id'])): ?>
                  <?= e(trim(($f['subject_code'] ?? '') . ' · ' . ($f['subject_name'] ?? ''), ' ·')) ?>
                <?php else: ?>
                  All Subjects
                <?php endif; ?>
              </td>
              <td><span class="badge badge-info"><?= e($scope) ?></span></td>
              <td>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/formulas?edit=' . (int)$f['id'])) ?>">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
(function () {
  const dept = document.getElementById('fdept');
  const subject = document.getElementById('fsubject');
  const typeSel = document.getElementById('ftype');

  function filterSubjects() {
    const deptId = dept.value || '';
    let visible = 0;
    [...subject.options].forEach((opt, idx) => {
      if (idx === 0) {
        opt.hidden = false;
        return;
      }
      const optDept = opt.getAttribute('data-department-id') || '';
      const show = !deptId || optDept === deptId;
      opt.hidden = !show;
      if (show) visible++;
    });
    if (subject.selectedOptions[0] && subject.selectedOptions[0].hidden) {
      subject.value = '';
    }
    subject.disabled = !!deptId && visible === 0;
  }

  subject.addEventListener('change', () => {
    const opt = subject.selectedOptions[0];
    if (!opt || !opt.value) return;
    const d = opt.getAttribute('data-department-id');
    const t = opt.getAttribute('data-subject-type');
    if (d && !dept.value) dept.value = d;
    if (t && !typeSel.value) typeSel.value = t;
    filterSubjects();
  });

  dept.addEventListener('change', filterSubjects);
  filterSubjects();

  document.getElementById('parseBtn').addEventListener('click', async () => {
    const plain = document.getElementById('plain').value;
    const fd = new FormData();
    fd.append('module','formula');
    fd.append('plain_english', plain);
    fd.append('csrf', '<?= e(csrf_token()) ?>');
    const res = await fetch('<?= e(url('/api/ai')) ?>?module=formula', {method:'POST', body:fd, headers:{'X-CSRF-TOKEN':fd.get('csrf')}});
    const data = await res.json();
    if (!data.ok) return alert(data.error||'Failed');
    const d = data.data;
    document.getElementById('fname').value = d.name || 'Parsed Formula';
    document.getElementById('fplain').value = plain;
    document.getElementById('fexpr').value = d.expression || '';
    document.getElementById('ftotal').value = d.total_max || 25;
    document.getElementById('fcomp').value = JSON.stringify(d.components || [], null, 2);
    document.getElementById('ai_parsed').value = JSON.stringify(d);
  });
})();
</script>
