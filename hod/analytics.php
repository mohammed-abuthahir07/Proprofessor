<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('hod', 'admin');
$user = Auth::user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$instId = (int)($user['institution_id'] ?? 0);
$deptId = $isAdmin
    ? (int)($_GET['department_id'] ?? ($user['department_id'] ?? 0))
    : hod_department_id($user);

$dept = $deptId > 0
    ? Database::fetch('SELECT id, name, code FROM departments WHERE id = ? AND institution_id = ?', [$deptId, $instId])
    : null;
$inst = Database::fetch('SELECT academic_year, current_semester FROM institutions WHERE id = ?', [$instId]) ?: [];

$plans = [];
if ($deptId > 0 || $isAdmin) {
    $planSql = 'SELECT p.subject_name, p.ai_score, p.bloom_data, p.status, p.semester, p.academic_year,
                       p.professor_id, u.full_name AS professor_name
                FROM course_plans p
                LEFT JOIN users u ON u.id = p.professor_id
                WHERE p.institution_id = ?';
    $planParams = [$instId];
    if ($deptId > 0) {
        $planSql .= ' AND p.department_id = ?';
        $planParams[] = $deptId;
    }
    $planSql .= ' ORDER BY p.subject_name, u.full_name';
    $plans = Database::fetchAll($planSql, $planParams);
}

$fmtNum = static function (?float $n, int $dec = 1): string {
    if ($n === null) {
        return '—';
    }
    $rounded = round($n, $dec);
    if ($dec > 0 && abs($rounded - round($rounded)) < 0.05) {
        return (string)(int)round($rounded);
    }
    return number_format($rounded, $dec);
};
$fmtPct = static function (?float $n) use ($fmtNum): string {
    return $n === null ? '—' : $fmtNum($n) . '%';
};

$scoreSum = 0.0;
$scoreN = 0;
$bloomSum = 0.0;
$bloomN = 0;
$submittedPlans = 0;
$byProf = [];
$bySubject = [];
$byPeriod = [];

foreach ($plans as $p) {
    $status = strtolower((string)($p['status'] ?? 'draft'));
    if (in_array($status, ['submitted', 'under_review', 'approved', 'returned'], true)) {
        $submittedPlans++;
    }

    $pid = (int)($p['professor_id'] ?? 0);
    $pname = trim((string)($p['professor_name'] ?? ''));
    if ($pname === '') {
        $pname = 'Faculty';
    }
    if ($pid > 0 && !isset($byProf[$pid])) {
        $byProf[$pid] = ['name' => $pname, 'sum' => 0.0, 'n' => 0, 'plans' => 0];
    }
    if ($pid > 0) {
        $byProf[$pid]['plans']++;
    }

    $score = $p['ai_score'];
    $hasScore = $score !== null && $score !== '';
    if ($hasScore) {
        $scoreSum += (float)$score;
        $scoreN++;
        if ($pid > 0) {
            $byProf[$pid]['sum'] += (float)$score;
            $byProf[$pid]['n']++;
        }
    }

    $bloom = json_decode((string)($p['bloom_data'] ?? ''), true);
    $subject = trim((string)($p['subject_name'] ?? ''));
    if (is_array($bloom) && $bloom !== [] && $subject !== '') {
        $higher = (float)($bloom['K4'] ?? 0) + (float)($bloom['K5'] ?? 0) + (float)($bloom['K6'] ?? 0);
        $bloomSum += $higher;
        $bloomN++;
        if (!isset($bySubject[$subject])) {
            $bySubject[$subject] = ['sum' => 0.0, 'n' => 0];
        }
        $bySubject[$subject]['sum'] += $higher;
        $bySubject[$subject]['n']++;
    }

    if ($hasScore) {
        $sem = trim((string)($p['semester'] ?? ''));
        $year = trim((string)($p['academic_year'] ?? ''));
        $unspecified = $sem === '' && $year === '';
        $label = $unspecified ? 'Not recorded' : trim($sem . ($sem !== '' && $year !== '' ? ' ' : '') . $year);
        if (!isset($byPeriod[$label])) {
            $byPeriod[$label] = ['label' => $label, 'sum' => 0.0, 'n' => 0, 'unspecified' => $unspecified];
        }
        $byPeriod[$label]['sum'] += (float)$score;
        $byPeriod[$label]['n']++;
    }
}

