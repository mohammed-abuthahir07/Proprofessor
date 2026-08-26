<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin', 'hod');
$user = Auth::user();
$id = (int)get('id');
$plan = Database::fetch('SELECT * FROM course_plans WHERE id = ?', [$id]);
if (!$plan) { flash('error','Plan not found'); redirect('/professor/plans.php'); }
if (!CoursePlanTools::canViewPlan($user, $plan)) {
    flash('error','Access denied');
    redirect(dashboard_path_for_role((string)$user['role']));
}
// Soft-fill real syllabus topics when stored topics are placeholders (no status/version change).
$plan = CoursePlanTools::syncPlanTopicsFromSyllabus($plan);
$isOwner = (int)$plan['professor_id'] === (int)$user['id']
    || in_array((string)$user['role'], ['admin', 'superadmin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');
    if ($action === 'add_comment') {
        if (!CoursePlanTools::canCommentOnPlan($user, $plan)) {
            flash('error', 'You are not allowed to comment on this plan.');
            redirect('/professor/plan-view.php?id=' . $id);
        }
        $body = trim((string)post('comment'));
        if ($body === '') {
            flash('error', 'Comment cannot be empty.');
            redirect('/professor/plan-view.php?id=' . $id . '&tab=comments');
        }
        if (strlen($body) > 4000) {
            flash('error', 'Comment is too long.');
            redirect('/professor/plan-view.php?id=' . $id . '&tab=comments');
        }
        Database::insert('plan_reviews', [
            'plan_id' => $id,
            'reviewer_id' => (int)$user['id'],
            'action' => 'comment',
            'comments' => $body,
            'checklist' => json_encode([
                'plan_version' => (int)$plan['version'],
                'kind' => 'co_faculty',
            ], JSON_UNESCAPED_UNICODE),
        ]);
        if (!$isOwner && (int)$plan['professor_id'] > 0) {
            notify_user(
                (int)$plan['professor_id'],
                'plan_comment',
                'New course plan comment',
                $user['full_name'] . ' commented on ' . $plan['title'],
                '/professor/plan-view.php?id=' . $id . '&tab=comments',
                [
                    'priority' => 'medium',
                    'category' => 'course_plans',
                    'action' => ['type' => 'VIEW_PLAN', 'record_id' => $id],
                ]
            );
        }
        flash('success', 'Comment added.');
        redirect('/professor/plan-view.php?id=' . $id . '&tab=comments');
    }
}

$units = Database::fetchAll('SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number', [$id]);
$versions = Database::fetchAll(
    'SELECT id, version, change_note, created_at, snapshot FROM course_plan_versions WHERE plan_id = ? ORDER BY version DESC',
    [$id]
);
$bloom = json_decode($plan['bloom_data'] ?: '{}', true) ?: [];
$resources = json_decode($plan['resources'] ?: '[]', true) ?: [];
$weekly = json_decode($plan['weekly_plan'] ?: '[]', true) ?: [];
$advice = json_decode($plan['expert_advice'] ?: '[]', true) ?: [];
$review = json_decode($plan['ai_review'] ?: 'null', true);
$data = json_decode($plan['plan_data'] ?: '{}', true) ?: [];
$meta = json_decode($plan['meta'] ?: '{}', true) ?: [];
$template = CoursePlanTools::normalizeTemplate($meta['accreditation_template'] ?? ($data['accreditation_template'] ?? 'standard'));
$balance = CoursePlanTools::bloomBalance($plan, $units);
$fb = HodFeedback::parse($plan['hod_comments'] ?? null);
$fbCounts = HodFeedback::counts($fb);

$comments = Database::fetchAll(
    'SELECT r.*, u.full_name AS author_name, u.role AS author_role
     FROM plan_reviews r
     JOIN users u ON u.id = r.reviewer_id
     WHERE r.plan_id = ? AND r.action = "comment"
       AND u.institution_id = ?
     ORDER BY r.created_at DESC',
    [$id, (int)$plan['institution_id']]
);

$compareA = (int)get('v1');
$compareB = (int)get('v2');
$diff = null;
if ($compareA > 0 && $compareB > 0 && $compareA !== $compareB) {
    $va = null;
    $vb = null;
    foreach ($versions as $v) {
        if ((int)$v['version'] === $compareA) {
            $va = json_decode((string)$v['snapshot'], true) ?: [];
        }
        if ((int)$v['version'] === $compareB) {
            $vb = json_decode((string)$v['snapshot'], true) ?: [];
        }
    }
    if ($va && $vb) {
        $diff = CoursePlanTools::diffSnapshots($va, $vb);
    }
}

