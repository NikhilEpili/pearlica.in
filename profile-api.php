<?php
/**
 * JSON profile API: addresses and checkout shipping selection.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/store_user.php';
require_once __DIR__ . '/lib/store_user_address.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/includes/login_csrf.php';
require_once __DIR__ . '/includes/store_profile_validation.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('profile_api_json')) {
	function profile_api_json(array $payload, int $status = 200): void
	{
		http_response_code($status);
		echo json_encode($payload);
		exit;
	}
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	profile_api_json(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$user_id = auth_cookie_user_id();
if ($user_id === null) {
	profile_api_json(['ok' => false, 'error' => 'Please sign in first.'], 401);
}

if (!login_validate_csrf()) {
	if (defined('APP_DEBUG') && APP_DEBUG) {
		error_log(
			'Pearlica profile-api CSRF rejected: session_active='
			. (session_status() === PHP_SESSION_ACTIVE ? '1' : '0')
			. ' posted_token=' . (isset($_POST['csrf_token']) && (string) $_POST['csrf_token'] !== '' ? '1' : '0')
			. ' session_token=' . (!empty($_SESSION['login_csrf_token']) ? '1' : '0')
		);
	}
	profile_api_json(['ok' => false, 'error' => 'Invalid request. Please refresh and try again.']);
}

$pdo = $GLOBALS['pdo'];
$user = store_user_find_by_id($pdo, (int) $user_id);
if (!$user) {
	auth_cookie_clear();
	profile_api_json(['ok' => false, 'error' => 'Session expired. Please sign in again.'], 401);
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

if ($action === 'status') {
	$addresses = array_map('store_user_address_for_client', store_user_address_list($pdo, (int) $user_id));
	profile_api_json([
		'ok' => true,
		'profile' => store_user_profile_for_client($user, $pdo),
		'registration_complete' => store_user_registration_complete($user),
		'shipping_complete' => store_user_shipping_complete($user, $pdo),
		'addresses' => $addresses,
		'checkout_address_id' => (int) ($_SESSION['checkout_address_id'] ?? 0),
	]);
}

if ($action === 'list_addresses') {
	$addresses = array_map('store_user_address_for_client', store_user_address_list($pdo, (int) $user_id));
	profile_api_json(['ok' => true, 'addresses' => $addresses]);
}

if ($action === 'checkout_select_address') {
	$address_id = isset($_POST['address_id']) ? (int) $_POST['address_id'] : 0;
	if ($address_id <= 0) {
		profile_api_json(['ok' => false, 'error' => 'Please select a delivery address.']);
	}
	$row = store_user_address_find($pdo, (int) $user_id, $address_id);
	if (!$row) {
		profile_api_json(['ok' => false, 'error' => 'Address not found.']);
	}
	store_user_address_sync_profile($pdo, (int) $user_id, $row);
	$_SESSION['checkout_address_id'] = $address_id;
	$user = store_user_find_by_id($pdo, (int) $user_id);
	profile_api_json([
		'ok' => true,
		'message' => 'Delivery address selected.',
		'address' => store_user_address_for_client($row),
		'shipping_complete' => store_user_shipping_complete($user, $pdo),
	]);
}

if ($action === 'checkout_save_address') {
	$result = store_profile_validate_shipping($_POST);
	if (!$result['ok']) {
		profile_api_json(['ok' => false, 'error' => $result['errors'][0] ?? 'Please check your address.']);
	}
	$save_for_later = isset($_POST['save_for_later']) && (string) $_POST['save_for_later'] === '1';
	$label = trim((string) ($_POST['label'] ?? ''));
	$data = $result['data'];
	$data['full_name'] = trim((string) ($user['full_name'] ?? ''));
	$data['phone'] = trim((string) ($user['phone'] ?? ''));
	$data['label'] = $label !== '' ? $label : 'Home';
	$address_id = 0;
	if ($save_for_later) {
		try {
			$address_id = store_user_address_create($pdo, (int) $user_id, $data, true);
		} catch (Throwable $e) {
			profile_api_json(['ok' => false, 'error' => 'Could not save your address. Please try again.']);
		}
		$row = store_user_address_find($pdo, (int) $user_id, $address_id);
	} else {
		$row = array_merge(['id' => 0, 'label' => $data['label'], 'full_name' => $data['full_name'], 'phone' => $data['phone']], $data);
		store_user_address_sync_profile($pdo, (int) $user_id, $row);
	}
	if ($row) {
		if ($address_id > 0) {
			$_SESSION['checkout_address_id'] = $address_id;
		} else {
			unset($_SESSION['checkout_address_id']);
		}
		store_user_address_sync_profile($pdo, (int) $user_id, $row);
	}
	$user = store_user_find_by_id($pdo, (int) $user_id);
	profile_api_json([
		'ok' => true,
		'message' => $save_for_later ? 'Address saved for next time.' : 'Address confirmed for this order.',
		'address' => $row ? store_user_address_for_client($row) : null,
		'addresses' => array_map('store_user_address_for_client', store_user_address_list($pdo, (int) $user_id)),
		'shipping_complete' => store_user_shipping_complete($user, $pdo),
	]);
}

profile_api_json(['ok' => false, 'error' => 'Unknown action.'], 400);
