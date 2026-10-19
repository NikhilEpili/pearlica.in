<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/product.php';
require_once __DIR__ . '/lib/product_media.php';
require_once __DIR__ . '/lib/product_group.php';
require_once __DIR__ . '/lib/product_specification.php';
require_once __DIR__ . '/includes/cart_cookie.php';
require_once __DIR__ . '/includes/wishlist_cookie.php';
require_once __DIR__ . '/includes/shop_product_helpers.php';
require_once __DIR__ . '/lib/product_review.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/lib/store_user.php';

if (empty($_SESSION['product_review_csrf']) || !is_string($_SESSION['product_review_csrf'])) {
	$_SESSION['product_review_csrf'] = bin2hex(random_bytes(32));
}
$product_review_csrf = (string) $_SESSION['product_review_csrf'];

if (empty($_SESSION['checkout_csrf_token']) || !is_string($_SESSION['checkout_csrf_token'])) {
	$_SESSION['checkout_csrf_token'] = bin2hex(random_bytes(32));
}
$product_delivery_csrf = (string) $_SESSION['checkout_csrf_token'];

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
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

if ($slug === '') {
	header('Location: ' . $base_path . 'products', true, 302);
	exit;
}

$pdo = $GLOBALS['pdo'];
$stmt = $pdo->prepare('SELECT p.id, p.name, p.slug, p.sku, p.description, p.specification, p.additional_information, p.styling_tips, p.mrp, p.discount_percentage, p.stock_quantity, p.category_id, p.product_group_id, p.metal_type, p.purity, p.weight_grams, p.size, p.meta_title, p.meta_description, p.meta_keywords, p.is_gifting, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL WHERE p.slug = ? AND p.deleted_at IS NULL AND p.status = ? LIMIT 1');
$stmt->execute([$slug, 'active']);
$product = $stmt->fetch();
if (!$product) {
	header('Location: ' . $base_path . 'products', true, 302);
	exit;
}
$stock_qty = (int) ($product['stock_quantity'] ?? 0);
if ($stock_qty <= 0) {
	header('Location: ' . $base_path . 'products', true, 302);
	exit;
}

$group_members = [];
if (!empty($product['product_group_id'])) {
	$group_members = product_group_members_for_store($pdo, (int) $product['product_group_id']);
}

$related_products = [];
try {
	$related_products = product_related_for_store(
		$pdo,
		(int) $product['id'],
		!empty($product['category_id']) ? (int) $product['category_id'] : null,
		8
	);
} catch (Throwable $e) {
	$related_products = [];
}

$review_stats = ['average' => 0.0, 'count' => 0];
$review_list = [];
try {
	$review_stats = product_review_average_and_count($pdo, (int) $product['id']);
	$review_list = product_review_list_for_product($pdo, (int) $product['id'], 40);
} catch (Throwable $e) {
	$review_stats = ['average' => 0.0, 'count' => 0];
	$review_list = [];
}

$review_flash = isset($_GET['review']) ? trim((string) $_GET['review']) : '';

$review_viewer_id = auth_cookie_user_id();
$review_logged_in = $review_viewer_id !== null;
$review_has_purchased = false;
$review_user_has_reviewed = false;
if ($review_logged_in) {
	try {
		$review_has_purchased = product_review_user_has_purchased($pdo, (int) $review_viewer_id, (int) $product['id']);
		$review_user_has_reviewed = product_review_exists_for_user($pdo, (int) $review_viewer_id, (int) $product['id']);
	} catch (Throwable $e) {
		$review_has_purchased = false;
		$review_user_has_reviewed = false;
	}
}
$review_show_form = $review_logged_in && $review_has_purchased && !$review_user_has_reviewed;
$review_return_url = $base_path . 'product?slug=' . rawurlencode((string) ($product['slug'] ?? ''));
$review_display_name = '';
if ($review_logged_in) {
	$ru = store_user_find_by_id($pdo, (int) $review_viewer_id);
	if ($ru) {
		$review_display_name = trim((string) ($ru['full_name'] ?? ''));
		if ($review_display_name === '') {
			$review_display_name = trim((string) ($ru['email'] ?? ''));
		}
	}
}

$media = product_media_list_by_product($pdo, $product['id']);
$uploads_base = pearlica_uploads_products_base($base_path);

$primary_img = null;
foreach ($media as $m) {
	if (!empty($m['url'])) {
		$primary_img = $m['url'];
		break;
	}
}
$primary_img_src = $primary_img
	? media_storage_public_url($uploads_base, (string) $primary_img, 'products')
	: 'https://placehold.co/600x700?text=No+Image';

