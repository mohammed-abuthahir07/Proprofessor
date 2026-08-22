<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    Database::update('users', [
        'full_name' => trim((string)post('full_name')),
        'phone' => trim((string)post('phone')),
        'preferences' => json_encode([
            'email_notifications' => (bool)post('email_notifications'),
            'theme' => post('theme', 'light'),
        ]),
    ], 'id = :id', ['id'=>$user['id']]);
    if (post('new_password')) {
        Database::update('users', [
            'password_hash' => password_hash((string)post('new_password'), PASSWORD_BCRYPT),
        ], 'id = :id', ['id'=>$user['id']]);
    }
    Auth::refresh();
    flash('success', 'Settings saved.');
    redirect('/professor/settings.php');
}
$prefs = json_decode($user['preferences'] ?? '{}', true) ?: [];
$geminiOk = (new Gemini())->isConfigured();
render_header('Settings', 'settings', ['subtitle' => 'Profile & workspace']);
?>
<div class="grid grid-2">
  <div class="panel">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <div class="form-row"><label>Full name</label><input name="full_name" value="<?= e($user['full_name']) ?>"></div>
      <div class="form-row"><label>Email</label><input value="<?= e($user['email']) ?>" disabled></div>
      <div class="form-row"><label>Phone</label><input name="phone" value="<?= e((string)$user['phone']) ?>"></div>
      <div class="form-row"><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep"></div>
      <label><input type="checkbox" name="email_notifications" value="1" <?= !empty($prefs['email_notifications'])?'checked':'' ?>> Email notifications</label>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </div>
  <div class="panel">
    <h3>AI connection</h3>
    <p>Gemini API: <?= $geminiOk ? '<span class="badge badge-success">Configured</span>' : '<span class="badge badge-warn">Demo mode</span>' ?></p>
    <p style="color:var(--muted);font-size:.9rem">Set <code>gemini.api_key</code> in <code>config/config.php</code> or environment variable <code>GEMINI_API_KEY</code>.</p>
    <h3 style="margin-top:1.2rem">My plans shortcut</h3>
    <a class="btn btn-ghost" href="<?= e(base_url('/professor/plans.php')) ?>">Open My Plans</a>
  </div>
</div>
<?php render_footer(); ?>
