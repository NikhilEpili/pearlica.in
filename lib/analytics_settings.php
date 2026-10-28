<?php
declare(strict_types=1);

function analytics_settings_file_path(): string
{
	$base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);

	return rtrim((string) $base, '/\\') . '/storage/analytics.settings.json';
}

/** @return array{meta_capi_enabled:bool,meta_capi_access_token:string,meta_test_event_code:string} */
function analytics_settings_defaults(): array
{
	return [
		'meta_capi_enabled' => false,
		'meta_capi_access_token' => '',
		'meta_test_event_code' => '',
	];
}

/** @return array{meta_capi_enabled:bool,meta_capi_access_token:string,meta_test_event_code:string} */
function analytics_settings_load(): array
{
	$defaults = analytics_settings_defaults();
	$file = analytics_settings_file_path();
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

	return [
		'meta_capi_enabled' => !empty($merged['meta_capi_enabled']),
		'meta_capi_access_token' => trim((string) ($merged['meta_capi_access_token'] ?? '')),
		'meta_test_event_code' => trim((string) ($merged['meta_test_event_code'] ?? '')),
	];
}

/** @param array{meta_capi_enabled?:bool,meta_capi_access_token?:string,meta_test_event_code?:string} $settings */
function analytics_settings_save(array $settings): bool
{
	$current = analytics_settings_load();
	$next = [
		'meta_capi_enabled' => array_key_exists('meta_capi_enabled', $settings)
			? !empty($settings['meta_capi_enabled'])
			: $current['meta_capi_enabled'],
		'meta_capi_access_token' => array_key_exists('meta_capi_access_token', $settings)
			? trim((string) $settings['meta_capi_access_token'])
			: $current['meta_capi_access_token'],
		'meta_test_event_code' => array_key_exists('meta_test_event_code', $settings)
			? trim((string) $settings['meta_test_event_code'])
			: $current['meta_test_event_code'],
	];
	$file = analytics_settings_file_path();
	$dir = dirname($file);
	if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
		return false;
	}
	$json = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if (!is_string($json)) {
		return false;
	}

	return @file_put_contents($file, $json . "\n") !== false;
}

function analytics_settings_mask_token(string $token): string
{
	$token = trim($token);
	if ($token === '') {
		return '';
	}
	if (strlen($token) <= 8) {
		return str_repeat('*', strlen($token));
	}

	return substr($token, 0, 4) . str_repeat('*', max(0, strlen($token) - 8)) . substr($token, -4);
}