$mrp = (float) ($product['mrp'] ?? 0);
$discount = (float) ($product['discount_percentage'] ?? 0);
$sale_price = $discount > 0 ? $mrp * (1 - $discount / 100) : $mrp;

$show_limited_stock = ($stock_qty === 1);

$cart = cart_from_cookie();
$in_cart = isset($cart[$product['id']]) && (int) $cart[$product['id']] > 0;
$wishlist_ids = array_keys(wishlist_from_cookie());
$in_wishlist = in_array((int) $product['id'], $wishlist_ids, true);

$seo = product_store_seo_meta($product);
$meta_title_full = $seo['meta_title_full'];
$meta_description = $seo['meta_description'];
$meta_keywords = $seo['meta_keywords'];

$description_display = trim((string) ($product['description'] ?? ''));
if ($description_display === '' || strtolower($description_display) === 'a very heavy silver ring') {
	$description_display = 'Handcrafted in certified 925 sterling silver, designed for presence and permanence.';
}
$styling_tips_raw = trim((string) ($product['styling_tips'] ?? ''));
$styling_tips_list = $styling_tips_raw !== '' ? array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $styling_tips_raw)), function ($s) {
	return $s !== '';
})) : [];

$spec_rows = product_specification_list_by_product($pdo, $product['id']);
$spec_map = [];
foreach ($spec_rows as $row) {
	$k = trim((string) ($row['spec_key'] ?? ''));
	if ($k !== '') {
		$spec_map[$k] = trim((string) ($row['spec_value'] ?? ''));
	}
}
$spec_private_keys = ['SKU', 'sku'];
$spec_grid = [];
foreach ($spec_map as $key => $val) {
	if ($val === '')
		continue;
	if (in_array($key, $spec_private_keys, true))
		continue;
	if (strcasecmp($key, 'Weight') === 0)
		continue;
	if (strcasecmp($key, 'Dimensions / size') === 0 || strcasecmp($key, 'Dimensions') === 0 || strcasecmp($key, 'Size') === 0)
		continue;
	if (in_array($key, ['Material', 'Purity', 'Finish', 'Collection'], true))
		continue;
	$spec_grid[] = ['label' => $key, 'value' => $val];
}

$rating_avg = (float) ($review_stats['average'] ?? 0);
$rating_cnt = (int) ($review_stats['count'] ?? 0);
$gallery_slide_srcs = [];
foreach ($media as $gm) {
	if (!empty($gm['url'])) {
		$gallery_slide_srcs[] = media_storage_public_url($uploads_base, (string) $gm['url'], 'products');
	}
}
if ($gallery_slide_srcs === []) {
	$gallery_slide_srcs[] = $primary_img_src;
}
$gallery_slide_count = count($gallery_slide_srcs);
$product_has_variants = count($group_members) > 1;
$return_slug_q = 'product?slug=' . rawurlencode($product['slug']);
$product_share_path = ltrim($base_path . 'product?slug=' . rawurlencode((string) ($product['slug'] ?? '')), '/');
$product_share_url = (defined('SITE_PUBLIC_URL') && (string) SITE_PUBLIC_URL !== '')
	? rtrim((string) SITE_PUBLIC_URL, '/') . '/' . $product_share_path
	: '';
?>
<!DOCTYPE html>
<html lang="en" class="product-detail-no-scrollbar">

<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h(asset_url('assets/css/product.css', $base_path)); ?>">

