<?php
declare(strict_types=1);

function refrens_settings_file_path(): string
{
	$base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
	return rtrim((string) $base, '/\\') . '/storage/refrens.settings.json';
}

function refrens_settings_defaults(): array
{
	return [
		'enabled' => defined('REFRENS_ENABLED') ? (bool) REFRENS_ENABLED : false,
		'api_base' => defined('REFRENS_API_BASE') ? rtrim((string) REFRENS_API_BASE, '/') : 'https://api.refrens.com',
		'url_key' => defined('REFRENS_URL_KEY') ? trim((string) REFRENS_URL_KEY) : '',
		'app_id' => defined('REFRENS_APP_ID') ? trim((string) REFRENS_APP_ID) : '',
		'app_secret' => defined('REFRENS_APP_SECRET') ? (string) REFRENS_APP_SECRET : '',
		'invoice_type' => defined('REFRENS_INVOICE_TYPE') ? trim((string) REFRENS_INVOICE_TYPE) : 'INVOICE',
		'currency' => defined('REFRENS_CURRENCY') ? strtoupper(trim((string) REFRENS_CURRENCY)) : 'INR',
		'send_invoice_email' => defined('REFRENS_SEND_INVOICE_EMAIL') ? (bool) REFRENS_SEND_INVOICE_EMAIL : true,
		'invoice_cc_email' => defined('REFRENS_INVOICE_CC_EMAIL') ? trim((string) REFRENS_INVOICE_CC_EMAIL) : '',
		'payment_method' => defined('REFRENS_PAYMENT_METHOD') ? strtoupper(trim((string) REFRENS_PAYMENT_METHOD)) : 'UPI',
		'shipping_line_gst_rate' => defined('REFRENS_SHIPPING_LINE_GST_RATE') ? (float) REFRENS_SHIPPING_LINE_GST_RATE : 18.0,
		'seller_name' => defined('REFRENS_SELLER_NAME') ? trim((string) REFRENS_SELLER_NAME) : '',
		'seller_street' => defined('REFRENS_SELLER_STREET') ? trim((string) REFRENS_SELLER_STREET) : '',
		'seller_pincode' => defined('REFRENS_SELLER_PINCODE') ? trim((string) REFRENS_SELLER_PINCODE) : '',
		'seller_gst_state' => defined('REFRENS_SELLER_GST_STATE') ? trim((string) REFRENS_SELLER_GST_STATE) : '27',
		'seller_gstin' => defined('REFRENS_SELLER_GSTIN') ? trim((string) REFRENS_SELLER_GSTIN) : '',
		'seller_country' => defined('REFRENS_SELLER_COUNTRY') ? strtoupper(trim((string) REFRENS_SELLER_COUNTRY)) : 'IN',
		'seller_email' => defined('REFRENS_SELLER_EMAIL') ? trim((string) REFRENS_SELLER_EMAIL) : '',
		'seller_phone' => defined('REFRENS_SELLER_PHONE') ? trim((string) REFRENS_SELLER_PHONE) : '',
	];
}

function refrens_settings_load(): array
{
	$defaults = refrens_settings_defaults();
	$file = refrens_settings_file_path();
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

function refrens_settings_save(array $settings): bool
{
	$file = refrens_settings_file_path();
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

function refrens_settings_mask(string $value): string
{
	$len = strlen($value);
	if ($len <= 6) {
		return str_repeat('*', $len);
	}
	return substr($value, 0, 4) . str_repeat('*', max(0, $len - 6)) . substr($value, -2);
}
