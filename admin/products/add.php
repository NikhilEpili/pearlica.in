<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('products');
require_once ROOT . '/lib/product.php';
require_once ROOT . '/lib/category.php';
require_once ROOT . '/lib/product_group.php';

$pdo = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('products/add.php'));
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
		redirect(admin_url('products/add.php'));
	}
	if ($slug === '') {
		$slug = slug_from_string($name);
	}
	if (product_slug_exists($pdo, $slug)) {
		flash_set('error', 'Slug already in use.');
		redirect(admin_url('products/add.php'));
	}
	if ($category_id <= 0) {
		flash_set('error', 'Category is required.');
		redirect(admin_url('products/add.php'));
	}
	if ($sku !== '' && product_sku_exists($pdo, $sku)) {
		flash_set('error', 'SKU already in use.');
		redirect(admin_url('products/add.php'));
	}
	$product_id = product_insert($pdo, [
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
	flash_set('success', 'Product added.');
	redirect(admin_url('products/edit.php?id=' . $product_id));
}

$categories = category_list_for_select($pdo);
$product_groups = product_group_list_for_select($pdo);
$page_title = 'Add Product';
$name = $_POST['name'] ?? '';
$slug = $_POST['slug'] ?? '';
$description = $_POST['description'] ?? '';
$specification = $_POST['specification'] ?? '';
$additional_information = $_POST['additional_information'] ?? '';
$styling_tips = $_POST['styling_tips'] ?? '';
$category_id = (string) ($_POST['category_id'] ?? '');
$product_group_id = (string) ($_POST['product_group_id'] ?? '');
$status = $_POST['status'] ?? 'draft';
$sku = $_POST['sku'] ?? '';
$metal_type = $_POST['metal_type'] ?? 'Silver';
$purity = $_POST['purity'] ?? '925';
$weight_grams = $_POST['weight_grams'] ?? '';
$size = $_POST['size'] ?? '';
$cost_price = $_POST['cost_price'] ?? '';
$mrp = $_POST['mrp'] ?? '';
$discount_percentage = $_POST['discount_percentage'] ?? '0';
$gst_percent = $_POST['gst_percent'] ?? '3';
$stock_quantity = $_POST['stock_quantity'] ?? '1';
$is_gifting = !empty($_POST['is_gifting']);
$meta_title = $_POST['meta_title'] ?? '';
$meta_description = $_POST['meta_description'] ?? '';
$meta_keywords = $_POST['meta_keywords'] ?? '';
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>

<body class="admin-body">
	<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
	<main class="container-fluid p-4 admin-content">
		<h1 class="page-title">Add Product</h1>
		<?php if (!empty($error)): ?>
			<div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
		<?php if (empty($categories)): ?>
			<div class="alert alert-warning">Add a <a href="<?= h(admin_url('categories/add.php')) ?>">category</a> first.
			</div>
		<?php endif; ?>
		<form method="post" action="<?= h(admin_url('products/add.php')) ?>">
			<?= csrf_field() ?>
			<div class="card mb-4">
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
							<select class="form-select" name="status">
								<option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
								<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
								<option value="out_of_stock" <?= $status === 'out_of_stock' ? 'selected' : '' ?>>Out of
									stock</option>
								<option value="discontinued" <?= $status === 'discontinued' ? 'selected' : '' ?>>
									Discontinued</option>
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
							<small class="text-muted">Other products in this group show as variants on the product page.</small>
						</div>
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
							<textarea class="form-control" id="additional_information" name="additional_information"
								rows="3"
								placeholder="Care, packaging, etc."><?= h($additional_information) ?></textarea>
						</div>
						<div class="col-12">
							<label for="styling_tips" class="form-label">Styling Tips</label>
							<textarea class="form-control" id="styling_tips" name="styling_tips" rows="3"
								placeholder="How to wear, pair with..."><?= h($styling_tips) ?></textarea>
						</div>
						<div class="col-md-4"><label for="sku" class="form-label">SKU</label><input type="text"
								class="form-control" id="sku" name="sku" placeholder="e.g. RING-001"
								value="<?= h($sku) ?>"></div>
						<div class="col-md-4"><label for="metal_type" class="form-label">Metal</label><input type="text"
								class="form-control" id="metal_type" name="metal_type" placeholder="e.g. Silver"
								value="<?= h($metal_type) ?>"></div>
						<div class="col-md-4"><label for="purity" class="form-label">Purity</label><input type="text"
								class="form-control" id="purity" name="purity" placeholder="e.g. 925"
								value="<?= h($purity) ?>"></div>
						<div class="col-md-3"><label for="weight_grams" class="form-label">Weight (g)</label><input
								type="number" step="0.001" min="0" class="form-control" id="weight_grams"
								name="weight_grams" placeholder="e.g. 2.5" value="<?= h($weight_grams) ?>"></div>
						<div class="col-md-3"><label for="size" class="form-label">Size</label><input type="text"
								class="form-control" id="size" name="size" placeholder="e.g. 18"
								value="<?= h($size) ?>"></div>
						<div class="col-md-3"><label for="cost_price" class="form-label">Cost</label><input
								type="number" step="0.01" class="form-control" id="cost_price" name="cost_price"
								placeholder="0.00" value="<?= h($cost_price) ?>"></div>
						<div class="col-md-3"><label for="mrp" class="form-label">MRP</label><input type="number"
								step="0.01" class="form-control" id="mrp" name="mrp" placeholder="0.00"
								value="<?= h($mrp) ?>"></div>
						<div class="col-md-3"><label for="discount_percentage" class="form-label">Discount
								%</label><input type="number" step="0.01" class="form-control" id="discount_percentage"
								name="discount_percentage" placeholder="e.g. 10" value="<?= h($discount_percentage) ?>">
						</div>
						<div class="col-md-3"><label for="gst_percent" class="form-label">GST %</label><input
								type="number" step="0.01" class="form-control" id="gst_percent" name="gst_percent"
								placeholder="e.g. 3" value="<?= h($gst_percent) ?>"></div>
						<div class="col-md-3"><label for="stock_quantity" class="form-label">Stock</label><input
								type="number" min="1" class="form-control" id="stock_quantity" name="stock_quantity"
								placeholder="1" value="<?= h($stock_quantity) ?>"></div>
						<div class="col-12">
							<hr class="my-2">
							<p class="fw-semibold mb-2">Gifting &amp; SEO</p>
							<div class="form-check mb-3">
								<input class="form-check-input" type="checkbox" value="1" id="is_gifting" name="is_gifting" <?= $is_gifting ? 'checked' : '' ?>>
								<label class="form-check-label" for="is_gifting">Show on gifting page</label>
							</div>
						</div>
						<div class="col-md-6">
							<label for="meta_title" class="form-label">Meta title</label>
							<input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="255" placeholder="Leave blank for default: product name | Pearlica…" value="<?= h($meta_title) ?>">
						</div>
						<div class="col-md-6">
							<label for="meta_keywords" class="form-label">Meta keywords</label>
							<input type="text" class="form-control" id="meta_keywords" name="meta_keywords" maxlength="512" placeholder="Comma-separated; blank uses defaults" value="<?= h($meta_keywords) ?>">
						</div>
						<div class="col-12">
							<label for="meta_description" class="form-label">Meta description</label>
							<textarea class="form-control" id="meta_description" name="meta_description" rows="2" maxlength="512" placeholder="Blank uses product description or a short default"><?= h($meta_description) ?></textarea>
							<small class="text-muted">Optional. When empty, the storefront builds title, description, and keywords from the product.</small>
						</div>
					</div>
					<div class="mt-3">
						<button type="submit" class="btn btn-primary">Add Product</button>
						<a href="<?= h(admin_url('products/')) ?>" class="btn btn-secondary">Cancel</a>
					</div>
				</div>
			</div>
		</form>
		<script>
			(function () {
				var n = document.getElementById('name'), s = document.getElementById('slug');
				if (!n || !s) return;
				function slug(s) { return s.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
				n.addEventListener('input', function () { if (!s.dataset.manual) s.value = slug(n.value); });
				s.addEventListener('input', function () { s.dataset.manual = '1'; });
			})();
		</script>
	</main>
	<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
	<?php require dirname(__DIR__) . '/footer.php'; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>