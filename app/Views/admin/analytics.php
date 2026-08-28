<?php
/** @var array $metrics */
/** @var array $roles */
/** @var array $aiByDept */
/** @var int $aiDeptTotal */
/** @var array|null $topAiDept */
/** @var array $deptPeople */
/** @var array $deptExpenses */
/** @var int $expenseYear */
/** @var string $expenseMonthLabel */
/** @var float $expenseYearTotal */
/** @var float $expenseMonthTotal */
/** @var array|null $topExpenseDept */
$roles = $roles ?? ['student' => 0, 'professor' => 0, 'hod' => 0];
$aiByDept = $aiByDept ?? [];
$aiDeptTotal = (int)($aiDeptTotal ?? 0);
$topAiDept = $topAiDept ?? null;
$deptPeople = $deptPeople ?? [];
$deptExpenses = $deptExpenses ?? [];
$expenseYear = (int)($expenseYear ?? date('Y'));
$expenseMonthLabel = (string)($expenseMonthLabel ?? date('F Y'));
$expenseYearTotal = (float)($expenseYearTotal ?? 0);
$expenseMonthTotal = (float)($expenseMonthTotal ?? 0);
$topExpenseDept = $topExpenseDept ?? null;
$formatMoney = static function (float $amount): string {
    return '₹' . number_format($amount, 2);
};
$studentN = (int)($roles['student'] ?? 0);
$professorN = (int)($roles['professor'] ?? 0);
$hodN = (int)($roles['hod'] ?? 0);
$peopleTotal = $studentN + $professorN + $hodN;
$topDeptLabel = $topAiDept
    ? trim((string)(($topAiDept['code'] ?? '') !== '' ? $topAiDept['code'] : ($topAiDept['name'] ?? '—')))
    : '—';
$topDeptCount = (int)($topAiDept['ai_count'] ?? 0);
?>
<style>
  .analytics-page .stat {
    position: relative;
    overflow: hidden;
    transition: transform .35s cubic-bezier(.2,.8,.2,1), box-shadow .35s ease;
  }
  .analytics-page .stat:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 12px 28px rgba(124, 58, 237, .18);
  }
  .analytics-page .stat::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, transparent 40%, rgba(167,139,250,.12) 50%, transparent 60%);
    background-size: 220% 100%;
    animation: analyticsShimmer 3.2s ease-in-out infinite;
    pointer-events: none;
  }
  @keyframes analyticsShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
  }
  @keyframes analyticsCountUp {
    from { opacity: 0; transform: translateY(10px) scale(.92); }
    to { opacity: 1; transform: none; }
  }
  .analytics-page .stat .value {
    animation: analyticsCountUp .7s cubic-bezier(.2,.8,.2,1) both;
  }
  .analytics-page .role-chip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .85rem 1rem;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: rgba(255,255,255,.02);
    transition: transform .3s ease, border-color .3s ease, background .3s ease;
  }
  .analytics-page .role-chip:hover {
    transform: translateX(4px);
    border-color: rgba(167,139,250,.45);
    background: rgba(124, 58, 237, .08);
  }
  .analytics-page .role-dot {
    width: .7rem;
    height: .7rem;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 4px rgba(255,255,255,.06);
    animation: analyticsPulse 2s ease-in-out infinite;
  }
  @keyframes analyticsPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.25); opacity: .75; }
  }
  .analytics-page .chart-wrap {
    position: relative;
    max-width: 340px;
    margin: 0 auto;
    min-height: 280px;
  }
  .analytics-page .chart-center {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    pointer-events: none;
    text-align: center;
  }
  .analytics-page .chart-center strong {
    display: block;
    font-size: 1.6rem;
    line-height: 1.1;
  }
  .analytics-page .chart-center span {
    color: var(--muted);
    font-size: .8rem;
  }
  .analytics-page .readiness-bar {
    height: 10px;
    border-radius: 999px;
    background: rgba(255,255,255,.06);
    overflow: hidden;
    margin-top: .75rem;
  }
  .analytics-page .readiness-fill {
    height: 100%;
    width: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, #7c3aed, #a78bfa, #22d3ee);
    background-size: 200% 100%;
    animation: analyticsBarFill 1.2s cubic-bezier(.2,.8,.2,1) forwards, analyticsShimmer 2.8s ease infinite;
  }
  @keyframes analyticsBarFill {
    from { width: 0; }
    to { width: var(--ready-pct, 0%); }
  }
  .analytics-page .dept-ai-row {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 2fr) auto;
    gap: .75rem;
    align-items: center;
    padding: .65rem 0;
    border-bottom: 1px solid var(--line);
  }
  .analytics-page .dept-ai-row:last-child { border-bottom: 0; }
  .analytics-page .dept-ai-track {
    height: 8px;
    border-radius: 999px;
    background: rgba(255,255,255,.06);
    overflow: hidden;
  }
  .analytics-page .dept-ai-fill {
    height: 100%;
    width: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, #22d3ee, #a78bfa);
    animation: analyticsBarFill 1s cubic-bezier(.2,.8,.2,1) forwards;
  }
  .analytics-page .dept-ai-row.is-top {
    background: rgba(124, 58, 237, .08);
    border-radius: 10px;
    padding: .65rem .75rem;
    border: 1px solid rgba(167,139,250,.28);
  }
  .analytics-page .dept-people-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1rem;
    margin-top: .85rem;
  }
  .analytics-page .dept-people-card {
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 1rem 1.05rem;
    background: rgba(255,255,255,.02);
    transition: transform .3s ease, border-color .3s ease, box-shadow .3s ease;
  }
  .analytics-page .dept-people-card:hover {
    transform: translateY(-3px);
    border-color: rgba(167,139,250,.4);
    box-shadow: 0 10px 24px rgba(124, 58, 237, .12);
  }
  .analytics-page .dept-people-card h3 {
    margin: 0 0 .75rem;
    font-size: 1rem;
  }
  .analytics-page .dept-people-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .55rem;
  }
  .analytics-page .dept-people-stat {
    text-align: center;
    padding: .65rem .4rem;
    border-radius: 10px;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.04);
  }
  .analytics-page .dept-people-stat .n {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.1;
  }
  .analytics-page .dept-people-stat .l {
    display: block;
    margin-top: .2rem;
    font-size: .72rem;
    color: var(--muted);
    letter-spacing: .02em;
  }
  .analytics-page .expense-stat-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .55rem;
  }
  .analytics-page .expense-stat {
    text-align: left;
    padding: .7rem .75rem;
    border-radius: 10px;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.04);
  }
  .analytics-page .expense-stat .n {
    display: block;
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.2;
    color: #34d399;
  }
  .analytics-page .expense-stat .l {
    display: block;
    margin-top: .2rem;
    font-size: .72rem;
    color: var(--muted);
  }
  @media (prefers-reduced-motion: reduce) {
    .analytics-page .stat::after,
    .analytics-page .stat .value,
    .analytics-page .role-dot,
    .analytics-page .readiness-fill,
    .analytics-page .dept-ai-fill {
      animation: none !important;
    }
    .analytics-page .readiness-fill,
    .analytics-page .dept-ai-fill { width: var(--ready-pct, 0%); }
  }
