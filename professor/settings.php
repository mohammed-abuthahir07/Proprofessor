<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $existing = json_decode((string)($user['preferences'] ?? '{}'), true) ?: [];
    $merged = NotificationService::mergePreferencePost($existing, [
        'email_notifications' => post('email_notifications'),
        'digest_mode' => post('digest_mode', 'immediate'),
        'theme' => post('theme', 'light'),
        'notification_channels' => post('notification_channels') ?: [],
    ]);
    Database::update('users', [
        'full_name' => trim((string)post('full_name')),
        'phone' => trim((string)post('phone')),
        'preferences' => json_encode($merged, JSON_UNESCAPED_UNICODE),
    ], 'id = :id', ['id' => $user['id']]);
    if (post('new_password')) {
        Database::update('users', [
            'password_hash' => password_hash((string)post('new_password'), PASSWORD_BCRYPT),
        ], 'id = :id', ['id' => $user['id']]);
    }
    Auth::refresh();
    flash('success', 'Settings saved.');
    redirect('/professor/settings.php');
}

$notifPrefs = NotificationService::preferencesFromUser($user);
$prefs = $notifPrefs;
$channels = $prefs['notification_channels'];
$providers = NotificationService::allProviderStatuses();
$categories = [
    'assignments' => 'Assignments',
    'attendance' => 'Attendance',
    'course_plans' => 'Course Plans',
    'approvals' => 'Approvals',
    'system' => 'System',
    'ai' => 'AI',
];

render_header('Settings', 'settings', ['subtitle' => 'Profile & workspace']);
?>
<div class="panel" style="max-width:640px">
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <div class="form-row"><label>Full name</label><input name="full_name" value="<?= e($user['full_name']) ?>"></div>
    <div class="form-row"><label>Email</label><input value="<?= e($user['email']) ?>" disabled></div>
    <div class="form-row"><label>Phone</label><input name="phone" value="<?= e((string)$user['phone']) ?>"></div>
    <div class="form-row"><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep"></div>

    <label><input type="checkbox" name="email_notifications" value="1" <?= !empty($prefs['email_notifications']) ? 'checked' : '' ?>> Email notifications (default for categories)</label>

    <div class="form-row">
      <label>Digest mode</label>
      <select name="digest_mode">
        <option value="immediate" <?= $prefs['digest_mode'] === 'immediate' ? 'selected' : '' ?>>Immediate</option>
        <option value="daily" <?= $prefs['digest_mode'] === 'daily' ? 'selected' : '' ?>>Daily Digest</option>
        <option value="weekly" <?= $prefs['digest_mode'] === 'weekly' ? 'selected' : '' ?>>Weekly Digest</option>
      </select>
      <div class="muted" style="font-size:.8rem;margin-top:.25rem">Digest summarizes your feed; individual notifications still appear.</div>
    </div>

    <div style="margin-top:.5rem">
      <strong>Delivery preferences by category</strong>
      <div class="muted" style="font-size:.8rem;margin:.25rem 0 .6rem">
        WhatsApp: <?= e($providers['whatsapp']['label']) ?> · SMS: <?= e($providers['sms']['label']) ?>
        <?php if (!$providers['whatsapp']['configured'] || !$providers['sms']['configured']): ?>
          (enable in server config when ready — no fake sends)
        <?php endif; ?>
      </div>
      <div class="table-wrap"><table>
        <thead>
          <tr>
            <th>Category</th>
            <th>In-App</th>
            <th>Email</th>
            <th>WhatsApp</th>
            <th>SMS</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $key => $label):
            $row = $channels[$key] ?? ['in_app' => true, 'email' => false, 'whatsapp' => false, 'sms' => false];
        ?>
          <tr>
            <td><?= e($label) ?></td>
            <td>
              <input type="hidden" name="notification_channels[<?= e($key) ?>][in_app]" value="0">
              <input type="checkbox" name="notification_channels[<?= e($key) ?>][in_app]" value="1" <?= !empty($row['in_app']) ? 'checked' : '' ?>>
            </td>
            <td>
              <input type="hidden" name="notification_channels[<?= e($key) ?>][email]" value="0">
              <input type="checkbox" name="notification_channels[<?= e($key) ?>][email]" value="1" <?= !empty($row['email']) ? 'checked' : '' ?>>
            </td>
            <td>
              <input type="hidden" name="notification_channels[<?= e($key) ?>][whatsapp]" value="0">
              <input type="checkbox" name="notification_channels[<?= e($key) ?>][whatsapp]" value="1" <?= !empty($row['whatsapp']) ? 'checked' : '' ?> <?= !$providers['whatsapp']['configured'] ? 'title="Provider not configured"' : '' ?>>
            </td>
            <td>
              <input type="hidden" name="notification_channels[<?= e($key) ?>][sms]" value="0">
              <input type="checkbox" name="notification_channels[<?= e($key) ?>][sms]" value="1" <?= !empty($row['sms']) ? 'checked' : '' ?> <?= !$providers['sms']['configured'] ? 'title="Provider not configured"' : '' ?>>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <button class="btn btn-primary" type="submit">Save</button>
  </form>
</div>
<?php render_footer(); ?>
