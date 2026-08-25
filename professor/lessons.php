<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
LessonPlanTools::ensureSchema();

$planId = (int)get('plan_id');

// —— Session actions (additive; does not touch AI generation) ——
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');
    $lessonId = (int)post('lesson_id');
    $returnPlan = (int)post('plan_id', $planId);

    if ($action !== '' && $lessonId > 0) {
        $lesson = LessonPlanTools::loadOwnedSession($user, $lessonId);
        if (!$lesson) {
            flash('error', 'Session not found or access denied.');
            redirect('/professor/lessons.php' . ($returnPlan ? ('?plan_id=' . $returnPlan) : ''));
        }
        $plan = LessonPlanTools::loadOwnedPlan($user, (int)$lesson['plan_id']);
        if (!$plan) {
            flash('error', 'Course plan not found.');
            redirect('/professor/lessons.php');
        }
        $returnPlan = (int)$plan['id'];

        try {
            if ($action === 'save_method') {
                $method = trim((string)post('suggested_method', ''));
                if ($method === '' || strlen($method) > 200) {
                    throw new RuntimeException('Enter a teaching-method suggestion (max 200 characters).');
                }
                // Suggestion only — does not overwrite teaching_method from generation.
                Database::update('lesson_plans', [
                    'suggested_method' => $method,
                ], 'id = :id', ['id' => $lessonId]);
                flash('success', 'Teaching-method suggestion updated.');
            } elseif ($action === 'mark_completed') {
                $actual = trim((string)post('actual_date', ''));
                if ($actual === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actual)) {
                    $actual = date('Y-m-d');
                }
                Database::update('lesson_plans', [
                    'session_status' => 'completed',
                    'actual_date' => $actual,
                ], 'id = :id', ['id' => $lessonId]);
                flash('success', 'Session marked completed.');
            } elseif ($action === 'mark_delayed') {
                $actual = trim((string)post('actual_date', ''));
                if ($actual !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actual)) {
                    throw new RuntimeException('Invalid actual date.');
                }
                $data = ['session_status' => 'delayed'];
                if ($actual !== '') {
                    $data['actual_date'] = $actual;
                }
                $planned = trim((string)post('planned_date', ''));
                if ($planned !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $planned)) {
                    $data['planned_date'] = $planned;
                    $data['session_date'] = $planned;
                }
                Database::update('lesson_plans', $data, 'id = :id', ['id' => $lessonId]);
                flash('success', 'Session marked delayed.');
            } elseif ($action === 'save_schedule') {
                $planned = trim((string)post('planned_date', ''));
                $period = trim((string)post('period_label', ''));
                if ($planned === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $planned)) {
                    throw new RuntimeException('Choose a valid planned date.');
                }
                if (strlen($period) > 40) {
                    $period = substr($period, 0, 40);
                }
                Database::update('lesson_plans', [
                    'planned_date' => $planned,
                    'session_date' => $planned,
                    'period_label' => $period !== '' ? $period : null,
                ], 'id = :id', ['id' => $lessonId]);
                flash('success', 'Session schedule saved.');
            } elseif ($action === 'save_resources') {
                $raw = (string)post('resources_json', '[]');
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Invalid resources payload.');
                }
                $clean = LessonPlanTools::sanitizeResources($decoded);
                Database::update('lesson_plans', [
                    'resources' => json_encode($clean, JSON_UNESCAPED_UNICODE),
                ], 'id = :id', ['id' => $lessonId]);
                flash('success', 'Resources updated.');
            } elseif ($action === 'refresh_resources') {
                $bloom = (string)($lesson['bloom_k_level'] ?? 'K2');
                $res = LessonPlanTools::suggestResources(
                    (string)$plan['subject_name'],
                    (string)$lesson['title'],
                    $bloom,
                    (int)($lesson['unit_number'] ?? 0)
                );
                Database::update('lesson_plans', [
                    'resources' => json_encode($res, JSON_UNESCAPED_UNICODE),
                ], 'id = :id', ['id' => $lessonId]);
                flash('success', 'Resource suggestions refreshed (review before use).');
            } elseif ($action === 'add_calendar') {
                $lesson = LessonPlanTools::loadOwnedSession($user, $lessonId) ?: $lesson;
                $classRow = LessonPlanTools::classContextForPlan($plan);
                $sync = LessonPlanTools::syncToCalendar($user, $lesson, $plan, $classRow);
                $fname = 'lesson_session_' . $lessonId . '.ics';
                header('Content-Type: text/calendar; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $fname . '"');
                echo $sync['ics'];
                exit;
            } else {
                throw new RuntimeException('Unknown action.');
            }
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/professor/lessons.php?plan_id=' . $returnPlan . '#session-' . $lessonId);
    }
}

