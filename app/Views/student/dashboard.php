<?php
/** @var array $attendance */
/** @var int $pendingCount */
/** @var string $pendingHint */
/** @var array $marks */
/** @var array $exam */
/** @var list<array{title:string,meta:string,when:string,tone:string}> $upcoming */
/** @var list<array{title:string,detail:string,when:string}> $notices */
$attendance = $attendance ?? ['percent' => null, 'hint' => 'No classes yet', 'tone' => 'none'];
$pendingCount = (int)($pendingCount ?? 0);
$pendingHint = (string)($pendingHint ?? '');
$marks = $marks ?? ['percent' => null, 'hint' => 'Not published yet'];
$exam = $exam ?? ['days' => null, 'hint' => 'No exam date set'];
$upcoming = $upcoming ?? [];
$notices = $notices ?? [];
$user = \Auth::user();
$firstName = explode(' ', trim((string)($user['full_name'] ?? 'Student')))[0];
if ($firstName === '') {
    $firstName = 'Student';
}
?>
<div class="stu-dash">
  <section class="welcome-banner">
    <div>
      <h2>Welcome back, <?= e($firstName) ?></h2>
      <p>Your courses, assignments, and study assistant in one place</p>
    </div>
    <a class="btn btn-primary btn-shine" href="<?= e(url('/student/ask-ai')) ?>"><?= icon('ai') ?> Ask AI</a>
  </section>
  <div class="stu-dash-kpis">
    <a class="stu-dash-kpi is-att is-<?= e((string)$attendance['tone']) ?>" href="<?= e(url('/student/attendance')) ?>">
      <strong><?= $attendance['percent'] === null ? '—' : e((string)$attendance['percent']) ?></strong>
      <span>Overall attendance</span>
      <em><?= e((string)$attendance['hint']) ?></em>
    </a>
    <a class="stu-dash-kpi is-pending" href="<?= e(url('/student/assignments')) ?>">
      <strong><?= $pendingCount ?></strong>
      <span>Pending assignments</span>
      <em><?= e($pendingHint) ?></em>
    </a>
    <a class="stu-dash-kpi is-marks" href="<?= e(url('/student/marks')) ?>">
      <strong><?= $marks['percent'] === null ? '—' : e((string)$marks['percent']) ?></strong>
      <span>Avg internal marks</span>
      <em><?= e((string)$marks['hint']) ?></em>
    </a>
    <a class="stu-dash-kpi is-exam" href="<?= e(url('/student/calendar')) ?>">
      <strong><?= $exam['days'] === null ? '—' : (int)$exam['days'] ?></strong>
      <span>Days to exams</span>
      <em><?= e((string)$exam['hint']) ?></em>
    </a>
  </div>

  <div class="stu-dash-split">
    <section class="stu-dash-panel">
      <div class="stu-dash-panel-h">
        <h2><?= icon('calendar', 'icon-inline') ?> Upcoming this week</h2>
      </div>
      <?php if (!$upcoming): ?>
        <div class="empty">Nothing scheduled this week.</div>
      <?php else: ?>
        <div class="stu-dash-list">
          <?php foreach ($upcoming as $item): ?>
            <div class="stu-dash-row">
              <div>
                <strong><?= e($item['title']) ?></strong>
                <?php if ($item['meta'] !== ''): ?><span><?= e($item['meta']) ?></span><?php endif; ?>
              </div>
              <em class="is-<?= e($item['tone']) ?>"><?= e($item['when']) ?></em>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="stu-dash-panel">
      <div class="stu-dash-panel-h">
        <h2><?= icon('bell', 'icon-inline') ?> Recent notices</h2>
      </div>
      <?php if (!$notices): ?>
        <div class="empty">No notices yet.</div>
      <?php else: ?>
        <div class="stu-dash-list">
          <?php foreach ($notices as $notice): ?>
            <div class="stu-dash-row">
              <div>
                <strong><?= e($notice['title']) ?></strong>
                <?php if ($notice['detail'] !== ''): ?><span><?= e($notice['detail']) ?></span><?php endif; ?>
              </div>
              <em><?= e($notice['when']) ?></em>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <a class="stu-dash-more" href="<?= e(url('/student/notifications')) ?>">View all notices</a>
    </section>
  </div>
</div>
