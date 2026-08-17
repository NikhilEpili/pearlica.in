<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('banners');
require_once ROOT . '/lib/homepage_banner.php';

$pdo = $GLOBALS['pdo'];
$migration_hint = false;
$banners = [];
try {
	homepage_banner_ensure_schema($pdo);
	$banners = homepage_banner_list_all($pdo);
} catch (Throwable $e) {
	$banners = [];
	$migration_hint = true;
}

$hero = [];
$mid = [];
foreach ($banners as $b) {
	if (($b['placement'] ?? '') === 'mid') {
		$mid[] = $b;
	} else {
		$hero[] = $b;
	}
}

$page_title = 'Homepage banners';
$success = flash_get('success');
$error = flash_get('error');
$uploads_base = uploads_banners_base_url();
$csrf = csrf_token();

function admin_banner_src(array $b, string $uploads_base): string
{
	$rel = (string) ($b['image_url'] ?? '');
	$id = (int) ($b['id'] ?? 0);
	$updated_at = isset($b['updated_at']) ? (string) $b['updated_at'] : null;
	if (function_exists('homepage_banner_public_src_versioned')) {
		return homepage_banner_public_src_versioned($rel, $uploads_base, $id > 0 ? $id : null, $updated_at);
	}
	return homepage_banner_public_src($rel, $uploads_base);
}
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Homepage banners</h1>
	<p class="text-muted small mb-4">
		Manage the homepage hero carousel and the mid-page banner. Recommended size about
		<strong>1280×600</strong> (JPG/PNG/WebP, max 8MB). Drag hero slides to reorder.
		On phones the storefront keeps the same wide ratio — keep important content toward the center.
	</p>

	<?php if (!empty($migration_hint)): ?>
		<div class="alert alert-warning">
			Could not create or read the banners table. Check database permissions, or run
			<code>sql/add_homepage_banners.sql</code> in phpMyAdmin, then refresh.
		</div>
	<?php endif; ?>
	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<div id="banner-ajax-error" class="alert alert-danger d-none" role="alert"></div>

	<section class="mb-5" id="hero">
		<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
			<div>
				<h2 class="h5 mb-0">Hero carousel</h2>
				<p class="text-muted small mb-0">Top of the homepage. Multiple slides rotate automatically.</p>
			</div>
			<label class="btn btn-primary mb-0">
				<span id="hero-upload-label">Upload hero banner</span>
				<input type="file" id="hero-upload-input" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none" <?= $migration_hint ? 'disabled' : '' ?>>
			</label>
		</div>
		<div id="hero-list" class="banner-admin-list" data-placement="hero">
			<?php if (empty($hero)): ?>
				<p class="text-muted banner-empty mb-0" data-empty>No hero banners yet. Upload one, or the storefront will use the default images in <code>assets/images/</code>.</p>
			<?php endif; ?>
			<?php foreach ($hero as $b): ?>
				<?php
				$id = (int) $b['id'];
				$src = admin_banner_src($b, $uploads_base);
				$active = !empty($b['is_active']);
				$missing = !homepage_banner_file_exists((string) ($b['image_url'] ?? ''));
				?>
				<article class="card banner-admin-card mb-3<?= $missing ? ' border-warning' : '' ?>" data-banner-id="<?= $id ?>">
					<div class="card-body">
						<?php if ($missing): ?>
							<div class="alert alert-warning py-2 px-3 small mb-3">
								Image file missing on this server. Delete this banner and upload again, or the homepage may show a blank slide / fallback.
							</div>
						<?php endif; ?>
						<div class="row g-3 align-items-start">
							<div class="col-auto banner-admin-handle text-muted" title="Drag to reorder" aria-hidden="true">
								<i class="fa-solid fa-grip-vertical"></i>
							</div>
							<div class="col-sm-4 col-md-3">
								<img src="<?= h($src) ?>" alt="" class="banner-admin-thumb rounded border w-100" data-banner-preview="<?= $id ?>">
								<label class="btn btn-sm btn-outline-secondary w-100 mt-2 mb-0">
									Replace image
									<input type="file" class="d-none" accept="image/jpeg,image/png,image/gif,image/webp" data-banner-replace="<?= $id ?>">
								</label>
							</div>
							<div class="col">
								<form method="post" action="<?= h(admin_url('banners/save.php')) ?>" class="row g-2">
									<?= csrf_field() ?>
									<input type="hidden" name="id" value="<?= $id ?>">
									<input type="hidden" name="placement" value="hero">
									<div class="col-md-6">
										<label class="form-label small mb-1">Alt text</label>
										<input type="text" name="alt_text" class="form-control form-control-sm" value="<?= h((string) ($b['alt_text'] ?? '')) ?>" maxlength="255">
									</div>
									<div class="col-md-6">
										<label class="form-label small mb-1">Link URL <span class="text-muted">(optional)</span></label>
										<input type="text" name="link_url" class="form-control form-control-sm" value="<?= h((string) ($b['link_url'] ?? '')) ?>" placeholder="/products" maxlength="512">
									</div>
									<div class="col-12 d-flex flex-wrap align-items-center gap-3 mt-1">
										<div class="form-check mb-0">
											<input class="form-check-input" type="checkbox" name="is_active" value="1" id="active_<?= $id ?>" <?= $active ? 'checked' : '' ?>>
											<label class="form-check-label" for="active_<?= $id ?>">Active</label>
										</div>
										<button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
										<button type="button" class="btn btn-sm btn-outline-danger" data-banner-delete="<?= $id ?>">Delete</button>
									</div>
								</form>
							</div>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="mb-4" id="mid">
		<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
			<div>
				<h2 class="h5 mb-0">Mid-page banner</h2>
				<p class="text-muted small mb-0">Shown below New Arrivals. Use one active image; optional link (defaults to Products).</p>
			</div>
			<label class="btn btn-outline-primary mb-0">
				<span id="mid-upload-label">Upload mid banner</span>
				<input type="file" id="mid-upload-input" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none" <?= $migration_hint ? 'disabled' : '' ?>>
			</label>
		</div>
		<div id="mid-list" class="banner-admin-list" data-placement="mid">
			<?php if (empty($mid)): ?>
				<p class="text-muted banner-empty mb-0" data-empty>No mid-page banners yet. The storefront falls back to <code>assets/images/banner_1.png</code>.</p>
			<?php endif; ?>
			<?php foreach ($mid as $b): ?>
				<?php
				$id = (int) $b['id'];
				$src = admin_banner_src($b, $uploads_base);
				$active = !empty($b['is_active']);
				$missing = !homepage_banner_file_exists((string) ($b['image_url'] ?? ''));
				?>
				<article class="card banner-admin-card mb-3<?= $missing ? ' border-warning' : '' ?>" data-banner-id="<?= $id ?>">
					<div class="card-body">
						<?php if ($missing): ?>
							<div class="alert alert-warning py-2 px-3 small mb-3">
								Image file missing on this server. Delete this banner and upload again, or the homepage may show a blank slide / fallback.
							</div>
						<?php endif; ?>
						<div class="row g-3 align-items-start">
							<div class="col-sm-4 col-md-3">
								<img src="<?= h($src) ?>" alt="" class="banner-admin-thumb rounded border w-100" data-banner-preview="<?= $id ?>">
								<label class="btn btn-sm btn-outline-secondary w-100 mt-2 mb-0">
									Replace image
									<input type="file" class="d-none" accept="image/jpeg,image/png,image/gif,image/webp" data-banner-replace="<?= $id ?>">
								</label>
							</div>
							<div class="col">
								<form method="post" action="<?= h(admin_url('banners/save.php')) ?>" class="row g-2">
									<?= csrf_field() ?>
									<input type="hidden" name="id" value="<?= $id ?>">
									<input type="hidden" name="placement" value="mid">
									<div class="col-md-6">
										<label class="form-label small mb-1">Alt text</label>
										<input type="text" name="alt_text" class="form-control form-control-sm" value="<?= h((string) ($b['alt_text'] ?? '')) ?>" maxlength="255">
									</div>
									<div class="col-md-6">
										<label class="form-label small mb-1">Link URL <span class="text-muted">(optional)</span></label>
										<input type="text" name="link_url" class="form-control form-control-sm" value="<?= h((string) ($b['link_url'] ?? '')) ?>" placeholder="/products" maxlength="512">
									</div>
									<div class="col-12 d-flex flex-wrap align-items-center gap-3 mt-1">
										<div class="form-check mb-0">
											<input class="form-check-input" type="checkbox" name="is_active" value="1" id="active_<?= $id ?>" <?= $active ? 'checked' : '' ?>>
											<label class="form-check-label" for="active_<?= $id ?>">Active</label>
										</div>
										<button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
										<button type="button" class="btn btn-sm btn-outline-danger" data-banner-delete="<?= $id ?>">Delete</button>
									</div>
								</form>
							</div>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<style>
