<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin', 'student');
$user = Auth::user();
$id = (int)get('id');
$isEditor = in_array((string)($user['role'] ?? ''), ['professor', 'admin'], true);
$home = ($user['role'] ?? '') === 'student' ? '/student/notes.php' : '/professor/ppt.php';

if ($isEditor && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireRole('professor', 'admin');
    verify_csrf();
    $pptPost = PresentationTools::ownedPresentation($user, $id);
    if (!$pptPost) {
        flash('error', 'Not found or access denied.');
        redirect($home);
    }
    $action = (string)post('action', '');
    if ($action === 'regen_slide') {
        $index = (int)post('slide_index', -1);
        $instruction = trim((string)post('regen_instruction', ''));
        $result = PresentationTools::regenerateSlide($user, $pptPost, $index, $instruction);
        if (!empty($result['ok'])) {
            flash('success', 'Slide ' . ($index + 1) . ' regenerated. Other slides unchanged.');
        } else {
            flash('error', (string)($result['error'] ?? 'Could not regenerate slide. Original slide preserved.'));
        }
        redirect('/professor/ppt-view.php?id=' . $id);
    }
    if ($action === 'narration') {
        if (PresentationTools::narrationConfigured()) {
            flash('error', 'Narration provider is marked configured but no voice adapter is installed yet.');
        } else {
            flash('error', 'AI narration requires a configured voice provider in config (narration.enabled + provider). No audio was generated.');
        }
        redirect('/professor/ppt-view.php?id=' . $id);
    }
    if ($action === 'google_slides') {
        if (PresentationTools::googleSlidesConfigured()) {
            flash('error', 'Google Slides credentials are present but OAuth export is not wired yet.');
        } else {
            flash('error', 'Google Slides integration requires configuration (google_slides.enabled + client_id). No share URL was created.');
        }
        redirect('/professor/ppt-view.php?id=' . $id);
    }
}

$ppt = Database::fetch('SELECT * FROM presentations WHERE id = ?', [$id]);
if (!$ppt || !presentation_accessible($user, $ppt)) {
    flash('error', 'Not found');
    redirect($home);
}
$slides = json_decode($ppt['slides'] ?: '[]', true) ?: [];
$branding = PresentationTools::brandingForPresentation($user, $ppt);
$saveUrl = base_url('/professor/ppt-download.php?id=' . $id);
$pdfUrl = base_url('/professor/ppt-pdf.php?id=' . $id);
$handoutUrl = base_url('/professor/ppt-handout.php?id=' . $id);
$narrationOk = PresentationTools::narrationConfigured();
$googleOk = PresentationTools::googleSlidesConfigured();

$actionsHtml = '<a class="btn btn-accent btn-sm topbar-save" href="' . e($saveUrl) . '">' . icon('download') . ' Save PPT</a>';
render_header($ppt['title'], 'ppt', [
    'subtitle' => count($slides) . ' slides · ' . $branding['name'],
    'actions' => $actionsHtml,
]);
?>
<div class="deck-toolbar">
  <p class="deck-toolbar-hint">
    Institution branding: <strong><?= e($branding['name']) ?></strong>
    · Speaker notes included in PPTX
    · Colors applied from your institution settings
  </p>
  <div class="deck-toolbar-actions">
    <a class="btn btn-accent" href="<?= e($saveUrl) ?>"><?= icon('download') ?> Export PPTX</a>
    <a class="btn btn-primary" href="<?= e($pdfUrl) ?>">Export PDF</a>
    <?php if ($isEditor): ?>
      <a class="btn" href="<?= e($handoutUrl) ?>">Student Handout</a>
      <form method="post" style="display:inline;margin:0;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="narration">
        <button class="btn" type="submit" <?= $narrationOk ? '' : 'disabled' ?>
          title="<?= $narrationOk ? 'Generate AI narration' : 'Requires a configured voice provider (config narration.enabled + provider)' ?>">
          Generate Narration
        </button>
      </form>
      <form method="post" style="display:inline;margin:0;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="google_slides">
        <button class="btn" type="submit" <?= $googleOk ? '' : 'disabled' ?>
          title="<?= $googleOk ? 'Open in Google Slides' : 'Requires Google Slides API configuration (google_slides.enabled + client_id)' ?>">
          Google Slides
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php if ($isEditor && (!$narrationOk || !$googleOk)): ?>
  <p class="muted" style="margin-top:-.5rem;margin-bottom:1rem;">
    <?php if (!$narrationOk): ?>AI narration is unavailable until a voice provider is configured. No audio files are generated.<?php endif; ?>
    <?php if (!$narrationOk && !$googleOk): ?> · <?php endif; ?>
    <?php if (!$googleOk): ?>Google Slides export requires API configuration. No share URL is created.<?php endif; ?>
  </p>
<?php endif; ?>