if (in_array((string)$user['role'], ['admin', 'superadmin'], true)) {
    $plans = Database::fetchAll(
        'SELECT id, title, subject_name FROM course_plans WHERE institution_id=? ORDER BY id DESC',
        [$user['institution_id']]
    );
} else {
    $plans = Database::fetchAll(
        'SELECT id, title, subject_name FROM course_plans WHERE professor_id=? AND institution_id=? ORDER BY id DESC',
        [$user['id'], $user['institution_id']]
    );
}

$plan = $planId ? LessonPlanTools::loadOwnedPlan($user, $planId) : null;
if ($planId && !$plan) {
    flash('error', 'Course plan not found or access denied.');
    redirect('/professor/lessons.php');
}

$lessons = [];
$units = [];
$classRow = null;
$stats = ['total' => 0, 'planned' => 0, 'completed' => 0, 'delayed' => 0, 'remaining' => 0, 'completion_pct' => 0.0];
$selectedTitle = '';

if ($plan) {
    $selectedTitle = (string)($plan['title'] ?: $plan['subject_name']);
    $units = Database::fetchAll(
        'SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number',
        [(int)$plan['id']]
    );
    $lessons = Database::fetchAll(
        'SELECT * FROM lesson_plans WHERE plan_id = ? AND professor_id = ? ORDER BY session_number',
        [(int)$plan['id'], (int)$user['id']]
    );
    // Admin viewing another professor's plan in same institution
    if (!$lessons && in_array((string)$user['role'], ['admin', 'superadmin'], true)) {
        $lessons = Database::fetchAll(
            'SELECT * FROM lesson_plans WHERE plan_id = ? ORDER BY session_number',
            [(int)$plan['id']]
        );
    }
    foreach ($lessons as $i => $l) {
        $lessons[$i] = LessonPlanTools::enrichSession($l, $plan, $units);
    }
    $stats = LessonPlanTools::progressStats($lessons);
    $classRow = LessonPlanTools::classContextForPlan($plan);
}