$avgAi = $scoreN > 0 ? round($scoreSum / $scoreN, 1) : null;
$avgK46 = $bloomN > 0 ? round($bloomSum / $bloomN, 1) : null;
$planCount = count($plans);
$submittedRate = $planCount > 0 ? round($submittedPlans * 100 / $planCount, 1) : null;

$attendance = null;
if ($deptId > 0) {
    $attendanceRows = Database::fetchAll(
        'SELECT s.class_id, s.subject_id,
                SUM(r.status IN ("present","late")) AS presentish,
                COUNT(r.id) AS total_rows
         FROM attendance_sessions s
         JOIN attendance_records r ON r.session_id = s.id
         JOIN classes c ON c.id = s.class_id
         WHERE c.institution_id = ? AND c.department_id = ?
           AND EXISTS (
             SELECT 1 FROM subject_assignments sa
             JOIN subjects sub ON sub.id = sa.subject_id
             WHERE sa.class_id = s.class_id AND sa.subject_id = s.subject_id
               AND sub.institution_id = ?
           )
         GROUP BY s.class_id, s.subject_id',
        [$instId, $deptId, $instId]
    );
    $pairSum = 0.0;
    $pairN = 0;
    foreach ($attendanceRows as $row) {
        $total = (int)($row['total_rows'] ?? 0);
        if ($total < 1) {
            continue;
        }
        $pairSum += ((float)$row['presentish'] * 100.0) / $total;
        $pairN++;
    }
    $attendance = $pairN > 0 ? round($pairSum / $pairN, 1) : null;
}

$facultyChart = array_values($byProf);
usort($facultyChart, static fn(array $a, array $b): int => strcmp((string)$a['name'], (string)$b['name']));
foreach ($facultyChart as &$row) {
    $row['score'] = $row['n'] > 0 ? round($row['sum'] / $row['n'], 1) : null;
}
unset($row);

$subjectChart = [];
foreach ($bySubject as $name => $row) {
    $pct = round($row['sum'] / max(1, (int)$row['n']), 1);
    $subjectChart[] = [
        'name' => $name,
        'pct' => $pct,
        'below' => $pct < 30,
    ];
}
usort($subjectChart, static fn(array $a, array $b): int => strcmp((string)$a['name'], (string)$b['name']));

$periodChart = array_values($byPeriod);
usort($periodChart, static function (array $a, array $b): int {
    $rank = static function (array $row): array {
        if (!empty($row['unspecified'])) {
            return [9999, 9, (string)$row['label']];
        }
        $label = (string)$row['label'];
        $year = 0;
        if (preg_match('/(20\d{2})/', $label, $m)) {
            $year = (int)$m[1];
        }
        $low = strtolower($label);
        $semRank = str_contains($low, 'odd') ? 1 : (str_contains($low, 'even') ? 2 : 5);
        return [$year, $semRank, $label];
    };
    return $rank($a) <=> $rank($b);
});
foreach ($periodChart as &$row) {
    $row['avg'] = $row['n'] > 0 ? round($row['sum'] / $row['n'], 1) : null;
}
unset($row);

$aiTrend = null;
$realPeriods = array_values(array_filter($periodChart, static fn(array $row): bool => empty($row['unspecified']) && $row['avg'] !== null));
if (count($realPeriods) >= 2) {
    $last = (float)$realPeriods[count($realPeriods) - 1]['avg'];
    $prev = (float)$realPeriods[count($realPeriods) - 2]['avg'];
    $delta = round($last - $prev, 1);
    $aiTrend = ($delta > 0 ? '+' : '') . $fmtNum($delta) . ' vs previous period';
}

$deptLabel = $dept
    ? trim((string)(($dept['code'] ?? '') !== '' ? $dept['code'] . ' — ' . ($dept['name'] ?? '') : ($dept['name'] ?? 'Department')))
    : 'Department';
