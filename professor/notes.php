<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::requireRole('professor', 'admin');
Auth::refresh();
$user = Auth::user();
StudyMaterialTools::ensureSchema();

$scopes = StudyMaterialTools::scopesForProfessor($user);
$filterType = strtolower(trim((string)get('type', '')));
if (!in_array($filterType, ['notes', 'ppt'], true)) {
    $filterType = '';
}
$filterSubject = (int)get('course');
$filterClass = (int)get('class');
$ownedSubjects = [];
$ownedClasses = [];
foreach ($scopes as $scope) {
    $ownedSubjects[(int)$scope['subject_id']] = [
        'id' => (int)$scope['subject_id'],
        'name' => (string)$scope['subject_name'],
        'code' => (string)$scope['subject_code'],
    ];
    $ownedClasses[(int)$scope['class_id']] = [
        'id' => (int)$scope['class_id'],
        'line' => (string)$scope['class_line'],
    ];
}
if ($filterSubject > 0 && !isset($ownedSubjects[$filterSubject])) {
    $filterSubject = 0;
}
if ($filterClass > 0 && !isset($ownedClasses[$filterClass])) {
    $filterClass = 0;
}
$filterQuery = static function (string $type, int $course, int $class): string {
    $q = [];
    if ($type !== '') {
        $q['type'] = $type;
    }
    if ($course > 0) {
        $q['course'] = $course;
    }
    if ($class > 0) {
        $q['class'] = $class;
    }
    return $q ? ('?' . http_build_query($q)) : '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');
    $back = '/professor/notes.php' . $filterQuery($filterType, $filterSubject, $filterClass);

    if ($action === 'delete_material') {
        $result = StudyMaterialTools::delete($user, (int)post('material_id'));
        flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? 'Material deleted. Students in that class can no longer open it.'
            : ($result['error'] ?? 'Unable to delete this material.'));
        redirect($back);
    }

    if ($action === 'send_material') {
        $scope = (string)post('scope');
        $subjectId = 0;
        $classId = 0;
        if (preg_match('/^(\d+):(\d+)$/', $scope, $m)) {
            $subjectId = (int)$m[1];
            $classId = (int)$m[2];
        }
        $result = StudyMaterialTools::create(
            $user,
            $subjectId,
            $classId,
            (string)post('material_type'),
            (string)post('title'),
            (string)post('description'),
            isset($_FILES['file']) && is_array($_FILES['file']) ? $_FILES['file'] : null
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'Unable to send this material.');
            redirect($back);
        }
        flash(
            'success',
            'Material sent successfully. ' . $result['title'] . ' is now available to students in: ' . $result['class_line'] . '.'
        );
        redirect($back);
    }
}

$anyMaterials = StudyMaterialTools::forProfessor($user);
$materials = array_values(array_filter(
    $anyMaterials,
    static function (array $item) use ($filterType, $filterSubject, $filterClass): bool {
        if ($filterType !== '' && (string)$item['material_type'] !== $filterType) {
            return false;
        }
        if ($filterSubject > 0 && (int)$item['subject_id'] !== $filterSubject) {
            return false;
        }
        if ($filterClass > 0 && (int)$item['class_id'] !== $filterClass) {
            return false;
        }
        return true;
    }
));

$groups = [];
foreach ($scopes as $scope) {
    $sid = (int)$scope['subject_id'];
    if (!isset($groups[$sid])) {
        $code = trim((string)$scope['subject_code']);
        $name = trim((string)$scope['subject_name']);
        $groups[$sid] = [
            'label' => $code !== '' ? ($code . ' · ' . $name) : $name,
            'items' => [],
        ];
    }
    $groups[$sid]['items'][] = $scope;
}

render_header('Notes', 'notes', [
    'subtitle' => 'Share notes and presentation materials with your assigned classes.',
]);
?>
<div class="prof-notes">
<?php if (!$scopes): ?>
  <div class="panel empty">
    <strong>No assigned classes available</strong>
    <p>You cannot send materials until a course and class are assigned to you.</p>
  </div>
