<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('product_groups');
require_once ROOT . '/lib/product_group.php';

$pdo = $GLOBALS['pdo'];
$groups = product_group_list_all($pdo);
$page_title = 'Product Groups';
$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Product Groups</h1>
<p class="text-muted small">Group products to show them as variants on the product page. Assign a group to products in Edit Product.</p>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<div class="mb-3"><a href="<?= h(admin_url('product-groups/add.php')) ?>" class="btn btn-primary">Add Group</a></div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Slug</th><th>Products</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g): ?>
                <tr>
                    <td><?= h($g['id']) ?></td>
                    <td><?= h($g['name']) ?></td>
                    <td><?= h($g['slug']) ?></td>
                    <td><?= h($g['product_count']) ?></td>
                    <td>
                        <a href="<?= h(admin_url('product-groups/edit.php?id=' . $g['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delModal" data-url="<?= h(admin_url('product-groups/delete.php')) ?>" data-id="<?= h($g['id']) ?>" data-name="<?= h($g['name']) ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($groups)): ?>
                <tr><td colspan="5" class="text-muted">No product groups yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Delete Group</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">Delete "<span id="delName"></span>"? Products in this group will be unassigned.</div>
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
