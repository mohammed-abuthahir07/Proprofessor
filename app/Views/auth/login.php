<?php
/** @var string $error */
/** @var string $email */
$error = $error ?? '';
$email = $email ?? '';
?>
<div class="auth-screen">
  <a class="auth-home-link" href="<?= e(url('/')) ?>"><?= icon('home', 'icon-sm') ?> Home</a>
  <span class="proto-badge proto-float">Interactive Prototype v1.0</span>
  <div class="auth-card-modern">
    <img class="auth-logo-img" src="<?= e(asset('img/logo.svg')) ?>" width="64" height="64" alt="ProProfessor AI">
    <h1>ProProfessor AI</h1>
    <p class="lede">AI-Powered Academic Operating System</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" action="<?= e(url('/login')) ?>" class="form-grid" id="loginForm" autocomplete="on">
      <?= csrf_field() ?>
      <div class="login-role-section">
        <p class="login-role-label" id="signInAsLabel">Sign in as</p>
        <div class="role-switch" role="radiogroup" aria-labelledby="signInAsLabel" id="loginRoleSwitch">
          <button type="button" class="role-option active" role="radio" aria-checked="true" data-role="Admin">Admin</button>
          <button type="button" class="role-option" role="radio" aria-checked="false" data-role="HOD">HOD</button>
          <button type="button" class="role-option" role="radio" aria-checked="false" data-role="Professor">Professor</button>
          <button type="button" class="role-option" role="radio" aria-checked="false" data-role="Student">Student</button>
        </div>
      </div>
      <div class="form-row">
        <label for="loginEmail">Email Address</label>
        <div class="input-with-icon">
          <?= icon('mail', 'input-icon') ?>
          <input type="email" name="email" id="loginEmail" required placeholder="you@university.edu" value="<?= e($email) ?>" autocomplete="username">
        </div>
      </div>
      <div class="form-row">
        <label for="loginPassword">Password</label>
        <div class="input-with-icon">
          <?= icon('lock', 'input-icon') ?>
          <input type="password" name="password" id="loginPassword" required placeholder="••••••••" value="" autocomplete="current-password">
        </div>
      </div>
      <button class="btn btn-primary btn-block btn-shine" type="submit">Sign In <?= icon('spark', 'icon-sm') ?></button>
    </form>
    <div class="auth-foot">Secured session authentication</div>
  </div>
</div>
<script>
(function () {
  var group = document.getElementById('loginRoleSwitch');
  if (!group) return;
  var options = group.querySelectorAll('.role-option');
  function selectRole(btn) {
    options.forEach(function (el) {
      var on = el === btn;
      el.classList.toggle('active', on);
      el.setAttribute('aria-checked', on ? 'true' : 'false');
      el.tabIndex = on ? 0 : -1;
    });
    btn.focus();
  }
  options.forEach(function (btn, index) {
    btn.tabIndex = btn.classList.contains('active') ? 0 : -1;
    btn.addEventListener('click', function () { selectRole(btn); });
    btn.addEventListener('keydown', function (e) {
      var key = e.key;
      if (key !== 'ArrowRight' && key !== 'ArrowLeft' && key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 'Home' && key !== 'End') return;
      e.preventDefault();
      var i = index;
      if (key === 'ArrowRight' || key === 'ArrowDown') i = (index + 1) % options.length;
      else if (key === 'ArrowLeft' || key === 'ArrowUp') i = (index - 1 + options.length) % options.length;
      else if (key === 'Home') i = 0;
      else if (key === 'End') i = options.length - 1;
      selectRole(options[i]);
    });
  });
})();
</script>
