<?php
/** @var array $inst */
/** @var array $plans */
/** @var array $faculty */
?>
<div class="panel print-doc">
  <div class="print-only print-letterhead">
    <strong>ProProfessor AI</strong>
    <span>NAAC accreditation snapshot · <?= e(date('d M Y')) ?></span>
  </div>
  <div class="panel-h">
    <div>
      <h2><?= e($inst['name']) ?> — Accreditation snapshot</h2>
      <p style="margin:0;color:var(--muted)">NAAC <?= e((string)$inst['naac_grade']) ?> · <?= e((string)$inst['affiliation_university']) ?></p>
    </div>
    <button class="btn btn-primary btn-sm no-print" type="button" data-print>Print / PDF</button>
  </div>
  <h3>Plan compliance</h3>
  <div class="chip-row" style="margin-bottom:1rem">
    <?php foreach ($plans as $p): ?><span class="chip"><?= e($p['status']) ?>: <?= (int)$p['c'] ?></span><?php endforeach; ?>
  </div>
  <h3>Faculty matrix</h3>
  <div class="table-wrap"><table>
    <thead><tr><th>Faculty</th><th>Department</th><th>Plans</th><th>Avg AI score</th></tr></thead>
    <tbody>
    <?php foreach ($faculty as $f): ?>
      <tr>
        <td><?= e($f['full_name']) ?></td>
        <td><?= e((string)$f['dept']) ?></td>
        <td><?= (int)$f['plans'] ?></td>
        <td><?= $f['score'] !== null ? round((float)$f['score'], 1) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
