<?php
require_once __DIR__ . '/config.php';
require_admin_login();

$page_title = 'Access denied';
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Access denied</h1>
	<p class="text-muted">You don't have permission to view this page.</p>
	<a href="<?= h(admin_url('logout.php')) ?>" class="btn btn-primary">Logout</a>
</main>
<?php require __DIR__ . '/components/wrapper_end.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
