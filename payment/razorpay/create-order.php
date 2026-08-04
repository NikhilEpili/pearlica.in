<?php
/**
 * Create a local order + Razorpay order.
 * Returns JSON for Razorpay Checkout.
 */
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/razorpay_init.php';
require_once __DIR__ . '/../../lib/store_user.php';
require_once __DIR__ . '/../../lib/product.php';
require_once __DIR__ . '/../../lib/order.php';
require_once __DIR__ . '/../../lib/promo_code.php';
require_once __DIR__ . '/../../includes/cart_cookie.php';
require_once __DIR__ . '/../../includes/auth_cookie.php';
require_once __DIR__ . '/../../includes/indian_states.php';
require_once __DIR__ . '/../../includes/checkout_shipping.php';

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
	exit;
}

$user_id = auth_cookie_user_id();
$guest_requested = isset($_POST['guest_checkout']) && (string) $_POST['guest_checkout'] === '1';
if ($user_id !== null) {
	$guest_requested = false;
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals($_SESSION['checkout_csrf_token'] ?? '', $csrf)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

if ($user_id === null && !$guest_requested) {
	http_response_code(401);
	echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
	exit;
}

$payment_method = trim((string) ($_POST['payment_method'] ?? 'razorpay'));
if ($payment_method !== 'razorpay') {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Invalid payment method']);
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
$order_store_user_id = null;

if ($guest_requested) {
	$states = indian_states_list();
	$shipping_name = trim((string) ($_POST['shipping_name'] ?? ''));
	$shipping_email = trim((string) ($_POST['shipping_email'] ?? ''));
	$shipping_phone_raw = trim((string) ($_POST['shipping_phone'] ?? ''));
	$shipping_address_1 = trim((string) ($_POST['shipping_address'] ?? ''));
	$shipping_address_2 = trim((string) ($_POST['shipping_address_line_2'] ?? ''));
	$shipping_city = trim((string) ($_POST['shipping_city'] ?? ''));
	$shipping_state = trim((string) ($_POST['shipping_state'] ?? ''));
	$shipping_pincode = trim((string) ($_POST['shipping_pincode'] ?? ''));

	if ($shipping_name === '' || mb_strlen($shipping_name) > 200) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Please enter your full name.']);
		exit;
	}
	if ($shipping_email === '' || !filter_var($shipping_email, FILTER_VALIDATE_EMAIL)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
		exit;
	}
	$e164 = store_user_phone_e164_digits($shipping_phone_raw);
	if ($e164 === null) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Please enter a valid Indian mobile number.']);
		exit;
	}
	$shipping_phone = '+91' . substr($e164, 2);
	if ($shipping_address_1 === '' || mb_strlen($shipping_address_1) > 500) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Please enter address line 1.']);
		exit;
	}
	if (mb_strlen($shipping_address_2) > 500) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Address line 2 is too long.']);
		exit;
	}
	if ($shipping_city === '' || mb_strlen($shipping_city) > 120) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Please enter city.']);
		exit;
	}
	if (!in_array($shipping_state, $states, true)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Please select a valid state.']);
		exit;
	}
	if (!preg_match('/^\d{6}$/', $shipping_pincode)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Please enter a valid 6-digit pincode.']);
		exit;
	}
	$order_store_user_id = null;
} else {
	$user = store_user_find_by_id($pdo, (int) $user_id);
	if (!$user) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'User not found']);
		exit;
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
		echo json_encode(['ok' => false, 'error' => 'Please add your shipping address in Profile before checkout.']);
		exit;
	}
	$order_store_user_id = (int) $user_id;
}

$shipping_address = $shipping_address_1 . ($shipping_address_2 !== '' ? ("\n" . $shipping_address_2) : '');

// Calculate totals from live product data.
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
// `shipping_paise` stores delivery + gift packaging (no separate columns).
$shipping_paise = $delivery_fee_paise + $gift_packaging_paise;
$total_paise = (int) max(0, $subtotal_paise + $shipping_paise - $discount_paise);

