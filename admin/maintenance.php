<?php
http_response_code(503);

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

function h($s): string
{
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Web base to prefix assets/links when the site is mounted under a sub-path.
 * Example: if URL is `/pearlica.in/admin/...` then web base becomes `/pearlica.in`.
 */
function web_base(): string
{
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	return preg_match('#^(.*?)/admin#', $req_uri, $m) ? rtrim($m[1], '/') : '';
}

function admin_url($path = ''): string
{
	$path = ltrim($path, '/');
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
	return $base . '/admin' . ($path !== '' ? '/' . $path : '');
}

// Used by the sidebar to conditionally show links (no DB needed).
function current_user_permissions(): array
{
	return $_SESSION['admin_permissions'] ?? [];
}

function current_user_is_super_admin(): bool
{
	return !empty($_SESSION['admin_is_super_admin']);
}

function current_user_can(string $area): bool
{
	if (current_user_is_super_admin()) {
		return true;
	}
	$perms = current_user_permissions();
	return !empty($perms[$area]);
}

$web_base = web_base();
$page_title = 'Maintenance';
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= h($page_title) ?> Admin | Pearlica</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link
		rel="stylesheet"
		href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
		integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
		crossorigin="anonymous" referrerpolicy="no-referrer"
	/>
	<link rel="stylesheet" href="<?= h($web_base) ?>/assets/css/admin.css">
</head>

<body class="admin-body">
	<?php require __DIR__ . '/components/wrapper_start.php'; ?>

	<main class="container-fluid p-4 admin-content">
		<div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 140px);">
			<div class="card" style="width: 100%; max-width: 820px;">
				<div class="card-header">Maintenance</div>
				<div class="card-body text-center">
					<h1 class="page-title" style="margin-bottom: 0.85rem; font-size: 1.7rem;">Maintenance</h1>
					<p class="text-muted mb-4" style="font-size: 1.05rem;">
						This website is currently undergoing maintenance. Please check back later.
					</p>
					<a href="<?= h(admin_url('dashboard/')) ?>" class="btn btn-primary">
						Back to Admin
					</a>
				</div>
			</div>
		</div>
	</main>

	<?php require __DIR__ . '/components/wrapper_end.php'; ?>
	<?php require __DIR__ . '/footer.php'; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

