<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('blogs');
require_once ROOT . '/lib/blog.php';

$pdo = $GLOBALS['pdo'];
$migration_hint = false;
$posts = [];
try {
	blog_ensure_schema($pdo);
	$posts = blog_list_all($pdo);
} catch (Throwable $e) {
	$posts = [];
	$migration_hint = true;
}

$page_title = 'Blog';
$success = flash_get('success');
$error = flash_get('error');
$blogs_upload_base = uploads_blogs_base_url();
$store_base = rtrim(web_base(), '/');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Blog</h1>
	<p class="text-muted small mb-4">
		Manage journal articles shown on the storefront. Unpublished drafts stay hidden from visitors.
	</p>

	<?php if (!empty($migration_hint)): ?>
		<div class="alert alert-warning">
			Could not create or read the blog table. Check database permissions, or run
			<code>sql/add_blog_posts.sql</code> in phpMyAdmin, then refresh.
		</div>
	<?php endif; ?>
	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

	<div class="mb-3 d-flex flex-wrap gap-2">
		<a href="<?= h(admin_url('blogs/add.php')) ?>" class="btn btn-primary<?= $migration_hint ? ' disabled' : '' ?>">Add post</a>
		<?php if ($store_base !== ''): ?>
			<a href="<?= h($store_base . '/blogs/') ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">View blog</a>
		<?php endif; ?>
	</div>

	<div class="card">
		<div class="card-body p-0">
			<table class="table table-hover mb-0 align-middle">
				<thead>
					<tr>
						<th style="width:4rem">Cover</th>
						<th>Title</th>
						<th>Slug</th>
						<th style="width:8rem">Published</th>
						<th style="width:5rem">Live</th>
						<th style="width:12rem">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($posts as $post): ?>
						<?php
						$id = (int) $post['id'];
						$cover_rel = trim((string) ($post['cover_image'] ?? ''));
						$cover_url = $cover_rel !== '' ? blog_cover_public_url($cover_rel, rtrim(web_base(), '/') . '/') : '';
						$published_label = '';
						$published_raw = trim((string) ($post['published_at'] ?? ''));
						if ($published_raw !== '') {
							try {
								$published_label = (new DateTimeImmutable($published_raw))->format('j M Y');
							} catch (Throwable $e) {
								$published_label = $published_raw;
							}
						}
						?>
						<tr>
							<td>
								<?php if ($cover_url !== ''): ?>
									<img src="<?= h($cover_url) ?>" alt="" class="rounded" width="48" height="32" style="object-fit:cover;">
								<?php else: ?>
									<span class="text-muted small">—</span>
								<?php endif; ?>
							</td>
							<td><?= h((string) ($post['title'] ?? '')) ?></td>
							<td><code><?= h((string) ($post['slug'] ?? '')) ?></code></td>
							<td class="small text-muted"><?= h($published_label !== '' ? $published_label : 'Draft') ?></td>
							<td><?= !empty($post['is_published']) ? 'Yes' : 'No' ?></td>
							<td>
								<a href="<?= h(admin_url('blogs/edit.php?id=' . $id)) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
								<?php if (!empty($post['is_published']) && $store_base !== ''): ?>
									<a href="<?= h($store_base . '/blogs/' . rawurlencode((string) ($post['slug'] ?? ''))) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">View</a>
								<?php endif; ?>
								<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delModal" data-url="<?= h(admin_url('blogs/delete.php')) ?>" data-id="<?= $id ?>" data-name="<?= h((string) ($post['title'] ?? '')) ?>">Delete</button>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($posts) && !$migration_hint): ?>
						<tr><td colspan="6" class="text-muted p-3">No blog posts yet. Click Add post to create one.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="modal fade" id="delModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header"><h5 class="modal-title">Delete blog post</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
				<div class="modal-body">Delete “<span id="delName"></span>”?</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<form id="delForm" method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" id="delId"><button type="submit" class="btn btn-danger">Delete</button></form>
				</div>
			</div>
		</div>
	</div>
	<script>
	document.getElementById('delModal') && document.getElementById('delModal').addEventListener('show.bs.modal', function (e) {
		var btn = e.relatedTarget;
		if (!btn) return;
		document.getElementById('delName').textContent = btn.getAttribute('data-name') || '';
		document.getElementById('delId').value = btn.getAttribute('data-id') || '';
		document.getElementById('delForm').action = btn.getAttribute('data-url') || '';
	});
	</script>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
