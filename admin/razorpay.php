<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_super_admin();

require_once ROOT . '/config/razorpay.php';
require_once ROOT . '/payment/razorpay/razorpay_settings.php';

function rp_mask_key_id(string $key_id): string
{
	if ($key_id === '') {
		return '';
	}
	if (strlen($key_id) <= 8) {
		return $key_id;
	}
	return substr($key_id, 0, 8) . str_repeat('*', max(0, strlen($key_id) - 8));
}

$settings = razorpay_settings_load();
$cfg = razorpay_config();
$key_id_active = (string) ($cfg['key_id'] ?? '');
$mode = strtoupper((string) ($cfg['mode'] ?? ''));
$success = flash_get('success');
$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('razorpay.php'));
	}
	$current = $settings;
	$test_secret_in = trim((string) ($_POST['test_key_secret'] ?? ''));
	$live_secret_in = trim((string) ($_POST['live_key_secret'] ?? ''));
	$webhook_secret_in = trim((string) ($_POST['webhook_secret'] ?? ''));
	$next = [
		'mode' => (string) ($_POST['mode'] ?? 'test') === 'live' ? 'live' : 'test',
		'test_key_id' => trim((string) ($_POST['test_key_id'] ?? '')),
		'test_key_secret' => $test_secret_in !== '' ? $test_secret_in : (string) ($current['test_key_secret'] ?? ''),
		'live_key_id' => trim((string) ($_POST['live_key_id'] ?? '')),
		'live_key_secret' => $live_secret_in !== '' ? $live_secret_in : (string) ($current['live_key_secret'] ?? ''),
		'webhook_secret' => $webhook_secret_in !== '' ? $webhook_secret_in : (string) ($current['webhook_secret'] ?? ''),
		'api_base' => trim((string) ($_POST['api_base'] ?? 'https://api.razorpay.com/v1')),
		'currency' => strtoupper(trim((string) ($_POST['currency'] ?? 'INR'))),
	];
	if ($next['currency'] === '') {
		$next['currency'] = 'INR';
	}
	if (!razorpay_settings_save($next)) {
		flash_set('error', 'Could not save Razorpay settings. Check storage permissions.');
		redirect(admin_url('razorpay.php'));
	}
	flash_set('success', 'Razorpay settings updated.');
	redirect(admin_url('razorpay.php'));
}

$page_title = 'Razorpay';
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>

<main class="container-fluid p-4 admin-content">
	<div class="d-flex align-items-center justify-content-between mb-3">
		<h1 class="page-title mb-0">Razorpay</h1>
		<a href="<?= h(admin_url('razorpay-payments.php')) ?>" class="btn btn-secondary btn-sm">Razorpay Payments</a>
	</div>
	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

	<div class="card">
		<div class="card-body">
			<form method="post" action="<?= h(admin_url('razorpay.php')) ?>" class="row g-3">
				<?= csrf_field() ?>
				<div class="col-md-3">
					<label class="form-label">Mode</label>
					<select class="form-select" name="mode">
						<option value="test" <?= strtolower((string) ($cfg['mode'] ?? '')) === 'test' ? 'selected' : '' ?>>Test</option>
						<option value="live" <?= strtolower((string) ($cfg['mode'] ?? '')) === 'live' ? 'selected' : '' ?>>Live</option>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label">Currency</label>
					<input type="text" class="form-control" name="currency" value="<?= h((string) ($cfg['currency'] ?? 'INR')) ?>">
				</div>
				<div class="col-md-6">
					<label class="form-label">API Base</label>
					<input type="text" class="form-control" name="api_base" value="<?= h((string) ($cfg['api_base'] ?? 'https://api.razorpay.com/v1')) ?>">
				</div>
				<div class="col-md-6">
					<label class="form-label">Test Key ID</label>
					<input type="text" class="form-control" name="test_key_id" value="<?= h((string) ($settings['test_key_id'] ?? '')) ?>">
				</div>
				<div class="col-md-6">
					<label class="form-label">Test Key Secret</label>
					<input type="password" class="form-control" name="test_key_secret" placeholder="Leave blank to keep existing">
					<small class="text-muted">Current: <?= h(razorpay_settings_mask((string) ($settings['test_key_secret'] ?? ''))) ?></small>
				</div>
				<div class="col-md-6">
					<label class="form-label">Live Key ID</label>
					<input type="text" class="form-control" name="live_key_id" value="<?= h((string) ($settings['live_key_id'] ?? '')) ?>">
				</div>
				<div class="col-md-6">
					<label class="form-label">Live Key Secret</label>
					<input type="password" class="form-control" name="live_key_secret" placeholder="Leave blank to keep existing">
					<small class="text-muted">Current: <?= h(razorpay_settings_mask((string) ($settings['live_key_secret'] ?? ''))) ?></small>
				</div>
				<div class="col-md-12">
					<label class="form-label">Webhook Secret</label>
					<input type="password" class="form-control" name="webhook_secret" placeholder="Leave blank to keep existing">
					<small class="text-muted">Current: <?= h(razorpay_settings_mask((string) ($settings['webhook_secret'] ?? ''))) ?></small>
				</div>
				<div class="col-12">
					<div class="small text-muted mb-2">Active key id: <code><?= h(rp_mask_key_id($key_id_active)) ?></code> | Current mode: <strong><?= h($mode) ?></strong></div>
					<button type="submit" class="btn btn-primary">Save Razorpay Settings</button>
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
