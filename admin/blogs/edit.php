<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('blogs');
require_once ROOT . '/lib/blog.php';

$pdo = $GLOBALS['pdo'];
blog_ensure_schema($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = $id > 0 ? blog_find_by_id($pdo, $id) : null;
if (!$post) {
	flash_set('error', 'Blog post not found.');
	redirect(admin_url('blogs/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('blogs/edit.php?id=' . $id));
	}
	$title = trim((string) ($_POST['title'] ?? ''));
	$slug = blog_normalize_slug(trim((string) ($_POST['slug'] ?? '')), $title);
	$excerpt = trim((string) ($_POST['excerpt'] ?? ''));
	$body_html = (string) ($_POST['body_html'] ?? '');
	$cover_image = trim((string) ($_POST['cover_image'] ?? ''));
	$meta_title = trim((string) ($_POST['meta_title'] ?? ''));
	$meta_description = trim((string) ($_POST['meta_description'] ?? ''));
	$published_at_input = trim((string) ($_POST['published_at'] ?? ''));
	$sort_order = (int) ($_POST['sort_order'] ?? 0);
	$is_published = isset($_POST['is_published']);

	if ($title === '' || trim($body_html) === '') {
		flash_set('error', 'Title and body HTML are required.');
		redirect(admin_url('blogs/edit.php?id=' . $id));
	}
	$slug_error = blog_validate_slug($slug);
	if ($slug_error !== null) {
		flash_set('error', $slug_error);
		redirect(admin_url('blogs/edit.php?id=' . $id));
	}
	if (blog_slug_exists($pdo, $slug, $id)) {
		flash_set('error', 'Slug already in use.');
		redirect(admin_url('blogs/edit.php?id=' . $id));
	}

	blog_update(
		$pdo,
		$id,
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
	flash_set('success', 'Blog post updated.');
	redirect(admin_url('blogs/'));
}

$page_title = 'Edit blog post';
$error = flash_get('error');
$title = (string) ($post['title'] ?? '');
$slug = (string) ($post['slug'] ?? '');
$excerpt = (string) ($post['excerpt'] ?? '');
$body_html = (string) ($post['body_html'] ?? '');
$cover_image = (string) ($post['cover_image'] ?? '');
$meta_title = (string) ($post['meta_title'] ?? '');
$meta_description = (string) ($post['meta_description'] ?? '');
$published_at_input = blog_format_published_at_input($post['published_at'] ?? null);
$sort_order = (int) ($post['sort_order'] ?? 0);
$is_published = !empty($post['is_published']);
$cover_url = $cover_image !== '' ? blog_cover_public_url($cover_image, rtrim(web_base(), '/') . '/') : '';
$store_base = rtrim(web_base(), '/');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Edit blog post</h1>
	<p class="text-muted small mb-3">
		Slug: <code><?= h($slug) ?></code>
		<?php if ($is_published && $store_base !== ''): ?>
			· Public URL: <a href="<?= h($store_base . '/blogs/' . rawurlencode($slug)) ?>" target="_blank" rel="noopener">/blogs/<?= h($slug) ?></a>
		<?php endif; ?>
	</p>
	<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<form method="post" action="<?= h(admin_url('blogs/edit.php?id=' . $id)) ?>">
		<?= csrf_field() ?>
		<div class="row g-4">
			<div class="col-lg-8">
				<div class="card mb-4">
					<div class="card-body">
						<div class="row g-3">
							<div class="col-12">
								<label for="title" class="form-label">Title *</label>
								<input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= h($title) ?>">
							</div>
							<div class="col-md-6">
								<label for="slug" class="form-label">Slug *</label>
								<input type="text" class="form-control" id="slug" name="slug" required maxlength="128" value="<?= h($slug) ?>" data-manual="1">
							</div>
							<div class="col-12">
								<label for="excerpt" class="form-label">Excerpt</label>
								<textarea class="form-control" id="excerpt" name="excerpt" rows="2"><?= h($excerpt) ?></textarea>
							</div>
							<div class="col-12">
								<label for="body_html" class="form-label">Body HTML *</label>
								<textarea class="form-control font-monospace small" id="body_html" name="body_html" rows="22" required><?= h($body_html) ?></textarea>
							</div>
							<div class="col-12">
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
						<button type="submit" class="btn btn-primary">Update</button>
						<a href="<?= h(admin_url('blogs/')) ?>" class="btn btn-secondary">Cancel</a>
					</div>
				</div>
			</div>
			<div class="col-lg-4">
				<div class="card mb-4">
					<div class="card-body">
						<h2 class="h6 mb-3">Cover image</h2>
						<div class="ratio ratio-16x9 mb-3 border rounded overflow-hidden bg-light">
							<?php if ($cover_url !== ''): ?>
								<img src="<?= h($cover_url) ?>" alt="" class="w-100 h-100" style="object-fit: cover;" id="blog-cover-img">
							<?php else: ?>
								<div class="d-flex align-items-center justify-content-center text-muted small" id="blog-cover-placeholder">No cover yet</div>
							<?php endif; ?>
						</div>
						<label for="cover_image" class="form-label">Stored path or external URL</label>
						<input type="text" class="form-control mb-2" id="cover_image" name="cover_image" value="<?= h($cover_image) ?>">
						<input type="file" class="form-control mb-2" id="cover_file" accept="image/jpeg,image/png,image/gif,image/webp">
						<button type="button" class="btn btn-sm btn-outline-primary" id="cover_upload_btn">Upload cover</button>
						<?php if ($cover_image !== ''): ?>
							<button type="button" class="btn btn-sm btn-outline-danger" id="cover_remove_btn">Remove cover</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</form>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script>
(function () {
	var uploadUrl = <?= json_encode(admin_url('blogs/image-upload.php')) ?>;
	var deleteUrl = <?= json_encode(admin_url('blogs/image-delete.php')) ?>;
	var csrf = <?= json_encode(csrf_token()) ?>;
	var postId = <?= (int) $id ?>;
	var coverInput = document.getElementById('cover_image');
	var fileInput = document.getElementById('cover_file');
	var uploadBtn = document.getElementById('cover_upload_btn');
	var removeBtn = document.getElementById('cover_remove_btn');

	function setPreview(url) {
		var wrap = document.querySelector('.ratio');
		if (!wrap) return;
		wrap.innerHTML = '<img src="' + url + '" alt="" class="w-100 h-100" style="object-fit: cover;" id="blog-cover-img">';
	}

	if (uploadBtn && fileInput) {
		uploadBtn.addEventListener('click', function () {
			if (!fileInput.files || !fileInput.files[0]) {
				alert('Choose an image first.');
				return;
			}
			var fd = new FormData();
			fd.append('csrf_token', csrf);
			fd.append('post_id', String(postId));
			fd.append('image', fileInput.files[0]);
			uploadBtn.disabled = true;
			fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data || !data.ok) throw new Error((data && data.error) || 'Upload failed');
					if (coverInput) coverInput.value = data.path || '';
					if (data.url) setPreview(data.url);
					fileInput.value = '';
				})
				.catch(function (err) { alert(err.message || 'Upload failed'); })
				.finally(function () { uploadBtn.disabled = false; });
		});
	}

	if (removeBtn) {
		removeBtn.addEventListener('click', function () {
			if (!confirm('Remove the cover image?')) return;
			var fd = new FormData();
			fd.append('csrf_token', csrf);
			fd.append('post_id', String(postId));
			removeBtn.disabled = true;
			fetch(deleteUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data || !data.ok) throw new Error((data && data.error) || 'Remove failed');
					if (coverInput) coverInput.value = '';
					location.reload();
				})
				.catch(function (err) { alert(err.message || 'Remove failed'); })
				.finally(function () { removeBtn.disabled = false; });
		});
	}
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
