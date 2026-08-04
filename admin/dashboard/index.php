<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('dashboard');
require_once ROOT . '/lib/product.php';
require_once ROOT . '/lib/category.php';
require_once ROOT . '/lib/product_group.php';

$pdo = $GLOBALS['pdo'];
$stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL');
$total_products = (int) $stmt->fetchColumn();
$stmt = $pdo->query('SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL');
$total_categories = (int) $stmt->fetchColumn();
$stmt = $pdo->query('SELECT COUNT(*) FROM product_groups WHERE deleted_at IS NULL');
$total_groups = (int) $stmt->fetchColumn();
$total_collections = 0;
try {
	$stmt = $pdo->query('SELECT COUNT(*) FROM collections WHERE deleted_at IS NULL');
	$total_collections = (int) $stmt->fetchColumn();
} catch (Throwable $e) {
	$total_collections = 0;
}

$page_title = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Dashboard</h1>
<div class="row g-4">
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6>Products</h6>
                <h3 class="mb-0"><?= h($total_products) ?></h3>
                <a href="<?= h(admin_url('products/')) ?>" class="btn btn-sm btn-outline-primary mt-2">View</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6>Categories</h6>
                <h3 class="mb-0"><?= h($total_categories) ?></h3>
                <a href="<?= h(admin_url('categories/')) ?>" class="btn btn-sm btn-outline-primary mt-2">View</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6>Product Groups</h6>
                <h3 class="mb-0"><?= h($total_groups) ?></h3>
                <a href="<?= h(admin_url('product-groups/')) ?>" class="btn btn-sm btn-outline-primary mt-2">View</a>
            </div>
        </div>
    </div>
    <?php if (current_user_can('collections')): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6>Collections</h6>
                <h3 class="mb-0"><?= h($total_collections) ?></h3>
                <a href="<?= h(admin_url('collections/')) ?>" class="btn btn-sm btn-outline-primary mt-2">View</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
