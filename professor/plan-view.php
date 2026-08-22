<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin', 'hod');
$user = Auth::user();
$id = (int)get('id');
$plan = Database::fetch('SELECT * FROM course_plans WHERE id = ?', [$id]);
if (!$plan) { flash('error','Plan not found'); redirect('/professor/plans.php'); }
if ($user['role'] === 'professor' && (int)$plan['professor_id'] !== (int)$user['id']) {
    flash('error','Access denied'); redirect('/professor/plans.php');
}
if ($user['role'] === 'hod' && (int)$plan['department_id'] !== (int)$user['department_id']) {
    flash('error','Access denied'); redirect('/hod/approvals.php');
}
if (in_array($user['role'], ['admin', 'superadmin', 'hod', 'professor'], true)
    && (int)$plan['institution_id'] !== (int)$user['institution_id']) {
    flash('error','Access denied');
    redirect(dashboard_path_for_role((string)$user['role']));
}
$units = Database::fetchAll('SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number', [$id]);
$versions = Database::fetchAll('SELECT id, version, change_note, created_at FROM course_plan_versions WHERE plan_id = ? ORDER BY version DESC', [$id]);
$bloom = json_decode($plan['bloom_data'] ?: '{}', true) ?: [];
$resources = json_decode($plan['resources'] ?: '[]', true) ?: [];
$weekly = json_decode($plan['weekly_plan'] ?: '[]', true) ?: [];
$advice = json_decode($plan['expert_advice'] ?: '[]', true) ?: [];
$review = json_decode($plan['ai_review'] ?: 'null', true);
$data = json_decode($plan['plan_data'] ?: '{}', true) ?: [];
$fb = HodFeedback::parse($plan['hod_comments'] ?? null);
$fbCounts = HodFeedback::counts($fb);

render_header($plan['title'], 'plans', ['subtitle' => $plan['subject_name'] . ' · ' . status_badge($plan['status'])]);
?>
<?php if ($fb['overall'] !== '' || $fb['points']): ?>
  <div class="alert alert-warn hod-fb-summary">
    <strong>HOD feedback</strong>
    <?php if ($fbCounts['must_fix'] || $fbCounts['suggest']): ?>
      <span class="chip"><?= (int)$fbCounts['must_fix'] ?> must fix</span>
      <span class="chip"><?= (int)$fbCounts['suggest'] ?> suggestion<?= $fbCounts['suggest'] === 1 ? '' : 's' ?></span>
    <?php endif; ?>
    <?php if ($fb['overall'] !== ''): ?><p><?= e($fb['overall']) ?></p><?php endif; ?>
  </div>
<?php endif; ?>
<div class="grid grid-3" style="margin-bottom:1rem">
  <div class="stat"><div class="label">AI score</div><div class="value"><?= e((string)($plan['ai_score'] ?? '-')) ?></div></div>
  <div class="stat"><div class="label">Version</div><div class="value">v<?= (int)$plan['version'] ?></div></div>
  <div class="stat"><div class="label">Credits</div><div class="value"><?= e((string)$plan['credits']) ?></div></div>
</div>

