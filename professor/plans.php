<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
CoursePlanTools::ensureShareSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');

    if ($action === 'delete') {
        $id = (int)post('plan_id');
        $plan = Database::fetch(
            'SELECT id, title FROM course_plans WHERE id = ? AND professor_id = ? AND institution_id = ?',
            [$id, $user['id'], $user['institution_id']]
        );
        if (!$plan) {
            flash('error', 'Plan not found.');
            redirect('/professor/plans.php');
        }
        try {
            $pdo = Database::pdo();
            $pdo->beginTransaction();
            foreach ([
                'course_plan_versions',
                'plan_units',
                'plan_reviews',
                'lesson_plans',
                'documents',
                'presentations',
                'question_banks',
                'assignments',
                'compliance_alerts',
            ] as $table) {
                Database::query("DELETE FROM {$table} WHERE plan_id = ?", [$id]);
            }
            Database::query(
                'DELETE FROM course_plans WHERE id = ? AND professor_id = ? AND institution_id = ?',
                [$id, $user['id'], $user['institution_id']]
            );
            $pdo->commit();
            flash('success', 'Plan deleted: ' . (string)$plan['title']);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'Could not delete the plan. Please try again.');
        }
        redirect('/professor/plans.php');
    }

    if ($action === 'submit') {
        $id = (int)post('plan_id');
        Database::update('course_plans', [
            'status' => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
        ], 'id = :id AND professor_id = :pid AND institution_id = :iid', [
            'id' => $id,
            'pid' => $user['id'],
            'iid' => $user['institution_id'],
        ]);
        $hod = Database::fetch(
            'SELECT hod_user_id FROM departments WHERE id = ?',
            [$user['department_id']]
        );
        $hodUserId = (int)($hod['hod_user_id'] ?? 0);
        if ($hodUserId < 1 && !empty($user['department_id'])) {
            $fallbackHod = Database::fetch(
                'SELECT id FROM users WHERE department_id = ? AND role = "hod" AND is_active = 1 ORDER BY id ASC LIMIT 1',
                [$user['department_id']]
            );
            $hodUserId = (int)($fallbackHod['id'] ?? 0);
        }
        if ($hodUserId > 0) {
            notify_user($hodUserId, 'approval', 'Course plan submitted', 'A faculty plan awaits review.', '/hod/approvals.php');
        }
        flash('success', 'Plan submitted to HOD.');
        redirect('/professor/plans.php');
    }

    if ($action === 'share_enable') {
        $id = (int)post('plan_id');
        $plan = Database::fetch(
            'SELECT * FROM course_plans WHERE id = ? AND professor_id = ? AND institution_id = ?',
            [$id, $user['id'], $user['institution_id']]
        );
        try {
            if (!$plan) {
                throw new RuntimeException('Plan not found.');
            }
            $link = CoursePlanTools::enableShareLink($plan, $user);
            flash('success', 'Read-only share link ready: ' . $link['url']);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/professor/plans.php?' . http_build_query(array_filter([
            'status' => (string)post('filter_status', ''),
            'subject' => (string)post('filter_subject', ''),
            'semester' => (string)post('filter_semester', ''),
            'q' => (string)post('filter_q', ''),
        ], static fn($v) => $v !== '' && $v !== null)));
    }

    if ($action === 'share_disable') {
        $id = (int)post('plan_id');
        $plan = Database::fetch(
            'SELECT * FROM course_plans WHERE id = ? AND professor_id = ? AND institution_id = ?',
            [$id, $user['id'], $user['institution_id']]
        );
        try {
            if (!$plan) {
                throw new RuntimeException('Plan not found.');
            }
            CoursePlanTools::disableShareLink($plan, $user);
            flash('success', 'Share link revoked.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/professor/plans.php');
    }

    if ($action === 'bulk_export') {
        $ids = post('plan_ids');
        if (!is_array($ids)) {
            $ids = [];
        }
        $format = strtolower((string)post('export_format', 'naac')) === 'nba' ? 'nba' : 'naac';
        $plans = CoursePlanTools::ownedApprovedPlans($user, $ids);
        if (!$plans) {
            flash('error', 'Select one or more approved plans to export.');
            redirect('/professor/plans.php');
        }
        try {
            $pkg = CoursePlanTools::buildAccreditationPackage($plans, $format);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/professor/plans.php');
        }
        header('Content-Type: ' . $pkg['content_type']);
        header('Content-Disposition: attachment; filename="' . $pkg['filename'] . '"');
        header('Content-Length: ' . strlen($pkg['bytes']));
        echo $pkg['bytes'];
        exit;
    }
}

