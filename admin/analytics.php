<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_super_admin();
require_once ROOT . '/lib/analytics_settings.php';
require_once ROOT . '/lib/store_analytics.php';
require_once ROOT . '/lib/order.php';

$success = flash_get('success');
$error = flash_get('error');
$info = flash_get('info');
$settings = analytics_settings_load();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		flash_set('error', 'Invalid request.');
		redirect(admin_url('analytics.php'));
	}
	$action = trim((string) ($_POST['action'] ?? 'save'));
	if ($action === 'test_capi') {
		$result = store_analytics_meta_capi_send_test(trim((string) ($_POST['test_event_code'] ?? '')) ?: null);
		if (!empty($result['ok'])) {
			flash_set('success', 'Test event sent to Meta. Open Events Manager → Test events to verify.');
		} else {
			flash_set('error', (string) ($result['error'] ?? 'Test event failed.') . (isset($result['response']) ? ' ' . (string) $result['response'] : ''));
		}
		redirect(admin_url('analytics.php'));
	}
	if ($action === 'preview_purchase') {
		$preview_id = (int) ($_POST['preview_order_id'] ?? 0);
		$pdo = $GLOBALS['pdo'];
		$order = $preview_id > 0 ? order_find_by_id($pdo, $preview_id) : null;
		if (!$order) {
			flash_set('error', 'Order not found.');
			redirect(admin_url('analytics.php'));
		}
		$items = order_items_list($pdo, $preview_id);
		$lines = [];
		foreach ($items as $item) {
			$lines[] = store_analytics_line_from_order_item($item);
		}
		$bundle = store_analytics_bundle_lines($lines, ((int) ($order['total_paise'] ?? 0)) / 100);
		$_SESSION['analytics_preview_purchase'] = [
			'event_id' => store_analytics_purchase_event_id($preview_id),
			'order_id' => $preview_id,
			'transaction_id' => (string) ($order['order_number'] ?? $preview_id),
			'currency' => STORE_ANALYTICS_CURRENCY,
			'value' => $bundle['value'],
			'content_ids' => $bundle['content_ids'],
			'contents' => $bundle['contents'],
			'num_items' => $bundle['num_items'],
			'ga_items' => $bundle['ga_items'],
			'payment_method' => (string) ($order['payment_method'] ?? ''),
			'sent_at' => (string) ($order['analytics_purchase_sent_at'] ?? ''),
		];
		flash_set('info', 'Purchase payload preview updated below (not sent to Meta/GA).');
		redirect(admin_url('analytics.php'));
	}

	$token_in = trim((string) ($_POST['meta_capi_access_token'] ?? ''));
	$next = [
		'meta_capi_enabled' => isset($_POST['meta_capi_enabled']) && (string) $_POST['meta_capi_enabled'] === '1',
		'meta_capi_access_token' => $token_in !== '' ? $token_in : $settings['meta_capi_access_token'],
		'meta_test_event_code' => trim((string) ($_POST['meta_test_event_code'] ?? '')),
	];
	if (!analytics_settings_save($next)) {
		flash_set('error', 'Could not save analytics settings. Check storage permissions.');
		redirect(admin_url('analytics.php'));
	}
	flash_set('success', 'Analytics settings saved.');
	redirect(admin_url('analytics.php'));
}

