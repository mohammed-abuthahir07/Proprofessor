<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin');
$user = Auth::user();
$isProfessor = ($user['role'] ?? '') === 'professor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $aiAction = (string)post('ai_action', '');
    if ($aiAction !== '') {
        if (!$isProfessor) {
            if ((string)post('ajax', '') === '1') {
                json_response(['ok' => false, 'error' => 'Only professors can connect an AI provider.'], 403);
            }
            flash('error', 'Only professors can connect an AI provider.');
            redirect('/professor/settings.php');
        }
        $professorId = (int)$user['id'];
        $provider = (string)post('ai_provider', '');
        $model = trim((string)post('ai_model_custom', '')) !== ''
            ? (string)post('ai_model_custom')
            : (string)post('ai_model', '');
        $apiKey = trim((string)post('ai_api_key', ''));

        if ($aiAction === 'remove') {
            ProfessorAiSettings::disconnect($professorId);
            flash('success', 'AI connection removed. Existing course plans, lessons, questions, PPTs and assignments were not changed.');
            redirect('/professor/settings.php');
        }

        if ($apiKey === '' && in_array($aiAction, ['test', 'connect'], true)) {
            $stored = ProfessorAiSettings::activeSecretForProfessor($professorId);
            if ($stored && $stored['provider'] === ProfessorAiSettings::normalizeProvider($provider)) {
                $apiKey = $stored['api_key'];
                if ($model === '') {
                    $model = $stored['model'];
                }
            }
        }

        if ($aiAction === 'test') {
            $out = ProfessorAiSettings::testConnection($provider, $model, $apiKey);
            if ((string)post('ajax', '') === '1') {
                json_response($out, !empty($out['ok']) ? 200 : 400);
            }
            flash(!empty($out['ok']) ? 'success' : 'error', (string)($out['message'] ?? $out['error'] ?? 'Test failed.'));
            redirect('/professor/settings.php');
        }

        if ($aiAction === 'connect') {
            $out = ProfessorAiSettings::connect($professorId, $provider, $model, $apiKey);
            if ((string)post('ajax', '') === '1') {
                json_response($out, !empty($out['ok']) ? 200 : 400);
            }
            flash(!empty($out['ok']) ? 'success' : 'error', (string)($out['message'] ?? $out['error'] ?? 'Could not connect.'));
            redirect('/professor/settings.php');
        }

        flash('error', 'Unknown AI action.');
        redirect('/professor/settings.php');
    }

    $existing = json_decode((string)($user['preferences'] ?? '{}'), true) ?: [];
    $merged = NotificationService::mergePreferencePost($existing, [
        'email_notifications' => post('email_notifications'),
        'digest_mode' => post('digest_mode', 'immediate'),
        'theme' => post('theme', 'light'),
        'notification_channels' => post('notification_channels') ?: [],
    ]);
    Database::update('users', [
        'full_name' => trim((string)post('full_name')),
        'phone' => trim((string)post('phone')),
        'preferences' => json_encode($merged, JSON_UNESCAPED_UNICODE),
    ], 'id = :id', ['id' => $user['id']]);
    if (post('new_password')) {
        Database::update('users', [
            'password_hash' => password_hash((string)post('new_password'), PASSWORD_BCRYPT),
        ], 'id = :id', ['id' => $user['id']]);
    }
    Auth::refresh();
    flash('success', 'Settings saved.');
    redirect('/professor/settings.php');
}

$notifPrefs = NotificationService::preferencesFromUser($user);
$prefs = $notifPrefs;
$channels = $prefs['notification_channels'];
$providers = NotificationService::allProviderStatuses();
$categories = [
    'assignments' => 'Assignments',
    'attendance' => 'Attendance',
    'course_plans' => 'Course Plans',
    'approvals' => 'Approvals',
    'system' => 'System',
    'ai' => 'AI',
];

$aiConn = $isProfessor ? ProfessorAiSettings::publicForProfessor((int)$user['id']) : null;
$aiModels = ProfessorAiSettings::modelsByProvider();
$changeProvider = $isProfessor && (string)get('ai') === 'change';

