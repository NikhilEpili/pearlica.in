<?php
require_once dirname(__DIR__) . '/config.php';
require_super_admin();
require_once ROOT . '/lib/admin_user.php';

$pdo = $GLOBALS['pdo'];
$users = admin_user_list_all($pdo);
$page_title = 'Users';
$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">User management</h1>
<p class="text-muted small">Create, delete, and change passwords. Only super admins can access this page.</p>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<div class="mb-3"><a href="<?= h(admin_url('users/add.php')) ?>" class="btn btn-primary">Add user</a></div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>ID</th><th>Username</th><th>Role</th><th>Permissions</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= h($u['id']) ?></td>
                    <td><?= h($u['username']) ?></td>
                    <td><?= !empty($u['is_super_admin']) ? 'Super admin' : 'Admin' ?></td>
                    <td>
                        <?php if (!empty($u['is_super_admin'])): ?>
                            <span class="badge bg-dark text-uppercase">Super admin</span>
                        <?php else: ?>
                            <span class="badge bg-secondary text-uppercase">Admin</span>
                        <?php endif; ?>
                        <?php if ((int) $u['id'] !== (int) ($_SESSION['admin_id'] ?? 0)): ?>
                        <form method="post" action="<?= h(admin_url('users/role.php')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <input type="hidden" name="make_super" value="<?= !empty($u['is_super_admin']) ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary ms-1">
                                <?= !empty($u['is_super_admin']) ? 'Remove super' : 'Make super' ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= h(admin_url('users/change-password.php?id=' . $u['id'])) ?>" class="btn btn-sm btn-outline-primary">Change password</a>
                        <?php if ((int) $u['id'] !== (int) ($_SESSION['admin_id'] ?? 0)): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delModal" data-url="<?= h(admin_url('users/delete.php')) ?>" data-id="<?= h($u['id']) ?>" data-name="<?= h($u['username']) ?>">Delete</button>
                        <?php else: ?>
                        <span class="text-muted small">(you)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="5" class="text-muted">No users yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Delete user</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">Delete user "<span id="delName"></span>"? They will no longer be able to log in.</div>
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
