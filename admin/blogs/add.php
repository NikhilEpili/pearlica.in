<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('blogs');
require_once ROOT . '/lib/blog.php';

$pdo = $GLOBALS['pdo'];
blog_ensure_schema($pdo);

$title = '';
$slug = '';
$excerpt = '';
$body_html = '';
$cover_image = '';
$meta_title = '';
$meta_description = '';
$published_at_input = '';
$sort_order = blog_next_sort_order($pdo);
$is_published = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('blogs/add.php'));
	}
	$title = trim((string) ($_POST['title'] ?? ''));
	$slug = blog_normalize_slug(trim((string) ($_POST['slug'] ?? '')), $title);
	$excerpt = trim((string) ($_POST['excerpt'] ?? ''));
	$body_html = (string) ($_POST['body_html'] ?? '');
	$cover_image = trim((string) ($_POST['cover_image'] ?? ''));
	$meta_title = trim((string) ($_POST['meta_title'] ?? ''));
	$meta_description = trim((string) ($_POST['meta_description'] ?? ''));
	$published_at_input = trim((string) ($_POST['published_at'] ?? ''));
	$sort_order = (int) ($_POST['sort_order'] ?? $sort_order);
	$is_published = isset($_POST['is_published']);

	if ($title === '' || trim($body_html) === '') {
		flash_set('error', 'Title and body HTML are required.');
		redirect(admin_url('blogs/add.php'));
	}
	$slug_error = blog_validate_slug($slug);
	if ($slug_error !== null) {
		flash_set('error', $slug_error);
		redirect(admin_url('blogs/add.php'));
	}
	if (blog_slug_exists($pdo, $slug)) {
		flash_set('error', 'Slug already in use.');
		redirect(admin_url('blogs/add.php'));
	}

	blog_insert(
		$pdo,
		$title,
		$slug,
		$excerpt,
		$body_html,
		$cover_image !== '' ? $cover_image : null,
		$meta_title,
		$meta_description,
		blog_parse_published_at($published_at_input),
		$is_published,
		$sort_order
	);
	flash_set('success', 'Blog post created.');
	redirect(admin_url('blogs/'));
}

$page_title = 'Add blog post';
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Add blog post</h1>
	<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<form method="post" action="<?= h(admin_url('blogs/add.php')) ?>">
		<?= csrf_field() ?>
		<div class="card mb-4">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-8">
						<label for="title" class="form-label">Title *</label>
						<input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= h($title) ?>">
					</div>
					<div class="col-md-4">
						<label for="slug" class="form-label">Slug *</label>
						<input type="text" class="form-control" id="slug" name="slug" maxlength="128" value="<?= h($slug) ?>" data-manual="">
						<small class="text-muted">URL segment, e.g. <code>silver-care-tips</code></small>
					</div>
					<div class="col-12">
						<label for="excerpt" class="form-label">Excerpt</label>
						<textarea class="form-control" id="excerpt" name="excerpt" rows="2"><?= h($excerpt) ?></textarea>
						<small class="text-muted">Short preview shown on the blog listing.</small>
					</div>
					<div class="col-12">
						<label for="body_html" class="form-label">Body HTML *</label>
						<textarea class="form-control font-monospace small" id="body_html" name="body_html" rows="22" required><?= h($body_html) ?></textarea>
						<small class="text-muted">HTML only (headings, paragraphs, lists, links, images).</small>
					</div>
					<div class="col-md-6">
						<label for="cover_image" class="form-label">Cover image URL or upload path</label>
						<input type="text" class="form-control" id="cover_image" name="cover_image" value="<?= h($cover_image) ?>" placeholder="https://… or upload after saving">
					</div>
					<div class="col-md-6">
						<label for="meta_title" class="form-label">SEO title</label>
						<input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="255" value="<?= h($meta_title) ?>">
					</div>
					<div class="col-12">
						<label for="meta_description" class="form-label">SEO description</label>
						<textarea class="form-control" id="meta_description" name="meta_description" rows="2" maxlength="512"><?= h($meta_description) ?></textarea>
					</div>
					<div class="col-md-4">
						<label for="published_at" class="form-label">Publish date</label>
						<input type="datetime-local" class="form-control" id="published_at" name="published_at" value="<?= h($published_at_input) ?>">
					</div>
					<div class="col-md-4">
						<label for="sort_order" class="form-label">Sort order</label>
						<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int) $sort_order ?>">
					</div>
					<div class="col-12">
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" <?= $is_published ? 'checked' : '' ?>>
							<label class="form-check-label" for="is_published">Published on storefront</label>
						</div>
					</div>
				</div>
				<hr>
				<button type="submit" class="btn btn-primary">Save</button>
				<a href="<?= h(admin_url('blogs/')) ?>" class="btn btn-secondary">Cancel</a>
			</div>
		</div>
	</form>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script>
(function () {
	var title = document.getElementById('title');
	var slug = document.getElementById('slug');
	if (!title || !slug) return;
	function slugify(t) {
		return t.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
	}
	title.addEventListener('input', function () {
		if (!slug.dataset.manual) slug.value = slugify(title.value);
	});
	slug.addEventListener('input', function () {
		slug.dataset.manual = slug.value.trim() !== '' ? '1' : '';
	});
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
