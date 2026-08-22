<?php
/** @var array $users */
/** @var array $depts */
/** @var array $classes */
/** @var array $filters */
/** @var array|null $editing */
/** @var array $permissions */
/** @var array $editPerms */
/** @var bool $editIsFull */
$editing = $editing ?? null;
$filters = $filters ?? ['role' => '', 'department_id' => '', 'is_active' => '', 'program_level' => '', 'year' => ''];
$permissions = $permissions ?? [];
$editPerms = $editPerms ?? [];
$isEdit = is_array($editing);
$roleVal = $isEdit ? (string)$editing['role'] : 'professor';
?>
<p class="lede" style="margin-top:0">Add or remove professors, HODs, students, and sub-admins. Access is limited to <strong>this college</strong> (row-level). Department assignment segments academic data. Unchecked admin permissions = full College Admin; checked boxes = limited sub-admin.</p>

<div class="panel">
  <form method="get" action="<?= e(url('/admin/users')) ?>" class="form-grid user-filter">
    <div class="form-row"><label>Role</label>
      <select name="role">
        <option value="">All roles</option>
        <?php foreach (['admin' => 'Admin / Sub-admin', 'hod' => 'HOD', 'professor' => 'Professor', 'student' => 'Student'] as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= ($filters['role'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>Department</label>
      <select name="department_id">
        <option value="">All departments</option>
        <?php foreach ($depts as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= (string)($filters['department_id'] ?? '') === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>Access</label>
      <select name="status">
        <option value="">All</option>
        <option value="1" <?= (string)($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= (string)($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Removed</option>
      </select>
    </div>
    <div class="form-row student-only-filter"><label>UG / PG (students)</label>
      <select name="program_level">
        <option value="">All</option>
        <option value="UG" <?= ($filters['program_level'] ?? '') === 'UG' ? 'selected' : '' ?>>UG</option>
        <option value="PG" <?= ($filters['program_level'] ?? '') === 'PG' ? 'selected' : '' ?>>PG</option>
      </select>
    </div>
    <div class="form-row student-only-filter"><label>Year (students)</label>
      <select name="year">
        <option value="">All years</option>
        <?php foreach ([1, 2, 3, 4] as $yr): ?>
          <option value="<?= $yr ?>" <?= (string)($filters['year'] ?? '') === (string)$yr ? 'selected' : '' ?>><?= $yr ?> year</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-ghost" type="submit">Filter</button>
  </form>
</div>

<div class="grid grid-2" style="margin-top:1rem">
  <div class="panel">
    <div class="panel-h">
      <h3><?= $isEdit ? 'Edit user' : 'Add user' ?></h3>
      <?php if ($isEdit): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/users')) ?>">Cancel</a>
      <?php endif; ?>
    </div>
    <form method="post" action="<?= e(url('/admin/users')) ?>" class="form-grid" id="userForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
      <?php if ($isEdit): ?><input type="hidden" name="user_id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
      <div class="form-row"><label>Full name</label><input name="full_name" required value="<?= e((string)($editing['full_name'] ?? '')) ?>"></div>
      <div class="form-row"><label>Email</label><input type="email" name="email" required value="<?= e((string)($editing['email'] ?? '')) ?>"></div>
      <div class="form-row two">
        <div><label>Role</label>
          <select name="role" id="userRole">
            <option value="professor" <?= $roleVal === 'professor' ? 'selected' : '' ?>>Professor</option>
            <option value="student" <?= $roleVal === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="hod" <?= $roleVal === 'hod' ? 'selected' : '' ?>>HOD</option>
            <option value="admin" <?= $roleVal === 'admin' ? 'selected' : '' ?>>Admin / Sub-admin</option>
          </select>
        </div>
        <div><label>Department</label>
          <select name="department_id" id="userDept">
            <option value="">—</option>
            <?php foreach ($depts as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= (int)($editing['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row two" id="studentFields">
        <div><label>Class (students)</label>
          <select name="class_id">
            <option value="">—</option>
            <?php if (!$classes): ?>
              <option value="" disabled>No classes yet — add one on the right</option>
            <?php endif; ?>
            <?php foreach ($classes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($editing['class_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e(class_batch_label($c)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Register No</label><input name="register_no" value="<?= e((string)($editing['register_no'] ?? '')) ?>"></div>
      </div>
      <div class="form-row two">
        <div><label>Employee ID</label><input name="employee_id" value="<?= e((string)($editing['employee_id'] ?? '')) ?>"></div>
        <div><label>Phone</label><input name="phone" value="<?= e((string)($editing['phone'] ?? '')) ?>"></div>
      </div>
      <?php if (!$isEdit): ?>
        <div class="form-row"><label>Password</label><input name="password" value="Password@123" autocomplete="new-password"></div>
      <?php endif; ?>
      <div id="permBox" class="form-row" hidden>
        <label>Sub-admin permissions (leave empty for full College Admin)</label>
        <div class="perm-grid">
          <?php foreach ($permissions as $code => $label): ?>
            <label><input type="checkbox" name="permissions[]" value="<?= e($code) ?>" <?= in_array($code, $editPerms, true) ? 'checked' : '' ?>> <?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Create' ?></button>
    </form>
  </div>

  <div>
    <div class="panel">
      <h3>Add class</h3>
      <p style="color:var(--muted);font-size:.85rem;margin:.3rem 0 .8rem">Needed before assigning students. Scoped to this institution.</p>
      <form method="post" action="<?= e(url('/admin/users')) ?>" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_class">
        <div class="form-row"><label>Department</label>
          <select name="department_id" required>
            <option value="">Select department</option>
            <?php foreach ($depts as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-row two">
          <div><label>UG / PG</label>
            <select name="program_level" required>
              <option value="">Select</option>
              <option value="UG">UG</option>
              <option value="PG">PG</option>
            </select>
          </div>
          <div><label>Year</label>
            <select name="year" required>
              <option value="">Select</option>
              <?php foreach ([1, 2, 3, 4] as $yr): ?>
                <option value="<?= $yr ?>"><?= $yr ?> year</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row two">
          <div><label>Class name</label><input name="class_name" required placeholder="CSE"></div>
          <div><label>Section</label><input name="section" required placeholder="A"></div>
        </div>
        <button class="btn btn-ghost" type="submit">Add class</button>
      </form>
    </div>
    <div class="panel" style="margin-top:1rem">
      <h3>Bulk import (Excel)</h3>
      <p style="color:var(--muted);font-size:.85rem;margin:.3rem 0 .8rem">Download the template, fill rows in Excel, then <strong>Save As → CSV</strong> and upload. Existing emails are skipped. Department code must match (e.g. CSE).</p>
      <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/users?export=template')) ?>">Download Excel template</a>
      <form method="post" action="<?= e(url('/admin/users')) ?>" enctype="multipart/form-data" class="form-grid" style="margin-top:.9rem">
        <?= csrf_field() ?><input type="hidden" name="action" value="import">
        <div class="form-row"><label>CSV file</label><input type="file" name="import_file" accept=".csv,.txt,.tsv" required></div>
        <button class="btn btn-primary" type="submit">Import users</button>
      </form>
    </div>
  </div>
</div>

<div class="panel" style="margin-top:1rem">
  <div class="panel-h"><h3>People in this college</h3><span class="chip"><?= count($users) ?> shown</span></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Role</th><th>Department</th><th>Class</th><th>Access</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u):
      $listed = Permissions::listed($u);
      $roleLabel = $u['role'] === 'admin' ? ($listed === null ? 'College Admin' : 'Sub-admin') : $u['role'];
    ?>
      <tr>
        <td>
          <strong><?= e($u['full_name']) ?></strong>
          <div style="font-size:.75rem;color:var(--muted)"><?= e($u['email']) ?></div>
        </td>
        <td><span class="chip"><?= e($roleLabel) ?></span></td>
        <td><?= e((string)$u['dept_name']) ?></td>
        <td><?php
          if (($u['role'] ?? '') === 'student') {
              echo e(class_batch_label([
                  'name' => $u['class_name'] ?? '',
                  'year' => $u['class_year'] ?? null,
                  'section' => $u['class_section'] ?? '',
                  'meta' => $u['class_meta'] ?? null,
                  'dept_code' => $u['dept_code'] ?? '',
                  'dept_name' => $u['dept_name'] ?? '',
              ]));
          }
        ?></td>
        <td><?= !empty($u['is_active']) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Removed</span>' ?></td>
        <td>
          <div class="user-actions">
            <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/users?edit=' . (int)$u['id'])) ?>">Edit</a>
            <form method="post" action="<?= e(url('/admin/users')) ?>"><?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <button class="btn btn-sm btn-ghost" type="submit"><?= !empty($u['is_active']) ? 'Remove' : 'Restore' ?></button>
            </form>
            <form method="post" action="<?= e(url('/admin/users')) ?>" class="reset-pw"><?= csrf_field() ?>
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <input type="password" name="new_password" placeholder="New password" minlength="8" required>
              <button class="btn btn-sm btn-ghost" type="submit">Set password</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (!$users): ?><div class="empty">No users match this filter.</div><?php endif; ?>
</div>
<script>
(function () {
  const role = document.getElementById('userRole');
  const student = document.getElementById('studentFields');
  const perms = document.getElementById('permBox');
  const sync = () => {
    const v = role?.value;
    if (student) student.hidden = v !== 'student';
    if (perms) perms.hidden = v !== 'admin';
  };
  role?.addEventListener('change', sync);
  sync();
})();
</script>
