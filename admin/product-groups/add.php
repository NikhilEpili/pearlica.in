<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('product_groups');
require_once ROOT . '/lib/product_group.php';

$pdo = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        flash_set('error', 'Invalid request.');
        redirect(admin_url('product-groups/add.php'));
    }
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($name === '') {
        flash_set('error', 'Name is required.');
        redirect(admin_url('product-groups/add.php'));
    }
    if ($slug === '') {
        $slug = slug_from_string($name);
    }
    if (product_group_slug_exists($pdo, $slug)) {
        flash_set('error', 'Slug already in use.');
        redirect(admin_url('product-groups/add.php'));
    }
    product_group_insert($pdo, ['name' => $name, 'slug' => $slug]);
    flash_set('success', 'Group added.');
    redirect(admin_url('product-groups/'));
}

$page_title = 'Add Product Group';
$name = $_POST['name'] ?? '';
$slug = $_POST['slug'] ?? '';
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Add Product Group</h1>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" action="<?= h(admin_url('product-groups/add.php')) ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="name" class="form-label">Name *</label>
        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. Silver Ring Sizes" value="<?= h($name) ?>">
    </div>
    <div class="mb-3">
        <label for="slug" class="form-label">Slug</label>
        <input type="text" class="form-control" id="slug" name="slug" placeholder="e.g. silver-ring-sizes" value="<?= h($slug) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Add Group</button>
    <a href="<?= h(admin_url('product-groups/')) ?>" class="btn btn-secondary">Cancel</a>
</form>
<script>
(function(){
    var n=document.getElementById('name'),s=document.getElementById('slug');
    if(!n||!s)return;
    function slug(s){return s.trim().toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');}
    n.addEventListener('input',function(){if(!s.dataset.manual)s.value=slug(n.value);});
    s.addEventListener('input',function(){s.dataset.manual='1';});
})();
</script>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