.banner-admin-thumb { aspect-ratio: 1280 / 600; object-fit: cover; background: #f5f5f5; display: block; }
.banner-admin-handle { cursor: grab; padding-top: .65rem; font-size: 1.1rem; }
.banner-admin-card.sortable-ghost { opacity: .45; }
@media (max-width: 575px) {
	.banner-admin-card .row { --bs-gutter-x: 0.75rem; }
	.banner-admin-card .btn { min-height: 44px; }
}
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
	var csrf = <?= json_encode($csrf) ?>;
	var uploadUrl = <?= json_encode(admin_url('banners/upload.php')) ?>;
	var deleteUrl = <?= json_encode(admin_url('banners/delete.php')) ?>;
	var reorderUrl = <?= json_encode(admin_url('banners/reorder.php')) ?>;
	var replaceUrl = <?= json_encode(admin_url('banners/replace.php')) ?>;
	var saveUrl = <?= json_encode(admin_url('banners/save.php')) ?>;
	var errBox = document.getElementById('banner-ajax-error');

	function showError(msg) {
		if (!errBox) return;
		errBox.textContent = msg || 'Something went wrong.';
		errBox.classList.remove('d-none');
		errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	function clearError() {
		if (!errBox) return;
		errBox.classList.add('d-none');
		errBox.textContent = '';
	}

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function cardHtml(banner, placement) {
		var id = banner.id;
		var active = banner.is_active ? ' checked' : '';
		var handle = placement === 'hero'
			? '<div class="col-auto banner-admin-handle text-muted" title="Drag to reorder" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></div>'
			: '';
		return (
			'<article class="card banner-admin-card mb-3" data-banner-id="' + id + '">' +
			'<div class="card-body"><div class="row g-3 align-items-start">' +
			handle +
			'<div class="col-sm-4 col-md-3"><img src="' + escapeHtml(banner.src) + '" alt="" class="banner-admin-thumb rounded border w-100" data-banner-preview="' + id + '">' +
			'<label class="btn btn-sm btn-outline-secondary w-100 mt-2 mb-0">Replace image' +
			'<input type="file" class="d-none" accept="image/jpeg,image/png,image/gif,image/webp" data-banner-replace="' + id + '"></label></div>' +
			'<div class="col"><form method="post" action="' + escapeHtml(saveUrl) + '" class="row g-2">' +
			'<input type="hidden" name="csrf_token" value="' + escapeHtml(csrf) + '">' +
			'<input type="hidden" name="id" value="' + id + '">' +
			'<input type="hidden" name="placement" value="' + escapeHtml(placement) + '">' +
			'<div class="col-md-6"><label class="form-label small mb-1">Alt text</label>' +
			'<input type="text" name="alt_text" class="form-control form-control-sm" value="' + escapeHtml(banner.alt_text || '') + '" maxlength="255"></div>' +
			'<div class="col-md-6"><label class="form-label small mb-1">Link URL <span class="text-muted">(optional)</span></label>' +
			'<input type="text" name="link_url" class="form-control form-control-sm" value="' + escapeHtml(banner.link_url || '') + '" placeholder="/products" maxlength="512"></div>' +
			'<div class="col-12 d-flex flex-wrap align-items-center gap-3 mt-1">' +
			'<div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active_' + id + '"' + active + '>' +
			'<label class="form-check-label" for="active_' + id + '">Active</label></div>' +
			'<button type="submit" class="btn btn-sm btn-outline-primary">Save</button>' +
			'<button type="button" class="btn btn-sm btn-outline-danger" data-banner-delete="' + id + '">Delete</button>' +
			'</div></form></div></div></div></article>'
		);
	}

	function bindUpload(inputId, labelId, placement, listId) {
		var input = document.getElementById(inputId);
		var label = document.getElementById(labelId);
		var list = document.getElementById(listId);
		if (!input || !list) return;
		input.addEventListener('change', function () {
			var file = input.files && input.files[0];
			input.value = '';
			if (!file) return;
			clearError();
			if (label) label.textContent = 'Uploading…';
			input.disabled = true;
			var fd = new FormData();
			fd.append('csrf_token', csrf);
			fd.append('placement', placement);
			fd.append('image', file);
			fd.append('alt_text', 'Homepage banner');
			fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data || !data.ok || !data.banner) {
						showError((data && data.error) || 'Upload failed');
						return;
					}
					var empty = list.querySelector('[data-empty]');
					if (empty) empty.remove();
					list.insertAdjacentHTML('beforeend', cardHtml(data.banner, placement));
				})
				.catch(function () { showError('Upload failed'); })
				.finally(function () {
					input.disabled = false;
					if (label) label.textContent = placement === 'mid' ? 'Upload mid banner' : 'Upload hero banner';
				});
		});
	}

	bindUpload('hero-upload-input', 'hero-upload-label', 'hero', 'hero-list');
	bindUpload('mid-upload-input', 'mid-upload-label', 'mid', 'mid-list');

	document.addEventListener('change', function (e) {
		var input = e.target.closest('[data-banner-replace]');
		if (!input || !input.files || !input.files[0]) return;
		var id = parseInt(input.getAttribute('data-banner-replace'), 10);
		if (!id) return;
		var file = input.files[0];
		input.value = '';
		clearError();
		var card = document.querySelector('[data-banner-id="' + id + '"]');
		var preview = card && card.querySelector('[data-banner-preview="' + id + '"]');
		var label = input.closest('label');
		if (label) label.textContent = 'Uploading…';
		var fd = new FormData();
		fd.append('csrf_token', csrf);
		fd.append('id', String(id));
		fd.append('image', file);
		fetch(replaceUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data || !data.ok || !data.banner) {
					showError((data && data.error) || 'Replace failed');
					return;
				}
				if (preview && data.banner.src) {
					preview.src = data.banner.src;
				}
				if (card) {
					card.classList.remove('border-warning');
					var warn = card.querySelector('.alert-warning');
					if (warn) warn.remove();
				}
			})
			.catch(function () { showError('Replace failed'); })
			.finally(function () {
				if (label) label.textContent = 'Replace image';
			});
	});

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-banner-delete]');
		if (!btn) return;
		var id = parseInt(btn.getAttribute('data-banner-delete'), 10);
		if (!id || !window.confirm('Delete this banner?')) return;
		clearError();
		fetch(deleteUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ csrf_token: csrf, id: id })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data || !data.ok) {
					showError((data && data.error) || 'Delete failed');
					return;
				}
				var card = document.querySelector('[data-banner-id="' + id + '"]');
				var list = card && card.parentElement;
				if (card) card.remove();
				if (list && !list.querySelector('[data-banner-id]')) {
					var placement = list.getAttribute('data-placement') || 'hero';
					var msg = placement === 'mid'
						? 'No mid-page banners yet. The storefront falls back to <code>assets/images/banner_1.png</code>.'
						: 'No hero banners yet. Upload one, or the storefront will use the default images in <code>assets/images/</code>.';
					list.insertAdjacentHTML('beforeend', '<p class="text-muted banner-empty mb-0" data-empty>' + msg + '</p>');
				}
			})
			.catch(function () { showError('Delete failed'); });
	});

	var heroList = document.getElementById('hero-list');
	if (heroList && typeof Sortable !== 'undefined') {
		new Sortable(heroList, {
			handle: '.banner-admin-handle',
			draggable: '.banner-admin-card',
			animation: 150,
			ghostClass: 'sortable-ghost',
			onEnd: function () {
				var ids = Array.prototype.map.call(heroList.querySelectorAll('[data-banner-id]'), function (el) {
					return parseInt(el.getAttribute('data-banner-id'), 10);
				}).filter(Boolean);
				fetch(reorderUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ csrf_token: csrf, placement: 'hero', order: ids })
				}).then(function (r) { return r.json(); }).then(function (data) {
					if (!data || !data.ok) showError((data && data.error) || 'Could not save order');
				}).catch(function () { showError('Could not save order'); });
			}
		});
	}
})();
</script>
</body>
</html>
