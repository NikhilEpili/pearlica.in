<?php
require_once dirname(__DIR__) . '/config.php';
require_super_admin();
require_once ROOT . '/lib/admin_user.php';

$pdo = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('users/add.php'));
	}
	$username = trim($_POST['username'] ?? '');
	$password = $_POST['password'] ?? '';
	$password_confirm = $_POST['password_confirm'] ?? '';
	$perms_input = $_POST['permissions'] ?? [];
	if ($username === '') {
		flash_set('error', 'Username is required.');
		redirect(admin_url('users/add.php'));
	}
	if (strlen($password) < 6) {
		flash_set('error', 'Password must be at least 6 characters.');
		redirect(admin_url('users/add.php'));
	}
	if ($password !== $password_confirm) {
		flash_set('error', 'Passwords do not match.');
		redirect(admin_url('users/add.php'));
	}
	if (admin_user_username_exists($pdo, $username)) {
		flash_set('error', 'Username already in use.');
		redirect(admin_url('users/add.php'));
	}
	$user_id = admin_user_create($pdo, $username, $password);
	admin_user_permissions_set($pdo, $user_id, [
		'dashboard' => !empty($perms_input['dashboard']),
		'categories' => !empty($perms_input['categories']),
		'product_groups' => !empty($perms_input['product_groups']),
		'collections' => !empty($perms_input['collections']),
		'products' => !empty($perms_input['products']),
		'orders' => !empty($perms_input['orders']),
		'banners' => !empty($perms_input['banners']),
		'faqs' => !empty($perms_input['faqs']),
		'reels' => !empty($perms_input['reels']),
		'policies' => !empty($perms_input['policies']),
		'blogs' => !empty($perms_input['blogs']),
	]);
	flash_set('success', 'User created.');
	redirect(admin_url('users/'));
}

$page_title = 'Add user';
$username = $_POST['username'] ?? '';
$permissions_post = $_POST['permissions'] ?? [];
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Add user</h1>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" action="<?= h(admin_url('users/add.php')) ?>">
	<?= csrf_field() ?>
	<div class="card mb-4">
		<div class="card-body">
			<div class="mb-3">
				<label for="username" class="form-label">Username *</label>
				<input type="text" class="form-control" id="username" name="username" required value="<?= h($username) ?>">
			</div>
			<div class="mb-3">
				<label for="password" class="form-label">Password *</label>
				<input type="password" class="form-control" id="password" name="password" required minlength="6">
				<small class="text-muted">At least 6 characters.</small>
			</div>
			<div class="mb-3">
				<label for="password_confirm" class="form-label">Confirm password *</label>
				<input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6">
			</div>
			<div class="mb-3">
				<label class="form-label">Page access</label>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_dashboard" name="permissions[dashboard]" value="1"
						<?= !isset($permissions_post['dashboard']) || !empty($permissions_post['dashboard']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_dashboard">Dashboard</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_categories" name="permissions[categories]" value="1"
						<?= !isset($permissions_post['categories']) || !empty($permissions_post['categories']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_categories">Categories</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_product_groups" name="permissions[product_groups]" value="1"
						<?= !isset($permissions_post['product_groups']) || !empty($permissions_post['product_groups']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_product_groups">Product Groups</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_collections" name="permissions[collections]" value="1"
						<?= !isset($permissions_post['collections']) || !empty($permissions_post['collections']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_collections">Collections</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_products" name="permissions[products]" value="1"
						<?= !isset($permissions_post['products']) || !empty($permissions_post['products']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_products">Products</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_orders" name="permissions[orders]" value="1"
						<?= !isset($permissions_post['orders']) || !empty($permissions_post['orders']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_orders">Orders</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_banners" name="permissions[banners]" value="1"
						<?= !isset($permissions_post['banners']) || !empty($permissions_post['banners']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_banners">Homepage banners</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_faqs" name="permissions[faqs]" value="1"
						<?= !isset($permissions_post['faqs']) || !empty($permissions_post['faqs']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_faqs">FAQs</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_reels" name="permissions[reels]" value="1"
						<?= !isset($permissions_post['reels']) || !empty($permissions_post['reels']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_reels">Instagram reels</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_policies" name="permissions[policies]" value="1"
						<?= !isset($permissions_post['policies']) || !empty($permissions_post['policies']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_policies">Policies</label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="perm_blogs" name="permissions[blogs]" value="1"
						<?= !isset($permissions_post['blogs']) || !empty($permissions_post['blogs']) ? 'checked' : '' ?>>
					<label class="form-check-label" for="perm_blogs">Blog</label>
				</div>
				<small class="text-muted d-block mt-1">Super admins always have full access, regardless of these settings.</small>
			</div>
			<button type="submit" class="btn btn-primary">Create user</button>
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
