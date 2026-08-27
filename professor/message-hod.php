<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

Auth::requireRole('professor');
$user = Auth::user();
ProfessorHodMessageTools::ensureSchema();

$hod = ProfessorHodMessageTools::findDepartmentHod($user);
$deptId = (int)($user['department_id'] ?? 0);
$deptLabel = ProfessorHodMessageTools::departmentLabel($deptId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');
    if ($action === 'send_hod_message') {
        $result = ProfessorHodMessageTools::professorSend(
            $user,
            (string)post('message'),
            (string)post('title'),
            isset($_FILES['attachment']) && is_array($_FILES['attachment']) ? $_FILES['attachment'] : null
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'Unable to send message.');
        } else {
            flash('success', 'Message sent to your department HOD.');
        }
        redirect('/professor/message-hod');
    }
    if ($action === 'delete_thread') {
        $result = ProfessorHodMessageTools::professorDeleteThread($user, (int)post('thread_id'));
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'Unable to delete message.');
        } else {
            flash('success', 'Conversation deleted for you and your HOD.');
        }
        redirect('/professor/message-hod');
    }
}

if ($threadRead = (int)get('read_thread')) {
    ProfessorHodMessageTools::markThreadReadForProfessor($user, $threadRead);
    redirect('/professor/message-hod');
}

$threads = ProfessorHodMessageTools::threadsForProfessor($user, 30);

render_header('Message HOD', 'message-hod', [
    'subtitle' => 'Send complaints or messages to your department HOD (PDF/DOCX allowed)',
]);
?>
<?php if ($deptId < 1): ?>
<div class="panel">
  <div class="alert alert-warn">Your professor account is not linked to a department. Contact College Admin.</div>
</div>
<?php elseif (!$hod): ?>
<div class="panel">
  <div class="alert alert-warn">No active HOD is assigned for <?= e($deptLabel) ?>. Contact College Admin.</div>
</div>
<?php else: ?>

<div class="grid grid-2" style="align-items:start">
  <div class="panel">
    <div class="panel-h">
      <h2 style="margin:0;font-size:1.05rem">Message your HOD</h2>
      <span class="chip"><?= e($deptLabel) ?></span>
    </div>
    <p class="muted" style="font-size:.85rem;margin:.35rem 0 .85rem">
      Messages go only to <strong><?= e((string)$hod['full_name']) ?></strong> (your department HOD).
      ECE professors reach ECE HOD; CSE professors reach CSE HOD.
    </p>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send_hod_message">
      <div class="form-row">
        <label for="hod_title">Subject <span class="muted">(optional)</span></label>
        <input type="text" name="title" id="hod_title" maxlength="200" placeholder="Complaint / request subject">
      </div>
      <div class="form-row">
        <label for="hod_message">Message</label>
        <textarea name="message" id="hod_message" rows="6" maxlength="4000" required placeholder="Write your message to the HOD…"></textarea>
        <div class="muted" style="font-size:.8rem;margin-top:.25rem">Max 4000 characters.</div>
      </div>
      <div class="form-row">
        <label for="hod_attachment">Attachment <span class="muted">(optional)</span></label>
        <input type="file" name="attachment" id="hod_attachment" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        <div class="muted" style="font-size:.8rem;margin-top:.25rem">Supported: PDF, DOCX · Max 10 MB.</div>
      </div>
      <button class="btn btn-primary" type="submit">Send to HOD</button>
    </form>
  </div>

  <div class="panel" style="max-height:min(75vh,720px);overflow:auto">
    <div class="panel-h"><strong>Conversation history</strong></div>
    <?php if (!$threads): ?>
      <div class="empty">No messages yet. Send your first message to the HOD.</div>
    <?php else: ?>
      <?php foreach ($threads as $thread):
        $root = $thread['root'];
        $replies = $thread['replies'];
        $tid = (int)($root['thread_id'] ?? $root['id']);
        $hasAtt = trim((string)($root['attachment_path'] ?? '')) !== '';
        $attName = (string)($root['attachment_original_name'] ?? '');
        $attExt = strtolower(pathinfo($attName, PATHINFO_EXTENSION));
      ?>
        <div style="padding:.9rem 0;border-bottom:1px solid var(--line)">
          <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:flex-start">
            <strong><?= e((string)$root['title']) ?></strong>
            <div style="display:flex;align-items:center;gap:.35rem;flex-shrink:0">
              <span class="chip">You → HOD</span>
              <form method="post" style="margin:0" onsubmit="return confirm('Delete this conversation for you and your HOD?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_thread">
                <input type="hidden" name="thread_id" value="<?= $tid ?>">
                <button class="btn btn-sm btn-ghost" type="submit" style="color:#f87171">Delete</button>
              </form>
            </div>
          </div>
          <div style="white-space:pre-wrap;font-size:.92rem;margin-top:.3rem"><?= e((string)$root['body']) ?></div>
          <?php if ($hasAtt && $attName !== ''): ?>
            <div style="margin-top:.4rem;font-size:.88rem">
              📄 <?= e($attName) ?>
              <a class="btn btn-sm btn-ghost" style="margin-left:.25rem" href="<?= e(base_url('/api/messages/attachment?source=professor_hod&id=' . (int)$root['id'])) ?>">Download<?= $attExt === 'pdf' ? ' PDF' : ($attExt === 'docx' ? ' DOCX' : '') ?></a>
            </div>
          <?php endif; ?>
          <div class="chip-row" style="margin-top:.35rem">
            <span class="chip"><?= e((string)$root['created_at']) ?></span>
            <?php if ($replies): ?><span class="chip">HOD replied</span><?php else: ?><span class="chip">Awaiting reply</span><?php endif; ?>
          </div>

          <?php foreach ($replies as $r):
            $rAtt = trim((string)($r['attachment_path'] ?? '')) !== '';
            $rName = (string)($r['attachment_original_name'] ?? '');
            $rExt = strtolower(pathinfo($rName, PATHINFO_EXTENSION));
          ?>
            <div style="margin-top:.75rem;padding:.75rem;border-radius:12px;background:rgba(124,58,237,.08);border:1px solid rgba(167,139,250,.25)">
              <div style="display:flex;justify-content:space-between;gap:.5rem">
                <strong style="font-size:.9rem">HOD reply</strong>
                <span class="chip"><?= e((string)$r['created_at']) ?></span>
              </div>
              <div style="white-space:pre-wrap;font-size:.9rem;margin-top:.3rem"><?= e((string)$r['body']) ?></div>
              <?php if ($rAtt && $rName !== ''): ?>
                <div style="margin-top:.35rem;font-size:.85rem">
                  📄 <?= e($rName) ?>
                  <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/api/messages/attachment?source=professor_hod&id=' . (int)$r['id'])) ?>">Download<?= $rExt === 'pdf' ? ' PDF' : ($rExt === 'docx' ? ' DOCX' : '') ?></a>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php render_footer(); ?>
