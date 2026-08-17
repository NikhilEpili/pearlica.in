<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('reels');
require_once ROOT . '/lib/instagram_reel.php';

$pdo = $GLOBALS['pdo'];
instagram_reel_ensure_schema($pdo);

$instagram_url = '';
$product_id = '';
$sort_order = instagram_reel_next_sort_order($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (post_exceeded_max_size()) {
		flash_set('error', post_max_size_error_message());
		redirect(admin_url('reels/add.php'));
	}
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('reels/add.php'));
	}
	$instagram_url = trim((string) ($_POST['instagram_url'] ?? ''));
	$product_id_raw = trim((string) ($_POST['product_id'] ?? ''));
	$product_id_val = $product_id_raw !== '' ? (int) $product_id_raw : null;
	$sort_order = (int) ($_POST['sort_order'] ?? $sort_order);

	$video_path = null;
	$poster_path = null;

	try {
		$video_path = instagram_reel_store_upload($_FILES['video'] ?? [], 'video') ?: null;
		$poster_path = instagram_reel_store_upload($_FILES['poster'] ?? [], 'poster') ?: null;

		if ($video_path === null && $instagram_url === '') {
			throw new InvalidArgumentException('Upload a video, or paste an Instagram reel link.');
		}
		if ($product_id_val !== null && $product_id_val > 0) {
			require_once ROOT . '/lib/product.php';
			if (!product_find_by_id($pdo, $product_id_val)) {
				throw new InvalidArgumentException('Product ID not found.');
			}
		}

		instagram_reel_insert($pdo, $instagram_url, $product_id_val, $sort_order, false, $video_path, $poster_path);
		flash_set('success', 'Reel added to the admin list. Push the list when it has an odd number of reels.');
		redirect(admin_url('reels/'));
	} catch (InvalidArgumentException | RuntimeException $e) {
		instagram_reel_delete_media($video_path);
		instagram_reel_delete_media($poster_path);
		flash_set('error', $e->getMessage());
		redirect(admin_url('reels/add.php'));
	}
}

$page_title = 'Add Instagram reel';
$error = flash_get('error');
$max_video_mb = (int) round((defined('REEL_MAX_VIDEO_SIZE') ? REEL_MAX_VIDEO_SIZE : 33554432) / 1048576);
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Add Instagram reel</h1>
	<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<div class="alert alert-info">
		Adding reels is unrestricted. Reels are not changed on the homepage until you use
		<strong>Push to homepage</strong> from the reels list.
	</div>
	<form method="post" action="<?= h(admin_url('reels/add.php')) ?>" enctype="multipart/form-data">
		<?= csrf_field() ?>
		<div class="card mb-4">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-6">
						<label for="video" class="form-label">Reel video <span class="text-muted">(recommended)</span></label>
						<input type="file" class="form-control" id="video" name="video" accept="video/mp4,video/webm,video/quicktime">
						<small class="text-muted">
							MP4 or WebM, vertical 9:16, max <?= $max_video_mb ?>MB. Uploaded videos autoplay muted in the carousel.
							Instagram embeds cannot autoplay, so uploading is strongly preferred.
						</small>
					</div>
					<div class="col-md-6">
						<label for="poster" class="form-label">Poster image <span class="text-muted">(recommended)</span></label>
						<input type="file" class="form-control" id="poster" name="poster" accept="image/jpeg,image/png,image/webp">
						<small class="text-muted">Shown on the side cards and before the video loads. Use a 9:16 frame from the video.</small>
					</div>
					<div class="col-12">
						<label for="instagram_url" class="form-label">Instagram reel or post URL <span class="text-muted">(optional)</span></label>
						<input type="url" class="form-control" id="instagram_url" name="instagram_url" maxlength="512"
							placeholder="https://www.instagram.com/reel/…"
							value="<?= h($instagram_url) ?>">
						<small class="text-muted">Used as a fallback when no video is uploaded, and to link out to the original post.</small>
					</div>
					<div class="col-md-6">
						<label for="product_id" class="form-label">Linked product ID <span class="text-muted">(optional)</span></label>
						<input type="number" class="form-control" id="product_id" name="product_id" min="1" step="1"
							placeholder="e.g. 42"
							value="<?= h($product_id) ?>">
						<small class="text-muted">Shows product name and price below the reel. Find the ID on the Products admin list.</small>
					</div>
					<div class="col-md-4">
						<label for="sort_order" class="form-label">Sort order</label>
						<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int) $sort_order ?>">
						<small class="text-muted">Reel 1 is centered on load, reel 2 sits to its right, and the rest wrap around circularly.</small>
					</div>
				</div>
				<hr>
				<button type="submit" class="btn btn-primary">Save</button>
				<a href="<?= h(admin_url('reels/')) ?>" class="btn btn-secondary">Cancel</a>
			</div>
		</div>
	</form>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
