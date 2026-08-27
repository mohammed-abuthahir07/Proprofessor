<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('student');
Auth::refresh();
$user = Auth::user();
$subjects = courses_for_student($user);
$chats = Database::fetchAll('SELECT * FROM ai_chats WHERE user_id=? ORDER BY id DESC LIMIT 10', [$user['id']]);
$chatId = (int)get('chat_id');
$chat = $chatId ? Database::fetch('SELECT * FROM ai_chats WHERE id=? AND user_id=?', [$chatId, $user['id']]) : null;
$messages = $chat ? Database::fetchAll('SELECT * FROM ai_chat_messages WHERE chat_id=? ORDER BY id', [$chat['id']]) : [];
if ($chatId && !$chat) {
    $chatId = 0;
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
      <input type="hidden" name="chat_id" value="<?= $chatId ?>">
      <div class="form-row">
        <label>Subject context</label>
        <select name="subject_id" required>
          <option value="">Select a subject</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
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
    <h3>Recent chats</h3>
    <?php if (!$chats): ?>
      <div class="empty">No chats yet. Ask a question to start.</div>
    <?php else: ?>
      <div class="chat-list">
        <?php foreach ($chats as $c): ?>
          <a class="chat-link <?= $chatId === (int)$c['id'] ? 'is-active' : '' ?>" href="?chat_id=<?= (int)$c['id'] ?>">
            <?= e($c['title'] ?: ('Chat #'.$c['id'])) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($messages): ?>
      <div class="chat-thread">
        <?php foreach ($messages as $m): ?>
          <?php $role = strtolower((string)$m['role']); ?>
          <div class="chat-msg <?= $role === 'user' ? 'chat-msg-user' : 'chat-msg-assistant' ?>">
            <strong><?= e($role === 'user' ? 'You' : 'Study assistant') ?></strong>
            <div class="chat-msg-body"><?= nl2br(e($m['content'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php if ($subjects): ?>
<script>
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
    if (data.chat_id) location = '?chat_id=' + data.chat_id;
  } catch (err) { alert(err.message); }
  finally { btn.disabled=false; btn.textContent='Ask'; }
});
</script>
<?php endif; ?>
<?php render_footer(); ?>
