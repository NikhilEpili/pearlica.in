<?php
/**
 * Place order with Cash on Delivery (no Razorpay).
 */
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/store_user.php';
require_once __DIR__ . '/lib/store_user_address.php';
require_once __DIR__ . '/lib/product.php';
require_once __DIR__ . '/lib/order.php';
require_once __DIR__ . '/lib/promo_code.php';
require_once __DIR__ . '/includes/cart_cookie.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/includes/indian_states.php';
require_once __DIR__ . '/includes/checkout_shipping.php';
require_once __DIR__ . '/payment/shiprocket/shiprocket_init.php';
require_once __DIR__ . '/lib/email_notifications.php';
require_once __DIR__ . '/lib/refrens.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
	exit;
}

$user_id = auth_cookie_user_id();

$csrf = (string) ($_POST['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals($_SESSION['checkout_csrf_token'] ?? '', $csrf)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

if ($user_id === null) {
	http_response_code(401);
	echo json_encode(['ok' => false, 'error' => 'Please sign in to place your order.']);
	exit;
}

$cart = cart_from_cookie();
if (empty($cart)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Cart is empty']);
	exit;
}

$pdo = $GLOBALS['pdo'];
$cart = cart_prune_unavailable($pdo, $cart);
if (empty($cart)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Cart is empty']);
	exit;
}
$user = store_user_find_by_id($pdo, (int) $user_id);
if (!$user) {
	http_response_code(401);
	echo json_encode(['ok' => false, 'error' => 'Session expired. Please sign in again.']);
	exit;
}

// Prefer the address the shopper confirmed at checkout, else their default one.
$address_row = null;
$selected_address_id = isset($_SESSION['checkout_address_id']) ? (int) $_SESSION['checkout_address_id'] : 0;
if ($selected_address_id > 0) {
	$address_row = store_user_address_find($pdo, (int) $user_id, $selected_address_id);
}
if (!$address_row) {
	$address_row = store_user_address_get_default($pdo, (int) $user_id);
}
if ($address_row) {
	store_user_address_sync_profile($pdo, (int) $user_id, $address_row);
	$user = store_user_find_by_id($pdo, (int) $user_id) ?? $user;
}

$shipping_name = trim((string) ($user['full_name'] ?? ''));
$shipping_email = trim((string) ($user['email'] ?? ''));
$shipping_phone = trim((string) ($user['phone'] ?? ''));
$shipping_address_1 = trim((string) ($user['address'] ?? ''));
$shipping_address_2 = trim((string) ($user['address_line_2'] ?? ''));
$shipping_city = trim((string) ($user['city'] ?? ''));
$shipping_state = trim((string) ($user['state'] ?? ''));
$shipping_pincode = trim((string) ($user['pincode'] ?? ''));

if ($shipping_email !== '' && !filter_var($shipping_email, FILTER_VALIDATE_EMAIL)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Invalid email on your profile.']);
	exit;
}
if ($shipping_name === '') {
	$shipping_name = $shipping_email !== '' ? $shipping_email : 'Customer';
}
if ($shipping_address_1 === '' || $shipping_city === '' || $shipping_state === '' || $shipping_pincode === '') {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Please choose or add a delivery address before placing your order.']);
	exit;
}
$order_store_user_id = (int) $user_id;

$shipping_address = $shipping_address_1 . ($shipping_address_2 !== '' ? ("\n" . $shipping_address_2) : '');

$product_ids = array_keys($cart);
$details = product_get_cart_details($pdo, $product_ids);
$subtotal_paise = 0;
$items = [];
foreach ($cart as $pid => $qty) {
	$pid = (int) $pid;
	$qty = (int) $qty;
	$d = $details[$pid] ?? null;
	if (!$d || $qty <= 0) {
		continue;
	}
	$mrp = (float) ($d['mrp'] ?? 0);
	$disc = (float) ($d['discount_percentage'] ?? 0);
	$unit = $disc > 0 ? ($mrp * (1 - $disc / 100)) : $mrp;
	$unit_paise = (int) max(0, round($unit * 100));
	$line_paise = (int) max(0, $unit_paise * $qty);
	$subtotal_paise += $line_paise;
	$items[] = [
		'product_id' => $pid,
		'sku' => (string) ($d['sku'] ?? ''),
		'product_name' => (string) ($d['name'] ?? ''),
		'unit_price_paise' => $unit_paise,
		'quantity' => $qty,
		'line_total_paise' => $line_paise,
	];
}

if (empty($items) || $subtotal_paise <= 0) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Cart items invalid']);
	exit;
}

$gift_packaging_paise = 0;
if (isset($_POST['gift_packaging']) && (string) $_POST['gift_packaging'] === '1') {
	$gift_packaging_paise = defined('CHECKOUT_GIFT_PACKAGING_PAISE') ? (int) CHECKOUT_GIFT_PACKAGING_PAISE : 3000;
	if ($gift_packaging_paise < 0) {
		$gift_packaging_paise = 0;
	}
}
$discount_paise = 0;
$promo_code_id = null;
$promo_code_raw = trim((string) ($_POST['promo_code'] ?? ''));
if ($promo_code_raw !== '') {
	$validation = promo_code_validate($pdo, $promo_code_raw, $subtotal_paise);
	if (!empty($validation['valid'])) {
		$discount_paise = (int) $validation['discount_paise'];
		$promo_code_id = (int) $validation['promo_id'];
	}
}
$merch_net_paise = checkout_merchandise_net_paise($subtotal_paise, $discount_paise);
$delivery_fee_paise = checkout_delivery_fee_paise($merch_net_paise);
$shipping_paise = $delivery_fee_paise + $gift_packaging_paise;
$total_paise = (int) max(0, $subtotal_paise + $shipping_paise - $discount_paise);

if ($total_paise > 0 && $total_paise < 100) {
	http_response_code(400);
	echo json_encode([
		'ok' => false,
		'error' => 'Order amount is below the minimum (₹1). Please adjust your cart or promo.',
	], JSON_UNESCAPED_UNICODE);
	exit;
}
if ($total_paise <= 0) {
	http_response_code(400);
	echo json_encode([
		'ok' => false,
		'error' => 'Order amount is ₹0. Please remove the promo or add more items.',
		'total_paise' => $total_paise,
	], JSON_UNESCAPED_UNICODE);
	exit;
}

$cod_max_paise = (int) max(0, (int) CHECKOUT_COD_MAX_ORDER_RUPEES * 100);
if ($total_paise > $cod_max_paise) {
	http_response_code(400);
	echo json_encode([
		'ok' => false,
		'error' => 'Cash on Delivery is only available for orders up to ₹' . number_format(CHECKOUT_COD_MAX_ORDER_RUPEES, 0) . '.',
	], JSON_UNESCAPED_UNICODE);
	exit;
}

$pdo->beginTransaction();
try {
	$order_data = [
		'store_user_id' => $order_store_user_id,
		'status' => 'cod_pending',
		'payment_method' => 'cod',
		'subtotal_paise' => $subtotal_paise,
		'shipping_paise' => $shipping_paise,
		'discount_paise' => $discount_paise,
		'total_paise' => $total_paise,
		'shipping_name' => $shipping_name,
		'shipping_phone' => $shipping_phone,
		'shipping_email' => $shipping_email,
		'shipping_address' => $shipping_address,
		'shipping_city' => $shipping_city,
		'shipping_state' => $shipping_state,
		'shipping_pincode' => $shipping_pincode,
	];
	if ($promo_code_id !== null) {
		$order_data['promo_code_id'] = $promo_code_id;
	}
	$order_id = order_create($pdo, $order_data, $items);
	if ($promo_code_id !== null) {
		promo_code_increment_used($pdo, $promo_code_id);
	}

	$order = order_find_by_id($pdo, $order_id);
	if (!$order) {
		throw new RuntimeException('Order create failed');
	}

	payment_create($pdo, $order_id, [
		'provider' => 'cod',
		'status' => 'pending',
		'amount_paise' => $total_paise,
		'currency' => 'INR',
		'provider_order_id' => 'COD-' . (string) ($order['order_number'] ?? (string) $order_id),
	]);

	$pdo->commit();

	email_notify_admin_order_placed($pdo, $order_id);

	try {
		// COD: create tax invoice now; leave unpaid until cash is collected.
		refrens_sync_invoice_for_order($pdo, $order_id, false);
	} catch (Throwable $e) {
		refrens_safe_log('checkout-place-cod.php order_id=' . $order_id . ' ' . $e->getMessage());
	}

	$shiprocket_result = shiprocket_sync_order($pdo, $order_id);
	if (empty($shiprocket_result['ok'])) {
		shiprocket_safe_log('checkout-place-cod.php order_id=' . $order_id . ' sync failed: ' . (string) ($shiprocket_result['error'] ?? 'unknown'));
	}

	cart_to_cookie([]);

	echo json_encode(['ok' => true, 'order_id' => $order_id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	http_response_code(500);
	$msg = 'Unable to place your order. Please try again.';
	if (defined('APP_DEBUG') && APP_DEBUG) {
		$msg = $msg . ' ' . $e->getMessage();
	}
	echo json_encode(['ok' => false, 'error' => $msg]);
}
