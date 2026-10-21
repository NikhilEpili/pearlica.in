<?php
declare(strict_types=1);

function site_announcement_bar_settings_file_path(): string
{
	$base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
	return rtrim((string) $base, '/\\') . '/storage/announcement-bar.settings.json';
}

function site_announcement_bar_settings_defaults(): array
{
	return [
		'enabled' => true,
		'text' => '40% Festive Discount Offer Extending to 25 November · EXTRA 5% OFF ON PREPAID · FREE SHIPPING ABOVE ₹700',
	];
}

function site_announcement_bar_settings_load(): array
{
	$defaults = site_announcement_bar_settings_defaults();
	$file = site_announcement_bar_settings_file_path();
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
	$merged['enabled'] = !empty($merged['enabled']);
	$merged['text'] = trim((string) ($merged['text'] ?? $defaults['text']));
	if ($merged['text'] === '') {
		$merged['text'] = (string) $defaults['text'];
	}
	if (strlen($merged['text']) > 500) {
		$merged['text'] = substr($merged['text'], 0, 500);
	}

	return $merged;
}

function site_announcement_bar_settings_save(array $settings): bool
{
	$defaults = site_announcement_bar_settings_defaults();
	$payload = [
		'enabled' => !empty($settings['enabled']),
		'text' => trim((string) ($settings['text'] ?? $defaults['text'])),
	];
	if ($payload['text'] === '') {
		$payload['text'] = (string) $defaults['text'];
	}
	if (strlen($payload['text']) > 500) {
		$payload['text'] = substr($payload['text'], 0, 500);
	}

	$file = site_announcement_bar_settings_file_path();
	$dir = dirname($file);
	if (!is_dir($dir)) {
		@mkdir($dir, 0755, true);
	}
	$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if (!is_string($json)) {
		return false;
	}
	return @file_put_contents($file, $json) !== false;
}
