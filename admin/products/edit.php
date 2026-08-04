<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('products');
require_once ROOT . '/lib/product.php';
require_once ROOT . '/lib/category.php';
require_once ROOT . '/lib/product_group.php';
require_once ROOT . '/lib/product_media.php';
require_once ROOT . '/lib/product_review.php';

$pdo = $GLOBALS['pdo'];
$list_state = admin_products_list_state_from_request();
$products_index_url = admin_products_index_url_with_state($list_state);
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
	redirect($products_index_url);
}
$product = product_find_by_id($pdo, $id);
if (!$product) {
	flash_set('error', 'Product not found.');
	redirect($products_index_url);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_products_edit_url($id, $list_state));
	}
	$name = trim($_POST['name'] ?? '');
	$slug = trim($_POST['slug'] ?? '');
	$description = $_POST['description'] ?? '';
	$specification = $_POST['specification'] ?? '';
	$additional_information = $_POST['additional_information'] ?? '';
	$styling_tips = $_POST['styling_tips'] ?? '';
	$category_id = (int) ($_POST['category_id'] ?? 0);
	$status = $_POST['status'] ?? 'draft';
	if (!in_array($status, ['draft', 'active', 'archived', 'out_of_stock', 'discontinued'], true)) {
		$status = 'draft';
	}
	$sku = trim($_POST['sku'] ?? '');
	if ($name === '') {
		flash_set('error', 'Name is required.');
		redirect(admin_products_edit_url($id, $list_state));
	}
	if ($slug === '') {
		$slug = slug_from_string($name);
	}
	if (product_slug_exists($pdo, $slug, $id)) {
		flash_set('error', 'Slug already in use.');
		redirect(admin_products_edit_url($id, $list_state));
	}
	if ($category_id <= 0) {
		flash_set('error', 'Category is required.');
		redirect(admin_products_edit_url($id, $list_state));
	}
	if ($sku !== '' && product_sku_exists($pdo, $sku, $id)) {
		flash_set('error', 'SKU already in use.');
		redirect(admin_products_edit_url($id, $list_state));
	}
	product_update($pdo, $id, [
		'name' => $name,
		'slug' => $slug,
		'description' => $description,
		'specification' => $specification,
		'additional_information' => $additional_information,
		'styling_tips' => $styling_tips,
		'category_id' => $category_id,
		'product_group_id' => isset($_POST['product_group_id']) && $_POST['product_group_id'] !== '' ? $_POST['product_group_id'] : null,
		'status' => $status,
		'sku' => $sku ?: null,
		'metal_type' => trim($_POST['metal_type'] ?? '') !== '' ? trim($_POST['metal_type']) : 'Silver',
		'purity' => trim($_POST['purity'] ?? '') !== '' ? trim($_POST['purity']) : '925',
		'weight_grams' => isset($_POST['weight_grams']) && $_POST['weight_grams'] !== '' ? (float) $_POST['weight_grams'] : null,
		'size' => trim($_POST['size'] ?? ''),
		'cost_price' => isset($_POST['cost_price']) && $_POST['cost_price'] !== '' ? $_POST['cost_price'] : null,
		'mrp' => isset($_POST['mrp']) && $_POST['mrp'] !== '' ? $_POST['mrp'] : null,
		'discount_percentage' => isset($_POST['discount_percentage']) && $_POST['discount_percentage'] !== '' ? (float) $_POST['discount_percentage'] : 0,
		'gst_percent' => isset($_POST['gst_percent']) && $_POST['gst_percent'] !== '' ? (float) $_POST['gst_percent'] : null,
		'stock_quantity' => max(1, (int) ($_POST['stock_quantity'] ?? 1)),
		'is_gifting' => isset($_POST['is_gifting']) && (string) $_POST['is_gifting'] === '1',
		'meta_title' => trim((string) ($_POST['meta_title'] ?? '')),
		'meta_description' => trim((string) ($_POST['meta_description'] ?? '')),
		'meta_keywords' => trim((string) ($_POST['meta_keywords'] ?? '')),
	]);
	flash_set('success', 'Product updated.');
	redirect(admin_products_edit_url($id, $list_state));
}

