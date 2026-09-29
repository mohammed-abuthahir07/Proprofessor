<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod');
$user = Auth::user();
$deptId = (int)($user['department_id'] ?? 0);
$instId = (int)$user['institution_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'announce') {
    verify_csrf();
    $title = trim((string)post('title'));
    $body = trim((string)post('body'));
    if ($title === '' || $body === '') {
        flash('error', 'Title and message are required.');
        redirect('/hod/faculty.php');
    }
    if ($deptId < 1) {
        flash('error', 'Your HOD account is not linked to a department.');
        redirect('/hod/faculty.php');
    }
    Database::insert('announcements', [
        'institution_id' => $instId,
        'department_id' => $deptId ?: null,
        'created_by' => (int)$user['id'],
        'title' => $title,
        'body' => $body,
        'announcement_type' => in_array((string)post('announcement_type', 'circular'), ['circular', 'deadline', 'exam', 'general'], true)
            ? (string)post('announcement_type', 'circular')
            : 'circular',
    ]);
    $profs = Database::fetchAll(
        'SELECT id FROM users WHERE institution_id=? AND role="professor" AND is_active=1'
        . ($deptId ? ' AND department_id=?' : ''),
        $deptId ? [$instId, $deptId] : [$instId]
    );
    foreach ($profs as $p) {
        notify_user((int)$p['id'], 'system', $title, $body, '/professor/notifications.php', [
            'priority' => 'medium',
            'category' => 'system',
            'action' => ['type' => 'OPEN_NOTIFICATIONS'],
        ]);
    }
    flash('success', 'Circular sent to department faculty.');
    redirect('/hod/faculty.php');
}

$facultySql = 'SELECT u.id, u.full_name, u.email, u.designation,
            SUM(p.status="approved") approved,
            SUM(p.status IN ("submitted","under_review")) pending,
            COUNT(p.id) total,
            AVG(p.ai_score) avg_score
     FROM users u
     LEFT JOIN course_plans p ON p.professor_id=u.id
     WHERE u.institution_id=? AND u.role="professor" AND u.is_active=1';
$facultyParams = [$instId];
if ($deptId) {
    $facultySql .= ' AND u.department_id=?';
    $facultyParams[] = $deptId;
}
$facultySql .= ' GROUP BY u.id ORDER BY u.full_name';
$faculty = Database::fetchAll($facultySql, $facultyParams);

$dept = $deptId > 0
    ? Database::fetch('SELECT name, code FROM departments WHERE id = ? AND institution_id = ?', [$deptId, $instId])
    : null;
$inst = Database::fetch('SELECT academic_year, current_semester FROM institutions WHERE id = ?', [$instId]) ?: [];

$subjectMap = [];
if ($deptId > 0) {
    $subjectRows = Database::fetchAll(
        'SELECT sa.professor_id, s.name AS subject_name
         FROM subject_assignments sa
         JOIN subjects s ON s.id = sa.subject_id
         JOIN classes c ON c.id = sa.class_id
         WHERE c.institution_id = ? AND c.department_id = ? AND s.institution_id = ?
         ORDER BY s.name',
        [$instId, $deptId, $instId]
    );
    foreach ($subjectRows as $row) {
        $name = trim((string)($row['subject_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $pid = (int)$row['professor_id'];
        if (!in_array($name, $subjectMap[$pid] ?? [], true)) {
            $subjectMap[$pid][] = $name;
        }
    }
}

$bloomMap = [];
$bloomSql = 'SELECT professor_id, bloom_data FROM course_plans WHERE institution_id = ?';
$bloomParams = [$instId];
if ($deptId > 0) {
    $bloomSql .= ' AND department_id = ?';
    $bloomParams[] = $deptId;
}
foreach (Database::fetchAll($bloomSql, $bloomParams) as $plan) {
    $bloom = json_decode((string)($plan['bloom_data'] ?? ''), true);
    if (!is_array($bloom) || !$bloom) {
        continue;
    }
    $pid = (int)$plan['professor_id'];
    if (!isset($bloomMap[$pid])) {
        $bloomMap[$pid] = ['higher' => 0.0, 'lower' => 0.0, 'n' => 0];
    }
    $bloomMap[$pid]['higher'] += (float)($bloom['K4'] ?? 0) + (float)($bloom['K5'] ?? 0) + (float)($bloom['K6'] ?? 0);
    $bloomMap[$pid]['lower'] += (float)($bloom['K1'] ?? 0) + (float)($bloom['K2'] ?? 0);
    $bloomMap[$pid]['n']++;
}

$avgRow = Database::fetch(
    'SELECT AVG(ai_score) a FROM course_plans WHERE institution_id = ?'
    . ($deptId > 0 ? ' AND department_id = ?' : '')
    . ' AND ai_score IS NOT NULL',
    $deptId > 0 ? [$instId, $deptId] : [$instId]
);

$facultyStatus = static function (array $row): string {
    $pending = (int)($row['pending'] ?? 0);
    $approved = (int)($row['approved'] ?? 0);
    if ($pending > 0) {
        return 'needs_attention';
    }
    if ($approved > 0) {
        return 'on_track';
    }
    return 'not_started';
};

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'on_track', 'needs_attention', 'not_started'], true)) {
    $statusFilter = 'all';
}
$qLower = mb_strtolower($q);

