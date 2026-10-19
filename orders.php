<?php
/**
 * Storefront: list orders for the logged-in user.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/order.php';
require_once __DIR__ . '/lib/store_user.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/includes/store_base_path.php';

if (!function_exists('h')) {
	function h($s) {
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$base_path = store_base_path_from_request();
$user_id = auth_cookie_user_id();
if ($user_id === null) {
	header('Location: ' . $base_path . 'login?return_url=' . rawurlencode('orders'), true, 302);
	exit;
}

$pdo = $GLOBALS['pdo'];
$user = store_user_find_by_id($pdo, $user_id);
if (!$user) {
	auth_cookie_clear();
	header('Location: ' . $base_path . 'login?return_url=orders', true, 302);
	exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = 15;
$res = order_list_for_store_user($pdo, (int) $user_id, $page, $per_page);
$orders = $res['items'];
$total = (int) ($res['total'] ?? 0);
$total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;
$db_error = $res['db_error'] ?? null;
$db_missing = !empty($res['db_missing']);

function storefront_order_status_label(string $status): string {
	return ucwords(str_replace('_', ' ', $status));
}

$page_title = 'My Orders | Pearlica 925 Silver Jewellery';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h(asset_url('assets/css/profile.css', $base_path)); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('assets/css/orders.css', $base_path)); ?>">
<body class="profile-page orders-page">
	<?php include __DIR__ . '/components/gtm_body.php'; ?>
	<?php include __DIR__ . '/components/navbar.php'; ?>
	<main class="profile-main">
		<div class="profile-wrap page-content-constrained">
			<div class="profile-content orders-content">
				<p class="profile-content-label">Account</p>
				<h1 class="profile-content-title">My Orders</h1>
				<hr class="profile-content-rule">

				<?php if ($db_error): ?>
					<div class="profile-alert profile-alert-danger"><?php echo h($db_error); ?></div>
					<?php if ($db_missing): ?>
						<p class="orders-empty-note">If you are the store owner, create the orders tables from your SQL migration, then try again.</p>
					<?php endif; ?>
				<?php elseif (empty($orders)): ?>
					<div class="orders-empty-block profile-fade-up">
						<p class="orders-empty">You have not placed any orders yet.</p>
						<a href="<?php echo h($base_path); ?>products" class="orders-btn-shop">Shop collection</a>
					</div>
				<?php else: ?>
					<div class="orders-table-wrap profile-fade-up">
						<table class="orders-table">
							<thead>
								<tr>
									<th scope="col">Order</th>
									<th scope="col">Date</th>
									<th scope="col">Status</th>
									<th scope="col" class="orders-col-total">Total</th>
									<th scope="col" class="orders-col-action"></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($orders as $o): ?>
									<?php
									$oid = (int) ($o['id'] ?? 0);
									$num = (string) ($o['order_number'] ?? '');
									$created = (string) ($o['created_at'] ?? '');
									$date_disp = $created !== '' ? date('j M Y', strtotime($created)) : '—';
									$st = storefront_order_status_label((string) ($o['status'] ?? ''));
									$total_rupees = number_format(((int) ($o['total_paise'] ?? 0)) / 100, 0);
									?>
									<tr>
										<td><span class="orders-num"><?php echo h($num); ?></span></td>
										<td><?php echo h($date_disp); ?></td>
										<td><span class="orders-status"><?php echo h($st); ?></span></td>
										<td class="orders-col-total">₹<?php echo h($total_rupees); ?></td>
										<td class="orders-col-action">
											<a href="<?php echo h($base_path); ?>order?id=<?php echo $oid; ?>" class="orders-link-view">View</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<?php if ($total_pages > 1): ?>
						<nav class="orders-pagination orders-pagination--delayed profile-fade-up" aria-label="Order pages">
							<?php if ($page > 1): ?>
								<a class="orders-page-link" href="<?php echo h($base_path); ?>orders?page=<?php echo (int) ($page - 1); ?>">Previous</a>
							<?php endif; ?>
							<span class="orders-page-meta">Page <?php echo (int) $page; ?> of <?php echo (int) $total_pages; ?></span>
							<?php if ($page < $total_pages): ?>
								<a class="orders-page-link" href="<?php echo h($base_path); ?>orders?page=<?php echo (int) ($page + 1); ?>">Next</a>
							<?php endif; ?>
						</nav>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</main>
	<?php include __DIR__ . '/components/footer.php'; ?>
	<script src="<?php echo h(asset_url('assets/js/jquery.min.js', $base_path)); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
