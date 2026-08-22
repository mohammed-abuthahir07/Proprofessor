<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();

// Auto-generate alerts from plans
$plans = Database::fetchAll('SELECT * FROM course_plans WHERE institution_id=? AND department_id=?', [$user['institution_id'], $user['department_id']]);
foreach ($plans as $p) {
    $bloom = json_decode($p['bloom_data']?:'{}', true) ?: [];
    $higher = (float)($bloom['K4']??0)+(float)($bloom['K5']??0)+(float)($bloom['K6']??0);
    if ($higher < 30 && $p['status'] !== 'draft') {
        $exists = Database::fetch(
            'SELECT id FROM compliance_alerts WHERE plan_id=? AND alert_type="low_bloom" AND is_resolved=0',
            [$p['id']]
        );
        if (!$exists) {
            Database::insert('compliance_alerts', [
                'institution_id' => $user['institution_id'],
                'department_id' => $user['department_id'],
                'plan_id' => $p['id'],
                'alert_type' => 'low_bloom',
                'severity' => 'high',
                'message' => $p['subject_name'].': Low K4-K6 coverage ('.$higher.'%). NBA risk.',
            ]);
        }
    }
    if (($p['ai_score'] !== null && (float)$p['ai_score'] < 65)) {
        $exists = Database::fetch(
            'SELECT id FROM compliance_alerts WHERE plan_id=? AND alert_type="low_score" AND is_resolved=0',
            [$p['id']]
        );
        if (!$exists) {
            Database::insert('compliance_alerts', [
                'institution_id' => $user['institution_id'],
                'department_id' => $user['department_id'],
                'plan_id' => $p['id'],
                'alert_type' => 'low_score',
                'severity' => 'medium',
                'message' => $p['subject_name'].': AI quality score below 65.',
            ]);
        }
    }
}
$alerts = Database::fetchAll(
    'SELECT * FROM compliance_alerts WHERE department_id=? ORDER BY is_resolved, FIELD(severity,"high","medium","low"), id DESC',
    [$user['department_id']]
);
render_header('Compliance Alerts', 'compliance');
?>
<div class="panel">
<?php foreach ($alerts as $a): ?>
  <div class="alert alert-<?= $a['is_resolved']?'success':'warn' ?>">
    <strong><?= e(strtoupper($a['severity'])) ?></strong> · <?= e($a['message']) ?>
    <?php if ($a['plan_id']): ?> · <a href="<?= e(base_url('/hod/approvals.php?id='.$a['plan_id'])) ?>">Review</a><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (!$alerts): ?><div class="empty">No alerts.</div><?php endif; ?>
</div>
<?php render_footer(); ?>
