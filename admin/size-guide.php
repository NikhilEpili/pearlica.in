<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_permission('banners');
require_once ROOT . '/lib/size_guide.php';

$success = flash_get('success');
$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('size-guide.php'));
	}

	$messages = [];
	$errors = [];
	foreach (array_keys(size_guide_slots()) as $slot) {
		if (!empty($_POST['clear_' . $slot])) {
			if (!size_guide_clear_upload($slot)) {
				$errors[] = 'Could not remove the uploaded image.';
			} else {
				$messages[] = 'Custom image removed. The built-in chart is showing again.';
			}
			continue;
		}

		$file = $_FILES['image_' . $slot] ?? null;
		if (!is_array($file)) {
			continue;
		}
		$err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($err === UPLOAD_ERR_NO_FILE) {
			continue;
		}
		$stored = size_guide_store_upload($slot, $file);
		if (empty($stored['ok'])) {
			$errors[] = (string) ($stored['error'] ?? 'Upload failed.');
		} else {
			$messages[] = 'Size guide image updated.';
		}
	}

	if ($errors !== []) {
		flash_set('error', implode(' ', array_unique($errors)));
	} elseif ($messages !== []) {
		flash_set('success', implode(' ', array_unique($messages)));
	} else {
		flash_set('error', 'Choose an image to upload.');
	}
	redirect(admin_url('size-guide.php'));
}

$cfg = size_guide_settings_load();
$page_title = 'Size guides';

/**
 * @return array{src:string,label:string}
 */
function size_guide_admin_preview(string $kind, bool $custom, string $rel): array
{
	if ($custom && $rel !== '') {
		return [
			'src' => media_storage_public_url(uploads_banners_base_url(), $rel, 'banners'),
			'label' => 'Uploaded image',
		];
	}
	$file = size_guide_fallback_file($kind);
	$prefix = rtrim(web_base(), '/');

	return [
		'src' => ($prefix === '' ? '' : $prefix) . '/assets/images/size-guide/' . rawurlencode($file),
		'label' => 'Built-in image',
	];
}

$rings_rel = size_guide_custom_rel('rings_kadas');
$chain_rel = size_guide_custom_rel('necklaces_bracelets');
$rings_preview = size_guide_admin_preview('rings_kadas', $rings_rel !== '', $rings_rel !== '' ? $rings_rel : (string) $cfg['rings_kadas']);
$necklace_preview = size_guide_admin_preview('necklaces', $chain_rel !== '', $chain_rel);
$bracelet_preview = size_guide_admin_preview('bracelets', $chain_rel !== '', $chain_rel);
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Size guides</h1>
	<p class="text-muted small mb-4">
		These charts open from the Size Guide link on product pages.
		Rings and kadas share one image. Necklaces and bracelets share one image.
		Until you upload a replacement, the store uses the built-in charts
		(necklaces and bracelets each keep their own chart until that shared image is uploaded).
	</p>

	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

	<form method="post" action="<?= h(admin_url('size-guide.php')) ?>" enctype="multipart/form-data">
		<?= csrf_field() ?>
		<div class="row g-4">
			<div class="col-lg-6">
				<div class="card h-100">
					<div class="card-header bg-light"><strong>Rings and kadas</strong></div>
					<div class="card-body">
						<p class="small text-muted"><?= h($rings_preview['label']) ?></p>
						<img src="<?= h($rings_preview['src']) ?>" alt="Ring and kada size guide" class="img-fluid border bg-white mb-3" style="max-height: 420px; width: auto;">
						<div class="mb-3">
							<label class="form-label" for="image-rings-kadas">Replace image</label>
							<input class="form-control" type="file" id="image-rings-kadas" name="image_rings_kadas" accept="image/jpeg,image/png,image/gif,image/webp">
						</div>
						<?php if ($rings_rel !== ''): ?>
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="clear-rings-kadas" name="clear_rings_kadas" value="1">
								<label class="form-check-label" for="clear-rings-kadas">Remove uploaded image and use the built-in chart</label>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="card h-100">
					<div class="card-header bg-light"><strong>Necklaces and bracelets</strong></div>
					<div class="card-body">
						<?php if ($chain_rel !== ''): ?>
							<p class="small text-muted"><?= h($necklace_preview['label']) ?> — shown on necklaces and bracelets</p>
							<img src="<?= h($necklace_preview['src']) ?>" alt="Necklace and bracelet size guide" class="img-fluid border bg-white mb-3" style="max-height: 420px; width: auto;">
						<?php else: ?>
							<p class="small text-muted">Built-in images, until you upload one chart for both.</p>
							<div class="row g-3 mb-3">
								<div class="col-sm-6">
									<p class="small mb-1">Necklaces</p>
									<img src="<?= h($necklace_preview['src']) ?>" alt="Necklace size guide" class="img-fluid border bg-white">
								</div>
								<div class="col-sm-6">
									<p class="small mb-1">Bracelets</p>
									<img src="<?= h($bracelet_preview['src']) ?>" alt="Bracelet size guide" class="img-fluid border bg-white">
								</div>
							</div>
						<?php endif; ?>
						<div class="mb-3">
							<label class="form-label" for="image-necklaces-bracelets">Replace image</label>
							<input class="form-control" type="file" id="image-necklaces-bracelets" name="image_necklaces_bracelets" accept="image/jpeg,image/png,image/gif,image/webp">
							<small class="text-muted">This one image is used for both necklaces and bracelets.</small>
						</div>
						<?php if ($chain_rel !== ''): ?>
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="clear-necklaces-bracelets" name="clear_necklaces_bracelets" value="1">
								<label class="form-check-label" for="clear-necklaces-bracelets">Remove uploaded image and use the built-in charts</label>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<div class="mt-4">
			<button type="submit" class="btn btn-primary">Save</button>
		</div>
	</form>
</main>
<?php require __DIR__ . '/components/wrapper_end.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
