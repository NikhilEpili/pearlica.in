<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('categories');
require_once ROOT . '/lib/category.php';

$pdo = $GLOBALS['pdo'];
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect(admin_url('categories/'));
}
$category = category_find_by_id($pdo, $id);
if (!$category) {
    flash_set('error', 'Category not found.');
    redirect(admin_url('categories/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        flash_set('error', 'Invalid request.');
        redirect(admin_url('categories/edit.php?id=' . $id));
    }
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $parent_id = $_POST['parent_id'] ?? '';
    if ($name === '') {
        flash_set('error', 'Name is required.');
        redirect(admin_url('categories/edit.php?id=' . $id));
    }
    if ($slug === '') {
        $slug = slug_from_string($name);
    }
    if (category_slug_exists($pdo, $slug, $id)) {
        flash_set('error', 'Slug already in use.');
        redirect(admin_url('categories/edit.php?id=' . $id));
    }
    if ((int) $parent_id === $id) {
        flash_set('error', 'Category cannot be its own parent.');
        redirect(admin_url('categories/edit.php?id=' . $id));
    }
    category_update($pdo, $id, [
        'name' => $name,
        'slug' => $slug,
        'parent_id' => $parent_id !== '' ? (int) $parent_id : null
    ]);
    flash_set('success', 'Category updated.');
    redirect(admin_url('categories/'));
}

$parent_options = category_list_for_select($pdo, $id);
$page_title = 'Edit Category';
$name = $category['name'];
$slug = $category['slug'];
$parent_id = (string) $category['parent_id'];
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Edit Category</h1>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" action="<?= h(admin_url('categories/edit.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="name" class="form-label">Name *</label>
        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. Rings" value="<?= h($name) ?>">
    </div>
    <div class="mb-3">
        <label for="slug" class="form-label">Slug</label>
        <input type="text" class="form-control" id="slug" name="slug" placeholder="e.g. rings" value="<?= h($slug) ?>">
    </div>
    <div class="mb-3">
        <label for="parent_id" class="form-label">Parent</label>
        <select class="form-select" id="parent_id" name="parent_id">
            <option value="">- None -</option>
            <?php foreach ($parent_options as $opt): ?>
            <option value="<?= h($opt['id']) ?>" <?= $parent_id === (string) $opt['id'] ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Update</button>
    <a href="<?= h(admin_url('categories/')) ?>" class="btn btn-secondary">Cancel</a>
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
