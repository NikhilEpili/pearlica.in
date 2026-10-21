<?php
/**
 * COD mobile verification for guest checkout (TeleSign SMS OTP).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/telesign.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/includes/login_telesign_session.php';
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
	echo json_encode(['ok' => true, 'skipped' => true, 'reason' => 'signed_in']);
	exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
$ts_cfg = telesign_config();
$login_otp_len = telesign_login_otp_digit_count();

if ($action === 'send') {
	$guest = checkout_guest_get();
	if (!checkout_guest_is_complete($guest)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Please fill in delivery details first.']);
		exit;
	}
	$e164 = (string) ($guest['phone_e164'] ?? '');
	$send = login_telesign_send_or_fail($e164, $ts_cfg, $login_otp_len);
	if (empty($send['ok'])) {
		$payload = ['ok' => false, 'error' => (string) ($send['error'] ?? 'Unable to send code.')];
		if (isset($send['wait_seconds'])) {
			$payload['wait_seconds'] = (int) $send['wait_seconds'];
		}
		http_response_code(400);
		echo json_encode($payload);
		exit;
	}
	echo json_encode([
		'ok' => true,
		'phone_mask' => $send['phone_mask'] ?? login_phone_mask_last4($e164),
		'expires_at' => (int) ($send['expires_at'] ?? 0),
		'otp_length' => (int) ($send['otp_length'] ?? $login_otp_len),
	], JSON_UNESCAPED_UNICODE);
	exit;
}

if ($action === 'verify') {
	$guest = checkout_guest_get();
	if (!checkout_guest_is_complete($guest)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Please fill in delivery details first.']);
		exit;
	}
	$expected = preg_replace('/\D+/', '', (string) ($guest['phone_e164'] ?? ''));
	$verify = login_telesign_verify_or_fail((string) ($_POST['otp'] ?? ''), $login_otp_len);
	if (empty($verify['ok'])) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => (string) ($verify['error'] ?? 'Invalid code.')]);
		exit;
	}
	$verified = preg_replace('/\D+/', '', (string) ($verify['e164'] ?? ''));
	if ($expected === '' || $verified === '' || !hash_equals($expected, $verified)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'This code does not match your delivery mobile number.']);
		exit;
	}
	checkout_guest_mark_cod_otp_verified($expected);
	echo json_encode(['ok' => true, 'verified' => true], JSON_UNESCAPED_UNICODE);
	exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
