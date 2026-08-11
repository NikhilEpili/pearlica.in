<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('faqs');
require_once ROOT . '/lib/faq.php';

$pdo = $GLOBALS['pdo'];
$migration_hint = false;
$faqs = [];
try {
	faq_ensure_schema($pdo);
	$faqs = faq_list_all($pdo);
} catch (Throwable $e) {
	$faqs = [];
	$migration_hint = true;
}

$page_title = 'FAQs';
$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">FAQs</h1>
	<p class="text-muted small mb-4">
		Edit questions shown on the About page. Inactive items stay hidden on the storefront.
	</p>

	<?php if (!empty($migration_hint)): ?>
		<div class="alert alert-warning">
			Could not create or read the FAQs table. Check database permissions, or run
			<code>sql/add_faqs.sql</code> in phpMyAdmin, then refresh.
		</div>
	<?php endif; ?>
	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

	<div class="mb-3">
		<a href="<?= h(admin_url('faqs/add.php')) ?>" class="btn btn-primary<?= $migration_hint ? ' disabled' : '' ?>">Add FAQ</a>
	</div>

	<div class="card">
		<div class="card-body p-0">
			<table class="table table-hover mb-0 align-middle">
				<thead>
					<tr>
						<th style="width:4rem">Order</th>
						<th>Question</th>
						<th>Answer preview</th>
						<th style="width:5rem">Active</th>
						<th style="width:10rem">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($faqs as $f): ?>
						<?php
						$id = (int) $f['id'];
						$preview = trim((string) ($f['answer'] ?? ''));
						if (mb_strlen($preview) > 120) {
							$preview = mb_substr($preview, 0, 117) . '…';
						}
						?>
						<tr>
							<td class="text-muted"><?= (int) ($f['sort_order'] ?? 0) ?></td>
							<td><?= h((string) ($f['question'] ?? '')) ?></td>
							<td class="small text-muted"><?= h($preview) ?></td>
							<td><?= !empty($f['is_active']) ? 'Yes' : 'No' ?></td>
							<td>
								<a href="<?= h(admin_url('faqs/edit.php?id=' . $id)) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
								<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delModal" data-url="<?= h(admin_url('faqs/delete.php')) ?>" data-id="<?= $id ?>" data-name="<?= h((string) ($f['question'] ?? '')) ?>">Delete</button>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($faqs) && !$migration_hint): ?>
						<tr><td colspan="5" class="text-muted p-3">No FAQs yet. Click Add FAQ to create one.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="modal fade" id="delModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header"><h5 class="modal-title">Delete FAQ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
				<div class="modal-body">Delete “<span id="delName"></span>”?</div>
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
