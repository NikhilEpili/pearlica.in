<?php
/**
 * Clear applied cart promo (single code). POST: csrf_token.
 */
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_cookie.php';

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
	exit;
}

if (auth_cookie_user_id() === null) {
	http_response_code(401);
	echo json_encode(['ok' => false, 'error' => 'Please sign in']);
	exit;
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals($_SESSION['checkout_csrf_token'] ?? '', $csrf)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

unset($_SESSION['cart_promo']);
echo json_encode(['ok' => true]);
