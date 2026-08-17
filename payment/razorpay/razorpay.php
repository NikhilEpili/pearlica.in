<?php
/**
 * Razorpay — single file: keys + API + Checkout helpers.
 *
 * Mode defaults to live on pearlica.in and test on localhost.
 * Override in secrets.local.php: define('RAZORPAY_USE_LIVE', false);
 */
declare(strict_types=1);
require_once __DIR__ . '/razorpay_settings.php';

if (!defined('RAZORPAY_USE_LIVE')) {
	define(
		'RAZORPAY_USE_LIVE',
		function_exists('pearlica_is_local_dev_host') ? !pearlica_is_local_dev_host() : false
	);
}

const RAZORPAY_TEST_KEY_ID = 'rzp_test_SSfE5gZhpmhihe';
const RAZORPAY_TEST_KEY_SECRET = 'npmiPf7ACtr8sKwBuNYciVHv';

const RAZORPAY_LIVE_KEY_ID = 'rzp_live_SSRwqj0BSqbgBi';
const RAZORPAY_LIVE_KEY_SECRET = 'D1T5zt1C7l3cjR8PJT6oDdAc';

/** Webhook signing secret from Razorpay Dashboard (optional; leave empty to skip webhook verify) */
const RAZORPAY_WEBHOOK_SECRET = '';

function razorpay_config(): array
{
	$settings = razorpay_settings_load();
	$live = razorpay_effective_mode($settings) === 'live';
	$key_id = $live ? (string) ($settings['live_key_id'] ?? '') : (string) ($settings['test_key_id'] ?? '');
	$key_secret = $live ? (string) ($settings['live_key_secret'] ?? '') : (string) ($settings['test_key_secret'] ?? '');

	return [
		'key_id' => $key_id,
		'key_secret' => $key_secret,
		'webhook_secret' => (string) ($settings['webhook_secret'] ?? ''),
		'api_base' => (string) ($settings['api_base'] ?? 'https://api.razorpay.com/v1'),
		'currency' => (string) ($settings['currency'] ?? 'INR'),
		'mode' => $live ? 'live' : 'test',
		'mode_saved' => $live ? 'live' : 'test',
		'force_test_localhost' => false,
	];
}

function razorpay_api_request(string $method, string $path, ?array $payload = null): array
{
	$cfg = razorpay_config();
	if ($cfg['key_id'] === '' || $cfg['key_secret'] === '') {
		return ['ok' => false, 'status' => 0, 'error' => 'Razorpay keys not configured'];
	}

	$url = rtrim($cfg['api_base'], '/') . '/' . ltrim($path, '/');
	$ch = curl_init($url);
	if ($ch === false) {
		return ['ok' => false, 'status' => 0, 'error' => 'Unable to init curl'];
	}

	$headers = ['Content-Type: application/json'];
	$body = $payload !== null ? json_encode($payload) : null;

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_USERPWD, $cfg['key_id'] . ':' . $cfg['key_secret']);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	if ($body !== null) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	}

	$res_body = curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curl_err = curl_error($ch);
	curl_close($ch);

	if ($res_body === false) {
		return ['ok' => false, 'status' => $status, 'error' => $curl_err !== '' ? $curl_err : 'Request failed'];
	}

	$decoded = json_decode($res_body, true);
	if ($status >= 200 && $status < 300 && is_array($decoded)) {
		return ['ok' => true, 'status' => $status, 'data' => $decoded];
	}

	$err_msg = 'Razorpay API error';
	if (is_array($decoded) && isset($decoded['error']['description'])) {
		$err_msg = (string) $decoded['error']['description'];
	}
	return ['ok' => false, 'status' => $status, 'error' => $err_msg, 'raw' => $res_body];
}

function razorpay_normalize_india_contact(?string $raw): ?string
{
	$digits = preg_replace('/\D+/', '', (string) $raw);
	if ($digits === '') {
		return null;
	}
	if (strlen($digits) === 10) {
		return '+91' . $digits;
	}
	if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
		return '+' . $digits;
	}
	if (strlen($digits) === 11 && $digits[0] === '0') {
		return '+91' . substr($digits, 1);
	}
	return '+' . $digits;
}

function razorpay_checkout_prefill(string $name, string $email, ?string $phone): array
{
	$out = [];
	$name = trim($name);
	if ($name !== '') {
		$out['name'] = $name;
	}
	$email = trim($email);
	if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$out['email'] = $email;
	}
	$c = razorpay_normalize_india_contact($phone);
	if ($c !== null) {
		$out['contact'] = $c;
	}
	return $out;
}

function razorpay_create_order(int $amount_paise, string $currency, string $receipt): array
{
	$receipt = substr(preg_replace('/[^\w\-]/', '_', $receipt), 0, 40);
	if ($receipt === '') {
		$receipt = 'ord_' . bin2hex(random_bytes(6));
	}

	return razorpay_api_request('POST', '/orders', [
		'amount' => $amount_paise,
		'currency' => $currency,
		'receipt' => $receipt,
		'payment_capture' => 1,
		'notes' => [
			'store_order' => substr($receipt, 0, 50),
		],
	]);
}

function razorpay_verify_signature(string $provider_order_id, string $provider_payment_id, string $signature): bool
{
	$cfg = razorpay_config();
	if ($cfg['key_secret'] === '') {
		return false;
	}
	$payload = $provider_order_id . '|' . $provider_payment_id;
	$expected = hash_hmac('sha256', $payload, $cfg['key_secret']);
	return hash_equals($expected, $signature);
}

function razorpay_verify_webhook(string $payload, string $signature_header): bool
{
	$cfg = razorpay_config();
	$secret = (string) ($cfg['webhook_secret'] ?? '');
	if ($secret === '' || $signature_header === '') {
		return false;
	}
	$expected = hash_hmac('sha256', $payload, $secret);
	return hash_equals($expected, $signature_header);
}
