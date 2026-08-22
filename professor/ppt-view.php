<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
Auth::requireRole('professor', 'admin', 'student');
$user = Auth::user();
$id = (int)get('id');
$ppt = Database::fetch('SELECT * FROM presentations WHERE id = ?', [$id]);
$home = $user['role'] === 'student' ? '/student/notes.php' : '/professor/ppt.php';
if (!$ppt || !presentation_accessible($user, $ppt)) {
    flash('error', 'Not found');
    redirect($home);
}
$slides = json_decode($ppt['slides'] ?: '[]', true) ?: [];
$saveUrl = base_url('/professor/ppt-download.php?id=' . $id);
$saveBtn = '<a class="btn btn-accent btn-sm topbar-save" href="' . e($saveUrl) . '">' . icon('download') . ' Save PPT</a>';
render_header($ppt['title'], 'ppt', [
    'subtitle' => count($slides) . ' slides',
    'actions' => $saveBtn,
]);
?>
<div class="deck-toolbar">
  <p class="deck-toolbar-hint">Download a PowerPoint file with titles, bullets, and speaker notes.</p>
  <a class="btn btn-accent" href="<?= e($saveUrl) ?>"><?= icon('download') ?> Save PPT</a>
</div>
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
    <div class="slide-stage-body">
      <ul id="slideBullets"></ul>
    </div>
    <div class="notes" id="slideNotes"></div>
  </div>
</div>
<script>
const slides = <?= json_encode($slides, JSON_UNESCAPED_UNICODE) ?>;
function show(i){
  const s = slides[i] || {};
  document.querySelectorAll('.thumb').forEach((t,idx)=>t.classList.toggle('active', idx===i));
  document.getElementById('slideTitle').textContent = s.title || '';
  const tag = document.getElementById('unitTag');
  tag.textContent = s.unit_tag || '';
  tag.style.display = s.unit_tag ? '' : 'none';
  document.getElementById('slideNotes').textContent = s.speaker_notes || '';
  document.getElementById('slideNotes').style.display = s.speaker_notes ? '' : 'none';
  const ul = document.getElementById('slideBullets');
  ul.innerHTML = '';
  (s.bullets || []).forEach(b => { const li=document.createElement('li'); li.textContent=b; ul.appendChild(li); });
}
document.querySelectorAll('.thumb').forEach(t => t.addEventListener('click', () => show(+t.dataset.i)));
show(0);
</script>
<?php render_footer(); ?>
