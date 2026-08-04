<?php
require_once dirname(__DIR__) . '/config.php';
require_super_admin();
require_once ROOT . '/lib/promo_code.php';

$pdo = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('promo-codes/add.php'));
	}
	$code = trim((string) ($_POST['code'] ?? ''));
	$description = trim((string) ($_POST['description'] ?? ''));
	$discount_type = (string) ($_POST['discount_type'] ?? 'percent');
	$discount_value = (float) ($_POST['discount_value'] ?? 0);
	$min_order_rupees = (float) ($_POST['min_order'] ?? 0);
	$min_order_paise = (int) round($min_order_rupees * 100);
	$valid_from = trim((string) ($_POST['valid_from'] ?? ''));
	$valid_until = trim((string) ($_POST['valid_until'] ?? ''));
	$usage_limit = trim((string) ($_POST['usage_limit'] ?? ''));
	$is_active = isset($_POST['is_active']);

	if ($code === '') {
		flash_set('error', 'Code is required.');
		redirect(admin_url('promo-codes/add.php'));
	}
	if ($discount_type !== 'fixed' && $discount_type !== 'percent') {
		$discount_type = 'percent';
	}
	if ($discount_type === 'percent' && ($discount_value < 0 || $discount_value > 100)) {
		flash_set('error', 'Percent must be 0–100.');
		redirect(admin_url('promo-codes/add.php'));
	}
	if ($discount_type === 'fixed' && $discount_value < 0) {
		flash_set('error', 'Fixed amount cannot be negative.');
		redirect(admin_url('promo-codes/add.php'));
	}
	$existing = promo_code_find_by_code($pdo, $code);
	if ($existing) {
		flash_set('error', 'This code already exists.');
		redirect(admin_url('promo-codes/add.php'));
	}

	$valid_from_dt = $valid_from !== '' ? $valid_from : null;
	$valid_until_dt = $valid_until !== '' ? $valid_until : null;
	$stored_value = $discount_type === 'percent' ? (float) $discount_value : (float) $discount_value * 100;

	promo_code_create($pdo, [
		'code' => $code,
		'description' => $description,
		'discount_type' => $discount_type,
		'discount_value' => $stored_value,
		'min_order_paise' => $min_order_paise,
		'valid_from' => $valid_from_dt,
		'valid_until' => $valid_until_dt,
		'usage_limit' => $usage_limit !== '' ? (int) $usage_limit : null,
		'is_active' => $is_active,
	]);
	flash_set('success', 'Promo code added.');
	redirect(admin_url('promo-codes/'));
}

$page_title = 'Add Promo Code';
$error = flash_get('error');
$code = $_POST['code'] ?? '';
$description = $_POST['description'] ?? '';
$discount_type = $_POST['discount_type'] ?? 'percent';
$discount_value = $_POST['discount_value'] ?? '';
$min_order = $_POST['min_order'] ?? '';
$valid_from = $_POST['valid_from'] ?? '';
$valid_until = $_POST['valid_until'] ?? '';
$usage_limit = $_POST['usage_limit'] ?? '';
$is_active = isset($_POST['is_active']) ? (bool) $_POST['is_active'] : true;
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Add Promo Code</h1>
	<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<form method="post" action="<?= h(admin_url('promo-codes/add.php')) ?>">
		<?= csrf_field() ?>
		<div class="card mb-4">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-6">
						<label for="code" class="form-label">Code *</label>
						<input type="text" class="form-control" id="code" name="code" required placeholder="e.g. SAVE10" value="<?= h($code) ?>">
					</div>
					<div class="col-md-6">
						<label for="description" class="form-label">Description</label>
						<input type="text" class="form-control" id="description" name="description" placeholder="Optional" value="<?= h($description) ?>">
					</div>
					<div class="col-md-4">
						<label for="discount_type" class="form-label">Discount type</label>
						<select class="form-select" id="discount_type" name="discount_type">
							<option value="percent" <?= $discount_type === 'percent' ? 'selected' : '' ?>>Percentage</option>
							<option value="fixed" <?= $discount_type === 'fixed' ? 'selected' : '' ?>>Fixed amount (₹)</option>
						</select>
					</div>
					<div class="col-md-4">
						<label for="discount_value" class="form-label">Value *</label>
						<input type="number" class="form-control" id="discount_value" name="discount_value" step="0.01" min="0" max="<?= $discount_type === 'percent' ? '100' : '99999' ?>" required placeholder="<?= $discount_type === 'percent' ? 'e.g. 10' : 'e.g. 500' ?>" value="<?= h($discount_value) ?>">
						<small class="text-muted"><?= $discount_type === 'percent' ? 'Percent (0–100)' : 'Amount in ₹' ?></small>
					</div>
					<div class="col-md-4">
						<label for="min_order" class="form-label">Min order (₹)</label>
						<input type="number" class="form-control" id="min_order" name="min_order" min="0" step="1" placeholder="0 = no minimum" value="<?= h($min_order) ?>">
					</div>
					<div class="col-md-4">
						<label for="valid_from" class="form-label">Valid from</label>
						<input type="datetime-local" class="form-control" id="valid_from" name="valid_from" value="<?= h($valid_from) ?>">
					</div>
					<div class="col-md-4">
						<label for="valid_until" class="form-label">Valid until</label>
						<input type="datetime-local" class="form-control" id="valid_until" name="valid_until" value="<?= h($valid_until) ?>">
					</div>
					<div class="col-md-4">
						<label for="usage_limit" class="form-label">Usage limit</label>
						<input type="number" class="form-control" id="usage_limit" name="usage_limit" min="0" placeholder="Unlimited" value="<?= h($usage_limit) ?>">
					</div>
					<div class="col-12">
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $is_active ? 'checked' : '' ?>>
							<label class="form-check-label" for="is_active">Active</label>
						</div>
					</div>
				</div>
				<hr>
				<button type="submit" class="btn btn-primary">Add Promo Code</button>
				<a href="<?= h(admin_url('promo-codes/')) ?>" class="btn btn-secondary">Cancel</a>
			</div>
		</div>
	</form>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
