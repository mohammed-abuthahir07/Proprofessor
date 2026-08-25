<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
AttendanceTools::ensureSchema();
$token = trim((string)get('token', ''));
$qr = $token !== '' ? AttendanceTools::findActiveQr($token) : null;
$error = '';
$success = '';
$geo = AttendanceTools::geofenceConfig((int)$user['institution_id']);
$needGeo = $qr && !empty($qr['geofence_required']) && $geo['lat'] !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $token = trim((string)post('token', $token));
    $lat = post('lat') !== null && post('lat') !== '' ? (float)post('lat') : null;
    $lng = post('lng') !== null && post('lng') !== '' ? (float)post('lng') : null;
    $result = AttendanceTools::checkInWithQr($user, $token, $lat, $lng);
    if ($result['ok']) {
        flash('success', 'Checked in — marked Present.');
        redirect('/student/attendance.php');
    }
    $error = (string)($result['error'] ?? 'Check-in failed.');
    $qr = AttendanceTools::findActiveQr($token);
    $needGeo = $qr && !empty($qr['geofence_required']) && $geo['lat'] !== null;
}

render_header('QR Check-in', 'attendance', ['subtitle' => 'Scan attendance QR']);
?>
<div class="panel" style="max-width:32rem">
  <?php if (!$qr): ?>
    <div class="empty">Invalid, expired, or inactive QR code. Ask your professor to start a new QR session.</div>
  <?php elseif (strtotime((string)$qr['expires_at']) < time()): ?>
    <div class="empty">This QR expired at <?= e((string)$qr['expires_at']) ?>.</div>
  <?php else: ?>
    <h3 style="margin-top:0">Confirm check-in</h3>
    <p>Session date <strong><?= e((string)$qr['session_date']) ?></strong> · Period <strong><?= e((string)$qr['period']) ?></strong></p>
    <p class="muted">Expires <?= e((string)$qr['expires_at']) ?></p>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($needGeo): ?>
      <p class="muted">This institution requires location for QR check-in. Allow location when prompted.</p>
    <?php elseif ($geo['lat'] !== null): ?>
      <p class="muted">Location is optional for this session.</p>
    <?php endif; ?>
    <form method="post" id="qrForm" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <input type="hidden" name="lat" id="lat" value="">
      <input type="hidden" name="lng" id="lng" value="">
      <button class="btn btn-primary" type="submit" id="checkInBtn">Check in as Present</button>
    </form>
  <?php endif; ?>
  <p style="margin-top:1rem"><a class="btn btn-sm btn-ghost" href="<?= e(base_url('/student/attendance.php')) ?>">Back to attendance</a></p>
</div>
<?php if ($qr && strtotime((string)$qr['expires_at']) >= time()): ?>
<script>
(function () {
  const form = document.getElementById('qrForm');
  const need = <?= $needGeo ? 'true' : 'false' ?>;
  form?.addEventListener('submit', function (ev) {
    if (!navigator.geolocation) {
      if (need) {
        ev.preventDefault();
        alert('Location is required but not available on this device.');
      }
      return;
    }
    ev.preventDefault();
    const btn = document.getElementById('checkInBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Getting location…'; }
    navigator.geolocation.getCurrentPosition(function (pos) {
      document.getElementById('lat').value = String(pos.coords.latitude);
      document.getElementById('lng').value = String(pos.coords.longitude);
      form.submit();
    }, function () {
      if (need) {
        alert('Location permission is required for this check-in.');
        if (btn) { btn.disabled = false; btn.textContent = 'Check in as Present'; }
      } else {
        form.submit();
      }
    }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
  });
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
