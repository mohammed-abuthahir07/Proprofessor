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
render_header('Settings', 'settings', ['subtitle' => 'Profile & workspace']);
?>
<div class="panel" style="max-width:520px">
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
<?php render_footer(); ?>