$semester = trim((string)($inst['current_semester'] ?? ''));
$year = trim((string)($inst['academic_year'] ?? ''));
$period = trim($semester . ($semester !== '' && $year !== '' ? ' ' : '') . $year);
$contextLine = $deptLabel . ($period !== '' ? ' · ' . $period : '');
$semesterNote = $periodChart === []
    ? 'Average AI score by the semester stored on each course plan.'
    : (count(array_filter($periodChart, static fn(array $row): bool => !empty($row['unspecified']))) === count($periodChart)
        ? 'Semester is not stored on these course plans, so they stay in one group.'
        : 'Average AI score by the semester stored on each course plan.');

$facultyChartH = max(220, count($facultyChart) * 52);
$subjectChartH = max(220, count($subjectChart) * 56);

render_header('Department Analytics', 'analytics', ['compactTitle' => true]);
?>
<?php if (!$isAdmin && $deptId < 1): ?>
<div class="panel">
  <div class="alert alert-warn">Your HOD account is not linked to a department. Contact the College Admin.</div>
</div>
<?php else: ?>

<div class="hod-an">
  <section class="hod-hero">
    <div class="hod-hero-copy">
      <h2><?= icon('trend', 'icon-inline') ?> Department Analytics</h2>
      <p><?= e($contextLine) ?></p>
    </div>
  </section>

  <section class="hod-kpi hod-an-kpi" aria-label="Department summary">
    <div class="hod-kpi-card hod-fac-stat">
      <span class="hod-fac-ico"><?= icon('spark') ?></span>
      <span class="hod-fac-stat-copy">
        <span class="label">Dept Avg AI Score</span>
        <strong class="value tone-brand"><?= e($fmtNum($avgAi)) ?></strong>
        <span class="hint"><?= e($aiTrend ?? 'Across scored course plans') ?></span>
      </span>
    </div>
    <div class="hod-kpi-card hod-fac-stat">
      <span class="hod-fac-ico"><?= icon('chart') ?></span>
      <span class="hod-fac-stat-copy">
        <span class="label">Avg K4–K6 Coverage</span>
        <strong class="value tone-ok"><?= e($fmtPct($avgK46)) ?></strong>
        <span class="hint">Average of K4, K5, and K6</span>
      </span>
    </div>
    <div class="hod-kpi-card hod-fac-stat">
      <span class="hod-fac-ico"><?= icon('file') ?></span>
      <span class="hod-fac-stat-copy">
        <span class="label">Plans Submitted Rate</span>
        <strong class="value tone-info"><?= e($fmtPct($submittedRate)) ?></strong>
        <span class="hint">Submitted, in review, approved, or returned</span>
      </span>
    </div>
    <div class="hod-kpi-card hod-fac-stat">
      <span class="hod-fac-ico"><?= icon('users') ?></span>
      <span class="hod-fac-stat-copy">
        <span class="label">Student Avg Attendance</span>
        <strong class="value tone-ok"><?= e($fmtPct($attendance)) ?></strong>
        <span class="hint"><?= $attendance === null ? 'No attendance sessions' : 'Present or late, by class and subject' ?></span>
      </span>
    </div>
  </section>

  <div class="hod-an-grid">
    <section class="hod-panel">
      <div class="hod-panel-h">
        <h2><?= icon('users', 'icon-inline') ?> AI Score by Faculty</h2>
      </div>
      <?php if (!$facultyChart): ?>
        <div class="empty">No analytics data available</div>
      <?php else: ?>
        <div class="hod-an-chart" style="--chart-h: <?= (int)$facultyChartH ?>px">
          <canvas id="aiFacultyChart" aria-label="Average AI score by faculty"></canvas>
        </div>
      <?php endif; ?>
    </section>

    <section class="hod-panel">
      <div class="hod-panel-h">
        <h2><?= icon('chart', 'icon-inline') ?> K4–K6 by Subject</h2>
      </div>
      <p class="hod-an-note">The red line marks 30%. A bar that stops short of it is below the threshold.</p>
      <?php if (!$subjectChart): ?>
        <div class="empty">No analytics data available</div>
      <?php else: ?>
        <div class="hod-an-chart" style="--chart-h: <?= (int)$subjectChartH ?>px">
          <canvas id="k46SubjectChart" aria-label="K4 to K6 coverage by subject with a 30 percent threshold"></canvas>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <section class="hod-panel">
    <div class="hod-panel-h">
      <h2><?= icon('trend', 'icon-inline') ?> Semester-wise AI Score Trend</h2>
    </div>
    <p class="hod-an-note"><?= e($semesterNote) ?></p>
    <?php if (!$periodChart): ?>
      <div class="empty">No analytics data available</div>
    <?php else: ?>
      <div class="hod-an-chart hod-an-chart-wide" style="--chart-h: 280px">
        <canvas id="semesterTrendChart" aria-label="Semester-wise average AI score"></canvas>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
