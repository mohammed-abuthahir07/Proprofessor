<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
QuestionBankTools::ensureSchema();

$bankId = (int)get('bank_id');
$planId = (int)get('plan_id');
$prefillUnit = max(1, min(20, (int)get('unit', 1)));
$prefillK = strtoupper(trim((string)get('klevel', 'K2')));
if (!preg_match('/^K[1-6]$/', $prefillK)) {
    $prefillK = 'K2';
}
$prefillTopic = trim((string)get('topic', ''));
$prefillContext = trim((string)get('context', ''));
$tab = (string)get('tab', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');

    if ($action === 'keep_question') {
        $qid = (int)post('question_id');
        $q = QuestionBankTools::ownedQuestion($user, $qid);
        if (!$q) {
            flash('error', 'Question not found.');
            redirect('/professor/questions.php');
        }
        $meta = json_decode((string)($q['meta'] ?? '{}'), true) ?: [];
        $meta['duplicate_reviewed'] = true;
        Database::update('questions', [
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $qid]);
        flash('success', 'Similarity warning dismissed (question kept).');
        redirect('/professor/questions.php?bank_id=' . (int)$q['bank_id']);
    }

    if ($action === 'regenerate_question') {
        $qid = (int)post('question_id');
        $q = QuestionBankTools::ownedQuestion($user, $qid);
        if (!$q) {
            flash('error', 'Question not found.');
            redirect('/professor/questions.php');
        }
        $type = (string)$q['question_type'];
        $klevel = (string)($q['bloom_k_level'] ?: 'K2');
        $unit = max(1, (int)($q['unit_number'] ?: 1));
        $subject = 'Course';
        $plan = null;
        if (!empty($q['plan_id'])) {
            $plan = Database::fetch(
                'SELECT * FROM course_plans WHERE id = ? AND professor_id = ? AND institution_id = ?',
                [(int)$q['plan_id'], $user['id'], $user['institution_id']]
            );
            if ($plan) {
                $subject = (string)($plan['subject_name'] ?: $plan['title']);
            }
        }
        $bankCfg = Database::fetch('SELECT config FROM question_banks WHERE id = ?', [(int)$q['bank_id']]);
        $cfg = json_decode((string)($bankCfg['config'] ?? '{}'), true) ?: [];
        if (!empty($cfg['subject'])) {
            $subject = (string)$cfg['subject'];
        }
        $fresh = Gemini::demoQuestionBank($subject, $type, $klevel, $unit, 1);
        $fresh = QuestionBankTools::enrichGeneratedQuestions(
            $fresh,
            $plan,
            $unit,
            $klevel,
            array_values(array_filter(
                QuestionBankTools::professorQuestions($user),
                static fn($row) => (int)$row['id'] !== $qid
            ))
        );
        $nq = $fresh[0] ?? null;
        if (!$nq) {
            flash('error', 'Could not regenerate question.');
            redirect('/professor/questions.php?bank_id=' . (int)$q['bank_id']);
        }
        $meta = [
            'subject' => $subject,
            'clo_code' => $nq['clo_code'] ?? null,
            'regenerated_from' => $qid,
            'duplicate_reviewed' => empty($nq['similarity']),
        ];
        if (!empty($nq['similarity'])) {
            $meta['similarity'] = $nq['similarity'];
        }
        $update = [
            'stem' => (string)$nq['stem'],
            'options' => isset($nq['options']) ? json_encode($nq['options'], JSON_UNESCAPED_UNICODE) : null,
            'correct_answer' => $nq['correct_answer'] ?? null,
            'explanation' => $nq['explanation'] ?? null,
            'bloom_k_level' => $nq['bloom_k_level'] ?? $klevel,
            'marks' => $nq['marks'] ?? $q['marks'],
            'unit_number' => $nq['unit_number'] ?? $unit,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'marking_scheme' => (string)($nq['marking_scheme'] ?? QuestionBankTools::buildMarkingScheme($nq)),
        ];
        if (!empty($nq['clo_code'])) {
            $update['clo_code'] = (string)$nq['clo_code'];
        }
        Database::update('questions', $update, 'id = :id', ['id' => $qid]);
        flash('success', 'Question regenerated. Review similarity warning if shown.');
        redirect('/professor/questions.php?bank_id=' . (int)$q['bank_id']);
    }

    if ($action === 'save_clo') {
        $qid = (int)post('question_id');
        $q = QuestionBankTools::ownedQuestion($user, $qid);
        if (!$q) {
            flash('error', 'Question not found.');
            redirect('/professor/questions.php');
        }
        $clo = strtoupper(trim((string)post('clo_code', '')));
        if ($clo !== '' && !preg_match('/^CLO\d{1,2}$/', $clo)) {
            flash('error', 'CLO must look like CLO1, CLO2, …');
            redirect('/professor/questions.php?bank_id=' . (int)$q['bank_id']);
        }
        $meta = json_decode((string)($q['meta'] ?? '{}'), true) ?: [];
        $meta['clo_code'] = $clo !== '' ? $clo : null;
        Database::update('questions', [
            'clo_code' => $clo !== '' ? $clo : null,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], 'id = :id', ['id' => $qid]);
        flash('success', 'CLO mapping updated.');
        redirect('/professor/questions.php?bank_id=' . (int)$q['bank_id']);
    }

    if ($action === 'build_paper' || $action === 'generate_sets') {
        $title = trim((string)post('paper_title', 'Question Paper'));
        $totalMarks = max(1, (int)post('total_marks', 50));
        $parts = [];
        $counts = post('part_count');
        $marks = post('part_marks');
        if (!is_array($counts)) {
            $counts = [];
        }
        if (!is_array($marks)) {
            $marks = [];
        }
        foreach ($counts as $i => $c) {
            $c = (int)$c;
            $m = (float)($marks[$i] ?? 0);
            if ($c > 0 && $m > 0) {
                $parts[] = ['count' => $c, 'marks' => $m];
            }
        }
        $bloomPct = [];
        foreach (['K1', 'K2', 'K3', 'K4', 'K5', 'K6'] as $k) {
            $bloomPct[$k] = max(0, (float)post('bloom_' . $k, 0));
        }
        if (array_sum($bloomPct) <= 0) {
            $bloomPct = QuestionBankTools::defaultBloomPct();
        }
        $pool = QuestionBankTools::professorQuestions($user);
        if (!$pool) {
            flash('error', 'Your personal question bank is empty. Generate questions first.');
            redirect('/professor/questions.php');
        }

        if ($action === 'build_paper') {
            $built = QuestionBankTools::assemblePaper($pool, $parts, $bloomPct, $totalMarks);
            if (!$built['ok']) {
                flash('error', (string)$built['error']);
                redirect('/professor/questions.php?tab=paper');
            }
            $qs = $built['questions'];
            $key = QuestionBankTools::buildAnswerKey($qs);
            $paperId = Database::insert('exam_papers', [
                'institution_id' => (int)$user['institution_id'],
                'professor_id' => (int)$user['id'],
                'title' => mb_substr($title !== '' ? $title : 'Question Paper', 0, 255),
                'total_marks' => (float)$built['total_marks'],
                'config' => json_encode([
                    'parts' => $parts,
                    'bloom_pct' => $bloomPct,
                    'mode' => 'single',
                ], JSON_UNESCAPED_UNICODE),
                'sets_data' => json_encode(['A' => array_map(static fn($q) => (int)$q['id'], $qs)], JSON_UNESCAPED_UNICODE),
                'answer_key' => json_encode(['A' => $key], JSON_UNESCAPED_UNICODE),
            ]);
            flash('success', 'Question paper assembled.');
            redirect('/professor/question-paper.php?id=' . $paperId);
        }

        $setsResult = QuestionBankTools::generateEquivalentSets($pool, $parts, $bloomPct, $totalMarks, 3);
        if (!$setsResult['ok']) {
            flash('error', (string)$setsResult['error']);
            redirect('/professor/questions.php?tab=paper');
        }
        $setsPayload = [];
        $keysPayload = [];
        foreach ($setsResult['sets'] as $label => $qs) {
            $setsPayload[$label] = array_map(static fn($q) => (int)$q['id'], $qs);
            $keysPayload[$label] = QuestionBankTools::buildAnswerKey($qs);
        }
        $paperId = Database::insert('exam_papers', [
            'institution_id' => (int)$user['institution_id'],
            'professor_id' => (int)$user['id'],
            'title' => mb_substr(($title !== '' ? $title : 'Question Paper') . ' · Sets A/B/C', 0, 255),
            'total_marks' => (float)$totalMarks,
            'config' => json_encode([
                'parts' => $parts,
                'bloom_pct' => $bloomPct,
                'mode' => 'sets',
                'note' => $setsResult['note'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'sets_data' => json_encode($setsPayload, JSON_UNESCAPED_UNICODE),
            'answer_key' => json_encode($keysPayload, JSON_UNESCAPED_UNICODE),
        ]);
        if (!empty($setsResult['note'])) {
            flash('success', 'Sets generated. Note: ' . $setsResult['note']);
        } else {
            flash('success', 'Sets A / B / C generated with equivalent structure.');
        }
        redirect('/professor/question-paper.php?id=' . $paperId);
    }
}

$plans = Database::fetchAll(
    'SELECT id, title, subject_name, syllabus_input, plan_data FROM course_plans WHERE professor_id=? AND institution_id=?',
    [$user['id'], $user['institution_id']]
);
$banks = Database::fetchAll(
    'SELECT * FROM question_banks WHERE professor_id=? ORDER BY id DESC LIMIT 30',
    [$user['id']]
);
$bank = $bankId ? QuestionBankTools::ownedBank($user, $bankId) : null;
$questions = $bank
    ? Database::fetchAll('SELECT * FROM questions WHERE bank_id=? ORDER BY id ASC', [$bank['id']])
    : [];
$allQs = QuestionBankTools::professorQuestions($user);
$papers = Database::fetchAll(
    'SELECT id, title, total_marks, created_at, config FROM exam_papers
     WHERE professor_id = ? AND institution_id = ? ORDER BY id DESC LIMIT 20',
    [$user['id'], $user['institution_id']]
);

$context = $prefillContext;
if ($context === '' && $planId) {
    foreach ($plans as $p) {
        if ((int)$p['id'] === $planId) {
            $context = $p['syllabus_input'] ?: $p['plan_data'];
            break;
        }
    }
}
if ($prefillTopic !== '' && $context !== '' && !str_contains((string)$context, $prefillTopic)) {
    $context = $prefillTopic . "\n\n" . $context;
} elseif ($prefillTopic !== '' && $context === '') {
    $context = $prefillTopic;
}

$defaultBloom = QuestionBankTools::defaultBloomPct();
$showPaperTab = $tab === 'paper';

render_header('Question Bank Generator', 'questions', ['subtitle' => 'MCQ · Short · Long · By K-level']);
?>
<div class="panel" data-tabs>
  <div class="tabs">
    <button type="button" class="tab <?= !$showPaperTab ? 'active' : '' ?>" data-tab="gen">Generate</button>
    <button type="button" class="tab" data-tab="banks">My banks</button>
    <button type="button" class="tab <?= $showPaperTab ? 'active' : '' ?>" data-tab="paper">Build paper / Sets</button>
  </div>
  <div data-pane="gen" <?= $showPaperTab ? 'hidden' : '' ?>>
    <form class="form-grid" method="post" action="<?= e(base_url('/api/ai?module=questions')) ?>" data-ai-form="#qOut">
      <?= csrf_field() ?>
      <input type="hidden" name="module" value="questions">
      <div class="form-row two">
        <div>
          <label>Type</label>
          <select name="question_type">
            <option value="mcq">MCQ</option>
            <option value="short">Short answer</option>
            <option value="long">Long answer</option>
            <option value="essay">Essay</option>
          </select>
        </div>
        <div>
          <label>Bloom K-level</label>
          <select name="klevel">
            <?php foreach (['K1','K2','K3','K4','K5','K6'] as $k): ?>
              <option <?= $prefillK === $k ? 'selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row two">
        <div><label>Unit</label><input name="unit" value="<?= (int)$prefillUnit ?>"></div>
        <div><label>Count</label><input name="count" type="number" value="5" min="1" max="20"></div>
      </div>
      <div class="form-row">
        <label>Course plan (optional)</label>
        <select name="plan_id">
          <option value="">-</option>
          <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $planId===(int)$p['id']?'selected':'' ?>><?= e(trim((string)(($p['subject_name'] ?? '') !== '' ? ($p['subject_name'] . ' · ' . $p['title']) : $p['title']))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>Context / syllabus excerpt</label>
        <textarea name="context"><?= e(is_string($context) ? $context : '') ?></textarea>
      </div>
      <button class="btn btn-accent" type="submit">Generate questions</button>
    </form>
    <div id="qOut" style="margin-top:1rem"></div>
  </div>
  <div data-pane="banks" hidden>
    <div class="table-wrap"><table>
      <thead><tr><th>Title</th><th>Created</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($banks as $b): ?>
        <tr>
          <td><?= e($b['title']) ?></td>
          <td><?= e($b['created_at']) ?></td>
          <td><a class="btn btn-sm btn-ghost" href="?bank_id=<?= (int)$b['id'] ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div data-pane="paper" <?= $showPaperTab ? '' : 'hidden' ?>>
    <p style="color:var(--muted);font-size:.9rem;margin-top:0">Uses questions from your personal banks only. Distribution is enforced — shortages are reported instead of silently ignored.</p>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <div class="form-row"><label>Paper title</label><input name="paper_title" value="CIA / End Semester Paper" required></div>
      <div class="form-row"><label>Total marks</label><input type="number" name="total_marks" value="50" min="1" max="200" required></div>
      <h3 style="margin:.4rem 0 0">Marks distribution (parts)</h3>
      <div class="form-row two">
        <div><label>Part A — count</label><input type="number" name="part_count[]" value="10" min="0"></div>
        <div><label>marks each</label><input type="number" name="part_marks[]" value="2" min="0" step="0.5"></div>
      </div>
      <div class="form-row two">
        <div><label>Part B — count</label><input type="number" name="part_count[]" value="4" min="0"></div>
        <div><label>marks each</label><input type="number" name="part_marks[]" value="5" min="0" step="0.5"></div>
      </div>
      <div class="form-row two">
        <div><label>Part C — count</label><input type="number" name="part_count[]" value="1" min="0"></div>
        <div><label>marks each</label><input type="number" name="part_marks[]" value="10" min="0" step="0.5"></div>
      </div>
      <h3 style="margin:.4rem 0 0">Bloom distribution (%)</h3>
      <div class="form-row two">
        <?php foreach ($defaultBloom as $k => $pct): ?>
          <div><label><?= e($k) ?></label><input type="number" name="bloom_<?= e($k) ?>" value="<?= (float)$pct ?>" min="0" max="100" step="1"></div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:.5rem">
        <button class="btn btn-accent" type="submit" name="action" value="build_paper">Build Question Paper</button>
        <button class="btn btn-primary" type="submit" name="action" value="generate_sets">Generate Sets A / B / C</button>
      </div>
    </form>
    <?php if ($papers): ?>
      <h3 style="margin-top:1.2rem">Recent papers</h3>
      <div class="table-wrap"><table>
        <thead><tr><th>Title</th><th>Marks</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($papers as $p): ?>
          <tr>
            <td><?= e($p['title']) ?></td>
            <td><?= e((string)$p['total_marks']) ?></td>
            <td><?= e($p['created_at']) ?></td>
            <td><a class="btn btn-sm btn-ghost" href="<?= e(base_url('/professor/question-paper.php?id='.(int)$p['id'])) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
    <p style="margin-top:1rem;font-size:.85rem;color:var(--muted)">Pool size: <?= count($allQs) ?> question(s) in your banks.</p>
  </div>
</div>

<?php if ($questions && $bank): ?>
<div class="panel" style="margin-top:1rem">
  <div class="panel-h">
    <h2><?= e($bank['title']) ?></h2>
    <a class="btn btn-sm btn-ghost" href="?tab=paper">Build Question Paper</a>
  </div>
  <?php foreach ($questions as $i => $q):
    $meta = json_decode((string)($q['meta'] ?? '{}'), true) ?: [];
    $sim = $meta['similarity'] ?? null;
    $reviewed = !empty($meta['duplicate_reviewed']);
    $clo = (string)($q['clo_code'] ?? $meta['clo_code'] ?? '');
    $scheme = (string)($q['marking_scheme'] ?? '');
    if ($scheme === '') {
        $scheme = QuestionBankTools::buildMarkingScheme($q);
    }
    $analysis = QuestionBankTools::itemAnalysis($user, (int)$q['id']);
  ?>
    <div style="padding:1rem 0;border-bottom:1px solid var(--line)">
      <div class="chip-row" style="margin-bottom:.4rem">
        <span class="chip">Q<?= $i + 1 ?></span>
        <span class="chip"><?= e($q['question_type']) ?></span>
        <span class="chip">Bloom <?= e((string)$q['bloom_k_level']) ?></span>
        <?php if ($clo !== ''): ?><span class="chip"><?= e($clo) ?></span><?php endif; ?>
        <span class="chip">Unit <?= e((string)($q['unit_number'] ?? '—')) ?></span>
        <span class="chip"><?= e((string)$q['marks']) ?> marks</span>
      </div>
      <strong><?= e($q['stem']) ?></strong>
      <?php if ($q['options']): $opts=json_decode($q['options'],true); ?>
        <ul><?php foreach ((array)$opts as $k=>$v): ?><li><strong><?= e((string)$k) ?>.</strong> <?= e(is_string($v)?$v:json_encode($v)) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <div style="color:var(--muted);font-size:.85rem;margin-top:.35rem">Answer: <?= e((string)$q['correct_answer']) ?></div>

      <?php if (is_array($sim) && !$reviewed): ?>
        <div class="alert alert-warn" style="margin-top:.75rem">
          <strong>Similar question found</strong>
          <div style="margin:.35rem 0;font-size:.9rem">Existing: <?= e((string)($sim['existing_stem'] ?? '')) ?></div>
          <div style="font-size:.85rem">Similarity: <?= e((string)($sim['level'] ?? '')) ?> (<?= e((string)($sim['score'] ?? '')) ?>%)</div>
          <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.55rem">
            <form method="post"><?= csrf_field() ?>
              <input type="hidden" name="action" value="keep_question">
              <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
              <button class="btn btn-sm btn-primary" type="submit">Keep</button>
            </form>
            <form method="post"><?= csrf_field() ?>
              <input type="hidden" name="action" value="regenerate_question">
              <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
              <button class="btn btn-sm btn-ghost" type="submit">Regenerate</button>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <details style="margin-top:.55rem">
        <summary style="cursor:pointer">Marking scheme / CLO / Item analysis</summary>
        <pre class="panel" style="white-space:pre-wrap;margin:.5rem 0;font-size:.85rem"><?= e($scheme) ?></pre>
        <form method="post" class="form-grid" style="max-width:280px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_clo">
          <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
          <div class="form-row"><label>CLO (editable)</label>
            <input name="clo_code" value="<?= e($clo) ?>" placeholder="CLO2" maxlength="10">
          </div>
          <button class="btn btn-sm btn-ghost" type="submit">Save CLO</button>
        </form>
        <div style="margin-top:.65rem;font-size:.9rem">
          <?php if (!$analysis['available']): ?>
            <div class="empty" style="padding:.6rem">Item analysis unavailable.<br>Reason: <?= e((string)($analysis['reason'] ?? 'Not enough student responses.')) ?>
              <?php if (isset($analysis['attempts'])): ?><br>Attempts recorded: <?= (int)$analysis['attempts'] ?><?php endif; ?>
            </div>
          <?php else: ?>
            <div>Attempts: <?= (int)$analysis['attempts'] ?> · Correct: <?= (int)$analysis['correct'] ?></div>
            <div>Difficulty Index: <?= e((string)$analysis['difficulty_pct']) ?>% (<?= e((string)$analysis['difficulty_label']) ?>)</div>
            <?php if ($analysis['discrimination'] !== null): ?>
              <div>Discrimination Index: <?= e((string)$analysis['discrimination']) ?> (<?= e((string)$analysis['discrimination_label']) ?>)</div>
            <?php else: ?>
              <div>Discrimination: not enough scored responses.</div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </details>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php render_footer(); ?>