render_header('Settings', 'settings', ['subtitle' => 'Profile & workspace']);
?>
<div class="settings-page settings-grid">
  <form method="post" class="settings-form-contents">
    <?= csrf_field() ?>
    <div class="panel settings-panel">
      <h3 class="settings-ai-title">Profile</h3>
      <div class="form-grid">
        <div class="form-row"><label>Full name</label><input name="full_name" value="<?= e($user['full_name']) ?>" autocomplete="name"></div>
        <div class="form-row"><label>Email</label><input value="<?= e($user['email']) ?>" disabled></div>
        <div class="form-row"><label>Phone</label><input name="phone" value="<?= e((string)$user['phone']) ?>" autocomplete="tel"></div>
        <div class="form-row"><label>New password</label><input type="password" name="new_password" placeholder="Leave blank to keep" autocomplete="new-password"></div>
      </div>
      <label class="settings-check">
        <input type="checkbox" name="email_notifications" value="1" <?= !empty($prefs['email_notifications']) ? 'checked' : '' ?>>
        <span>Email notifications (default for categories)</span>
      </label>
      <div class="form-row">
        <label>Digest mode</label>
        <select name="digest_mode">
          <option value="immediate" <?= $prefs['digest_mode'] === 'immediate' ? 'selected' : '' ?>>Immediate</option>
          <option value="daily" <?= $prefs['digest_mode'] === 'daily' ? 'selected' : '' ?>>Daily Digest</option>
          <option value="weekly" <?= $prefs['digest_mode'] === 'weekly' ? 'selected' : '' ?>>Weekly Digest</option>
        </select>
        <div class="muted settings-help">Digest summarizes your feed; individual notifications still appear.</div>
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>

    <div class="panel settings-panel">
      <h3 class="settings-ai-title">Delivery preferences</h3>
      <div class="muted settings-help">
        WhatsApp: <?= e($providers['whatsapp']['label']) ?> · SMS: <?= e($providers['sms']['label']) ?>
        <?php if (!$providers['whatsapp']['configured'] || !$providers['sms']['configured']): ?>
          (enable in server config when ready — no fake sends)
        <?php endif; ?>
      </div>
      <div class="settings-channel-list">
        <?php foreach ($categories as $key => $label):
            $row = $channels[$key] ?? ['in_app' => true, 'email' => false, 'whatsapp' => false, 'sms' => false];
        ?>
          <div class="settings-channel-card">
            <div class="settings-channel-title"><?= e($label) ?></div>
            <div class="settings-channel-opts">
              <label>
                <input type="hidden" name="notification_channels[<?= e($key) ?>][in_app]" value="0">
                <input type="checkbox" name="notification_channels[<?= e($key) ?>][in_app]" value="1" <?= !empty($row['in_app']) ? 'checked' : '' ?>>
                In-App
              </label>
              <label>
                <input type="hidden" name="notification_channels[<?= e($key) ?>][email]" value="0">
                <input type="checkbox" name="notification_channels[<?= e($key) ?>][email]" value="1" <?= !empty($row['email']) ? 'checked' : '' ?>>
                Email
              </label>
              <label>
                <input type="hidden" name="notification_channels[<?= e($key) ?>][whatsapp]" value="0">
                <input type="checkbox" name="notification_channels[<?= e($key) ?>][whatsapp]" value="1" <?= !empty($row['whatsapp']) ? 'checked' : '' ?> <?= !$providers['whatsapp']['configured'] ? 'title="Provider not configured"' : '' ?>>
                WhatsApp
              </label>
              <label>
                <input type="hidden" name="notification_channels[<?= e($key) ?>][sms]" value="0">
                <input type="checkbox" name="notification_channels[<?= e($key) ?>][sms]" value="1" <?= !empty($row['sms']) ? 'checked' : '' ?> <?= !$providers['sms']['configured'] ? 'title="Provider not configured"' : '' ?>>
                SMS
              </label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </form>

  <?php if ($isProfessor):
    $openaiDocs = ProfessorAiSettings::docsUrl('openai');
    $geminiDocs = ProfessorAiSettings::docsUrl('gemini');
    $claudeDocs = ProfessorAiSettings::docsUrl('claude');
  ?>
  <div class="panel settings-panel settings-ai-panel" id="ai-provider">
    <h3 class="settings-ai-title">AI Provider</h3>
    <p class="muted settings-help">Choose your AI provider. Only one provider is active. Changing provider or model does not change existing course plans, lessons, questions, PPTs, or assignments.</p>

    <?php if ($aiConn && !$changeProvider): ?>
      <div class="settings-channel-card settings-ai-status">
        <div class="settings-channel-title">
          <?= e($aiConn['provider_label']) ?>
          <span class="badge badge-success">Connected</span>
        </div>
        <div class="muted settings-help">Model: <?= e((string)$aiConn['model']) ?></div>
        <div class="muted settings-help">API key: <?= e((string)$aiConn['masked_key']) ?></div>
        <div class="settings-ai-actions">
          <a class="btn btn-sm" href="<?= e(base_url('/professor/settings.php?ai=change')) ?>">Change Provider</a>
          <form method="post" class="settings-ai-inline-form" onsubmit="return confirm('Remove the AI connection? Existing generated content will not be deleted.');">
            <?= csrf_field() ?>
            <input type="hidden" name="ai_action" value="remove">
            <button class="btn btn-sm" type="submit">Remove Connection</button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <p class="muted settings-help"><strong>Status:</strong> No AI provider connected.</p>
      <form method="post" class="form-grid" id="ai-provider-form" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="ajax" id="ai_ajax" value="0">
        <div class="form-row">
          <label for="ai_provider">Provider</label>
          <select name="ai_provider" id="ai_provider">
            <option value="openai">OpenAI</option>
            <option value="gemini">Gemini</option>
            <option value="claude">Claude</option>
          </select>
        </div>
        <div class="form-row">
          <label for="ai_model">Model</label>
          <select name="ai_model" id="ai_model"></select>
        </div>
        <div class="form-row">
          <label for="ai_model_custom">Custom model (optional)</label>
          <input name="ai_model_custom" id="ai_model_custom" placeholder="Leave blank to use the selected model" autocomplete="off">
        </div>
        <div class="form-row">
          <label for="ai_api_key">API Key</label>
          <input type="password" name="ai_api_key" id="ai_api_key" placeholder="<?= $aiConn ? e((string)$aiConn['masked_key']) : 'Paste your API key' ?>" autocomplete="new-password">
          <div class="muted settings-help">The key is sent to the server only. It is never placed in page URLs.</div>
        </div>
        <div id="ai-provider-msg" class="settings-ai-msg" hidden></div>
        <div class="settings-ai-actions">
          <button class="btn" type="button" id="ai-test-btn">Test Connection</button>
          <button class="btn btn-primary" type="submit" name="ai_action" value="connect">Connect AI</button>
          <?php if ($aiConn): ?>
            <a class="btn" href="<?= e(base_url('/professor/settings.php')) ?>">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
      <script>
        (function () {
          const models = <?= json_encode($aiModels, JSON_UNESCAPED_UNICODE) ?>;
          const providerEl = document.getElementById('ai_provider');
          const modelEl = document.getElementById('ai_model');
          const form = document.getElementById('ai-provider-form');
          const msg = document.getElementById('ai-provider-msg');
          const testBtn = document.getElementById('ai-test-btn');
          const ajaxField = document.getElementById('ai_ajax');
          function fillModels() {
            const list = models[providerEl.value] || [];
            modelEl.innerHTML = '';
            list.forEach(function (m, i) {
              const opt = document.createElement('option');
              opt.value = m;
              opt.textContent = m;
              if (i === 0) opt.selected = true;
              modelEl.appendChild(opt);
            });
          }
          fillModels();
          providerEl.addEventListener('change', fillModels);
          function showMsg(ok, text) {
            msg.hidden = false;
            msg.className = 'settings-ai-msg alert ' + (ok ? 'alert-success' : 'alert-error');
            msg.textContent = text;
          }
          testBtn.addEventListener('click', async function () {
            const original = testBtn.textContent;
            testBtn.disabled = true;
            testBtn.textContent = 'Testing...';
            ajaxField.value = '1';
            const fd = new FormData(form);
            fd.set('ai_action', 'test');
            fd.set('ajax', '1');
            try {
                const res = await fetch(form.getAttribute('action') || window.location.pathname, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': fd.get('csrf') || '', 'Accept': 'application/json' },
                body: fd
              });
              const data = await res.json();
              showMsg(!!data.ok, data.message || data.error || (data.ok ? 'Connection successful.' : 'Connection failed.'));
            } catch (err) {
              showMsg(false, 'Could not test the connection. Please try again.');
            } finally {
              ajaxField.value = '0';
              testBtn.disabled = false;
              testBtn.textContent = original;
            }
          });
        })();
      </script>
    <?php endif; ?>
  </div>
    <aside class="panel settings-panel settings-ai-help">
      <h3 class="settings-ai-title">How to get an API key?</h3>
      <p class="muted settings-help">API keys belong to your own provider account. ProProfessor does not provide or include provider API keys.</p>
      <div class="settings-ai-help-block">
        <strong>OpenAI</strong>
        <ol>
          <li>Open the <a href="<?= e($openaiDocs ?: 'https://platform.openai.com/api-keys') ?>" target="_blank" rel="noopener noreferrer">OpenAI API keys</a> page.</li>
          <li>Sign in to your OpenAI account.</li>
          <li>Create an API key from the API keys section.</li>
          <li>Copy the API key.</li>
          <li>Return here and paste it into the API Key field.</li>
        </ol>
      </div>
      <div class="settings-ai-help-block">
        <strong>Gemini</strong>
        <ol>
          <li>Open <a href="<?= e($geminiDocs ?: 'https://aistudio.google.com/apikey') ?>" target="_blank" rel="noopener noreferrer">Google AI Studio</a>.</li>
          <li>Sign in with your Google account.</li>
          <li>Open the API key section.</li>
          <li>Create or copy your Gemini API key.</li>
          <li>Return here and paste it into the API Key field.</li>
        </ol>
      </div>
      <div class="settings-ai-help-block">
        <strong>Claude</strong>
        <ol>
          <li>Open the <a href="<?= e($claudeDocs ?: 'https://console.anthropic.com/settings/keys') ?>" target="_blank" rel="noopener noreferrer">Anthropic API keys</a> page.</li>
          <li>Sign in to your Anthropic account.</li>
          <li>Create an API key.</li>
          <li>Copy the API key.</li>
          <li>Return here and paste it into the API Key field.</li>
        </ol>
      </div>
    </aside>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
