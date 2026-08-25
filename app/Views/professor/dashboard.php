<?php
/** @var array $byStatus */
/** @var int $assignments */
/** @var int $unread */
/** @var array $recent */
$user = \Auth::user();
$firstName = explode(' ', (string)($user['full_name'] ?? 'Professor'))[0];
$active = (int)array_sum($byStatus);
$drafts = (int)($byStatus['draft'] ?? 0);
$submitted = (int)(($byStatus['submitted'] ?? 0) + ($byStatus['under_review'] ?? 0));
?>
<section class="welcome-banner reveal">
  <div>
    <h2>Welcome back, <?= e($firstName) ?></h2>
    <p><?= e(ucfirst((string)($user['role'] ?? 'professor'))) ?> workspace · <?= $submitted ?> plan(s) pending review</p>
  </div>
  <a class="btn btn-primary btn-shine" href="<?= e(url('/professor/generate-plan')) ?>"><?= icon('spark') ?> Generate Course Plan</a>
</section>

<div class="grid grid-3 stagger" style="margin-bottom:1rem">
  <div class="stat">
    <div class="label">Active Plans</div>
    <div class="value"><?= $active ?></div>
    <div class="hint">Across all subjects</div>
  </div>
  <div class="stat">
    <div class="label">Drafts</div>
    <div class="value"><?= $drafts ?></div>
    <div class="hint">Awaiting submission</div>
  </div>
  <div class="stat">
    <div class="label">Submitted</div>
    <div class="value"><?= $submitted ?></div>
    <div class="hint">Under HOD review</div>
  </div>
</div>