</style>

<div class="analytics-page">
  <div class="grid grid-4 stagger">
    <div class="stat reveal">
      <div class="label">AI generations</div>
      <div class="value" data-count="<?= (int)$metrics['ai_calls'] ?>">0</div>
    </div>
    <div class="stat reveal">
      <div class="label">Avg plan score</div>
      <div class="value" data-count="<?= $metrics['avg_score'] !== null ? round((float)$metrics['avg_score'], 1) : 0 ?>" data-decimals="1">
        <?= $metrics['avg_score'] !== null ? '0' : '—' ?>
      </div>
    </div>
    <div class="stat reveal">
      <div class="label">Top AI department</div>
      <div class="value" style="font-size:1.25rem"><?= e($topDeptLabel) ?></div>
      <div class="hint"><?= $topDeptCount > 0 ? ($topDeptCount . ' generations') : 'No usage yet' ?></div>
    </div>
    <div class="stat reveal">
      <div class="label">Active people</div>
      <div class="value" data-count="<?= (int)$peopleTotal ?>">0</div>
    </div>
  </div>

  <div class="panel reveal" style="margin-top:1rem">
    <div class="panel-h">
      <h2>AI usage by department</h2>
      <span class="chip"><?= (int)$aiDeptTotal ?> tracked</span>
    </div>
    <p class="muted" style="margin-top:0;font-size:.88rem">Updates automatically from live AI generation logs — highest usage shown first.</p>
    <?php if (!$aiByDept): ?>
      <div class="empty">No department AI usage yet.</div>
    <?php else: ?>
      <div class="stagger" style="margin-top:.5rem">
        <?php foreach ($aiByDept as $i => $dept):
          $count = (int)($dept['ai_count'] ?? 0);
          $pct = $aiDeptTotal > 0 ? round(($count / $aiDeptTotal) * 100) : 0;
          $label = trim((string)(($dept['code'] ?? '') . ' · ' . ($dept['name'] ?? '')), ' ·');
        ?>
          <div class="dept-ai-row<?= $i === 0 ? ' is-top' : '' ?>">
            <div>
              <?php if ($i === 0): ?><span class="chip" style="margin-right:.35rem">Top</span><?php endif; ?>
              <strong><?= e($label !== '' ? $label : 'Department') ?></strong>
            </div>
            <div class="dept-ai-track"><div class="dept-ai-fill" style="--ready-pct:<?= (int)$pct ?>%;animation-delay:<?= number_format(0.08 * $i, 2) ?>s"></div></div>
            <div class="chip"><?= $count ?> · <?= (int)$pct ?>%</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="grid grid-2" style="margin-top:1rem;align-items:stretch">
    <div class="panel reveal">
      <div class="panel-h">
        <h2>People by role</h2>
        <span class="chip"><?= (int)$peopleTotal ?> active</span>
      </div>
      <p class="muted" style="margin-top:0;font-size:.88rem">Active accounts in this institution — students, professors, and HODs.</p>
      <div class="chart-wrap">
        <canvas id="rolePieChart" aria-label="Role distribution pie chart"></canvas>
        <div class="chart-center">
          <div>
            <strong id="rolePieTotal"><?= (int)$peopleTotal ?></strong>
            <span>people</span>
          </div>
        </div>
      </div>
    </div>

    <div class="panel reveal">
      <h2>Role breakdown</h2>
      <p class="muted" style="margin-top:0;font-size:.88rem">Live counts for this college.</p>
      <div class="form-grid stagger" style="gap:.75rem;margin-top:1rem">
        <div class="role-chip">
          <div style="display:flex;align-items:center;gap:.55rem">
            <span class="role-dot" style="background:#22d3ee"></span>
            <strong>Students</strong>
          </div>
          <span class="chip"><?= $studentN ?></span>
        </div>
        <div class="role-chip">
          <div style="display:flex;align-items:center;gap:.55rem">
            <span class="role-dot" style="background:#a78bfa;animation-delay:.3s"></span>
            <strong>Professors</strong>
          </div>
          <span class="chip"><?= $professorN ?></span>
        </div>
        <div class="role-chip">
          <div style="display:flex;align-items:center;gap:.55rem">
            <span class="role-dot" style="background:#f59e0b;animation-delay:.6s"></span>
            <strong>HODs</strong>
          </div>
          <span class="chip"><?= $hodN ?></span>
        </div>
      </div>
      <?php
        $ready = 0;
        $ready += min(35, (int)$metrics['ai_calls'] > 0 ? 35 : 0);
        $ready += min(35, $metrics['avg_score'] !== null ? 35 : 0);
        $ready += min(30, $peopleTotal > 0 ? 30 : 0);
      ?>
      <div style="margin-top:1.25rem">
        <div class="panel-h" style="margin:0">
          <strong>Readiness index</strong>
          <span class="chip"><?= (int)$ready ?>%</span>
        </div>
        <p class="muted" style="font-size:.82rem;margin:.35rem 0 0">Institution-wide academic documentation readiness for accreditation audits.</p>
        <div class="readiness-bar"><div class="readiness-fill" style="--ready-pct:<?= (int)$ready ?>%"></div></div>
      </div>
    </div>
  </div>

  <div class="panel reveal" style="margin-top:1rem">
    <div class="panel-h">
      <h2>People by department</h2>
      <span class="chip"><?= count($deptPeople) ?> department<?= count($deptPeople) === 1 ? '' : 's' ?></span>
    </div>
    <p class="muted" style="margin-top:0;font-size:.88rem">Active professors, students, and HODs for each department in this institution.</p>
    <?php if (!$deptPeople): ?>
      <div class="empty">No departments yet. Add departments under Institution.</div>
    <?php else: ?>
      <div class="dept-people-grid stagger">
        <?php foreach ($deptPeople as $dept):
          $code = trim((string)($dept['code'] ?? ''));
          $name = trim((string)($dept['name'] ?? ''));
          $title = $code !== '' && $name !== '' ? ($code . ' · ' . $name) : ($name !== '' ? $name : ($code !== '' ? $code : 'Department'));
          $p = (int)($dept['professors'] ?? 0);
          $s = (int)($dept['students'] ?? 0);
          $h = (int)($dept['hods'] ?? 0);
        ?>
          <div class="dept-people-card">
            <h3><?= e($title) ?></h3>
            <div class="dept-people-stats">
              <div class="dept-people-stat">
                <span class="n" style="color:#a78bfa"><?= $p ?></span>
                <span class="l">Professors</span>
              </div>
              <div class="dept-people-stat">
                <span class="n" style="color:#22d3ee"><?= $s ?></span>
                <span class="l">Students</span>
              </div>
              <div class="dept-people-stat">
                <span class="n" style="color:#f59e0b"><?= $h ?></span>
                <span class="l">HODs</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel reveal" style="margin-top:1rem">
    <div class="panel-h">
      <h2>Expenses by department</h2>
      <span class="chip"><?= (int)$expenseYear ?> · <?= e($expenseMonthLabel) ?></span>
    </div>
    <p class="muted" style="margin-top:0;font-size:.88rem">Live totals from Finance expenses — this calendar year and the current month, department-wise.</p>
    <div class="grid grid-2 stagger" style="margin-top:.75rem;margin-bottom:1rem">
      <div class="stat">
        <div class="label">This year (<?= (int)$expenseYear ?>)</div>
        <div class="value" style="font-size:1.35rem"><?= e($formatMoney($expenseYearTotal)) ?></div>
      </div>
      <div class="stat">
        <div class="label">This month (<?= e($expenseMonthLabel) ?>)</div>
        <div class="value" style="font-size:1.35rem"><?= e($formatMoney($expenseMonthTotal)) ?></div>
      </div>
    </div>
    <?php if (!$deptExpenses): ?>
      <div class="empty">No expenses recorded yet. Add them under Finance.</div>
    <?php else: ?>
      <div class="dept-people-grid stagger">
        <?php foreach ($deptExpenses as $dept):
          $code = trim((string)($dept['code'] ?? ''));
          $name = trim((string)($dept['name'] ?? ''));
          $title = $code !== '' && $name !== '' ? ($code . ' · ' . $name) : ($name !== '' ? $name : ($code !== '' ? $code : 'Department'));
          $yearAmt = (float)($dept['year_total'] ?? 0);
          $monthAmt = (float)($dept['month_total'] ?? 0);
        ?>
          <div class="dept-people-card">
            <h3><?= e($title) ?></h3>
            <div class="expense-stat-row">
              <div class="expense-stat">
                <span class="n"><?= e($formatMoney($yearAmt)) ?></span>
                <span class="l">This year</span>
              </div>
              <div class="expense-stat">
                <span class="n"><?= e($formatMoney($monthAmt)) ?></span>
                <span class="l">This month</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php
          $topExpTitle = '';
          $topExpAmt = 0.0;
          if (is_array($topExpenseDept)) {
              $topExpCode = trim((string)($topExpenseDept['code'] ?? ''));
              $topExpName = trim((string)($topExpenseDept['name'] ?? ''));
              $topExpTitle = $topExpCode !== '' && $topExpName !== ''
                  ? ($topExpCode . ' · ' . $topExpName)
                  : ($topExpName !== '' ? $topExpName : $topExpCode);
              $topExpAmt = (float)($topExpenseDept['year_total'] ?? 0);
          }
        ?>
        <?php if ($topExpTitle !== '' && $topExpAmt > 0): ?>
          <div class="dept-people-card">
            <h3>Highest this year</h3>
            <div class="expense-stat-row">
              <div class="expense-stat" style="grid-column:1 / -1">
                <span class="n"><?= e($topExpTitle) ?></span>
                <span class="l"><?= e($formatMoney($topExpAmt)) ?> · <?= (int)$expenseYear ?></span>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function animateCount(el) {
    const raw = el.getAttribute('data-count');
    if (raw === null || el.textContent === '—') return;
    const decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
    const target = parseFloat(raw);
    if (!isFinite(target)) return;
    if (reduce) {
      el.textContent = decimals ? target.toFixed(decimals) : String(Math.round(target));
      return;
    }
    const duration = 900;
    const start = performance.now();
    function frame(now) {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      const val = target * eased;
      el.textContent = decimals ? val.toFixed(decimals) : String(Math.round(val));
      if (t < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  document.querySelectorAll('.analytics-page .value[data-count]').forEach(animateCount);

  function bootChart() {
    const canvas = document.getElementById('rolePieChart');
    if (!canvas || typeof Chart === 'undefined') {
      if (!canvas) return;
      setTimeout(bootChart, 40);
      return;
    }
    const students = <?= $studentN ?>;
    const professors = <?= $professorN ?>;
    const hods = <?= $hodN ?>;
    const total = students + professors + hods;
    new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: ['Students', 'Professors', 'HODs'],
        datasets: [{
          data: total > 0 ? [students, professors, hods] : [1, 1, 1],
          backgroundColor: total > 0
            ? ['#22d3ee', '#a78bfa', '#f59e0b']
            : ['rgba(255,255,255,.12)', 'rgba(255,255,255,.08)', 'rgba(255,255,255,.05)'],
          borderColor: 'rgba(11,9,28,.85)',
          borderWidth: 3,
          hoverOffset: 10
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '68%',
        animation: reduce ? false : {
          animateRotate: true,
          animateScale: true,
          duration: 1200,
          easing: 'easeOutQuart'
        },
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: 'rgba(255,255,255,.78)',
              boxWidth: 12,
              padding: 14,
              font: { family: 'DM Sans, sans-serif', size: 12 }
            }
          },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                if (total < 1) return ' ' + ctx.label + ': 0';
                const v = ctx.raw || 0;
                const pct = Math.round((v / total) * 100);
                return ' ' + ctx.label + ': ' + v + ' (' + pct + '%)';
              }
            }
          }
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootChart);
  } else {
    bootChart();
  }
})();
</script>
