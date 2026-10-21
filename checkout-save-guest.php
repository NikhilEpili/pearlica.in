<?php
/**
 * Save guest delivery details for checkout (session only, no login).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/store_user.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/includes/checkout_guest.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
	exit;
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals($_SESSION['checkout_csrf_token'] ?? '', $csrf)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

if (auth_cookie_user_id() !== null) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'You are already signed in.']);
	exit;
}

$pdo = $GLOBALS['pdo'];
store_user_ensure_schema($pdo);
$result = checkout_guest_validate_and_store($pdo, $_POST);
if (empty($result['ok'])) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'Please check your details.')]);
	exit;
}

echo json_encode(['ok' => true, 'shipping_complete' => true], JSON_UNESCAPED_UNICODE);
