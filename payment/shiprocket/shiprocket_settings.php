<?php
declare(strict_types=1);

function shiprocket_settings_file_path(): string
{
	$base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
	return rtrim((string) $base, '/') . '/storage/shiprocket.settings.json';
}

function shiprocket_settings_defaults(): array
{
	return [
		'enabled' => defined('SHIPROCKET_ENABLED') ? (bool) SHIPROCKET_ENABLED : false,
		'env' => defined('SHIPROCKET_ENV') ? strtolower((string) SHIPROCKET_ENV) : 'staging',
		'email' => defined('SHIPROCKET_EMAIL') ? trim((string) SHIPROCKET_EMAIL) : '',
		'password' => defined('SHIPROCKET_PASSWORD') ? (string) SHIPROCKET_PASSWORD : '',
		'pickup_location' => defined('SHIPROCKET_PICKUP_LOCATION') ? trim((string) SHIPROCKET_PICKUP_LOCATION) : '',
		'pickup_postcode' => defined('SHIPROCKET_PICKUP_POSTCODE') ? preg_replace('/\D+/', '', (string) SHIPROCKET_PICKUP_POSTCODE) : '',
		'estimate_weight_kg' => defined('SHIPROCKET_ESTIMATE_WEIGHT_KG') ? (float) SHIPROCKET_ESTIMATE_WEIGHT_KG : 0.5,
		'channel_id' => defined('SHIPROCKET_CHANNEL_ID') ? (int) SHIPROCKET_CHANNEL_ID : 0,
		'token_ttl' => defined('SHIPROCKET_TOKEN_TTL_SECONDS') ? max(300, (int) SHIPROCKET_TOKEN_TTL_SECONDS) : 3300,
		'api_base_staging' => defined('SHIPROCKET_API_BASE_STAGING') ? rtrim((string) SHIPROCKET_API_BASE_STAGING, '/') : '',
		'api_base_production' => defined('SHIPROCKET_API_BASE_PRODUCTION') ? rtrim((string) SHIPROCKET_API_BASE_PRODUCTION, '/') : '',
	];
}

function shiprocket_settings_load(): array
{
	$defaults = shiprocket_settings_defaults();
	$file = shiprocket_settings_file_path();
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
	$merged = array_merge($defaults, $decoded);
	$pcMerged = preg_replace('/\D+/', '', (string) ($merged['pickup_postcode'] ?? ''));
	$pcDefault = preg_replace('/\D+/', '', (string) ($defaults['pickup_postcode'] ?? ''));
	if (strlen($pcMerged) !== 6 && strlen($pcDefault) === 6) {
		$merged['pickup_postcode'] = $pcDefault;
	}

	return $merged;
}

function shiprocket_settings_save(array $settings): bool
{
	$file = shiprocket_settings_file_path();
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

function shiprocket_settings_mask_password(string $password): string
{
	$len = strlen($password);
	if ($len <= 4) {
		return str_repeat('*', $len);
	}
	return substr($password, 0, 2) . str_repeat('*', max(0, $len - 4)) . substr($password, -2);
}
