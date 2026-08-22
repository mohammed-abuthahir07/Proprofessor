<?php
$login = url('/login');
$home = url('/');
?>
<a class="lp-skip" href="#home">Skip to content</a>

<header class="lp-nav" id="lpNav">
  <div class="lp-nav-inner">
    <a class="lp-brand" href="#home">
      <img src="<?= e(asset('img/logo.svg')) ?>" width="40" height="40" alt="">
      <span>ProProfessor AI</span>
    </a>
    <button class="lp-burger" id="lpBurger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="lpMenu">
      <?= icon('menu') ?>
    </button>
    <nav class="lp-menu" id="lpMenu">
      <a href="#home">Home</a>
      <a href="#features">Features</a>
      <a href="#how">How It Works</a>
      <a href="#benefits">Benefits</a>
      <a href="#contact">Contact</a>
      <a class="btn btn-ghost btn-sm lp-nav-login" href="<?= e($login) ?>">Login</a>
      <a class="btn btn-primary btn-sm btn-shine" href="<?= e($login) ?>"><?= icon('spark') ?> Get Started</a>
    </nav>
  </div>
</header>

<main>
  <section class="lp-hero" id="home">
    <div class="lp-hero-copy lp-reveal">
      <p class="lp-kicker"><?= icon('spark', 'icon-sm') ?> AI-Native Academic Operating System</p>
      <h1>Transform Academic Management with <span>ProProfessor AI</span></h1>
      <p class="lp-lede">Help institutions, departments, HODs, professors, and students run academic workflows in one secure, institution-isolated platform — from course planning to analytics and compliance.</p>
      <div class="lp-hero-actions">
        <a class="btn btn-primary btn-shine" href="<?= e($login) ?>"><?= icon('spark') ?> Get Started</a>
        <a class="btn btn-ghost" href="<?= e($login) ?>"><?= icon('lock', 'icon-sm') ?> Login</a>
      </div>
      <ul class="lp-hero-points">
        <li><?= icon('check', 'icon-sm') ?> Role-based workspaces</li>
        <li><?= icon('check', 'icon-sm') ?> Institution data isolation</li>
        <li><?= icon('check', 'icon-sm') ?> AI-assisted academics</li>
      </ul>
    </div>

    <div class="lp-hero-visual lp-reveal" style="transition-delay:.12s">
      <div class="lp-float lp-float-a">
        <span class="lp-mini-ico"><?= icon('trend') ?></span>
        <strong>Readiness</strong>
        <small>Live analytics</small>
      </div>
      <div class="lp-float lp-float-b">
        <span class="lp-mini-ico"><?= icon('users') ?></span>
        <strong>Faculty</strong>
        <small>Aligned in one view</small>
      </div>
      <div class="lp-dash-preview" aria-hidden="true">
        <div class="lp-dash-bar">
          <span></span><span></span><span></span>
          <em>Admin Dashboard</em>
        </div>
        <div class="lp-dash-stats">
          <div><b>Users</b><strong>128</strong></div>
          <div><b>Plans</b><strong>46</strong></div>
          <div><b>Students</b><strong>980</strong></div>
          <div><b>Spend</b><strong>$12k</strong></div>
        </div>
        <div class="lp-dash-cards">
          <div><?= icon('users') ?> Users & roles</div>
          <div><?= icon('formula') ?> Marks formulas</div>
          <div><?= icon('file') ?> NAAC builder</div>
          <div><?= icon('card') ?> Subscription</div>
        </div>
      </div>
    </div>
  </section>

  <section class="lp-stats" id="trust">
    <div class="lp-stats-grid">
      <article class="lp-stat lp-reveal">
        <span class="lp-stat-n" data-count="4">0</span>
        <h3>Smarter Academic Management</h3>
        <p>One operating system for every academic role.</p>
      </article>
      <article class="lp-stat lp-reveal">
        <span class="lp-stat-n" data-count="20" data-suffix="+">0</span>
        <h3>Better Faculty Collaboration</h3>
        <p>Plans, reviews, and approvals in a shared workflow.</p>
      </article>
      <article class="lp-stat lp-reveal">
        <span class="lp-stat-n" data-count="1">0</span>
        <h3>Centralized Institution Management</h3>
        <p>College data stays isolated and under your control.</p>
      </article>
      <article class="lp-stat lp-reveal">
        <span class="lp-stat-n" data-count="360" data-suffix="°">0</span>
        <h3>Data-Driven Insights</h3>
        <p>Attendance, marks, AI usage, and accreditation snapshots.</p>
      </article>
    </div>
  </section>

  <section class="lp-section" id="features">
    <div class="lp-head lp-reveal">
      <p class="lp-kicker">Platform capabilities</p>
      <h2>Everything your institution needs to run academics</h2>
      <p>Modular features you can enable per college — without rewriting your processes.</p>
    </div>
    <div class="lp-feature-grid">
      <article class="lp-card lp-reveal"><?= icon('building') ?><h3>Institution Management</h3><p>College profile, affiliation, NAAC grade, academic year, and settings in one place.</p></article>
      <article class="lp-card lp-reveal"><?= icon('grid') ?><h3>Department Management</h3><p>Structure departments, programs, and classes the way your college already works.</p></article>
      <article class="lp-card lp-reveal"><?= icon('users') ?><h3>Faculty Management</h3><p>Onboard professors and HODs with roles, departments, and access that stay in bounds.</p></article>
      <article class="lp-card lp-reveal"><?= icon('spark') ?><h3>Course Planning</h3><p>AI-assisted course plans, Bloom mapping, versioning, and HOD review.</p></article>
      <article class="lp-card lp-reveal"><?= icon('book') ?><h3>Student Management</h3><p>Roster, portal access, assignments, attendance, and internal marks together.</p></article>
      <article class="lp-card lp-reveal"><?= icon('trend') ?><h3>Analytics</h3><p>Institution-wide visibility into plans, sessions, AI generations, and scores.</p></article>
      <article class="lp-card lp-reveal"><?= icon('file') ?><h3>Academic Reports</h3><p>NAAC-ready snapshots and compliance views without extra spreadsheets.</p></article>
      <article class="lp-card lp-reveal"><?= icon('bell') ?><h3>Notifications</h3><p>Approvals, AI completions, and system events delivered in-app.</p></article>
    </div>
  </section>

  <section class="lp-section lp-section-alt" id="how">
    <div class="lp-head lp-reveal">
      <p class="lp-kicker">How it works</p>
      <h2>Live in four clear steps</h2>
      <p>Go from empty college to operational academic workspace without changing your hierarchy.</p>
    </div>
    <ol class="lp-steps">
      <li class="lp-reveal">
        <span>1</span>
        <h3>Create Institution</h3>
        <p>Set college identity, affiliation, seats, and academic calendar.</p>
      </li>
      <li class="lp-reveal">
        <span>2</span>
        <h3>Configure Departments</h3>
        <p>Add departments and programs so every user lands in the right unit.</p>
      </li>
      <li class="lp-reveal">
        <span>3</span>
        <h3>Manage Faculty &amp; Students</h3>
        <p>Invite HODs, professors, and students with role-based access.</p>
      </li>
      <li class="lp-reveal">
        <span>4</span>
        <h3>Monitor Performance</h3>
        <p>Track plans, attendance, marks, finance, and accreditation readiness.</p>
      </li>
    </ol>
  </section>

  <section class="lp-section" id="roles">
    <div class="lp-head lp-reveal">
      <p class="lp-kicker">Built for the academic hierarchy</p>
      <h2>Every role sees only what it should</h2>
      <p>Strict institution isolation. One college never sees another college’s data.</p>
    </div>
    <div class="lp-roles">
      <article class="lp-role lp-reveal"><em>01</em><h3>Platform Admin</h3><p>System-level control for the product.</p></article>
      <article class="lp-role lp-reveal"><em>02</em><h3>Institution / College</h3><p>The tenant boundary for all academic data.</p></article>
      <article class="lp-role lp-reveal"><em>03</em><h3>College Admin</h3><p>Users, features, finance, formulas, and NAAC.</p></article>
      <article class="lp-role lp-reveal"><em>04</em><h3>HOD</h3><p>Approvals, faculty, analytics, and compliance.</p></article>
      <article class="lp-role lp-reveal"><em>05</em><h3>Professor</h3><p>Plans, lessons, questions, attendance, and marks.</p></article>
      <article class="lp-role lp-reveal"><em>06</em><h3>Student</h3><p>Courses, notes, assignments, and Ask AI.</p></article>
    </div>
  </section>

  <section class="lp-section lp-section-alt" id="preview">
    <div class="lp-preview-wrap">
      <div class="lp-head lp-preview-copy lp-reveal">
        <p class="lp-kicker">Product preview</p>
        <h2>A dashboard that feels like a modern SaaS — for academia</h2>
        <p>This is a visual preview of the existing ProProfessor workspace. Live dashboards stay behind login, with your institution’s real data.</p>
        <a class="btn btn-primary btn-shine" href="<?= e($login) ?>">Open the real workspace</a>
      </div>
      <div class="lp-preview-frame lp-reveal" aria-hidden="true">
        <div class="lp-preview-side">
          <strong>ProProfessor AI</strong>
          <small>Admin View</small>
          <i>Dashboard</i>
          <i>Institution</i>
          <i>Users &amp; Roles</i>
          <i>Analytics</i>
        </div>
        <div class="lp-preview-main">
          <header><b>Admin Dashboard</b><span>Institution control center</span></header>
          <div class="lp-preview-metrics">
            <div>Users <b>128</b></div>
            <div>Course plans <b>46</b></div>
            <div>Students <b>980</b></div>
            <div>Expenses <b>$12k</b></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="lp-section" id="benefits">
    <div class="lp-head lp-reveal">
      <p class="lp-kicker">Why institutions choose it</p>
      <h2>Benefits that show up in daily academic work</h2>
    </div>
    <div class="lp-benefit-grid">
      <article class="lp-benefit lp-reveal"><?= icon('building') ?><h3>Centralized operations</h3><p>Replace scattered sheets and chats with one academic OS.</p></article>
      <article class="lp-benefit lp-reveal"><?= icon('monitor') ?><h3>Better visibility</h3><p>See plans, faculty load, and student activity without chasing files.</p></article>
      <article class="lp-benefit lp-reveal"><?= icon('check') ?><h3>Faster approvals</h3><p>HOD review queues keep course plans moving from draft to approved.</p></article>
      <article class="lp-benefit lp-reveal"><?= icon('users') ?><h3>Improved collaboration</h3><p>Professors, HODs, and admins work from the same source of truth.</p></article>
      <article class="lp-benefit lp-reveal"><?= icon('chart') ?><h3>Academic analytics</h3><p>Readiness, attendance sessions, AI usage, and scores at a glance.</p></article>
      <article class="lp-benefit lp-reveal"><?= icon('file') ?><h3>Reporting &amp; compliance</h3><p>NAAC snapshots and audit-friendly documentation, ready when you are.</p></article>
    </div>
  </section>

  <section class="lp-cta-band lp-reveal">
    <h2>Ready to simplify academic management?</h2>
    <p>Start with your college workspace. Existing accounts sign in with the same secure login.</p>
    <div class="lp-hero-actions">
      <a class="btn btn-primary btn-shine" href="<?= e($login) ?>"><?= icon('spark') ?> Get Started</a>
      <a class="btn btn-ghost" href="<?= e($login) ?>">Login</a>
    </div>
  </section>

  <section class="lp-section" id="contact">
    <div class="lp-contact lp-reveal">
      <div>
        <p class="lp-kicker">Contact</p>
        <h2>Talk to us about your institution</h2>
        <p>Whether you are a college admin or evaluating ProProfessor AI for your campus, start from the secure login — or reach out.</p>
      </div>
      <div class="lp-contact-card">
        <p><strong>Email</strong><a href="mailto:hello@proprofessor.ai">hello@proprofessor.ai</a></p>
        <p><strong>Access</strong><a href="<?= e($login) ?>">Sign in to your workspace</a></p>
        <a class="btn btn-primary btn-block" href="<?= e($login) ?>">Get Started</a>
      </div>
    </div>
  </section>