$preview = $_SESSION['analytics_preview_purchase'] ?? null;
if (!is_array($preview)) {
	$preview = null;
}
$token_display = analytics_settings_mask_token($settings['meta_capi_access_token']);
$page_title = 'Analytics';
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Analytics &amp; conversion tracking</h1>
	<p class="text-muted small mb-4">
		Storefront uses Meta Pixel <code><?= h(STORE_ANALYTICS_META_PIXEL_ID) ?></code> and GA4
		<code><?= h(STORE_ANALYTICS_GA4_MEASUREMENT_ID) ?></code>.
		Purchase fires once on the thank-you page (prepaid and COD) with order value, INR, order number, and SKUs as <code>content_id</code>.
		Browser and Conversions API share the same <code>event_id</code> for deduplication.
	</p>

	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<?php if ($info): ?><div class="alert alert-info"><?= h($info) ?></div><?php endif; ?>

	<div class="row g-4">
		<div class="col-lg-7">
			<div class="card">
				<div class="card-header bg-light"><strong>Meta Conversions API</strong></div>
				<div class="card-body">
					<form method="post" action="<?= h(admin_url('analytics.php')) ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="action" value="save">
						<div class="form-check form-switch mb-3">
							<input class="form-check-input" type="checkbox" id="meta-capi-enabled" name="meta_capi_enabled" value="1"
								<?= !empty($settings['meta_capi_enabled']) ? 'checked' : '' ?>>
							<label class="form-check-label" for="meta-capi-enabled">Send Purchase via Conversions API on thank-you page</label>
						</div>
						<div class="mb-3">
							<label class="form-label" for="meta-capi-token">Access token</label>
							<input type="password" class="form-control" id="meta-capi-token" name="meta_capi_access_token"
								placeholder="<?= $token_display !== '' ? h($token_display) : 'Paste token from Meta Events Manager' ?>" autocomplete="new-password">
							<small class="text-muted">Leave blank to keep the saved token. Stored in <code>storage/analytics.settings.json</code>.</small>
						</div>
						<div class="mb-3">
							<label class="form-label" for="meta-test-event-code">Test event code</label>
							<input type="text" class="form-control" id="meta-test-event-code" name="meta_test_event_code"
								value="<?= h((string) ($settings['meta_test_event_code'] ?? '')) ?>" placeholder="TEST12345">
							<small class="text-muted">From Meta Events Manager → Test events. Used for the test button and optional live debugging.</small>
						</div>
						<button type="submit" class="btn btn-primary">Save</button>
					</form>
				</div>
			</div>
		</div>
		<div class="col-lg-5">
			<div class="card mb-4">
				<div class="card-header bg-light"><strong>Test tools</strong></div>
				<div class="card-body d-grid gap-3">
					<form method="post" action="<?= h(admin_url('analytics.php')) ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="action" value="test_capi">
						<button type="submit" class="btn btn-outline-primary w-100">Send Meta CAPI test PageView</button>
					</form>
					<form method="post" action="<?= h(admin_url('analytics.php')) ?>" class="d-flex gap-2">
						<?= csrf_field() ?>
						<input type="hidden" name="action" value="preview_purchase">
						<input type="number" min="1" class="form-control" name="preview_order_id" placeholder="Order ID" required>
						<button type="submit" class="btn btn-outline-secondary text-nowrap">Preview Purchase payload</button>
					</form>
					<p class="small text-muted mb-0">
						To test a real Purchase: place a test order (prepaid or COD), open the thank-you URL once, then check Meta Test events and GA4 Realtime.
						Refreshing thank-you should not send a second Purchase.
					</p>
				</div>
			</div>
			<?php if ($preview): ?>
				<div class="card">
					<div class="card-header bg-light"><strong>Purchase preview (order #<?= h((string) ($preview['order_id'] ?? '')) ?>)</strong></div>
					<div class="card-body">
						<pre class="small mb-0" style="white-space: pre-wrap; max-height: 360px; overflow: auto;"><?= h(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '') ?></pre>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="card mt-4">
		<div class="card-header bg-light"><strong>Events on storefront</strong></div>
		<div class="card-body small">
			<ul class="mb-0">
				<li><strong>ViewContent</strong> — product detail page (SKU as <code>content_id</code>)</li>
				<li><strong>AddToCart</strong> — after add-to-cart / buy-now cart add (queued on next page load)</li>
				<li><strong>InitiateCheckout</strong> — checkout page load</li>
				<li><strong>Purchase</strong> — order success page only, once per order; GA4 <code>purchase</code> with <code>transaction_id</code> = order number</li>
			</ul>
		</div>
	</div>
</main>
<?php require __DIR__ . '/components/wrapper_end.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
