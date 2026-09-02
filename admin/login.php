<?php
require_once __DIR__ . '/config.php';
require_once ROOT . '/lib/admin_user.php';

$pdo = $GLOBALS['pdo'];

if (!empty($_SESSION['admin_id'])) {
	redirect(admin_url('dashboard/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('login.php'));
	}
	$username = trim($_POST['username'] ?? '');
	$password = $_POST['password'] ?? '';
	if ($username === '' || $password === '') {
		flash_set('error', 'Username and password required.');
		redirect(admin_url('login.php'));
	}
	try {
		$user = admin_user_find_by_username($pdo, $username);
	} catch (PDOException $e) {
		flash_set('error', 'Database error.');
		redirect(admin_url('login.php'));
	}
	if (!$user || !password_verify($password, $user['password_hash'])) {
		flash_set('error', 'Invalid credentials.');
		redirect(admin_url('login.php'));
	}
	session_regenerate_id(true);
	$_SESSION['admin_id'] = (int) $user['id'];
	$_SESSION['admin_username'] = $user['username'];
	$_SESSION['admin_is_super_admin'] = !empty($user['is_super_admin']);
	// Load per-page permissions for non-super admins
	if (!$_SESSION['admin_is_super_admin']) {
		try {
			$_SESSION['admin_permissions'] = admin_user_permissions_get($pdo, $user['id']);
		} catch (Throwable $e) {
			// Table missing or error: default to full access so login still works
			$_SESSION['admin_permissions'] = [
				'dashboard' => true,
				'categories' => true,
				'product_groups' => true,
				'collections' => true,
				'products' => true,
				'orders' => true,
				'banners' => true,
				'faqs' => true,
				'reels' => true,
				'policies' => true,
				'blogs' => true,
			];
		}
	} else {
		$_SESSION['admin_permissions'] = [
			'dashboard' => true,
			'categories' => true,
			'product_groups' => true,
			'collections' => true,
			'products' => true,
			'orders' => true,
			'banners' => true,
			'faqs' => true,
			'reels' => true,
			'policies' => true,
			'blogs' => true,
		];
	}
	require_once ROOT . '/lib/email_notifications.php';
	email_notify_admin_login(
		(string) $user['username'],
		isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
		isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null
	);
	redirect(admin_url('dashboard/'));
}

$page_title = 'Login';
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login - Admin | Pearlica</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= h(web_base()) ?>/assets/css/admin.css">
</head>
<body class="admin-body admin-login-page">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-5 col-lg-4">
				<div class="card login-card">
					<div class="card-body p-4">
						<h4 class="card-title">Admin Login</h4>
						<?php if (!empty($error)): ?>
							<div class="alert alert-danger"><?= h($error) ?></div>
						<?php endif; ?>
						<form method="post" action="<?= h(admin_url('login.php')) ?>">
							<?= csrf_field() ?>
							<div class="mb-3">
								<label for="username" class="form-label">Username</label>
								<input type="text" class="form-control" id="username" name="username" required
									value="<?= h($_POST['username'] ?? '') ?>">
							</div>
							<div class="mb-3">
								<label for="password" class="form-label">Password</label>
								<input type="password" class="form-control" id="password" name="password" required>
							</div>
							<button type="submit" class="btn btn-primary">Login</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
