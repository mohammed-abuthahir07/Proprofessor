<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
QuestionBankTools::ensureSchema();

$id = (int)get('id');
$paper = QuestionBankTools::ownedPaper($user, $id);
if (!$paper) {
    flash('error', 'Paper not found.');
    redirect('/professor/questions.php?tab=paper');
}

$config = json_decode((string)($paper['config'] ?? '{}'), true) ?: [];
$sets = json_decode((string)($paper['sets_data'] ?? '{}'), true) ?: [];
$keys = json_decode((string)($paper['answer_key'] ?? '{}'), true) ?: [];
$viewSet = strtoupper((string)get('set', array_key_first($sets) ?: 'A'));
if (!isset($sets[$viewSet])) {
    $viewSet = (string)(array_key_first($sets) ?: 'A');
}

$ids = array_map('intval', $sets[$viewSet] ?? []);
$questions = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = Database::fetchAll(
        "SELECT q.* FROM questions q
         JOIN question_banks qb ON qb.id = q.bank_id
         JOIN users u ON u.id = qb.professor_id
         WHERE q.id IN ($in) AND qb.professor_id = ? AND u.institution_id = ?",
        array_merge($ids, [(int)$user['id'], (int)$user['institution_id']])
    );
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int)$r['id']] = $r;
    }
    foreach ($ids as $qid) {
        if (isset($byId[$qid])) {
            $questions[] = $byId[$qid];
        }
    }
}

$answerKey = $keys[$viewSet] ?? QuestionBankTools::buildAnswerKey($questions);
$showKey = (string)get('view', '') === 'key';

render_header($paper['title'], 'questions', [
    'subtitle' => 'Total ' . (string)$paper['total_marks'] . ' marks · Set ' . $viewSet,
]);
?>
<div class="panel">
  <div class="panel-h">
    <h2><?= e($paper['title']) ?></h2>
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/professor/questions.php?tab=paper')) ?>">← Question Bank</a>
  </div>
  <?php if (count($sets) > 1): ?>
    <div class="chip-row" style="margin-bottom:.8rem">
      <?php foreach (array_keys($sets) as $label): ?>
        <a class="chip<?= $viewSet === $label ? ' active' : '' ?>" href="?id=<?= $id ?>&set=<?= e($label) ?><?= $showKey ? '&view=key' : '' ?>">Set <?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div style="display:flex;flex-wrap:wrap;gap:.45rem;margin-bottom:1rem">
    <a class="btn btn-sm <?= !$showKey ? 'btn-primary' : 'btn-ghost' ?>" href="?id=<?= $id ?>&set=<?= e($viewSet) ?>">Question paper</a>
    <a class="btn btn-sm <?= $showKey ? 'btn-primary' : 'btn-ghost' ?>" href="?id=<?= $id ?>&set=<?= e($viewSet) ?>&view=key">Answer key + Marking scheme</a>
  </div>
  <?php if (!empty($config['note'])): ?>
    <div class="alert alert-warn"><?= e((string)$config['note']) ?></div>
  <?php endif; ?>

  <?php if ($showKey): ?>
    <h3>Answer key — Set <?= e($viewSet) ?></h3>
    <div class="table-wrap"><table>
      <thead><tr><th>Q</th><th>Type</th><th>Answer</th><th>Marks</th></tr></thead>
      <tbody>
      <?php foreach ($answerKey as $row): ?>
        <tr>
          <td>Q<?= (int)$row['q'] ?></td>
          <td><?= e((string)$row['type']) ?></td>
          <td><?= e((string)$row['answer']) ?></td>
          <td><?= e((string)$row['marks']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <h3 style="margin-top:1.2rem">Marking schemes</h3>
    <?php foreach ($answerKey as $row): ?>
      <div style="padding:.8rem 0;border-bottom:1px solid var(--line)">
        <strong>Q<?= (int)$row['q'] ?></strong>
        <pre style="white-space:pre-wrap;margin:.4rem 0 0;font-size:.85rem;color:var(--muted)"><?= e((string)($row['marking_scheme'] ?? '')) ?></pre>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <?php
      // Group into parts by marks sequence for display
      $n = 1;
      foreach ($questions as $q):
    ?>
      <div style="padding:1rem 0;border-bottom:1px solid var(--line)">
        <div class="chip-row" style="margin-bottom:.35rem">
          <span class="chip">Q<?= $n ?></span>
          <span class="chip"><?= e((string)$q['question_type']) ?></span>
          <span class="chip"><?= e((string)$q['bloom_k_level']) ?></span>
          <?php if (!empty($q['clo_code'])): ?><span class="chip"><?= e((string)$q['clo_code']) ?></span><?php endif; ?>
          <span class="chip"><?= e((string)$q['marks']) ?> marks</span>
        </div>
        <strong><?= e($q['stem']) ?></strong>
        <?php if ($q['options']): $opts = json_decode((string)$q['options'], true); ?>
          <ul><?php foreach ((array)$opts as $k => $v): ?>
            <li><strong><?= e((string)$k) ?>.</strong> <?= e(is_string($v) ? $v : json_encode($v)) ?></li>
          <?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
    <?php
      $n++;
      endforeach;
    ?>
    <?php if (!$questions): ?>
      <div class="empty">No questions could be loaded for this set.</div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