// Razorpay enforces a minimum order amount (₹1 / 100 paise for INR).
// Prevent creating local orders that can never be paid.
if ($total_paise > 0 && $total_paise < 100) {
	http_response_code(400);
	echo json_encode([
		'ok' => false,
		'error' => 'Order amount less than minimum amount allowed (₹1). Please remove the promo/discount or add more items.',
		'min_paise' => 100,
		'total_paise' => $total_paise,
	], JSON_UNESCAPED_UNICODE);
	exit;
}
if ($total_paise <= 0) {
	http_response_code(400);
	echo json_encode([
		'ok' => false,
		'error' => 'Order amount is ₹0. Please remove the promo/discount or add more items.',
		'total_paise' => $total_paise,
	], JSON_UNESCAPED_UNICODE);
	exit;
}

// Create local order + payment record.
$pdo->beginTransaction();
try {
	$order_data = [
		'store_user_id' => $order_store_user_id,
		'status' => 'pending_payment',
		'payment_method' => 'razorpay',
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

	$cfg = razorpay_config();
	$rp = razorpay_create_order($total_paise, (string) $cfg['currency'], (string) $order['order_number']);
	if (empty($rp['ok'])) {
		throw new RuntimeException((string) ($rp['error'] ?? 'Unable to create payment'));
	}
	$rp_data = is_array($rp['data'] ?? null) ? $rp['data'] : [];
	$rp_order_id = (string) ($rp_data['id'] ?? '');
	if ($rp_order_id === '') {
		throw new RuntimeException('Invalid Razorpay response');
	}
	// Checkout must use the same amount/currency Razorpay stored on the order (avoids "Invalid Token").
	$checkout_amount_paise = (int) ($rp_data['amount'] ?? $total_paise);
	$checkout_currency = strtoupper((string) ($rp_data['currency'] ?? $cfg['currency']));
	if ($checkout_amount_paise <= 0) {
		$checkout_amount_paise = $total_paise;
	}

	payment_create($pdo, $order_id, [
		'provider' => 'razorpay',
		'status' => 'created',
		'amount_paise' => $total_paise,
		'currency' => (string) $cfg['currency'],
		'provider_order_id' => $rp_order_id,
	]);

	$pdo->commit();

	require_once __DIR__ . '/../../lib/email_notifications.php';
	email_notify_admin_order_placed($pdo, $order_id);

	// One-time nonce for verify.php (works even if checkout CSRF drifts; still requires same session).
	$verify_nonce = bin2hex(random_bytes(32));
	if (!isset($_SESSION['razorpay_verify_nonces']) || !is_array($_SESSION['razorpay_verify_nonces'])) {
		$_SESSION['razorpay_verify_nonces'] = [];
	}
	$_SESSION['razorpay_verify_nonces'][$rp_order_id] = $verify_nonce;
	$nonce_keys = array_keys($_SESSION['razorpay_verify_nonces']);
	if (count($nonce_keys) > 40) {
		foreach (array_slice($nonce_keys, 0, -40) as $old_k) {
			unset($_SESSION['razorpay_verify_nonces'][$old_k]);
		}
	}

	echo json_encode([
		'ok' => true,
		'key_id' => (string) $cfg['key_id'],
		'razorpay_order_id' => $rp_order_id,
		'amount' => $checkout_amount_paise,
		'currency' => $checkout_currency,
		'verify_nonce' => $verify_nonce,
		'prefill' => razorpay_checkout_prefill($shipping_name, $shipping_email, $shipping_phone),
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	http_response_code(500);
	$msg = 'Unable to start payment. Please try again.';
	$em = $e->getMessage();
	if ($guest_requested && strpos($em, 'store_user_id') !== false && strpos($em, 'cannot be null') !== false) {
		$msg = 'Guest checkout needs a one-time database update: run sql/guest_checkout_telesign.sql (ALTER orders.store_user_id to allow NULL), then try again.';
	} elseif (defined('APP_DEBUG') && APP_DEBUG) {
		$msg = $msg . ' ' . $em;
	}
	echo json_encode(['ok' => false, 'error' => $msg]);
}

