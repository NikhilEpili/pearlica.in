<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_super_admin();

$pdo = $GLOBALS['pdo'];
$page_title = 'Shiprocket Orders';

$rows = [];
try {
	$stmt = $pdo->query("SELECT so.id, so.order_id, so.shiprocket_order_id, so.shipment_id, so.awb_code, so.status, so.updated_at,
		o.order_number, o.shipping_email, o.payment_method
		FROM shiprocket_orders so
		LEFT JOIN orders o ON o.id = so.order_id
		ORDER BY so.id DESC
		LIMIT 200");
	$rows = $stmt->fetchAll();
} catch (PDOException $e) {
	$rows = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<div class="d-flex align-items-center justify-content-between mb-3">
		<h1 class="page-title mb-0">Shiprocket Orders</h1>
		<a href="<?= h(admin_url('shiprocket.php')) ?>" class="btn btn-secondary btn-sm">Settings</a>
	</div>
	<div class="card">
		<div class="card-body p-0">
			<table class="table mb-0">
				<thead>
					<tr>
						<th>Local Order</th>
						<th>Email</th>
						<th>Payment</th>
						<th>Shiprocket Status</th>
						<th>SR Order ID</th>
						<th>Shipment</th>
						<th>AWB</th>
						<th>Updated</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $r): ?>
						<tr>
							<td>
								<?php if (!empty($r['order_id'])): ?>
									<a href="<?= h(admin_url('orders/view.php?id=' . (int) $r['order_id'])) ?>"><?= h((string) ($r['order_number'] ?? ('#' . (int) $r['order_id']))) ?></a>
								<?php else: ?>
									-
								<?php endif; ?>
							</td>
							<td><?= h((string) ($r['shipping_email'] ?? '-')) ?></td>
							<td><?= h((string) ($r['payment_method'] ?? '-')) ?></td>
							<td><span class="badge bg-secondary text-uppercase"><?= h((string) ($r['status'] ?? '')) ?></span></td>
							<td><?= h((string) ($r['shiprocket_order_id'] ?? '')) ?></td>
							<td><?= h((string) ($r['shipment_id'] ?? '')) ?></td>
							<td><?= h((string) ($r['awb_code'] ?? '')) ?></td>
							<td><?= h((string) ($r['updated_at'] ?? '')) ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($rows)): ?>
						<tr><td colspan="8" class="text-muted p-3">No Shiprocket rows found yet.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</main>
<?php require __DIR__ . '/components/wrapper_end.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