<div class="panel reveal" style="margin-bottom:1rem">
  <div class="panel-h">
    <h2><?= icon('clock', 'icon-inline') ?> Recent Course Plans</h2>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/professor/plans')) ?>">View all</a>
  </div>
  <?php if (!$recent): ?>
    <div class="empty">No plans yet. <a href="<?= e(url('/professor/generate-plan')) ?>">Generate your first course plan</a>.</div>
  <?php else: ?>
  <div class="plan-list">
    <?php foreach ($recent as $r): ?>
      <a class="plan-row" href="<?= e(url('/professor/plan-view?id=' . $r['id'])) ?>">
        <div class="plan-ico"><?= icon('file') ?></div>
        <div class="meta">
          <strong><?= e($r['title']) ?></strong>
          <span><?= e($r['subject_name']) ?> · Updated <?= e($r['updated_at']) ?></span>
        </div>
        <?= status_badge($r['status']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="panel reveal">
  <div class="panel-h"><h2><?= icon('spark', 'icon-inline') ?> Quick Actions</h2></div>
  <div class="module-cards stagger">
    <a class="qa-card" href="<?= e(url('/professor/generate-plan')) ?>"><div class="ico"><?= icon('spark') ?></div><h3>New Course Plan</h3><p>Syllabus → OBE plan</p></a>
    <a class="qa-card" href="<?= e(url('/professor/lessons')) ?>"><div class="ico"><?= icon('book') ?></div><h3>Lesson Plan</h3><p>Session-wise design</p></a>
    <a class="qa-card" href="<?= e(url('/professor/questions')) ?>"><div class="ico"><?= icon('help') ?></div><h3>Question Bank</h3><p>MCQ / short / long</p></a>
    <a class="qa-card" href="<?= e(url('/hod/dashboard')) ?>"><div class="ico"><?= icon('building') ?></div><h3>HOD View</h3><p>Approvals & analytics</p></a>
  </div>
</div>

<?php
/** @var array $insights */
$insights = $insights ?? [];
$today = $insights['today'] ?? ['classes' => [], 'pending_grading' => 0, 'low_attendance' => 0];
$digest = $insights['digest'] ?? ['lines' => [], 'source' => 'stats'];
$obe = $insights['obe'] ?? [];
$atRisk = $insights['at_risk'] ?? [];
$bench = $insights['benchmark'] ?? [];
$order = $insights['widget_order'] ?? ['today_glance', 'weekly_digest', 'obe_compliance', 'at_risk', 'dept_benchmark'];
$minAtt = (float)($insights['attendance_min'] ?? 75);

$fmtPct = static function (?float $v): string {
    return $v === null ? '—' : rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.') . '%';
};

ob_start();
?>
<div class="panel dash-widget" data-widget="today_glance" draggable="true">
  <div class="panel-h">
    <h2><span class="dash-drag" title="Drag to reorder" aria-hidden="true">⋮⋮</span> Today at a Glance</h2>
  </div>
  <div class="grid grid-3" style="margin-bottom:.8rem">
    <div class="stat">
      <div class="label">Pending Grading</div>
      <div class="value"><?= (int)($today['pending_grading'] ?? 0) ?></div>
      <div class="hint">Submissions awaiting grade</div>
    </div>
    <div class="stat">
      <div class="label">Low Attendance</div>
      <div class="value"><?= (int)($today['low_attendance'] ?? 0) ?></div>
      <div class="hint">Below <?= e((string)$minAtt) ?>% threshold</div>
    </div>
    <div class="stat">
      <div class="label">Today's Classes</div>
      <div class="value"><?= count($today['classes'] ?? []) ?></div>
      <div class="hint">Sessions / lessons today</div>
    </div>
  </div>
  <?php if (empty($today['classes'])): ?>
    <div class="empty" style="padding:.6rem 0">No attendance sessions or lesson plans scheduled for today.</div>
  <?php else: ?>
    <ul style="margin:0;padding-left:1.1rem;line-height:1.55">
      <?php foreach ($today['classes'] as $c): ?>
        <li>
          <strong><?= e((string)$c['title']) ?></strong>
          — <?= e((string)$c['when']) ?>
          <?php if (!empty($c['meta'])): ?><span style="color:var(--muted);font-size:.85rem"> · <?= e((string)$c['meta']) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php
$widgetHtml = ['today_glance' => ob_get_clean()];

ob_start();
?>
<div class="panel dash-widget" data-widget="weekly_digest" draggable="true">
  <div class="panel-h">
    <h2><span class="dash-drag" title="Drag to reorder" aria-hidden="true">⋮⋮</span> Weekly AI Digest</h2>
    <span class="chip"><?= e(($digest['source'] ?? 'stats') === 'gemini' ? 'AI summary' : 'From your data') ?></span>
  </div>
  <?php if (empty($digest['lines'])): ?>
    <div class="empty">No activity recorded this week yet.</div>
  <?php else: ?>
    <ul style="margin:0;padding-left:1.1rem;line-height:1.6">
      <?php foreach ($digest['lines'] as $line): ?>
        <li><?= e((string)$line) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php
$widgetHtml['weekly_digest'] = ob_get_clean();

ob_start();
?>
<div class="panel dash-widget" data-widget="obe_compliance" draggable="true">
  <div class="panel-h">
    <h2><span class="dash-drag" title="Drag to reorder" aria-hidden="true">⋮⋮</span> OBE Compliance</h2>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/professor/plans')) ?>">Plans</a>
  </div>
  <?php if (empty($obe['available'])): ?>
    <div class="empty">No course plans yet — CLO/PLO metrics appear after you create plans.</div>
  <?php else: ?>
    <div class="grid grid-3">
      <div class="stat">
        <div class="label">CLO Mapping</div>
        <div class="value"><?= e($fmtPct(isset($obe['clo_mapping_pct']) ? (float)$obe['clo_mapping_pct'] : null)) ?></div>
        <div class="hint"><?= (int)($obe['units_with_clo'] ?? 0) ?>/<?= (int)($obe['units_total'] ?? 0) ?> units with outcomes</div>
      </div>
      <div class="stat">
        <div class="label">PLO / LO Mapping</div>
        <div class="value"><?= e($fmtPct(isset($obe['plo_mapping_pct']) ? (float)$obe['plo_mapping_pct'] : null)) ?></div>
        <div class="hint">From plan learning outcomes</div>
      </div>
      <div class="stat">
        <div class="label">Higher-order Bloom</div>
        <div class="value"><?= e($fmtPct(isset($obe['bloom_higher_pct']) ? (float)$obe['bloom_higher_pct'] : null)) ?></div>
        <div class="hint">K4–K6 share (OBE signal)</div>
      </div>
    </div>
    <p style="margin:.75rem 0 0;font-size:.82rem;color:var(--muted)">Based on your course-plan units &amp; Bloom data — not invented attainment scores.</p>
  <?php endif; ?>
</div>
<?php
$widgetHtml['obe_compliance'] = ob_get_clean();

ob_start();
?>
<div class="panel dash-widget" data-widget="at_risk" draggable="true">
  <div class="panel-h">
    <h2><span class="dash-drag" title="Drag to reorder" aria-hidden="true">⋮⋮</span> At-Risk Students</h2>
    <span class="chip"><?= count($atRisk) ?> flagged</span>
  </div>
  <?php if (!$atRisk): ?>
    <div class="empty">No cross-module risk signals for your classes right now.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Signals</th>
          <th>Risk</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($atRisk as $r):
        $flags = $r['flags'] ?? [];
        $details = $r['details'] ?? [];
        $bits = [];
        foreach ($flags as $f) {
          $label = match ($f) {
            'attendance' => 'Attendance',
            'marks' => 'Internal Marks',
            'assignments' => 'Assignments',
            default => (string)$f,
          };
          $bits[] = $label . (!empty($details[$f]) ? ' (' . $details[$f] . ')' : '');
        }
        $level = (string)($r['level'] ?? 'Medium');
      ?>
        <tr>
          <td>
            <strong><?= e((string)($r['name'] ?: $r['register_no'])) ?></strong>
            <div style="font-size:.82rem;color:var(--muted)"><?= e((string)$r['register_no']) ?></div>
          </td>
          <td style="font-size:.9rem"><?= e(implode(' · ', $bits)) ?></td>
          <td>
            <?php if ($level === 'High'): ?>
              <span class="badge badge-danger">High</span>
            <?php else: ?>
              <span class="badge badge-warn">Medium</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php
$widgetHtml['at_risk'] = ob_get_clean();

ob_start();
?>
<div class="panel dash-widget" data-widget="dept_benchmark" draggable="true">
  <div class="panel-h">
    <h2><span class="dash-drag" title="Drag to reorder" aria-hidden="true">⋮⋮</span> Department Benchmark</h2>
  </div>
  <p style="margin:0 0 .8rem;font-size:.9rem;color:var(--muted)">Your classes vs department average (aggregates only — no other professors' student lists).</p>
  <div class="grid grid-2">
    <div class="stat">
      <div class="label">Attendance</div>
      <div class="value" style="font-size:1.35rem">You <?= e($fmtPct(isset($bench['you_attendance']) ? (float)$bench['you_attendance'] : null)) ?></div>
      <div class="hint">Dept avg <?= e($fmtPct(isset($bench['dept_attendance']) ? (float)$bench['dept_attendance'] : null)) ?></div>
    </div>
    <div class="stat">
      <div class="label">Internal Marks</div>
      <div class="value" style="font-size:1.35rem">You <?= e($fmtPct(isset($bench['you_marks']) ? (float)$bench['you_marks'] : null)) ?></div>
      <div class="hint">Dept avg <?= e($fmtPct(isset($bench['dept_marks']) ? (float)$bench['dept_marks'] : null)) ?></div>
    </div>
  </div>
</div>
<?php
$widgetHtml['dept_benchmark'] = ob_get_clean();
?>

<section class="dash-insights reveal" style="margin-top:1.25rem">
  <div class="panel-h" style="margin-bottom:.75rem">
    <h2 style="margin:0;font-size:1.05rem"><?= icon('grid', 'icon-inline') ?> Insights &amp; widgets</h2>
    <div class="filters" style="gap:.5rem;display:flex;flex-wrap:wrap;align-items:center">
      <span style="font-size:.82rem;color:var(--muted)">Drag widgets to reorder</span>
      <form method="post" action="<?= e(url('/professor/dashboard/layout')) ?>" id="dash-layout-form" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="widget_order" id="dash-widget-order" value="<?= e(json_encode(array_values($order))) ?>">
        <button class="btn btn-sm btn-primary" type="submit">Save layout</button>
      </form>
    </div>
  </div>
  <div id="dash-widget-board" class="dash-widget-board" style="display:flex;flex-direction:column;gap:1rem">
    <?php foreach ($order as $key): ?>
      <?= $widgetHtml[$key] ?? '' ?>
    <?php endforeach; ?>
  </div>
</section>

<style>
.dash-widget { cursor: grab; }
.dash-widget.dragging { opacity: .55; cursor: grabbing; }
.dash-drag { display:inline-block; margin-right:.35rem; opacity:.45; letter-spacing:-2px; font-size:.9rem; }
.dash-widget-board .dash-widget-placeholder {
  border: 2px dashed color-mix(in srgb, var(--accent, #7c5cff) 55%, transparent);
  border-radius: 12px;
  min-height: 72px;
  background: color-mix(in srgb, var(--accent, #7c5cff) 8%, transparent);
}
</style>
<script>
(function () {
  var board = document.getElementById('dash-widget-board');
  var orderInput = document.getElementById('dash-widget-order');
  if (!board || !orderInput) return;
  var dragEl = null;

  function syncOrder() {
    var keys = [...board.querySelectorAll('.dash-widget')].map(function (el) {
      return el.getAttribute('data-widget');
    }).filter(Boolean);
    orderInput.value = JSON.stringify(keys);
  }

  board.querySelectorAll('.dash-widget').forEach(function (el) {
    el.addEventListener('dragstart', function (e) {
      dragEl = el;
      el.classList.add('dragging');
      try { e.dataTransfer.setData('text/plain', el.getAttribute('data-widget') || ''); } catch (err) {}
      e.dataTransfer.effectAllowed = 'move';
    });
    el.addEventListener('dragend', function () {
      el.classList.remove('dragging');
      dragEl = null;
      board.querySelectorAll('.dash-widget-placeholder').forEach(function (p) { p.remove(); });
      syncOrder();
    });
  });

  board.addEventListener('dragover', function (e) {
    e.preventDefault();
    if (!dragEl) return;
    var after = null;
    var cards = [...board.querySelectorAll('.dash-widget:not(.dragging)')];
    for (var i = 0; i < cards.length; i++) {
      var box = cards[i].getBoundingClientRect();
      if (e.clientY < box.top + box.height / 2) {
        after = cards[i];
        break;
      }
    }
    if (after) board.insertBefore(dragEl, after);
    else board.appendChild(dragEl);
  });

  board.addEventListener('drop', function (e) {
    e.preventDefault();
    syncOrder();
  });

  syncOrder();
})();
</script>
