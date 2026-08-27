<?php
/** @var array $inst */
/** @var array $depts */
/** @var array $settings */
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
<div class="panel" style="margin-top:1rem">
  <h3>Academic courses</h3>
  <p style="color:var(--muted);font-size:.88rem;margin-top:0">Courses and subjects are managed by each department HOD under <strong>HOD → Courses</strong>. Admin sets up departments and user accounts; classes are managed under <strong>Users & Roles</strong>.</p>
</div>