(function () {
  const faculty = <?= json_encode($facultyChart, JSON_UNESCAPED_UNICODE) ?>;
  const subjects = <?= json_encode($subjectChart, JSON_UNESCAPED_UNICODE) ?>;
  const periods = <?= json_encode($periodChart, JSON_UNESCAPED_UNICODE) ?>;
  const font = 'DM Sans, sans-serif';
  const grid = 'rgba(139, 92, 246, 0.12)';
  const tick = '#c4b5fd';

  function tooltipStyle() {
    return {
      backgroundColor: 'rgba(26, 22, 53, 0.96)',
      titleColor: '#f5f3ff',
      bodyColor: '#ddd6fe',
      borderColor: 'rgba(167, 139, 250, 0.35)',
      borderWidth: 1,
      padding: 10,
    };
  }

  function wrapLabel(label) {
    const text = String(label || '');
    if (text.length <= 18) return text;
    const parts = [];
    let line = '';
    text.split(' ').forEach((word) => {
      const next = (line + ' ' + word).trim();
      if (next.length > 16 && line) {
        parts.push(line);
        line = word;
      } else {
        line = next;
      }
    });
    if (line) parts.push(line);
    return parts;
  }

  function drawEndLabels(chart, suffix) {
    const ctx = chart.ctx;
    const meta = chart.getDatasetMeta(0);
    const horizontal = chart.options.indexAxis === 'y';
    ctx.save();
    ctx.fillStyle = '#f5f3ff';
    ctx.font = '700 12px ' + font;
    ctx.textBaseline = 'middle';
    meta.data.forEach((bar, i) => {
      const raw = chart.data.datasets[0].data[i];
      if (raw === null || raw === undefined) return;
      const text = String(raw) + suffix;
      if (horizontal) {
        ctx.textAlign = 'left';
        ctx.fillText(text, bar.x + 8, bar.y);
      } else {
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        ctx.fillText(text, bar.x, bar.y - 6);
      }
    });
    ctx.restore();
  }

  const thresholdLine = {
    id: 'k46Threshold30',
    afterDatasetsDraw(chart) {
      const scale = chart.scales.x;
      const area = chart.chartArea;
      if (!scale || !area) return;
      const x = scale.getPixelForValue(30);
      const ctx = chart.ctx;
      ctx.save();
      ctx.beginPath();
      ctx.setLineDash([5, 4]);
      ctx.strokeStyle = '#f87171';
      ctx.lineWidth = 2;
      ctx.moveTo(x, area.top);
      ctx.lineTo(x, area.bottom);
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.fillStyle = '#f87171';
      ctx.font = '700 11px ' + font;
      ctx.textAlign = 'left';
      ctx.textBaseline = 'top';
      ctx.fillText('30%', x + 6, area.top + 4);
      ctx.restore();
    }
  };

  const barValueLabels = {
    id: 'barValueLabels',
    afterDatasetsDraw(chart) {
      drawEndLabels(chart, chart.options.plugins.barValueLabels?.suffix || '');
    }
  };

  function boot() {
    if (typeof Chart === 'undefined') {
      setTimeout(boot, 40);
      return;
    }
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const facultyEl = document.getElementById('aiFacultyChart');
    if (facultyEl && faculty.length) {
      new Chart(facultyEl, {
        type: 'bar',
        plugins: [barValueLabels],
        data: {
          labels: faculty.map((row) => row.name),
          datasets: [{
            label: 'Avg AI score',
            data: faculty.map((row) => row.score),
            backgroundColor: '#8b5cf6',
            hoverBackgroundColor: '#a78bfa',
            borderRadius: 8,
            maxBarThickness: 18,
          }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          animation: reduce ? false : { duration: 700 },
          layout: { padding: { right: 42, top: 4 } },
          plugins: {
            legend: { display: false },
            barValueLabels: { suffix: '' },
            tooltip: {
              ...tooltipStyle(),
              callbacks: {
                title(items) { return faculty[items[0].dataIndex].name; },
                label(item) {
                  const row = faculty[item.dataIndex];
                  const score = row.score === null ? 'No AI score' : 'Avg AI score: ' + row.score;
                  return [score, 'Plans: ' + row.plans];
                },
              },
            },
          },
          scales: {
            x: {
              min: 0,
              max: 100,
              ticks: { color: tick, font: { family: font } },
              grid: { color: grid },
            },
            y: {
              ticks: {
                color: '#f5f3ff',
                font: { family: font, size: 12 },
                callback(value) { return wrapLabel(this.getLabelForValue(value)); },
              },
              grid: { display: false },
            },
          },
        },
      });
    }

    const subjectEl = document.getElementById('k46SubjectChart');
    if (subjectEl && subjects.length) {
      new Chart(subjectEl, {
        type: 'bar',
        plugins: [thresholdLine, barValueLabels],
        data: {
          labels: subjects.map((row) => row.name),
          datasets: [{
            label: 'K4–K6',
            data: subjects.map((row) => row.pct),
            backgroundColor: subjects.map((row) => row.below ? '#f87171' : '#4ade80'),
            hoverBackgroundColor: subjects.map((row) => row.below ? '#fca5a5' : '#86efac'),
            borderRadius: 8,
            maxBarThickness: 18,
          }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          animation: reduce ? false : { duration: 700 },
          layout: { padding: { right: 48, top: 4 } },
          plugins: {
            legend: { display: false },
            barValueLabels: { suffix: '%' },
            tooltip: {
              ...tooltipStyle(),
              callbacks: {
                title(items) { return subjects[items[0].dataIndex].name; },
                label(item) {
                  const row = subjects[item.dataIndex];
                  return [
                    'K4–K6 coverage: ' + row.pct + '%',
                    'Threshold: 30%',
                    row.below ? 'Status: Below threshold' : 'Status: Meets threshold',
                  ];
                },
              },
            },
          },
          scales: {
            x: {
              min: 0,
              max: 100,
              ticks: {
                color: tick,
                font: { family: font },
                callback(value) { return value + '%'; },
              },
              grid: { color: grid },
            },
            y: {
              ticks: {
                color: '#f5f3ff',
                font: { family: font, size: 12 },
                callback(value) { return wrapLabel(this.getLabelForValue(value)); },
              },
              grid: { display: false },
            },
          },
        },
      });
    }

    const periodEl = document.getElementById('semesterTrendChart');
    if (periodEl && periods.length) {
      new Chart(periodEl, {
        type: 'bar',
        plugins: [barValueLabels],
        data: {
          labels: periods.map((row) => row.label),
          datasets: [{
            label: 'Avg AI score',
            data: periods.map((row) => row.avg),
            backgroundColor: periods.map((row, i) => i === periods.length - 1 ? '#c4b5fd' : '#8b5cf6'),
            hoverBackgroundColor: '#ddd6fe',
            borderRadius: 8,
            maxBarThickness: 48,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: reduce ? false : { duration: 700 },
          layout: { padding: { top: 18 } },
          plugins: {
            legend: { display: false },
            barValueLabels: { suffix: '' },
            tooltip: {
              ...tooltipStyle(),
              callbacks: {
                title(items) { return periods[items[0].dataIndex].label; },
                label(item) {
                  const row = periods[item.dataIndex];
                  const lines = ['Avg AI score: ' + row.avg, 'Plans: ' + row.n];
                  if (row.unspecified) lines.push('Semester is not stored on these plans');
                  return lines;
                },
              },
            },
          },
          scales: {
            x: {
              ticks: { color: '#f5f3ff', font: { family: font, size: 12 } },
              grid: { display: false },
            },
            y: {
              min: 0,
              max: 100,
              ticks: { color: tick, font: { family: font } },
              grid: { color: grid },
            },
          },
        },
      });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
