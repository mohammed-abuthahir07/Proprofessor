<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $view */
/** @var list<array<string,mixed>> $visible */
/** @var list<array<string,mixed>> $units */
/** @var array<string,int> $counts */
/** @var string $statusFilter */
/** @var string $search */
/** @var int $viewId */
/** @var array{overall:string,points:list<array<string,mixed>>} $fb */

$tab = (string)get('tab', 'details');
if (!in_array($tab, ['details', 'bloom', 'ai', 'feedback'], true)) {
    $tab = 'details';
}
$balance = $view ? CoursePlanTools::bloomBalance($view, $units) : ['distribution' => [], 'warning' => null];
$bloomNames = [
    'K1' => 'Remember',
    'K2' => 'Understand',
    'K3' => 'Apply',
    'K4' => 'Analyse',
    'K5' => 'Evaluate',
    'K6' => 'Create',
];
$aiReview = [];
if ($view && trim((string)($view['ai_review'] ?? '')) !== '') {
    $decodedReview = json_decode((string)$view['ai_review'], true);
    $aiReview = is_array($decodedReview) ? $decodedReview : [];
}
$aiParameters = [];
$aiRecommendations = [];
if ($aiReview) {
    $rawParams = $aiReview['parameters'] ?? $aiReview['scores'] ?? null;
    if (is_array($rawParams)) {
        foreach ($rawParams as $name => $score) {
            if (is_numeric($score)) {
                $aiParameters[] = ['name' => (string)$name, 'score' => (float)$score];
            } elseif (is_array($score) && isset($score['score']) && is_numeric($score['score'])) {
                $aiParameters[] = [
                    'name' => (string)($score['name'] ?? $name),
                    'score' => (float)$score['score'],
                ];
            }
        }
    }
    $rawRecs = $aiReview['recommendations'] ?? [];
    if (is_array($rawRecs)) {
        foreach ($rawRecs as $rec) {
            $text = trim(is_string($rec) ? $rec : (string)json_encode($rec));
            if ($text !== '') {
                $aiRecommendations[] = $text;
            }
        }
    }
}
$showMethods = false;
foreach ($units as $unitRow) {
    if ($asList($unitRow['teaching_methods'] ?? null)) {
        $showMethods = true;
        break;
    }
}
$viewStatus = (string)($view['status'] ?? '');
$canApprove = $view && !in_array($viewStatus, ['approved', 'draft'], true);
$canReturn = $view && in_array($viewStatus, ['submitted', 'under_review'], true);
$canComment = $view && !in_array($viewStatus, ['approved', 'draft'], true);
$viewK46 = $view ? $k46Of($view) : null;
$viewMeta = $view ? $statusMeta($viewStatus) : ['', 'none'];
$classBits = [];
if ($view) {
    $classBits = array_values(array_filter([
        trim((string)($view['class_name'] ?? '')),
        trim((string)($view['class_section'] ?? '')) !== '' ? ('Sec ' . trim((string)$view['class_section'])) : '',
        (int)($view['class_year'] ?? 0) > 0 ? subject_year_label((int)$view['class_year']) : '',
    ], static fn(string $bit): bool => $bit !== ''));
}
$submittedLabel = $view ? $fmtWhen((string)($view['submitted_at'] ?? '')) : '';
$pageTitle = $view ? 'Lesson Plan Review' : 'Approvals';
$backQuery = $queueLink($statusFilter, $search);

