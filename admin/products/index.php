<?php
require_once dirname(__DIR__) . '/config.php';
require_admin_login();
require_once ROOT . '/lib/product.php';
require_once ROOT . '/lib/category.php';

$pdo = $GLOBALS['pdo'];
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$category_id = trim($_GET['category_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$per_page = ADMIN_ITEMS_PER_PAGE;
$result = product_list_paginated($pdo, $page, $per_page, $search, $category_id, $status);
$total_pages = pagination_total_pages($result['total'], $per_page);
$categories = category_list_for_select($pdo);
$export_query = [];
if ($search !== '') {
	$export_query['search'] = $search;
}
if ($category_id !== '') {
	$export_query['category_id'] = $category_id;
}
if ($status !== '') {
	$export_query['status'] = $status;
}
$export_qs = $export_query === [] ? '' : ('?' . http_build_query($export_query));
$export_base = admin_url('products/export.php') . $export_qs;
$page_title = 'Products';
$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content admin-products-page">
<div class="admin-products-page-header">
	<h1 class="page-title mb-0">Products</h1>
	<div class="admin-products-header-actions d-flex flex-wrap gap-2">
		<div class="dropdown">
			<button class="btn btn-outline-secondary dropdown-toggle" type="button" id="productExportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
				Export
			</button>
			<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="productExportDropdown">
				<li><a class="dropdown-item" href="<?= h($export_base . ($export_qs === '' ? '?' : '&') . 'format=csv') ?>">CSV (all columns)</a></li>
				<li><a class="dropdown-item" href="<?= h($export_base . ($export_qs === '' ? '?' : '&') . 'format=excel') ?>">Excel (.xls)</a></li>
				<li><hr class="dropdown-divider"></li>
				<li><a class="dropdown-item" href="<?= h($export_base . ($export_qs === '' ? '?' : '&') . 'format=csv&split=1') ?>">By category (ZIP of CSVs)</a></li>
			</ul>
		</div>
		<a href="<?= h(admin_url('products/add.php')) ?>" class="btn btn-primary">Add Product</a>
	</div>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<div class="card mb-3 admin-products-filters-card">
	<div class="card-body admin-products-filters-card-body">
		<form method="get" action="<?= h(admin_url('products/')) ?>" class="admin-products-toolbar-form">
			<label class="visually-hidden" for="adminProductSearch">Search</label>
			<input type="text" class="form-control form-control-sm admin-products-toolbar-search" id="adminProductSearch" name="search" placeholder="Search by name or SKU…" value="<?= h($search) ?>" autocomplete="off">
			<label class="visually-hidden" for="adminProductCategory">Category</label>
			<select name="category_id" id="adminProductCategory" class="form-select form-select-sm admin-products-toolbar-select">
				<option value="">All categories</option>
				<?php foreach ($categories as $c): ?>
				<option value="<?= h($c['id']) ?>" <?= (string) $c['id'] === $category_id ? 'selected' : '' ?>><?= h($c['name']) ?></option>
				<?php endforeach; ?>
			</select>
			<label class="visually-hidden" for="adminProductStatus">Status</label>
			<select name="status" id="adminProductStatus" class="form-select form-select-sm admin-products-toolbar-select">
				<option value="">All statuses</option>
				<option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
				<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
				<option value="out_of_stock" <?= $status === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option>
				<option value="discontinued" <?= $status === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
				<option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
			</select>
			<button type="submit" class="btn btn-sm btn-secondary admin-products-toolbar-btn">Filter</button>
			<a href="<?= h(admin_url('products/')) ?>" class="btn btn-sm btn-outline-secondary admin-products-toolbar-btn">Reset</a>
		</form>
	</div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>ID</th><th>Name</th><th>SKU</th><th>Category</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($result['items'] as $p): ?>
                <tr>
                    <td><?= h($p['id']) ?></td>
                    <td><?= h($p['name']) ?></td>
                    <td><?= h($p['sku'] ?? '') ?></td>
                    <td><?= h($p['category_name'] ?? '') ?></td>
                    <td><?= h($p['status']) ?></td>
                    <td>
                        <a href="<?= h(admin_products_edit_url((int) $p['id'], ['page' => $page, 'search' => $search, 'category_id' => $category_id, 'status' => $status])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delModal" data-url="<?= h(admin_url('products/delete.php')) ?>" data-id="<?= h($p['id']) ?>" data-name="<?= h($p['name']) ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($result['items'])): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No products. <a href="<?= h(admin_url('products/add.php')) ?>">Add one</a> (create a <a href="<?= h(admin_url('categories/')) ?>">category</a> first).</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($total_pages > 1): ?>
<nav class="mt-3"><ul class="pagination">
<?php for ($i = 1; $i <= $total_pages; $i++): ?>
<li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= h(admin_url('products/') . '?page=' . $i . ($search !== '' ? '&search=' . rawurlencode($search) : '') . ($category_id !== '' ? '&category_id=' . $category_id : '') . ($status !== '' ? '&status=' . rawurlencode($status) : '')) ?>"><?= $i ?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>
<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Delete Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
