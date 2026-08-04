<?php
/**
 * Validate and apply promo code. POST: code, subtotal_paise, csrf_token.
 * Only one promo may be active per cart; applying a new code replaces the previous one.
 * Returns JSON: { ok, discount_paise?, message?, promo_id?, promo_code? } or { ok: false, error }.
 */
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/promo_code.php';
require_once __DIR__ . '/includes/auth_cookie.php';

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
if ($user_id === null) {
	http_response_code(401);
	echo json_encode(['ok' => false, 'error' => 'Please sign in to use a promo code']);
	exit;
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals($_SESSION['checkout_csrf_token'] ?? '', $csrf)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

$code = trim((string) ($_POST['code'] ?? ''));
$subtotal_paise = (int) ($_POST['subtotal_paise'] ?? 0);
if ($code === '') {
	echo json_encode(['ok' => false, 'error' => 'Enter a promo code']);
	exit;
}
if ($subtotal_paise <= 0) {
	echo json_encode(['ok' => false, 'error' => 'Invalid cart total']);
	exit;
}

$pdo = $GLOBALS['pdo'];
$result = promo_code_validate($pdo, $code, $subtotal_paise);
if (!empty($result['valid'])) {
	$_SESSION['cart_promo'] = [
		'code' => (string) $result['promo_code'],
		'promo_id' => (int) $result['promo_id'],
		'discount_paise' => (int) $result['discount_paise'],
	];
	echo json_encode([
		'ok' => true,
		'discount_paise' => (int) $result['discount_paise'],
		'message' => (string) ($result['message'] ?? 'Discount applied'),
		'promo_id' => (int) $result['promo_id'],
		'promo_code' => (string) $result['promo_code'],
	]);
	exit;
}

echo json_encode([
	'ok' => false,
	'error' => (string) ($result['message'] ?? 'This promo code is not valid'),
]);
