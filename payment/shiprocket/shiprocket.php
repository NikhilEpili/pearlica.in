<?php
/**
 * Shiprocket API helpers (auth + create order + payload builder).
 */
declare(strict_types=1);

require_once __DIR__ . '/../../lib/order.php';
require_once __DIR__ . '/shiprocket_settings.php';

function shiprocket_config(): array
{
	$settings = shiprocket_settings_load();
	$env = strtolower((string) ($settings['env'] ?? 'staging'));
	$is_production = $env === 'production';
	$base = $is_production
		? (string) ($settings['api_base_production'] ?? '')
		: (string) ($settings['api_base_staging'] ?? '');

	$pickup_pc = preg_replace('/\D+/', '', trim((string) ($settings['pickup_postcode'] ?? '')));

	return [
		'enabled' => !empty($settings['enabled']),
		'env' => $is_production ? 'production' : 'staging',
		'api_base' => rtrim($base, '/'),
		'email' => trim((string) ($settings['email'] ?? '')),
		'password' => (string) ($settings['password'] ?? ''),
		'pickup_location' => trim((string) ($settings['pickup_location'] ?? '')),
		'pickup_postcode' => strlen($pickup_pc) === 6 ? $pickup_pc : '',
		'estimate_weight_kg' => max(0.05, min(100.0, (float) ($settings['estimate_weight_kg'] ?? 0.5))),
		'channel_id' => (int) ($settings['channel_id'] ?? 0),
		'token_ttl' => max(300, (int) ($settings['token_ttl'] ?? 3300)),
		'token_cache_file' => BASE_PATH . '/storage/shiprocket.token.json',
	];
}

function shiprocket_safe_log(string $message): void
{
	error_log('[Shiprocket] ' . $message);
}

function shiprocket_api_request(string $method, string $path, ?array $payload = null, ?string $bearer_token = null): array
{
	$cfg = shiprocket_config();
	if ($cfg['api_base'] === '') {
		return ['ok' => false, 'status' => 0, 'error' => 'Shiprocket API base is not configured'];
	}

	$url = $cfg['api_base'] . '/' . ltrim($path, '/');
	$ch = curl_init($url);
	if ($ch === false) {
		return ['ok' => false, 'status' => 0, 'error' => 'Unable to init curl'];
	}

	$headers = ['Content-Type: application/json'];
	if ($bearer_token !== null && trim($bearer_token) !== '') {
		$headers[] = 'Authorization: Bearer ' . trim($bearer_token);
	}

	$method_up = strtoupper($method);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_TIMEOUT, 25);
	if ($method_up === 'GET') {
		curl_setopt($ch, CURLOPT_HTTPGET, true);
	} else {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method_up);
		if ($payload !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		}
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
		return ['ok' => true, 'status' => $status, 'data' => $decoded, 'raw' => $res_body];
	}

	$err_msg = 'Shiprocket API error';
	if (is_array($decoded)) {
		if (!empty($decoded['message']) && is_string($decoded['message'])) {
			$err_msg = $decoded['message'];
		} elseif (!empty($decoded['errors']) && is_array($decoded['errors'])) {
			$err_msg = 'Validation error';
		}
	}

	return ['ok' => false, 'status' => $status, 'error' => $err_msg, 'raw' => $res_body];
}

function shiprocket_cached_token_read(string $cache_file): ?array
{
	if (!is_file($cache_file)) {
		return null;
	}
	$raw = @file_get_contents($cache_file);
	if (!is_string($raw) || $raw === '') {
		return null;
	}
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return null;
	}
	$token = isset($decoded['token']) ? trim((string) $decoded['token']) : '';
	$expires_at = isset($decoded['expires_at']) ? (int) $decoded['expires_at'] : 0;
	if ($token === '' || $expires_at <= 0) {
		return null;
	}
	return ['token' => $token, 'expires_at' => $expires_at];
}

function shiprocket_cached_token_write(string $cache_file, string $token, int $expires_at): void
{
	$data = ['token' => $token, 'expires_at' => $expires_at];
	@file_put_contents($cache_file, json_encode($data));
}