$filtered = [];
foreach ($faculty as $row) {
    $pid = (int)$row['id'];
    $subjects = $subjectMap[$pid] ?? [];
    $row['subjects'] = $subjects;
    $row['ui_status'] = $facultyStatus($row);
    $bloom = $bloomMap[$pid] ?? null;
    $row['k46'] = ($bloom && (int)$bloom['n'] > 0) ? round($bloom['higher'] / $bloom['n'], 1) : null;
    $row['bloom_attention'] = ($bloom && (int)$bloom['n'] > 0) ? (($bloom['lower'] / $bloom['n']) >= 55) : false;
    if ($statusFilter !== 'all' && $row['ui_status'] !== $statusFilter) {
        continue;
    }
    if ($qLower !== '') {
        $hay = mb_strtolower(implode(' ', [
            (string)($row['full_name'] ?? ''),
            (string)($row['email'] ?? ''),
            (string)($row['designation'] ?? ''),
            implode(' ', $subjects),
        ]));
        if (!str_contains($hay, $qLower)) {
            continue;
        }
    }
    $filtered[] = $row;
}

$submittedTotal = 0;
$approvedTotal = 0;
foreach ($faculty as $row) {
    $submittedTotal += (int)($row['pending'] ?? 0) + (int)($row['approved'] ?? 0);
    $approvedTotal += (int)($row['approved'] ?? 0);
}
$avgAi = ($avgRow['a'] !== null && $avgRow['a'] !== '') ? round((float)$avgRow['a'], 1) : null;

$perPage = 10;
$facultyTotal = count($faculty);
$filteredTotal = count($filtered);
$facultyTotalPages = max(1, (int)ceil($filteredTotal / $perPage));
$facultyPage = (int)($_GET['page'] ?? 1);
if ($facultyPage < 1) {
    $facultyPage = 1;
}
if ($facultyPage > $facultyTotalPages) {
    $facultyPage = $facultyTotalPages;
}
$facultyOffset = ($facultyPage - 1) * $perPage;
$facultyPageRows = $filteredTotal > 0 ? array_slice($filtered, $facultyOffset, $perPage) : [];
$showingFrom = $filteredTotal > 0 ? ($facultyOffset + 1) : 0;
$showingTo = min($facultyOffset + $perPage, $filteredTotal);

$facultyPageQuery = static function (int $page) use ($q, $statusFilter): string {
    $params = [];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($statusFilter !== 'all') {
        $params['status'] = $statusFilter;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    $query = http_build_query($params);
    return url('/hod/faculty' . ($query !== '' ? '?' . $query : ''));
};

$fmt = static function (float|int|string|null $n): string {
    if ($n === null || $n === '') {
        return '—';
    }
    $r = round((float)$n, 1);
    return abs($r - round($r)) < 0.05 ? (string)(int)round($r) : number_format($r, 1);
};

$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
    if (!$parts) {
        return 'F';
    }
    $first = mb_substr($parts[0], 0, 1);
    $last = mb_substr($parts[count($parts) - 1], 0, 1);
    return strtoupper($first . ($parts[0] !== $parts[count($parts) - 1] ? $last : ''));
};

