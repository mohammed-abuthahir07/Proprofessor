<?php
/** @var list<array<string,mixed>> $rows */
/** @var array{total:int,theory:int,lab:int,window:string} $summary */
/** @var list<array<string,mixed>> $departmentOptions */
/** @var list<array<string,mixed>> $subjectOptions */
/** @var list<array<string,mixed>> $classOptions */
/** @var array<int,string> $years */
/** @var array<string,string> $semesters */
/** @var array<string,string> $levels */
/** @var array<string,string> $types */
/** @var array{department_id:int,year_level:int,semester:string,exam_type:string,date:string} $filters */
/** @var int $pageSize */
/** @var string $returnTo */
$rows = $rows ?? [];
$summary = $summary ?? ['total' => 0, 'theory' => 0, 'lab' => 0, 'window' => '—'];
$departmentOptions = $departmentOptions ?? [];
$subjectOptions = $subjectOptions ?? [];
$classOptions = $classOptions ?? [];
$years = $years ?? [];
$semesters = $semesters ?? [];
$levels = $levels ?? [];
$types = $types ?? [];
$filters = $filters ?? ['department_id' => 0, 'academic_level' => '', 'year_level' => 0, 'semester' => '', 'exam_type' => '', 'date' => ''];
$pageSize = max(1, (int)($pageSize ?? 15));
$returnTo = (string)($returnTo ?? '/admin/exam-timetable');
$shown = count($rows);
$first = min($pageSize, $shown);
$deptLabel = static function (array $row): string {
    if (($row['dept_code'] ?? '') !== '') {
        return (string)$row['dept_code'];
    }
    return ($row['dept_name'] ?? '') !== '' ? (string)$row['dept_name'] : '—';
};
?>
<div class="adm-exam">
  <div class="adm-exam-head">
    <div>
      <h2>Exam Timetable</h2>
      <p>Schedule examinations for each year, section, and semester. Students see only the exams that match their own academic details.</p>
    </div>
    <div class="adm-exam-head-actions">
      <button class="btn btn-primary btn-sm" type="button" id="examAddOpen">+ Add Exam</button>
    </div>
  </div>

  <div class="adm-exam-kpis">
    <div class="adm-exam-kpi">
      <strong><?= (int)$summary['total'] ?></strong>
      <span>Scheduled exams</span>
    </div>
    <div class="adm-exam-kpi is-info">
      <strong><?= (int)$summary['theory'] ?></strong>
      <span>Written</span>
    </div>
    <div class="adm-exam-kpi is-accent">
      <strong><?= (int)$summary['lab'] ?></strong>
      <span>Lab / Practical</span>
    </div>
    <div class="adm-exam-kpi">
      <strong class="adm-exam-window"><?= e((string)$summary['window']) ?></strong>
      <span>Exam window</span>
    </div>
  </div>

  <form class="adm-exam-bar" method="get" action="<?= e(url('/admin/exam-timetable')) ?>">
    <select name="department_id" aria-label="Department">
      <option value="">All departments</option>
      <?php foreach ($departmentOptions as $dept): ?>
        <option value="<?= (int)$dept['id'] ?>" <?= (int)$filters['department_id'] === (int)$dept['id'] ? 'selected' : '' ?>><?= e((string)$dept['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="academic_level" aria-label="Academic level">
      <option value="">UG &amp; PG</option>
      <?php foreach ($levels as $key => $label): ?>
        <option value="<?= e((string)$key) ?>" <?= (string)$filters['academic_level'] === (string)$key ? 'selected' : '' ?>><?= e($label) ?> only</option>
      <?php endforeach; ?>
    </select>
    <select name="year_level" aria-label="Year">
      <option value="">All years</option>
      <?php foreach ($years as $value => $label): ?>
        <option value="<?= (int)$value ?>" <?= (int)$filters['year_level'] === (int)$value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="semester" aria-label="Semester">
      <option value="">All semesters</option>
      <?php foreach ($semesters as $value => $label): ?>
        <option value="<?= e((string)$value) ?>" <?= (string)$filters['semester'] === (string)$value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="exam_type" aria-label="Exam type">
      <option value="">All types</option>
      <?php foreach ($types as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $filters['exam_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="adm-exam-date">
      <span class="sr-only">Exam date</span>
      <input type="date" name="date" value="<?= e((string)$filters['date']) ?>" aria-label="Exam date">
    </label>
    <button class="btn btn-sm btn-ghost" type="submit">Apply</button>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/exam-timetable')) ?>">Reset</a>
  </form>

  <div class="panel adm-exam-table">
    <?php if (!$rows): ?>
      <div class="empty">No exams match these filters yet. Use Add Exam to schedule one subject at a time.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Subject</th>
              <th>Department</th>
              <th>Level</th>
              <th>Year</th>
              <th>Class / Section</th>
              <th>Semester</th>
              <th>Exam Date</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Type</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $row): ?>
            <tr class="adm-exam-row<?= $i >= $pageSize ? ' is-extra' : '' ?>"<?= $i >= $pageSize ? ' hidden' : '' ?>>
              <td>
                <strong><?= e((string)$row['subject_name']) ?></strong>
                <?php if ($row['exam_hall'] !== ''): ?>
                  <small class="adm-exam-sub"><?= e((string)$row['exam_hall']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= e($deptLabel($row)) ?></td>
              <td><?= e((string)$row['academic_level']) ?></td>
              <td><?= e((string)$row['year_label']) ?></td>
              <td><?= e((string)$row['scope_label']) ?></td>
              <td><?= e((string)$row['semester_label']) ?></td>
              <td><?= e((string)$row['date_label']) ?></td>
              <td><?= e((string)$row['start_label']) ?></td>
              <td><?= e((string)$row['end_label']) ?></td>
              <td>
                <span class="adm-exam-badge <?= in_array($row['exam_type'], ['lab', 'practical'], true) ? 'is-lab' : 'is-theory' ?>"><?= e((string)$row['type_label']) ?></span>
                <?php if ($row['is_lab'] && !in_array($row['exam_type'], ['lab', 'practical'], true)): ?>
                  <span class="adm-exam-badge is-lab">Lab</span>
                <?php endif; ?>
              </td>
              <td class="adm-exam-actions">
                <button class="adm-exam-link" type="button" data-exam-edit
                  data-id="<?= (int)$row['id'] ?>"
                  data-subject="<?= e((string)$row['subject_name']) ?>"
                  data-department="<?= (int)$row['dept_id'] ?>"
                  data-level="<?= e((string)$row['academic_level']) ?>"
                  data-year="<?= (int)$row['year_level'] ?>"
                  data-class="<?= (int)$row['class_id'] ?>"
                  data-semester="<?= e((string)$row['semester']) ?>"
                  data-date="<?= e((string)$row['exam_date']) ?>"
                  data-start="<?= e((string)$row['start_time']) ?>"
                  data-end="<?= e((string)$row['end_time']) ?>"
                  data-type="<?= e((string)$row['exam_type']) ?>"
                  data-lab="<?= $row['is_lab'] ? '1' : '0' ?>"
                  data-hall="<?= e((string)$row['exam_hall']) ?>">Edit</button>
                <button class="adm-exam-link is-delete" type="button" data-exam-delete
                  data-id="<?= (int)$row['id'] ?>"
                  data-subject="<?= e((string)$row['subject_name']) ?>"
                  data-date-label="<?= e((string)$row['date_label']) ?>">Delete</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="adm-exam-foot">
        <span id="admExamCount">Showing <?= (int)$first ?> of <?= (int)$shown ?> scheduled exams</span>
        <?php if ($shown > $pageSize): ?>
          <button class="btn btn-sm btn-ghost" type="button" id="admExamMore">Load more</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<datalist id="examSubjectList">
  <?php foreach ($subjectOptions as $subject): ?>
    <option value="<?= e((string)$subject['name']) ?>" data-dept="<?= (int)$subject['department_id'] ?>"><?= e((string)$subject['code']) ?></option>
  <?php endforeach; ?>
</datalist>

<dialog class="adm-exam-dialog" id="examAddDialog">
  <form method="post" action="<?= e(url('/admin/exam-timetable')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_exam">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-exam-dialog-h">
      <div>
        <h2>Add Exam</h2>
        <p>Add each subject separately. Students matching the level, year, department, section, and semester below will see it in their calendar.</p>
      </div>
      <button class="icon-btn" type="button" data-exam-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <div class="form-row two">
      <label>Subject / exam name
        <input name="subject_name" list="examSubjectList" required placeholder="Database Management Systems" autocomplete="off">
      </label>
      <label>Department
        <select name="department_id" class="adm-exam-dept" required>
          <option value="">Select department</option>
          <?php foreach ($departmentOptions as $dept): ?>
            <option value="<?= (int)$dept['id'] ?>" <?= (int)$filters['department_id'] === (int)$dept['id'] ? 'selected' : '' ?>><?= e((string)$dept['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="form-row three">
      <label>Academic level
        <select name="academic_level" required>
          <?php foreach ($levels as $key => $label): ?>
            <option value="<?= e((string)$key) ?>" <?= (string)$filters['academic_level'] === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Year
        <select name="year_level" required>
          <?php foreach ($years as $value => $label): ?>
            <option value="<?= (int)$value ?>" <?= (int)$filters['year_level'] === (int)$value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Semester
        <select name="semester" required>
          <?php foreach ($semesters as $value => $label): ?>
            <option value="<?= e((string)$value) ?>" <?= (string)$filters['semester'] === (string)$value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label>Class / Section
      <select name="class_id" class="adm-exam-class">
        <option value="">All sections of this year</option>
        <?php foreach ($classOptions as $class): ?>
          <option value="<?= (int)$class['id'] ?>" data-dept="<?= (int)$class['department_id'] ?>"><?= e((string)$class['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="form-row three">
      <label>Exam date<input type="date" name="exam_date" required value="<?= e((string)$filters['date']) ?>"></label>
      <label>Start time<input type="time" name="start_time" required value="09:30"></label>
      <label>End time<input type="time" name="end_time" required value="12:30"></label>
    </div>
    <div class="form-row two">
      <label>Exam type
        <select name="exam_type" required>
          <?php foreach ($types as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $key === 'end_semester' ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Exam hall / room <small>(optional)</small>
        <input name="exam_hall" placeholder="Hall 3" autocomplete="off">
      </label>
    </div>
    <label class="adm-exam-check">
      <input type="checkbox" name="is_lab" value="1">
      <span>Lab / practical exam</span>
    </label>
    <div class="adm-exam-dialog-actions">
      <button class="btn btn-ghost" type="button" data-exam-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Create Exam</button>
    </div>
  </form>
</dialog>

<dialog class="adm-exam-dialog" id="examEditDialog">
  <form method="post" action="<?= e(url('/admin/exam-timetable')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_exam">
    <input type="hidden" name="exam_id" id="examEditId">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-exam-dialog-h">
      <div>
        <h2>Edit Exam</h2>
        <p id="examEditName">This updates the exam timetable entry only.</p>
      </div>
      <button class="icon-btn" type="button" data-exam-close aria-label="Close"><?= icon('close') ?></button>
    </div>
    <div class="form-row two">
      <label>Subject / exam name
        <input name="subject_name" id="examEditSubject" list="examSubjectList" required autocomplete="off">
      </label>
      <label>Department
        <select name="department_id" id="examEditDept" class="adm-exam-dept" required>
          <?php foreach ($departmentOptions as $dept): ?>
            <option value="<?= (int)$dept['id'] ?>"><?= e((string)$dept['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="form-row three">
      <label>Academic level
        <select name="academic_level" id="examEditLevel" required>
          <?php foreach ($levels as $key => $label): ?>
            <option value="<?= e((string)$key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Year
        <select name="year_level" id="examEditYear" required>
          <?php foreach ($years as $value => $label): ?>
            <option value="<?= (int)$value ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Semester
        <select name="semester" id="examEditSemester" required>
          <?php foreach ($semesters as $value => $label): ?>
            <option value="<?= e((string)$value) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label>Class / Section
      <select name="class_id" id="examEditClass" class="adm-exam-class">
        <option value="">All sections of this year</option>
        <?php foreach ($classOptions as $class): ?>
          <option value="<?= (int)$class['id'] ?>" data-dept="<?= (int)$class['department_id'] ?>"><?= e((string)$class['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="form-row three">
      <label>Exam date<input type="date" name="exam_date" id="examEditDate" required></label>
      <label>Start time<input type="time" name="start_time" id="examEditStart" required></label>
      <label>End time<input type="time" name="end_time" id="examEditEnd" required></label>
    </div>
    <div class="form-row two">
      <label>Exam type
        <select name="exam_type" id="examEditType" required>
          <?php foreach ($types as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Exam hall / room <small>(optional)</small>
        <input name="exam_hall" id="examEditHall" autocomplete="off">
      </label>
    </div>
    <label class="adm-exam-check">
      <input type="checkbox" name="is_lab" id="examEditLab" value="1">
      <span>Lab / practical exam</span>
    </label>
    <div class="adm-exam-dialog-actions">
      <button class="btn btn-ghost" type="button" data-exam-close>Cancel</button>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>
</dialog>

<dialog class="adm-exam-dialog" id="examDeleteDialog">
  <form method="post" action="<?= e(url('/admin/exam-timetable')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_exam">
    <input type="hidden" name="exam_id" id="examDeleteId">
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <div class="adm-exam-dialog-h">
      <div>
        <h2>Delete Exam Schedule?</h2>
        <p id="examDeleteText">This removes the selected exam from the timetable. The subject, department, and students are not changed.</p>
      </div>
    </div>
    <div class="adm-exam-dialog-actions">
      <button class="btn btn-ghost" type="button" data-exam-close>Cancel</button>
      <button class="btn adm-exam-delete" type="submit">Delete</button>
    </div>
  </form>
</dialog>

<script>
(function () {
  var titleBox = document.querySelector('.topbar-title');
  if (titleBox) titleBox.hidden = true;
  function bindDialog(id, openId) {
    var dialog = document.getElementById(id);
    if (openId) {
      document.getElementById(openId)?.addEventListener('click', function () { dialog?.showModal(); });
    }
    dialog?.querySelectorAll('[data-exam-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { dialog.close(); });
    });
    return dialog;
  }
  // Keep the class list to the chosen department so a mismatched scope can't be saved.
  function scopeClasses(form) {
    var dept = form.querySelector('.adm-exam-dept');
    var classes = form.querySelector('.adm-exam-class');
    if (!dept || !classes) return;
    function apply() {
      var selected = dept.value;
      var current = classes.value;
      var stillValid = false;
      [...classes.options].forEach(function (opt) {
        if (!opt.value) return;
        var visible = !selected || opt.dataset.dept === selected;
        opt.hidden = !visible;
        opt.disabled = !visible;
        if (visible && opt.value === current) stillValid = true;
      });
      if (!stillValid) classes.value = '';
    }
    dept.addEventListener('change', apply);
    apply();
  }
  document.querySelectorAll('#examAddDialog form, #examEditDialog form').forEach(scopeClasses);

  bindDialog('examAddDialog', 'examAddOpen');
  var editDialog = bindDialog('examEditDialog', '');
  var deleteDialog = bindDialog('examDeleteDialog', '');
  document.querySelectorAll('[data-exam-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('examEditId').value = btn.dataset.id || '';
      document.getElementById('examEditName').textContent = btn.dataset.subject || 'This updates the exam timetable entry only.';
      var dept = document.getElementById('examEditDept');
      dept.value = btn.dataset.department || '';
      dept.dispatchEvent(new Event('change'));
      document.getElementById('examEditSubject').value = btn.dataset.subject || '';
      document.getElementById('examEditLevel').value = btn.dataset.level || 'UG';
      document.getElementById('examEditYear').value = btn.dataset.year || '1';
      document.getElementById('examEditClass').value = btn.dataset.class && btn.dataset.class !== '0' ? btn.dataset.class : '';
      document.getElementById('examEditSemester').value = btn.dataset.semester || 'Odd Semester';
      document.getElementById('examEditDate').value = btn.dataset.date || '';
      document.getElementById('examEditStart').value = btn.dataset.start || '';
      document.getElementById('examEditEnd').value = btn.dataset.end || '';
      document.getElementById('examEditType').value = btn.dataset.type || 'end_semester';
      document.getElementById('examEditHall').value = btn.dataset.hall || '';
      document.getElementById('examEditLab').checked = btn.dataset.lab === '1';
      editDialog?.showModal();
    });
  });
  document.querySelectorAll('[data-exam-delete]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('examDeleteId').value = btn.dataset.id || '';
      var text = document.getElementById('examDeleteText');
      if (text) {
        text.textContent = 'This will remove the ' + (btn.dataset.subject || 'selected') + ' exam on '
          + (btn.dataset.dateLabel || 'the scheduled date') + '. The subject and department stay in place.';
      }
      deleteDialog?.showModal();
    });
  });
  document.getElementById('admExamMore')?.addEventListener('click', function () {
    document.querySelectorAll('.adm-exam-row.is-extra').forEach(function (row) { row.hidden = false; });
    this.hidden = true;
    var total = document.querySelectorAll('.adm-exam-row').length;
    var countLabel = document.getElementById('admExamCount');
    if (countLabel) countLabel.textContent = 'Showing ' + total + ' of ' + total + ' scheduled exams';
  });
})();
</script>
