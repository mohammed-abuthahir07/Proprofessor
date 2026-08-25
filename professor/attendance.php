<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
$instId = (int)$user['institution_id'];
AttendanceTools::ensureSchema();
$classes = professor_manageable_classes($user);
$classId = (int)(get('class_id') ?: post('class_id'));
if ($classId > 0 && !professor_can_manage_class($user, $classId)) {
    $classId = 0;
}
$subjectId = (int)(get('subject_id') ?: post('subject_id'));
if ($subjectId > 0 && ($classId < 1 || !professor_can_manage_subject($user, $subjectId, $classId))) {
    $subjectId = 0;
}
$subjects = $classId > 0 ? professor_subjects($user, $classId) : [];
$month = preg_match('/^\d{4}-\d{2}$/', (string)get('month')) ? (string)get('month') : date('Y-m');
$minPct = institution_attendance_min($instId);
$tab = (string)(get('tab') ?: 'mark');
if (!in_array($tab, ['mark', 'import', 'reports', 'tools'], true)) {
    $tab = 'mark';
}

if (get('download') === 'roster_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_roster_template.csv"');
    echo "register_no,full_name,email\nCS2024001,Rahul Kumar,rahul@college.edu\n";
    exit;
}

if (get('download') === 'attendance_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_import_template.csv"');
    echo "register_no,session_date,period,status\nCS2024001,2026-08-20,1,present\n";
    exit;
}