<?php else: ?>
  <div class="grid grid-2 prof-notes-layout">
    <section class="panel">
      <div class="panel-h"><strong>Upload material</strong></div>
      <form method="post" enctype="multipart/form-data" class="form-grid" id="profNotesForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_material">
        <div class="form-row">
          <label for="scope">Class / course</label>
          <select name="scope" id="scope" required>
            <option value="">Select class / course</option>
            <?php foreach ($groups as $group): ?>
              <optgroup label="<?= e($group['label']) ?>">
                <?php foreach ($group['items'] as $scope): ?>
                  <option
                    value="<?= (int)$scope['subject_id'] ?>:<?= (int)$scope['class_id'] ?>"
                    data-course="<?= e((string)$scope['subject_name']) ?>"
                    data-class="<?= e((string)$scope['class_line']) ?>"
                  ><?= e((string)$scope['class_line']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
        <fieldset class="prof-notes-types">
          <legend>Material type</legend>
          <label>
            <input type="radio" name="material_type" value="notes" required>
            <span><?= icon('file', 'icon-inline') ?> Notes</span>
          </label>
          <label>
            <input type="radio" name="material_type" value="ppt">
            <span><?= icon('monitor', 'icon-inline') ?> PPT</span>
          </label>
        </fieldset>
        <div class="form-row">
          <label for="title">Title</label>
          <input type="text" name="title" id="title" maxlength="200" required placeholder="DBMS Unit 1 — Introduction to Database Systems">
        </div>
        <div class="form-row">
          <label for="description">Description <span class="muted">(optional)</span></label>
          <textarea name="description" id="description" maxlength="2000" rows="4" placeholder="Study material covering the unit topics."></textarea>
        </div>
        <div class="form-row">
          <label for="file">File</label>
          <input type="file" name="file" id="file" required>
          <div class="muted" id="fileHint">Notes: PDF, DOC, DOCX · PPT: PPT, PPTX, PDF · Maximum 20 MB.</div>
        </div>
        <div class="prof-notes-summary" id="sendSummary">
          <p class="prof-notes-kicker">Sending to</p>
          <strong id="sumCourse">Select a class / course</strong>
          <p id="sumClass" class="muted"></p>
          <p class="prof-notes-kicker">Material</p>
          <strong id="sumTitle">—</strong>
          <p class="prof-notes-kicker">Type</p>
          <strong id="sumType">—</strong>
        </div>
        <button type="submit" class="btn btn-primary">Upload &amp; send to class</button>
      </form>
    </section>

    <section class="panel">
      <div class="panel-h"><strong>My materials</strong></div>
      <form method="get" class="prof-notes-filters">
        <div class="prof-notes-pills" role="tablist" aria-label="Material type">
          <a class="<?= $filterType === '' ? 'is-active' : '' ?>" href="<?= e(base_url('/professor/notes.php' . $filterQuery('', $filterSubject, $filterClass))) ?>">All</a>
          <a class="<?= $filterType === 'notes' ? 'is-active' : '' ?>" href="<?= e(base_url('/professor/notes.php' . $filterQuery('notes', $filterSubject, $filterClass))) ?>">Notes</a>
          <a class="<?= $filterType === 'ppt' ? 'is-active' : '' ?>" href="<?= e(base_url('/professor/notes.php' . $filterQuery('ppt', $filterSubject, $filterClass))) ?>">PPT</a>
        </div>
        <label>
          <span class="muted">Course</span>
          <select name="course" onchange="this.form.submit()">
            <option value="">All courses</option>
            <?php foreach ($ownedSubjects as $subject): ?>
              <option value="<?= (int)$subject['id'] ?>" <?= $filterSubject === (int)$subject['id'] ? 'selected' : '' ?>>
                <?= e(trim((string)$subject['code'] . ' · ' . (string)$subject['name'], ' ·')) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($filterType !== ''): ?><input type="hidden" name="type" value="<?= e($filterType) ?>"><?php endif; ?>
        </label>
        <label>
          <span class="muted">Class</span>
          <select name="class" onchange="this.form.submit()">
            <option value="">All classes</option>
            <?php foreach ($ownedClasses as $class): ?>
              <option value="<?= (int)$class['id'] ?>" <?= $filterClass === (int)$class['id'] ? 'selected' : '' ?>>
                <?= e((string)$class['line']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>

      <?php if (!$anyMaterials): ?>
        <div class="empty">
          <strong>No materials shared yet</strong>
          <p>Start by uploading notes or a presentation for one of your assigned classes.</p>
        </div>
      <?php elseif (!$materials): ?>
        <div class="empty">No materials match these filters.</div>
      <?php else: ?>
        <div class="prof-notes-list">
          <?php foreach ($materials as $item): ?>
            <article class="prof-notes-card">
              <div class="prof-notes-card-top">
                <div>
                  <h3><?= e((string)$item['title']) ?></h3>
                  <p><?= e(trim((string)$item['subject_code'] . ' · ' . (string)$item['subject_name'], ' ·')) ?></p>
                  <p class="muted"><?= e((string)$item['class_line']) ?></p>
                </div>
                <span class="chip prof-notes-type is-<?= e((string)$item['material_type']) ?>"><?= e((string)$item['type_label']) ?></span>
              </div>
              <?php if (trim((string)($item['description'] ?? '')) !== ''): ?>
                <p class="prof-notes-desc"><?= e((string)$item['description']) ?></p>
              <?php endif; ?>
              <p class="muted prof-notes-file">
                <?= e((string)$item['file_original_name']) ?>
                · <?= e((string)$item['uploaded_label']) ?>
                · <?= e(StudyMaterialTools::formatBytes((int)$item['file_size'])) ?>
              </p>
              <div class="prof-notes-actions">
                <a class="btn btn-sm btn-primary" href="<?= e(base_url('/api/study-materials/file?id=' . (int)$item['id'])) ?>">View / Download</a>
                <button
                  type="button"
                  class="btn btn-sm prof-notes-delete"
                  data-delete="<?= (int)$item['id'] ?>"
                  data-title="<?= e((string)$item['title']) ?>"
                >Delete</button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>
</div>

<dialog class="prof-notes-dialog" id="deleteMaterial">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_material">
    <input type="hidden" name="material_id" value="">
    <h3>Delete this material?</h3>
    <p id="deleteMaterialName"></p>
    <p>This material will no longer be available to the assigned students.</p>
    <div class="prof-notes-dialog-actions">
      <button type="button" class="btn btn-sm" id="deleteMaterialCancel">Cancel</button>
      <button type="submit" class="btn btn-sm prof-notes-delete">Delete</button>
    </div>
  </form>
</dialog>
<script>
(function () {
  var form = document.getElementById('profNotesForm');
  if (!form) return;
  var scope = document.getElementById('scope');
  var title = document.getElementById('title');
  var file = document.getElementById('file');
  var hint = document.getElementById('fileHint');
  var sumCourse = document.getElementById('sumCourse');
  var sumClass = document.getElementById('sumClass');
  var sumTitle = document.getElementById('sumTitle');
  var sumType = document.getElementById('sumType');
  function selectedType() {
    var picked = form.querySelector('input[name="material_type"]:checked');
    return picked ? picked.value : '';
  }
  function refresh() {
    var option = scope.options[scope.selectedIndex];
    var course = option && option.value ? (option.getAttribute('data-course') || '') : '';
    var klass = option && option.value ? (option.getAttribute('data-class') || '') : '';
    sumCourse.textContent = course || 'Select a class / course';
    sumClass.textContent = klass;
    var name = (title.value || '').trim();
    sumTitle.textContent = name || '—';
    var type = selectedType();
    sumType.textContent = type === 'ppt' ? 'PPT' : (type === 'notes' ? 'Notes' : '—');
    if (type === 'ppt') {
      file.setAttribute('accept', '.ppt,.pptx,.pdf,application/pdf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation');
      hint.textContent = 'PPT, PPTX, or PDF · Maximum 20 MB.';
    } else if (type === 'notes') {
      file.setAttribute('accept', '.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document');
      hint.textContent = 'PDF, DOC, or DOCX · Maximum 20 MB.';
    }
  }
  scope.addEventListener('change', refresh);
  title.addEventListener('input', refresh);
  form.querySelectorAll('input[name="material_type"]').forEach(function (input) {
    input.addEventListener('change', refresh);
  });
  refresh();

  var dialog = document.getElementById('deleteMaterial');
  var cancel = document.getElementById('deleteMaterialCancel');
  document.querySelectorAll('[data-delete]').forEach(function (button) {
    button.addEventListener('click', function () {
      dialog.querySelector('[name="material_id"]').value = button.getAttribute('data-delete') || '';
      document.getElementById('deleteMaterialName').textContent = button.getAttribute('data-title') || '';
      if (typeof dialog.showModal === 'function') dialog.showModal();
    });
  });
  if (cancel) cancel.addEventListener('click', function () { dialog.close(); });
})();
</script>
<?php
render_footer();
