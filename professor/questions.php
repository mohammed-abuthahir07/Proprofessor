<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
$bankId = (int)get('bank_id');
$planId = (int)get('plan_id');
$prefillUnit = max(1, min(20, (int)get('unit', 1)));
$prefillK = strtoupper(trim((string)get('klevel', 'K2')));
if (!preg_match('/^K[1-6]$/', $prefillK)) {
    $prefillK = 'K2';
}
$prefillTopic = trim((string)get('topic', ''));
$prefillContext = trim((string)get('context', ''));
$plans = Database::fetchAll('SELECT id, title, subject_name, syllabus_input, plan_data FROM course_plans WHERE professor_id=? AND institution_id=?', [$user['id'], $user['institution_id']]);
$banks = Database::fetchAll('SELECT * FROM question_banks WHERE professor_id=? ORDER BY id DESC LIMIT 20', [$user['id']]);
$bank = $bankId ? Database::fetch('SELECT * FROM question_banks WHERE id=? AND professor_id=?', [$bankId, $user['id']]) : null;
$questions = $bank ? Database::fetchAll('SELECT * FROM questions WHERE bank_id=?', [$bank['id']]) : [];
$context = $prefillContext;
if ($context === '' && $planId) {
    foreach ($plans as $p) if ((int)$p['id']===$planId) { $context = $p['syllabus_input'] ?: $p['plan_data']; break; }
}
if ($prefillTopic !== '' && $context !== '' && !str_contains($context, $prefillTopic)) {
    $context = $prefillTopic . "\n\n" . $context;
} elseif ($prefillTopic !== '' && $context === '') {
    $context = $prefillTopic;
}
render_header('Question Bank Generator', 'questions', ['subtitle' => 'MCQ · Short · Long · By K-level']);
?>
<div class="panel" data-tabs>
  <div class="tabs">
    <button type="button" class="tab active" data-tab="gen">Generate</button>
    <button type="button" class="tab" data-tab="banks">My banks</button>
  </div>
  <div data-pane="gen">
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
</div>
<?php if ($questions): ?>
<div class="panel" style="margin-top:1rem">
  <h2>Questions</h2>
  <?php foreach ($questions as $q): ?>
    <div style="padding:1rem 0;border-bottom:1px solid var(--line)">
      <div class="chip-row" style="margin-bottom:.4rem">
        <span class="chip"><?= e($q['question_type']) ?></span>
        <span class="chip"><?= e((string)$q['bloom_k_level']) ?></span>
        <span class="chip"><?= e((string)$q['marks']) ?> marks</span>
      </div>
      <strong><?= e($q['stem']) ?></strong>
      <?php if ($q['options']): $opts=json_decode($q['options'],true); ?>
        <ul><?php foreach ((array)$opts as $k=>$v): ?><li><strong><?= e((string)$k) ?>.</strong> <?= e(is_string($v)?$v:json_encode($v)) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <div style="color:var(--muted);font-size:.85rem">Answer: <?= e((string)$q['correct_answer']) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php render_footer(); ?>