if (get('download') === 'attendance_export' && $classId && $subjectId) {
    try {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)get('from')) ? (string)get('from') : null;
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)get('to')) ? (string)get('to') : null;
        $csv = AttendanceTools::exportCsv($user, $classId, $subjectId, $from, $to);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_export.csv"');
        echo $csv;
        exit;
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/professor/attendance.php?class_id=' . $classId . '&subject_id=' . $subjectId . '&tab=tools');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    if ($action === 'import') {
        $classId = (int)post('class_id');
        $classOk = professor_can_manage_class($user, $classId);
        if (!$classOk) {
            flash('error', 'Select a class first.');
            redirect('/professor/attendance.php?tab=import');
        }
        $raw = trim((string)post('roster_csv'));
        $file = $_FILES['roster_file'] ?? null;
        if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string)$file['tmp_name'])) {
            try {
                $raw = spreadsheet_to_csv_text((string)$file['tmp_name'], (string)$file['name']);
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('/professor/attendance.php?class_id=' . $classId . '&tab=import');
            }
        }
        $n = import_roster_rows($instId, $classId, parse_roster_csv($raw));
        sync_class_roster($instId, $classId);
        flash('success', $n ? "Imported/updated $n students." : 'No valid rows. Use register_no, full_name, email.');
        redirect('/professor/attendance.php?class_id=' . $classId . '&subject_id=' . $subjectId . '&tab=import');
    }
    if ($action === 'import_attendance') {
        $classId = (int)post('class_id');
        $subjectId = (int)post('subject_id');
        if (!professor_can_manage_subject($user, $subjectId, $classId)) {
            flash('error', 'Select an assigned class and subject.');
            redirect('/professor/attendance.php?tab=tools');
        }
        $raw = trim((string)post('attendance_csv'));
        $file = $_FILES['attendance_file'] ?? null;
        if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string)$file['tmp_name'])) {
            try {
                $raw = spreadsheet_to_csv_text((string)$file['tmp_name'], (string)$file['name']);
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
                redirect('/professor/attendance.php?class_id=' . $classId . '&subject_id=' . $subjectId . '&tab=tools');
            }
        }
        $result = AttendanceTools::importAttendanceCsv($user, $classId, $subjectId, $raw);
        $msg = 'Import completed. Valid rows: ' . (int)$result['valid'] . '. Invalid rows: ' . count($result['invalid'] ?? []);
        if (!empty($result['invalid'])) {
            $msg .= ' · ' . implode(' · ', array_slice($result['invalid'], 0, 5));
        }
        flash(!empty($result['invalid']) && !(int)$result['valid'] ? 'error' : 'success', $msg);
        redirect('/professor/attendance.php?class_id=' . $classId . '&subject_id=' . $subjectId . '&tab=tools');
    }
    if ($action === 'start_qr') {
        $classId = (int)post('class_id');
        $subjectId = (int)post('subject_id');
        $date = (string)post('session_date');
        $period = trim((string)post('period', '1')) ?: '1';
        $topic = (string)post('topic', '');
        $result = AttendanceTools::startQr($user, $classId, $subjectId, $date, $period, $topic, (int)post('ttl', 15));
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'Could not start QR.');
            redirect('/professor/attendance.php?class_id=' . $classId . '&subject_id=' . $subjectId . '&tab=mark');
        }
        flash('success', 'QR started. Expires ' . $result['expires_at']);
        redirect('/professor/attendance.php?class_id=' . $classId . '&subject_id=' . $subjectId . '&tab=mark&qr=' . urlencode((string)$result['token']));
    }
    if ($action === 'send_shortage_alerts') {
        $classId = (int)post('class_id');
        $subjectId = (int)post('subject_id');
        // Recompute summary briefly for alerts
        $summaryTmp = [];
        if ($classId && $subjectId) {
            $rows = Database::fetchAll(
                'SELECT r.register_no, r.status FROM attendance_records r
                 JOIN attendance_sessions s ON s.id=r.session_id
                 WHERE s.class_id=? AND s.subject_id=?',
                [$classId, $subjectId]
            );
            $agg = [];
            foreach ($rows as $r) {
                $agg[$r['register_no']]['total'] = ($agg[$r['register_no']]['total'] ?? 0) + 1;
                if (in_array($r['status'], ['present', 'late'], true)) {
                    $agg[$r['register_no']]['present'] = ($agg[$r['register_no']]['present'] ?? 0) + 1;
                }
            }
            foreach ($agg as $reg => $a) {
                $summaryTmp[$reg] = $a['total'] ? round(($a['present'] ?? 0) * 100 / $a['total'], 1) : 0;
            }
        }
        $rosterTmp = $classId ? sync_class_roster($instId, $classId) : [];
        $n = AttendanceTools::dispatchShortageAlerts($user, $classId, $subjectId, $summaryTmp, $rosterTmp, $minPct);
        flash('success', $n ? "Sent {$n} shortage alert(s)." : 'No new alerts to send (already notified or none flagged).');
        redirect('/professor/attendance.php?class_id=' . $classId . '&subject_id=' . $subjectId . '&tab=tools');
    }
    if ($action === 'regularization_decide') {
        $result = AttendanceTools::decideRegularization(
            $user,
            (int)post('request_id'),
            (string)post('decision'),
            trim((string)post('professor_note'))
        );
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Request updated.' : ($result['error'] ?? 'Failed.'));
        redirect('/professor/attendance.php?class_id=' . (int)post('class_id') . '&subject_id=' . (int)post('subject_id') . '&tab=tools');
    }
    if ($action === 'save_session') {
        $classId = (int)post('class_id');
        $subjectId = (int)post('subject_id');
        $classOk = professor_can_manage_class($user, $classId);
        if (!$classOk) {
            flash('error', 'Class not found.');
            redirect('/professor/attendance.php');
        }
        if (!professor_can_manage_subject($user, $subjectId, $classId)) {
            flash('error', 'You are not assigned to this course for the selected class.');
            redirect('/professor/attendance.php?class_id=' . $classId);
        }
        if ($subjectId < 1) {
            flash('error', 'Select a subject before saving attendance.');
            redirect('/professor/attendance.php?class_id=' . $classId . '&tab=mark');
        }
        $date = (string)post('session_date');
        $period = trim((string)post('period', '1')) ?: '1';
        $statuses = post('status') ?: [];
        if (!$statuses) {
            flash('error', 'No students to mark. Import a roster or assign students to this class.');
            redirect('/professor/attendance.php?class_id=' . $classId . '&subject_id=' . $subjectId);
        }
        $records = [];
        $present = 0;
        $absent = 0;
        foreach ($statuses as $reg => $st) {
            $st = in_array($st, ['present', 'absent', 'late', 'excused'], true) ? $st : 'present';
            $records[] = ['register_no' => $reg, 'status' => $st];
            if ($st === 'present' || $st === 'late') {
                $present++;
            } else {
                $absent++;
            }
        }
        $payload = [
            'institution_id' => $instId,
            'professor_id' => (int)$user['id'],
            'subject_id' => $subjectId,
            'class_id' => $classId,
            'session_date' => $date,
            'period' => $period,
            'topic' => post('topic'),
            'records' => json_encode($records),
            'present_count' => $present,
            'absent_count' => $absent,
        ];
        $existing = Database::fetch(
            'SELECT id FROM attendance_sessions WHERE class_id=? AND subject_id=? AND session_date=? AND period=?',
            [$classId, $subjectId, $date, $period]
        );
        if ($existing) {
            $sid = (int)$existing['id'];
            Database::update('attendance_sessions', $payload, 'id = :wid', ['wid' => $sid]);
            Database::query('DELETE FROM attendance_records WHERE session_id=?', [$sid]);
        } else {
            $sid = Database::insert('attendance_sessions', $payload);
        }
        foreach ($statuses as $reg => $st) {
            $st = in_array($st, ['present', 'absent', 'late', 'excused'], true) ? $st : 'present';
            $stu = Database::fetch(
                'SELECT user_id FROM students_roster WHERE class_id=? AND register_no=? AND institution_id=?',
                [$classId, $reg, $instId]
            );
            Database::insert('attendance_records', [
                'session_id' => $sid,
                'student_id' => is_array($stu) ? ($stu['user_id'] ?? null) : null,
                'register_no' => $reg,
                'status' => $st,
            ]);
        }
        flash('success', $existing ? 'Attendance session updated.' : 'Attendance saved.');
        redirect('/professor/attendance.php?class_id=' . $classId . '&subject_id=' . $subjectId . '&tab=mark');
    }
}

