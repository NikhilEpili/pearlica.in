<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_super_admin();

require_once ROOT . '/lib/refrens_settings.php';

$success = flash_get('success');
$error = flash_get('error');

$payment_methods = ['UPI', 'CREDIT_CARD', 'DEBIT_CARD', 'ACCOUNT_TRANSFER', 'CASH', 'CHEQUE', 'DD'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('refrens.php'));
	}
	$current = refrens_settings_load();
	$secret_input = trim((string) ($_POST['app_secret'] ?? ''));
	$pay_method = strtoupper(trim((string) ($_POST['payment_method'] ?? 'UPI')));
	if (!in_array($pay_method, $payment_methods, true)) {
		$pay_method = 'UPI';
	}
	$currency = strtoupper(trim((string) ($_POST['currency'] ?? 'INR')));
	if ($currency === '') {
		$currency = 'INR';
	}
	$gst_rate = (float) ($_POST['shipping_line_gst_rate'] ?? 18);
	$gst_rate = max(0.0, min(100.0, $gst_rate));
	$invoice_type = trim((string) ($_POST['invoice_type'] ?? 'INVOICE'));
	if ($invoice_type === '') {
		$invoice_type = 'INVOICE';
	}
	$next = [
		'enabled' => isset($_POST['enabled']) && (string) $_POST['enabled'] === '1',
		'api_base' => rtrim(trim((string) ($_POST['api_base'] ?? '')), '/'),
		'url_key' => trim((string) ($_POST['url_key'] ?? '')),
		'app_id' => trim((string) ($_POST['app_id'] ?? '')),
		'app_secret' => $secret_input !== '' ? $secret_input : (string) ($current['app_secret'] ?? ''),
		'invoice_type' => $invoice_type,
		'currency' => $currency,
		'send_invoice_email' => isset($_POST['send_invoice_email']) && (string) $_POST['send_invoice_email'] === '1',
		'invoice_cc_email' => trim((string) ($_POST['invoice_cc_email'] ?? '')),
		'payment_method' => $pay_method,
		'shipping_line_gst_rate' => $gst_rate,
		'seller_name' => trim((string) ($_POST['seller_name'] ?? '')),
		'seller_street' => trim((string) ($_POST['seller_street'] ?? '')),
		'seller_pincode' => trim((string) ($_POST['seller_pincode'] ?? '')),
		'seller_gst_state' => trim((string) ($_POST['seller_gst_state'] ?? '')),
		'seller_gstin' => trim((string) ($_POST['seller_gstin'] ?? '')),
		'seller_country' => strtoupper(trim((string) ($_POST['seller_country'] ?? 'IN'))) ?: 'IN',
		'seller_email' => trim((string) ($_POST['seller_email'] ?? '')),
		'seller_phone' => trim((string) ($_POST['seller_phone'] ?? '')),
	];
	if (!refrens_settings_save($next)) {
		flash_set('error', 'Could not save Invoicing settings. Check storage permissions.');
		redirect(admin_url('refrens.php'));
	}
	$token_file = defined('REFRENS_TOKEN_CACHE_FILE') ? REFRENS_TOKEN_CACHE_FILE : (ROOT . '/storage/refrens.token.json');
	@unlink($token_file);
	flash_set('success', 'Invoicing (Refrens) settings updated.');
	redirect(admin_url('refrens.php'));
}