// —— Filters (GET). Empty = show all (existing behaviour). ——
$filterStatus = strtolower(trim((string)get('status', '')));
if ($filterStatus === 'rejected') {
    $filterStatus = 'returned'; // existing workflow uses "returned"
}
$filterSubject = trim((string)get('subject', ''));
$filterSemester = trim((string)get('semester', ''));
$filterQ = trim((string)get('q', ''));

$sql = 'SELECT p.*,
               c.year AS class_year, c.semester AS class_semester,
               c.academic_year AS class_ay, c.name AS class_name, c.section AS class_section
        FROM course_plans p
        LEFT JOIN classes c ON c.id = p.class_id
        WHERE p.professor_id = ? AND p.institution_id = ?';
$params = [(int)$user['id'], (int)$user['institution_id']];

if ($filterStatus !== '' && $filterStatus !== 'all') {
    $allowedStatuses = ['draft', 'submitted', 'under_review', 'approved', 'returned'];
    if (in_array($filterStatus, $allowedStatuses, true)) {
        $sql .= ' AND p.status = ?';
        $params[] = $filterStatus;
    }
}
if ($filterSubject !== '') {
    $sql .= ' AND p.subject_name = ?';
    $params[] = $filterSubject;
}
if ($filterSemester !== '') {
    $sql .= ' AND (
        p.semester = ?
        OR c.semester = ?
        OR c.academic_year = ?
        OR p.academic_year = ?
        OR CONCAT("Year ", c.year) = ?
        OR CAST(c.year AS CHAR) = ?
    )';
    $params[] = $filterSemester;
    $params[] = $filterSemester;
    $params[] = $filterSemester;
    $params[] = $filterSemester;
    $params[] = $filterSemester;
    $yearNum = preg_replace('/\D+/', '', $filterSemester) ?: $filterSemester;
    $params[] = $yearNum;
}
if ($filterQ !== '') {
    $sql .= ' AND (p.title LIKE ? OR p.subject_name LIKE ? OR p.university LIKE ?)';
    $like = '%' . $filterQ . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY p.updated_at DESC';
$plans = Database::fetchAll($sql, $params);

// Version counts for Compare action (tenant-scoped via plan ids already filtered)
$versionCounts = [];
if ($plans) {
    $ids = array_map(static fn($p) => (int)$p['id'], $plans);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = Database::fetchAll(
        "SELECT plan_id, COUNT(*) AS c FROM course_plan_versions WHERE plan_id IN ($in) GROUP BY plan_id",
        $ids
    );
    foreach ($rows as $r) {
        $versionCounts[(int)$r['plan_id']] = (int)$r['c'];
    }
}

// Filter option lists from professor's own plans (not just current page)
$allMine = Database::fetchAll(
    'SELECT p.subject_name, p.semester, p.academic_year, p.status,
            c.year AS class_year, c.semester AS class_semester, c.academic_year AS class_ay
     FROM course_plans p
     LEFT JOIN classes c ON c.id = p.class_id
     WHERE p.professor_id = ? AND p.institution_id = ?',
    [(int)$user['id'], (int)$user['institution_id']]
);
$subjectOptions = [];
$semesterOptions = [];
foreach ($allMine as $row) {
    $sn = trim((string)($row['subject_name'] ?? ''));
    if ($sn !== '') {
        $subjectOptions[$sn] = true;
    }
    $label = CoursePlanTools::planSemesterLabel($row);
    if ($label !== '') {
        $semesterOptions[$label] = true;
    }
}
$subjectOptions = array_keys($subjectOptions);
sort($subjectOptions);
$semesterOptions = array_keys($semesterOptions);
sort($semesterOptions);

$statusSelect = (string)get('status', '');
if ($statusSelect === '' && $filterStatus !== '' && $filterStatus !== 'all') {
    $statusSelect = $filterStatus === 'returned' ? 'rejected' : $filterStatus;
}

$queryKeep = [];
if ($statusSelect !== '') {
    $queryKeep['status'] = $statusSelect;
}
if ($filterSubject !== '') {
    $queryKeep['subject'] = $filterSubject;
}
if ($filterSemester !== '') {
    $queryKeep['semester'] = $filterSemester;
}
if ($filterQ !== '') {
    $queryKeep['q'] = $filterQ;
}

render_header('My Course Plans', 'plans', ['subtitle' => 'Draft → Submitted → Approved']);
?>
<div class="panel" style="margin-bottom:1rem">
  <form method="get" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.65rem;align-items:end">
    <div class="form-row">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <?php
        $statusChoices = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'under_review' => 'Under review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
        foreach ($statusChoices as $val => $label):
        ?>
          <option value="<?= e($val) ?>" <?= $statusSelect === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label>Subject</label>
      <select name="subject">
        <option value="">All subjects</option>
        <?php foreach ($subjectOptions as $sn): ?>
          <option value="<?= e($sn) ?>" <?= $filterSubject === $sn ? 'selected' : '' ?>><?= e($sn) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label>Semester</label>
      <select name="semester">
        <option value="">All</option>
        <?php foreach ($semesterOptions as $sem): ?>
          <option value="<?= e($sem) ?>" <?= $filterSemester === $sem ? 'selected' : '' ?>><?= e($sem) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label>Search</label>
      <input type="search" name="q" value="<?= e($filterQ) ?>" placeholder="Title or subject">
    </div>
    <div class="form-row" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit">Filter</button>
      <a class="btn btn-ghost" href="<?= e(base_url('/professor/plans.php')) ?>">Reset</a>
    </div>
  </form>
</div>

<div class="panel">
  <div class="panel-h">
    <h2>All plans</h2>
    <a class="btn btn-primary btn-sm" href="<?= e(base_url('/professor/generate-plan.php')) ?>">+ New</a>
  </div>
  <?php if (!$plans): ?>
    <div class="empty"><?= $allMine ? 'No plans match these filters.' : 'No course plans yet.' ?></div>
  <?php else: ?>
  <form method="post" id="bulkExportForm" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-bottom:.75rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_export">
    <select name="export_format" style="max-width:140px">
      <option value="naac">NAAC</option>
      <option value="nba">NBA</option>
    </select>
    <button class="btn btn-sm btn-accent" type="submit">Export Selected</button>
    <span style="font-size:.85rem;color:var(--muted)">Approved plans only · downloads a styled PDF</span>
  </form>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th style="width:2.2rem" title="Select approved for export"></th>
        <th>Title</th>
        <th>Subject</th>
        <th>Semester</th>
        <th>Version</th>
        <th>Status</th>
        <th>AI</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($plans as $p):
      $pid = (int)$p['id'];
      $isApproved = (string)$p['status'] === 'approved';
      $vc = $versionCounts[$pid] ?? (int)$p['version'];
      $semLabel = CoursePlanTools::planSemesterLabel($p);
      $shareOn = !empty($p['share_enabled']) && !empty($p['share_token']);
    ?>
      <tr>
        <td>
          <?php if ($isApproved): ?>
            <input type="checkbox" name="plan_ids[]" value="<?= $pid ?>" form="bulkExportForm" aria-label="Select <?= e($p['subject_name']) ?>">
          <?php else: ?>
            <input type="checkbox" disabled title="Only approved plans can be exported" aria-label="Not exportable">
          <?php endif; ?>
        </td>
        <td><a href="<?= e(base_url('/professor/plan-view.php?id=' . $pid)) ?>"><?= e($p['title']) ?></a></td>
        <td><?= e($p['subject_name']) ?></td>
        <td><?= e($semLabel !== '' ? $semLabel : '—') ?></td>
        <td>v<?= (int)$p['version'] ?></td>
        <td><?= status_badge($p['status']) ?></td>
        <td><?= e((string)($p['ai_score'] ?? '-')) ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/professor/plan-view.php?id=' . $pid)) ?>">Open</a>
          <?php if ($vc >= 2): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/professor/plan-compare.php?id=' . $pid)) ?>">Compare Versions</a>
          <?php endif; ?>
          <?php if ($isApproved): ?>
            <?php if ($shareOn): ?>
              <button class="btn btn-sm btn-ghost" type="button" data-copy="<?= e(base_url('/share/plan.php?t=' . urlencode((string)$p['share_token']))) ?>">Copy Link</button>
              <form method="post" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="action" value="share_disable">
                <input type="hidden" name="plan_id" value="<?= $pid ?>">
                <button class="btn btn-sm btn-ghost" type="submit">Revoke Share</button>
              </form>
            <?php else: ?>
              <form method="post" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="action" value="share_enable">
                <input type="hidden" name="plan_id" value="<?= $pid ?>">
                <?php foreach ($queryKeep as $qk => $qv): ?>
                  <input type="hidden" name="filter_<?= e($qk) ?>" value="<?= e((string)$qv) ?>">
                <?php endforeach; ?>
                <button class="btn btn-sm btn-ghost" type="submit">Share</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (in_array($p['status'], ['draft', 'returned', 'under_review'], true)): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="plan_id" value="<?= $pid ?>">
            <button class="btn btn-sm btn-primary" type="submit">Submit</button>
          </form>
          <?php endif; ?>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="plan_id" value="<?= $pid ?>">
            <button class="btn btn-sm btn-ghost" type="submit" onclick="return confirm('Delete this course plan permanently?');">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<script>
(function () {
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-copy') || '';
      if (!url) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () {
          alert('Share link copied:\n' + url);
        }).catch(function () {
          prompt('Copy this read-only link:', url);
        });
      } else {
        prompt('Copy this read-only link:', url);
      }
    });
  });
})();
</script>
<?php render_footer(); ?>
