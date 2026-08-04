<?php
/**
 * Load cart data for the cart drawer. Sets $cart_items, $cart_total, $uploads_base.
 * Skip if $cart_items already set. Uses $base_path when set.
 */
if (isset($cart_items)) {
	return;
}
$base_path = isset($base_path) ? $base_path : '';
require_once __DIR__ . '/cart_cookie.php';

// cart.php may have already updated $cart and called setcookie(); $_COOKIE is stale until the next request.
if (!isset($cart) || !is_array($cart)) {
	$cart = cart_from_cookie();
}
$uploads_base = pearlica_uploads_products_base($base_path);
$cart_items = [];
$cart_total = 0;

if (empty($cart)) {
	return;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/product.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
	return;
}

$cart = cart_prune_unavailable($pdo, $cart);
if (empty($cart)) {
	return;
}

$cart_product_ids = array_keys($cart);
$cart_details = product_get_cart_details($pdo, $cart_product_ids);
foreach ($cart as $product_id => $quantity) {
	$product_id = (int) $product_id;
	$detail = $cart_details[$product_id] ?? null;
	if (!$detail) {
		continue;
	}
	$mrp = (float) ($detail['mrp'] ?? 0);
	$discount = (float) ($detail['discount_percentage'] ?? 0);
	$unit_price = $discount > 0 ? $mrp * (1 - $discount / 100) : $mrp;
	$line_total = $unit_price * $quantity;
	$line_mrp_total = $mrp * $quantity;
	$cart_total += $line_total;
	$cart_items[] = [
		'id' => $product_id,
		'name' => $detail['name'],
		'slug' => $detail['slug'],
		'image_url' => $detail['image_url'] ?? '',
		'category_name' => $detail['category_name'] ?? '',
		'quantity' => $quantity,
		'unit_price' => $unit_price,
		'unit_mrp' => $mrp,
		'mrp' => $mrp,
		'discount' => $discount,
		'discount_percentage' => $discount,
		'line_total' => $line_total,
		'line_mrp_total' => $line_mrp_total,
	];
}
