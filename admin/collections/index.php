<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('collections');
require_once ROOT . '/lib/collection.php';

$pdo = $GLOBALS['pdo'];
$migration_hint = false;
$collections = [];
try {
	$collections = collection_list_all($pdo);
} catch (Throwable $e) {
	$collections = [];
	$migration_hint = true;
}
$page_title = 'Collections';
$success = flash_get('success');
$error = flash_get('error');
$collections_upload_base = uploads_collections_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Collections</h1>
<p class="text-muted small">Curated product sets for the shop. Assign products on Edit; the Collections link in the storefront lists collections that have at least one active product.</p>
<?php if (!empty($migration_hint)): ?>
<div class="alert alert-warning">Database tables for collections are missing. Run <code>migrations/20260416190000_collections_all_in_one.sql</code> (Section A) on this database, then refresh.</div>
<?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<div class="mb-3"><a href="<?= h(admin_url('collections/add.php')) ?>" class="btn btn-primary">Add collection</a></div>
<div class="card">
	<div class="card-body p-0">
		<table class="table table-hover mb-0">
			<thead>
				<tr><th>ID</th><th class="text-center">Cover</th><th>Name</th><th>Slug</th><th>Products</th><th>Actions</th></tr>
			</thead>
			<tbody>
				<?php foreach ($collections as $c): ?>
				<tr>
					<td><?= h($c['id']) ?></td>
					<td class="text-center align-middle" style="width:5.5rem">
						<?php
						$rel = trim(str_replace(['\\', '..'], '', (string) ($c['thumbnail_url'] ?? '')), '/ ');
						if ($rel !== ''):
							$src = $collections_upload_base . '/' . implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $rel))));
							?>
						<img src="<?= h($src) ?>" alt="" class="rounded border" style="width:48px;height:64px;object-fit:cover">
						<?php else: ?>
						<span class="text-muted small">—</span>
						<?php endif; ?>
					</td>
					<td><?= h($c['name']) ?></td>
					<td><code><?= h($c['slug']) ?></code></td>
					<td><?= h((string) ($c['product_count'] ?? '0')) ?></td>
					<td>
						<a href="<?= h(admin_url('collections/edit.php?id=' . (int) $c['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
						<a href="<?= h(rtrim(web_base(), '/') . '/collection?slug=' . rawurlencode((string) $c['slug'])) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">View</a>
						<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delModal" data-url="<?= h(admin_url('collections/delete.php')) ?>" data-id="<?= h($c['id']) ?>" data-name="<?= h($c['name']) ?>">Delete</button>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if (empty($collections)): ?>
				<tr><td colspan="6" class="text-muted">No collections yet.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
<div class="modal fade" id="delModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header"><h5 class="modal-title">Delete collection</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
			<div class="modal-body">Delete &quot;<span id="delName"></span>&quot;? Product assignments will be removed.</div>
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