$roster = $classId ? sync_class_roster($instId, $classId) : [];
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart) ?: time());
$sessions = [];
$heatSessions = [];
if ($classId && $subjectId) {
    $sessions = Database::fetchAll(
        'SELECT * FROM attendance_sessions
         WHERE class_id=? AND subject_id=? AND session_date BETWEEN ? AND ?
         ORDER BY session_date DESC, period DESC',
        [$classId, $subjectId, $monthStart, $monthEnd]
    );
    $heatSessions = array_reverse($sessions);
}

$summary = [];
$heat = [];
if ($classId && $subjectId) {
    $rows = Database::fetchAll(
        'SELECT r.register_no, r.status, s.id AS session_id, s.session_date
         FROM attendance_records r
         JOIN attendance_sessions s ON s.id=r.session_id
         WHERE s.class_id=? AND s.subject_id=?',
        [$classId, $subjectId]
    );
    $agg = [];
    foreach ($rows as $r) {
        $agg[$r['register_no']]['total'] = ($agg[$r['register_no']]['total'] ?? 0) + 1;
        if (in_array($r['status'], ['present', 'late'], true)) {
            $agg[$r['register_no']]['present'] = ($agg[$r['register_no']]['present'] ?? 0) + 1;
        }
        $heat[$r['register_no']][(int)$r['session_id']] = $r['status'];
    }
    foreach ($agg as $reg => $a) {
        $summary[$reg] = $a['total'] ? round(($a['present'] ?? 0) * 100 / $a['total'], 1) : 0;
    }
}

$subjectName = '';
foreach ($subjects as $s) {
    if ((int)$s['id'] === $subjectId) {
        $subjectName = (string)$s['name'];
        break;
    }
}
$classLabel = '';
foreach ($classes as $c) {
    if ((int)$c['id'] === $classId) {
        $classLabel = class_batch_label($c);
        break;
    }
}

$flagged = array_filter($summary, fn($p) => $p < $minPct);
$approaching = [];
$atRisk = [];
foreach ($summary as $reg => $pct) {
    $band = AttendanceTools::shortageBand((float)$pct, $minPct);
    if ($band['band'] === 'approaching') {
        $approaching[$reg] = $pct;
    } elseif ($band['band'] === 'at_risk') {
        $atRisk[$reg] = $pct;
    }
}
$names = array_column($roster, 'full_name', 'register_no');
$heatLetter = ['present' => 'P', 'absent' => 'A', 'late' => 'L', 'excused' => 'E'];
$weekBuckets = AttendanceTools::weekBuckets($monthStart, $monthEnd);
$weeklyHeat = ($classId && $subjectId && $heatSessions && $roster)
    ? AttendanceTools::weeklyHeatLevels($roster, $heatSessions, $heat, $weekBuckets)
    : [];
