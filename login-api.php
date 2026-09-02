<?php
/**
 * Storefront auth API: SMS OTP login and registration.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/store_user.php';
require_once __DIR__ . '/lib/telesign.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/includes/login_csrf.php';
require_once __DIR__ . '/includes/login_telesign_session.php';
require_once __DIR__ . '/includes/store_profile_validation.php';
require_once __DIR__ . '/includes/store_base_path.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('login_api_json')) {
	function login_api_json(array $payload, int $status = 200): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		http_response_code($status);
		echo json_encode($payload);
		exit;
	}
}

if (!function_exists('login_api_auth_response')) {
	function login_api_auth_response(PDO $pdo, array $user, string $return_url, string $base_path): void
	{
		$redirect = $return_url !== '' ? $return_url : 'profile';
		if ($redirect !== '' && $redirect[0] !== '/') {
			$redirect = $base_path . ltrim($redirect, '/');
		}
		login_api_json([
			'ok' => true,
			'redirect' => $redirect,
			'registration_complete' => store_user_registration_complete($user),
			'shipping_complete' => store_user_shipping_complete($user, $pdo),
			'profile' => store_user_profile_for_client($user, $pdo),
		]);
	}
}

if (!function_exists('login_api_clear_pending_login')) {
	function login_api_clear_pending_login(): void
	{
		unset($_SESSION['pending_login_user_id'], $_SESSION['pending_login_phone_e164']);
	}
}

if (!function_exists('login_api_pending_login_user')) {
	function login_api_pending_login_user(PDO $pdo): ?array
	{
		$user_id = isset($_SESSION['pending_login_user_id']) ? (int) $_SESSION['pending_login_user_id'] : 0;
		if ($user_id <= 0) {
			return null;
		}
		$user = store_user_find_by_id($pdo, $user_id);
		if (!$user) {
			login_api_clear_pending_login();

			return null;
		}

		return $user;
	}
}

if (!function_exists('login_api_send_login_otp')) {
	/**
	 * @return array{ok:bool,error?:string,wait_seconds?:int,phone_mask?:string,expires_at?:int,resend_at?:int,otp_length?:int}
	 */
	function login_api_send_login_otp(PDO $pdo, array $user, array $ts_cfg, int $login_otp_len): array
	{
		$e164 = store_user_phone_e164_digits((string) ($user['phone'] ?? ''));
		if ($e164 === null && !empty($_SESSION['pending_login_phone_e164'])) {
			$e164 = store_user_phone_e164_digits((string) $_SESSION['pending_login_phone_e164']);
		}
		if ($e164 === null) {
			return ['ok' => false, 'error' => 'Session expired. Please start again.', 'step' => 'login'];
		}
		$send = login_telesign_send_or_fail($e164, $ts_cfg, $login_otp_len);
		if (empty($send['ok'])) {
			$payload = ['ok' => false, 'error' => (string) ($send['error'] ?? 'Unable to send code.')];
			if (isset($send['wait_seconds'])) {
				$payload['wait_seconds'] = (int) $send['wait_seconds'];
			}

			return $payload;
		}
		$_SESSION['pending_login_user_id'] = (int) $user['id'];
		$_SESSION['pending_login_phone_e164'] = $e164;

		return [
			'ok' => true,
			'step' => 'login_verify',
			'phone_mask' => $send['phone_mask'] ?? login_phone_mask_last4($e164),
			'expires_at' => (int) ($send['expires_at'] ?? 0),
			'resend_at' => time() + (OTP_RATE_LIMIT_MINUTES * 60),
			'otp_length' => (int) ($send['otp_length'] ?? $login_otp_len),
		];
	}
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	login_api_json(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!login_validate_csrf()) {
	if (defined('APP_DEBUG') && APP_DEBUG) {
		error_log(
			'Pearlica login-api CSRF rejected: session_active='
			. (session_status() === PHP_SESSION_ACTIVE ? '1' : '0')
			. ' posted_token=' . (isset($_POST['csrf_token']) && (string) $_POST['csrf_token'] !== '' ? '1' : '0')
			. ' session_token=' . (!empty($_SESSION['login_csrf_token']) ? '1' : '0')
		);
	}
	login_api_json(['ok' => false, 'error' => 'Invalid request. Please refresh and try again.']);
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$pdo = $GLOBALS['pdo'];
$ts_cfg = telesign_config();
$login_otp_len = telesign_login_otp_digit_count();
$return_url = isset($_POST['return_url']) ? trim((string) $_POST['return_url']) : 'profile';
if ($return_url !== '' && strpos($return_url, '://') !== false) {
	$return_url = 'profile';
}
$base_path = store_base_path_from_request();
$guest_actions = ['login_send_otp', 'login_resend_otp', 'login_verify', 'register_send_otp', 'register_verify'];

if ($pdo instanceof PDO) {
	try {
		store_user_ensure_schema($pdo);
		require_once __DIR__ . '/lib/store_user_address.php';
		store_user_address_ensure_schema($pdo);
	} catch (Throwable $e) {
		error_log('Pearlica login-api schema: ' . $e->getMessage());
	}
}

if (auth_cookie_user_id() !== null && in_array($action, $guest_actions, true)) {
	login_api_json(['ok' => true, 'already_logged_in' => true]);
}

if ($action === 'login_send_otp') {
	$phone_raw = trim((string) ($_POST['phone'] ?? ''));
	$e164 = store_user_phone_e164_digits($phone_raw);
	if ($e164 === null) {
		login_api_json(['ok' => false, 'error' => 'Please enter a valid 10-digit mobile number.']);
	}
	$user = store_user_find_by_phone_e164($pdo, $e164);
	if (!$user) {
		login_api_json([
			'ok' => false,
			'error' => 'No account found with this number. Please create an account.',
			'needs_register' => true,
		]);
	}
	$send = login_api_send_login_otp($pdo, $user, $ts_cfg, $login_otp_len);
	if (empty($send['ok'])) {
		$payload = ['ok' => false, 'error' => (string) ($send['error'] ?? 'Unable to send code.')];
		if (isset($send['wait_seconds'])) {
			$payload['wait_seconds'] = (int) $send['wait_seconds'];
		}
		if (isset($send['step'])) {
			$payload['step'] = $send['step'];
		}
		login_api_json($payload);
	}
	login_api_json($send);
}

if ($action === 'login_resend_otp') {
	$user = login_api_pending_login_user($pdo);
	if (!$user) {
		login_api_json(['ok' => false, 'error' => 'Session expired. Please start again.', 'step' => 'login']);
	}
	$send = login_api_send_login_otp($pdo, $user, $ts_cfg, $login_otp_len);
	if (empty($send['ok'])) {
		$payload = ['ok' => false, 'error' => (string) ($send['error'] ?? 'Unable to send code.')];
		if (isset($send['wait_seconds'])) {
			$payload['wait_seconds'] = (int) $send['wait_seconds'];
		}
		if (isset($send['step'])) {
			$payload['step'] = $send['step'];
		}
		login_api_json($payload);
	}
	login_api_json($send);
}

if ($action === 'login_verify') {
	$user = login_api_pending_login_user($pdo);
	if (!$user) {
		login_api_json(['ok' => false, 'error' => 'Session expired. Please start again.', 'step' => 'login']);
	}
	$verify = login_telesign_verify_or_fail((string) ($_POST['otp'] ?? ''), $login_otp_len);
	if (empty($verify['ok'])) {
		login_api_json(['ok' => false, 'error' => (string) ($verify['error'] ?? 'Invalid code.')]);
	}
	$user_id = (int) $user['id'];
	login_api_clear_pending_login();
	session_regenerate_id(true);
	auth_cookie_set($user_id);
	$user = store_user_find_by_id($pdo, $user_id);
	login_api_auth_response($pdo, $user, $return_url, $base_path);
}

if ($action === 'register_send_otp') {
	$result = store_profile_validate_new_registration($pdo, $_POST);
	if (!$result['ok']) {
		login_api_json(['ok' => false, 'error' => $result['errors'][0] ?? 'Please check your details.']);
	}
	$e164 = (string) ($result['data']['phone_e164'] ?? '');
	$send = login_telesign_send_or_fail($e164, $ts_cfg, $login_otp_len);
	if (empty($send['ok'])) {
		login_api_json(['ok' => false, 'error' => (string) ($send['error'] ?? 'Unable to send code.')]);
	}
	$_SESSION['pending_register'] = [
		'full_name' => (string) $result['data']['full_name'],
		'email' => (string) $result['data']['email'],
		'birthday' => $result['data']['birthday'],
		'gender' => (string) $result['data']['gender'],
		'phone_display' => (string) $result['data']['phone_display'],
		'phone_e164' => $e164,
	];
	login_api_json([
		'ok' => true,
		'step' => 'register_verify',
		'phone_mask' => $send['phone_mask'] ?? login_phone_mask_last4($e164),
		'expires_at' => (int) ($send['expires_at'] ?? 0),
		'otp_length' => (int) ($send['otp_length'] ?? $login_otp_len),
	]);
}

if ($action === 'register_verify') {
	$pending = isset($_SESSION['pending_register']) && is_array($_SESSION['pending_register'])
		? $_SESSION['pending_register']
		: null;
	if (!$pending) {
		login_api_json(['ok' => false, 'error' => 'Registration session expired. Please start again.', 'step' => 'register']);
	}
	$verify = login_telesign_verify_or_fail((string) ($_POST['otp'] ?? ''), $login_otp_len);
	if (empty($verify['ok'])) {
		login_api_json(['ok' => false, 'error' => (string) ($verify['error'] ?? 'Invalid code.')]);
	}
	$e164 = (string) ($pending['phone_e164'] ?? '');
	if ($e164 === '' || store_user_phone_taken($pdo, $e164)) {
		unset($_SESSION['pending_register']);
		login_api_json(['ok' => false, 'error' => 'This mobile number is already registered.', 'step' => 'register']);
	}
	try {
		store_user_ensure_schema($pdo);
		$user_id = store_user_create_registered($pdo, [
			'full_name' => (string) ($pending['full_name'] ?? ''),
			'email' => (string) ($pending['email'] ?? ''),
			'phone_display' => (string) ($pending['phone_display'] ?? ''),
			'birthday' => $pending['birthday'] ?? null,
			'gender' => (string) ($pending['gender'] ?? ''),
		]);
	} catch (Throwable $e) {
		error_log('Pearlica register create: ' . $e->getMessage());
		$duplicate = stripos($e->getMessage(), 'Duplicate') !== false;
		login_api_json([
			'ok' => false,
			'error' => $duplicate
				? 'An account with this email already exists. Please sign in instead.'
				: 'Could not create your account. Please try again.',
		]);
	}
	unset($_SESSION['pending_register']);
	session_regenerate_id(true);
	auth_cookie_set($user_id);
	$user = store_user_find_by_id($pdo, $user_id);
	login_api_auth_response($pdo, $user, $return_url, $base_path);
}

login_api_json(['ok' => false, 'error' => 'Unknown action.'], 400);