<div data-tabs>
  <div class="tabs">
    <button type="button" class="tab active" data-tab="units">Unit-wise plan</button>
    <button type="button" class="tab" data-tab="bloom">Bloom's taxonomy</button>
    <button type="button" class="tab" data-tab="weekly">Weekly plan</button>
    <button type="button" class="tab" data-tab="resources">Resources</button>
    <button type="button" class="tab" data-tab="advice">Expert advice</button>
    <button type="button" class="tab" data-tab="ai">AI tools</button>
    <button type="button" class="tab" data-tab="versions">Versions</button>
  </div>

  <div data-pane="units" class="panel">
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>Title</th><th>Hours</th><th>Bloom</th><th>Topics</th><th>Outcomes</th></tr></thead>
      <tbody>
      <?php foreach ($units as $u): ?>
        <tr>
          <td><?= (int)$u['unit_number'] ?></td>
          <td><?= e($u['title']) ?></td>
          <td><?= e((string)$u['hours']) ?></td>
          <td><span class="badge badge-info"><?= e((string)$u['bloom_k_level']) ?></span></td>
          <td><?php $t=json_decode($u['topics']?:'[]',true); echo e(is_array($t)?implode(', ',$t):''); ?></td>
          <td><?php $o=json_decode($u['outcomes']?:'[]',true); echo e(is_array($o)?implode('; ',$o):''); ?></td>
        </tr>
        <?php $unitFb = HodFeedback::renderInline(HodFeedback::point($fb, 'unit:' . (int)$u['unit_number'])); ?>
        <?php if ($unitFb !== ''): ?>
          <tr class="inline-fb-row"><td colspan="6"><?= $unitFb ?></td></tr>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$units && !empty($data['units'])): foreach ($data['units'] as $u): ?>
        <tr>
          <td><?= (int)($u['unit_number']??0) ?></td>
          <td><?= e((string)($u['title']??'')) ?></td>
          <td><?= e((string)($u['hours']??'')) ?></td>
          <td><?= e((string)($u['bloom_k_level']??'')) ?></td>
          <td><?= e(is_array($u['topics']??null)?implode(', ',$u['topics']):'') ?></td>
          <td><?= e(is_array($u['outcomes']??null)?implode('; ',$u['outcomes']):'') ?></td>
        </tr>
        <?php $unitFb = HodFeedback::renderInline(HodFeedback::point($fb, 'unit:' . (int)($u['unit_number'] ?? 0))); ?>
        <?php if ($unitFb !== ''): ?>
          <tr class="inline-fb-row"><td colspan="6"><?= $unitFb ?></td></tr>
        <?php endif; ?>
      <?php endforeach; endif; ?>
      </tbody>
    </table>    </div>
    <?= HodFeedback::renderInline(HodFeedback::point($fb, 'outcomes')) ?>
    <?= HodFeedback::renderInline(HodFeedback::point($fb, 'overview')) ?>
  </div>

  <div data-pane="bloom" class="panel" hidden>
    <div class="grid grid-2">
      <div><canvas id="bloomChart" height="220"></canvas></div>
      <div class="bloom-bars">
        <?php foreach ($bloom as $k=>$v): ?>
          <div class="bloom-row"><strong><?= e((string)$k) ?></strong><div class="bar"><span style="width:<?= (float)$v ?>%"></span></div><span><?= e((string)$v) ?>%</span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?= HodFeedback::renderInline(HodFeedback::point($fb, 'bloom')) ?>
  </div>

  <div data-pane="weekly" class="panel" hidden>
    <div class="table-wrap"><table>
      <thead><tr><th>Week</th><th>Focus</th></tr></thead>
      <tbody>
      <?php foreach ($weekly as $w): ?>
        <tr><td><?= e((string)($w['week'] ?? '')) ?></td><td><?= e((string)($w['focus'] ?? json_encode($w))) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?= HodFeedback::renderInline(HodFeedback::point($fb, 'weekly')) ?>
  </div>

  <div data-pane="resources" class="panel" hidden>
    <ul><?php foreach ($resources as $r): ?><li><?= e(is_string($r)?$r:json_encode($r)) ?></li><?php endforeach; ?></ul>
    <?= HodFeedback::renderInline(HodFeedback::point($fb, 'resources')) ?>
  </div>

  <div data-pane="advice" class="panel" hidden>
    <ul><?php foreach ($advice as $a): ?><li><?= e(is_string($a)?$a:json_encode($a)) ?></li><?php endforeach; ?></ul>
    <?= HodFeedback::renderInline(HodFeedback::point($fb, 'advice')) ?>
    <?php if ($review): ?>
      <h3 style="margin-top:1rem">AI Review</h3>
      <pre style="white-space:pre-wrap;background:#f6faf8;padding:1rem;border-radius:12px"><?= e(json_encode($review, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
    <?php endif; ?>
  </div>

  <div data-pane="ai" class="panel" hidden>
    <div class="grid grid-2">
      <form method="post" action="<?= e(base_url('/api/ai?module=review')) ?>" data-ai-form="#toolOut" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="module" value="review"><input type="hidden" name="plan_id" value="<?= $id ?>">
        <button class="btn btn-primary" type="submit">Run AI Review</button>
      </form>
      <form method="post" action="<?= e(base_url('/api/ai?module=bloom')) ?>" data-ai-form="#toolOut" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="module" value="bloom"><input type="hidden" name="plan_id" value="<?= $id ?>">
        <button class="btn btn-ghost" type="submit">Refresh Bloom map</button>
      </form>
    </div>
    <form method="post" action="<?= e(base_url('/api/ai?module=improve')) ?>" data-ai-form="#toolOut" class="form-grid" style="margin-top:1rem">
      <?= csrf_field() ?>
      <input type="hidden" name="module" value="improve">
      <input type="hidden" name="plan_id" value="<?= $id ?>">
      <label>Improve with AI · plain English instruction</label>
      <textarea name="instruction" placeholder='e.g. "Add more K4 outcomes in Unit 3"'></textarea>
      <button class="btn btn-accent" type="submit">Improve plan</button>
    </form>
    <div class="chip-row" style="margin-top:1rem">
      <a class="chip" href="<?= e(base_url('/professor/lessons.php?plan_id='.$id)) ?>">Lesson planner</a>
      <a class="chip" href="<?= e(base_url('/professor/questions.php?plan_id='.$id)) ?>">Question bank</a>
      <a class="chip" href="<?= e(base_url('/professor/ppt.php?plan_id='.$id)) ?>">PPT generator</a>
    </div>
    <div id="toolOut" style="margin-top:1rem"></div>
  </div>

  <div data-pane="versions" class="panel" hidden>
    <div class="table-wrap"><table>
      <thead><tr><th>Version</th><th>Note</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($versions as $v): ?>
        <tr><td>v<?= (int)$v['version'] ?></td><td><?= e($v['change_note'] ?? '') ?></td><td><?= e($v['created_at']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>PPAI.renderBloomChart('bloomChart', <?= json_encode($bloom) ?>));
</script>
<?php render_footer(); ?>
