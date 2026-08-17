<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('reels');
require_once ROOT . '/lib/instagram_reel.php';
require_once ROOT . '/lib/product.php';

$pdo = $GLOBALS['pdo'];
instagram_reel_ensure_schema($pdo);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$row = $id > 0 ? instagram_reel_find_by_id($pdo, $id) : null;
if (!$row) {
	flash_set('error', 'Reel not found.');
	redirect(admin_url('reels/'));
}

$instagram_url = (string) ($row['instagram_url'] ?? '');
$product_id = !empty($row['product_id']) ? (string) (int) $row['product_id'] : '';
$sort_order = (int) ($row['sort_order'] ?? 0);
$is_active = !empty($row['is_active']);
$current_video = (string) ($row['video_path'] ?? '');
$current_poster = (string) ($row['poster_path'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (post_exceeded_max_size()) {
		flash_set('error', post_max_size_error_message());
		redirect(admin_url('reels/edit.php?id=' . $id));
	}
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('reels/edit.php?id=' . $id));
	}
	$instagram_url = trim((string) ($_POST['instagram_url'] ?? ''));
	$product_id_raw = trim((string) ($_POST['product_id'] ?? ''));
	$product_id_val = $product_id_raw !== '' ? (int) $product_id_raw : null;
	$sort_order = (int) ($_POST['sort_order'] ?? $sort_order);
	$remove_video = isset($_POST['remove_video']);
	$remove_poster = isset($_POST['remove_poster']);

	$new_video = null;
	$new_poster = null;

	try {
		$new_video = instagram_reel_store_upload($_FILES['video'] ?? [], 'video') ?: null;
		$new_poster = instagram_reel_store_upload($_FILES['poster'] ?? [], 'poster') ?: null;

		$video_path = $new_video ?? ($remove_video ? null : ($current_video !== '' ? $current_video : null));
		$poster_path = $new_poster ?? ($remove_poster ? null : ($current_poster !== '' ? $current_poster : null));

		if ($video_path === null && $instagram_url === '') {
			throw new InvalidArgumentException('Upload a video, or paste an Instagram reel link.');
		}
		if ($product_id_val !== null && $product_id_val > 0 && !product_find_by_id($pdo, $product_id_val)) {
			throw new InvalidArgumentException('Product ID not found.');
		}

		instagram_reel_update($pdo, $id, $instagram_url, $product_id_val, $sort_order, $is_active, $video_path, $poster_path);

		// Only discard the old files once the row points at the new ones.
		if ($current_video !== '' && $current_video !== $video_path) {
			instagram_reel_delete_media($current_video);
		}
		if ($current_poster !== '' && $current_poster !== $poster_path) {
			instagram_reel_delete_media($current_poster);
		}

		flash_set('success', 'Reel updated.');
		redirect(admin_url('reels/'));
	} catch (InvalidArgumentException | RuntimeException $e) {
		instagram_reel_delete_media($new_video);
		instagram_reel_delete_media($new_poster);
		flash_set('error', $e->getMessage());
		redirect(admin_url('reels/edit.php?id=' . $id));
	}
}

$page_title = 'Edit Instagram reel';
$error = flash_get('error');
$reels_base = uploads_reels_base_url();
$max_video_mb = (int) round((defined('REEL_MAX_VIDEO_SIZE') ? REEL_MAX_VIDEO_SIZE : 33554432) / 1048576);
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Edit Instagram reel</h1>
	<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<div class="alert alert-info small">
		Changes are saved to the admin list. Use <strong>Push to homepage</strong>
		from the reels list when you are ready to publish an odd number of reels.
	</div>
	<form method="post" action="<?= h(admin_url('reels/edit.php?id=' . $id)) ?>" enctype="multipart/form-data">
		<?= csrf_field() ?>
		<input type="hidden" name="id" value="<?= $id ?>">
		<div class="card mb-4">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-6">
						<label for="video" class="form-label">Reel video</label>
						<?php if ($current_video !== ''): ?>
							<div class="mb-2">
								<video src="<?= h($reels_base . '/' . $current_video) ?>" muted playsinline controls
									style="width:150px;aspect-ratio:9/16;object-fit:cover;background:#111;border-radius:4px;"></video>
								<div class="form-check mt-1">
									<input class="form-check-input" type="checkbox" id="remove_video" name="remove_video" value="1">
									<label class="form-check-label small" for="remove_video">Remove current video</label>
								</div>
							</div>
						<?php endif; ?>
						<input type="file" class="form-control" id="video" name="video" accept="video/mp4,video/webm,video/quicktime">
						<small class="text-muted">MP4 or WebM, vertical 9:16, max <?= $max_video_mb ?>MB. Uploading replaces the current video.</small>
					</div>
					<div class="col-md-6">
						<label for="poster" class="form-label">Poster image</label>
						<?php if ($current_poster !== ''): ?>
							<div class="mb-2">
								<img src="<?= h($reels_base . '/' . $current_poster) ?>" alt=""
									style="width:150px;aspect-ratio:9/16;object-fit:cover;background:#111;border-radius:4px;">
								<div class="form-check mt-1">
									<input class="form-check-input" type="checkbox" id="remove_poster" name="remove_poster" value="1">
									<label class="form-check-label small" for="remove_poster">Remove current poster</label>
								</div>
							</div>
						<?php endif; ?>
						<input type="file" class="form-control" id="poster" name="poster" accept="image/jpeg,image/png,image/webp">
						<small class="text-muted">Shown on the side cards. Use a 9:16 frame from the video.</small>
					</div>
					<div class="col-12">
						<label for="instagram_url" class="form-label">Instagram reel or post URL <span class="text-muted">(optional)</span></label>
						<input type="url" class="form-control" id="instagram_url" name="instagram_url" maxlength="512"
							value="<?= h($instagram_url) ?>">
					</div>
					<div class="col-md-6">
						<label for="product_id" class="form-label">Linked product ID <span class="text-muted">(optional)</span></label>
						<input type="number" class="form-control" id="product_id" name="product_id" min="1" step="1"
							value="<?= h($product_id) ?>">
					</div>
					<div class="col-md-4">
						<label for="sort_order" class="form-label">Sort order</label>
						<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int) $sort_order ?>">
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
