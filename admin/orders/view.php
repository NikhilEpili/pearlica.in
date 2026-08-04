<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('orders');
require_once ROOT . '/lib/order.php';

$pdo = $GLOBALS['pdo'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$order = $id > 0 ? order_find_by_id($pdo, $id) : null;
if (!$order) {
	flash_set('error', 'Order not found.');
	redirect(admin_url('orders/'));
}
$items = order_items_list($pdo, (int) $order['id']);

$pay_stmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1');
$pay_stmt->execute([(int) $order['id']]);
$payment = $pay_stmt->fetch();

$shiprocket = null;
$shiprocket_table_missing = false;
$shiprocket_total_rows = null;
try {
	$sr_stmt = $pdo->prepare('SELECT * FROM shiprocket_orders WHERE order_id = ? LIMIT 1');
	$sr_stmt->execute([(int) $order['id']]);
	$shiprocket = $sr_stmt->fetch();
	$shiprocket_total_rows = (int) $pdo->query('SELECT COUNT(*) FROM shiprocket_orders')->fetchColumn();
} catch (PDOException $e) {
	$msg = $e->getMessage();
	$shiprocket_table_missing = strpos($msg, 'Base table or view not found') !== false || strpos($msg, "doesn't exist") !== false;
}

$success = flash_get('success');
$error = flash_get('error');
$page_title = 'Order ' . $order['order_number'];

$status_opts = ['pending_payment', 'cod_pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

function money_inr(int $paise): string
{
	return '₹' . number_format($paise / 100, 2);
}

function order_status_badge_class(string $status): string
{
	$status = strtolower(trim($status));
	return match ($status) {
		'paid', 'delivered' => 'bg-success',
		'pending_payment' => 'bg-warning',
		'cod_pending' => 'bg-info',
		'processing', 'shipped' => 'bg-primary',
		'cancelled', 'refunded' => 'bg-danger',
		default => 'bg-secondary',
	};
}

function order_status_update_text_class(string $status): string
{
	// Keep button text readable on all bg-* badge colors.
	return 'text-white';
}

function payment_status_badge_class(?string $status): string
{
	$status = strtolower(trim((string) ($status ?? '')));
	return match ($status) {
		'paid', 'captured' => 'bg-success',
		'failed' => 'bg-danger',
		'created' => 'bg-secondary',
		default => 'bg-secondary',
	};
}

function shiprocket_badge_class(string $sr_status): string
{
	$sr_status = strtolower(trim($sr_status));
	return match ($sr_status) {
		'created', 'accepted' => 'bg-success',
		'failed' => 'bg-danger',
		'pending' => 'bg-warning',
		default => 'bg-secondary',
	};
}
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>

<body class="admin-body">
	<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
	<main class="container-fluid p-4 admin-content">
		<div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
			<div>
				<h1 class="page-title mb-1">Order <?= h($order['order_number']) ?></h1>
				<div class="text-muted small">Created: <?= h($order['created_at']) ?> · ID: <?= (int) $order['id'] ?>
				</div>
			</div>
			<div class="text-end">
				<a href="<?= h(admin_url('orders/')) ?>" class="btn btn-sm btn-secondary">Back</a>
			</div>
		</div>

		<?php if ($success): ?>
			<div class="alert alert-success mt-3"><?= h($success) ?></div><?php endif; ?>
		<?php if ($error): ?>
			<div class="alert alert-danger mt-3"><?= h($error) ?></div><?php endif; ?>

		<div class="row g-3 mt-1">
			<div class="col-lg-7">
				<div class="card">
					<div class="card-header bg-white"><strong>Items</strong></div>
					<div class="card-body p-0">
						<table class="table mb-0">
							<thead>
								<tr>
									<th>Product</th>
									<th>SKU</th>
									<th class="text-end">Unit</th>
									<th class="text-end">Qty</th>
									<th class="text-end">Total</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($items as $it): ?>
									<tr>
										<td><?= h($it['product_name']) ?></td>
										<td class="small text-muted"><?= h($it['sku']) ?></td>
										<td class="text-end"><?= h(money_inr((int) $it['unit_price_paise'])) ?></td>
										<td class="text-end"><?= (int) $it['quantity'] ?></td>
										<td class="text-end"><?= h(money_inr((int) $it['line_total_paise'])) ?></td>
									</tr>
								<?php endforeach; ?>
								<?php if (empty($items)): ?>
									<tr>
										<td colspan="5" class="text-muted p-3">No items.</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<div class="card-footer bg-white text-end">
						<div class="small text-muted">Subtotal: <?= h(money_inr((int) $order['subtotal_paise'])) ?>
						</div>
						<div class="small text-muted">Shipping: <?= h(money_inr((int) $order['shipping_paise'])) ?>
						</div>
						<div class="small text-muted">Discount: -<?= h(money_inr((int) $order['discount_paise'])) ?>
						</div>
						<div class="h5 mb-0">Total: <?= h(money_inr((int) $order['total_paise'])) ?></div>
					</div>
				</div>
			</div>

			<div class="col-lg-5">
				<div class="card mb-3">
					<div class="card-header bg-white d-flex justify-content-between align-items-center">
						<strong>Status</strong>
						<span
							class="badge <?= h(order_status_badge_class((string) ($order['status'] ?? ''))) ?> text-uppercase"><?= h((string) ($order['status'] ?? '')) ?></span>
					</div>
					<div class="card-body">
						<form method="post" action="<?= h(admin_url('orders/update-status.php')) ?>" class="row g-2">
							<?= csrf_field() ?>
							<input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
							<div class="col-8">
								<select name="status" class="form-select form-select-sm" required>
									<?php foreach ($status_opts as $opt): ?>
										<option value="<?= h($opt) ?>" <?= $opt === $order['status'] ? 'selected' : '' ?>>
											<?= h($opt) ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-4">
								<button id="order-status-update-btn"
									class="btn btn-sm w-100 <?= h(order_status_badge_class((string) ($order['status'] ?? ''))) ?> <?= h(order_status_update_text_class((string) ($order['status'] ?? ''))) ?>"
									type="submit">Update</button>
							</div>
						</form>
					</div>
				</div>

				<div class="card mb-3">
					<div class="card-header bg-white"><strong>Customer & Shipping</strong></div>
					<div class="card-body">
						<div><strong><?= h($order['shipping_name']) ?></strong></div>
						<div class="small text-muted">
							<?= h($order['shipping_email']) ?><?= trim((string) $order['shipping_phone']) !== '' ? ' · ' . h($order['shipping_phone']) : '' ?>
						</div>
						<hr>
						<div class="small" style="white-space:pre-line;"><?= h($order['shipping_address']) ?></div>
						<?php
						$ship_loc = array_filter([
							trim((string) ($order['shipping_city'] ?? '')),
							trim((string) ($order['shipping_state'] ?? '')),
							trim((string) ($order['shipping_pincode'] ?? '')),
						], static fn($p) => $p !== '');
						?>
						<?php if ($ship_loc !== []): ?>
							<div class="small text-muted mt-2"><?= h(implode(', ', $ship_loc)) ?></div>
						<?php endif; ?>
					</div>
				</div>

				<div class="card">
					<div class="card-header bg-white"><strong>Payment</strong></div>
					<div class="card-body small">
						<div><span class="text-muted">Method:</span> <?= h($order['payment_method']) ?></div>
						<?php if ($payment): ?>
							<div><span class="text-muted">Provider:</span> <?= h($payment['provider']) ?></div>
							<div><span class="text-muted">Status:</span> <span
									class="badge <?= h(payment_status_badge_class((string) ($payment['status'] ?? ''))) ?> text-uppercase"><?= h((string) ($payment['status'] ?? '')) ?></span>
							</div>
							<div><span class="text-muted">Amount:</span> <?= h(money_inr((int) $payment['amount_paise'])) ?>
								<?= h($payment['currency']) ?></div>
							<?php if (!empty($payment['provider_order_id'])): ?>
								<div><span class="text-muted">Razorpay order:</span> <?= h($payment['provider_order_id']) ?>
								</div>
							<?php endif; ?>
							<?php if (!empty($payment['provider_payment_id'])): ?>
								<div><span class="text-muted">Razorpay payment:</span> <?= h($payment['provider_payment_id']) ?>
								</div>
							<?php endif; ?>
							<?php if (!empty($payment['paid_at'])): ?>
								<div><span class="text-muted">Paid at:</span> <?= h($payment['paid_at']) ?></div>
							<?php endif; ?>
						<?php else: ?>
							<div class="text-muted">No payment record.</div>
						<?php endif; ?>
						<hr>
						<?php if ($shiprocket_table_missing): ?>
							<div class="text-warning">Shiprocket setup pending: run migration
								`migrations/20260326_create_shiprocket_orders.sql`.</div>
						<?php else: ?>
							<?php
							$sr_display = $shiprocket ? (string) ($shiprocket['status'] ?? 'pending') : 'not_created';
							$sr_class = $shiprocket ? shiprocket_badge_class((string) ($shiprocket['status'] ?? 'pending')) : 'bg-warning';
							?>
							<div><span class="text-muted">Shiprocket status:</span> <span
									class="badge <?= h($sr_class) ?> text-uppercase"><?= h($sr_display) ?></span></div>
							<?php if ($shiprocket_total_rows === 0): ?>
								<div class="text-muted">No Shiprocket rows exist yet in database.</div>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ($shiprocket && !empty($shiprocket['shiprocket_order_id'])): ?>
							<div><span class="text-muted">Shiprocket order:</span>
								<?= h($shiprocket['shiprocket_order_id']) ?></div>
						<?php endif; ?>
						<?php if ($shiprocket && !empty($shiprocket['shipment_id'])): ?>
							<div><span class="text-muted">Shipment ID:</span> <?= h($shiprocket['shipment_id']) ?></div>
						<?php endif; ?>
						<?php if ($shiprocket && !empty($shiprocket['awb_code'])): ?>
							<div><span class="text-muted">AWB:</span> <?= h($shiprocket['awb_code']) ?></div>
						<?php endif; ?>
						<div class="mt-2">
							<form method="post" action="<?= h(admin_url('orders/shiprocket-sync.php')) ?>">
								<?= csrf_field() ?>
								<input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
								<button type="submit" class="btn btn-sm btn-dark">Sync to Shiprocket</button>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
	<?php require dirname(__DIR__) . '/footer.php'; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		(function () {
			var btn = document.getElementById('order-status-update-btn');
			var sel = document.querySelector('select[name="status"]');
			if (!btn || !sel) return;

			function mapClass(v) {
				v = String(v || '').trim();
				switch (v) {
					case 'paid':
					case 'delivered':
						return 'bg-success';
					case 'pending_payment':
						return 'bg-warning';
					case 'cod_pending':
						return 'bg-info';
					case 'processing':
					case 'shipped':
						return 'bg-primary';
					case 'cancelled':
					case 'refunded':
						return 'bg-danger';
					default:
						return 'bg-secondary';
				}
			}

			function apply() {
				var cls = mapClass(sel.value);
				// Replace only the bootstrap bg-* class
				btn.classList.remove('bg-success', 'bg-warning', 'bg-info', 'bg-primary', 'bg-danger', 'bg-secondary');
				btn.classList.add(cls);
			}

			sel.addEventListener('change', apply);
			apply();
		})();
	</script>
</body>

</html>