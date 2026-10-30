<form class="toolbar" method="get" action="<?= e(url('/admin/audit')) ?>">
  <div class="field"><label class="sr-only" for="action">Action</label><input class="input" id="action" name="action" placeholder="Action, e.g. order." value="<?= e($query['action'] ?? '') ?>"></div>
  <div class="field"><label class="sr-only" for="entity">Entity</label><select class="select" id="entity" name="entity"><option value="">All entities</option><?php foreach ($entities as $en): ?><option<?= ($query['entity'] ?? '') === $en ? ' selected' : '' ?>><?= e($en) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="sr-only" for="entity_id">Entity ID</label><input class="input" id="entity_id" name="entity_id" placeholder="ID" size="6" value="<?= e($query['entity_id'] ?? '') ?>"></div>
  <button class="btn btn-dark btn-sm" type="submit">Filter</button>
</form>
<p class="hint">Append-only record of sensitive staff actions. Secrets and passwords are never recorded.</p>
<?php if ($rows): ?>
<table class="admin-table stack-sm">
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Entity</th><th>Change</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $l): ?>
    <tr>
      <td data-label="When"><?= e(dt($l['created_at'])) ?><br><span class="xsmall muted"><?= e((string) $l['ip_address']) ?></span></td>
      <td data-label="Who"><?= e((string) $l['actor_email']) ?></td>
      <td data-label="Action"><code><?= e($l['action']) ?></code></td>
      <td data-label="Entity"><?= e($l['entity_type']) ?><?= $l['entity_id'] ? ' #' . e($l['entity_id']) : '' ?><?php if ($l['entity_type'] === 'order' && $l['entity_id']): ?><?php if (!empty($l['order_exists'])): ?> <a class="xsmall" href="<?= e(url('/admin/orders/' . $l['entity_id'])) ?>">open</a><?php else: ?> <span class="xsmall muted">(deleted)</span><?php endif; ?><?php endif; ?></td>
      <td data-label="Change"><?php if ($l['before_data'] || $l['after_data']): ?><pre class="json-diff"><?= $l['before_data'] ? 'before: ' . e($l['before_data']) . "\n" : '' ?><?= $l['after_data'] ? 'after:  ' . e($l['after_data']) : '' ?></pre><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?= App\Core\View::partial('admin/_pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/audit', 'query' => $query]) ?>
<?php else: ?><div class="card empty-state"><h2>No audit entries</h2></div><?php endif; ?>
