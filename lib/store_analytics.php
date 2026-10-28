<?php
declare(strict_types=1);

require_once __DIR__ . '/analytics_settings.php';

const STORE_ANALYTICS_META_PIXEL_ID = '1606163897779149';
const STORE_ANALYTICS_GA4_MEASUREMENT_ID = 'G-FCQW9SZ8VF';
const STORE_ANALYTICS_CURRENCY = 'INR';

function store_analytics_content_id(string $sku, ?int $product_id = null): string
{
	$sku = trim($sku);
	if ($sku !== '') {
		return $sku;
	}
	if ($product_id !== null && $product_id > 0) {
		return 'product-' . (string) $product_id;
	}

	return '';
}

function store_analytics_unit_price_from_product(array $product): float
{
	$mrp = (float) ($product['mrp'] ?? 0);
	$discount = (float) ($product['discount_percentage'] ?? 0);

	return $discount > 0 ? $mrp * (1 - $discount / 100) : $mrp;
}

/** @return array{content_id:string,name:string,quantity:int,value:float,product_id:?int} */
function store_analytics_line_from_product(array $product, int $quantity): array
{
	$quantity = max(1, $quantity);
	$unit = store_analytics_unit_price_from_product($product);
	$product_id = isset($product['id']) ? (int) $product['id'] : null;

	return [
		'content_id' => store_analytics_content_id((string) ($product['sku'] ?? ''), $product_id),
		'name' => trim((string) ($product['name'] ?? '')),
		'quantity' => $quantity,
		'value' => round($unit * $quantity, 2),
		'product_id' => $product_id,
	];
}

/** @return array{content_id:string,name:string,quantity:int,value:float,product_id:?int} */
function store_analytics_line_from_order_item(array $item): array
{
	$quantity = max(1, (int) ($item['quantity'] ?? 1));
	$line_paise = (int) ($item['line_total_paise'] ?? 0);
	$value = $line_paise > 0 ? round($line_paise / 100, 2) : round(((int) ($item['unit_price_paise'] ?? 0)) * $quantity / 100, 2);
	$product_id = isset($item['product_id']) ? (int) $item['product_id'] : null;

	return [
		'content_id' => store_analytics_content_id((string) ($item['sku'] ?? ''), $product_id),
		'name' => trim((string) ($item['product_name'] ?? '')),
		'quantity' => $quantity,
		'value' => $value,
		'product_id' => $product_id,
	];
}

/** @param list<array{content_id:string,name:string,quantity:int,value:float,product_id:?int}> $lines */
function store_analytics_bundle_lines(array $lines, float $total_value): array
{
	$content_ids = [];
	$contents = [];
	$ga_items = [];
	$num_items = 0;
	foreach ($lines as $line) {
		$content_id = (string) ($line['content_id'] ?? '');
		if ($content_id === '') {
			continue;
		}
		$qty = max(1, (int) ($line['quantity'] ?? 1));
		$num_items += $qty;
		$line_value = (float) ($line['value'] ?? 0);
		$unit_price = $qty > 0 ? round($line_value / $qty, 2) : $line_value;
		$content_ids[] = $content_id;
		$contents[] = [
			'id' => $content_id,
			'quantity' => $qty,
			'item_price' => $unit_price,
		];
		$ga_items[] = [
			'item_id' => $content_id,
			'item_name' => (string) ($line['name'] ?? ''),
			'quantity' => $qty,
			'price' => $unit_price,
		];
	}

	return [
		'currency' => STORE_ANALYTICS_CURRENCY,
		'value' => round($total_value, 2),
		'content_ids' => array_values(array_unique($content_ids)),
		'contents' => $contents,
		'num_items' => $num_items,
		'ga_items' => $ga_items,
	];
}

function store_analytics_purchase_event_id(int $order_id): string
{
	return 'purchase-' . (string) max(1, $order_id);
}

function store_analytics_order_purchase_eligible(array $order): bool
{
	$status = strtolower(trim((string) ($order['status'] ?? '')));

	return in_array($status, ['paid', 'cod_pending'], true);
}

