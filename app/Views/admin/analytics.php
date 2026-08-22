<?php /** @var array $metrics */ ?>
<div class="grid grid-4">
  <div class="stat"><div class="label">Attendance sessions</div><div class="value"><?= (int)$metrics['attendance_sessions'] ?></div></div>
  <div class="stat"><div class="label">Assignments</div><div class="value"><?= (int)$metrics['assignments'] ?></div></div>
  <div class="stat"><div class="label">AI generations</div><div class="value"><?= (int)$metrics['ai_calls'] ?></div></div>
  <div class="stat"><div class="label">Avg plan score</div><div class="value"><?= $metrics['avg_score'] !== null ? round((float)$metrics['avg_score'], 1) : '—' ?></div></div>
</div>
<div class="panel" style="margin-top:1rem">
  <h2>Readiness index</h2>
  <p>Institution-wide academic documentation readiness for accreditation audits.</p>
</div>
