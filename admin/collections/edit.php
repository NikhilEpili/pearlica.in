<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('collections');
require_once ROOT . '/lib/collection.php';

$pdo = $GLOBALS['pdo'];
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
	redirect(admin_url('collections/'));
}
$collection = collection_find_by_id($pdo, $id);
if (!$collection) {
	flash_set('error', 'Collection not found.');
	redirect(admin_url('collections/'));
}

$picker_q = trim($_GET['picker_q'] ?? '');
$picker_products = collection_list_products_for_picker($pdo, $picker_q, 600);
$selected_ids = collection_product_ids_ordered($pdo, $id);
$selected_set = array_fill_keys($selected_ids, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('collections/edit.php?id=' . $id));
	}
	$name = trim($_POST['name'] ?? '');
	$slug = trim($_POST['slug'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$meta_title = trim($_POST['meta_title'] ?? '');
	$meta_description = trim($_POST['meta_description'] ?? '');
	$product_ids = isset($_POST['product_ids']) && is_array($_POST['product_ids']) ? $_POST['product_ids'] : [];
	if ($name === '') {
		flash_set('error', 'Name is required.');
		redirect(admin_url('collections/edit.php?id=' . $id));
	}
	if ($slug === '') {
		$slug = slug_from_string($name);
	}
	if (collection_slug_exists($pdo, $slug, $id)) {
		flash_set('error', 'Slug already in use.');
		redirect(admin_url('collections/edit.php?id=' . $id));
	}
	collection_update($pdo, $id, [
		'name' => $name,
		'slug' => $slug,
		'description' => $description !== '' ? $description : null,
		'meta_title' => $meta_title !== '' ? $meta_title : null,
		'meta_description' => $meta_description !== '' ? $meta_description : null,
	]);
	$ordered = [];
	foreach ($product_ids as $pid) {
		$ordered[] = (int) $pid;
	}
	collection_replace_products($pdo, $id, $ordered);
	flash_set('success', 'Collection saved.');
	redirect(admin_url('collections/edit.php?id=' . $id));
}

$page_title = 'Edit collection';
$name = $collection['name'];
$slug = $collection['slug'];
$description = (string) ($collection['description'] ?? '');
$meta_title = (string) ($collection['meta_title'] ?? '');
$meta_description = (string) ($collection['meta_description'] ?? '');
$error = flash_get('error');
$include_cropper = true;
$collections_upload_base = uploads_collections_base_url();
$thumbnail_rel = trim((string) ($collection['thumbnail_url'] ?? ''));
$success = flash_get('success');
$info = flash_get('info');

$picker_rows = [];
foreach ($picker_products as $p) {
	$pid = (int) $p['id'];
	if (!isset($selected_set[$pid])) {
		$picker_rows[] = $p;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
<h1 class="page-title">Edit collection</h1>
<p class="text-muted small mb-3">Update name, description, cover image, and the product list below. Click <strong>Save</strong> when done. Cover image uploads apply immediately.</p>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
<?php if (!empty($info)): ?><div class="alert alert-info"><?= h($info) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

<form method="get" action="<?= h(admin_url('collections/edit.php')) ?>" class="mb-3 d-flex gap-2 flex-wrap align-items-end">
	<input type="hidden" name="id" value="<?= (int) $id ?>">
	<label for="picker_q" class="form-label mb-0 me-2">Product picker search</label>
	<input type="search" class="form-control form-control-sm" id="picker_q" name="picker_q" placeholder="Name or SKU…" value="<?= h($picker_q) ?>" style="min-width:14rem;max-width:22rem">
	<button type="submit" class="btn btn-sm btn-secondary">Filter list</button>
	<?php if ($picker_q !== ''): ?>
	<a href="<?= h(admin_url('collections/edit.php?id=' . $id)) ?>" class="btn btn-sm btn-outline-secondary">Clear search</a>
	<?php endif; ?>
</form>

<form method="post" action="<?= h(admin_url('collections/edit.php?id=' . $id)) ?>" id="collection-form">
	<?= csrf_field() ?>
	<div class="row mb-4">
		<div class="col-lg-8">
			<div class="mb-3">
				<label for="name" class="form-label">Name *</label>
				<input type="text" class="form-control" id="name" name="name" required value="<?= h($name) ?>">
			</div>
			<div class="mb-3">
				<label for="slug" class="form-label">Slug *</label>
				<input type="text" class="form-control" id="slug" name="slug" required value="<?= h($slug) ?>">
			</div>
			<div class="mb-3">
				<label for="description" class="form-label">Description</label>
				<textarea class="form-control" id="description" name="description" rows="4"><?= h($description) ?></textarea>
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
			<p class="small text-muted mb-0">Storefront: <a href="<?= h(rtrim(web_base(), '/') . '/collection?slug=' . rawurlencode($slug)) ?>" target="_blank" rel="noopener">Open live page</a></p>
		</div>
	</div>

		<div class="card mb-4">
			<div class="card-header bg-light">Collection cover</div>
			<div class="card-body">
				<p class="small text-muted mb-3">Portrait <strong>3:4</strong> image, exported at <strong>1080 × 1440</strong> px (same workflow as product photos). Used on the collections listing.</p>
				<div id="collection-thumb-drop-zone" class="border border-2 border-dashed rounded p-4 text-center mb-3"
					style="min-height: 120px; cursor: pointer;">
					<input type="file" id="collection-thumb-file-input" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
					<span class="drop-zone-text text-muted">Drop an image here or click to upload</span>
					<div class="drop-zone-loading d-none flex-column align-items-center justify-content-center" id="collection-thumb-upload-loading" style="min-height: 100px;">
						<div class="spinner-border text-dark" role="status" style="width: 2.5rem; height: 2.5rem;"><span class="visually-hidden">Loading</span></div>
						<p class="text-danger small mt-2 mb-0 fw-medium">Please do not close this window or refresh.</p>
					</div>
				</div>
				<ul id="collection-thumb-list" class="list-unstyled d-flex flex-wrap gap-2 mb-0">
					<?php if ($thumbnail_rel !== ''): ?>
					<li class="collection-thumb-item border rounded overflow-hidden position-relative" style="width: 120px; aspect-ratio: 3/4; flex-shrink: 0;">
						<img src="<?= h($collections_upload_base . '/' . $thumbnail_rel) ?>" alt="" class="w-100 h-100 object-fit-cover" id="collection-thumb-img">
						<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 collection-thumb-delete-btn" title="Remove">×</button>
					</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>

		<div class="modal fade" id="collection-thumb-crop-modal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" style="max-width: min(92vw, 760px);">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Crop cover (3:4)</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div class="small text-muted mb-2">Target export size: 1080 × 1440 px</div>
						<div class="w-100 bg-light d-flex align-items-center justify-content-center mx-auto" style="width: min(100%, 420px); aspect-ratio: 3 / 4; max-height: 70vh; overflow: hidden;">
							<img id="collection-cropper-image-preview" alt="Crop preview" style="max-width: 100%; max-height: 100%; display: block;">
						</div>
						<div class="small text-muted mt-2 mb-0">Tip: zoom is limited to preserve export quality.</div>
					</div>
					<div class="modal-footer d-flex justify-content-between">
						<div class="d-flex gap-2">
							<button type="button" id="collection-cropper-zoom-out" class="btn btn-secondary btn-sm">Zoom Out</button>
							<button type="button" id="collection-cropper-zoom-in" class="btn btn-secondary btn-sm">Zoom In</button>
							<button type="button" id="collection-cropper-reset" class="btn btn-secondary btn-sm">Reset</button>
						</div>
						<div class="d-flex gap-2">
							<button type="button" id="collection-cropper-skip" class="btn btn-danger btn-sm">Skip</button>
							<button type="button" id="collection-cropper-apply-upload" class="btn btn-success btn-sm">Crop &amp; Upload</button>
						</div>
					</div>
				</div>
			</div>
		</div>

	<h2 class="h5">Products in this collection</h2>
	<p class="text-muted small">Add from the left, remove with <strong>×</strong>, reorder with ↑ ↓. Only <strong>active</strong> products with stock appear on the live page. Remember to click <strong>Save</strong>.</p>
	<div class="row g-3">
		<div class="col-lg-5">
			<div class="card">
				<div class="card-header">Add products</div>
				<div class="card-body">
					<p class="small text-muted mb-2">Search above by name or SKU (max 600). Products already in the collection are hidden here.</p>
					<div class="admin-collection-pool" style="max-height:22rem;overflow:auto;border:1px solid #dee2e6;border-radius:.25rem">
						<table class="table table-sm table-striped mb-0">
							<tbody id="pool-body">
								<?php foreach ($picker_rows as $p): ?>
								<tr data-pool-id="<?= (int) $p['id'] ?>" data-label="<?= h($p['name']) ?>" data-sku="<?= h((string) ($p['sku'] ?? '')) ?>" data-status="<?= h((string) ($p['status'] ?? '')) ?>">
									<td class="small"><?= h($p['name']) ?><br><span class="text-muted"><?= h((string) ($p['sku'] ?? '')) ?></span> <span class="badge bg-light text-dark"><?= h((string) ($p['status'] ?? '')) ?></span></td>
									<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary js-add-product" data-id="<?= (int) $p['id'] ?>" data-label="<?= h($p['name']) ?>" data-sku="<?= h((string) ($p['sku'] ?? '')) ?>" data-status="<?= h((string) ($p['status'] ?? '')) ?>">Add</button></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="text-muted small p-3 mb-0<?= !empty($picker_rows) ? ' d-none' : '' ?>" id="pool-empty">No products match this filter, or all matches are already in the collection.</p>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-7">
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<span>Order in collection</span>
					<span class="small text-muted" id="ordered-count"><?= (int) count($selected_ids) ?> selected</span>
				</div>
				<div class="card-body">
					<ul class="list-group" id="ordered-list">
						<?php
						$id_to_meta = [];
						foreach ($picker_products as $p) {
							$id_to_meta[(int) $p['id']] = [
								'name' => (string) $p['name'],
								'sku' => (string) ($p['sku'] ?? ''),
								'status' => (string) ($p['status'] ?? ''),
							];
						}
						$missing_label = array_values(array_diff($selected_ids, array_keys($id_to_meta)));
						if ($missing_label !== []) {
							$ph = implode(',', array_fill(0, count($missing_label), '?'));
							$st = $pdo->prepare("SELECT id, name, sku, status FROM products WHERE deleted_at IS NULL AND id IN ({$ph})");
							$st->execute($missing_label);
							while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
								$id_to_meta[(int) $r['id']] = [
									'name' => (string) $r['name'],
									'sku' => (string) ($r['sku'] ?? ''),
									'status' => (string) ($r['status'] ?? ''),
								];
							}
						}
						foreach ($selected_ids as $pid):
							$meta = $id_to_meta[$pid] ?? ['name' => '#' . $pid, 'sku' => '', 'status' => ''];
							$lbl = $meta['name'];
							?>
						<li class="list-group-item d-flex align-items-center gap-2 js-order-item" data-id="<?= (int) $pid ?>" data-label="<?= h($lbl) ?>" data-sku="<?= h($meta['sku']) ?>" data-status="<?= h($meta['status']) ?>">
							<span class="flex-grow-1"><?= h($lbl) ?> <small class="text-muted">#<?= (int) $pid ?></small></span>
							<button type="button" class="btn btn-sm btn-outline-secondary js-up" aria-label="Move up">↑</button>
							<button type="button" class="btn btn-sm btn-outline-secondary js-down" aria-label="Move down">↓</button>
							<button type="button" class="btn btn-sm btn-outline-danger js-remove" aria-label="Remove">×</button>
						</li>
						<?php endforeach; ?>
					</ul>
					<p class="text-muted small mb-0<?= !empty($selected_ids) ? ' d-none' : '' ?>" id="ordered-empty">No products yet. Use Add from the left.</p>
				</div>
			</div>
		</div>
	</div>
	<div id="hidden-inputs"></div>
	<button type="submit" class="btn btn-primary mt-3">Save collection</button>
	<a href="<?= h(admin_url('collections/')) ?>" class="btn btn-secondary mt-3">Back to list</a>
</form>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
(function () {
	var collectionId = <?= (int) $id ?>;
	var uploadUrl = <?= json_encode(admin_url('collections/image-upload.php')) ?>;
	var deleteUrl = <?= json_encode(admin_url('collections/image-delete.php')) ?>;
	var csrfToken = <?= json_encode(csrf_token()) ?>;
	var uploadsBase = <?= json_encode($collections_upload_base) ?>;

	var dropZone = document.getElementById('collection-thumb-drop-zone');
	var fileInput = document.getElementById('collection-thumb-file-input');
	var thumbList = document.getElementById('collection-thumb-list');
	var cropModalEl = document.getElementById('collection-thumb-crop-modal');
	var cropImageEl = document.getElementById('collection-cropper-image-preview');
	var cropApplyBtn = document.getElementById('collection-cropper-apply-upload');
	var cropSkipBtn = document.getElementById('collection-cropper-skip');
	var cropZoomInBtn = document.getElementById('collection-cropper-zoom-in');
	var cropZoomOutBtn = document.getElementById('collection-cropper-zoom-out');
	var cropResetBtn = document.getElementById('collection-cropper-reset');
	if (!dropZone || !fileInput || !thumbList) return;

	var cropModal = null;
	var cropper = null;
	var cropQueue = [];
	var currentCropFile = null;
	var currentObjectUrl = '';
	var isCroppingFlow = false;
	var minZoomRatio = null;
	var maxZoomRatio = null;

	var dropZoneText = dropZone.querySelector('.drop-zone-text');
	var uploadLoading = document.getElementById('collection-thumb-upload-loading');
	function setUploading(show) {
		if (!dropZoneText || !uploadLoading) return;
		if (show) {
			dropZoneText.classList.add('d-none');
			uploadLoading.classList.remove('d-none');
			uploadLoading.classList.add('d-flex');
		} else {
			dropZoneText.classList.remove('d-none');
			uploadLoading.classList.add('d-none');
			uploadLoading.classList.remove('d-flex');
		}
	}
	function thumbSrcFromRel(rel) {
		if (!rel) return '';
		return uploadsBase + '/' + String(rel).split('/').map(function (p) { return encodeURIComponent(p); }).join('/');
	}
	function renderThumbLi(relPath) {
		thumbList.innerHTML = '';
		var li = document.createElement('li');
		li.className = 'collection-thumb-item border rounded overflow-hidden position-relative';
		li.style.cssText = 'width: 120px; aspect-ratio: 3/4; flex-shrink: 0;';
		var img = document.createElement('img');
		img.src = thumbSrcFromRel(relPath);
		img.alt = '';
		img.className = 'w-100 h-100 object-fit-cover';
		img.id = 'collection-thumb-img';
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'btn btn-sm btn-danger position-absolute top-0 end-0 m-1 collection-thumb-delete-btn';
		btn.title = 'Remove';
		btn.textContent = '×';
		btn.addEventListener('click', deleteThumb);
		li.appendChild(img);
		li.appendChild(btn);
		thumbList.appendChild(li);
	}
	function deleteThumb() {
		if (!confirm('Remove collection cover?')) return;
		fetch(deleteUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ csrf_token: csrfToken, collection_id: collectionId })
		}).then(function (r) { return r.json(); }).then(function (data) {
			if (data.ok) thumbList.innerHTML = '';
			else if (data.error) alert(data.error);
		}).catch(function () { alert('Error'); });
	}
	thumbList.querySelectorAll('.collection-thumb-delete-btn').forEach(function (b) {
		b.addEventListener('click', deleteThumb);
	});

	function ensureCropModal() {
		if (!cropModalEl) return null;
		if (!cropModal && typeof window.bootstrap !== 'undefined' && bootstrap.Modal) {
			cropModal = new bootstrap.Modal(cropModalEl, { backdrop: 'static', keyboard: false });
		}
		return cropModal;
	}
	function uploadRawFiles(files, done) {
		if (!files || !files.length) {
			if (typeof done === 'function') done(false);
			return;
		}
		var fd = new FormData();
		fd.append('csrf_token', csrfToken);
		fd.append('collection_id', collectionId);
		for (var i = 0; i < files.length; i++) fd.append('images[]', files[i]);
		setUploading(true);
		fetch(uploadUrl, { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				setUploading(false);
				if (data.ok && data.images && data.images[0]) {
					renderThumbLi(data.images[0].url);
					if (typeof done === 'function') done(true);
				} else {
					alert(data.error || 'Upload failed');
					if (typeof done === 'function') done(false);
				}
			})
			.catch(function () {
				setUploading(false);
				alert('Upload failed');
				if (typeof done === 'function') done(false);
			});
	}
	function uploadBlob(blob, done) {
		var fd = new FormData();
		fd.append('csrf_token', csrfToken);
		fd.append('collection_id', collectionId);
		fd.append('image', blob, 'cropped-' + Date.now() + '.jpg');
		setUploading(true);
		fetch(uploadUrl, { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				setUploading(false);
				if (data.ok && data.images && data.images[0]) {
					renderThumbLi(data.images[0].url);
					if (typeof done === 'function') done(true);
				} else {
					alert(data.error || 'Upload failed');
					if (typeof done === 'function') done(false);
				}
			})
			.catch(function () {
				setUploading(false);
				alert('Upload failed');
				if (typeof done === 'function') done(false);
			});
	}

	function destroyCropper() {
		if (cropper) {
			cropper.destroy();
			cropper = null;
		}
		if (currentObjectUrl) {
			URL.revokeObjectURL(currentObjectUrl);
			currentObjectUrl = '';
		}
		minZoomRatio = null;
		maxZoomRatio = null;
	}
	function hasQualitySafeCrop() {
		if (!cropper) return false;
		var d = cropper.getData(true);
		return d && d.width >= 1080 && d.height >= 1440;
	}

	function beginNextCrop() {
		if (!cropQueue.length) {
			isCroppingFlow = false;
			currentCropFile = null;
			destroyCropper();
			return;
		}
		currentCropFile = cropQueue.shift();
		destroyCropper();
		currentObjectUrl = URL.createObjectURL(currentCropFile);
		cropImageEl.src = currentObjectUrl;
		var m = ensureCropModal();
		if (!m) {
			uploadRawFiles([currentCropFile], function () { beginNextCrop(); });
			return;
		}
		m.show();
	}

	function uploadFiles(files) {
		if (!files || !files.length) return;
		if (typeof window.Cropper === 'undefined') {
			uploadRawFiles(Array.prototype.slice.call(files));
			fileInput.value = '';
			return;
		}
		cropQueue = Array.prototype.slice.call(files);
		isCroppingFlow = true;
		beginNextCrop();
		fileInput.value = '';
	}

	if (cropModalEl) {
		cropModalEl.addEventListener('shown.bs.modal', function () {
			if (!cropImageEl || !window.Cropper || !currentCropFile) return;
			cropper = new Cropper(cropImageEl, {
				aspectRatio: 3 / 4,
				viewMode: 1,
				dragMode: 'move',
				autoCropArea: 1,
				background: false,
				responsive: true,
				checkOrientation: true,
				ready: function () {
					if (!cropper) return;
					var imageData = cropper.getImageData();
					var cropBoxData = cropper.getCropBoxData();
					var baseRatio = imageData.naturalWidth > 0 ? (imageData.width / imageData.naturalWidth) : null;
					minZoomRatio = baseRatio ? (baseRatio * 0.95) : null;
					var limitByWidth = cropBoxData.width / 1080;
					var limitByHeight = cropBoxData.height / 1440;
					maxZoomRatio = Math.min(limitByWidth, limitByHeight);
					if (minZoomRatio !== null && maxZoomRatio !== null && maxZoomRatio < minZoomRatio) {
						maxZoomRatio = minZoomRatio;
					}
				},
				zoom: function (event) {
					if (!cropper) return;
					var nextRatio = event.detail.ratio;
					if (minZoomRatio !== null && nextRatio < minZoomRatio) {
						event.preventDefault();
						return;
					}
					if (maxZoomRatio !== null && nextRatio > maxZoomRatio) {
						event.preventDefault();
					}
				}
			});
		});

		cropModalEl.addEventListener('hidden.bs.modal', function () {
			destroyCropper();
			if (isCroppingFlow && cropQueue.length) {
				beginNextCrop();
			}
		});
	}

	if (cropApplyBtn) {
		cropApplyBtn.addEventListener('click', function () {
			if (!cropper) return;
			if (!hasQualitySafeCrop()) {
				alert('Please zoom out a little. Crop area must be at least 1080 × 1440 to keep quality.');
				return;
			}
			cropApplyBtn.disabled = true;
			var canvas = cropper.getCroppedCanvas({
				width: 1080,
				height: 1440,
				imageSmoothingEnabled: true,
				imageSmoothingQuality: 'high',
				fillColor: '#fff'
			});
			if (!canvas) {
				cropApplyBtn.disabled = false;
				alert('Unable to crop image.');
				return;
			}
			canvas.toBlob(function (blob) {
				if (!blob) {
					cropApplyBtn.disabled = false;
					alert('Unable to process image.');
					return;
				}
				uploadBlob(blob, function () {
					cropApplyBtn.disabled = false;
					var m = ensureCropModal();
					if (m) m.hide();
					else beginNextCrop();
				});
			}, 'image/jpeg', 0.92);
		});
	}

	if (cropSkipBtn) {
		cropSkipBtn.addEventListener('click', function () {
			var m = ensureCropModal();
			if (m) m.hide();
			else beginNextCrop();
		});
	}
	if (cropZoomInBtn) {
		cropZoomInBtn.addEventListener('click', function () {
			if (!cropper) return;
			var oldData = cropper.getData(true);
			cropper.zoom(0.1);
			if (!hasQualitySafeCrop()) {
				cropper.setData(oldData);
			}
		});
	}
	if (cropZoomOutBtn) {
		cropZoomOutBtn.addEventListener('click', function () { if (cropper) cropper.zoom(-0.1); });
	}
	if (cropResetBtn) {
		cropResetBtn.addEventListener('click', function () { if (cropper) cropper.reset(); });
	}

	dropZone.addEventListener('click', function () { fileInput.click(); });
	dropZone.addEventListener('dragover', function (e) { e.preventDefault(); dropZone.classList.add('border-primary'); });
	dropZone.addEventListener('dragleave', function () { dropZone.classList.remove('border-primary'); });
	dropZone.addEventListener('drop', function (e) {
		e.preventDefault();
		dropZone.classList.remove('border-primary');
		uploadFiles(e.dataTransfer.files);
	});
	fileInput.addEventListener('change', function () { uploadFiles(fileInput.files); });
})();
</script>
<script>
(function () {
	var list = document.getElementById('ordered-list');
	var pool = document.getElementById('pool-body');
	var hiddenWrap = document.getElementById('hidden-inputs');
	var emptyHint = document.getElementById('ordered-empty');
	var poolEmpty = document.getElementById('pool-empty');
	var orderedCount = document.getElementById('ordered-count');
	if (!list || !hiddenWrap) return;

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}

	function refreshHidden() {
		hiddenWrap.innerHTML = '';
		var items = list.querySelectorAll('.js-order-item');
		items.forEach(function (li) {
			var id = li.getAttribute('data-id');
			var inp = document.createElement('input');
			inp.type = 'hidden';
			inp.name = 'product_ids[]';
			inp.value = id;
			hiddenWrap.appendChild(inp);
		});
		if (emptyHint) {
			if (items.length) emptyHint.classList.add('d-none');
			else emptyHint.classList.remove('d-none');
		}
		if (poolEmpty && pool) {
			if (pool.querySelector('tr[data-pool-id]')) poolEmpty.classList.add('d-none');
			else poolEmpty.classList.remove('d-none');
		}
		if (orderedCount) orderedCount.textContent = items.length + ' selected';
	}

	function addRow(id, label, sku, status) {
		id = String(id);
		if (list.querySelector('.js-order-item[data-id="' + id + '"]')) return;
		label = label || ('#' + id);
		sku = sku || '';
		status = status || '';
		var li = document.createElement('li');
		li.className = 'list-group-item d-flex align-items-center gap-2 js-order-item';
		li.setAttribute('data-id', id);
		li.setAttribute('data-label', label);
		li.setAttribute('data-sku', sku);
		li.setAttribute('data-status', status);
		li.innerHTML = '<span class="flex-grow-1">' + escapeHtml(label) + ' <small class="text-muted">#' + escapeHtml(id) + '</small></span>' +
			'<button type="button" class="btn btn-sm btn-outline-secondary js-up" aria-label="Move up">↑</button>' +
			'<button type="button" class="btn btn-sm btn-outline-secondary js-down" aria-label="Move down">↓</button>' +
			'<button type="button" class="btn btn-sm btn-outline-danger js-remove" aria-label="Remove">×</button>';
		list.appendChild(li);
		var tr = pool && pool.querySelector('tr[data-pool-id="' + id + '"]');
		if (tr) tr.remove();
		refreshHidden();
	}

	function returnToPool(li) {
		if (!pool || !li) return;
		var id = String(li.getAttribute('data-id') || '');
		if (!id || pool.querySelector('tr[data-pool-id="' + id + '"]')) return;
		var label = li.getAttribute('data-label') || ('#' + id);
		var sku = li.getAttribute('data-sku') || '';
		var status = li.getAttribute('data-status') || '';
		var tr = document.createElement('tr');
		tr.setAttribute('data-pool-id', id);
		tr.setAttribute('data-label', label);
		tr.setAttribute('data-sku', sku);
		tr.setAttribute('data-status', status);
		tr.innerHTML = '<td class="small">' + escapeHtml(label) + '<br><span class="text-muted">' + escapeHtml(sku) + '</span> <span class="badge bg-light text-dark">' + escapeHtml(status) + '</span></td>' +
			'<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary js-add-product" data-id="' + escapeHtml(id) + '" data-label="' + escapeHtml(label) + '" data-sku="' + escapeHtml(sku) + '" data-status="' + escapeHtml(status) + '">Add</button></td>';
		pool.insertBefore(tr, pool.firstChild);
	}

	document.addEventListener('click', function (e) {
		var add = e.target.closest('.js-add-product');
		if (add) {
			addRow(
				add.getAttribute('data-id'),
				add.getAttribute('data-label') || '',
				add.getAttribute('data-sku') || '',
				add.getAttribute('data-status') || ''
			);
			return;
		}
		var up = e.target.closest('.js-up');
		if (up) {
			var li = up.closest('.js-order-item');
			if (li && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
			refreshHidden();
			return;
		}
		var down = e.target.closest('.js-down');
		if (down) {
			var li2 = down.closest('.js-order-item');
			if (li2 && li2.nextElementSibling) li2.parentNode.insertBefore(li2.nextElementSibling, li2);
			refreshHidden();
			return;
		}
		var rm = e.target.closest('.js-remove');
		if (rm) {
			var li3 = rm.closest('.js-order-item');
			if (li3) {
				returnToPool(li3);
				li3.remove();
			}
			refreshHidden();
		}
	});

	refreshHidden();
})();
</script>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