</main>

<footer class="lp-footer">
  <div class="lp-footer-inner">
    <div class="lp-footer-grid">
      <div class="lp-footer-brand">
        <a class="lp-brand" href="#home">
          <img src="<?= e(asset('img/logo.svg')) ?>" width="42" height="42" alt="ProProfessor AI">
          <span>ProProfessor AI</span>
        </a>
        <p>AI-native academic operating system for colleges — institution management, faculty workflows, student learning, analytics, and compliance in one secure workspace.</p>
        <div class="lp-footer-social" aria-label="Contact channels">
          <a href="mailto:hello@proprofessor.ai" aria-label="Email"><?= icon('mail') ?></a>
          <a href="<?= e($login) ?>" aria-label="Login"><?= icon('lock') ?></a>
          <a href="#contact" aria-label="Contact"><?= icon('bell') ?></a>
        </div>
        <a class="btn btn-primary btn-sm btn-shine" href="<?= e($login) ?>"><?= icon('spark') ?> Get Started</a>
      </div>

      <nav class="lp-footer-col" aria-label="Product">
        <h3>Product</h3>
        <a href="#features">Features</a>
        <a href="#how">How it works</a>
        <a href="#preview">Dashboard preview</a>
        <a href="#benefits">Benefits</a>
        <a href="<?= e($login) ?>">Get started</a>
      </nav>

      <nav class="lp-footer-col" aria-label="Platform">
        <h3>Platform</h3>
        <a href="#roles">College Admin</a>
        <a href="#roles">HOD workspace</a>
        <a href="#roles">Professor tools</a>
        <a href="#roles">Student portal</a>
        <a href="#features">Analytics &amp; reports</a>
      </nav>

      <nav class="lp-footer-col" aria-label="Company">
        <h3>Company</h3>
        <a href="#home">Home</a>
        <a href="#trust">Why ProProfessor</a>
        <a href="#contact">Contact</a>
        <a href="<?= e($login) ?>">Login</a>
      </nav>

      <div class="lp-footer-col lp-footer-contact">
        <h3>Contact</h3>
        <p><span><?= icon('mail', 'icon-sm') ?></span><a href="mailto:hello@proprofessor.ai">hello@proprofessor.ai</a></p>
        <p><span><?= icon('building', 'icon-sm') ?></span>For colleges &amp; universities</p>
        <p><span><?= icon('lock', 'icon-sm') ?></span><a href="<?= e($login) ?>">Secure workspace login</a></p>
      </div>
    </div>

    <div class="lp-footer-bottom">
      <small>© <?= date('Y') ?> ProProfessor AI. All rights reserved.</small>
      <small>AI-Powered Academic Operating System · Institution data isolation</small>
    </div>
  </div>
</footer>
