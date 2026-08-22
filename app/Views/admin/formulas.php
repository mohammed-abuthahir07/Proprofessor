<?php
/** @var array $list */
/** @var array $depts */
?>
<div class="grid grid-2">
  <div class="panel">
    <form id="formulaForm" class="form-grid">
      <?= csrf_field() ?>
      <div class="form-row">
        <label>Describe formula in plain English</label>
        <textarea name="plain_english" id="plain" placeholder="Average of CIA 1 and CIA 2 scaled to 15..."></textarea>
      </div>
      <button class="btn btn-accent" type="button" id="parseBtn">Parse with AI</button>
    </form>
    <form method="post" action="<?= e(url('/admin/formulas')) ?>" class="form-grid" style="margin-top:1rem">
      <?= csrf_field() ?>
      <input type="hidden" name="ai_parsed" id="ai_parsed">
      <div class="form-row"><label>Name</label><input name="name" id="fname" required></div>
      <div class="form-row two">
        <div><label>Pattern</label><input name="pattern" id="fpattern" placeholder="Madurai / Anna / CBCS"></div>
        <div><label>Total max</label><input name="total_max" id="ftotal" value="25"></div>
      </div>
      <div class="form-row"><label>Department</label>
        <select name="department_id"><option value="">All</option>
          <?php foreach ($depts as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>Plain English</label><textarea name="plain_english" id="fplain" required></textarea></div>
      <div class="form-row"><label>Expression</label><input name="expression" id="fexpr" required></div>
      <div class="form-row"><label>Components JSON</label><textarea name="components_json" id="fcomp" required>[]</textarea></div>
      <label><input type="checkbox" name="is_default" value="1"> Default formula</label>
      <button class="btn btn-primary" type="submit">Save formula</button>
    </form>
  </div>
  <div class="panel">
    <h3>Configured formulas</h3>
    <?php foreach ($list as $f): ?>
      <div style="padding:.8rem 0;border-bottom:1px solid var(--line)">
        <strong><?= e($f['name']) ?></strong> <?= $f['is_default'] ? '<span class="badge badge-success">Default</span>' : '' ?>
        <div style="font-size:.85rem;color:var(--muted)"><?= e($f['plain_english']) ?></div>
        <code><?= e((string)$f['expression']) ?></code>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
document.getElementById('parseBtn').addEventListener('click', async () => {
  const plain = document.getElementById('plain').value;
  const fd = new FormData();
  fd.append('module','formula');
  fd.append('plain_english', plain);
  fd.append('csrf', '<?= e(csrf_token()) ?>');
  const res = await fetch('<?= e(url('/api/ai')) ?>?module=formula', {method:'POST', body:fd, headers:{'X-CSRF-TOKEN':fd.get('csrf')}});
  const data = await res.json();
  if (!data.ok) return alert(data.error||'Failed');
  const d = data.data;
  document.getElementById('fname').value = d.name || 'Parsed Formula';
  document.getElementById('fplain').value = plain;
  document.getElementById('fexpr').value = d.expression || '';
  document.getElementById('ftotal').value = d.total_max || 25;
  document.getElementById('fcomp').value = JSON.stringify(d.components || [], null, 2);
  document.getElementById('ai_parsed').value = JSON.stringify(d);
});
</script>
