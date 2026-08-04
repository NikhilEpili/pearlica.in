<?php
/**
 * Razorpay webhook handler.
 * Verifies webhook signature and updates local payment/order status.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/razorpay_init.php';
require_once __DIR__ . '/../shiprocket/shiprocket_init.php';
require_once __DIR__ . '/../../lib/order.php';
require_once __DIR__ . '/../../lib/email_notifications.php';
require_once __DIR__ . '/../../lib/refrens.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	echo 'Method not allowed';
	exit;
}

$payload = file_get_contents('php://input');
$sig = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

if ($payload === false) {
	http_response_code(400);
	echo 'Bad request';
	exit;
}

if (!razorpay_verify_webhook($payload, $sig)) {
	http_response_code(400);
	echo 'Invalid signature';
	exit;
}

$data = json_decode($payload, true);
if (!is_array($data)) {
	http_response_code(400);
	echo 'Invalid payload';
	exit;
}

$event = (string) ($data['event'] ?? '');
$entity = $data['payload']['payment']['entity'] ?? null;
if (!is_array($entity)) {
	http_response_code(200);
	echo 'OK';
	exit;
}

$provider_order_id = (string) ($entity['order_id'] ?? '');
$provider_payment_id = (string) ($entity['id'] ?? '');
$status = (string) ($entity['status'] ?? '');

if ($provider_order_id === '') {
	http_response_code(200);
	echo 'OK';
	exit;
}

$pdo = $GLOBALS['pdo'];
$payment = payment_find_by_provider_order_id($pdo, $provider_order_id);
if (!$payment) {
	http_response_code(200);
	echo 'OK';
	exit;
}

$order_id = (int) ($payment['order_id'] ?? 0);
if ($order_id <= 0) {
	http_response_code(200);
	echo 'OK';
	exit;
}

// Store raw payload for audit, then update status.
$pdo->prepare('UPDATE payments SET raw_payload = ?, updated_at = NOW() WHERE id = ?')->execute([$payload, (int) $payment['id']]);

$payment_row_id = (int) ($payment['id'] ?? 0);
if ($payment_row_id <= 0) {
	http_response_code(200);
	echo 'OK';
	exit;
}

$prev_payment_status = (string) ($payment['status'] ?? '');

if ($event === 'payment.captured' || $status === 'captured') {
	payment_update_by_id($pdo, $payment_row_id, $provider_order_id, $provider_payment_id, null, 'paid');
	order_mark_paid($pdo, $order_id);
	if ($prev_payment_status !== 'paid') {
		email_notify_admin_payment_success(
			$pdo,
			$order_id,
			$provider_payment_id !== '' ? $provider_payment_id : $provider_order_id
		);
		try {
			refrens_create_invoice_for_paid_order($pdo, $order_id);
		} catch (Throwable $e) {
			refrens_safe_log('webhook.php order_id=' . $order_id . ' ' . $e->getMessage());
		}
	}
	$shiprocket_result = shiprocket_sync_order($pdo, $order_id);
	if (empty($shiprocket_result['ok'])) {
		shiprocket_safe_log('webhook.php order_id=' . $order_id . ' sync failed: ' . (string) ($shiprocket_result['error'] ?? 'unknown'));
	}
} elseif ($event === 'payment.failed' || $status === 'failed') {
	payment_update_by_id($pdo, $payment_row_id, $provider_order_id, $provider_payment_id, null, 'failed');
	order_set_status($pdo, $order_id, 'pending_payment');
	if ($prev_payment_status !== 'failed') {
		$fail_parts = array_filter([
			trim((string) ($entity['error_description'] ?? '')),
			trim((string) ($entity['error_code'] ?? '')),
			trim((string) ($entity['error_reason'] ?? '')),
		]);
		$fail_detail = $fail_parts !== [] ? implode(' — ', $fail_parts) : 'Payment failed (Razorpay).';
		email_notify_admin_payment_failed(
			$pdo,
			$order_id,
			$provider_order_id,
			$provider_payment_id !== '' ? $provider_payment_id : '—',
			$fail_detail
		);
	}
}

http_response_code(200);
echo 'OK';

