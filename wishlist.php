<?php
/**
 * Wishlist page. Cookie-based wishlist; lists items with "Move to cart" (adds to cart and removes from wishlist).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/product.php';
require_once __DIR__ . '/includes/cart_cookie.php';
require_once __DIR__ . '/includes/wishlist_cookie.php';

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path_only = parse_url($req_uri, PHP_URL_PATH);
$path_only = $path_only ?: '/';
$parts = array_values(array_filter(explode('/', $path_only)));
if (count($parts) <= 1) {
	$base_path = '';
} else {
	array_pop($parts);
	$base_path = '/' . implode('/', $parts) . '/';
}

$wishlist = wishlist_from_cookie();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
	if ($action === 'move_to_cart') {
		$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
		if ($product_id > 0) {
			$pdo = $GLOBALS['pdo'];
			$product = product_find_by_id($pdo, $product_id);
			if ($product && (string) ($product['status'] ?? '') === 'active' && (int) ($product['stock_quantity'] ?? 0) > 0) {
				$cart = cart_from_cookie();
				$stock = (int) ($product['stock_quantity'] ?? 0);
				$cur = (int) ($cart[$product_id] ?? 0);
				$cart[$product_id] = min($stock, $cur + 1);
				cart_to_cookie($cart);
				unset($wishlist[$product_id]);
				wishlist_to_cookie($wishlist);
			}
		}
		header('Location: ' . $base_path . 'wishlist', true, 302);
		exit;
	}
}

$pdo = $GLOBALS['pdo'];
$uploads_base = pearlica_uploads_products_base($base_path);
$wishlist_ids = array_keys($wishlist);
$wishlist_details = $wishlist_ids !== [] ? product_get_cart_details($pdo, $wishlist_ids) : [];

$wishlist_items = [];
foreach ($wishlist as $product_id => $qty) {
	$product_id = (int) $product_id;
	$detail = $wishlist_details[$product_id] ?? null;
	if (!$detail) {
		continue;
	}
	$mrp = (float) ($detail['mrp'] ?? 0);
	$discount = (float) ($detail['discount_percentage'] ?? 0);
	$unit_price = $discount > 0 ? $mrp * (1 - $discount / 100) : $mrp;
	$wishlist_items[] = [
		'id' => $product_id,
		'name' => $detail['name'],
		'slug' => $detail['slug'],
		'image_url' => $detail['image_url'] ?? '',
		'unit_price' => $unit_price,
		'mrp' => $mrp,
		'discount' => $discount,
	];
}

$page_title = 'Wishlist | Pearlica 925 Silver Jewellery';
?>
<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h($base_path); ?>assets/css/cart.css">

<body class="homepage">

	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main>
		<section class="cart-section py-4 py-lg-5 page-content-constrained">
			<div>
				<h4 class="section-title text-uppercase mb-4">Your Wishlist</h4>

				<?php if (empty($wishlist_items)): ?>
					<div class="cart-empty text-center py-5">
						<p class="text-secondary mb-4">Your wishlist is empty.</p>
						<a href="<?php echo h($base_path); ?>products" class="btn btn-dark text-uppercase">Continue Shopping</a>
					</div>
				<?php else: ?>
					<div class="cart-content">
						<div class="cart-items-list">
							<?php foreach ($wishlist_items as $item): ?>
								<?php
								$img_src = !empty($item['image_url']) ? $uploads_base . '/' . $item['image_url'] : 'https://placehold.co/100x100?text=No+Image';
								$product_url = $base_path . 'product?slug=' . rawurlencode($item['slug']);
								?>
								<div class="cart-item-card wishlist-item-card" data-product-id="<?php echo $item['id']; ?>">
									<div class="cart-item-image">
										<?php if ((float) ($item['discount'] ?? 0) > 0): ?>
											<span class="wishlist-discount-badge"><?php echo (int) round((float) $item['discount']); ?>% OFF</span>
										<?php endif; ?>
										<a href="<?php echo h($product_url); ?>">
											<img src="<?php echo h($img_src); ?>" alt="<?php echo h($item['name']); ?>" loading="lazy">
										</a>
									</div>
									<div class="cart-item-details">
										<a href="<?php echo h($product_url); ?>" class="cart-item-name"><?php echo h($item['name']); ?></a>
										<div class="cart-item-price">
											₹<?php echo number_format($item['unit_price'], 0); ?>
											<?php if ($item['discount'] > 0): ?>
												<span class="cart-item-mrp">₹<?php echo number_format($item['mrp'], 0); ?></span>
											<?php endif; ?>
											<?php include __DIR__ . '/components/product_tax_incl_note.php'; ?>
										</div>
										<div class="cart-item-actions wishlist-item-actions">
											<form method="post" action="<?php echo h($base_path); ?>wishlist" class="d-inline">
												<input type="hidden" name="action" value="move_to_cart">
												<input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
												<button type="submit" class="btn btn-dark btn-sm text-uppercase">Move to cart</button>
											</form>
											<a href="<?php echo h($base_path); ?>wishlist-toggle?product_id=<?php echo $item['id']; ?>&return_url=<?php echo rawurlencode($base_path . 'wishlist'); ?>" class="btn btn-outline-secondary btn-sm text-uppercase ms-2">Remove</a>
										</div>
									</div>
									<div class="cart-item-subtotal d-none d-md-block">
										<span>₹<?php echo number_format($item['unit_price'], 0); ?></span>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="cart-summary">
							<p class="text-muted small mb-3"><?php echo count($wishlist_items); ?> item(s) in your wishlist.</p>
							<a href="<?php echo h($base_path); ?>products" class="btn btn-outline-dark text-uppercase">Continue Shopping</a>
							<a href="<?php echo h($base_path); ?>checkout" class="btn btn-dark text-uppercase">Checkout</a>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>
	</main>

	<?php include __DIR__ . '/components/footer.php'; ?>

	<script src="<?php echo h($base_path); ?>assets/js/jquery.min.js"></script>
	<script src="<?php echo h($base_path); ?>assets/js/plugins.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe"
		crossorigin="anonymous"></script>
	<?php include __DIR__ . '/components/swiper_bundle_script.php'; ?>
	<script src="<?php echo h($base_path); ?>assets/js/script.min.js"></script>
	<script src="<?php echo h($base_path); ?>assets/js/wishlist.js"></script>
</body>

</html>
