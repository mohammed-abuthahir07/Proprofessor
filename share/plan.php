<?php
declare(strict_types=1);
/**
 * Public read-only course plan view (tokenized).
 * No login, no professor dashboard, no other modules.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$token = (string)($_GET['t'] ?? '');
$plan = CoursePlanTools::findPublicSharedPlan($token);
if (!$plan) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not found</title>';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(base_url('/assets/css/app.css'), ENT_QUOTES, 'UTF-8') . '">';
    echo '</head><body class="app-body" style="padding:2rem"><div class="panel"><div class="empty">This share link is invalid, revoked, or the plan is no longer approved.</div></div></body></html>';
    exit;
}

$units = Database::fetchAll(
    'SELECT * FROM plan_units WHERE plan_id = ? ORDER BY sort_order, unit_number',
    [(int)$plan['id']]
);
$bloom = CoursePlanTools::bloomBalance($plan, $units);
$planData = json_decode((string)($plan['plan_data'] ?? '{}'), true) ?: [];
$outcomes = is_array($planData['learning_outcomes'] ?? null) ? $planData['learning_outcomes'] : [];
$inst = Database::fetch('SELECT name FROM institutions WHERE id = ?', [(int)$plan['institution_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e((string)$plan['title']) ?> · Read-only</title>
  <link rel="stylesheet" href="<?= e(base_url('/assets/css/app.css')) ?>">
</head>
<body class="app-body" style="padding:1.25rem;max-width:960px;margin:0 auto">
  <div class="panel">
    <p class="chip" style="display:inline-block;margin:0 0 .75rem">Read-only shared course plan</p>
    <h1 style="margin:.2rem 0"><?= e((string)$plan['title']) ?></h1>
    <p style="color:var(--muted);margin:0">
      <?= e((string)$plan['subject_name']) ?>
      · v<?= (int)$plan['version'] ?>
      · <?= e((string)($inst['name'] ?? '')) ?>
      · <?= status_badge((string)$plan['status']) ?>
    </p>
    <p style="font-size:.85rem;color:var(--muted);margin:.6rem 0 0">
      This page shows only this course plan. Editing and other modules are not available here.
    </p>
  </div>

  <?php if ($outcomes): ?>
  <div class="panel">
    <h2>Learning outcomes</h2>
    <ol>
      <?php foreach ($outcomes as $lo): ?>
        <li><?= e(is_string($lo) ? $lo : (string)json_encode($lo)) ?></li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2>Units</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>Title</th><th>Hours</th><th>Bloom</th><th>Topics</th><th>Outcomes</th></tr></thead>
      <tbody>
      <?php foreach ($units as $u):
        $topics = json_decode((string)($u['topics'] ?? '[]'), true);
        $uo = json_decode((string)($u['outcomes'] ?? '[]'), true);
      ?>
        <tr>
          <td><?= (int)$u['unit_number'] ?></td>
          <td><?= e((string)$u['title']) ?></td>
          <td><?= e((string)$u['hours']) ?></td>
          <td><?= e((string)($u['bloom_k_level'] ?? '')) ?></td>
          <td><?= e(is_array($topics) ? implode(', ', $topics) : '') ?></td>
          <td><?= e(is_array($uo) ? implode('; ', $uo) : '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="panel">
    <h2>Bloom distribution</h2>
    <div class="table-wrap"><table>
      <tr>
        <?php foreach ($bloom['distribution'] as $k => $v): ?>
          <th><?= e((string)$k) ?></th>
        <?php endforeach; ?>
      </tr>
      <tr>
        <?php foreach ($bloom['distribution'] as $v): ?>
          <td><?= e((string)$v) ?>%</td>
        <?php endforeach; ?>
      </tr>
    </table></div>
  </div>
</body>
</html>