$tab = (string)(get('tab') ?: 'units');
$allowedTabs = ['units', 'bloom', 'weekly', 'resources', 'advice', 'ai', 'versions', 'comments'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'units';
}
$canComment = CoursePlanTools::canCommentOnPlan($user, $plan);
$canExport = in_array((string)$plan['status'], ['approved'], true) || in_array((string)$user['role'], ['admin', 'superadmin', 'hod'], true);

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
  <div class="stat"><div class="label">Template</div><div class="value" style="font-size:1.2rem"><?= e(CoursePlanTools::templateLabel($template)) ?></div></div>
</div>

<div class="filters" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
  <?php if ($isOwner && in_array((string)$plan['status'], ['draft', 'returned'], true)): ?>
    <a class="btn btn-sm btn-accent" href="<?= e(base_url('/professor/generate-plan.php?plan_id=' . $id)) ?>">Regenerate (new version)</a>
  <?php endif; ?>
  <?php if ($canExport): ?>
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/professor/plan-export.php?id=' . $id . '&format=naac')) ?>" target="_blank">Export NAAC</a>
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/professor/plan-export.php?id=' . $id . '&format=nba')) ?>" target="_blank">Export NBA</a>
  <?php else: ?>
    <span style="font-size:.85rem;color:var(--muted)">Accreditation export available after HOD approval.</span>
  <?php endif; ?>
  <span class="chip">Status: <?= e((string)$plan['status']) ?></span>
</div>

