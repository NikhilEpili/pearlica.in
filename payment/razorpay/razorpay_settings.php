<?php
declare(strict_types=1);

function razorpay_settings_file_path(): string
{
	$base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
	return rtrim((string) $base, '/') . '/storage/razorpay.settings.json';
}

function razorpay_settings_defaults(): array
{
	return [
		'mode' => defined('RAZORPAY_USE_LIVE') && RAZORPAY_USE_LIVE ? 'live' : 'test',
		'test_key_id' => defined('RAZORPAY_TEST_KEY_ID') ? (string) RAZORPAY_TEST_KEY_ID : '',
		'test_key_secret' => defined('RAZORPAY_TEST_KEY_SECRET') ? (string) RAZORPAY_TEST_KEY_SECRET : '',
		'live_key_id' => defined('RAZORPAY_LIVE_KEY_ID') ? (string) RAZORPAY_LIVE_KEY_ID : '',
		'live_key_secret' => defined('RAZORPAY_LIVE_KEY_SECRET') ? (string) RAZORPAY_LIVE_KEY_SECRET : '',
		'webhook_secret' => defined('RAZORPAY_WEBHOOK_SECRET') ? (string) RAZORPAY_WEBHOOK_SECRET : '',
		'api_base' => 'https://api.razorpay.com/v1',
		'currency' => 'INR',
	];
}

function razorpay_settings_load(): array
{
	$defaults = razorpay_settings_defaults();
	$file = razorpay_settings_file_path();
	if (!is_file($file)) {
		return $defaults;
	}
	$raw = @file_get_contents($file);
	if (!is_string($raw) || trim($raw) === '') {
		return $defaults;
	}
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return $defaults;
	}
	return array_merge($defaults, $decoded);
}

function razorpay_settings_save(array $settings): bool
{
	$file = razorpay_settings_file_path();
	$dir = dirname($file);
	if (!is_dir($dir)) {
		@mkdir($dir, 0755, true);
	}
	$payload = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if (!is_string($payload)) {
		return false;
	}
	return @file_put_contents($file, $payload) !== false;
}

function razorpay_settings_mask(string $value): string
{
	$len = strlen($value);
	if ($len <= 6) {
		return str_repeat('*', $len);
	}
	return substr($value, 0, 4) . str_repeat('*', max(0, $len - 6)) . substr($value, -2);
}
