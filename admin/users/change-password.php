<?php
require_once dirname(__DIR__) . '/config.php';
require_super_admin();
require_once ROOT . '/lib/admin_user.php';

$pdo = $GLOBALS['pdo'];
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
	redirect(admin_url('users/'));
}
$user = admin_user_find_by_id($pdo, $id);
if (!$user) {
	flash_set('error', 'User not found.');
	redirect(admin_url('users/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('users/change-password.php?id=' . $id));
	}
	$new_password = $_POST['new_password'] ?? '';
	$new_password_confirm = $_POST['new_password_confirm'] ?? '';
	if (strlen($new_password) < 6) {
		flash_set('error', 'Password must be at least 6 characters.');
		redirect(admin_url('users/change-password.php?id=' . $id));
	}
	if ($new_password !== $new_password_confirm) {
		flash_set('error', 'Passwords do not match.');
		redirect(admin_url('users/change-password.php?id=' . $id));
	}
	admin_user_update_password($pdo, $id, $new_password);
	flash_set('success', 'Password updated for ' . h($user['username']) . '.');
	redirect(admin_url('users/'));
}

$page_title = 'Change password';
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Change password</h1>
<p class="text-muted">User: <strong><?= h($user['username']) ?></strong></p>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" action="<?= h(admin_url('users/change-password.php?id=' . $id)) ?>">
	<?= csrf_field() ?>
	<div class="card mb-4">
		<div class="card-body">
			<div class="mb-3">
				<label for="new_password" class="form-label">New password *</label>
				<input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
				<small class="text-muted">At least 6 characters.</small>
			</div>
			<div class="mb-3">
				<label for="new_password_confirm" class="form-label">Confirm new password *</label>
				<input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" required minlength="6">
			</div>
			<button type="submit" class="btn btn-primary">Update password</button>
			<a href="<?= h(admin_url('users/')) ?>" class="btn btn-secondary">Cancel</a>
		</div>
	</div>
</form>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
