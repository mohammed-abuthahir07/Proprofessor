<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod');
$user = Auth::user();
$userId = (int)$user['id'];
$deptId = hod_department_id($user);
$dept = $deptId > 0
    ? Database::fetch('SELECT name, code FROM departments WHERE id = ? AND institution_id = ?', [$deptId, (int)$user['institution_id']])
    : null;
$deptName = trim((string)($dept['name'] ?? ''));
if ($deptName === '') {
    $deptName = 'Not assigned';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action', '');
    $fresh = Database::fetch(
        'SELECT id, full_name, preferences, password_hash, role FROM users WHERE id = ? AND role = "hod" AND is_active = 1',
        [$userId]
    );
    if (!$fresh) {
        flash('error', 'Your account could not be updated.');
        redirect('/hod/settings');
    }

    if ($action === 'profile') {
        $name = trim((string)post('full_name'));
        if ($name === '') {
            flash('error', 'Name is required.');
            redirect('/hod/settings');
        }
        if (mb_strlen($name) > 160) {
            flash('error', 'Name must be 160 characters or fewer.');
            redirect('/hod/settings');
        }
        Database::update('users', ['full_name' => $name], 'id = :id AND role = :role', ['id' => $userId, 'role' => 'hod']);
        Auth::refresh();
        flash('success', 'Profile saved.');
        redirect('/hod/settings');
    }

    if ($action === 'alerts') {
        $existing = json_decode((string)($fresh['preferences'] ?? ''), true);
        if (!is_array($existing)) {
            $existing = [];
        }
        $alerts = NotificationService::normalizeHodAlerts([
            'plan_approved' => post('plan_approved'),
            'plan_rejected' => post('plan_rejected'),
            'weekly_summary' => post('weekly_summary'),
            'ai_complete' => post('ai_complete'),
        ]);
        $existing['hod_alerts'] = $alerts;
        $digest = strtolower((string)($existing['digest_mode'] ?? 'immediate'));
        if ($alerts['weekly_summary']) {
            $existing['digest_mode'] = 'weekly';
        } elseif ($digest === 'weekly') {
            $existing['digest_mode'] = 'immediate';
        }
        Database::update('users', [
            'preferences' => json_encode($existing, JSON_UNESCAPED_UNICODE),
        ], 'id = :id AND role = :role', ['id' => $userId, 'role' => 'hod']);
        Auth::refresh();
        flash('success', 'Notification preferences saved.');
        redirect('/hod/settings');
    }

    if ($action === 'password') {
        $current = (string)post('current_password');
        $next = (string)post('new_password');
        if ($current === '' || $next === '') {
            flash('error', 'Current password and new password are required.');
            redirect('/hod/settings');
        }
        if (!password_verify($current, (string)$fresh['password_hash'])) {
            flash('error', 'Current password is incorrect.');
            redirect('/hod/settings');
        }
        if (strlen($next) < 8) {
            flash('error', 'New password must be at least 8 characters.');
            redirect('/hod/settings');
        }
        Database::update('users', [
            'password_hash' => password_hash($next, PASSWORD_BCRYPT),
        ], 'id = :id AND role = :role', ['id' => $userId, 'role' => 'hod']);
        Auth::refresh();
        flash('success', 'Password updated successfully.');
        redirect('/hod/settings');
    }

    flash('error', 'That settings action is not available.');
    redirect('/hod/settings');
}

$user = Auth::user();
$alerts = NotificationService::hodAlertsFromUser($user);
$alertRows = [
    'plan_approved' => ['Plan approved alerts', 'Email when HOD approves your plan'],
    'plan_rejected' => ['Plan rejected alerts', 'Email when plan needs revision'],
    'weekly_summary' => ['Weekly activity summary', 'Recap of your week every Monday'],
    'ai_complete' => ['AI generation complete', 'Notify when AI finishes generating'],
];

render_header('Settings', 'settings', ['subtitle' => 'Manage your account and preferences']);
?>
<div class="hod-set">
  <div class="hod-set-grid">
    <section class="hod-panel hod-set-card">
      <h2><?= icon('users', 'icon-inline') ?> Profile</h2>
      <form method="post" class="hod-set-form" data-busy="Saving...">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <label class="hod-set-field">
          <span>Name</span>
          <input name="full_name" value="<?= e((string)$user['full_name']) ?>" required maxlength="160" autocomplete="name">
        </label>
        <label class="hod-set-field">
          <span>Email</span>
          <input value="<?= e((string)$user['email']) ?>" disabled autocomplete="email">
        </label>
        <label class="hod-set-field">
          <span>Department</span>
          <select disabled aria-label="Department">
            <option><?= e($deptName) ?></option>
          </select>
        </label>
        <button class="btn btn-primary hod-set-save" type="submit">Save Changes</button>
      </form>
    </section>

    <section class="hod-panel hod-set-card">
      <h2><?= icon('bell', 'icon-inline') ?> Notifications</h2>
      <form method="post" id="hodAlertForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="alerts">
        <?php foreach ($alertRows as $key => [$label, $hint]): ?>
          <div class="hod-set-alert">
            <div>
              <strong><?= e($label) ?></strong>
              <small><?= e($hint) ?></small>
            </div>
            <label class="hod-switch">
              <input type="hidden" name="<?= e($key) ?>" value="0">
              <input type="checkbox" name="<?= e($key) ?>" value="1" <?= !empty($alerts[$key]) ? 'checked' : '' ?> aria-label="<?= e($label) ?>">
              <span></span>
            </label>
          </div>
        <?php endforeach; ?>
      </form>
    </section>
  </div>

  <section class="hod-panel hod-set-security">
    <h2><?= icon('lock', 'icon-inline') ?> Security</h2>
    <form method="post" class="hod-set-form" data-busy="Updating...">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="hod-set-pass">
        <label class="hod-set-field">
          <span>Current Password</span>
          <input type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <label class="hod-set-field">
          <span>New Password</span>
          <input type="password" name="new_password" autocomplete="new-password" required minlength="8">
        </label>
      </div>
      <div class="hod-set-actions">
        <button class="btn btn-primary" type="submit">Update Password</button>
        <a class="btn hod-set-signout" href="<?= e(base_url('/logout')) ?>"><?= icon('logout', 'icon-inline') ?> Sign Out</a>
      </div>
    </form>
  </section>
</div>
<script>
(function () {
  document.querySelectorAll('form[data-busy]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (!btn || btn.dataset.locked === '1') return;
      btn.dataset.locked = '1';
      btn.disabled = true;
      btn.textContent = form.getAttribute('data-busy') || 'Saving...';
    });
  });
  var alerts = document.getElementById('hodAlertForm');
  if (alerts) {
    alerts.addEventListener('change', function () {
      if (alerts.dataset.sending === '1') return;
      alerts.dataset.sending = '1';
      alerts.requestSubmit();
    });
  }
})();
</script>
<?php render_footer(); ?>
