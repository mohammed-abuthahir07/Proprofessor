<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$deptId = $user['department_id'];
$isAdmin = ($user['role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $planId = (int)post('plan_id');
    $action = (string)post('action');
    $packed = HodFeedback::fromPost(
        is_array($_POST['points'] ?? null) ? $_POST['points'] : [],
        is_array($_POST['flags'] ?? null) ? $_POST['flags'] : [],
        is_array($_POST['labels'] ?? null) ? $_POST['labels'] : [],
        (string)post('overall', '')
    );
    $encoded = HodFeedback::encode($packed);
    $summary = HodFeedback::summary($packed);
    $status = match ($action) {
        'approve' => 'approved',
        'reject' => 'returned',
        'request_changes' => 'returned',
        default => 'under_review',
    };
    $reviewAction = match ($action) {
        'approve' => 'approve',
        'reject' => 'reject',
        'comment' => 'comment',
        default => 'request_changes',
    };
    $sql = 'SELECT * FROM course_plans WHERE id=? AND institution_id=?';
    $params = [$planId, (int)$user['institution_id']];
    if (!$isAdmin) {
        $sql .= ' AND department_id=?';
        $params[] = $deptId;
    }
    $plan = Database::fetch($sql, $params);
    if ($plan && in_array($action, ['approve', 'reject', 'request_changes'], true) && ($plan['status'] ?? '') === 'draft') {
        flash('error', 'The professor must submit this plan before you can approve or return it.');
        redirect('/hod/approvals.php?id=' . $planId);
    }
    if ($plan) {
        Database::update('course_plans', [
            'status' => $status,
            'hod_comments' => $encoded,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => $user['id'],
        ], 'id = :id', ['id' => $planId]);
        Database::insert('plan_reviews', [
            'plan_id' => $planId,
            'reviewer_id' => $user['id'],
            'action' => $reviewAction,
            'comments' => $summary,
            'checklist' => $encoded,
        ]);
        notify_user(
            (int)$plan['professor_id'],
            'approval',
            'Plan ' . str_replace('_', ' ', $status),
            $summary,
            '/professor/plan-view.php?id=' . $planId
        );
        flash('success', 'Point-by-point feedback saved.');
    }
    redirect('/hod/approvals.php?id=' . $planId);
}

$viewId = (int)get('id');
$queueSql = 'SELECT p.*, u.full_name AS professor_name FROM course_plans p
     JOIN users u ON u.id=p.professor_id
     WHERE p.institution_id=? AND p.status IN ("submitted","under_review","returned","approved")';
$queueParams = [(int)$user['institution_id']];
if (!$isAdmin) {
    $queueSql .= ' AND p.department_id=?';
    $queueParams[] = $deptId;
}
$queueSql .= ' ORDER BY FIELD(p.status,"submitted","under_review","returned","draft","approved"), p.submitted_at DESC';
$queue = Database::fetchAll($queueSql, $queueParams);

$view = null;
if ($viewId) {
    $view = Database::fetch(
        'SELECT p.*, u.full_name AS professor_name FROM course_plans p JOIN users u ON u.id=p.professor_id WHERE p.id=? AND p.institution_id=?',
        [$viewId, (int)$user['institution_id']]
    );
    if ($view && !$isAdmin && (int)$view['department_id'] !== (int)$deptId) {
        $view = null;
    }
}
$units = $view ? Database::fetchAll('SELECT * FROM plan_units WHERE plan_id=? ORDER BY unit_number', [$viewId]) : [];
$bloom = $view ? (json_decode($view['bloom_data'] ?: '{}', true) ?: []) : [];
$weekly = $view ? (json_decode($view['weekly_plan'] ?: '[]', true) ?: []) : [];
$resources = $view ? (json_decode($view['resources'] ?: '[]', true) ?: []) : [];
$advice = $view ? (json_decode($view['expert_advice'] ?: '[]', true) ?: []) : [];
$planData = $view ? (json_decode($view['plan_data'] ?: '{}', true) ?: []) : [];
$outcomes = $planData['learning_outcomes'] ?? [];
if (!$units && !empty($planData['units']) && is_array($planData['units'])) {
    $units = $planData['units'];
}
$fb = $view ? HodFeedback::parse($view['hod_comments'] ?? null) : ['overall' => '', 'points' => []];

render_header('Pending Approvals', 'approvals', ['subtitle' => 'Review · comment · approve']);
?>
<div class="grid grid-2 hod-approvals">
  <div class="panel">
    <div class="table-wrap"><table>
      <thead><tr><th>Plan</th><th>Faculty</th><th>Status</th><th>AI</th></tr></thead>
      <tbody>
      <?php if (!$queue): ?>
        <tr><td colspan="4" class="empty">No plans in the queue.</td></tr>
      <?php endif; ?>
      <?php foreach ($queue as $q): ?>
        <tr class="click-row <?= $viewId === (int)$q['id'] ? 'is-selected' : '' ?>" data-href="?id=<?= (int)$q['id'] ?>">
          <td><a href="?id=<?= (int)$q['id'] ?>"><?= e($q['title']) ?></a></td>
          <td><?= e($q['professor_name']) ?></td>
          <td><?= status_badge($q['status']) ?></td>
          <td><?= e((string)($q['ai_score'] ?? '-')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="panel review-pane">
    <?php if (!$view): ?><div class="empty">Select a plan to review.</div><?php else: ?>
      <div class="review-head">
        <h2><?= e($view['title']) ?></h2>
        <p><?= e($view['professor_name']) ?> · <?= e($view['subject_name']) ?> · Score <?= e((string)$view['ai_score']) ?></p>
      </div>
      <form method="post" class="form-grid review-form">
        <?= csrf_field() ?>
        <input type="hidden" name="plan_id" value="<?= (int)$view['id'] ?>">

        <section class="review-point">
          <div class="review-point-h">
            <strong>Course overview</strong>
            <span class="chip"><?= e((string)$view['credits']) ?> credits</span>
          </div>
          <p class="review-copy"><?= e((string)($view['university'] ?? '')) ?><?= $view['university'] ? ' · ' : '' ?>v<?= (int)$view['version'] ?></p>
          <?php if (!empty($view['syllabus_input'])): ?>
            <p class="review-copy"><?= e(mb_strimwidth((string)$view['syllabus_input'], 0, 420, '…')) ?></p>
          <?php endif; ?>
          <?php HodFeedback::renderEditor($fb, 'overview', 'Course overview'); ?>
        </section>

        <section class="review-point">
          <div class="review-point-h"><strong>Learning outcomes</strong></div>
          <?php if (!$outcomes): ?>
            <p class="review-copy">No outcomes listed.</p>
          <?php else: ?>
            <ol class="review-list">
              <?php foreach ($outcomes as $o): ?>
                <li><?= e(is_string($o) ? $o : json_encode($o)) ?></li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
          <?php HodFeedback::renderEditor($fb, 'outcomes', 'Learning outcomes'); ?>
        </section>

        <?php foreach ($units as $u): ?>
          <?php
            $num = (int)($u['unit_number'] ?? 0);
            $unitTitle = (string)($u['title'] ?? '');
            $topics = $u['topics'] ?? [];
            $unitOut = $u['outcomes'] ?? [];
            if (is_string($topics)) {
                $topics = json_decode($topics, true) ?: [];
            }
            if (is_string($unitOut)) {
                $unitOut = json_decode($unitOut, true) ?: [];
            }
            $label = 'Unit ' . $num . ($unitTitle !== '' ? ' · ' . $unitTitle : '');
          ?>
          <section class="review-point">
            <div class="review-point-h">
              <strong>Unit <?= $num ?> · <?= e($unitTitle) ?></strong>
              <span class="chip"><?= e((string)($u['bloom_k_level'] ?? '')) ?> · <?= e((string)($u['hours'] ?? '')) ?>h</span>
            </div>
            <?php if ($topics): ?>
              <p class="review-copy"><strong>Topics:</strong> <?= e(is_array($topics) ? implode(', ', $topics) : (string)$topics) ?></p>
            <?php endif; ?>
            <?php if ($unitOut): ?>
              <p class="review-copy"><strong>Outcomes:</strong> <?= e(is_array($unitOut) ? implode('; ', $unitOut) : (string)$unitOut) ?></p>
            <?php endif; ?>
            <?php HodFeedback::renderEditor($fb, 'unit:' . $num, $label); ?>
          </section>
        <?php endforeach; ?>

        <section class="review-point">
          <div class="review-point-h"><strong>Bloom's mapping</strong></div>
          <canvas id="bloomHod" height="140"></canvas>
          <?php HodFeedback::renderEditor($fb, 'bloom', "Bloom's mapping"); ?>
        </section>

        <section class="review-point">
          <div class="review-point-h"><strong>Weekly plan</strong></div>
          <?php if (!$weekly): ?>
            <p class="review-copy">No weekly plan.</p>
          <?php else: ?>
            <ul class="review-list">
              <?php foreach (array_slice($weekly, 0, 8) as $w): ?>
                <li>Week <?= e((string)($w['week'] ?? '')) ?> — <?= e((string)($w['focus'] ?? json_encode($w))) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php HodFeedback::renderEditor($fb, 'weekly', 'Weekly plan'); ?>
        </section>

        <section class="review-point">
          <div class="review-point-h"><strong>Resources</strong></div>
          <?php if (!$resources): ?>
            <p class="review-copy">No resources listed.</p>
          <?php else: ?>
            <ul class="review-list">
              <?php foreach ($resources as $r): ?>
                <li><?= e(is_string($r) ? $r : json_encode($r)) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php HodFeedback::renderEditor($fb, 'resources', 'Resources'); ?>
        </section>

        <section class="review-point">
          <div class="review-point-h"><strong>Expert advice</strong></div>
          <?php if (!$advice): ?>
            <p class="review-copy">No expert advice.</p>
          <?php else: ?>
            <ul class="review-list">
              <?php foreach ($advice as $a): ?>
                <li><?= e(is_string($a) ? $a : json_encode($a)) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php HodFeedback::renderEditor($fb, 'advice', 'Expert advice'); ?>
        </section>

        <section class="review-point">
          <div class="review-point-h"><strong>Overall decision note</strong></div>
          <textarea name="overall" rows="3" placeholder="Summary the professor will see at the top of the plan…"><?= e($fb['overall']) ?></textarea>
        </section>

        <div class="review-actions">
          <button class="btn btn-ghost" name="action" value="comment" type="submit">Save comments</button>
          <button class="btn btn-primary" name="action" value="approve" type="submit">Approve</button>
          <button class="btn btn-ghost" name="action" value="request_changes" type="submit">Request changes</button>
          <button class="btn btn-accent" name="action" value="reject" type="submit">Return</button>
          <a class="btn btn-ghost" href="<?= e(base_url('/professor/plan-view.php?id='.$view['id'])) ?>">Full view</a>
        </div>
      </form>
      <script>document.addEventListener('DOMContentLoaded',()=>PPAI.renderBloomChart('bloomHod', <?= json_encode($bloom) ?>));</script>
    <?php endif; ?>
  </div>
</div>
<?php render_footer(); ?>
