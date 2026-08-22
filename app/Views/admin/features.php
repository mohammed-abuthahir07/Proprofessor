<?php /** @var array $features */ ?>
<div class="panel">
  <p>Enable/disable modules without schema changes. Managed via MVC <code>FeatureController</code> + <code>feature_flags</code> tables.</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Module</th><th>Feature</th><th>Description</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($features as $f): ?>
      <tr>
        <td><span class="chip"><?= e($f['module']) ?></span></td>
        <td><strong><?= e($f['name']) ?></strong><div style="font-size:.75rem;color:var(--muted)"><?= e($f['code']) ?></div></td>
        <td><?= e((string)$f['description']) ?></td>
        <td><?= $f['is_enabled'] ? '<span class="badge badge-success">On</span>' : '<span class="badge badge-muted">Off</span>' ?></td>
        <td>
          <form method="post" action="<?= e(url('/admin/features')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="feature_code" value="<?= e($f['code']) ?>">
            <input type="hidden" name="is_enabled" value="<?= $f['is_enabled'] ? 0 : 1 ?>">
            <button class="btn btn-sm btn-ghost" type="submit"><?= $f['is_enabled'] ? 'Disable' : 'Enable' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