function store_analytics_orders_has_purchase_column(PDO $pdo): bool
{
	static $cached = null;
	if ($cached !== null) {
		return $cached;
	}
	try {
		$stmt = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'analytics_purchase_sent_at'");
		$cached = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);

		return (bool) $cached;
	} catch (Throwable $e) {
		$cached = false;

		return false;
	}
}

/**
 * Claim Purchase analytics once per order. Returns payload for browser + CAPI, or null if already sent / ineligible.
 *
 * @return array<string,mixed>|null
 */
function store_analytics_claim_purchase(PDO $pdo, int $order_id, array $order, array $order_items): ?array
{
	if ($order_id <= 0 || !store_analytics_order_purchase_eligible($order)) {
		return null;
	}

	$claimed = false;
	if (store_analytics_orders_has_purchase_column($pdo)) {
		$stmt = $pdo->prepare('UPDATE orders SET analytics_purchase_sent_at = NOW() WHERE id = ? AND analytics_purchase_sent_at IS NULL');
		$stmt->execute([$order_id]);
		$claimed = $stmt->rowCount() > 0;
	} else {
		if (!isset($_SESSION['pearlica_analytics_purchase_sent']) || !is_array($_SESSION['pearlica_analytics_purchase_sent'])) {
			$_SESSION['pearlica_analytics_purchase_sent'] = [];
		}
		if (empty($_SESSION['pearlica_analytics_purchase_sent'][$order_id])) {
			$_SESSION['pearlica_analytics_purchase_sent'][$order_id] = time();
			$claimed = true;
		}
	}
	if (!$claimed) {
		return null;
	}

	$lines = [];
	foreach ($order_items as $item) {
		$lines[] = store_analytics_line_from_order_item($item);
	}
	$total_value = ((int) ($order['total_paise'] ?? 0)) / 100;
	$bundle = store_analytics_bundle_lines($lines, $total_value);
	$event_id = store_analytics_purchase_event_id($order_id);
	$order_number = trim((string) ($order['order_number'] ?? ''));

	return [
		'event_id' => $event_id,
		'order_id' => $order_id,
		'transaction_id' => $order_number !== '' ? $order_number : (string) $order_id,
		'currency' => STORE_ANALYTICS_CURRENCY,
		'value' => $bundle['value'],
		'content_ids' => $bundle['content_ids'],
		'contents' => $bundle['contents'],
		'num_items' => $bundle['num_items'],
		'ga_items' => $bundle['ga_items'],
		'payment_method' => (string) ($order['payment_method'] ?? ''),
	];
}

/** @param array<string,mixed> $purchase */
function store_analytics_build_view_content(array $product, float $sale_price): array
{
	$line = store_analytics_line_from_product($product, 1);
	$bundle = store_analytics_bundle_lines([$line], $sale_price);

	return [
		'event' => 'ViewContent',
		'currency' => STORE_ANALYTICS_CURRENCY,
		'value' => round($sale_price, 2),
		'content_ids' => $bundle['content_ids'],
		'contents' => $bundle['contents'],
		'ga_items' => $bundle['ga_items'],
	];
}

/** @param list<array<string,mixed>> $cart_items checkout $cart_items shape */
function store_analytics_build_initiate_checkout(array $cart_items, float $total_value): array
{
	$lines = [];
	foreach ($cart_items as $row) {
		$lines[] = [
			'content_id' => store_analytics_content_id((string) ($row['sku'] ?? ''), (int) ($row['id'] ?? 0)),
			'name' => (string) ($row['name'] ?? ''),
			'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
			'value' => round((float) ($row['line_total'] ?? 0), 2),
			'product_id' => (int) ($row['id'] ?? 0),
		];
	}
	$bundle = store_analytics_bundle_lines($lines, $total_value);

	return [
		'event' => 'InitiateCheckout',
		'currency' => STORE_ANALYTICS_CURRENCY,
		'value' => $bundle['value'],
		'content_ids' => $bundle['content_ids'],
		'contents' => $bundle['contents'],
		'num_items' => $bundle['num_items'],
		'ga_items' => $bundle['ga_items'],
	];
}