$regRequests = ($classId && $subjectId) ? AttendanceTools::pendingRegularizations($user, $classId, $subjectId) : [];
$qrToken = trim((string)get('qr', ''));
$qrRow = $qrToken !== '' ? AttendanceTools::findActiveQr($qrToken) : null;
$qrUrl = $qrRow ? base_url('/student/attendance-qr.php?token=' . urlencode($qrToken)) : '';
$geoCfg = AttendanceTools::geofenceConfig($instId);

render_header('Attendance', 'attendance', ['subtitle' => 'Class-wise · Excel import · heatmap · AICTE 75% · PDF · QR']);
?>
<div class="panel no-print">
  <form method="get" class="form-grid" style="grid-template-columns:1fr 1fr auto;gap:.8rem;align-items:end">
    <div class="form-row"><label>Class</label>
      <select name="class_id" onchange="this.form.submit()">
        <option value="">Select class (UG/PG · year)</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>><?= e(class_batch_label($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>Subject</label>
      <select name="subject_id" onchange="this.form.submit()">
        <option value="">Select subject</option>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['code'] . ' · ' . $s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-ghost" type="submit">Load</button>
  </form>
  <?php if (!$classes): ?>
    <div class="empty" style="margin-top:1rem">No assigned classes yet. Your HOD must assign you to a course and class under HOD → Courses.</div>
  <?php endif; ?>
</div>

<div class="grid grid-2" style="margin-top:1rem" data-tabs>
  <div class="panel">
    <div class="tabs no-print">
      <button type="button" class="tab <?= $tab === 'mark' ? 'active' : '' ?>" data-tab="mark">Mark attendance</button>
      <button type="button" class="tab <?= $tab === 'import' ? 'active' : '' ?>" data-tab="import">Import students</button>
      <button type="button" class="tab <?= $tab === 'reports' ? 'active' : '' ?>" data-tab="reports">Reports</button>
      <button type="button" class="tab <?= $tab === 'tools' ? 'active' : '' ?>" data-tab="tools">QR · Export · Requests</button>
    </div>
    <div data-pane="mark" <?= $tab === 'mark' ? '' : 'hidden' ?>>
      <?php if (!$classId): ?>
        <div class="empty">Select a class (UG/PG and year) to load the roster.</div>
      <?php elseif (!$roster): ?>
        <div class="empty">No students in this class yet. Import an Excel/CSV list, or ask College Admin to add students to this class.</div>
      <?php elseif (!$subjectId): ?>
        <div class="empty">Select a course assigned to you for this class. Ask your HOD to assign courses under HOD → Courses.</div>
      <?php else: ?>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_session">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
        <div class="form-row two">
          <div><label>Date</label><input type="date" name="session_date" value="<?= e(date('Y-m-d')) ?>" required></div>
          <div><label>Period</label><input name="period" value="1"></div>
        </div>
        <div class="form-row"><label>Topic</label><input name="topic" placeholder="Optional topic"></div>
        <div class="table-wrap"><table>
          <thead><tr><th>Reg No</th><th>Name</th><th>%</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($roster as $st): $pct = $summary[$st['register_no']] ?? null; ?>
            <tr class="<?= ($pct !== null && $pct < $minPct) ? 'row-flag' : '' ?>">
              <td><?= e($st['register_no']) ?></td>
              <td><?= e($st['full_name']) ?></td>
              <td><?= $pct === null ? '-' : e((string)$pct) . '%' ?><?= ($pct !== null && $pct < $minPct) ? ' <span class="badge badge-danger">&lt;' . (int)$minPct . '%</span>' : '' ?></td>
              <td>
                <select name="status[<?= e($st['register_no']) ?>]">
                  <option value="present">Present</option>
                  <option value="absent">Absent</option>
                  <option value="late">Late</option>
                  <option value="excused">Excused</option>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <button class="btn btn-primary" type="submit">Save session</button>
      </form>
      <hr style="border:0;border-top:1px solid var(--line);margin:1.2rem 0">
      <h3 style="margin-top:0">Start QR attendance</h3>
      <p class="muted" style="margin-top:0">Students scan to mark Present. Manual marking above still works.</p>
      <?php if ($qrRow && $qrUrl): ?>
        <div class="panel" style="background:var(--surface-2);margin-bottom:1rem">
          <p><strong>Active QR</strong> · expires <?= e((string)$qrRow['expires_at']) ?></p>
          <p style="word-break:break-all;font-size:.85rem"><a href="<?= e($qrUrl) ?>" target="_blank" rel="noopener"><?= e($qrUrl) ?></a></p>
          <img alt="Attendance QR" width="180" height="180" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data=<?= e(rawurlencode($qrUrl)) ?>">
          <p class="muted" style="font-size:.8rem;margin-bottom:0">Students open the link or scan the code while logged in.</p>
        </div>
      <?php endif; ?>
      <form method="post" class="form-grid" style="grid-template-columns:1fr 1fr 1fr auto;gap:.6rem;align-items:end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="start_qr">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
        <div class="form-row" style="margin:0"><label>Date</label><input type="date" name="session_date" value="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="form-row" style="margin:0"><label>Period</label><input name="period" value="1"></div>
        <div class="form-row" style="margin:0"><label>TTL (min)</label><input type="number" name="ttl" value="15" min="5" max="60"></div>
        <button class="btn btn-accent" type="submit">Start QR</button>
        <div class="form-row" style="grid-column:1/-1;margin:0"><label>Topic</label><input name="topic" placeholder="Optional"></div>
      </form>
      <?php if ($geoCfg['required']): ?>
        <p class="muted">Geofence required for QR at this institution.</p>
      <?php elseif ($geoCfg['lat'] === null): ?>
        <p class="muted">Geofence not configured — QR works without location.</p>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <div data-pane="import" <?= $tab === 'import' ? '' : 'hidden' ?>>
      <form method="post" class="form-grid" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import">
        <div class="form-row"><label>Class</label>
          <select name="class_id" required>
            <option value="">Select class</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>><?= e(class_batch_label($c)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>Excel / CSV file</label>
          <input type="file" name="roster_file" accept=".csv,.xlsx,.txt">
          <p style="margin:.4rem 0 0;font-size:.82rem;color:var(--muted)">Columns: <code>register_no, full_name, email</code>. <a href="<?= e(base_url('/professor/attendance.php?download=roster_template')) ?>">Download template</a></p>
        </div>
        <div class="form-row">
          <label>Or paste CSV</label>
          <textarea name="roster_csv" placeholder="CS2024001, Rahul Kumar, rahul@college.edu"></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Import students</button>
      </form>
    </div>
    <div data-pane="reports" <?= $tab === 'reports' ? '' : 'hidden' ?>>
      <div class="print-letterhead print-only">
        <strong>Attendance report</strong>
        <span><?= e($classLabel ?: 'Class') ?> · <?= e($subjectName ?: 'Subject') ?> · <?= e($month) ?></span>
      </div>
      <form method="get" class="form-grid no-print" style="grid-template-columns:1fr auto auto;gap:.8rem;align-items:end;margin-bottom:1rem">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
        <input type="hidden" name="tab" value="reports">
        <div class="form-row"><label>Month</label><input type="month" name="month" value="<?= e($month) ?>"></div>
        <button class="btn btn-ghost" type="submit">Refresh</button>
        <button class="btn btn-primary" type="button" data-print>Export PDF</button>
      </form>
      <?php if (!$classId || !$subjectId): ?>
        <div class="empty">Load a class and subject to see monthly / subject-wise reports.</div>
      <?php else: ?>
        <h3>Session list · <?= e($subjectName) ?></h3>
        <div class="table-wrap"><table>
          <thead><tr><th>Date</th><th>Period</th><th>Topic</th><th>Present</th><th>Absent</th></tr></thead>
          <tbody>
          <?php if (!$sessions): ?>
            <tr><td colspan="5">No sessions in <?= e($month) ?>.</td></tr>
          <?php endif; ?>
          <?php foreach ($sessions as $s): ?>
            <tr>
              <td><?= e($s['session_date']) ?></td>
              <td><?= e((string)$s['period']) ?></td>
              <td><?= e((string)($s['topic'] ?? '')) ?></td>
              <td><?= (int)$s['present_count'] ?></td>
              <td><?= (int)$s['absent_count'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <h3 style="margin-top:1.2rem">Attendance heatmap</h3>
        <?php if (!$heatSessions || !$roster): ?>
          <div class="empty">Mark at least one session to see the heatmap.</div>
        <?php else: ?>
          <div class="heat-wrap"><table class="heat-table">
            <thead>
              <tr>
                <th>Student</th>
                <?php foreach ($heatSessions as $s): ?>
                  <th title="<?= e($s['session_date'] . ' P' . $s['period']) ?>"><?= e(date('d/m', strtotime((string)$s['session_date']) ?: time())) ?></th>
                <?php endforeach; ?>
                <th>%</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($roster as $st):
              $pct = $summary[$st['register_no']] ?? null;
            ?>
              <tr class="<?= ($pct !== null && $pct < $minPct) ? 'row-flag' : '' ?>">
                <td><?= e($st['register_no']) ?> · <?= e($st['full_name']) ?></td>
                <?php foreach ($heatSessions as $s):
                  $stt = $heat[$st['register_no']][(int)$s['id']] ?? '';
                  $cls = $stt !== '' ? 'heat-' . substr($stt, 0, 1) : 'heat-n';
                ?>
                  <td class="heat-cell <?= e($cls) ?>"><?= e($heatLetter[$stt] ?? '·') ?></td>
                <?php endforeach; ?>
                <td><?= $pct === null ? '-' : e((string)$pct) . '%' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <p class="heat-legend">P present · A absent · L late · E excused</p>
        <?php endif; ?>
        <?php if ($weeklyHeat && $weekBuckets): ?>
          <h3 style="margin-top:1.2rem">Weekly trend heatmap</h3>
          <div class="heat-wrap"><table class="heat-table">
            <thead><tr><th>Student</th><?php foreach ($weekBuckets as $wb): ?><th><?= e($wb['label']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($roster as $st): $reg = (string)$st['register_no']; ?>
              <tr>
                <td><?= e($st['full_name']) ?></td>
                <?php foreach ($weekBuckets as $wb):
                  $lvl = $weeklyHeat[$reg][$wb['label']] ?? '—';
                  $cls = $lvl === 'High' ? 'heat-p' : ($lvl === 'Medium' ? 'heat-l' : ($lvl === 'Low' ? 'heat-a' : 'heat-n'));
                ?>
                  <td class="heat-cell <?= e($cls) ?>"><?= e($lvl) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <div data-pane="tools" <?= $tab === 'tools' ? '' : 'hidden' ?>>
      <?php if (!$classId || !$subjectId): ?>
        <div class="empty">Load a class and subject first.</div>
      <?php else: ?>
        <h3 style="margin-top:0">Export attendance</h3>
        <form method="get" class="form-grid" style="grid-template-columns:1fr 1fr auto;gap:.6rem;align-items:end">
          <input type="hidden" name="class_id" value="<?= $classId ?>">
          <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
          <input type="hidden" name="download" value="attendance_export">
          <div class="form-row" style="margin:0"><label>From</label><input type="date" name="from"></div>
          <div class="form-row" style="margin:0"><label>To</label><input type="date" name="to"></div>
          <button class="btn btn-ghost" type="submit">Export CSV</button>
        </form>

        <h3>Import attendance records</h3>
        <p class="muted">Columns: <code>register_no, session_date, period, status</code>. Status: present / absent / late / excused. <a href="<?= e(base_url('/professor/attendance.php?download=attendance_template')) ?>">Template</a></p>
        <form method="post" enctype="multipart/form-data" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="import_attendance">
          <input type="hidden" name="class_id" value="<?= $classId ?>">
          <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
          <div class="form-row"><label>CSV / Excel</label><input type="file" name="attendance_file" accept=".csv,.xlsx,.txt"></div>
          <div class="form-row"><label>Or paste CSV</label><textarea name="attendance_csv" placeholder="CS2024001,2026-08-20,1,present"></textarea></div>
          <button class="btn btn-primary" type="submit">Import attendance</button>
        </form>

        <h3>Shortage notifications</h3>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="action" value="send_shortage_alerts">
          <input type="hidden" name="class_id" value="<?= $classId ?>">
          <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
          <button class="btn btn-ghost" type="submit">Notify approaching / at-risk / below students</button>
        </form>

        <h3>Regularization requests</h3>
        <?php if (!$regRequests): ?>
          <div class="empty">No requests yet.</div>
        <?php else: ?>
          <div class="table-wrap"><table>
            <thead><tr><th>Student</th><th>Date</th><th>From → To</th><th>Reason</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($regRequests as $rq): ?>
              <tr>
                <td><?= e((string)($rq['full_name'] ?? $rq['register_no'])) ?></td>
                <td><?= e((string)$rq['session_date']) ?> P<?= e((string)$rq['period']) ?></td>
                <td><?= e((string)$rq['original_status']) ?> → <?= e((string)$rq['requested_status']) ?></td>
                <td><?= e((string)$rq['reason']) ?><?php if (!empty($rq['proof_url'])): ?> · <a href="<?= e((string)$rq['proof_url']) ?>" target="_blank" rel="noopener">Proof</a><?php endif; ?></td>
                <td><?= status_badge($rq['status']) ?></td>
                <td>
                  <?php if ($rq['status'] === 'pending'): ?>
                  <form method="post" style="display:flex;gap:.35rem;flex-wrap:wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="regularization_decide">
                    <input type="hidden" name="request_id" value="<?= (int)$rq['id'] ?>">
                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                    <input name="professor_note" placeholder="Note" style="min-width:6rem">
                    <button class="btn btn-sm btn-primary" name="decision" value="approved" type="submit">Approve</button>
                    <button class="btn btn-sm btn-ghost" name="decision" value="rejected" type="submit">Reject</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>

        <h3>Predictive risk (deterministic)</h3>
        <div class="table-wrap"><table>
          <thead><tr><th>Student</th><th>Attendance %</th><th>Predicted risk</th></tr></thead>
          <tbody>
          <?php foreach ($roster as $st):
            $pct = $summary[$st['register_no']] ?? null;
            $risk = AttendanceTools::riskScore($classId, $subjectId, (string)$st['register_no'], $minPct);
          ?>
            <tr>
              <td><?= e($st['full_name']) ?></td>
              <td><?= $pct === null ? '—' : e((string)$pct) . '%' ?></td>
              <td>
                <?php if ($risk['level'] === 'insufficient'): ?>
                  <span class="muted"><?= e($risk['label']) ?></span>
                <?php else: ?>
                  <span class="badge <?= $risk['level'] === 'high' ? 'badge-danger' : ($risk['level'] === 'medium' ? 'badge-warn' : 'badge-success') ?>"><?= e($risk['label']) ?></span>
                  <span class="muted" style="font-size:.8rem"><?= e($risk['detail']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="panel">
    <h3>Below <?= (int)$minPct ?>% (AICTE)</h3>
    <?php if (!$classId || !$subjectId): ?>
      <div class="empty">Select class and subject to see alerts.</div>
    <?php elseif (!$flagged): ?>
      <div class="empty">No alerts for selected subject yet.</div>
    <?php else: ?>
      <ul><?php foreach ($flagged as $reg => $pct): ?>
        <li><strong><?= e($reg) ?></strong> <?= e($names[$reg] ?? '') ?> · <?= e((string)$pct) ?>%</li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
    <h3>At risk</h3>
    <?php if (!$atRisk): ?><div class="empty">None</div><?php else: ?>
      <ul><?php foreach ($atRisk as $reg => $pct): ?>
        <li><?= e($names[$reg] ?? $reg) ?> · <?= e((string)$pct) ?>% · At risk</li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
    <h3>Approaching shortage</h3>
    <?php if (!$approaching): ?><div class="empty">None</div><?php else: ?>
      <ul><?php foreach ($approaching as $reg => $pct): ?>
        <li><?= e($names[$reg] ?? $reg) ?> · <?= e((string)$pct) ?>%</li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>
<?php render_footer(); ?>
