<?php
/**
 * Verify Razorpay payment signature and mark order paid.
 * Accepts POST from frontend after payment success.
 */
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/razorpay_init.php';
require_once __DIR__ . '/../shiprocket/shiprocket_init.php';
require_once __DIR__ . '/../../lib/order.php';
require_once __DIR__ . '/../../lib/email_notifications.php';
require_once __DIR__ . '/../../lib/refrens.php';
require_once __DIR__ . '/../../includes/cart_cookie.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
	exit;
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
$provider_order_id = trim((string) ($_POST['razorpay_order_id'] ?? ''));
$verify_nonce = (string) ($_POST['verify_nonce'] ?? '');
$session_nonces = $_SESSION['razorpay_verify_nonces'] ?? [];
if (!is_array($session_nonces)) {
	$session_nonces = [];
}
$expected_nonce = $provider_order_id !== '' && isset($session_nonces[$provider_order_id]) ? (string) $session_nonces[$provider_order_id] : '';
$nonce_ok = $verify_nonce !== '' && $expected_nonce !== '' && hash_equals($expected_nonce, $verify_nonce);
$csrf_ok = $csrf !== '' && hash_equals($_SESSION['checkout_csrf_token'] ?? '', $csrf);
if (!$nonce_ok && !$csrf_ok) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

$provider_payment_id = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
$signature = trim((string) ($_POST['razorpay_signature'] ?? ''));

if ($provider_order_id === '' || $provider_payment_id === '' || $signature === '') {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Missing payment details']);
	exit;
}

if (!razorpay_verify_signature($provider_order_id, $provider_payment_id, $signature)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Payment verification failed']);
	exit;
}

try {
	$pdo = $GLOBALS['pdo'];
	$payment = payment_find_by_provider_order_id($pdo, $provider_order_id);
	if (!$payment) {
		http_response_code(404);
		echo json_encode(['ok' => false, 'error' => 'Payment not found']);
		exit;
	}

	$order_id = (int) ($payment['order_id'] ?? 0);
	if ($order_id <= 0) {
		http_response_code(404);
		echo json_encode(['ok' => false, 'error' => 'Order not found']);
		exit;
	}

	$prev_payment_status = (string) ($payment['status'] ?? '');
	// Idempotent update by payment row id (reliable on all MySQL charset/column types).
	payment_update_by_id($pdo, (int) $payment['id'], $provider_order_id, $provider_payment_id, $signature, 'paid');
	order_mark_paid($pdo, $order_id);
	if ($prev_payment_status !== 'paid') {
		email_notify_admin_payment_success($pdo, $order_id, $provider_payment_id);
		try {
			refrens_create_invoice_for_paid_order($pdo, $order_id);
		} catch (Throwable $e) {
			refrens_safe_log('verify.php order_id=' . $order_id . ' ' . $e->getMessage());
		}
	}
	$shiprocket_result = shiprocket_sync_order($pdo, $order_id);
	if (empty($shiprocket_result['ok'])) {
		shiprocket_safe_log('verify.php order_id=' . $order_id . ' sync failed: ' . (string) ($shiprocket_result['error'] ?? 'unknown'));
	}

	if (isset($_SESSION['razorpay_verify_nonces']) && is_array($_SESSION['razorpay_verify_nonces'])) {
		unset($_SESSION['razorpay_verify_nonces'][$provider_order_id]);
	}

	// Clear cart after successful payment.
	cart_to_cookie([]);
	order_success_grant($order_id);

	echo json_encode(['ok' => true, 'order_id' => $order_id]);
} catch (Throwable $e) {
	http_response_code(500);
	$msg = 'Could not complete payment confirmation.';
	if (defined('APP_DEBUG') && APP_DEBUG) {
		$msg .= ' ' . $e->getMessage();
	}
	echo json_encode(['ok' => false, 'error' => $msg]);
}