/** @param array<string,mixed> $payload */
function store_analytics_session_push(string $event, array $payload): void
{
	if (!isset($_SESSION['pearlica_analytics_events']) || !is_array($_SESSION['pearlica_analytics_events'])) {
		$_SESSION['pearlica_analytics_events'] = [];
	}
	$_SESSION['pearlica_analytics_events'][] = [
		'event' => $event,
		'payload' => $payload,
	];
}

/** @return list<array{event:string,payload:array<string,mixed>}> */
function store_analytics_session_take_queue(): array
{
	$queue = $_SESSION['pearlica_analytics_events'] ?? [];
	unset($_SESSION['pearlica_analytics_events']);
	if (!is_array($queue)) {
		return [];
	}
	$out = [];
	foreach ($queue as $row) {
		if (!is_array($row) || empty($row['event']) || !is_array($row['payload'] ?? null)) {
			continue;
		}
		$out[] = [
			'event' => (string) $row['event'],
			'payload' => $row['payload'],
		];
	}

	return $out;
}

function store_analytics_hash_pii(string $value): string
{
	$value = strtolower(trim($value));
	if ($value === '') {
		return '';
	}

	return hash('sha256', $value);
}

function store_analytics_normalize_phone_hash(string $phone): string
{
	$digits = preg_replace('/\D+/', '', $phone) ?? '';
	if ($digits === '') {
		return '';
	}
	if (strlen($digits) === 10) {
		$digits = '91' . $digits;
	}

	return store_analytics_hash_pii('+' . $digits);
}

/** @param array<string,mixed> $purchase */
function store_analytics_meta_user_data_from_order(array $order): array
{
	$user_data = [];
	$email = store_analytics_hash_pii((string) ($order['shipping_email'] ?? ''));
	if ($email !== '') {
		$user_data['em'] = [$email];
	}
	$phone = store_analytics_normalize_phone_hash((string) ($order['shipping_phone'] ?? ''));
	if ($phone !== '') {
		$user_data['ph'] = [$phone];
	}
	$name = trim((string) ($order['shipping_name'] ?? ''));
	if ($name !== '') {
		$parts = preg_split('/\s+/', $name) ?: [];
		$first = store_analytics_hash_pii((string) ($parts[0] ?? ''));
		$last = store_analytics_hash_pii((string) (count($parts) > 1 ? end($parts) : ''));
		if ($first !== '') {
			$user_data['fn'] = [$first];
		}
		if ($last !== '') {
			$user_data['ln'] = [$last];
		}
	}
	$pin = store_analytics_hash_pii((string) ($order['shipping_pincode'] ?? ''));
	if ($pin !== '') {
		$user_data['zp'] = [$pin];
	}
	$city = store_analytics_hash_pii((string) ($order['shipping_city'] ?? ''));
	if ($city !== '') {
		$user_data['ct'] = [$city];
	}
	$state = store_analytics_hash_pii((string) ($order['shipping_state'] ?? ''));
	if ($state !== '') {
		$user_data['st'] = [$state];
	}
	if (!empty($_SERVER['REMOTE_ADDR'])) {
		$user_data['client_ip_address'] = (string) $_SERVER['REMOTE_ADDR'];
	}
	if (!empty($_SERVER['HTTP_USER_AGENT'])) {
		$user_data['client_user_agent'] = (string) $_SERVER['HTTP_USER_AGENT'];
	}
	if (!empty($_COOKIE['_fbc'])) {
		$user_data['fbc'] = (string) $_COOKIE['_fbc'];
	}
	if (!empty($_COOKIE['_fbp'])) {
		$user_data['fbp'] = (string) $_COOKIE['_fbp'];
	}

	return $user_data;
}

/**
 * @param array<string,mixed> $purchase from store_analytics_claim_purchase
 * @return array{ok:bool,error?:string,response?:string}
 */
