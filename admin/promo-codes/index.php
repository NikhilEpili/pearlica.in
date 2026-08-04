<?php
require_once dirname(__DIR__) . '/config.php';
require_super_admin();
require_once ROOT . '/lib/promo_code.php';

$pdo = $GLOBALS['pdo'];
$promos = promo_code_list_all($pdo);
$page_title = 'Promo Codes';
$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Promo Codes</h1>
	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<div class="mb-3"><a href="<?= h(admin_url('promo-codes/add.php')) ?>" class="btn btn-primary">Add Promo Code</a></div>
	<div class="card">
		<div class="card-body p-0">
			<table class="table table-hover mb-0">
				<thead>
					<tr>
						<th>ID</th>
						<th>Code</th>
						<th>Type</th>
						<th>Value</th>
						<th>Min order</th>
						<th>Valid from</th>
						<th>Valid until</th>
						<th>Usage</th>
						<th>Active</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($promos as $p): ?>
						<tr>
							<td><?= (int) $p['id'] ?></td>
							<td><code><?= h($p['code']) ?></code></td>
							<td><?= h($p['discount_type']) ?></td>
							<td><?= $p['discount_type'] === 'percent' ? h($p['discount_value']) . '%' : '₹' . number_format((float) $p['discount_value'] / 100, 2) ?></td>
							<td><?= (int) $p['min_order_paise'] > 0 ? '₹' . number_format((int) $p['min_order_paise'] / 100, 0) : '—' ?></td>
							<td class="small"><?= $p['valid_from'] ? h($p['valid_from']) : '—' ?></td>
							<td class="small"><?= $p['valid_until'] ? h($p['valid_until']) : '—' ?></td>
							<td><?= (int) $p['used_count'] ?><?= $p['usage_limit'] !== null ? ' / ' . (int) $p['usage_limit'] : '' ?></td>
							<td><?= !empty($p['is_active']) ? 'Yes' : 'No' ?></td>
							<td>
								<a href="<?= h(admin_url('promo-codes/edit.php?id=' . $p['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
								<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delModal" data-url="<?= h(admin_url('promo-codes/delete.php')) ?>" data-id="<?= h($p['id']) ?>" data-name="<?= h($p['code']) ?>">Delete</button>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($promos)): ?>
						<tr><td colspan="10" class="text-muted p-3">No promo codes yet.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<div class="modal fade" id="delModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header"><h5 class="modal-title">Delete Promo Code</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
				<div class="modal-body">Delete "<span id="delName"></span>"?</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<form id="delForm" method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" id="delId"><button type="submit" class="btn btn-danger">Delete</button></form>
				</div>
			</div>
		</div>
	</div>
	<script>
	document.getElementById('delModal') && document.getElementById('delModal').addEventListener('show.bs.modal', function (e) {
		var btn = e.relatedTarget;
		if (!btn) return;
		document.getElementById('delName').textContent = btn.getAttribute('data-name') || '';
		document.getElementById('delId').value = btn.getAttribute('data-id') || '';
		document.getElementById('delForm').action = btn.getAttribute('data-url') || '';
	});
	</script>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
