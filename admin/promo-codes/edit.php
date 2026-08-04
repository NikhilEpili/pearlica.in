<?php
require_once dirname(__DIR__) . '/config.php';
require_super_admin();
require_once ROOT . '/lib/promo_code.php';

$pdo = $GLOBALS['pdo'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$promo = $id > 0 ? promo_code_find_by_id($pdo, $id) : null;
if (!$promo) {
	flash_set('error', 'Promo code not found.');
	redirect(admin_url('promo-codes/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('promo-codes/edit.php?id=' . $id));
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
		redirect(admin_url('promo-codes/edit.php?id=' . $id));
	}
	if ($discount_type !== 'fixed' && $discount_type !== 'percent') {
		$discount_type = 'percent';
	}
	if ($discount_type === 'percent' && ($discount_value < 0 || $discount_value > 100)) {
		flash_set('error', 'Percent must be 0–100.');
		redirect(admin_url('promo-codes/edit.php?id=' . $id));
	}
	$stored_value = $discount_type === 'percent' ? $discount_value : (float) $discount_value * 100;

	promo_code_update($pdo, $id, [
		'code' => $code,
		'description' => $description,
		'discount_type' => $discount_type,
		'discount_value' => $stored_value,
		'min_order_paise' => $min_order_paise,
		'valid_from' => $valid_from !== '' ? $valid_from : null,
		'valid_until' => $valid_until !== '' ? $valid_until : null,
		'usage_limit' => $usage_limit !== '' ? (int) $usage_limit : null,
		'is_active' => $is_active,
	]);
	flash_set('success', 'Promo code updated.');
	redirect(admin_url('promo-codes/'));
}

$page_title = 'Edit Promo Code';
$error = flash_get('error');
$code = $promo['code'];
$description = $promo['description'] ?? '';
$discount_type = $promo['discount_type'] ?? 'percent';
$discount_value = $discount_type === 'percent' ? (float) $promo['discount_value'] : (float) $promo['discount_value'] / 100;
$min_order = (int) $promo['min_order_paise'] > 0 ? (float) $promo['min_order_paise'] / 100 : '';
$valid_from = $promo['valid_from'] ?? '';
$valid_until = $promo['valid_until'] ?? '';
$usage_limit = $promo['usage_limit'] !== null ? (string) $promo['usage_limit'] : '';
$is_active = !empty($promo['is_active']);
if ($valid_from !== '') {
	$valid_from = date('Y-m-d\TH:i', strtotime($valid_from));
}
if ($valid_until !== '') {
	$valid_until = date('Y-m-d\TH:i', strtotime($valid_until));
}
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Edit Promo Code</h1>
	<?php if (!empty($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<form method="post" action="<?= h(admin_url('promo-codes/edit.php?id=' . $id)) ?>">
		<?= csrf_field() ?>
		<div class="card mb-4">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-6">
						<label for="code" class="form-label">Code *</label>
						<input type="text" class="form-control" id="code" name="code" required value="<?= h($code) ?>">
					</div>
					<div class="col-md-6">
						<label for="description" class="form-label">Description</label>
						<input type="text" class="form-control" id="description" name="description" value="<?= h($description) ?>">
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
						<input type="number" class="form-control" id="discount_value" name="discount_value" step="0.01" min="0" required value="<?= h($discount_value) ?>">
					</div>
					<div class="col-md-4">
						<label for="min_order" class="form-label">Min order (₹)</label>
						<input type="number" class="form-control" id="min_order" name="min_order" min="0" step="1" value="<?= h($min_order) ?>">
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
						<input type="number" class="form-control" id="usage_limit" name="usage_limit" min="0" value="<?= h($usage_limit) ?>">
						<small class="text-muted">Used: <?= (int) $promo['used_count'] ?></small>
					</div>
					<div class="col-12">
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $is_active ? 'checked' : '' ?>>
							<label class="form-check-label" for="is_active">Active</label>
						</div>
					</div>
				</div>
				<hr>
				<button type="submit" class="btn btn-primary">Update</button>
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