function shiprocket_login(): array
{
	$cfg = shiprocket_config();
	if (!$cfg['enabled']) {
		return ['ok' => false, 'error' => 'Shiprocket disabled'];
	}
	if ($cfg['email'] === '' || $cfg['password'] === '') {
		return ['ok' => false, 'error' => 'Shiprocket credentials missing'];
	}

	$cached = shiprocket_cached_token_read((string) $cfg['token_cache_file']);
	$now = time();
	if ($cached && (int) $cached['expires_at'] > ($now + 30)) {
		return ['ok' => true, 'token' => (string) $cached['token'], 'cached' => true];
	}

	$auth = shiprocket_api_request('POST', '/auth/login', [
		'email' => $cfg['email'],
		'password' => $cfg['password'],
	]);
	if (empty($auth['ok'])) {
		return ['ok' => false, 'error' => (string) ($auth['error'] ?? 'Auth failed'), 'status' => (int) ($auth['status'] ?? 0)];
	}

	$data = is_array($auth['data'] ?? null) ? $auth['data'] : [];
	$token = trim((string) ($data['token'] ?? ''));
	if ($token === '') {
		return ['ok' => false, 'error' => 'Shiprocket token missing in auth response'];
	}

	$expires_at = $now + (int) $cfg['token_ttl'];
	shiprocket_cached_token_write((string) $cfg['token_cache_file'], $token, $expires_at);
	return ['ok' => true, 'token' => $token, 'cached' => false];
}

function shiprocket_create_order(array $payload): array
{
	$login = shiprocket_login();
	if (empty($login['ok'])) {
		return ['ok' => false, 'error' => (string) ($login['error'] ?? 'Unable to authenticate with Shiprocket')];
	}

	$token = (string) ($login['token'] ?? '');
	$res = shiprocket_api_request('POST', '/orders/create/adhoc', $payload, $token);
	if (!empty($res['ok'])) {
		return $res;
	}

	// One refresh retry for expired/stale token.
	$cfg = shiprocket_config();
	@unlink((string) $cfg['token_cache_file']);
	$login2 = shiprocket_login();
	if (empty($login2['ok'])) {
		return ['ok' => false, 'error' => (string) ($login2['error'] ?? 'Unable to authenticate with Shiprocket')];
	}
	return shiprocket_api_request('POST', '/orders/create/adhoc', $payload, (string) ($login2['token'] ?? ''));
}

function shiprocket_normalize_phone(?string $phone): string
{
	$digits = preg_replace('/\D+/', '', (string) $phone);
	if ($digits === '') {
		return '';
	}
	if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
		return substr($digits, 2);
	}
	if (strlen($digits) === 11 && $digits[0] === '0') {
		return substr($digits, 1);
	}
	return strlen($digits) > 10 ? substr($digits, -10) : $digits;
}

function shiprocket_build_order_payload(array $order, array $items): array
{
	$cfg = shiprocket_config();
	$order_items = [];
	foreach ($items as $it) {
		$qty = max(1, (int) ($it['quantity'] ?? 1));
		$unit_price = ((int) ($it['unit_price_paise'] ?? 0)) / 100;
		$order_items[] = [
			'name' => (string) ($it['product_name'] ?? 'Product'),
			'sku' => (string) ($it['sku'] ?? ''),
			'units' => $qty,
			'selling_price' => round(max(0, $unit_price), 2),
		];
	}

	$order_date = !empty($order['created_at']) ? (string) $order['created_at'] : date('Y-m-d H:i:s');
	$pm = strtolower(trim((string) ($order['payment_method'] ?? '')));
	$payment_mode = $pm === 'cod' ? 'COD' : 'Prepaid';

	$sub_total = ((int) ($order['subtotal_paise'] ?? 0)) / 100;
	$shipping_amount = ((int) ($order['shipping_paise'] ?? 0)) / 100;
	$discount = ((int) ($order['discount_paise'] ?? 0)) / 100;
	$total = ((int) ($order['total_paise'] ?? 0)) / 100;

	return [
		'order_id' => (string) ($order['order_number'] ?? ('LOCAL-' . (string) ($order['id'] ?? '0'))),
		'order_date' => $order_date,
		'pickup_location' => $cfg['pickup_location'],
		'channel_id' => $cfg['channel_id'] > 0 ? $cfg['channel_id'] : null,
		'billing_customer_name' => (string) ($order['shipping_name'] ?? ''),
		'billing_last_name' => '',
		'billing_address' => (string) ($order['shipping_address'] ?? ''),
		'billing_city' => (string) ($order['shipping_city'] ?? ''),
		'billing_pincode' => (string) ($order['shipping_pincode'] ?? ''),
		'billing_state' => (string) ($order['shipping_state'] ?? ''),
		'billing_country' => 'India',
		'billing_email' => (string) ($order['shipping_email'] ?? ''),
		'billing_phone' => shiprocket_normalize_phone((string) ($order['shipping_phone'] ?? '')),
		'shipping_is_billing' => true,
		'order_items' => $order_items,
		'payment_method' => $payment_mode,
		'sub_total' => round(max(0, $sub_total), 2),
		'shipping_charges' => round(max(0, $shipping_amount), 2),
		'discount' => round(max(0, $discount), 2),
		'total_discount' => round(max(0, $discount), 2),
		'length' => 10,
		'breadth' => 10,
		'height' => 5,
		'weight' => 0.5,
	];
}

