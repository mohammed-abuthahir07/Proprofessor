<?php
/** @var array $inst */
/** @var array $depts */
/** @var array $settings */
/** @var array $subjectCatalog */
/** @var array $catalogFilters */
/** @var int $catalogTotal */
$subjectCatalog = $subjectCatalog ?? [];
$catalogFilters = $catalogFilters ?? ['department_id' => 0, 'year' => 0, 'semester' => '', 'type' => ''];
$catalogTotal = (int)($catalogTotal ?? 0);

$catalogQuery = static function (array $overrides = []) use ($catalogFilters): string {
    $params = array_merge($catalogFilters, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            unset($params[$key]);
        }
    }
    $query = http_build_query($params);
    return url('/admin/institution' . ($query !== '' ? '?' . $query : '') . '#academic-courses');
};
?>
<div class="grid grid-2">
  <div class="panel">
    <form method="post" action="<?= e(url('/admin/institution')) ?>" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_inst">
      <div class="form-row"><label>College name</label><input name="name" value="<?= e($inst['name']) ?>"></div>
      <div class="form-row"><label>University affiliation</label><input name="affiliation_university" value="<?= e((string)$inst['affiliation_university']) ?>"></div>
      <div class="form-row two">
        <div><label>NAAC grade</label><input name="naac_grade" value="<?= e((string)$inst['naac_grade']) ?>"></div>
        <div><label>Attendance min %</label><input name="attendance_min" value="<?= e((string)($settings['attendance_min'] ?? 75)) ?>"></div>
      </div>
      <div class="form-row"><label>Logo URL</label><input name="logo_url" value="<?= e((string)($inst['logo_url'] ?? '')) ?>" placeholder="https://…/logo.png"></div>
      <div class="form-row two">
        <div><label>Brand primary (#hex)</label><input name="brand_primary" value="<?= e((string)($settings['brand_primary'] ?? '1E3A8A')) ?>" placeholder="1E3A8A"></div>
        <div><label>Brand secondary (#hex)</label><input name="brand_secondary" value="<?= e((string)($settings['brand_secondary'] ?? '0F172A')) ?>" placeholder="0F172A"></div>
      </div>
      <div class="form-row"><label>Brand accent (#hex)</label><input name="brand_accent" value="<?= e((string)($settings['brand_accent'] ?? 'D97706')) ?>" placeholder="D97706"></div>
      <div class="form-row two">
        <div><label>QR geofence lat</label><input name="geofence_lat" value="<?= e((string)($settings['geofence_lat'] ?? '')) ?>" placeholder="optional"></div>
        <div><label>QR geofence lng</label><input name="geofence_lng" value="<?= e((string)($settings['geofence_lng'] ?? '')) ?>" placeholder="optional"></div>
      </div>
      <div class="form-row two">
        <div><label>Geofence radius (m)</label><input name="geofence_radius_m" value="<?= e((string)($settings['geofence_radius_m'] ?? '150')) ?>"></div>
        <div><label><input type="checkbox" name="geofence_required_for_qr" value="1" <?= !empty($settings['geofence_required_for_qr']) ? 'checked' : '' ?>> Require geofence for QR</label></div>
      </div>
      <div class="form-row two">
        <div><label>Academic year</label><input name="academic_year" value="<?= e((string)$inst['academic_year']) ?>"></div>
        <div><label>Semester</label><input name="current_semester" value="<?= e((string)$inst['current_semester']) ?>"></div>
      </div>
      <div class="form-row two">
        <div><label>City</label><input name="city" value="<?= e((string)$inst['city']) ?>"></div>
        <div><label>State</label><input name="state" value="<?= e((string)$inst['state']) ?>"></div>
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </div>
  <div class="panel">
    <h3>Departments</h3>
    <ul><?php foreach ($depts as $d): ?><li><strong><?= e($d['code']) ?></strong> — <?= e($d['name']) ?></li><?php endforeach; ?></ul>
    <form method="post" action="<?= e(url('/admin/institution')) ?>" class="form-grid" style="margin-top:1rem">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_dept">
      <div class="form-row two">
        <div><label>Name</label><input name="dept_name" required></div>
        <div><label>Code</label><input name="dept_code" required></div>
      </div>
      <button class="btn btn-ghost" type="submit">Add department</button>
    </form>
  </div>
</div>

<div class="panel" id="academic-courses" style="margin-top:1rem">
  <div class="panel-h" style="align-items:center">
    <div>
      <h3 style="margin:0">Academic courses</h3>
      <p class="muted" style="font-size:.88rem;margin:.35rem 0 0">All subjects created by department HODs — browse by department, year (1–4), and Odd/Even semester.</p>
    </div>
    <span class="chip"><?= (int)$catalogTotal ?> subject<?= $catalogTotal === 1 ? '' : 's' ?></span>
  </div>

  <div class="form-row" style="margin-top:1rem">
    <label>Department</label>
    <div class="chip-row" role="navigation" aria-label="Department">
      <a class="chip<?= (int)$catalogFilters['department_id'] === 0 ? ' active' : '' ?>" href="<?= e($catalogQuery(['department_id' => 0])) ?>">All</a>
      <?php foreach ($depts as $d): ?>
        <a class="chip<?= (int)$catalogFilters['department_id'] === (int)$d['id'] ? ' active' : '' ?>" href="<?= e($catalogQuery(['department_id' => (int)$d['id']])) ?>"><?= e((string)$d['code']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-row">
    <label>Year</label>
    <div class="chip-row" role="navigation" aria-label="Academic year">
      <a class="chip<?= (int)$catalogFilters['year'] === 0 ? ' active' : '' ?>" href="<?= e($catalogQuery(['year' => 0])) ?>">All</a>
      <?php foreach ([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'] as $yVal => $yLabel): ?>
        <a class="chip<?= (int)$catalogFilters['year'] === $yVal ? ' active' : '' ?>" href="<?= e($catalogQuery(['year' => $yVal])) ?>"><?= e($yLabel) ?> Year</a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-row">
    <label>Semester</label>
    <div class="chip-row" role="navigation" aria-label="Semester">
      <a class="chip<?= ($catalogFilters['semester'] ?? '') === '' ? ' active' : '' ?>" href="<?= e($catalogQuery(['semester' => ''])) ?>">All</a>
      <a class="chip<?= ($catalogFilters['semester'] ?? '') === 'odd' ? ' active' : '' ?>" href="<?= e($catalogQuery(['semester' => 'odd'])) ?>">Odd</a>
      <a class="chip<?= ($catalogFilters['semester'] ?? '') === 'even' ? ' active' : '' ?>" href="<?= e($catalogQuery(['semester' => 'even'])) ?>">Even</a>
    </div>
  </div>
  <div class="form-row">
    <label>Type</label>
    <div class="chip-row" role="navigation" aria-label="Course type">
      <a class="chip<?= ($catalogFilters['type'] ?? '') === '' ? ' active' : '' ?>" href="<?= e($catalogQuery(['type' => ''])) ?>">All</a>
      <a class="chip<?= ($catalogFilters['type'] ?? '') === 'theory' ? ' active' : '' ?>" href="<?= e($catalogQuery(['type' => 'theory'])) ?>">Courses</a>
      <a class="chip<?= ($catalogFilters['type'] ?? '') === 'lab' ? ' active' : '' ?>" href="<?= e($catalogQuery(['type' => 'lab'])) ?>">Labs</a>
    </div>
  </div>

  <?php if (!$subjectCatalog): ?>
    <div class="empty" style="margin-top:1rem">No subjects match these filters. HODs add courses under HOD → Courses.</div>
  <?php else: ?>
    <?php foreach ($subjectCatalog as $block):
      $dept = $block['department'];
      $subjects = $block['subjects'];
      if (!$subjects) {
          continue;
      }
      $grouped = [];
      foreach ($subjects as $s) {
        $y = (int)($s['year_level'] ?? 0);
        $sk = (string)($s['semester_key'] ?? 'odd');
        if ($y < 1) {
            $y = 0;
        }
        $grouped[$y][$sk][] = $s;
      }
      ksort($grouped);
    ?>
      <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--line)">
        <div class="panel-h" style="margin-bottom:.65rem">
          <strong><?= e((string)($dept['code'] ?? '')) ?> — <?= e((string)($dept['name'] ?? 'Department')) ?></strong>
          <span class="chip"><?= count($subjects) ?></span>
        </div>
        <?php foreach ($grouped as $y => $bySem):
          ksort($bySem);
          $yearLabel = $y > 0 ? subject_year_label($y) : 'Unassigned year';
        ?>
          <?php foreach ($bySem as $sk => $rows):
            $semLabel = $sk === 'even' ? 'Even Semester' : 'Odd Semester';
          ?>
            <div style="margin:.75rem 0 .35rem;font-size:.88rem;color:var(--muted)">
              <strong style="color:var(--text)"><?= e($yearLabel) ?></strong> · <?= e($semLabel) ?>
            </div>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Subject</th>
                    <th>Type</th>
                    <th>Credits</th>
                    <th>Hours</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $s): ?>
                    <tr>
                      <td><strong><?= e((string)$s['code']) ?></strong></td>
                      <td><?= e((string)$s['name']) ?></td>
                      <td><span class="chip"><?= ($s['course_type'] ?? '') === 'lab' ? 'Lab' : 'Course' ?></span></td>
                      <td><?= e((string)($s['credits'] ?? '')) ?></td>
                      <td><?= e((string)($s['contact_hours'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
