<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('categories');
require_once ROOT . '/lib/category.php';

$pdo = $GLOBALS['pdo'];
$categories = category_list_all($pdo);
$page_title = 'Categories';
$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Categories</h1>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<div class="mb-3"><a href="<?= h(admin_url('categories/add.php')) ?>" class="btn btn-primary">Add Category</a></div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Slug</th><th>Parent</th><th>Products</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php
                $by_id = [];
                foreach ($categories as $c) {
                    $by_id[$c['id']] = $c;
                }
                foreach ($categories as $c):
                    $parent_name = !empty($c['parent_id']) && isset($by_id[$c['parent_id']]) ? $by_id[$c['parent_id']]['name'] : null;
                ?>
                <tr>
                    <td><?= h($c['id']) ?></td>
                    <td><?= h($c['name']) ?></td>
                    <td><?= h($c['slug']) ?></td>
                    <td><?= $parent_name ? h($parent_name) : '-' ?></td>
                    <td><?= h($c['product_count']) ?></td>
                    <td>
                        <a href="<?= h(admin_url('categories/edit.php?id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delModal" data-url="<?= h(admin_url('categories/delete.php')) ?>" data-id="<?= h($c['id']) ?>" data-name="<?= h($c['name']) ?>" data-count="<?= h($c['product_count']) ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr><td colspan="6" class="text-muted">No categories yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Delete Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                Delete "<span id="delName"></span>"?
                <p id="delWarn" class="text-danger small mt-2 d-none">Category has products. Cannot delete.</p>
            </div>
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
    document.getElementById('delWarn').classList.toggle('d-none', parseInt(btn.getAttribute('data-count') || '0', 10) === 0);
});
</script>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
