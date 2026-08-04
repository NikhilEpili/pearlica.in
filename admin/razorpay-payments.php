<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_super_admin();

$pdo = $GLOBALS['pdo'];
$page_title = 'Razorpay Payments';

$status_badge_class = function (string $status): string {
	$s = strtolower(trim($status));
	return match ($s) {
		'paid', 'captured' => 'bg-success',
		'failed' => 'bg-danger',
		'created' => 'bg-secondary',
		default => 'bg-warning',
	};
};

$rows = [];
try {
	$stmt = $pdo->query("SELECT p.id, p.order_id, p.status, p.amount_paise, p.currency, p.provider_order_id, p.provider_payment_id, p.paid_at, p.updated_at,
		o.order_number, o.shipping_email
		FROM payments p
		LEFT JOIN orders o ON o.id = p.order_id
		WHERE p.provider = 'razorpay'
		ORDER BY p.id DESC
		LIMIT 300");
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
		<h1 class="page-title mb-0">Razorpay Payments</h1>
		<a href="<?= h(admin_url('razorpay.php')) ?>" class="btn btn-primary btn-sm">Razorpay Settings</a>
	</div>
	<div class="card">
		<div class="card-body p-0">
			<table class="table mb-0">
				<thead>
					<tr>
						<th>Order</th>
						<th>Email</th>
						<th>Status</th>
						<th>Amount</th>
						<th>Provider Order</th>
						<th>Provider Payment</th>
						<th>Paid At</th>
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
							<td>
								<?php $cls = $status_badge_class((string) ($r['status'] ?? '')); ?>
								<span class="badge <?= h($cls) ?> text-uppercase"><?= h((string) ($r['status'] ?? '')) ?></span>
							</td>
							<td><?= h('₹' . number_format(((int) ($r['amount_paise'] ?? 0)) / 100, 2) . ' ' . (string) ($r['currency'] ?? 'INR')) ?></td>
							<td><?= h((string) ($r['provider_order_id'] ?? '')) ?></td>
							<td><?= h((string) ($r['provider_payment_id'] ?? '')) ?></td>
							<td><?= h((string) ($r['paid_at'] ?? '')) ?></td>
							<td><?= h((string) ($r['updated_at'] ?? '')) ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($rows)): ?>
						<tr><td colspan="8" class="text-muted p-3">No Razorpay payments found.</td></tr>
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
