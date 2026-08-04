<?php
/**
 * Storefront: single order detail (owner only).
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

function money_inr_paise(int $paise): string {
	return '₹' . number_format($paise / 100, 0);
}

$base_path = store_base_path_from_request();
$user_id = auth_cookie_user_id();
if ($user_id === null) {
	header('Location: ' . $base_path . 'login?return_url=' . rawurlencode('orders'), true, 302);
	exit;
}

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pdo = $GLOBALS['pdo'];
$user = store_user_find_by_id($pdo, $user_id);
if (!$user) {
	auth_cookie_clear();
	header('Location: ' . $base_path . 'login?return_url=orders', true, 302);
	exit;
}

$order = $order_id > 0 ? order_find_by_id($pdo, $order_id) : null;
if (!$order || (int) ($order['store_user_id'] ?? 0) !== (int) $user_id) {
	header('Location: ' . $base_path . 'orders', true, 302);
	exit;
}

$items = order_items_list($pdo, $order_id);
$status_label = ucwords(str_replace('_', ' ', (string) ($order['status'] ?? '')));

$page_title = 'Order ' . (string) ($order['order_number'] ?? '') . ' | Pearlica';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h($base_path); ?>assets/css/profile.css">
<link rel="stylesheet" href="<?php echo h($base_path); ?>assets/css/orders.css">
<body class="profile-page orders-page">
	<?php include __DIR__ . '/components/navbar.php'; ?>
	<main class="profile-main">
		<div class="profile-wrap page-content-constrained">
			<div class="profile-content orders-content">
				<nav class="profile-breadcrumb-wrap orders-breadcrumb" aria-label="Breadcrumb">
					<ol class="profile-breadcrumb">
						<li class="profile-breadcrumb-item"><a href="<?php echo h($base_path); ?>index">Home</a></li>
						<li class="profile-breadcrumb-item"><a href="<?php echo h($base_path); ?>orders">My orders</a></li>
						<li class="profile-breadcrumb-item active" aria-current="page"><?php echo h((string) ($order['order_number'] ?? '')); ?></li>
					</ol>
				</nav>
				<p class="profile-content-label">Account</p>
				<h1 class="profile-content-title">Order details</h1>
				<hr class="profile-content-rule">

				<div class="orders-detail-card profile-fade-up">
					<div class="orders-detail-head">
						<div>
							<p class="orders-detail-kicker">Order number</p>
							<p class="orders-detail-strong"><?php echo h((string) ($order['order_number'] ?? '')); ?></p>
						</div>
						<div class="orders-detail-head-right">
							<p class="orders-detail-kicker">Status</p>
							<p class="orders-detail-strong"><?php echo h($status_label); ?></p>
						</div>
					</div>
					<?php if (!empty($order['created_at'])): ?>
						<p class="orders-detail-meta">Placed <?php echo h(date('j F Y, g:i a', strtotime((string) $order['created_at']))); ?></p>
					<?php endif; ?>
					<p class="orders-detail-meta">Payment: <?php echo h(strtoupper((string) ($order['payment_method'] ?? ''))); ?></p>

					<h2 class="profile-form-block-label orders-detail-items-heading">Items</h2>
					<ul class="orders-detail-items">
						<?php foreach ($items as $it): ?>
							<li class="orders-detail-line">
								<span class="orders-detail-line-name"><?php echo h((string) ($it['product_name'] ?? '')); ?></span>
								<span class="orders-detail-line-qty">× <?php echo (int) ($it['quantity'] ?? 0); ?></span>
								<span class="orders-detail-line-price"><?php echo h(money_inr_paise((int) ($it['line_total_paise'] ?? 0))); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>

					<div class="orders-detail-totals">
						<div class="orders-detail-total-row">
							<span>Subtotal</span>
							<span><?php echo h(money_inr_paise((int) ($order['subtotal_paise'] ?? 0))); ?></span>
						</div>
						<div class="orders-detail-total-row">
							<span>Shipping</span>
							<span><?php echo h(money_inr_paise((int) ($order['shipping_paise'] ?? 0))); ?></span>
						</div>
						<div class="orders-detail-total-row orders-detail-total-row--discount">
							<span>Discount</span>
							<span>−<?php echo h(money_inr_paise((int) ($order['discount_paise'] ?? 0))); ?></span>
						</div>
						<div class="orders-detail-total-row orders-detail-total-grand">
							<span>Total</span>
							<span><?php echo h(money_inr_paise((int) ($order['total_paise'] ?? 0))); ?></span>
						</div>
					</div>

					<div class="orders-detail-ship">
						<p class="orders-detail-kicker">Ship to</p>
						<p class="orders-detail-address">
							<?php echo h((string) ($order['shipping_name'] ?? '')); ?><br>
							<?php if (trim((string) ($order['shipping_phone'] ?? '')) !== ''): ?>
								<?php echo h((string) $order['shipping_phone']); ?><br>
							<?php endif; ?>
							<?php echo h((string) ($order['shipping_email'] ?? '')); ?><br>
							<?php echo nl2br(h(trim((string) ($order['shipping_address'] ?? '')))); ?>
							<?php
							$city = trim((string) ($order['shipping_city'] ?? ''));
							$state = trim((string) ($order['shipping_state'] ?? ''));
							$pin = trim((string) ($order['shipping_pincode'] ?? ''));
							$line2 = implode(', ', array_filter([$city, $state, $pin]));
							?>
							<?php if ($line2 !== ''): ?><br><?php echo h($line2); ?><?php endif; ?>
						</p>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include __DIR__ . '/components/footer.php'; ?>
	<script src="<?php echo h($base_path); ?>assets/js/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