<?php if ($isEditor): ?>
<div class="panel" style="margin-bottom:1rem;">
  <h3 style="margin-top:0;">Regenerate one slide</h3>
  <p class="muted" style="margin-top:0;">Only the selected slide is replaced. Other slides, branding, and deck metadata stay the same. If regeneration fails, the original slide is kept.</p>
  <form class="form-grid" method="post" style="grid-template-columns:1fr 2fr auto;gap:.75rem;align-items:end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="regen_slide">
    <div class="form-row" style="margin:0;">
      <label>Slide</label>
      <select name="slide_index" id="regenSlideSelect" required>
        <?php foreach ($slides as $i => $s): ?>
          <option value="<?= (int)$i ?>">Slide <?= (int)($s['number'] ?? $i + 1) ?> — <?= e((string)($s['title'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row" style="margin:0;">
      <label>Optional instruction</label>
      <input name="regen_instruction" maxlength="500" placeholder="e.g. Make this slide more beginner friendly">
    </div>
    <button class="btn btn-primary" type="submit">Regenerate Slide</button>
  </form>
</div>
<?php endif; ?>

<div class="slide-deck" id="deck">
  <div class="thumbs">
    <?php foreach ($slides as $i => $s): ?>
      <div class="thumb <?= $i===0?'active':'' ?>" data-i="<?= $i ?>">
        <strong>Slide <?= (int)($s['number'] ?? $i+1) ?></strong><br>
        <?= e((string)($s['title'] ?? '')) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="slide-stage" id="stage">
    <div class="slide-stage-bar">
      <h3 id="slideTitle"></h3>
      <span class="chip" id="unitTag"></span>
    </div>
    <div class="slide-stage-body" id="slideBody">
      <ul id="slideBullets"></ul>
    </div>
    <div class="notes" id="slideNotesWrap" style="display:none">
      <strong>Speaker notes</strong>
      <div id="slideNotes"></div>
    </div>
  </div>
</div>
<script>
const slides = <?= json_encode($slides, JSON_UNESCAPED_UNICODE) ?>;
const brandName = <?= json_encode($branding['name'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
function show(i){
  const s = slides[i] || {};
  document.querySelectorAll('.thumb').forEach((t,idx)=>t.classList.toggle('active', idx===i));
  document.getElementById('slideTitle').textContent = s.title || '';
  const tag = document.getElementById('unitTag');
  tag.textContent = s.unit_tag || '';
  tag.style.display = s.unit_tag ? '' : 'none';
  const notesWrap = document.getElementById('slideNotesWrap');
  document.getElementById('slideNotes').textContent = s.speaker_notes || '';
  notesWrap.style.display = s.speaker_notes ? '' : 'none';
  const body = document.getElementById('slideBody');
  body.innerHTML = '';
  body.className = 'slide-stage-body layout-' + (s.layout || 'content');

  if (s.layout === 'comparison' && s.comparison && Array.isArray(s.comparison.headers)) {
    const table = document.createElement('table');
    table.className = 'slide-compare';
    const thead = document.createElement('thead');
    const hr = document.createElement('tr');
    s.comparison.headers.forEach(h => { const th=document.createElement('th'); th.textContent=h; hr.appendChild(th); });
    thead.appendChild(hr); table.appendChild(thead);
    const tb = document.createElement('tbody');
    (s.comparison.rows || []).forEach(row => {
      const tr = document.createElement('tr');
      (Array.isArray(row) ? row : [row]).forEach(cell => { const td=document.createElement('td'); td.textContent=cell; tr.appendChild(td); });
      tb.appendChild(tr);
    });
    table.appendChild(tb);
    body.appendChild(table);
  }

  if (s.layout === 'diagram') {
    const flow = document.createElement('div');
    flow.className = 'slide-diagram';
    (s.bullets || []).forEach(b => {
      const line = String(b || '').trim();
      if (!line || line === '↓' || line === '↑' || line === '→') return;
      const box = document.createElement('div');
      box.className = 'slide-diagram-step';
      box.textContent = line.replace(/^[│├└─\s↓↑→]+/, '');
      flow.appendChild(box);
    });
    body.appendChild(flow);
  } else if (s.layout !== 'comparison') {
    const ul = document.createElement('ul');
    ul.id = 'slideBullets';
    (s.bullets || []).forEach(b => { const li=document.createElement('li'); li.textContent=b; ul.appendChild(li); });
    body.appendChild(ul);
  } else if ((s.bullets || []).length) {
    const ul = document.createElement('ul');
    ul.className = 'slide-compare-notes';
    (s.bullets || []).slice(0,4).forEach(b => { const li=document.createElement('li'); li.textContent=b; ul.appendChild(li); });
    body.appendChild(ul);
  }

  if (s.code) {
    const pre = document.createElement('pre');
    pre.className = 'slide-code';
    pre.textContent = s.code;
    body.appendChild(pre);
  }

  const sel = document.getElementById('regenSlideSelect');
  if (sel) sel.value = String(i);
}
document.querySelectorAll('.thumb').forEach(t => t.addEventListener('click', () => show(+t.dataset.i)));
show(0);
</script>
<?php render_footer(); ?>