$deptName = trim((string)($dept['name'] ?? ''));
$deptLabel = $deptName;
if ($deptName !== '' && !str_contains(mb_strtolower($deptName), 'department')) {
    $deptLabel = 'Department of ' . $deptName;
}
$semester = trim((string)($inst['current_semester'] ?? ''));
$year = trim((string)($inst['academic_year'] ?? ''));
$period = trim($semester . ($semester !== '' && $year !== '' ? ' ' : '') . $year);
$facultyWord = $facultyTotal === 1 ? '1 Faculty Member' : ($facultyTotal . ' Faculty Members');
$contextBits = array_filter([$deptLabel, $facultyWord, $period], static fn($v) => $v !== '');
$contextLine = implode(' · ', $contextBits);

$statusLabels = [
    'on_track' => 'Active',
    'needs_attention' => 'Needs Attention',
    'not_started' => 'Inactive',
];

render_header('Faculty Management', 'faculty', ['compactTitle' => true]);
?>
<div class="hod-fac">
  <section class="hod-hero">
    <div class="hod-hero-copy">
      <h2><?= icon('users', 'icon-inline') ?> Faculty Management</h2>
      <p><?= e($contextLine) ?></p>
    </div>
  </section>

  <section class="hod-kpi hod-kpi-4" aria-label="Faculty summary">
    <div class="hod-kpi-card hod-fac-stat">
      <span class="hod-fac-ico"><?= icon('users') ?></span>
      <span class="hod-fac-stat-copy">
        <span class="label">Total Faculty</span>
        <strong class="value tone-brand"><?= (int)$facultyTotal ?></strong>
        <span class="hint">In this department</span>
      </span>
    </div>
    <div class="hod-kpi-card hod-fac-stat">
      <span class="hod-fac-ico"><?= icon('file') ?></span>
      <span class="hod-fac-stat-copy">
        <span class="label">Plans Submitted</span>
        <strong class="value tone-info"><?= (int)$submittedTotal ?></strong>
        <span class="hint">Submitted or approved</span>
      </span>
    </div>
    <div class="hod-kpi-card hod-fac-stat">
      <span class="hod-fac-ico"><?= icon('check') ?></span>
      <span class="hod-fac-stat-copy">
        <span class="label">Plans Approved</span>
        <strong class="value tone-ok"><?= (int)$approvedTotal ?></strong>
        <span class="hint">Approved course plans</span>
      </span>
    </div>
    <div class="hod-kpi-card hod-fac-stat">
      <span class="hod-fac-ico"><?= icon('spark') ?></span>
      <span class="hod-fac-stat-copy">
        <span class="label">Avg. AI Score</span>
        <strong class="value tone-brand"><?= $avgAi !== null ? e($fmt($avgAi)) : '—' ?></strong>
        <span class="hint">Average AI plan score</span>
      </span>
    </div>
  </section>

  <section class="hod-panel hod-fac-card">
    <div class="hod-fac-head">
      <h2><?= icon('users', 'icon-inline') ?> Faculty Details</h2>
      <form class="hod-fac-tools" method="get" action="<?= e(url('/hod/faculty')) ?>">
        <input class="hod-fac-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search faculty by name or subject..." aria-label="Search faculty by name or subject">
        <select class="hod-fac-filter" name="status" aria-label="Status" onchange="this.form.submit()">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
          <option value="on_track" <?= $statusFilter === 'on_track' ? 'selected' : '' ?>>Active</option>
          <option value="needs_attention" <?= $statusFilter === 'needs_attention' ? 'selected' : '' ?>>Needs Attention</option>
          <option value="not_started" <?= $statusFilter === 'not_started' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </form>
    </div>

    <div class="table-wrap hod-fac-wrap">
        <table class="hod-fac-table">
          <colgroup>
            <col class="c-num">
            <col class="c-faculty">
            <col class="c-subjects">
            <col class="c-num2">
            <col class="c-num2">
            <col class="c-score">
            <col class="c-k46">
            <col class="c-status">
            <col class="c-action">
          </colgroup>
          <thead>
          <tr>
            <th>#</th>
            <th>Faculty</th>
            <th>Subjects</th>
            <th>Plans Submitted</th>
            <th>Plans Approved</th>
            <th>Avg AI Score</th>
            <th>K4–K6 Avg</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$facultyPageRows): ?>
          <tr><td colspan="9" class="empty">No faculty match this view.</td></tr>
        <?php endif; ?>
        <?php foreach ($facultyPageRows as $i => $f):
          $submitted = (int)$f['pending'] + (int)$f['approved'];
          $statusKey = (string)$f['ui_status'];
          $statusClass = match ($statusKey) {
              'on_track' => 'is-ok',
              'needs_attention' => 'is-warn',
              default => 'is-bad',
          };
          $designation = trim((string)($f['designation'] ?? ''));
          $secondary = $designation !== '' ? $designation : (string)($f['email'] ?? '');
          $subjects = $f['subjects'] ?? [];
          $subjectText = $subjects ? implode(', ', $subjects) : '—';
          $k46 = $f['k46'];
        ?>
          <tr>
            <td data-label="#"><?= (int)($facultyOffset + $i + 1) ?></td>
            <td data-label="Faculty">
              <div class="hod-fac-person">
                <span class="hod-avatar" aria-hidden="true"><?= e($initials((string)$f['full_name'])) ?></span>
                <span>
                  <strong><?= e((string)$f['full_name']) ?></strong>
                  <?php if ($secondary !== ''): ?><small><?= e($secondary) ?></small><?php endif; ?>
                </span>
              </div>
            </td>
            <td data-label="Subjects" title="<?= e($subjectText) ?>"><?= e($subjectText) ?></td>
            <td data-label="Plans Submitted">
              <strong class="<?= $submitted === 0 ? 'hod-fac-zero' : 'hod-fac-done' ?>" title="<?= (int)$f['pending'] > 0 ? (int)$f['pending'] . ' in review' : '' ?>"><?= $submitted ?></strong>
            </td>
            <td data-label="Plans Approved"><strong><?= (int)$f['approved'] ?></strong></td>
            <td data-label="Avg AI Score">
              <?php if ($f['avg_score'] === null || $f['avg_score'] === ''): ?>
                <span class="hod-fac-muted">—</span>
              <?php else: ?>
                <strong class="hod-fac-score <?= e($statusClass) ?>"><?= e($fmt($f['avg_score'])) ?></strong>
              <?php endif; ?>
            </td>
            <td data-label="K4–K6 Avg">
              <?php if ($k46 === null): ?>
                —
              <?php else: ?>
                <div class="hod-fac-k46">
                  <span><?= e($fmt($k46)) ?>%</span>
                  <div class="hod-bar <?= (float)$k46 >= 70 ? 'is-ok' : ((float)$k46 >= 40 ? 'is-warn' : 'is-bad') ?>"><span style="width:<?= e((string)max(0, min(100, (float)$k46))) ?>%"></span></div>
                </div>
              <?php endif; ?>
            </td>
            <td data-label="Status"><span class="hod-fac-badge <?= e($statusClass) ?>"><?= e($statusLabels[$statusKey]) ?></span></td>
            <td data-label="Action">
              <?php if ($statusKey === 'not_started'): ?>
                <form method="post" class="hod-fac-action">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="announce">
                  <input type="hidden" name="announcement_type" value="deadline">
                  <input type="hidden" name="title" value="<?= e('Course plan reminder') ?>">
                  <input type="hidden" name="body" value="<?= e('Please submit your course plans. Reminder for ' . (string)$f['full_name'] . '.') ?>">
                  <button class="hod-fac-remind" type="submit">Send Reminder</button>
                </form>
              <?php else: ?>
                <a class="hod-fac-view" href="<?= e(url('/hod/approvals')) ?>">View Plans</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($facultyTotalPages > 1): ?>
      <div class="hod-fac-pager">
        <span class="chip">Page <?= (int)$facultyPage ?> / <?= (int)$facultyTotalPages ?></span>
        <div>
          <?php if ($facultyPage > 1): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e($facultyPageQuery($facultyPage - 1)) ?>">Previous</a>
          <?php else: ?>
            <button class="btn btn-sm btn-ghost" type="button" disabled>Previous</button>
          <?php endif; ?>
          <?php if ($facultyPage < $facultyTotalPages): ?>
            <a class="btn btn-sm btn-primary" href="<?= e($facultyPageQuery($facultyPage + 1)) ?>">Next</a>
          <?php else: ?>
            <button class="btn btn-sm btn-ghost" type="button" disabled>Next</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php render_footer(); ?>