render_header('AI Lesson Planner', 'lessons', ['subtitle' => 'Session-by-session from the course plan']);
?>
<div class="panel">
  <form class="form-grid" method="post" action="<?= e(base_url('/api/ai?module=lesson')) ?>" data-ai-form="#out">
    <?= csrf_field() ?>
    <input type="hidden" name="module" value="lesson">
    <div class="form-row two">
      <div>
        <label>Course plan</label>
        <select name="plan_id" id="lessonPlan" required>
          <option value="">Select a course plan</option>
          <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $planId === (int)$p['id'] ? 'selected' : '' ?>>
              <?= e(trim(($p['title'] ?: $p['subject_name'] ?: 'Untitled'))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;align-items:end"><button class="btn btn-accent" type="submit" <?= $plans ? '' : 'disabled' ?>>Generate lesson plans</button></div>
    </div>
  </form>
  <?php if (!$plans): ?>
    <div class="empty" style="margin-top:1rem">Create a course plan first (<a href="<?= e(base_url('/professor/generate-plan.php')) ?>">New Course Plan</a>), then generate sessions here.</div>
  <?php endif; ?>
</div>
<div id="out"></div>
<?php if ($planId && $plan && !$lessons): ?>
  <div class="panel empty" style="margin-top:1rem">No sessions yet. Click <strong>Generate lesson plans</strong> to build method, activity, assessment, and engagement for each class from this course plan.</div>
<?php endif; ?>
<?php if ($lessons && $plan): ?>
<div class="panel" style="margin-top:1rem">
  <div class="panel-h">
    <h2>Progress<?= $selectedTitle !== '' ? ' · ' . e($selectedTitle) : '' ?></h2>
  </div>
  <div class="chip-row" style="margin-bottom:.75rem">
    <span class="chip">Total <?= (int)$stats['total'] ?></span>
    <span class="chip">Planned <?= (int)$stats['planned'] ?></span>
    <span class="chip">Completed <?= (int)$stats['completed'] ?></span>
    <span class="chip">Delayed <?= (int)$stats['delayed'] ?></span>
    <span class="chip">Remaining <?= (int)$stats['remaining'] ?></span>
    <span class="chip"><?= e((string)$stats['completion_pct']) ?>% complete</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Mins</th>
          <th>Teaching method</th>
          <th>Classroom activity</th>
          <th>Formative assessment</th>
          <th>Engagement</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($lessons as $l):
        $acts = lesson_as_list($l['activities'] ?? []);
        $assess = lesson_as_list($l['formative_assessment'] ?? []);
        $eng = lesson_as_list($l['engagement'] ?? []);
        $st = LessonPlanTools::sanitizeStatus((string)($l['session_status'] ?? 'planned'));
      ?>
        <tr>
          <td><?= (int)$l['session_number'] ?></td>
          <td><a href="#session-<?= (int)$l['id'] ?>"><?= e($l['title']) ?></a></td>
          <td><?= (int)$l['duration_mins'] ?></td>
          <td><?= e((string)$l['teaching_method']) ?></td>
          <td><?= e(implode('; ', $acts)) ?></td>
          <td><?= e(implode('; ', $assess)) ?></td>
          <td><?= e(implode('; ', $eng)) ?></td>
          <td><span class="chip"><?= e(ucfirst($st)) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="lesson-grid">
  <?php foreach ($lessons as $l):
    $acts = lesson_as_list($l['activities'] ?? []);
    $assess = lesson_as_list($l['formative_assessment'] ?? []);
    $eng = lesson_as_list($l['engagement'] ?? []);
    $objs = lesson_as_list($l['objectives'] ?? []);
    $st = LessonPlanTools::sanitizeStatus((string)($l['session_status'] ?? 'planned'));
    $resources = json_decode((string)($l['resources'] ?? '[]'), true);
    if (!is_array($resources)) {
        $resources = [];
    }
    $bloom = strtoupper((string)($l['bloom_k_level'] ?? 'K2'));
    $unitNum = (int)($l['unit_number'] ?? 0);
    $qbUrl = LessonPlanTools::questionBankUrl($l, $plan);
    $pptUrl = LessonPlanTools::pptUrl($l, $plan);
    $sectionLabel = $classRow ? class_batch_label($classRow) : '';
    $lid = (int)$l['id'];
  ?>
    <article class="panel lesson-card" id="session-<?= $lid ?>">
      <header>
        <span class="chip">Session <?= (int)$l['session_number'] ?></span>
        <span class="chip"><?= (int)$l['duration_mins'] ?> min</span>
        <?php if ($unitNum > 0): ?><span class="chip">Unit <?= $unitNum ?></span><?php endif; ?>
        <span class="chip">Bloom <?= e($bloom) ?></span>
        <span class="chip"><?= e(ucfirst($st)) ?></span>
      </header>
      <h3><?= e($l['title']) ?></h3>
      <?php if ($objs): ?>
        <p class="lesson-obj"><?= e(implode(' · ', $objs)) ?></p>
      <?php endif; ?>
      <dl>
        <div><dt>Teaching method (from generation)</dt><dd><?= e((string)($l['teaching_method'] ?: '—')) ?></dd></div>
        <div><dt>Classroom activity</dt><dd><?= e($acts ? implode('; ', $acts) : '—') ?></dd></div>
        <div><dt>Formative assessment</dt><dd><?= e($assess ? implode('; ', $assess) : '—') ?></dd></div>
        <div><dt>Student engagement</dt><dd><?= e($eng ? implode('; ', $eng) : '—') ?></dd></div>
      </dl>

      <div class="lesson-extra">
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_method">
          <input type="hidden" name="lesson_id" value="<?= $lid ?>">
          <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
          <div class="form-row">
            <label>AI teaching-method suggestion<br><span style="font-weight:400">(editable — does not overwrite generated method)</span></label>
            <input name="suggested_method" value="<?= e((string)($l['suggested_method'] ?? '')) ?>" maxlength="200">
          </div>
          <button class="btn btn-sm btn-ghost" type="submit">Save suggestion</button>
        </form>

        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_schedule">
          <input type="hidden" name="lesson_id" value="<?= $lid ?>">
          <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
          <div class="form-row two">
            <div class="form-row">
              <label>Planned date</label>
              <input type="date" name="planned_date" value="<?= e((string)($l['planned_date'] ?? $l['session_date'] ?? '')) ?>">
            </div>
            <div class="form-row">
              <label>Period</label>
              <input name="period_label" placeholder="e.g. Period 1" value="<?= e((string)($l['period_label'] ?? '')) ?>" maxlength="40">
            </div>
          </div>
          <?php if ($sectionLabel !== ''): ?>
            <p style="margin:0;font-size:.85rem;color:var(--muted)">Class/Section: <?= e($sectionLabel) ?></p>
          <?php endif; ?>
          <button class="btn btn-sm btn-ghost" type="submit">Save schedule</button>
        </form>

        <details>
          <summary style="cursor:pointer;font-weight:600">Suggested resources</summary>
          <p style="font-size:.8rem;color:var(--muted);margin:.4rem 0">Suggestions only — not verified institutional resources. Review before sharing with students.</p>
          <ul class="lesson-resources-list">
            <?php foreach ($resources as $r):
              $icon = match ((string)($r['type'] ?? '')) {
                  'video' => 'Video',
                  'reference', 'documentation' => 'Reference',
                  'practice', 'project' => 'Practice',
                  default => 'Reading',
              };
            ?>
              <li>
                <strong><?= e($icon) ?>:</strong> <?= e((string)($r['title'] ?? '')) ?>
                <?php if (!empty($r['note'])): ?><div style="font-size:.82rem;color:var(--muted)"><?= e((string)$r['note']) ?></div><?php endif; ?>
                <?php if (!empty($r['url']) && LessonPlanTools::isSafeHttpUrl((string)$r['url'])): ?>
                  <div><a href="<?= e((string)$r['url']) ?>" target="_blank" rel="noopener noreferrer">Open link</a></div>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
            <?php if (!$resources): ?><li>No suggestions yet.</li><?php endif; ?>
          </ul>
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="refresh_resources">
            <input type="hidden" name="lesson_id" value="<?= $lid ?>">
            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
            <button class="btn btn-sm btn-ghost" type="submit">Refresh suggestions</button>
          </form>
        </details>

        <div class="lesson-status-grid">
          <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_completed">
            <input type="hidden" name="lesson_id" value="<?= $lid ?>">
            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
            <div class="form-row">
              <label>Actual / completed date</label>
              <input type="date" name="actual_date" value="<?= e((string)($l['actual_date'] ?? date('Y-m-d'))) ?>">
            </div>
            <button class="btn btn-sm btn-primary" type="submit">Mark Completed</button>
          </form>
          <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_delayed">
            <input type="hidden" name="lesson_id" value="<?= $lid ?>">
            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
            <div class="form-row">
              <label>If delayed — new planned date</label>
              <input type="date" name="planned_date" value="<?= e((string)($l['planned_date'] ?? '')) ?>">
            </div>
            <div class="form-row">
              <label>Actual date (optional)</label>
              <input type="date" name="actual_date" value="<?= e((string)($l['actual_date'] ?? '')) ?>">
            </div>
            <button class="btn btn-sm btn-ghost" type="submit">Mark Delayed</button>
          </form>
        </div>

        <div class="lesson-actions">
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="add_calendar">
            <input type="hidden" name="lesson_id" value="<?= $lid ?>">
            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
            <button class="btn btn-sm btn-accent" type="submit">Add to Calendar</button>
          </form>
          <a class="btn btn-sm btn-ghost" href="<?= e($qbUrl) ?>">Generate Questions</a>
          <a class="btn btn-sm btn-ghost" href="<?= e($pptUrl) ?>">Generate PPT</a>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<script>
document.getElementById('lessonPlan')?.addEventListener('change', (e) => {
  const id = e.target.value;
  window.location = id ? ('?plan_id=' + encodeURIComponent(id)) : window.location.pathname;
});
</script>
<?php render_footer(); ?>