$cfg = refrens_settings_load();
$page_title = 'Invoicing Settings';
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<div class="d-flex align-items-center justify-content-between mb-3">
		<h1 class="page-title mb-0">Invoicing Settings</h1>
		<span class="text-muted small">Refrens</span>
	</div>

	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

	<form method="post" action="<?= h(admin_url('refrens.php')) ?>">
		<?= csrf_field() ?>

		<div class="card mb-4">
			<div class="card-body">
				<h2 class="h5 mb-3">API</h2>
				<div class="row g-3">
					<div class="col-md-3">
						<label class="form-label">Enabled</label>
						<select name="enabled" class="form-select">
							<option value="0" <?= empty($cfg['enabled']) ? 'selected' : '' ?>>No</option>
							<option value="1" <?= !empty($cfg['enabled']) ? 'selected' : '' ?>>Yes</option>
						</select>
					</div>
					<div class="col-md-9">
						<label class="form-label">API Base</label>
						<input type="text" name="api_base" class="form-control" value="<?= h((string) ($cfg['api_base'] ?? '')) ?>" placeholder="https://api.refrens.com">
					</div>
					<div class="col-md-4">
						<label class="form-label">Business URL Key</label>
						<input type="text" name="url_key" class="form-control" value="<?= h((string) ($cfg['url_key'] ?? '')) ?>" placeholder="your-business-slug">
					</div>
					<div class="col-md-4">
						<label class="form-label">App ID</label>
						<input type="text" name="app_id" class="form-control" value="<?= h((string) ($cfg['app_id'] ?? '')) ?>">
					</div>
					<div class="col-md-4">
						<label class="form-label">App Secret</label>
						<input type="password" name="app_secret" class="form-control" placeholder="Leave blank to keep existing" autocomplete="new-password">
						<small class="text-muted">Current: <?= h(refrens_settings_mask((string) ($cfg['app_secret'] ?? ''))) ?></small>
					</div>
				</div>
			</div>
		</div>

		<div class="card mb-4">
			<div class="card-body">
				<h2 class="h5 mb-3">Invoice defaults</h2>
				<div class="row g-3">
					<div class="col-md-3">
						<label class="form-label">Invoice Type</label>
						<input type="text" name="invoice_type" class="form-control" value="<?= h((string) ($cfg['invoice_type'] ?? 'INVOICE')) ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label">Currency</label>
						<input type="text" name="currency" class="form-control" value="<?= h((string) ($cfg['currency'] ?? 'INR')) ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label">Online payment method label</label>
						<select name="payment_method" class="form-select">
							<?php foreach ($payment_methods as $pm): ?>
							<option value="<?= h($pm) ?>" <?= strtoupper((string) ($cfg['payment_method'] ?? '')) === $pm ? 'selected' : '' ?>><?= h($pm) ?></option>
							<?php endforeach; ?>
						</select>
						<small class="text-muted">Used for Razorpay/online orders. COD uses CASH.</small>
					</div>
					<div class="col-md-3">
						<label class="form-label">Gift packaging GST %</label>
						<input type="number" name="shipping_line_gst_rate" class="form-control" min="0" max="100" step="0.01" value="<?= h((string) ($cfg['shipping_line_gst_rate'] ?? 18)) ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label">Send invoice email</label>
						<select name="send_invoice_email" class="form-select">
							<option value="0" <?= empty($cfg['send_invoice_email']) ? 'selected' : '' ?>>No</option>
							<option value="1" <?= !empty($cfg['send_invoice_email']) ? 'selected' : '' ?>>Yes</option>
						</select>
					</div>
					<div class="col-md-5">
						<label class="form-label">CC email on invoice</label>
						<input type="email" name="invoice_cc_email" class="form-control" value="<?= h((string) ($cfg['invoice_cc_email'] ?? '')) ?>" placeholder="optional">
					</div>
				</div>
			</div>
		</div>

		<div class="card mb-4">
			<div class="card-body">
				<h2 class="h5 mb-3">Seller (billed by)</h2>
				<div class="row g-3">
					<div class="col-md-6">
						<label class="form-label">Seller name</label>
						<input type="text" name="seller_name" class="form-control" value="<?= h((string) ($cfg['seller_name'] ?? '')) ?>">
					</div>
					<div class="col-md-6">
						<label class="form-label">Seller email</label>
						<input type="email" name="seller_email" class="form-control" value="<?= h((string) ($cfg['seller_email'] ?? '')) ?>">
					</div>
					<div class="col-12">
						<label class="form-label">Street address</label>
						<textarea name="seller_street" class="form-control" rows="2"><?= h((string) ($cfg['seller_street'] ?? '')) ?></textarea>
					</div>
					<div class="col-md-3">
						<label class="form-label">Pincode</label>
						<input type="text" name="seller_pincode" class="form-control" value="<?= h((string) ($cfg['seller_pincode'] ?? '')) ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label">GST state code</label>
						<input type="text" name="seller_gst_state" class="form-control" value="<?= h((string) ($cfg['seller_gst_state'] ?? '')) ?>" placeholder="e.g. 27">
					</div>
					<div class="col-md-3">
						<label class="form-label">GSTIN</label>
						<input type="text" name="seller_gstin" class="form-control" value="<?= h((string) ($cfg['seller_gstin'] ?? '')) ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label">Country</label>
						<input type="text" name="seller_country" class="form-control" value="<?= h((string) ($cfg['seller_country'] ?? 'IN')) ?>">
					</div>
					<div class="col-md-4">
						<label class="form-label">Phone</label>
						<input type="text" name="seller_phone" class="form-control" value="<?= h((string) ($cfg['seller_phone'] ?? '')) ?>" placeholder="optional">
					</div>
				</div>
			</div>
		</div>

		<button type="submit" class="btn btn-primary">Save Invoicing Settings</button>
	</form>
</main>
<?php require __DIR__ . '/components/wrapper_end.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
