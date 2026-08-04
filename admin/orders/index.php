<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('orders');
require_once ROOT . '/lib/order.php';

$pdo = $GLOBALS['pdo'];

$status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$payment_method = isset($_GET['payment_method']) ? trim((string) $_GET['payment_method']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$filters = [
	'status' => $status,
	'payment_method' => $payment_method,
];
$res = order_list_admin($pdo, $filters, $page, ADMIN_ITEMS_PER_PAGE);
$orders = $res['items'];
$total = (int) $res['total'];
$total_pages = pagination_total_pages($total, ADMIN_ITEMS_PER_PAGE);
$db_missing = !empty($res['db_missing']);
$db_error = isset($res['db_error']) ? (string) $res['db_error'] : '';
$shiprocket_status_map = [];
$shiprocket_total_rows = null;

try {
	$shiprocket_total_rows = (int) $pdo->query('SELECT COUNT(*) FROM shiprocket_orders')->fetchColumn();
} catch (PDOException $e) {
	$shiprocket_total_rows = null;
}

if (!empty($orders)) {
	$order_ids = [];
	foreach ($orders as $o) {
		$order_ids[] = (int) ($o['id'] ?? 0);
	}
	$order_ids = array_values(array_filter($order_ids));
	if (!empty($order_ids)) {
		try {
			$placeholders = implode(',', array_fill(0, count($order_ids), '?'));
			$stmt = $pdo->prepare("SELECT order_id, status FROM shiprocket_orders WHERE order_id IN ({$placeholders})");
			$stmt->execute($order_ids);
			$rows = $stmt->fetchAll();
			foreach ($rows as $r) {
				$oid = (int) ($r['order_id'] ?? 0);
				if ($oid > 0) {
					$shiprocket_status_map[$oid] = (string) ($r['status'] ?? 'pending');
				}
			}
		} catch (PDOException $e) {
			// Keep orders page usable even if Shiprocket table is missing.
		}
	}
}

$page_title = 'Orders';
$success = flash_get('success');
$error = flash_get('error');

$status_opts = ['', 'pending_payment', 'cod_pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
$pay_opts = ['', 'razorpay', 'cod'];

function money_inr(int $paise): string {
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

function payment_method_badge_class(string $method): string
{
	$method = strtolower(trim($method));
	return match ($method) {
		'razorpay' => 'bg-primary',
		'cod' => 'bg-info',
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
	<h1 class="page-title">Orders</h1>
	<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
	<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
	<?php if ($db_missing): ?>
		<div class="alert alert-warning">
			<strong>Orders tables are missing.</strong>
			Please run <code>sql/create_orders_tables.sql</code> on your database, then refresh this page.
		</div>
	<?php elseif ($db_error !== ''): ?>
		<div class="alert alert-danger"><?= h($db_error) ?></div>
	<?php endif; ?>
	<?php if ($shiprocket_total_rows !== null && $shiprocket_total_rows === 0): ?>
		<div class="alert alert-info">
			<strong>Shiprocket is connected but no synced rows yet.</strong>
			All orders will show <code>not_created</code> until the first order is synced to Shiprocket.
		</div>
	<?php endif; ?>

	<form class="row g-2 mb-3" method="get" action="">
		<div class="col-sm-3">
			<label class="form-label small text-muted">Status</label>
			<select name="status" class="form-select form-select-sm">
				<?php foreach ($status_opts as $opt): ?>
					<option value="<?= h($opt) ?>" <?= $opt === $status ? 'selected' : '' ?>><?= $opt === '' ? 'All' : h($opt) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-sm-3">
			<label class="form-label small text-muted">Payment</label>
			<select name="payment_method" class="form-select form-select-sm">
				<?php foreach ($pay_opts as $opt): ?>
					<option value="<?= h($opt) ?>" <?= $opt === $payment_method ? 'selected' : '' ?>><?= $opt === '' ? 'All' : h($opt) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-sm-3 d-flex align-items-end gap-2">
			<button class="btn btn-sm btn-primary" type="submit">Filter</button>
								<a class="btn btn-sm btn-secondary" href="<?= h(admin_url('orders/')) ?>">Reset</a>
		</div>
	</form>

	<div class="card">
		<div class="card-body p-0">
			<table class="table table-hover mb-0">
				<thead>
					<tr>
						<th>ID</th>
						<th>Order</th>
						<th>Status</th>
						<th>Payment</th>
						<th>Shiprocket</th>
						<th>Total</th>
						<th>Customer</th>
						<th>Created</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($orders as $o): ?>
						<tr>
							<td><?= (int) $o['id'] ?></td>
							<td><?= h($o['order_number']) ?></td>
							<td>
								<span class="badge <?= h(order_status_badge_class((string) ($o['status'] ?? ''))) ?> text-uppercase">
									<?= h((string) ($o['status'] ?? '')) ?>
								</span>
							</td>
							<td>
								<span class="badge <?= h(payment_method_badge_class((string) ($o['payment_method'] ?? ''))) ?> text-uppercase">
									<?= h((string) ($o['payment_method'] ?? '')) ?>
								</span>
							</td>
							<td>
								<?php $sr_status = $shiprocket_status_map[(int) $o['id']] ?? 'not_created'; ?>
								<?php $sr_class = $sr_status === 'not_created' ? 'bg-warning' : shiprocket_badge_class($sr_status); ?>
								<span class="badge <?= h($sr_class) ?> text-uppercase"><?= h($sr_status) ?></span>
							</td>
							<td><?= h(money_inr((int) $o['total_paise'])) ?></td>
							<td><?= h($o['shipping_name']) ?><div class="small text-muted"><?= h($o['shipping_email']) ?></div></td>
							<td class="small text-muted"><?= h($o['created_at']) ?></td>
							<td class="text-end">
								<a class="btn btn-sm btn-primary" href="<?= h(admin_url('orders/view.php?id=' . (int) $o['id'])) ?>">View</a>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($orders)): ?>
						<tr><td colspan="9" class="text-muted p-3">No orders found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php if ($total_pages > 1): ?>
		<nav class="mt-3">
			<ul class="pagination pagination-sm">
				<?php for ($p = 1; $p <= $total_pages; $p++): ?>
					<?php
					$q = $_GET;
					$q['page'] = $p;
					?>
					<li class="page-item <?= $p === $page ? 'active' : '' ?>">
						<a class="page-link" href="?<?= h(http_build_query($q)) ?>"><?= $p ?></a>
					</li>
				<?php endfor; ?>
			</ul>
		</nav>
	<?php endif; ?>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

