<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
$subjects = courses_for_student($user);
$subjectIds = array_map(static fn($s) => (int)$s['id'], $subjects);

$selectedSubjectId = (int)(get('subject_id') ?: post('subject_id') ?: 0);
if ($selectedSubjectId > 0 && !in_array($selectedSubjectId, $subjectIds, true)) {
    $selectedSubjectId = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post('action');

    if ($action === 'delete_message') {
        $messageId = (int)post('message_id');
        $row = $messageId > 0
            ? Database::fetch(
                'SELECT m.id, m.chat_id, c.subject_id
                 FROM ai_chat_messages m
                 JOIN ai_chats c ON c.id = m.chat_id
                 WHERE m.id = ? AND c.user_id = ?',
                [$messageId, (int)$user['id']]
            )
            : null;
        if (!$row) {
            flash('error', 'Message not found.');
            redirect('/student/ask-ai');
        }
        $cid = (int)$row['chat_id'];
        $sid = (int)($row['subject_id'] ?? 0);
        Database::query('DELETE FROM ai_chat_messages WHERE id = ?', [$messageId]);
        $left = Database::fetch('SELECT COUNT(*) AS c FROM ai_chat_messages WHERE chat_id = ?', [$cid]);
        $q = $sid > 0 ? ('?subject_id=' . $sid) : '';
        if ((int)($left['c'] ?? 0) < 1) {
            Database::query('DELETE FROM ai_chats WHERE id = ? AND user_id = ?', [$cid, (int)$user['id']]);
            flash('success', 'Message deleted.');
            redirect('/student/ask-ai' . $q);
        }
        flash('success', 'Message deleted.');
        redirect('/student/ask-ai' . ($q !== '' ? $q . '&' : '?') . 'chat_id=' . $cid);
    }

    if ($action === 'delete_chat') {
        $deleteChatId = (int)post('chat_id');
        $own = $deleteChatId > 0
            ? Database::fetch('SELECT id, subject_id FROM ai_chats WHERE id = ? AND user_id = ?', [$deleteChatId, (int)$user['id']])
            : null;
        if (!$own) {
            flash('error', 'Chat not found.');
            redirect('/student/ask-ai');
        }
        $sid = (int)($own['subject_id'] ?? 0);
        Database::query('DELETE FROM ai_chat_messages WHERE chat_id = ?', [$deleteChatId]);
        Database::query('DELETE FROM ai_chats WHERE id = ? AND user_id = ?', [$deleteChatId, (int)$user['id']]);
        flash('success', 'Chat deleted.');
        redirect('/student/ask-ai' . ($sid > 0 ? ('?subject_id=' . $sid) : ''));
    }
}

$chatId = (int)get('chat_id');
$chat = $chatId
    ? Database::fetch('SELECT * FROM ai_chats WHERE id=? AND user_id=?', [$chatId, $user['id']])
    : null;
if ($chatId && !$chat) {
    $chatId = 0;
}
// Opening a chat locks the subject to that chat's subject.
if ($chat && (int)($chat['subject_id'] ?? 0) > 0) {
    $selectedSubjectId = (int)$chat['subject_id'];
}
if ($selectedSubjectId < 1 && $subjects) {
    $selectedSubjectId = (int)$subjects[0]['id'];
}

$chats = $selectedSubjectId > 0
    ? Database::fetchAll(
        'SELECT * FROM ai_chats WHERE user_id=? AND subject_id=? ORDER BY id DESC LIMIT 20',
        [(int)$user['id'], $selectedSubjectId]
    )
    : [];

// If selected chat is not for this subject, clear it.
if ($chat && (int)($chat['subject_id'] ?? 0) !== $selectedSubjectId) {
    $chat = null;
    $chatId = 0;
}

$messages = $chat
    ? Database::fetchAll('SELECT * FROM ai_chat_messages WHERE chat_id=? ORDER BY id', [$chat['id']])
    : [];

$selectedSubjectName = '';
foreach ($subjects as $s) {
    if ((int)$s['id'] === $selectedSubjectId) {
        $selectedSubjectName = (string)$s['name'];
        break;
    }
}