$categories = category_list_for_select($pdo);
$product_groups = product_group_list_for_select($pdo);
$page_title = 'Edit Product';
$name = $product['name'];
$slug = $product['slug'];
$description = $product['description'] ?? '';
$specification = $product['specification'] ?? '';
$additional_information = $product['additional_information'] ?? '';
$styling_tips = $product['styling_tips'] ?? '';
$category_id = (string) $product['category_id'];
$product_group_id = isset($product['product_group_id']) && $product['product_group_id'] !== '' && $product['product_group_id'] !== null ? (string) $product['product_group_id'] : '';
$status = $product['status'];
$sku = $product['sku'] ?? '';
$metal_type = ($product['metal_type'] ?? '') !== '' ? $product['metal_type'] : 'Silver';
$purity = ($product['purity'] ?? '') !== '' ? $product['purity'] : '925';
$weight_grams = $product['weight_grams'] ?? '';
$size = $product['size'] ?? '';
$cost_price = $product['cost_price'] ?? '';
$mrp = $product['mrp'] ?? '';
$discount_percentage = $product['discount_percentage'] ?? '0';
$gst_percent = $product['gst_percent'] ?? '3';
$stock_quantity = (int) ($product['stock_quantity'] ?? 1);
$is_gifting = !empty($product['is_gifting']);
$meta_title = (string) ($product['meta_title'] ?? '');
$meta_description = (string) ($product['meta_description'] ?? '');
$meta_keywords = (string) ($product['meta_keywords'] ?? '');
$success = flash_get('success');
$error = flash_get('error');
$product_media = product_media_list_by_product($pdo, $id);
$product_reviews = product_review_list_for_product_admin($pdo, $id);
$uploads_base = uploads_base_url();
$include_cropper = true;
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>

