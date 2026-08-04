<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_super_admin();

require_once ROOT . '/payment/shiprocket/shiprocket_settings.php';

$success = flash_get('success');
$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('shiprocket.php'));
	}
	$current = shiprocket_settings_load();
	$password_input = trim((string) ($_POST['password'] ?? ''));
	$pickup_pc = preg_replace('/\D+/', '', trim((string) ($_POST['pickup_postcode'] ?? '')));
	$est_w = (float) ($_POST['estimate_weight_kg'] ?? 0.5);
	$est_w = max(0.05, min(100.0, $est_w));
	$next = [
		'enabled' => isset($_POST['enabled']) && (string) $_POST['enabled'] === '1',
		'env' => (string) ($_POST['env'] ?? 'staging') === 'production' ? 'production' : 'staging',
		'email' => trim((string) ($_POST['email'] ?? '')),
		'password' => $password_input !== '' ? $password_input : (string) ($current['password'] ?? ''),
		'pickup_location' => trim((string) ($_POST['pickup_location'] ?? '')),
		'pickup_postcode' => strlen($pickup_pc) === 6 ? $pickup_pc : '',
		'estimate_weight_kg' => $est_w,
		'channel_id' => max(0, (int) ($_POST['channel_id'] ?? 0)),
		'token_ttl' => max(300, (int) ($_POST['token_ttl'] ?? 3300)),
		'api_base_staging' => trim((string) ($_POST['api_base_staging'] ?? '')),
		'api_base_production' => trim((string) ($_POST['api_base_production'] ?? '')),
	];
	if (!shiprocket_settings_save($next)) {
		flash_set('error', 'Could not save Shiprocket settings. Check storage permissions.');
		redirect(admin_url('shiprocket.php'));
	}
	@unlink(ROOT . '/storage/shiprocket.token.json');
	flash_set('success', 'Shiprocket settings updated.');
	redirect(admin_url('shiprocket.php'));
}

$cfg = shiprocket_settings_load();
$page_title = 'Shiprocket Settings';
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<div class="d-flex align-items-center justify-content-between mb-3">
		<h1 class="page-title mb-0">Shiprocket Settings</h1>
		<a href="<?= h(admin_url('shiprocket-orders.php')) ?>" class="btn btn-secondary btn-sm">Shiprocket Orders</a>
	</div>

	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

	<div class="card">
		<div class="card-body">
			<form method="post" action="<?= h(admin_url('shiprocket.php')) ?>" class="row g-3">
				<?= csrf_field() ?>
				<div class="col-md-3">
					<label class="form-label">Enabled</label>
					<select name="enabled" class="form-select">
						<option value="0" <?= empty($cfg['enabled']) ? 'selected' : '' ?>>No</option>
						<option value="1" <?= !empty($cfg['enabled']) ? 'selected' : '' ?>>Yes</option>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label">Environment</label>
					<select name="env" class="form-select">
						<option value="staging" <?= ($cfg['env'] ?? '') === 'staging' ? 'selected' : '' ?>>Staging</option>
						<option value="production" <?= ($cfg['env'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option>
					</select>
				</div>
				<div class="col-md-6">
					<label class="form-label">Email</label>
					<input type="email" name="email" class="form-control" value="<?= h((string) ($cfg['email'] ?? '')) ?>">
				</div>
				<div class="col-md-6">
					<label class="form-label">Password</label>
					<input type="password" name="password" class="form-control" placeholder="Leave blank to keep existing">
					<small class="text-muted">Current: <?= h(shiprocket_settings_mask_password((string) ($cfg['password'] ?? ''))) ?></small>
				</div>
				<div class="col-md-6">
					<label class="form-label">Pickup Location</label>
					<input type="text" name="pickup_location" class="form-control" value="<?= h((string) ($cfg['pickup_location'] ?? '')) ?>" placeholder="e.g. work">
				</div>
				<div class="col-md-3">
					<label class="form-label">Pickup PIN code</label>
					<input type="text" name="pickup_postcode" class="form-control" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" value="<?= h((string) ($cfg['pickup_postcode'] ?? '')) ?>" placeholder="6 digits">
					<small class="text-muted">Warehouse PIN for Shiprocket ETA at checkout.</small>
				</div>
				<div class="col-md-3">
					<label class="form-label">ETA weight (kg)</label>
					<input type="number" name="estimate_weight_kg" class="form-control" min="0.05" max="100" step="0.05" value="<?= h((string) ($cfg['estimate_weight_kg'] ?? 0.5)) ?>">
					<small class="text-muted">Used only for delivery estimates.</small>
				</div>
				<div class="col-md-3">
					<label class="form-label">Channel ID</label>
					<input type="number" min="0" name="channel_id" class="form-control" value="<?= (int) ($cfg['channel_id'] ?? 0) ?>">
				</div>
				<div class="col-md-3">
					<label class="form-label">Token TTL (seconds)</label>
					<input type="number" min="300" name="token_ttl" class="form-control" value="<?= (int) ($cfg['token_ttl'] ?? 3300) ?>">
				</div>
				<div class="col-md-6">
					<label class="form-label">API Base (Staging)</label>
					<input type="text" name="api_base_staging" class="form-control" value="<?= h((string) ($cfg['api_base_staging'] ?? '')) ?>">
				</div>
				<div class="col-md-6">
					<label class="form-label">API Base (Production)</label>
					<input type="text" name="api_base_production" class="form-control" value="<?= h((string) ($cfg['api_base_production'] ?? '')) ?>">
				</div>
				<div class="col-12">
					<button type="submit" class="btn btn-primary">Save Shiprocket Settings</button>
				</div>
			</form>
		</div>
	</div>
</main>
<?php require __DIR__ . '/components/wrapper_end.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