render_header('Ask AI', 'ask', ['subtitle' => 'Study assistant grounded in your course materials']);
?>
<div class="grid grid-2">
  <div class="panel">
    <?php if (!$subjects): ?>
      <div class="empty">No subjects are currently assigned to your Year, Section, and Semester.</div>
    <?php else: ?>
    <form id="askForm" class="form-grid" method="post" action="<?= e(base_url('/api/ai?module=ask_ai')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="module" value="ask_ai">
      <input type="hidden" name="chat_id" id="askChatId" value="<?= $chatId ?>">
      <div class="form-row">
        <label>Subject context</label>
        <select name="subject_id" id="askSubjectId" required>
          <option value="">Select a subject</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $selectedSubjectId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>Question</label><textarea name="question" required placeholder="Explain normalization with an example from our syllabus"></textarea></div>
      <button class="btn btn-accent" type="submit">Ask</button>
    </form>
    <div id="askOut" style="margin-top:1rem"></div>
    <?php endif; ?>
  </div>
  <div class="panel">
    <div class="panel-h" style="align-items:center;margin-bottom:.65rem">
      <h3 style="margin:0">Recent chats</h3>
      <?php if ($selectedSubjectName !== ''): ?>
        <span class="chip"><?= e($selectedSubjectName) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($selectedSubjectId < 1): ?>
      <div class="empty">Select a subject to see its chats.</div>
    <?php elseif (!$chats): ?>
      <div class="empty">No chats yet for this subject. Ask a question to start.</div>
    <?php else: ?>
      <div class="chat-list">
        <?php foreach ($chats as $c): ?>
          <div class="chat-link-row" style="display:flex;align-items:center;gap:.35rem;margin-bottom:.35rem">
            <a class="chat-link <?= $chatId === (int)$c['id'] ? 'is-active' : '' ?>" style="flex:1;margin:0" href="?subject_id=<?= $selectedSubjectId ?>&chat_id=<?= (int)$c['id'] ?>">
              <?= e($c['title'] ?: ('Chat #'.$c['id'])) ?>
            </a>
            <form method="post" style="margin:0;flex-shrink:0" onsubmit="return confirm('Delete this entire chat?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_chat">
              <input type="hidden" name="chat_id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-ghost" type="submit" style="color:#f87171">Delete</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($messages): ?>
      <div class="chat-thread">
        <?php foreach ($messages as $m): ?>
          <?php $role = strtolower((string)$m['role']); ?>
          <div class="chat-msg <?= $role === 'user' ? 'chat-msg-user' : 'chat-msg-assistant' ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
              <strong><?= e($role === 'user' ? 'You' : 'Study assistant') ?></strong>
              <form method="post" style="margin:0;flex-shrink:0" onsubmit="return confirm('Delete this message?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_message">
                <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit" style="color:#f87171;padding:.15rem .45rem">Delete</button>
              </form>
            </div>
            <div class="chat-msg-body"><?= nl2br(e($m['content'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php if ($subjects): ?>
<script>
(function () {
  var subjectSel = document.getElementById('askSubjectId');
  var chatIdInput = document.getElementById('askChatId');
  if (subjectSel) {
    subjectSel.addEventListener('change', function () {
      var sid = subjectSel.value;
      if (!sid) {
        location = <?= json_encode(url('/student/ask-ai')) ?>;
        return;
      }
      location = <?= json_encode(url('/student/ask-ai')) ?> + '?subject_id=' + encodeURIComponent(sid);
    });
  }

  document.getElementById('askForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    if (!fd.get('subject_id')) {
      alert('Please select a subject first.');
      return;
    }
    const btn = form.querySelector('[type=submit]');
    btn.disabled = true; btn.textContent = 'Thinking...';
    try {
      const res = await fetch(form.action, { method:'POST', body: fd, headers:{'X-CSRF-TOKEN': fd.get('csrf')} });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      document.getElementById('askOut').innerHTML = '<div class="chat-msg chat-msg-assistant"><strong>Study assistant</strong><div class="chat-msg-body">' + (data.data.answer || '').replace(/</g,'&lt;').replace(/\n/g,'<br>') + '</div></div>';
      var sid = fd.get('subject_id');
      if (data.chat_id) {
        location = '?subject_id=' + encodeURIComponent(sid) + '&chat_id=' + data.chat_id;
      }
    } catch (err) { alert(err.message); }
    finally { btn.disabled=false; btn.textContent='Ask'; }
  });
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