function shiprocket_sync_order(PDO $pdo, int $order_id): array
{
	if ($order_id <= 0) {
		return ['ok' => false, 'error' => 'Invalid order id'];
	}
	$cfg = shiprocket_config();
	if (!$cfg['enabled']) {
		shiprocket_order_upsert($pdo, $order_id, [
			'status' => 'failed',
			'response_payload' => json_encode(['error' => 'Shiprocket disabled']),
		]);
		return ['ok' => false, 'error' => 'Shiprocket disabled'];
	}
	if ($cfg['pickup_location'] === '') {
		shiprocket_order_upsert($pdo, $order_id, [
			'status' => 'failed',
			'response_payload' => json_encode(['error' => 'Shiprocket pickup location missing']),
		]);
		return ['ok' => false, 'error' => 'Shiprocket pickup location missing'];
	}

	$existing = shiprocket_order_find_by_order_id($pdo, $order_id);
	if ($existing && !empty($existing['shiprocket_order_id'])) {
		return ['ok' => true, 'created' => false, 'existing' => true, 'shiprocket_order_id' => (string) $existing['shiprocket_order_id']];
	}

	$order = order_find_by_id($pdo, $order_id);
	if (!$order) {
		shiprocket_order_upsert($pdo, $order_id, [
			'status' => 'failed',
			'response_payload' => json_encode(['error' => 'Order not found']),
		]);
		return ['ok' => false, 'error' => 'Order not found'];
	}
	$items = order_items_list($pdo, $order_id);
	if (empty($items)) {
		shiprocket_order_upsert($pdo, $order_id, [
			'status' => 'failed',
			'response_payload' => json_encode(['error' => 'Order items not found']),
		]);
		return ['ok' => false, 'error' => 'Order items not found'];
	}

	$payload = shiprocket_build_order_payload($order, $items);
	$res = shiprocket_create_order($payload);
	$response_payload = isset($res['raw']) ? (string) $res['raw'] : json_encode($res);
	if (empty($res['ok'])) {
		shiprocket_order_upsert($pdo, $order_id, [
			'status' => 'failed',
			'request_payload' => json_encode($payload),
			'response_payload' => $response_payload !== false ? $response_payload : null,
		]);
		return ['ok' => false, 'error' => (string) ($res['error'] ?? 'Shiprocket create order failed')];
	}

	$data = is_array($res['data'] ?? null) ? $res['data'] : [];
	$shiprocket_order_id = isset($data['order_id']) ? (string) $data['order_id'] : '';
	$shipment_id = isset($data['shipment_id']) ? (string) $data['shipment_id'] : '';
	$awb_code = isset($data['awb_code']) ? (string) $data['awb_code'] : '';
	$status = $shiprocket_order_id !== '' ? 'created' : 'accepted';

	shiprocket_order_upsert($pdo, $order_id, [
		'shiprocket_order_id' => $shiprocket_order_id,
		'shipment_id' => $shipment_id,
		'awb_code' => $awb_code,
		'status' => $status,
		'request_payload' => json_encode($payload),
		'response_payload' => $response_payload !== false ? $response_payload : null,
	]);

	return [
		'ok' => true,
		'created' => true,
		'shiprocket_order_id' => $shiprocket_order_id,
		'shipment_id' => $shipment_id,
		'awb_code' => $awb_code,
	];
}

/**
 * Parse a day or day-range from API string/number (e.g. "2-4", "3", "5 days").
 *
 * @return array{0:int,1:int}|null [min, max] inclusive business-day style span
 */
function shiprocket_parse_day_span_from_mixed(mixed $value): ?array
{
	if (is_int($value) || is_float($value)) {
		$n = max(1, (int) round((float) $value));

		return [$n, $n];
	}
	if (!is_string($value)) {
		return null;
	}
	$s = trim($value);
	if ($s === '') {
		return null;
	}
	if (preg_match('/(\d+)\s*[\-–]\s*(\d+)/u', $s, $m)) {
		$a = (int) $m[1];
		$b = (int) $m[2];

		return [min($a, $b), max($a, $b)];
	}
	if (preg_match('/(\d+)/', $s, $m)) {
		$n = max(1, (int) $m[1]);

		return [$n, $n];
	}

	return null;
}

