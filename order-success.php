<?php
/**
 * Order success page (shown after successful Razorpay payment verification).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/order.php';
require_once __DIR__ . '/includes/auth_cookie.php';

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$base_path = '';
$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

$pdo = $GLOBALS['pdo'];
$order = $order_id > 0 ? order_find_by_id($pdo, $order_id) : null;

$page_title = 'Order Confirmed | Pearlica';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/components/head.php'; ?>

<body class="homepage">
	<?php include __DIR__ . '/components/navbar.php'; ?>
	<main class="py-5 page-content-constrained">
		<div class="text-center" style="max-width:720px;margin:0 auto;">
			<h1 class="h3 text-uppercase mb-3">Thank you</h1>
			<p class="text-secondary mb-4">Your order has been placed successfully.</p>

			<?php if ($order): ?>
				<div class="border p-4 bg-white text-start" style="border-color:#ddd;">
					<div class="d-flex justify-content-between flex-wrap gap-2">
						<div>
							<div class="small text-secondary text-uppercase" style="letter-spacing:0.14em;">Order</div>
							<div class="h5 mb-0"><?php echo h($order['order_number']); ?></div>
						</div>
						<div class="text-end">
							<div class="small text-secondary text-uppercase" style="letter-spacing:0.14em;">Status</div>
							<div class="h6 mb-0"><?php echo h((string) $order['status']); ?></div>
						</div>
					</div>
					<hr>
					<div class="d-flex justify-content-between">
						<span class="text-secondary">Total</span>
						<strong>₹<?php echo number_format(((int) $order['total_paise']) / 100, 2); ?></strong>
					</div>
					<div class="mt-3 small text-secondary">
						We will contact you if we need any more details. You can reach us at <a
							href="mailto:contact@pearlica.in">contact@pearlica.in</a>.
					</div>
				</div>
			<?php else: ?>
				<div class="alert alert-warning">Order not found.</div>
			<?php endif; ?>

			<div class="mt-4 d-flex justify-content-center gap-2 flex-wrap">
				<a href="<?php echo h($base_path); ?>products" class="btn btn-outline-dark text-uppercase">Continue
					shopping</a>
				<a href="<?php echo h($base_path); ?>index" class="btn btn-outline-dark text-uppercase">Home</a>
			</div>
		</div>
	</main>
	<?php include __DIR__ . '/components/footer.php'; ?>
	<script src="<?php echo h($base_path); ?>assets/js/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		crossorigin="anonymous"></script>
</body>

</html>