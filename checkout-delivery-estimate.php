<?php
/**
 * JSON: delivery ETA for a PIN via Shiprocket serviceability.
 * POST: csrf_token, pincode (6 digits). Valid checkout_csrf_token session (checkout or product page).
 * Same URL style as checkout-apply-promo.php (root = reliable rewrite).
 */
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/payment/shiprocket/shiprocket_init.php';
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

$csrf = (string) ($_POST['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals($_SESSION['checkout_csrf_token'] ?? '', $csrf)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'error' => 'Invalid request. Refresh the page and try again.']);
	exit;
}

$pin = preg_replace('/\D+/', '', trim((string) ($_POST['pincode'] ?? '')));
if (strlen($pin) !== 6) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Enter a valid 6-digit PIN code.']);
	exit;
}

$cfg = shiprocket_config();
if (empty($cfg['enabled'])) {
	echo json_encode([
		'ok' => false,
		'message' => 'Delivery estimates are turned off right now. You can still place your order.',
		'min_days' => null,
		'max_days' => null,
	]);
	exit;
}
$pickupPc = preg_replace('/\D+/', '', (string) ($cfg['pickup_postcode'] ?? ''));
if (strlen($pickupPc) !== 6) {
	echo json_encode([
		'ok' => false,
		'message' => 'Warehouse PIN is not configured for estimates. You can still place your order.',
		'min_days' => null,
		'max_days' => null,
	]);
	exit;
}

$eta = shiprocket_estimate_delivery_for_pincode($pin);
$message = trim((string) ($eta['message'] ?? ''));
if ($message === '') {
	$message = 'We could not load a delivery estimate for this PIN code right now. You can still place your order.';
}

echo json_encode([
	'ok' => !empty($eta['ok']),
	'message' => $message,
	'min_days' => $eta['min_days'] ?? null,
	'max_days' => $eta['max_days'] ?? null,
]);