/**
 * @param array<int, mixed> $couriers
 * @return array{0: array<int, int>, 1: array<int, int>}
 */
function shiprocket_collect_eta_spans_from_couriers(array $couriers): array
{
	$mins = [];
	$maxs = [];
	foreach ($couriers as $c) {
		if (!is_array($c)) {
			continue;
		}
		$span = null;
		foreach (['estimated_delivery_days', 'etd', 'delivery_days', 'tat'] as $k) {
			if (isset($c[$k])) {
				$span = shiprocket_parse_day_span_from_mixed($c[$k]);
				if ($span !== null) {
					break;
				}
			}
		}
		if ($span === null && isset($c['etd_hours']) && is_numeric($c['etd_hours'])) {
			$h = (float) $c['etd_hours'];
			if ($h > 0) {
				$d = max(1, (int) ceil($h / 24));
				$span = [$d, $d];
			}
		}
		if ($span !== null) {
			$mins[] = $span[0];
			$maxs[] = $span[1];
		}
	}

	return [$mins, $maxs];
}

/**
 * Domestic courier serviceability + ETA text for checkout (pickup vs delivery PIN).
 *
 * @return array{ok: bool, message: string, min_days: ?int, max_days: ?int}
 */
function shiprocket_estimate_delivery_for_pincode(string $delivery_pincode): array
{
	$empty = ['ok' => false, 'message' => '', 'min_days' => null, 'max_days' => null];
	$cfg = shiprocket_config();
	$del = preg_replace('/\D+/', '', $delivery_pincode);
	if (strlen($del) !== 6) {
		return $empty;
	}
	if (!$cfg['enabled']) {
		return $empty;
	}
	$pickup = (string) ($cfg['pickup_postcode'] ?? '');
	if (strlen($pickup) !== 6) {
		shiprocket_safe_log('estimate: pickup_postcode missing or invalid in settings');

		return $empty;
	}
	$weight = (float) ($cfg['estimate_weight_kg'] ?? 0.5);

	$login = shiprocket_login();
	if (empty($login['ok'])) {
		shiprocket_safe_log('estimate: login failed');

		return $empty;
	}

	$query = [
		'pickup_postcode' => $pickup,
		'delivery_postcode' => $del,
		'weight' => $weight,
		'cod' => 0,
	];
	$path = 'courier/serviceability?' . http_build_query($query);
	$res = shiprocket_api_request('GET', $path, null, (string) ($login['token'] ?? ''));
	if (empty($res['ok'])) {
		shiprocket_safe_log('estimate: serviceability HTTP ' . (string) ($res['status'] ?? '') . ' ' . (string) ($res['error'] ?? ''));

		return $empty;
	}

	$top = is_array($res['data'] ?? null) ? $res['data'] : [];
	$couriers = $top['data']['available_courier_companies'] ?? $top['available_courier_companies'] ?? null;
	if (!is_array($couriers) && isset($top['data']) && is_array($top['data'])) {
		$inner = $top['data'];
		if (isset($inner[0]) && is_array($inner[0]) && (isset($inner[0]['courier_name']) || isset($inner[0]['courier_company_id']))) {
			$couriers = $inner;
		}
	}
	if (!is_array($couriers) || $couriers === []) {
		return [
			'ok' => false,
			'message' => 'We could not confirm courier availability to this PIN code. You can still place your order — we will reach out if needed.',
			'min_days' => null,
			'max_days' => null,
		];
	}

	[$mins, $maxs] = shiprocket_collect_eta_spans_from_couriers($couriers);
	if ($mins === [] || $maxs === []) {
		$names = [];
		foreach (array_slice($couriers, 0, 3) as $row) {
			if (is_array($row) && !empty($row['courier_name'])) {
				$names[] = (string) $row['courier_name'];
			}
		}
		$msg = 'Delivery is available to this PIN code';
		if ($names !== []) {
			$msg .= ' via ' . implode(', ', $names);
		}
		$msg .= '. Transit time depends on the courier assigned after dispatch.';

		return ['ok' => true, 'message' => $msg, 'min_days' => null, 'max_days' => null];
	}

	$min = min($mins);
	$max = max($maxs);
	if ($min === $max) {
		$text = 'Estimated delivery: about ' . $min . ' business day' . ($min === 1 ? '' : 's') . ', based on available couriers.';
	} else {
		$text = 'Estimated delivery: about ' . $min . '–' . $max . ' business days, based on available couriers.';
	}

	return ['ok' => true, 'message' => $text, 'min_days' => $min, 'max_days' => $max];
}
