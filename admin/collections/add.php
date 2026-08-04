<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('collections');
require_once ROOT . '/lib/collection.php';

$pdo = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('collections/add.php'));
	}
	$name = trim($_POST['name'] ?? '');
	$slug = trim($_POST['slug'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$meta_title = trim($_POST['meta_title'] ?? '');
	$meta_description = trim($_POST['meta_description'] ?? '');
	if ($name === '') {
		flash_set('error', 'Name is required.');
		redirect(admin_url('collections/add.php'));
	}
	if ($slug === '') {
		$slug = slug_from_string($name);
	}
	if (collection_slug_exists($pdo, $slug)) {
		$existing_id = collection_find_id_by_slug($pdo, $slug);
		if ($existing_id !== null) {
			flash_set('info', 'This collection already exists. Change its products below, then click Save.');
			redirect(admin_url('collections/edit.php?id=' . $existing_id));
		}
		flash_set('error', 'Slug already in use.');
		redirect(admin_url('collections/add.php'));
	}
	try {
		$new_id = collection_insert($pdo, [
			'name' => $name,
			'slug' => $slug,
			'description' => $description !== '' ? $description : null,
			'meta_title' => $meta_title !== '' ? $meta_title : null,
			'meta_description' => $meta_description !== '' ? $meta_description : null,
		]);
	} catch (PDOException $e) {
		if ((string) ($e->getCode() ?? '') === '23000' || str_contains($e->getMessage(), '1062')) {
			$existing_id = collection_find_id_by_slug($pdo, $slug);
			if ($existing_id !== null) {
				flash_set('info', 'This collection already exists. Change its products below, then click Save.');
				redirect(admin_url('collections/edit.php?id=' . $existing_id));
			}
			flash_set('error', 'Slug already in use.');
			redirect(admin_url('collections/add.php'));
		}
		throw $e;
	}
	flash_set('success', 'Collection created. Add products below.');
	redirect(admin_url('collections/edit.php?id=' . (int) $new_id));
}

$page_title = 'Add collection';
$name = $_POST['name'] ?? '';
$slug = $_POST['slug'] ?? '';
$description = $_POST['description'] ?? '';
$meta_title = $_POST['meta_title'] ?? '';
$meta_description = $_POST['meta_description'] ?? '';
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Add collection</h1>
<p class="text-muted small">To update products in an existing collection (e.g. <strong>Everyday Essentials</strong>), open <a href="<?= h(admin_url('collections/')) ?>">Collections</a> and click <strong>Edit</strong> — do not create a duplicate here.</p>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" action="<?= h(admin_url('collections/add.php')) ?>">
	<?= csrf_field() ?>
	<div class="row">
		<div class="col-lg-8">
			<div class="mb-3">
				<label for="name" class="form-label">Name *</label>
				<input type="text" class="form-control" id="name" name="name" required placeholder="e.g. Wedding edit" value="<?= h($name) ?>">
			</div>
			<div class="mb-3">
				<label for="slug" class="form-label">Slug</label>
				<input type="text" class="form-control" id="slug" name="slug" placeholder="e.g. wedding-edit" value="<?= h($slug) ?>">
			</div>
			<div class="mb-3">
				<label for="description" class="form-label">Description</label>
				<textarea class="form-control" id="description" name="description" rows="4" placeholder="Shown on the storefront collections grid and collection page."><?= h($description) ?></textarea>
			</div>
		</div>
		<div class="col-lg-4">
			<div class="mb-3">
				<label for="meta_title" class="form-label">Meta title</label>
				<input type="text" class="form-control" id="meta_title" name="meta_title" value="<?= h($meta_title) ?>">
			</div>
			<div class="mb-3">
				<label for="meta_description" class="form-label">Meta description</label>
				<textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?= h($meta_description) ?></textarea>
			</div>
		</div>
	</div>
	<button type="submit" class="btn btn-primary">Create &amp; add products</button>
	<a href="<?= h(admin_url('collections/')) ?>" class="btn btn-secondary">Cancel</a>
</form>
<script>
(function(){
	var n=document.getElementById('name'),s=document.getElementById('slug');
	if(!n||!s)return;
	function slugify(t){return t.trim().toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');}
	n.addEventListener('input',function(){if(!s.dataset.manual)s.value=slugify(n.value);});
	s.addEventListener('input',function(){s.dataset.manual='1';});
})();
</script>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