<div data-tabs>
  <div class="tabs">
    <button type="button" class="tab <?= $tab === 'units' ? 'active' : '' ?>" data-tab="units">Unit-wise plan</button>
    <button type="button" class="tab <?= $tab === 'bloom' ? 'active' : '' ?>" data-tab="bloom">Bloom's taxonomy</button>
    <button type="button" class="tab <?= $tab === 'weekly' ? 'active' : '' ?>" data-tab="weekly">Weekly plan</button>
    <button type="button" class="tab <?= $tab === 'resources' ? 'active' : '' ?>" data-tab="resources">Resources</button>
    <button type="button" class="tab <?= $tab === 'advice' ? 'active' : '' ?>" data-tab="advice">Expert advice</button>
    <button type="button" class="tab <?= $tab === 'ai' ? 'active' : '' ?>" data-tab="ai">AI tools</button>
    <button type="button" class="tab <?= $tab === 'versions' ? 'active' : '' ?>" data-tab="versions">Versions</button>
    <button type="button" class="tab <?= $tab === 'comments' ? 'active' : '' ?>" data-tab="comments">Review comments</button>
  </div>

  <div data-pane="units" class="panel" <?= $tab === 'units' ? '' : 'hidden' ?>>
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

  <div data-pane="bloom" class="panel" <?= $tab === 'bloom' ? '' : 'hidden' ?>>
    <div class="grid grid-2">
      <div><canvas id="bloomChart" height="220"></canvas></div>
      <div class="bloom-bars">
        <?php foreach ($balance['distribution'] as $k=>$v): ?>
          <div class="bloom-row"><strong><?= e((string)$k) ?></strong><div class="bar"><span style="width:<?= (float)$v ?>%"></span></div><span><?= e((string)$v) ?>%</span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (!empty($balance['warning'])): ?>
      <div class="alert alert-warn" style="margin-top:1rem"><?= e((string)$balance['warning']) ?></div>
    <?php endif; ?>
    <p style="margin-top:.75rem;font-size:.85rem;color:var(--muted)">Bloom Balance Checker uses stored distribution / unit K-levels. Advisory only — plan is not auto-modified.</p>
    <?= HodFeedback::renderInline(HodFeedback::point($fb, 'bloom')) ?>
  </div>

  <div data-pane="weekly" class="panel" <?= $tab === 'weekly' ? '' : 'hidden' ?>>
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

  <div data-pane="resources" class="panel" <?= $tab === 'resources' ? '' : 'hidden' ?>>
    <ul><?php foreach ($resources as $r): ?><li><?= e(is_string($r)?$r:json_encode($r)) ?></li><?php endforeach; ?></ul>
    <?= HodFeedback::renderInline(HodFeedback::point($fb, 'resources')) ?>
  </div>

  <div data-pane="advice" class="panel" <?= $tab === 'advice' ? '' : 'hidden' ?>>
    <ul><?php foreach ($advice as $a): ?><li><?= e(is_string($a)?$a:json_encode($a)) ?></li><?php endforeach; ?></ul>
    <?= HodFeedback::renderInline(HodFeedback::point($fb, 'advice')) ?>
    <?php if ($review): ?>
      <h3 style="margin-top:1rem">AI Review</h3>
      <pre style="white-space:pre-wrap;background:#f6faf8;padding:1rem;border-radius:12px"><?= e(json_encode($review, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
    <?php endif; ?>
  </div>

  <div data-pane="ai" class="panel" <?= $tab === 'ai' ? '' : 'hidden' ?>>
    <?php if ($isOwner): ?>
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
    <?php else: ?>
      <div class="empty">AI tools are available to the plan owner.</div>
    <?php endif; ?>
  </div>

  <div data-pane="versions" class="panel" <?= $tab === 'versions' ? '' : 'hidden' ?>>
    <div class="table-wrap"><table>
      <thead><tr><th>Version</th><th>Note</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($versions as $v): ?>
        <tr><td>v<?= (int)$v['version'] ?></td><td><?= e($v['change_note'] ?? '') ?></td><td><?= e($v['created_at']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>

    <?php if (count($versions) >= 2): ?>
      <form method="get" class="form-grid" style="margin-top:1rem;grid-template-columns:1fr 1fr auto;gap:.6rem;align-items:end">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="tab" value="versions">
        <div class="form-row"><label>Compare from</label>
          <select name="v1">
            <?php foreach ($versions as $v): ?>
              <option value="<?= (int)$v['version'] ?>" <?= $compareA === (int)$v['version'] ? 'selected' : '' ?>>v<?= (int)$v['version'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row"><label>Compare to</label>
          <select name="v2">
            <?php foreach ($versions as $v): ?>
              <option value="<?= (int)$v['version'] ?>" <?= $compareB === (int)$v['version'] ? 'selected' : (($compareB < 1 && (int)$v['version'] === (int)$plan['version']) ? 'selected' : '') ?>>v<?= (int)$v['version'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit">Compare versions</button>
      </form>
    <?php endif; ?>

    <?php if ($diff): ?>
      <div class="grid grid-2" style="margin-top:1rem">
        <div class="panel" style="margin:0">
          <h3>Added topics</h3>
          <?php if (!$diff['added_topics']): ?><div class="empty">None</div><?php else: ?>
            <ul><?php foreach ($diff['added_topics'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </div>
        <div class="panel" style="margin:0">
          <h3>Removed topics</h3>
          <?php if (!$diff['removed_topics']): ?><div class="empty">None</div><?php else: ?>
            <ul><?php foreach ($diff['removed_topics'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </div>
      </div>
      <div class="panel" style="margin-top:1rem">
        <h3>Changed units / outcomes / Bloom / hours</h3>
        <ul>
          <?php foreach ($diff['changed_units'] as $c): ?>
            <li><strong><?= e($c['label']) ?>:</strong> <?= e($c['detail']) ?></li>
          <?php endforeach; ?>
          <?php foreach ($diff['changed_outcomes'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
          <?php foreach ($diff['changed_bloom'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
          <?php foreach ($diff['changed_hours'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
          <?php foreach (($diff['changed_clo_plo'] ?? []) as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
        </ul>
        <?php if (!$diff['changed_units'] && !$diff['changed_outcomes'] && !$diff['changed_bloom'] && !$diff['changed_hours'] && empty($diff['changed_clo_plo'])): ?>
          <div class="empty">No structural field changes detected.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div data-pane="comments" class="panel" <?= $tab === 'comments' ? '' : 'hidden' ?>>
    <p style="margin:0 0 .8rem;font-size:.9rem;color:var(--muted)">Co-faculty in your department can leave review comments before HOD submission. Tenant-scoped.</p>
    <?php if ($canComment && in_array((string)$plan['status'], ['draft', 'returned', 'submitted', 'under_review'], true)): ?>
      <form method="post" class="form-grid" style="margin-bottom:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_comment">
        <div class="form-row"><label>Add comment</label>
          <textarea name="comment" required maxlength="4000" placeholder="Suggest improvements before HOD review…"></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Post comment</button>
      </form>
    <?php endif; ?>
    <?php if (!$comments): ?>
      <div class="empty">No co-faculty comments yet.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Author</th><th>Comment</th><th>When</th><th>Plan ver.</th></tr></thead>
        <tbody>
        <?php foreach ($comments as $c):
          $ck = json_decode((string)($c['checklist'] ?? '{}'), true) ?: [];
        ?>
          <tr>
            <td>
              <strong><?= e((string)$c['author_name']) ?></strong>
              <div style="font-size:.8rem;color:var(--muted)"><?= e((string)$c['author_role']) ?></div>
            </td>
            <td><?= nl2br(e((string)$c['comments'])) ?></td>
            <td><?= e((string)$c['created_at']) ?></td>
            <td>v<?= (int)($ck['plan_version'] ?? $plan['version']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>PPAI.renderBloomChart('bloomChart', <?= json_encode($balance['distribution']) ?>));
</script>
<?php render_footer(); ?>