function store_analytics_meta_capi_send_purchase(array $purchase, array $order, ?string $event_source_url = null, ?string $test_event_code = null): array
{
	$settings = analytics_settings_load();
	if (!$settings['meta_capi_enabled'] || $settings['meta_capi_access_token'] === '') {
		return ['ok' => false, 'error' => 'Meta Conversions API is not configured.'];
	}

	$event_id = (string) ($purchase['event_id'] ?? '');
	if ($event_id === '') {
		return ['ok' => false, 'error' => 'Missing event_id.'];
	}

	$custom_data = [
		'currency' => (string) ($purchase['currency'] ?? STORE_ANALYTICS_CURRENCY),
		'value' => (float) ($purchase['value'] ?? 0),
		'content_ids' => array_values($purchase['content_ids'] ?? []),
		'contents' => array_values($purchase['contents'] ?? []),
		'num_items' => (int) ($purchase['num_items'] ?? 0),
		'order_id' => (string) ($purchase['transaction_id'] ?? ''),
	];
	$payload = [
		'data' => [[
			'event_name' => 'Purchase',
			'event_time' => time(),
			'event_id' => $event_id,
			'action_source' => 'website',
			'event_source_url' => $event_source_url ?: (defined('SITE_PUBLIC_URL') ? rtrim((string) SITE_PUBLIC_URL, '/') . '/order-success' : ''),
			'user_data' => store_analytics_meta_user_data_from_order($order),
			'custom_data' => $custom_data,
		]],
		'access_token' => $settings['meta_capi_access_token'],
	];
	$test_code = $test_event_code ?? $settings['meta_test_event_code'];
	if ($test_code !== '') {
		$payload['test_event_code'] = $test_code;
	}

	$url = 'https://graph.facebook.com/v21.0/' . rawurlencode(STORE_ANALYTICS_META_PIXEL_ID) . '/events';
	$body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if (!is_string($body)) {
		return ['ok' => false, 'error' => 'Could not encode CAPI payload.'];
	}

	if (!function_exists('curl_init')) {
		return ['ok' => false, 'error' => 'cURL is not available on this server.'];
	}

	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_POST => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
		CURLOPT_POSTFIELDS => $body,
		CURLOPT_TIMEOUT => 20,
	]);
	$response = curl_exec($ch);
	$errno = curl_errno($ch);
	$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($errno !== 0) {
		return ['ok' => false, 'error' => 'CAPI request failed (curl ' . $errno . ').'];
	}
	if ($http < 200 || $http >= 300) {
		return ['ok' => false, 'error' => 'CAPI HTTP ' . $http, 'response' => is_string($response) ? $response : ''];
	}

	return ['ok' => true, 'response' => is_string($response) ? $response : ''];
}

/** @return array{ok:bool,error?:string,response?:string} */
function store_analytics_meta_capi_send_test(?string $test_event_code = null): array
{
	$settings = analytics_settings_load();
	if (!$settings['meta_capi_enabled'] || $settings['meta_capi_access_token'] === '') {
		return ['ok' => false, 'error' => 'Meta Conversions API is not configured.'];
	}
	$test_code = $test_event_code ?? $settings['meta_test_event_code'];
	if ($test_code === '') {
		return ['ok' => false, 'error' => 'Add a Meta Test Event Code to run test events.'];
	}
	$event_id = 'test-' . bin2hex(random_bytes(8));
	$payload = [
		'data' => [[
			'event_name' => 'PageView',
			'event_time' => time(),
			'event_id' => $event_id,
			'action_source' => 'website',
			'user_data' => [
				'client_ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
				'client_user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
			],
		]],
		'test_event_code' => $test_code,
		'access_token' => $settings['meta_capi_access_token'],
	];
	$url = 'https://graph.facebook.com/v21.0/' . rawurlencode(STORE_ANALYTICS_META_PIXEL_ID) . '/events';
	$body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_POST => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
		CURLOPT_POSTFIELDS => $body,
		CURLOPT_TIMEOUT => 20,
	]);
	$response = curl_exec($ch);
	$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($http < 200 || $http >= 300) {
		return ['ok' => false, 'error' => 'CAPI HTTP ' . $http, 'response' => is_string($response) ? $response : ''];
	}

	return ['ok' => true, 'response' => is_string($response) ? $response : ''];
}