<body class="homepage product-detail-page" data-product-id="<?php echo (int) $product['id']; ?>"
	data-base-path="<?php echo h($base_path); ?>"
	data-checkout-csrf="<?php echo h($product_delivery_csrf); ?>">
	<?php include __DIR__ . '/components/gtm_body.php'; ?>

	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main>
		<section class="product-detail-section">
			<div class="page-content-constrained">
				<div class="product-detail-grid <?php echo count($media) === 0 ? 'has-no-thumbs' : ''; ?>"
					id="product-gallery-wrap">
					<div class="product-gallery-column">
						<div class="product-gallery-top">
							<div
								class="product-gallery-layout<?php echo $product_has_variants ? ' product-gallery-layout--with-variants' : ''; ?>">
								<?php if ($product_has_variants): ?>
									<aside class="product-other-options product-other-options--side-rail"
										aria-label="Other product variants">
										<p class="product-other-options-title">Other variants</p>
										<ul class="product-other-options-list" role="list">
											<?php foreach ($group_members as $gm):
												$gm_id = (int) ($gm['id'] ?? 0);
												$is_current = $gm_id === (int) $product['id'];
												$gm_slug = (string) ($gm['slug'] ?? '');
												$gm_url = $base_path . 'product?slug=' . rawurlencode($gm_slug);
												$gm_img = !empty($gm['image_url'])
													? media_storage_public_url($uploads_base, (string) $gm['image_url'], 'products')
													: 'https://placehold.co/120x120?text=No+Image';
												?>
												<li class="product-other-options-item">
													<a href="<?php echo h($gm_url); ?>"
														class="product-other-option<?php echo $is_current ? ' is-current' : ''; ?>"
														<?php echo $is_current ? ' aria-current="page"' : ''; ?>>
														<span class="product-other-option-img">
															<img src="<?php echo h($gm_img); ?>" alt="" loading="lazy">
														</span>
														<span
															class="product-other-option-name"><?php echo h($gm['name'] ?? ''); ?></span>
													</a>
												</li>
											<?php endforeach; ?>
										</ul>
									</aside>
								<?php endif; ?>
								<div class="product-gallery-main">
									<div class="product-gallery-swiper" data-product-gallery>
										<div class="product-gallery-frame" data-gallery-frame>
											<div class="product-gallery-viewport" data-gallery-viewport tabindex="0"
												role="region"
												aria-label="<?php echo h($product['name']); ?> — product images"
												aria-roledescription="carousel">
												<div class="product-gallery-track" data-gallery-track>
													<?php foreach ($gallery_slide_srcs as $gi => $gsrc): ?>
														<div class="product-gallery-slide" data-gallery-slide
															aria-roledescription="slide"
															aria-label="<?php echo 'Image ' . ($gi + 1) . ' of ' . $gallery_slide_count; ?>">
															<img src="<?php echo h($gsrc); ?>"
																alt="<?php echo $gi === 0 ? h($product['name']) : h($product['name'] . ' — view ' . ($gi + 1)); ?>"
																loading="<?php echo $gi === 0 ? 'eager' : 'lazy'; ?>"
																decoding="async" draggable="false">
														</div>
													<?php endforeach; ?>
												</div>
											</div>
											<?php if ($gallery_slide_count > 1): ?>
												<button type="button" class="product-gallery-nav product-gallery-nav--prev"
													data-gallery-prev aria-label="Previous image" disabled>
													<span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
												</button>
												<button type="button" class="product-gallery-nav product-gallery-nav--next"
													data-gallery-next aria-label="Next image">
													<span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
												</button>
											<?php endif; ?>
											<div class="product-gallery-overlay-bottom">
												<?php if ($rating_cnt > 0): ?>
													<div class="product-gallery-rating-badge"
														aria-label="Rated <?php echo h(number_format($rating_avg, 1)); ?> out of 5 from <?php echo h((string) $rating_cnt); ?> reviews">
														<span class="product-gallery-rating-star"
															aria-hidden="true">★</span>
														<span class="product-gallery-rating-num"
															aria-hidden="true"><?php echo h(number_format($rating_avg, 1)); ?></span>
														<span class="product-gallery-rating-sep" aria-hidden="true">|</span>
														<span class="product-gallery-rating-count"
															aria-hidden="true"><?php echo h(number_format($rating_cnt)); ?></span>
													</div>
												<?php else: ?>
													<div
														class="product-gallery-rating-badge product-gallery-rating-badge--empty">
														<span>No reviews yet</span>
													</div>
												<?php endif; ?>
												<button type="button" class="product-gallery-fs-btn"
													data-gallery-fullscreen aria-label="View gallery full screen">
													<span class="material-symbols-outlined product-gallery-fs-icon"
														aria-hidden="true">photo_library</span>
												</button>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="product-info-column">
						<?php if (!empty($product['category_name'])): ?>
							<p class="product-category-label fade-up fade-up-delay-1" data-fade-up>
								<?php echo h($product['category_name']); ?>
							</p>
						<?php endif; ?>
						<h1 class="product-title fade-up fade-up-delay-2" data-fade-up>
							<?php echo h($product['name']); ?>
						</h1>
						<hr class="product-hr fade-up fade-up-delay-2" data-fade-up>
						<div class="product-price-bar fade-up fade-up-delay-3" data-fade-up>
							<div class="product-price-wrap">
								<span class="product-price-current">₹<?php echo number_format($sale_price, 0); ?></span>
								<?php if ($discount > 0): ?>
									<span class="product-price-discount"><?php echo (int) round($discount); ?>% OFF</span>
									<span class="product-price-original">₹<?php echo number_format($mrp, 0); ?></span>
								<?php endif; ?>
								<?php include __DIR__ . '/components/product_tax_incl_note.php'; ?>
							</div>
							<div class="product-price-actions">
								<button type="button" class="product-share-btn"
									data-share-url="<?php echo h($product_share_url !== '' ? $product_share_url : ''); ?>"
									data-share-title="<?php echo h($product['name']); ?>"
									aria-label="Share this product">
									<span class="material-symbols-outlined product-share-ms"
										aria-hidden="true">share</span>
								</button>
								<a href="<?php echo h($base_path); ?>wishlist-toggle?product_id=<?php echo (int) $product['id']; ?>&return_url=<?php echo rawurlencode($base_path . $return_slug_q); ?>"
									class="product-wishlist-icon-only product-wishlist-icon-only--inline btn-wishlist <?php echo $in_wishlist ? 'active' : ''; ?>"
									data-product-id="<?php echo (int) $product['id']; ?>"
									aria-label="<?php echo $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
									<span class="material-symbols-outlined product-wishlist-ms"
										aria-hidden="true"><?php echo $in_wishlist ? 'favorite' : 'favorite_border'; ?></span>
								</a>
							</div>
						</div>
						<div class="product-delivery-pin-wrap fade-up fade-up-delay-3" data-fade-up id="product-delivery-pin-wrap">
							<div class="checkout-pin-check product-delivery-pin-check" id="checkout-pin-check">
								<p class="checkout-section-label checkout-pin-check__label product-delivery-pin__title">Delivery by PIN</p>
								<div class="checkout-pin-row">
									<label class="visually-hidden" for="checkout-delivery-pin">PIN code</label>
									<input type="text" id="checkout-delivery-pin" class="checkout-pin-input" name="delivery_pin_check"
										inputmode="numeric" autocomplete="postal-code" maxlength="6" pattern="[0-9]{6}"
										placeholder="PIN" value="">
									<button type="button" class="checkout-pin-btn" id="checkout-delivery-check-btn">Check</button>
								</div>
							</div>
							<div class="checkout-delivery-eta product-delivery-eta product-delivery-eta--compact" id="checkout-delivery-eta-result" role="status" aria-live="polite"></div>
						</div>
						<?php
						$product_sku_display = trim((string) ($product['sku'] ?? ''));
						if ($product_sku_display !== ''):
							?>
							<p class="product-sku-line fade-up fade-up-delay-4" data-fade-up>
								<span class="product-sku-label">SKU</span>
								<span class="product-sku-value"><?php echo h($product_sku_display); ?></span>
							</p>
						<?php endif; ?>
						<div class="product-rating-summary fade-up fade-up-delay-4" data-fade-up>
							<div class="product-stars"
								aria-label="<?php echo $rating_cnt > 0 ? h(number_format($rating_avg, 1)) . ' out of 5' : 'No reviews yet'; ?>">
								<?php for ($si = 1; $si <= 5; $si++): ?>
									<span
										class="product-star <?php echo $rating_avg >= $si - 0.25 ? 'is-on' : ''; ?>">★</span>
								<?php endfor; ?>
							</div>
							<span
								class="product-rating-meta"><?php echo $rating_cnt > 0 ? h(number_format($rating_avg, 1)) . ' · ' . (int) $rating_cnt . ' verified review' . ($rating_cnt === 1 ? '' : 's') : 'No reviews yet'; ?></span>
						</div>

						<?php if (!empty($spec_grid)): ?>
							<div class="product-specs-grid fade-up fade-up-delay-5" data-fade-up>
								<?php foreach ($spec_grid as $spec): ?>
									<div class="product-spec">
										<span class="product-spec-label"><?php echo h($spec['label']); ?></span>
										<span class="product-spec-value"><?php echo h($spec['value']); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div class="product-trust-badges fade-up fade-up-delay-6" data-fade-up role="list">
							<div class="product-trust-badge" role="listitem">
								<span class="material-symbols-outlined product-trust-ms" aria-hidden="true">lock</span>
								<span>Secure Payment</span>
							</div>
							<div class="product-trust-badge" role="listitem">
								<span class="material-symbols-outlined product-trust-ms"
									aria-hidden="true">workspace_premium</span>
								<span>925 Certified</span>
							</div>
							<div class="product-trust-badge" role="listitem">
								<span class="material-symbols-outlined product-trust-ms" aria-hidden="true">sync</span>
								<span>7 Day Easy Exchange</span>
							</div>
							<div class="product-trust-badge" role="listitem">
								<span class="material-symbols-outlined product-trust-ms"
									aria-hidden="true">local_shipping</span>
								<span>Free shipping on orders ₹700 &amp; above</span>
							</div>
							<div class="product-trust-badge" role="listitem">
								<span class="material-symbols-outlined product-trust-ms" aria-hidden="true">spa</span>
								<span>925 Fine Silver – Skin Friendly</span>
							</div>
							<div class="product-trust-badge" role="listitem">
								<span class="material-symbols-outlined product-trust-ms"
									aria-hidden="true">verified</span>
								<span>Authentication Certificate</span>
							</div>
						</div>
						<?php if (!empty($show_limited_stock)): ?>
							<p class="product-stock-limited fade-up fade-up-delay-6" data-fade-up>Limited Pieces Available</p>
						<?php endif; ?>

						<div id="product-main-ctas" class="product-cta-row fade-up fade-up-delay-6" data-fade-up>
							<div class="product-cta-btns">
								<?php if ($in_cart): ?>
									<div class="product-btn-added">Added to Cart ✓</div>
									<a href="<?php echo h($base_path); ?>checkout"
										class="product-btn-add product-btn-add--outline text-center text-decoration-none d-block">Checkout</a>
								<?php else: ?>
									<form method="post" action="<?php echo h($base_path . 'cart'); ?>" id="add-to-cart-form"
										class="product-add-form">
										<input type="hidden" name="action" value="add">
										<input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
										<input type="hidden" name="return_url" value="<?php echo h($return_slug_q); ?>">
										<input type="hidden" name="quantity" value="1">
										<button type="submit" class="product-btn-buy" id="add-to-cart-btn"
											name="add_to_cart">Add to Cart</button>
									</form>
									<form method="post" action="<?php echo h($base_path . 'cart'); ?>"
										class="product-buy-form">
										<input type="hidden" name="action" value="add">
										<input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
										<input type="hidden" name="return_url" value="checkout">
										<input type="hidden" name="quantity" value="1">
										<button type="submit" class="product-btn-add">Buy Now</button>
									</form>
								<?php endif; ?>
							</div>
						</div>

						<?php
						$acc_has_desc = $description_display !== '';
						$acc_has_spec = trim((string) ($product['specification'] ?? '')) !== '';
						$acc_has_add = trim((string) ($product['additional_information'] ?? '')) !== '';
						$acc_has_style = !empty($styling_tips_list);
						if ($acc_has_desc || $acc_has_spec || $acc_has_add || $acc_has_style):
							$acc_parent_id = 'product-detail-accordion';
							$acc_expand_next = true;
							?>
							<div class="accordion product-detail-accordion fade-up fade-up-delay-5"
								id="<?php echo h($acc_parent_id); ?>" data-fade-up>
								<?php if ($acc_has_desc):
									$cid = 'pdp-acc-description';
									$show = $acc_expand_next;
									if ($acc_expand_next) {
										$acc_expand_next = false;
									}
									?>
									<div class="accordion-item">
										<h2 class="accordion-header">
											<button class="accordion-button<?php echo $show ? '' : ' collapsed'; ?>"
												type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo h($cid); ?>"
												aria-expanded="<?php echo $show ? 'true' : 'false'; ?>"
												aria-controls="<?php echo h($cid); ?>">
												Description
											</button>
										</h2>
										<div id="<?php echo h($cid); ?>"
											class="accordion-collapse collapse<?php echo $show ? ' show' : ''; ?>"
											data-bs-parent="#<?php echo h($acc_parent_id); ?>">
											<div class="accordion-body product-detail-accordion-body">
												<?php echo nl2br(h($description_display)); ?>
											</div>
										</div>
									</div>
								<?php endif; ?>
								<?php if ($acc_has_spec):
									$cid = 'pdp-acc-spec';
									$show = $acc_expand_next;
									if ($acc_expand_next) {
										$acc_expand_next = false;
									}
									?>
									<div class="accordion-item">
										<h2 class="accordion-header">
											<button class="accordion-button<?php echo $show ? '' : ' collapsed'; ?>"
												type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo h($cid); ?>"
												aria-expanded="<?php echo $show ? 'true' : 'false'; ?>"
												aria-controls="<?php echo h($cid); ?>">
												Specification
											</button>
										</h2>
										<div id="<?php echo h($cid); ?>"
											class="accordion-collapse collapse<?php echo $show ? ' show' : ''; ?>"
											data-bs-parent="#<?php echo h($acc_parent_id); ?>">
											<div class="accordion-body product-detail-accordion-body">
												<?php echo nl2br(h($product['specification'])); ?>
											</div>
										</div>
									</div>
								<?php endif; ?>
								<?php if ($acc_has_add):
									$cid = 'pdp-acc-additional';
									$show = $acc_expand_next;
									if ($acc_expand_next) {
										$acc_expand_next = false;
									}
									?>
									<div class="accordion-item">
										<h2 class="accordion-header">
											<button class="accordion-button<?php echo $show ? '' : ' collapsed'; ?>"
												type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo h($cid); ?>"
												aria-expanded="<?php echo $show ? 'true' : 'false'; ?>"
												aria-controls="<?php echo h($cid); ?>">
												Additional Information
											</button>
										</h2>
										<div id="<?php echo h($cid); ?>"
											class="accordion-collapse collapse<?php echo $show ? ' show' : ''; ?>"
											data-bs-parent="#<?php echo h($acc_parent_id); ?>">
											<div class="accordion-body product-detail-accordion-body">
												<?php echo nl2br(h($product['additional_information'])); ?>
											</div>
										</div>
									</div>
								<?php endif; ?>
								<?php if ($acc_has_style):
									$cid = 'pdp-acc-styling';
									$show = $acc_expand_next;
									if ($acc_expand_next) {
										$acc_expand_next = false;
									}
									?>
									<div class="accordion-item">
										<h2 class="accordion-header">
											<button class="accordion-button<?php echo $show ? '' : ' collapsed'; ?>"
												type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo h($cid); ?>"
												aria-expanded="<?php echo $show ? 'true' : 'false'; ?>"
												aria-controls="<?php echo h($cid); ?>">
												Styling Tips
											</button>
										</h2>
										<div id="<?php echo h($cid); ?>"
											class="accordion-collapse collapse<?php echo $show ? ' show' : ''; ?>"
											data-bs-parent="#<?php echo h($acc_parent_id); ?>">
											<div class="accordion-body product-detail-accordion-body">
												<?php foreach ($styling_tips_list as $tip): ?>
													<div class="product-styling-tip">
														<span class="product-styling-text"><?php echo h($tip); ?></span>
													</div>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

					</div>
				</div>

				<div class="product-reviews-wrap" id="product-reviews">
					<h2 class="product-reviews-title">Ratings &amp; Reviews</h2>
					<?php if ($review_flash === 'thanks'): ?>
						<p class="alert alert-success py-2 px-3 small">Thank you — your review was posted.</p>
					<?php elseif ($review_flash === 'error'): ?>
						<p class="alert alert-warning py-2 px-3 small">We could not save your review. Choose a star rating
							and try again.</p>
					<?php elseif ($review_flash === 'auth'): ?>
						<p class="alert alert-info py-2 px-3 small">Please sign in to leave a review.</p>
					<?php elseif ($review_flash === 'forbidden'): ?>
						<p class="alert alert-warning py-2 px-3 small">Only customers who have purchased this product can
							leave a review.</p>
					<?php elseif ($review_flash === 'duplicate'): ?>
						<p class="alert alert-info py-2 px-3 small">You have already submitted a review for this product.
						</p>
					<?php endif; ?>

					<div class="product-reviews-summary-row">
						<div class="product-reviews-avg">
							<span
								class="product-reviews-avg-num"><?php echo $rating_cnt > 0 ? h(number_format($rating_avg, 1)) : '—'; ?></span>
							<div class="product-stars product-stars--lg" aria-hidden="true">
								<?php for ($si = 1; $si <= 5; $si++): ?>
									<span
										class="product-star <?php echo $rating_avg >= $si - 0.25 ? 'is-on' : ''; ?>">★</span>
								<?php endfor; ?>
							</div>
							<span class="product-reviews-count text-muted small"><?php echo (int) $rating_cnt; ?>
								verified review<?php echo $rating_cnt === 1 ? '' : 's'; ?></span>
						</div>
						<div class="product-review-aside">
							<?php if (!$review_logged_in): ?>
								<p class="text-muted small mb-2">Anyone can read reviews. Sign in to leave one after purchasing this product.</p>
								<a href="<?php echo h($base_path); ?>login?return_url=<?php echo rawurlencode($review_return_url); ?>"
									class="btn btn-outline-dark rounded-0 btn-sm text-uppercase letter-spacing-1">Sign in to write a review</a>
							<?php elseif ($review_show_form): ?>
								<form method="post" action="<?php echo h($base_path); ?>product-review-submit"
									class="product-review-form" id="product-review-form" novalidate>
									<input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
									<input type="hidden" name="slug" value="<?php echo h($product['slug']); ?>">
									<input type="hidden" name="csrf_token" value="<?php echo h($product_review_csrf); ?>">
									<p class="product-review-form-label">Your rating</p>
									<p class="product-review-rating-error text-danger small mb-2 d-none"
										id="review-rating-error" tabindex="-1" role="alert">Tap the stars to choose a rating
										(1–5).</p>
									<div class="product-review-stars-picker" data-review-star-picker>
										<input type="hidden" name="rating" id="review-rating-input" value=""
											autocomplete="off">
										<div class="product-review-stars-track" role="radiogroup" aria-label="Star rating">
											<?php for ($ri = 1; $ri <= 5; $ri++): ?>
												<button type="button" role="radio" class="product-review-star-btn"
													data-star-value="<?php echo $ri; ?>"
													aria-label="<?php echo $ri; ?> out of 5 stars" aria-checked="false">
													<span class="material-symbols-outlined product-review-star-glyph"
														aria-hidden="true">star</span>
												</button>
											<?php endfor; ?>
										</div>
									</div>
									<p class="product-review-verified-note text-muted small mb-2">Posting as
										<strong><?php echo h($review_display_name !== '' ? $review_display_name : 'Customer'); ?></strong>
										(verified purchase).
									</p>
									<label class="product-review-form-label" for="review-body">Review (optional)</label>
									<textarea class="form-control form-control-sm mb-3" id="review-body"
										name="body" rows="3" maxlength="5000"
										placeholder="Share your experience with this piece"></textarea>
									<button type="submit"
										class="btn btn-dark rounded-0 btn-sm text-uppercase letter-spacing-1">Submit
										review</button>
								</form>
							<?php elseif ($review_user_has_reviewed): ?>
								<p class="text-muted small mb-0">You have already shared a review for this product. Thank
									you.</p>
							<?php elseif (!$review_has_purchased): ?>
								<p class="text-muted small mb-0">After you purchase this item, you can leave a rating and
									review here.</p>
							<?php endif; ?>
						</div>
					</div>
					<?php if (!empty($review_list)): ?>
						<ul class="product-reviews-list list-unstyled mb-0">
							<?php foreach ($review_list as $rev): ?>
								<li class="product-review-item">
									<div class="product-review-item-stars">
										<?php for ($si = 1; $si <= 5; $si++): ?>
											<span
												class="product-star <?php echo (int) ($rev['rating'] ?? 0) >= $si ? 'is-on' : ''; ?>">★</span>
										<?php endfor; ?>
									</div>
									<p class="product-review-item-meta text-muted small mb-1">
										<?php echo h($rev['author_name'] ?? 'Customer'); ?> ·
										<?php echo h(date('M j, Y', strtotime((string) ($rev['created_at'] ?? 'now')))); ?>
									</p>
									<?php if (trim((string) ($rev['body'] ?? '')) !== ''): ?>
										<p class="product-review-item-body mb-0"><?php echo nl2br(h($rev['body'])); ?></p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php elseif ($rating_cnt === 0): ?>
						<p class="text-muted small mb-0">No reviews yet. Verified buyers will appear here first.</p>
					<?php endif; ?>
				</div>

				<?php if (!empty($related_products)): ?>
					<?php $ymal_ret = rawurlencode($base_path . $return_slug_q); ?>
					<div class="product-you-may-also">
						<h2 class="product-ymal-heading">You May Also Like</h2>
						<div class="product-ymal-carousel" data-product-ymal-carousel>
							<div class="product-ymal-track">
								<?php foreach ($related_products as $rp):
									$rp_img = !empty($rp['image_url'])
										? media_storage_public_url($uploads_base, (string) $rp['image_url'], 'products')
										: 'https://placehold.co/400x400?text=No+Image';
									$rp_url = $base_path . 'product?slug=' . rawurlencode((string) ($rp['slug'] ?? ''));
									$rp_mrp = (float) ($rp['mrp'] ?? 0);
									$rp_disc = (float) ($rp['discount_percentage'] ?? 0);
									$rp_price = $rp_disc > 0 ? $rp_mrp * (1 - $rp_disc / 100) : $rp_mrp;
									$rp_in_wl = in_array((int) ($rp['id'] ?? 0), $wishlist_ids, true);
									[$rp_w, $rp_h] = pearlica_get_image_dimensions((string) ($rp['image_url'] ?? ''), (string) $rp_img);
									?>
									<article class="product-ymal-card">
										<div class="product-ymal-card-inner">
											<div class="product-ymal-card-image-wrap" style="aspect-ratio: 4 / 5;">
												<?php if ($rp_disc > 0): ?>
													<span
														class="product-ymal-card-discount-badge"><?php echo (int) round($rp_disc); ?>%
														OFF</span>
												<?php endif; ?>
												<a href="<?php echo h($rp_url); ?>" class="product-ymal-card-image-link">
													<span class="product-ymal-card-image-inner">
														<img src="<?php echo h($rp_img); ?>"
															alt="<?php echo h($rp['name'] ?? ''); ?>"
															class="product-ymal-card-image" loading="lazy"
															width="<?php echo (int) $rp_w; ?>"
															height="<?php echo (int) $rp_h; ?>">
													</span>
												</a>
												<a href="<?php echo h($base_path); ?>wishlist-toggle?product_id=<?php echo (int) $rp['id']; ?>&return_url=<?php echo $ymal_ret; ?>"
													class="product-ymal-card-wishlist btn-wishlist <?php echo $rp_in_wl ? 'active' : ''; ?>"
													data-product-id="<?php echo (int) $rp['id']; ?>"
													aria-label="<?php echo $rp_in_wl ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
													<span class="material-symbols-outlined product-wishlist-ms"
														aria-hidden="true"><?php echo $rp_in_wl ? 'favorite' : 'favorite_border'; ?></span>
												</a>
												<form method="post" action="<?php echo h($base_path); ?>cart"
													class="product-ymal-add-form">
													<input type="hidden" name="action" value="add">
													<input type="hidden" name="product_id"
														value="<?php echo (int) $rp['id']; ?>">
													<input type="hidden" name="return_url"
														value="<?php echo h($return_slug_q); ?>">
													<input type="hidden" name="quantity" value="1">
													<button type="submit" class="product-ymal-card-addstrip">Add to
														Cart</button>
												</form>
											</div>
											<div class="product-ymal-card-info">
												<h3 class="product-ymal-card-name"><a
														href="<?php echo h($rp_url); ?>"><?php echo h($rp['name'] ?? ''); ?></a>
												</h3>
												<div class="product-ymal-card-price-row">
													<span
														class="product-ymal-card-price">₹<?php echo number_format($rp_price, 0); ?></span>
													<?php if ($rp_disc > 0): ?>
														<span
															class="product-ymal-card-price-original">₹<?php echo number_format($rp_mrp, 0); ?></span>
													<?php endif; ?>
													<?php include __DIR__ . '/components/product_tax_incl_note.php'; ?>
												</div>
											</div>
										</div>
									</article>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<?php if (!$in_cart): ?>
				<div class="product-sticky-cta d-lg-none" id="product-sticky-cta" aria-hidden="true">
					<div class="product-sticky-cta-inner page-content-constrained">
						<form method="post" action="<?php echo h($base_path . 'cart'); ?>" class="product-sticky-cta-form">
							<input type="hidden" name="action" value="add">
							<input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
							<input type="hidden" name="return_url" value="<?php echo h($return_slug_q); ?>">
							<input type="hidden" name="quantity" value="1">
							<button type="submit" class="product-sticky-btn product-sticky-btn--secondary">Add to
								Cart</button>
						</form>
						<form method="post" action="<?php echo h($base_path . 'cart'); ?>" class="product-sticky-cta-form">
							<input type="hidden" name="action" value="add">
							<input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
							<input type="hidden" name="return_url" value="checkout">
							<input type="hidden" name="quantity" value="1">
							<button type="submit" class="product-sticky-btn product-sticky-btn--primary">Buy Now</button>
						</form>
					</div>
				</div>
			<?php endif; ?>
		</section>
	</main>

	<?php include __DIR__ . '/components/footer.php'; ?>

	<script src="<?php echo h(asset_url('assets/js/jquery.min.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/plugins.js', $base_path)); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe"
		crossorigin="anonymous"></script>
	<?php include __DIR__ . '/components/swiper_bundle_script.php'; ?>
	<script src="<?php echo h(asset_url('assets/js/script.min.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/wishlist.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/checkout-delivery-estimate.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/product.js', $base_path)); ?>"></script>
</body>

</html>