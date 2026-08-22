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
      <div class="form-row">
        <label>Email Address</label>
        <div class="input-with-icon">
          <?= icon('mail', 'input-icon') ?>
          <input type="email" name="email" id="loginEmail" required placeholder="you@university.edu" value="<?= e($email) ?>" autocomplete="username">
        </div>
      </div>
      <div class="form-row">
        <label>Password</label>
        <div class="input-with-icon">
          <?= icon('lock', 'input-icon') ?>
          <input type="password" name="password" required placeholder="••••••••" value="" autocomplete="current-password">
        </div>
      </div>
      <button class="btn btn-primary btn-block btn-shine" type="submit">Sign In <?= icon('spark', 'icon-sm') ?></button>
    </form>
    <div class="auth-foot">Secured session authentication</div>
  </div>
</div>
