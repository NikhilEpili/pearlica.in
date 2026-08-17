<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('reels');
require_once ROOT . '/lib/instagram_reel.php';

$pdo = $GLOBALS['pdo'];
$migration_hint = false;
$reels = [];
try {
	instagram_reel_ensure_schema($pdo);
	$reels = instagram_reel_list_all($pdo);
} catch (Throwable $e) {
	$reels = [];
	$migration_hint = true;
}
$library_count = 0;
$published_count = 0;
$can_publish = false;
if (!$migration_hint) {
	try {
		$library_count = count($reels);
		$published_count = instagram_reel_count_active($pdo);
		$can_publish = $library_count > 0 && $library_count % 2 === 1;
	} catch (Throwable $e) {
		$library_count = 0;
	}
}

$page_title = 'Instagram reels';
$success = flash_get('success');
$error = flash_get('error');
$csrf = csrf_token();
$reels_base = uploads_reels_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Instagram reels</h1>
	<p class="text-muted small mb-4">
		Manage the reels shown in the homepage <strong>Your Favourite Influencers</strong> carousel (below New Arrivals).
		<strong>Upload an MP4 and a poster image</strong> for each reel — uploaded videos autoplay muted in the carousel,
		while Instagram embeds cannot autoplay and need a click. Optionally link a product to show its name and price under the reel.
		Adding, editing, deleting, and reordering are unrestricted. They affect the homepage only when you press
		<strong>Push to homepage</strong>. Publishing requires an odd total (1, 3, 5, 7…).
	</p>

	<?php if (!$migration_hint): ?>
		<div class="alert alert-<?= $can_publish ? 'secondary' : 'warning' ?> py-2 small mb-3">
			Admin list: <strong><?= (int) $library_count ?></strong> reel(s).
			Homepage currently: <strong><?= (int) $published_count ?></strong> reel(s).
			<?php if ($can_publish): ?>
				— ready to push.
			<?php else: ?>
				— upload or delete reels until the admin list has an odd total before pushing.
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if (!empty($migration_hint)): ?>
		<div class="alert alert-warning">
			Reels could not be loaded. The database table will be created automatically when permissions allow —
			refresh this page in a moment. If this persists, contact your developer.
		</div>
	<?php endif; ?>
	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<div id="reel-ajax-error" class="alert alert-danger d-none" role="alert"></div>

	<div class="mb-3 d-flex flex-wrap gap-2">
		<a href="<?= h(admin_url('reels/add.php')) ?>" class="btn btn-primary<?= $migration_hint ? ' disabled' : '' ?>">Add reel</a>
		<form method="post" action="<?= h(admin_url('reels/publish.php')) ?>" class="d-inline"
			onsubmit="return confirm('Push the current reel list and order to the homepage?');">
			<?= csrf_field() ?>
			<button type="submit" class="btn btn-success"<?= $can_publish ? '' : ' disabled' ?>>
				<i class="fa-solid fa-cloud-arrow-up me-1" aria-hidden="true"></i>
				Push to homepage
			</button>
		</form>
	</div>

	<div id="reels-list" class="reels-admin-list">
		<?php if (empty($reels)): ?>
			<p class="text-muted mb-0" data-empty>No reels yet. Upload a video to show it on the homepage.</p>
		<?php endif; ?>
		<?php foreach ($reels as $i => $r): ?>
			<?php
			$id = (int) $r['id'];
			$url = (string) ($r['instagram_url'] ?? '');
			$product_name = trim((string) ($r['product_name'] ?? ''));
			$active = !empty($r['is_active']);
			$reel_number = $i + 1;
			$video_rel = trim((string) ($r['video_path'] ?? ''));
			$poster_rel = trim((string) ($r['poster_path'] ?? ''));
			?>
			<article class="card mb-3 reels-admin-card" data-reel-id="<?= $id ?>">
				<div class="card-body">
					<div class="row g-3 align-items-start">
						<div class="col-auto reels-admin-handle text-muted" title="Drag to reorder" aria-hidden="true">
							<i class="fa-solid fa-grip-vertical"></i>
						</div>
						<?php if ($poster_rel !== '' || $video_rel !== ''): ?>
							<div class="col-auto">
								<?php if ($poster_rel !== ''): ?>
									<img src="<?= h($reels_base . '/' . $poster_rel) ?>" alt=""
										style="width:56px;aspect-ratio:9/16;object-fit:cover;border-radius:3px;background:#111;">
								<?php else: ?>
									<video src="<?= h($reels_base . '/' . $video_rel) ?>" muted playsinline preload="metadata"
										style="width:56px;aspect-ratio:9/16;object-fit:cover;border-radius:3px;background:#111;"></video>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<div class="col">
							<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
								<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Reel <?= $reel_number ?></span>
								<?php if ($video_rel !== ''): ?>
									<span class="badge bg-success-subtle text-success border border-success-subtle">Video uploaded</span>
								<?php else: ?>
									<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" title="Instagram embeds cannot autoplay">Embed only</span>
								<?php endif; ?>
								<?php if ($video_rel !== '' && $poster_rel === ''): ?>
									<span class="badge bg-light text-dark border">No poster</span>
								<?php endif; ?>
								<?php if ($active): ?>
									<span class="badge bg-success-subtle text-success border border-success-subtle">Published</span>
								<?php else: ?>
									<span class="badge bg-secondary-subtle text-secondary border">Not published</span>
								<?php endif; ?>
							</div>
							<?php if ($url !== ''): ?>
								<p class="mb-1 small">
									<a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><?= h($url) ?></a>
								</p>
							<?php endif; ?>
							<?php if ($product_name !== ''): ?>
								<p class="text-muted small mb-0">Product: <?= h($product_name) ?></p>
							<?php endif; ?>
						</div>
						<div class="col-auto d-flex flex-wrap gap-2">
							<a href="<?= h(admin_url('reels/edit.php?id=' . $id)) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
							<button type="button" class="btn btn-sm btn-outline-danger" data-reel-delete="<?= $id ?>">Delete</button>
						</div>
					</div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
	var csrf = <?= json_encode($csrf) ?>;
	var reorderUrl = <?= json_encode(admin_url('reels/reorder.php')) ?>;
	var deleteUrl = <?= json_encode(admin_url('reels/delete.php')) ?>;
	var list = document.getElementById('reels-list');
	var errBox = document.getElementById('reel-ajax-error');

	function showError(msg) {
		if (!errBox) return;
		errBox.textContent = msg || 'Request failed';
		errBox.classList.remove('d-none');
	}
	function clearError() {
		if (!errBox) return;
		errBox.classList.add('d-none');
		errBox.textContent = '';
	}

	if (list && typeof Sortable !== 'undefined') {
		new Sortable(list, {
			handle: '.reels-admin-handle',
			animation: 150,
			onEnd: function () {
				var order = [];
				list.querySelectorAll('[data-reel-id]').forEach(function (el) {
					order.push(parseInt(el.getAttribute('data-reel-id'), 10));
				});
				fetch(reorderUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					credentials: 'same-origin',
					body: JSON.stringify({ csrf_token: csrf, order: order })
				}).then(function (r) { return r.json(); }).then(function (data) {
					if (!data || !data.ok) showError((data && data.error) || 'Could not save order');
				}).catch(function () { showError('Could not save order'); });
			}
		});
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-reel-delete]');
		if (!btn) return;
		var id = parseInt(btn.getAttribute('data-reel-delete'), 10);
		if (!id || !window.confirm('Delete this reel from the admin list? The published homepage stays unchanged until the next push.')) return;
		clearError();
		btn.disabled = true;
		fetch(deleteUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'same-origin',
			body: JSON.stringify({ csrf_token: csrf, id: id })
		}).then(function (r) { return r.json(); }).then(function (data) {
			if (!data || !data.ok) {
				showError((data && data.error) || 'Delete failed');
				btn.disabled = false;
				return;
			}
			window.location.reload();
		}).catch(function () {
			showError('Delete failed');
			btn.disabled = false;
		});
	});
})();
</script>
</body>
</html>
