<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
$planId = (int)get('plan_id');
$prefillTitle = trim((string)get('title', ''));
$prefillContext = trim((string)get('context', ''));
$plans = Database::fetchAll('SELECT id, title, subject_name, syllabus_input FROM course_plans WHERE professor_id=? AND institution_id=?', [$user['id'], $user['institution_id']]);
$ppts = Database::fetchAll('SELECT id, title, slide_count, status, created_at FROM presentations WHERE professor_id=? ORDER BY id DESC', [$user['id']]);
$ctx = $prefillContext;
foreach ($plans as $p) if ($planId && (int)$p['id']===$planId && $ctx === '') $ctx = (string)$p['syllabus_input'];
render_header('PPT Generator', 'ppt', ['subtitle' => 'AI lecture decks with speaker notes']);
?>
<div class="grid grid-2">
  <div class="panel">
    <form class="form-grid" method="post" action="<?= e(base_url('/api/ai?module=ppt')) ?>" data-ai-form="#out">
      <?= csrf_field() ?>
      <input type="hidden" name="module" value="ppt">
      <div class="form-row"><label>Title</label><input name="title" required placeholder="Programming in C · Unit 1" value="<?= e($prefillTitle) ?>"></div>
      <div class="form-row">
        <label>From plan</label>
        <select name="plan_id">
          <option value="">-</option>
          <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $planId===(int)$p['id']?'selected':'' ?>><?= e(trim((string)(($p['subject_name'] ?? '') !== '' ? ($p['subject_name'] . ' · ' . $p['title']) : $p['title']))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>Context</label><textarea name="context"><?= e($ctx) ?></textarea></div>
      <button class="btn btn-accent" type="submit">Generate PPT</button>
    </form>
    <div id="out"></div>
  </div>
  <div class="panel">
    <h3>Generated decks</h3>
    <div class="table-wrap"><table>
      <thead><tr><th>Title</th><th>Slides</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($ppts as $p): ?>
        <tr>
          <td><?= e($p['title']) ?></td>
          <td><?= (int)$p['slide_count'] ?></td>
          <td class="ppt-row-actions">
            <a class="btn btn-sm btn-primary" href="<?= e(base_url('/professor/ppt-view.php?id='.$p['id'])) ?>">Open</a>
            <a class="btn btn-sm btn-accent" href="<?= e(base_url('/professor/ppt-download.php?id='.$p['id'])) ?>"><?= icon('download') ?> Save</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php render_footer(); ?>