<body class="admin-body">
	<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
	<main class="container-fluid p-4 admin-content">
		<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
			<h1 class="page-title mb-0">Edit Product</h1>
			<div class="d-flex gap-2">
				<a href="<?= h($products_index_url) ?>" class="btn btn-secondary btn-sm">Back to Products</a>
			</div>
		</div>
		<?php if ($success): ?>
			<div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
		<?php if ($error): ?>
			<div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

		<div class="row g-4 align-items-start">
			<div class="col-xl-8">
				<form method="post" action="<?= h(admin_products_edit_url($id, $list_state)) ?>">
					<?= csrf_field() ?>
					<input type="hidden" name="list_page" value="<?= h((string) $list_state['page']) ?>">
					<input type="hidden" name="list_search" value="<?= h($list_state['search']) ?>">
					<input type="hidden" name="list_category_id" value="<?= h($list_state['category_id']) ?>">
					<input type="hidden" name="list_status" value="<?= h($list_state['status']) ?>">

					<div class="card mb-3 border-success-subtle">
						<div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
							<div>
								<div class="fw-semibold">Save Product Changes</div>
								<div class="small text-muted">Use this after editing any section below.</div>
							</div>
							<div class="d-flex gap-2">
								<button type="submit" class="btn btn-success">Update Product</button>
								<a href="<?= h($products_index_url) ?>" class="btn btn-danger">Cancel</a>
							</div>
						</div>
					</div>

					<div class="card mb-3">
						<div class="card-header bg-light"><strong>Basic Details</strong></div>
						<div class="card-body">
							<div class="row g-3">
								<div class="col-md-6">
									<label for="name" class="form-label">Name *</label>
									<input type="text" class="form-control" id="name" name="name" required
										placeholder="e.g. Sterling Silver Ring" value="<?= h($name) ?>">
								</div>
								<div class="col-md-6">
									<label for="slug" class="form-label">Slug</label>
									<input type="text" class="form-control" id="slug" name="slug"
										placeholder="e.g. sterling-silver-ring" value="<?= h($slug) ?>">
								</div>
								<div class="col-md-6">
									<label for="category_id" class="form-label">Category *</label>
									<select class="form-select" id="category_id" name="category_id" required>
										<option value="">- Select -</option>
										<?php foreach ($categories as $c): ?>
											<option value="<?= h($c['id']) ?>" <?= $category_id === (string) $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-6">
									<label for="status" class="form-label">Status</label>
									<select class="form-select" name="status" id="status">
										<option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
										<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
										<option value="out_of_stock" <?= $status === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option>
										<option value="discontinued" <?= $status === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
										<option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
									</select>
								</div>
								<div class="col-md-6">
									<label for="product_group_id" class="form-label">Product Group (variants)</label>
									<select class="form-select" id="product_group_id" name="product_group_id">
										<option value="">- None -</option>
										<?php foreach ($product_groups as $g): ?>
											<option value="<?= h($g['id']) ?>" <?= $product_group_id === (string) $g['id'] ? 'selected' : '' ?>><?= h($g['name']) ?></option>
										<?php endforeach; ?>
									</select>
									<small class="text-muted">Grouped products show as variants on product page.</small>
								</div>
								<div class="col-md-6">
									<label for="sku" class="form-label">SKU</label>
									<input type="text" class="form-control" id="sku" name="sku" placeholder="e.g. RING-001"
										value="<?= h($sku) ?>">
								</div>
							</div>
						</div>
					</div>

					<div class="card mb-3">
						<div class="card-header bg-light"><strong>Product Content</strong></div>
						<div class="card-body">
							<div class="row g-3">
								<div class="col-12">
									<label for="description" class="form-label">Product Description</label>
									<textarea class="form-control" id="description" name="description" rows="4"
										placeholder="Product description..."><?= h($description) ?></textarea>
								</div>
								<div class="col-12">
									<label for="specification" class="form-label">Specification</label>
									<textarea class="form-control" id="specification" name="specification" rows="3"
										placeholder="e.g. Material, dimensions..."><?= h($specification) ?></textarea>
								</div>
								<div class="col-12">
									<label for="additional_information" class="form-label">Additional Information</label>
									<textarea class="form-control" id="additional_information" name="additional_information" rows="3"
										placeholder="Care, packaging, etc."><?= h($additional_information) ?></textarea>
								</div>
								<div class="col-12">
									<label for="styling_tips" class="form-label">Styling Tips</label>
									<textarea class="form-control" id="styling_tips" name="styling_tips" rows="3"
										placeholder="How to wear, pair with..."><?= h($styling_tips) ?></textarea>
								</div>
							</div>
						</div>
					</div>

					<div class="card mb-3">
						<div class="card-header bg-light"><strong>Gifting &amp; SEO</strong></div>
						<div class="card-body">
							<div class="row g-3">
								<div class="col-12">
									<div class="form-check">
										<input class="form-check-input" type="checkbox" value="1" id="is_gifting" name="is_gifting" <?= $is_gifting ? 'checked' : '' ?>>
										<label class="form-check-label" for="is_gifting">Show on gifting page</label>
									</div>
								</div>
								<div class="col-md-6">
									<label for="meta_title" class="form-label">Meta title</label>
									<input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="255" placeholder="Default: product name | Pearlica 925 Silver Jewellery" value="<?= h($meta_title) ?>">
								</div>
								<div class="col-md-6">
									<label for="meta_keywords" class="form-label">Meta keywords</label>
									<input type="text" class="form-control" id="meta_keywords" name="meta_keywords" maxlength="512" placeholder="Comma-separated; optional" value="<?= h($meta_keywords) ?>">
								</div>
								<div class="col-12">
									<label for="meta_description" class="form-label">Meta description</label>
									<textarea class="form-control" id="meta_description" name="meta_description" rows="2" maxlength="512" placeholder="Blank uses description snippet or a short default"><?= h($meta_description) ?></textarea>
									<small class="text-muted">Leave fields empty to use automatic storefront defaults based on this product.</small>
								</div>
							</div>
						</div>
					</div>

					<div class="card mb-3">
						<div class="card-header bg-light"><strong>Pricing, Inventory & Attributes</strong></div>
						<div class="card-body">
							<div class="row g-3">
								<div class="col-md-4">
									<label for="metal_type" class="form-label">Metal</label>
									<input type="text" class="form-control" id="metal_type" name="metal_type" placeholder="e.g. Silver"
										value="<?= h($metal_type) ?>">
								</div>
								<div class="col-md-4">
									<label for="purity" class="form-label">Purity</label>
									<input type="text" class="form-control" id="purity" name="purity" placeholder="e.g. 925"
										value="<?= h($purity) ?>">
								</div>
								<div class="col-md-4">
									<label for="size" class="form-label">Size</label>
									<input type="text" class="form-control" id="size" name="size" placeholder="e.g. 18"
										value="<?= h($size) ?>">
								</div>
								<div class="col-md-3">
									<label for="weight_grams" class="form-label">Weight (g)</label>
									<input type="number" step="0.001" min="0" class="form-control" id="weight_grams"
										name="weight_grams" placeholder="e.g. 2.5" value="<?= h($weight_grams) ?>">
								</div>
								<div class="col-md-3">
									<label for="cost_price" class="form-label">Cost</label>
									<input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price"
										placeholder="0.00" value="<?= h($cost_price) ?>">
								</div>
								<div class="col-md-3">
									<label for="mrp" class="form-label">MRP</label>
									<input type="number" step="0.01" class="form-control" id="mrp" name="mrp"
										placeholder="0.00" value="<?= h($mrp) ?>">
								</div>
								<div class="col-md-3">
									<label for="discount_percentage" class="form-label">Discount %</label>
									<input type="number" step="0.01" class="form-control" id="discount_percentage"
										name="discount_percentage" placeholder="e.g. 10" value="<?= h($discount_percentage) ?>">
								</div>
								<div class="col-md-3">
									<label for="gst_percent" class="form-label">GST %</label>
									<input type="number" step="0.01" class="form-control" id="gst_percent" name="gst_percent"
										placeholder="e.g. 3" value="<?= h($gst_percent) ?>">
								</div>
								<div class="col-md-3">
									<label for="stock_quantity" class="form-label">Stock</label>
									<input type="number" min="1" class="form-control" id="stock_quantity" name="stock_quantity"
										placeholder="1" value="<?= h($stock_quantity) ?>">
								</div>
							</div>
						</div>
					</div>

					<div class="card mb-4 border-success-subtle">
						<div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
							<div class="small text-muted">Finished editing? Save now.</div>
							<div class="d-flex gap-2">
								<button type="submit" class="btn btn-success">Update Product</button>
								<a href="<?= h($products_index_url) ?>" class="btn btn-danger">Cancel</a>
							</div>
						</div>
					</div>
				</form>
			</div>

			<div class="col-xl-4">
				<div class="card mb-4">
					<div class="card-header bg-light">SKU Barcode &amp; QR Code</div>
			<div class="card-body">
				<?php if ($sku === ''): ?>
					<p class="text-muted mb-0">Set an SKU above and save to generate CODE 128 barcode and QR code for this
						product.</p>
				<?php else:
					$barcode_png = admin_url('products/sku-barcode.php?id=' . $id . '&format=png');
					$barcode_svg = admin_url('products/sku-barcode.php?id=' . $id . '&format=svg');
					$qrcode_png = admin_url('products/sku-qrcode.php?id=' . $id . '&format=png');
					$qrcode_svg = admin_url('products/sku-qrcode.php?id=' . $id . '&format=svg');
					$safe_sku_file = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sku);
					?>
					<div class="row g-3">
						<div class="col-12">
							<p class="fw-semibold small text-muted mb-2">CODE 128 Barcode (SKU: <?= h($sku) ?>)</p>
							<div class="bg-light rounded p-3 mb-2 w-100">
								<img src="<?= h($barcode_png) ?>" alt="Barcode <?= h($sku) ?>"
									style="width: 100%; max-width: 100%; height: auto; display: block;">
							</div>
							<div class="d-flex flex-wrap gap-2">
								<a href="<?= h($barcode_png) ?>" class="btn btn-sm btn-primary"
									download="barcode-<?= h($safe_sku_file) ?>.png">Download PNG</a>
								<a href="<?= h($barcode_svg) ?>" class="btn btn-sm btn-primary"
									download="barcode-<?= h($safe_sku_file) ?>.svg">Download SVG</a>
							</div>
						</div>
						<div class="col-12">
							<p class="fw-semibold small text-muted mb-2">QR Code (SKU: <?= h($sku) ?>)</p>
							<div class="bg-light rounded p-3 mb-2 w-100">
								<img src="<?= h($qrcode_png) ?>" alt="QR Code <?= h($sku) ?>"
									style="width: 200px; max-width: 100%; height: auto; display: block;">
							</div>
							<div class="d-flex flex-wrap gap-2">
								<a href="<?= h($qrcode_png) ?>" class="btn btn-sm btn-primary"
									download="qrcode-<?= h($safe_sku_file) ?>.png">Download PNG</a>
								<a href="<?= h($qrcode_svg) ?>" class="btn btn-sm btn-primary"
									download="qrcode-<?= h($safe_sku_file) ?>.svg">Download SVG</a>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

				<div class="card mb-4">
					<div class="card-header bg-light">Product Images</div>
			<div class="card-body">
				<div id="image-drop-zone" class="border border-2 border-dashed rounded p-4 text-center mb-3"
					style="min-height: 120px; cursor: pointer;">
					<input type="file" id="image-file-input" accept="image/jpeg,image/png,image/gif,image/webp" multiple
						class="d-none">
					<span class="drop-zone-text text-muted">Drop images here or click to upload</span>
					<div class="drop-zone-loading d-none flex-column align-items-center justify-content-center"
						id="image-upload-loading" style="min-height: 100px;">
						<div class="spinner-border text-dark" role="status" style="width: 2.5rem; height: 2.5rem;"><span
								class="visually-hidden">Loading</span></div>
						<p class="text-danger small mt-2 mb-0 fw-medium">Please do not close this window or refresh.</p>
					</div>
				</div>
				<ul id="product-images-list" class="list-unstyled d-flex flex-wrap gap-2 mb-0">
					<?php foreach ($product_media as $m): ?>
						<li class="product-image-item border rounded overflow-hidden position-relative"
							data-id="<?= (int) $m['id'] ?>" style="width: 130px; height: 130px; flex-shrink: 0;">
							<img src="<?= h($uploads_base . '/' . $m['url']) ?>" alt=""
								class="w-100 h-100 object-fit-cover">
							<span class="badge bg-primary position-absolute top-0 start-0 m-1 primary-badge"
								style="font-size: 0.65rem; <?= empty($m['is_primary']) ? 'display:none' : '' ?>">Primary</span>
							<button type="button"
								class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 image-delete-btn"
								title="Remove">×</button>
							<span
								class="drag-handle position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-50 text-white text-center py-1"
								style="font-size: 0.7rem; cursor: move;">⋮⋮</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
			</div>
		</div>

		<div class="row g-4 mt-0">
			<div class="col-12">
				<div class="card border-primary-subtle">
					<div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
						<strong>Product reviews</strong>
						<span class="small text-muted">Storefront shows approved reviews. Admin-added reviews use any display name; buyer reviews stay linked to accounts.</span>
					</div>
					<div class="card-body">
						<?php if (!empty($product_reviews)): ?>
							<?php foreach ($product_reviews as $rev): ?>
								<div class="border rounded p-3 mb-3 bg-body-secondary bg-opacity-25">
									<div class="d-flex flex-wrap justify-content-between gap-2 mb-2 small text-muted">
										<span>Review #<?= (int) $rev['id'] ?> · <?= !empty($rev['store_user_id']) ? 'Verified buyer' : 'Admin' ?></span>
										<span><?= h((string) ($rev['created_at'] ?? '')) ?></span>
									</div>
									<form method="post" action="<?= h(admin_url('products/review-save.php')) ?>" class="row g-2 align-items-end">
										<?= csrf_field() ?>
										<input type="hidden" name="product_id" value="<?= (int) $id ?>">
										<input type="hidden" name="review_id" value="<?= (int) $rev['id'] ?>">
										<input type="hidden" name="list_page" value="<?= h((string) $list_state['page']) ?>">
										<input type="hidden" name="list_search" value="<?= h($list_state['search']) ?>">
										<input type="hidden" name="list_category_id" value="<?= h($list_state['category_id']) ?>">
										<input type="hidden" name="list_status" value="<?= h($list_state['status']) ?>">
										<div class="col-md-2">
											<label class="form-label small mb-0">Display name</label>
											<input type="text" name="author_name" class="form-control form-control-sm" required maxlength="120"
												value="<?= h((string) ($rev['author_name'] ?? '')) ?>">
										</div>
										<div class="col-md-1">
											<label class="form-label small mb-0">Rating</label>
											<select name="rating" class="form-select form-select-sm">
												<?php for ($s = 5; $s >= 1; $s--): ?>
													<option value="<?= $s ?>" <?= (int) ($rev['rating'] ?? 0) === $s ? 'selected' : '' ?>><?= $s ?>★</option>
												<?php endfor; ?>
											</select>
										</div>
										<div class="col-md-5">
											<label class="form-label small mb-0">Message</label>
											<textarea name="body" class="form-control form-control-sm" rows="2" maxlength="5000"><?= h((string) ($rev['body'] ?? '')) ?></textarea>
										</div>
										<div class="col-md-2">
											<div class="form-check">
												<input class="form-check-input" type="checkbox" name="is_approved" value="1" id="appr_<?= (int) $rev['id'] ?>"
													<?= !empty($rev['is_approved']) ? 'checked' : '' ?>>
												<label class="form-check-label small" for="appr_<?= (int) $rev['id'] ?>">Visible on storefront</label>
											</div>
										</div>
										<div class="col-md-2 d-flex flex-wrap gap-2 justify-content-md-end">
											<button type="submit" class="btn btn-sm btn-primary">Save</button>
										</div>
									</form>
									<form method="post" action="<?= h(admin_url('products/review-delete.php')) ?>" class="mt-2"
										onsubmit="return confirm('Delete this review permanently?');">
										<?= csrf_field() ?>
										<input type="hidden" name="product_id" value="<?= (int) $id ?>">
										<input type="hidden" name="review_id" value="<?= (int) $rev['id'] ?>">
										<input type="hidden" name="list_page" value="<?= h((string) $list_state['page']) ?>">
										<input type="hidden" name="list_search" value="<?= h($list_state['search']) ?>">
										<input type="hidden" name="list_category_id" value="<?= h($list_state['category_id']) ?>">
										<input type="hidden" name="list_status" value="<?= h($list_state['status']) ?>">
										<button type="submit" class="btn btn-sm btn-outline-danger">Delete review</button>
									</form>
								</div>
							<?php endforeach; ?>
						<?php else: ?>
							<p class="text-muted small mb-4">No reviews for this product yet. Add one below.</p>
						<?php endif; ?>

						<h6 class="text-muted small text-uppercase mb-3">Add review</h6>
						<form method="post" action="<?= h(admin_url('products/review-save.php')) ?>" class="row g-3 align-items-end">
							<?= csrf_field() ?>
							<input type="hidden" name="product_id" value="<?= (int) $id ?>">
							<input type="hidden" name="list_page" value="<?= h((string) $list_state['page']) ?>">
							<input type="hidden" name="list_search" value="<?= h($list_state['search']) ?>">
							<input type="hidden" name="list_category_id" value="<?= h($list_state['category_id']) ?>">
							<input type="hidden" name="list_status" value="<?= h($list_state['status']) ?>">
							<div class="col-md-3">
								<label for="new_rev_author" class="form-label">Display name</label>
								<input type="text" class="form-control" id="new_rev_author" name="author_name" maxlength="120" required placeholder="e.g. Priya M.">
							</div>
							<div class="col-md-2">
								<label for="new_rev_rating" class="form-label">Rating</label>
								<select class="form-select" id="new_rev_rating" name="rating" required>
									<?php for ($s = 5; $s >= 1; $s--): ?>
										<option value="<?= $s ?>" <?= $s === 5 ? 'selected' : '' ?>><?= $s ?> stars</option>
									<?php endfor; ?>
								</select>
							</div>
							<div class="col-md-5">
								<label for="new_rev_body" class="form-label">Message</label>
								<textarea class="form-control" id="new_rev_body" name="body" rows="2" maxlength="5000" placeholder="Review text"></textarea>
							</div>
							<div class="col-md-2">
								<div class="form-check mb-2">
									<input class="form-check-input" type="checkbox" name="is_approved" value="1" id="new_rev_appr" checked>
									<label class="form-check-label" for="new_rev_appr">Approved (visible on site)</label>
								</div>
								<button type="submit" class="btn btn-success w-100">Add review</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>

		<div class="modal fade" id="image-crop-modal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" style="max-width: min(92vw, 760px);">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Crop Image (4:5)</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div class="small text-muted mb-2">Target export size: 1080 x 1350 px</div>
						<div class="w-100 bg-light d-flex align-items-center justify-content-center mx-auto" style="width: min(100%, 420px); aspect-ratio: 4 / 5; max-height: 70vh; overflow: hidden;">
							<img id="cropper-image-preview" alt="Crop preview" style="max-width: 100%; max-height: 100%; display: block;">
						</div>
						<div class="small text-muted mt-2 mb-0">Tip: zoom is limited to preserve export quality.</div>
					</div>
					<div class="modal-footer d-flex justify-content-between">
						<div class="d-flex gap-2">
							<button type="button" id="cropper-zoom-out" class="btn btn-secondary btn-sm">Zoom Out</button>
							<button type="button" id="cropper-zoom-in" class="btn btn-secondary btn-sm">Zoom In</button>
							<button type="button" id="cropper-reset" class="btn btn-secondary btn-sm">Reset</button>
						</div>
						<div class="d-flex gap-2">
							<button type="button" id="cropper-skip" class="btn btn-danger btn-sm">Skip</button>
							<button type="button" id="cropper-apply-upload" class="btn btn-success btn-sm">Crop & Upload</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
		<script>
			(function () {
				var n = document.getElementById('name'), s = document.getElementById('slug');
				if (n && s) {
					function slug(s) { return s.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
					n.addEventListener('input', function () { if (!s.dataset.manual) s.value = slug(n.value); });
					s.addEventListener('input', function () { s.dataset.manual = '1'; });
				}
			})();
			(function () {
				var productId = <?= (int) $id ?>;
				var uploadUrl = <?= json_encode(admin_url('products/image-upload.php')) ?>;
				var deleteUrl = <?= json_encode(admin_url('products/image-delete.php')) ?>;
				var reorderUrl = <?= json_encode(admin_url('products/image-reorder.php')) ?>;
				var csrfToken = <?= json_encode(csrf_token()) ?>;
				var uploadsBase = <?= json_encode($uploads_base) ?>;

				var dropZone = document.getElementById('image-drop-zone');
				var fileInput = document.getElementById('image-file-input');
				var list = document.getElementById('product-images-list');
				var cropModalEl = document.getElementById('image-crop-modal');
				var cropImageEl = document.getElementById('cropper-image-preview');
				var cropApplyBtn = document.getElementById('cropper-apply-upload');
				var cropSkipBtn = document.getElementById('cropper-skip');
				var cropZoomInBtn = document.getElementById('cropper-zoom-in');
				var cropZoomOutBtn = document.getElementById('cropper-zoom-out');
				var cropResetBtn = document.getElementById('cropper-reset');
				var cropModal = null;
				var cropper = null;
				var cropQueue = [];
				var currentCropFile = null;
				var currentObjectUrl = '';
				var isCroppingFlow = false;
				var minZoomRatio = null;
				var maxZoomRatio = null;

				function addImageToDom(img) {
					var li = document.createElement('li');
					li.className = 'product-image-item border rounded overflow-hidden position-relative';
					li.style.cssText = 'width: 130px; height: 130px; flex-shrink: 0;';
					li.dataset.id = img.id;
					var primaryBadge = img.is_primary ? '' : ' style="display:none"';
					li.innerHTML = '<img src="' + uploadsBase + '/' + img.url + '" alt="" class="w-100 h-100 object-fit-cover">' +
						'<span class="badge bg-primary position-absolute top-0 start-0 m-1 primary-badge" style="font-size: 0.65rem"' + primaryBadge + '>Primary</span>' +
						'<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 image-delete-btn" title="Remove">×</button>' +
						'<span class="drag-handle position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-50 text-white text-center py-1" style="font-size: 0.7rem; cursor: move;">⋮⋮</span>';
					list.appendChild(li);
					li.querySelector('.image-delete-btn').addEventListener('click', function () { deleteImage(img.id, li); });
				}

				function deleteImage(id, el) {
					if (!confirm('Remove this image?')) return;
					fetch(deleteUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ csrf_token: csrfToken, id: id, product_id: productId })
					}).then(function (r) { return r.json(); }).then(function (data) {
						if (data.ok && el && el.parentNode) el.remove();
						else if (data.error) alert(data.error);
					}).catch(function () { alert('Error'); });
				}

				function sendOrder() {
					var ids = [].map.call(list.querySelectorAll('.product-image-item'), function (li) { return parseInt(li.dataset.id, 10); });
					fetch(reorderUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ csrf_token: csrfToken, product_id: productId, order: ids })
					}).then(function (r) { return r.json(); }).then(function (data) {
						if (data.ok) {
							list.querySelectorAll('.primary-badge').forEach(function (b) { b.style.display = 'none'; });
							var first = list.querySelector('.product-image-item');
							if (first) first.querySelector('.primary-badge').style.display = '';
						}
					});
				}

				var dropZoneText = dropZone.querySelector('.drop-zone-text');
				var uploadLoading = document.getElementById('image-upload-loading');
				function setUploading(show) {
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
					fd.append('product_id', productId);
					for (var i = 0; i < files.length; i++) fd.append('images[]', files[i]);
					setUploading(true);
					fetch(uploadUrl, { method: 'POST', body: fd })
						.then(function (r) { return r.json(); })
						.then(function (data) {
							setUploading(false);
							if (data.ok && data.images) {
								data.images.forEach(addImageToDom);
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
					fd.append('product_id', productId);
					fd.append('image', blob, 'cropped-' + Date.now() + '.jpg');
					setUploading(true);
					fetch(uploadUrl, { method: 'POST', body: fd })
						.then(function (r) { return r.json(); })
						.then(function (data) {
							setUploading(false);
							if (data.ok && data.images) {
								data.images.forEach(addImageToDom);
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
					return d && d.width >= 1080 && d.height >= 1350;
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
						uploadRawFiles([currentCropFile], function () {
							beginNextCrop();
						});
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
							aspectRatio: 4 / 5,
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
								var limitByHeight = cropBoxData.height / 1350;
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
							alert('Please zoom out a little. Crop area must be at least 1080 x 1350 to keep quality.');
							return;
						}
						cropApplyBtn.disabled = true;
						var canvas = cropper.getCroppedCanvas({
							width: 1080,
							height: 1350,
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

				list.querySelectorAll('.image-delete-btn').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var li = btn.closest('.product-image-item');
						if (li) deleteImage(parseInt(li.dataset.id, 10), li);
					});
				});

				if (typeof Sortable !== 'undefined') {
					new Sortable(list, {
						animation: 150,
						handle: '.drag-handle',
						onEnd: sendOrder
					});
				}
			})();
		</script>
	</main>
	<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
	<?php require dirname(__DIR__) . '/footer.php'; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>