render_header($pageTitle, 'approvals', ['compactTitle' => true]);
?>
<?php if ($view): ?>
<div class="hod-ap">
  <section class="hod-hero">
    <div class="hod-hero-copy">
      <a class="hod-ap-back" href="<?= e($backQuery !== '?' ? $backQuery : '?') ?>">&larr; Back to Approvals</a>
      <h2><?= icon('check', 'icon-inline') ?> Lesson Plan Review</h2>
      <p><?= e((string)$view['professor_name']) ?> · <?= e((string)$view['subject_name']) ?></p>
    </div>
    <a class="btn btn-ghost no-print" href="<?= e(base_url('/professor/plan-view.php?id=' . (int)$view['id'])) ?>">Full plan view</a>
  </section>

  <section class="hod-ap-facts" aria-label="Plan summary">
    <div><span>Faculty</span><strong><?= e((string)$view['professor_name']) ?></strong></div>
    <div><span>Subject</span><strong><?= e((string)$view['subject_name']) ?></strong></div>
    <div><span>Plan</span><strong><?= e((string)$view['title']) ?></strong></div>
    <?php if ($classBits): ?><div><span>Class</span><strong><?= e(implode(' · ', $classBits)) ?></strong></div><?php endif; ?>
    <?php if ($submittedLabel !== ''): ?><div><span>Submitted</span><strong><?= e($submittedLabel) ?></strong></div><?php endif; ?>
    <div><span>Status</span><strong class="hod-status is-<?= e($viewMeta[1]) ?>"><?= e($viewMeta[0]) ?></strong></div>
    <div><span>AI score</span><strong class="hod-ap-score is-<?= e($aiTone($view['ai_score'] ?? null)) ?>"><?= e($fmtNum($view['ai_score'] !== null && $view['ai_score'] !== '' ? (float)$view['ai_score'] : null)) ?></strong></div>
    <div><span>K4–K6</span><strong><?= $viewK46 === null ? '—' : e($fmtNum($viewK46) . '%') ?></strong></div>
  </section>

  <nav class="hod-ap-tabs" aria-label="Plan review sections">
    <?php foreach ([
        'details' => 'Plan Details',
        'bloom' => "Bloom's Analysis",
        'ai' => 'AI Review',
        'feedback' => 'Feedback',
    ] as $key => $label): ?>
      <a class="hod-ap-tab<?= $tab === $key ? ' is-on' : '' ?>" href="?id=<?= (int)$view['id'] ?>&amp;tab=<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($viewStatus === 'draft'): ?>
    <div class="alert alert-warn">The professor must submit this plan before you can approve or return it.</div>
  <?php endif; ?>

  <?php if ($tab === 'details'): ?>
  <section class="hod-panel">
    <div class="hod-panel-h"><h2><?= icon('file', 'icon-inline') ?> Plan Details</h2></div>
    <div class="hod-ap-overview">
      <span class="chip"><?= e($fmtNum((float)($view['credits'] ?? 0))) ?> credits</span>
      <span class="chip">v<?= (int)$view['version'] ?></span>
      <?php if (trim((string)($view['university'] ?? '')) !== ''): ?><span class="chip"><?= e((string)$view['university']) ?></span><?php endif; ?>
    </div>
    <?php if (!empty($view['syllabus_input'])): ?>
      <p class="hod-ap-copy"><?= e(mb_strimwidth((string)$view['syllabus_input'], 0, 520, '…')) ?></p>
    <?php endif; ?>
    <h3>Learning outcomes</h3>
    <?php if (!$outcomes): ?>
      <p class="hod-ap-copy">No learning outcomes are stored on this plan.</p>
    <?php else: ?>
      <ol class="hod-ap-list">
        <?php foreach ($outcomes as $outcome): ?>
          <li><?= e(is_string($outcome) ? $outcome : (string)json_encode($outcome)) ?></li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
    <h3>Unit table</h3>
    <div class="table-wrap">
      <table class="hod-ap-units">
        <thead>
          <tr>
            <th>Unit</th>
            <th>Topics</th>
            <th>Hours</th>
            <th>K-level</th>
            <th>Learning outcome</th>
            <?php if ($showMethods): ?><th>Method</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php if (!$units): ?>
          <tr><td colspan="<?= $showMethods ? 6 : 5 ?>" class="muted">No units are stored on this plan.</td></tr>
        <?php endif; ?>
        <?php foreach ($units as $unit):
            $topics = $asList($unit['topics'] ?? null);
            $unitOutcomes = $asList($unit['outcomes'] ?? null);
            $methods = $asList($unit['teaching_methods'] ?? null);
            $level = strtoupper(trim((string)($unit['bloom_k_level'] ?? '')));
            $hours = $unit['hours'] ?? null;
            $hoursLabel = ($hours === null || $hours === '') ? '—' : $fmtNum((float)$hours);
        ?>
          <tr>
            <td>
              <strong><?= (int)($unit['unit_number'] ?? 0) ?></strong>
              <span><?= e((string)($unit['title'] ?? '')) ?></span>
            </td>
            <td><?= e($topics ? implode('; ', $topics) : '—') ?></td>
            <td><?= e($hoursLabel) ?></td>
            <td><?php if ($level !== ''): ?><span class="hod-ap-k is-<?= e(strtolower($level)) ?>"><?= e($level) ?></span><?php else: ?>—<?php endif; ?></td>
            <td><?= e($unitOutcomes ? implode('; ', $unitOutcomes) : '—') ?></td>
            <?php if ($showMethods): ?><td><?= e($methods ? implode('; ', $methods) : '—') ?></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($weekly): ?>
      <h3>Weekly plan</h3>
      <ul class="hod-ap-list">
        <?php foreach ($weekly as $week):
            if (!is_array($week)) {
                continue;
            }
            $weekTopics = $asList($week['topics'] ?? ($week['focus'] ?? null));
            $pedagogy = trim((string)($week['pedagogy'] ?? ''));
            $weekText = implode('; ', $weekTopics);
            if ($pedagogy !== '') {
                $weekText = $weekText !== '' ? ($weekText . ' · ' . $pedagogy) : $pedagogy;
            }
        ?>
          <li><strong>Week <?= e((string)($week['week'] ?? '')) ?></strong> — <?= e($weekText !== '' ? $weekText : 'No detail stored') ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($resources): ?>
      <h3>Resources</h3>
      <ul class="hod-ap-list">
        <?php foreach ($asList($resources) as $resource): ?><li><?= e($resource) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($advice): ?>
      <h3>Expert advice</h3>
      <ul class="hod-ap-list">
        <?php foreach ($asList($advice) as $tip): ?><li><?= e($tip) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($tab === 'bloom'): ?>
  <section class="hod-panel">
    <div class="hod-panel-h"><h2><?= icon('chart', 'icon-inline') ?> Bloom's Analysis</h2></div>
    <?php
      $dist = is_array($balance['distribution'] ?? null) ? $balance['distribution'] : [];
      $higher = null;
      if ($dist) {
          $higher = round((float)($dist['K4'] ?? 0) + (float)($dist['K5'] ?? 0) + (float)($dist['K6'] ?? 0), 1);
      }
    ?>
    <?php if (!$dist): ?>
      <div class="empty">Bloom distribution is not stored on this plan.</div>
    <?php else: ?>
      <p class="hod-ap-copy">Stored Bloom distribution for this plan.<?php if ($higher !== null): ?> Higher-order coverage (K4–K6) is <?= e($fmtNum($higher)) ?>%.<?php endif; ?></p>
      <div class="hod-ap-bloom">
        <?php foreach ($bloomNames as $key => $name):
            $pct = round((float)($dist[$key] ?? 0), 1);
            $tone = in_array($key, ['K4', 'K5', 'K6'], true) ? 'is-brand' : '';
        ?>
          <div class="hod-ap-bloom-row">
            <span class="hod-ap-k is-<?= e(strtolower($key)) ?>"><?= e($key) ?></span>
            <span class="hod-ap-bloom-name"><?= e($name) ?></span>
            <div class="hod-bar <?= e($tone) ?>"><span style="width:<?= e((string)max(0, min(100, $pct))) ?>%"></span></div>
            <strong><?= e($fmtNum($pct)) ?>%</strong>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($balance['warning'])): ?>
      <div class="alert alert-warn"><?= e((string)$balance['warning']) ?></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($tab === 'ai'): ?>
  <section class="hod-panel">
    <div class="hod-panel-h"><h2><?= icon('spark', 'icon-inline') ?> AI Review</h2></div>
    <div class="hod-ap-ai-hero">
      <strong class="hod-ap-score is-<?= e($aiTone($view['ai_score'] ?? null)) ?>"><?= e($fmtNum($view['ai_score'] !== null && $view['ai_score'] !== '' ? (float)$view['ai_score'] : null)) ?></strong>
      <div>
        <span>Overall AI score</span>
        <p>This is the score stored on the course plan.</p>
      </div>
    </div>
    <?php if (!$aiParameters && !$aiRecommendations): ?>
      <div class="empty">No AI parameter breakdown is stored for this plan.</div>
    <?php endif; ?>
    <?php if ($aiParameters): ?>
      <div class="hod-ap-params">
        <?php foreach ($aiParameters as $param):
            $paramTone = $aiTone($param['score']);
        ?>
          <div class="hod-ap-param">
            <div>
              <strong><?= e($param['name']) ?></strong>
              <span class="hod-status is-<?= e($paramTone === 'none' ? 'none' : $paramTone) ?>"><?= e($fmtNum($param['score'])) ?></span>
            </div>
            <div class="hod-bar is-<?= e($paramTone === 'none' ? 'brand' : $paramTone) ?>"><span style="width:<?= e((string)max(0, min(100, (float)$param['score']))) ?>%"></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($aiRecommendations): ?>
      <h3>Recommendations</h3>
      <ul class="hod-ap-list">
        <?php foreach ($aiRecommendations as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($canApprove || $canComment || $canReturn): ?>
  <form method="post" class="hod-ap-form">
    <?= csrf_field() ?>
    <input type="hidden" name="plan_id" value="<?= (int)$view['id'] ?>">
    <input type="hidden" name="tab" value="feedback">
    <section class="hod-panel" <?= $tab === 'feedback' ? '' : 'hidden' ?>>
      <div class="hod-panel-h"><h2><?= icon('file', 'icon-inline') ?> Feedback</h2></div>
      <p class="hod-ap-copy">Comments are saved with the decision and sent to the faculty member through the existing review notice.</p>
      <label class="hod-ap-label" for="hodOverall">Feedback / comments</label>
      <textarea id="hodOverall" name="overall" rows="5" placeholder="Enter feedback for the faculty member..."><?= e($fb['overall']) ?></textarea>
      <div class="hod-ap-points">
        <?php HodFeedback::renderEditor($fb, 'overview', 'Course overview'); ?>
        <?php HodFeedback::renderEditor($fb, 'outcomes', 'Learning outcomes'); ?>
        <?php foreach ($units as $unit):
            $num = (int)($unit['unit_number'] ?? 0);
            $unitTitle = trim((string)($unit['title'] ?? ''));
            $label = 'Unit ' . $num . ($unitTitle !== '' ? ' · ' . $unitTitle : '');
            HodFeedback::renderEditor($fb, 'unit:' . $num, $label);
        endforeach; ?>
        <?php HodFeedback::renderEditor($fb, 'bloom', "Bloom's mapping"); ?>
        <?php HodFeedback::renderEditor($fb, 'weekly', 'Weekly plan'); ?>
        <?php HodFeedback::renderEditor($fb, 'resources', 'Resources'); ?>
        <?php HodFeedback::renderEditor($fb, 'advice', 'Expert advice'); ?>
      </div>
    </section>
    <div class="hod-ap-bar">
      <span class="hod-status is-<?= e($viewMeta[1]) ?>"><?= e($viewMeta[0]) ?></span>
      <div class="hod-ap-bar-actions">
        <?php if ($canComment): ?>
          <button class="btn btn-ghost" name="action" value="comment" type="submit">Save comments</button>
        <?php endif; ?>
        <?php if ($canReturn): ?>
          <button class="btn btn-ghost" name="action" value="request_changes" type="submit">Request changes</button>
          <button class="btn hod-ap-return" name="action" value="reject" type="submit">Return for Revision</button>
        <?php endif; ?>
        <?php if ($canApprove): ?>
          <button class="btn btn-primary" name="action" value="approve" type="submit">Approve Plan</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
  <?php elseif ($tab === 'feedback'): ?>
  <section class="hod-panel">
    <div class="hod-panel-h"><h2><?= icon('file', 'icon-inline') ?> Feedback</h2></div>
    <?php if ($fb['overall'] === '' && !$fb['points']): ?>
      <div class="empty">No HOD feedback is stored on this plan.</div>
    <?php else: ?>
      <?php if ($fb['overall'] !== ''): ?><p class="hod-ap-copy"><?= e($fb['overall']) ?></p><?php endif; ?>
      <?php foreach ($fb['points'] as $point): ?>
        <article class="hod-ap-saved">
          <strong><?= e((string)($point['label'] ?? 'Comment')) ?></strong>
          <span class="chip"><?= e(HodFeedback::flagLabel((string)($point['flag'] ?? ''))) ?></span>
          <?php if (trim((string)($point['comment'] ?? '')) !== ''): ?><p><?= e((string)$point['comment']) ?></p><?php endif; ?>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
    <div class="hod-ap-bar">
      <span class="hod-status is-<?= e($viewMeta[1]) ?>"><?= e($viewMeta[0]) ?></span>
    </div>
  </section>
  <?php else: ?>
  <div class="hod-ap-bar">
    <span class="hod-status is-<?= e($viewMeta[1]) ?>"><?= e($viewMeta[0]) ?></span>
  </div>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="hod-ap">
  <?php if ($viewId): ?>
    <div class="alert alert-warn">That plan is not available in this department.</div>
  <?php endif; ?>
  <section class="hod-hero">
    <div class="hod-hero-copy">
      <h2><?= icon('check', 'icon-inline') ?> Approvals</h2>
      <p>Review and manage lesson plans submitted by department faculty.</p>
    </div>
  </section>

  <section class="hod-kpi hod-ap-kpi" aria-label="Approval counts">
    <a class="hod-kpi-card" href="<?= e($queueLink('pending', $search)) ?>">
      <span class="label">Pending</span>
      <strong class="value tone-warn"><?= (int)$counts['pending'] ?></strong>
      <span class="hint">Submitted or in review</span>
    </a>
    <a class="hod-kpi-card" href="<?= e($queueLink('approved', $search)) ?>">
      <span class="label">Approved</span>
      <strong class="value tone-ok"><?= (int)$counts['approved'] ?></strong>
      <span class="hint">Approved plans</span>
    </a>
    <a class="hod-kpi-card" href="<?= e($queueLink('returned', $search)) ?>">
      <span class="label">Returned</span>
      <strong class="value" style="color:#fca5a5"><?= (int)$counts['returned'] ?></strong>
      <span class="hint">Returned for revision</span>
    </a>
  </section>

  <section class="hod-panel hod-ap-tools">
    <div class="hod-ap-filters">
      <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'returned' => 'Returned'] as $key => $label): ?>
        <a class="hod-ap-filter is-<?= e($key) ?><?= $statusFilter === $key ? ' is-on' : '' ?>" href="<?= e($queueLink($key, $search)) ?>"><?= e($label) ?> <span><?= (int)$counts[$key] ?></span></a>
      <?php endforeach; ?>
    </div>
    <form class="hod-ap-search" method="get">
      <?php if ($statusFilter !== 'all'): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search faculty, subject, or plan" aria-label="Search faculty, subject, or plan">
      <button class="btn btn-ghost" type="submit">Search</button>
    </form>
  </section>

  <?php if (!$visible): ?>
    <section class="hod-panel">
      <div class="empty"><?= $counts['all'] === 0 ? 'No plans are in the approval queue.' : 'No plans match this view.' ?></div>
    </section>
  <?php else: ?>
    <section class="hod-ap-list-wrap" aria-label="Approval queue">
      <?php foreach ($visible as $row):
          $rowStatus = (string)($row['status'] ?? '');
          $meta = $statusMeta($rowStatus);
          $score = $row['ai_score'];
          $k46 = $k46Of($row);
          $when = $fmtWhen((string)($row['submitted_at'] ?? ''));
          if ($when === '') {
              $when = $fmtWhen((string)($row['updated_at'] ?? ''));
          }
          $pendingRow = in_array($rowStatus, ['submitted', 'under_review'], true);
          $kTone = $k46 !== null && $k46 < 30 ? 'is-warn' : 'is-ok';
      ?>
        <article class="hod-ap-card">
          <?php
            $subjectLabel = trim((string)$row['subject_name']);
            $planLabel = trim((string)$row['title']);
            $showPlan = $planLabel !== '' && strcasecmp($planLabel, $subjectLabel) !== 0;
          ?>
          <div class="hod-ap-main">
            <span class="hod-ap-avatar" aria-hidden="true"><?= e($initialsOf((string)$row['professor_name'])) ?></span>
            <div class="hod-ap-id">
              <strong><?= e((string)$row['professor_name']) ?></strong>
              <span><?= e($subjectLabel !== '' ? $subjectLabel : $planLabel) ?></span>
              <?php if ($showPlan || $when !== ''): ?>
                <em><?= e(trim($showPlan ? $planLabel : '') . ($showPlan && $when !== '' ? ' · ' : '') . $when) ?></em>
              <?php endif; ?>
            </div>
            <span class="hod-status is-<?= e($meta[1]) ?>"><?= e($meta[0]) ?></span>
          </div>
          <div class="hod-ap-side">
            <div class="hod-ap-metric">
              <span>AI score</span>
              <strong class="hod-ap-score is-<?= e($aiTone($score)) ?>"><?= e($fmtNum($score !== null && $score !== '' ? (float)$score : null)) ?></strong>
            </div>
            <div class="hod-ap-metric">
              <span>K4–K6</span>
              <strong><?= $k46 === null ? '—' : e($fmtNum($k46) . '%') ?></strong>
              <div class="hod-ap-mini <?= e($k46 === null ? '' : $kTone) ?>"><span style="width:<?= $k46 === null ? '0' : e((string)max(0, min(100, $k46))) ?>%"></span></div>
            </div>
            <div class="hod-ap-actions">
            <a class="btn btn-sm btn-ghost" href="?id=<?= (int)$row['id'] ?>">Review</a>
            <?php if ($pendingRow): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="plan_id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="back" value="queue">
                <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <button class="btn btn-sm btn-primary" name="action" value="approve" type="submit">Approve</button>
              </form>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="plan_id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="back" value="queue">
                <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <button class="btn btn-sm hod-ap-return" name="action" value="reject" type="submit">Return</button>
              </form>
            <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php render_footer(); ?>
