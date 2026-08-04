<?php
/**
 * Refrens Invoice API: auth + create invoice + optional Refrens email (curl only).
 * Docs: https://www.refrens.com/api/docs/
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/order.php';
require_once __DIR__ . '/product.php';
require_once __DIR__ . '/refrens_settings.php';

function refrens_safe_log(string $message): void
{
	error_log('[Refrens] ' . $message);
}

function refrens_config(): array
{
	$s = refrens_settings_load();
	$base = rtrim((string) ($s['api_base'] ?? ''), '/');
	$url_key = trim((string) ($s['url_key'] ?? ''));
	$app_id = trim((string) ($s['app_id'] ?? ''));
	$secret = (string) ($s['app_secret'] ?? '');
	$currency = strtoupper(trim((string) ($s['currency'] ?? 'INR')));
	if ($currency === '') {
		$currency = 'INR';
	}
	$payment_method = strtoupper(trim((string) ($s['payment_method'] ?? 'UPI')));
	$allowed_pay = ['ACCOUNT_TRANSFER', 'CASH', 'CHEQUE', 'CREDIT_CARD', 'DEBIT_CARD', 'DD', 'UPI'];
	if (!in_array($payment_method, $allowed_pay, true)) {
		$payment_method = 'UPI';
	}
	$invoice_type = trim((string) ($s['invoice_type'] ?? 'INVOICE'));
	if ($invoice_type === '') {
		$invoice_type = 'INVOICE';
	}

	return [
		'enabled' => !empty($s['enabled']),
		'api_base' => $base,
		'url_key' => $url_key,
		'app_id' => $app_id,
		'app_secret' => $secret,
		'invoice_type' => $invoice_type,
		'currency' => $currency,
		'send_invoice_email' => !empty($s['send_invoice_email']),
		'invoice_cc_email' => trim((string) ($s['invoice_cc_email'] ?? '')),
		'payment_method' => $payment_method,
		'shipping_line_gst_rate' => max(0.0, (float) ($s['shipping_line_gst_rate'] ?? 18.0)),
		'seller_name' => trim((string) ($s['seller_name'] ?? '')),
		'seller_street' => trim((string) ($s['seller_street'] ?? '')),
		'seller_pincode' => trim((string) ($s['seller_pincode'] ?? '')),
		'seller_gst_state' => trim((string) ($s['seller_gst_state'] ?? '27')),
		'seller_gstin' => trim((string) ($s['seller_gstin'] ?? '')),
		'seller_country' => strtoupper(trim((string) ($s['seller_country'] ?? 'IN'))) ?: 'IN',
		'seller_email' => trim((string) ($s['seller_email'] ?? '')),
		'seller_phone' => trim((string) ($s['seller_phone'] ?? '')),
		'token_cache' => (string) (defined('REFRENS_TOKEN_CACHE_FILE') ? REFRENS_TOKEN_CACHE_FILE : ''),
		'configured' => $base !== '' && $url_key !== '' && $app_id !== '' && $secret !== '',
	];
}

/**
 * Map storefront Indian state label → GST state code (Refrens billedTo.gstState).
 */
function refrens_gst_state_code_for_name(string $state_name): string
{
	$key = strtolower(trim(preg_replace('/\s+/', ' ', $state_name)));
	$map = [
		'andaman and nicobar islands' => '35',
		'andhra pradesh' => '37',
		'arunachal pradesh' => '12',
		'assam' => '18',
		'bihar' => '10',
		'chandigarh' => '04',
		'chhattisgarh' => '22',
		'dadra and nagar haveli and daman and diu' => '26',
		'delhi' => '07',
		'goa' => '30',
		'gujarat' => '24',
		'haryana' => '06',
		'himachal pradesh' => '02',
		'jammu and kashmir' => '01',
		'jharkhand' => '20',
		'karnataka' => '29',
		'kerala' => '32',
		'ladakh' => '38',
		'lakshadweep' => '31',
		'madhya pradesh' => '23',
		'maharashtra' => '27',
		'manipur' => '14',
		'meghalaya' => '17',
		'mizoram' => '15',
		'nagaland' => '13',
		'odisha' => '21',
		'puducherry' => '34',
		'punjab' => '03',
		'rajasthan' => '08',
		'sikkim' => '11',
		'tamil nadu' => '33',
		'telangana' => '36',
		'tripura' => '16',
		'uttar pradesh' => '09',
		'uttarakhand' => '05',
		'west bengal' => '19',
	];

	return $map[$key] ?? '97';
}

function refrens_jwt_exp(string $jwt): int
{
	$parts = explode('.', $jwt);
	if (count($parts) < 2) {
		return 0;
	}
	$payload = $parts[1];
	$payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
	$json = base64_decode(strtr($payload, '-_', '+/'), true);
	if ($json === false) {
		return 0;
	}
	$data = json_decode($json, true);
	if (!is_array($data) || !isset($data['exp'])) {
		return 0;
	}

	return (int) $data['exp'];
}

function refrens_token_cache_read(string $path): ?array
{
	if ($path === '' || !is_file($path)) {
		return null;
	}
	$raw = @file_get_contents($path);
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

function refrens_token_cache_write(string $path, string $token, int $expires_at): void
{
	if ($path === '') {
		return;
	}
	$dir = dirname($path);
	if (!is_dir($dir)) {
		@mkdir($dir, 0755, true);
	}
	@file_put_contents(
		$path,
		json_encode(['token' => $token, 'expires_at' => $expires_at], JSON_UNESCAPED_SLASHES),
		LOCK_EX
	);
}

/**
 * CA bundle for HTTPS (Windows PHP often has empty curl.cainfo).
 */
function refrens_ca_bundle_path(): string
{
	if (defined('BASE_PATH')) {
		$bundled = BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem';
		if (is_file($bundled)) {
			return $bundled;
		}
	}
	$ini = trim((string) ini_get('curl.cainfo'));
	if ($ini !== '' && is_file($ini)) {
		return $ini;
	}
	$ini = trim((string) ini_get('openssl.cafile'));
	if ($ini !== '' && is_file($ini)) {
		return $ini;
	}

	return '';
}

/**
 * Store prices are GST-inclusive. Refrens `rate` is tax-exclusive and gstRate is added on top.
 */
function refrens_exclusive_unit_rate(int $line_inclusive_paise, int $qty, float $gst_percent): float
{
	$qty = max(1, $qty);
	$inclusive_rupees = max(0, $line_inclusive_paise) / 100.0;
	if ($gst_percent <= 0) {
		return round($inclusive_rupees / $qty, 2);
	}
	$exclusive_line = $inclusive_rupees / (1.0 + ($gst_percent / 100.0));

	return round($exclusive_line / $qty, 2);
}

function refrens_invoice_final_total_amount(array $invoice_data): ?float
{
	$ft = $invoice_data['finalTotal'] ?? null;
	if (is_array($ft) && isset($ft['total']) && is_numeric($ft['total'])) {
		return round((float) $ft['total'], 2);
	}
	foreach (['total', 'grandTotal', 'payableAmount', 'invoiceAmount'] as $key) {
		if (isset($invoice_data[$key]) && is_numeric($invoice_data[$key])) {
			return round((float) $invoice_data[$key], 2);
		}
	}

	return null;
}

function refrens_api_request(string $method, string $path, ?array $body, ?string $bearer): array
{
	$cfg = refrens_config();
	if ($cfg['api_base'] === '') {
		return ['ok' => false, 'status' => 0, 'error' => 'Refrens API base not configured'];
	}
	$url = $cfg['api_base'] . '/' . ltrim($path, '/');
	$ch = curl_init($url);
	if ($ch === false) {
		return ['ok' => false, 'status' => 0, 'error' => 'Unable to init curl'];
	}
	$headers = ['Content-Type: application/json'];
	if ($bearer !== null && trim($bearer) !== '') {
		$headers[] = 'Authorization: Bearer ' . trim($bearer);
	}
	$m = strtoupper($method);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_TIMEOUT, 35);
	$ca = refrens_ca_bundle_path();
	if ($ca !== '') {
		curl_setopt($ch, CURLOPT_CAINFO, $ca);
	}
	if ($m === 'GET') {
		curl_setopt($ch, CURLOPT_HTTPGET, true);
	} else {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $m);
		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
		}
	}
	$res_body = curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curl_err = curl_error($ch);
	if ($res_body === false) {
		return ['ok' => false, 'status' => $status, 'error' => $curl_err !== '' ? $curl_err : 'Request failed'];
	}
	$decoded = json_decode($res_body, true);
	if ($status >= 200 && $status < 300) {
		if (!is_array($decoded)) {
			return ['ok' => true, 'status' => $status, 'data' => [], 'raw' => $res_body];
		}

		return ['ok' => true, 'status' => $status, 'data' => $decoded, 'raw' => $res_body];
	}
	$err = 'Refrens HTTP ' . (string) $status;
	if (is_array($decoded) && !empty($decoded['message']) && is_string($decoded['message'])) {
		$err = (string) $decoded['message'];
	}

	return ['ok' => false, 'status' => $status, 'error' => $err, 'raw' => $res_body];
}

function refrens_get_access_token(): ?string
{
	$cfg = refrens_config();
	if (!$cfg['configured']) {
		return null;
	}
	$cache_file = $cfg['token_cache'];
	$now = time();
	$cached = refrens_token_cache_read($cache_file);
	if ($cached !== null && $cached['expires_at'] > $now + 120) {
		return $cached['token'];
	}
	$res = refrens_api_request('POST', '/authentication', [
		'strategy' => 'app-secret',
		'appId' => $cfg['app_id'],
		'appSecret' => $cfg['app_secret'],
	], null);
	if (empty($res['ok']) || !is_array($res['data'] ?? null)) {
		refrens_safe_log('auth failed: ' . (string) ($res['error'] ?? 'unknown'));

		return null;
	}
	$data = $res['data'];
	$token = '';
	if (!empty($data['accessToken']) && is_string($data['accessToken'])) {
		$token = trim($data['accessToken']);
	} elseif (isset($data['authentication']['accessToken']) && is_string($data['authentication']['accessToken'])) {
		$token = trim((string) $data['authentication']['accessToken']);
	}
	if ($token === '') {
		refrens_safe_log('auth response missing accessToken');

		return null;
	}
	$exp = refrens_jwt_exp($token);
	if ($exp <= 0) {
		$exp = $now + 3300;
	}
	refrens_token_cache_write($cache_file, $token, $exp - 90);

	return $token;
}

function refrens_orders_has_invoice_column(PDO $pdo): bool
{
	static $ok = null;
	if ($ok !== null) {
		return $ok;
	}
	try {
		$pdo->query('SELECT refrens_invoice_id FROM orders LIMIT 0');
		$ok = true;
	} catch (Throwable $e) {
		$ok = false;
	}

	return $ok;
}

/**
 * Reserve sync for this order (idempotent). Returns false if another worker already synced or is syncing.
 */
function refrens_try_claim_order_sync(PDO $pdo, int $order_id): bool
{
	if (!refrens_orders_has_invoice_column($pdo)) {
		return true;
	}
	try {
		$pdo->beginTransaction();
		$stmt = $pdo->prepare('SELECT refrens_invoice_id FROM orders WHERE id = ? FOR UPDATE');
		$stmt->execute([$order_id]);
		$row = $stmt->fetch();
		if (!$row) {
			$pdo->rollBack();

			return false;
		}
		$cur = $row['refrens_invoice_id'] ?? null;
		$cur = $cur !== null ? trim((string) $cur) : '';
		if ($cur !== '' && $cur !== 'PENDING') {
			$pdo->rollBack();

			return false;
		}
		if ($cur === 'PENDING') {
			$pdo->rollBack();

			return false;
		}
		$pdo->prepare('UPDATE orders SET refrens_invoice_id = ? WHERE id = ?')->execute(['PENDING', $order_id]);
		$pdo->commit();

		return true;
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		refrens_safe_log('claim failed order_id=' . $order_id . ' ' . $e->getMessage());

		return true;
	}
}

function refrens_clear_pending(PDO $pdo, int $order_id): void
{
	if (!refrens_orders_has_invoice_column($pdo)) {
		return;
	}
	try {
		$pdo->prepare('UPDATE orders SET refrens_invoice_id = NULL WHERE id = ? AND refrens_invoice_id = ?')->execute([$order_id, 'PENDING']);
	} catch (Throwable $e) {
		// ignore
	}
}

function refrens_save_invoice_id(PDO $pdo, int $order_id, string $invoice_id): void
{
	if (!refrens_orders_has_invoice_column($pdo)) {
		return;
	}
	$pdo->prepare('UPDATE orders SET refrens_invoice_id = ? WHERE id = ?')->execute([$invoice_id, $order_id]);
}

function refrens_normalize_phone(?string $phone): ?string
{
	$p = trim((string) $phone);
	if ($p === '') {
		return null;
	}
	if ($p[0] === '+') {
		return $p;
	}
	$digits = preg_replace('/\D+/', '', $p);
	if ($digits === null) {
		return null;
	}
	if (strlen($digits) === 10) {
		return '+91' . $digits;
	}
	if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
		return '+' . $digits;
	}

	return $p;
}

/**
 * Allocate promo discount across product lines (order_items are pre-promo); returns adjusted line totals in paise.
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, int> line_total_paise aligned with $items order
 */
function refrens_adjust_line_totals_for_discount(array $items, int $discount_paise): array
{
	$n = count($items);
	if ($n === 0) {
		return [];
	}
	$sub = 0;
	foreach ($items as $it) {
		$sub += (int) ($it['line_total_paise'] ?? 0);
	}
	$discount_paise = max(0, $discount_paise);
	if ($sub <= 0 || $discount_paise === 0) {
		$out = [];
		foreach ($items as $it) {
			$out[] = (int) ($it['line_total_paise'] ?? 0);
		}

		return $out;
	}
	$target = max(0, $sub - $discount_paise);
	$out = [];
	$acc = 0;
	for ($i = 0; $i < $n; $i++) {
		$line = (int) ($items[$i]['line_total_paise'] ?? 0);
		if ($i < $n - 1) {
			$adj = (int) round($line * $target / $sub);
			$out[] = $adj;
			$acc += $adj;
		} else {
			$out[] = max(0, $target - $acc);
		}
	}

	return $out;
}

/**
 * After payment success: create Refrens invoice, mark paid, and optionally email.
 */
function refrens_create_invoice_for_paid_order(PDO $pdo, int $order_id): void
{
	refrens_sync_invoice_for_order($pdo, $order_id, true);
}

/**
 * Create Refrens tax invoice for an order.
 * @param bool $mark_paid When true (Razorpay), record payment so the invoice is PAID. COD should pass false.
 */
function refrens_sync_invoice_for_order(PDO $pdo, int $order_id, bool $mark_paid = true): void
{
	$cfg = refrens_config();
	if (!$cfg['enabled'] || !$cfg['configured']) {
		return;
	}
	$release_lock_name = null;
	if (refrens_orders_has_invoice_column($pdo)) {
		if (!refrens_try_claim_order_sync($pdo, $order_id)) {
			return;
		}
	} else {
		$lock_name = 'pearlica_refrens_invoice_' . $order_id;
		try {
			$lk = $pdo->prepare('SELECT GET_LOCK(?, 25)');
			$lk->execute([$lock_name]);
			if ((int) $lk->fetchColumn() !== 1) {
				return;
			}
			$release_lock_name = $lock_name;
		} catch (Throwable $e) {
			refrens_safe_log('GET_LOCK failed order_id=' . $order_id . ' ' . $e->getMessage());

			return;
		}
	}
	try {
		try {
			refrens_create_invoice_for_paid_order_body($pdo, $order_id, $cfg, $mark_paid);
		} finally {
			if ($release_lock_name !== null) {
				try {
					$rel = $pdo->prepare('SELECT RELEASE_LOCK(?)');
					$rel->execute([$release_lock_name]);
				} catch (Throwable $e) {
					// ignore
				}
			}
		}
	} catch (Throwable $e) {
		refrens_safe_log('invoice sync error order_id=' . $order_id . ' ' . $e->getMessage());
		refrens_clear_pending($pdo, $order_id);
	}
}

function refrens_create_invoice_for_paid_order_body(PDO $pdo, int $order_id, array $cfg, bool $mark_paid = true): void
{
	$order = order_find_by_id($pdo, $order_id);
	if (!$order) {
		refrens_clear_pending($pdo, $order_id);

		return;
	}
	$items = order_items_list($pdo, $order_id);
	if ($items === []) {
		refrens_clear_pending($pdo, $order_id);
		refrens_safe_log('no items order_id=' . $order_id);

		return;
	}
	$email = trim((string) ($order['shipping_email'] ?? ''));
	if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		refrens_clear_pending($pdo, $order_id);
		refrens_safe_log('skip invoice: invalid email order_id=' . $order_id);

		return;
	}
	$token = refrens_get_access_token();
	if ($token === null) {
		refrens_clear_pending($pdo, $order_id);

		return;
	}
	$discount_paise = (int) ($order['discount_paise'] ?? 0);
	$adjusted_totals = refrens_adjust_line_totals_for_discount($items, $discount_paise);
	$refrens_items = [];
	foreach ($items as $idx => $it) {
		$qty = max(1, (int) ($it['quantity'] ?? 1));
		$line_paise = $adjusted_totals[$idx] ?? (int) ($it['line_total_paise'] ?? 0);
		$gst = 3.0;
		$pid = isset($it['product_id']) ? (int) $it['product_id'] : 0;
		if ($pid > 0) {
			$prod = product_find_by_id($pdo, $pid);
			if (is_array($prod) && isset($prod['gst_percent']) && $prod['gst_percent'] !== null && $prod['gst_percent'] !== '') {
				$gst = (float) $prod['gst_percent'];
			}
		}
		$refrens_items[] = [
			'name' => (string) ($it['product_name'] ?? 'Item'),
			'rate' => refrens_exclusive_unit_rate($line_paise, $qty, $gst),
			'quantity' => $qty,
			'gstRate' => $gst,
		];
	}
	$shipping_paise = (int) ($order['shipping_paise'] ?? 0);
	if ($shipping_paise > 0) {
		$ship_gst = (float) ($cfg['shipping_line_gst_rate'] ?? 18.0);
		$refrens_items[] = [
			'name' => 'Gift packaging & handling',
			'rate' => refrens_exclusive_unit_rate($shipping_paise, 1, $ship_gst),
			'quantity' => 1,
			'gstRate' => $ship_gst,
		];
	}
	$cust_state = trim((string) ($order['shipping_state'] ?? ''));
	$gst_state = refrens_gst_state_code_for_name($cust_state);
	$street_parts = array_filter([
		trim((string) ($order['shipping_address'] ?? '')),
		trim((string) ($order['shipping_city'] ?? '')),
		trim((string) ($order['shipping_state'] ?? '')),
	], static fn(string $p): bool => $p !== '');
	$billed_to = [
		'name' => trim((string) ($order['shipping_name'] ?? 'Customer')),
		'street' => implode("\n", $street_parts),
		'pincode' => trim((string) ($order['shipping_pincode'] ?? '')),
		'gstState' => $gst_state,
		'country' => 'IN',
		'email' => $email,
	];
	$ph = refrens_normalize_phone((string) ($order['shipping_phone'] ?? ''));
	if ($ph !== null) {
		$billed_to['phone'] = $ph;
	}
	$seller = [
		'name' => (string) (($cfg['seller_name'] ?? '') !== '' ? $cfg['seller_name'] : 'Pearlica'),
		'street' => (string) ($cfg['seller_street'] ?? ''),
		'pincode' => (string) ($cfg['seller_pincode'] ?? ''),
		'gstState' => (string) (($cfg['seller_gst_state'] ?? '') !== '' ? $cfg['seller_gst_state'] : '27'),
		'country' => (string) (($cfg['seller_country'] ?? '') !== '' ? $cfg['seller_country'] : 'IN'),
		'gstin' => (string) ($cfg['seller_gstin'] ?? ''),
		'email' => (string) ($cfg['seller_email'] ?? ''),
	];
	$seller_phone = trim((string) ($cfg['seller_phone'] ?? ''));
	if ($seller_phone !== '') {
		$seller['phone'] = $seller_phone;
	}
	$tz = new DateTimeZone('Asia/Kolkata');
	$now = new DateTimeImmutable('now', $tz);
	$invoice_date = $now->format('c');
	$order_number = (string) ($order['order_number'] ?? '');
	$payload = [
		'invoiceTitle' => 'Tax invoice',
		'invoiceSubTitle' => $order_number,
		'invoiceNumber' => $order_number,
		'invoiceDate' => $invoice_date,
		'dueDate' => $invoice_date,
		'invoiceType' => (string) ($cfg['invoice_type'] ?? 'INVOICE'),
		'currency' => (string) ($cfg['currency'] ?? 'INR'),
		'billedTo' => $billed_to,
		'billedBy' => $seller,
		'items' => $refrens_items,
	];
	$path = '/businesses/' . rawurlencode($cfg['url_key']) . '/invoices';
	$res = refrens_api_request('POST', $path, $payload, $token);
	if (empty($res['ok']) || !is_array($res['data'] ?? null)) {
		refrens_safe_log(
			'create invoice failed order_id=' . $order_id . ' ' . (string) ($res['error'] ?? '') . ' raw=' . substr((string) ($res['raw'] ?? ''), 0, 500)
		);
		refrens_clear_pending($pdo, $order_id);

		return;
	}
	$data = $res['data'];
	$inv_id = '';
	if (!empty($data['_id']) && is_string($data['_id'])) {
		$inv_id = trim($data['_id']);
	} elseif (isset($data['invoice']['_id']) && is_string($data['invoice']['_id'])) {
		$inv_id = trim((string) $data['invoice']['_id']);
	}
	if ($inv_id === '') {
		refrens_safe_log('create invoice OK but no _id order_id=' . $order_id);
		refrens_clear_pending($pdo, $order_id);

		return;
	}
	refrens_save_invoice_id($pdo, $order_id, $inv_id);

	if ($mark_paid) {
		$pay_amount = refrens_invoice_final_total_amount($data);
		$pay_res = refrens_add_payment_to_invoice($pdo, $cfg, $token, $inv_id, $order_id, $order, $invoice_date, $pay_amount);
		if (empty($pay_res['ok'])) {
			refrens_safe_log(
				'add invoice payment failed order_id=' . $order_id . ' inv=' . $inv_id . ' ' . (string) ($pay_res['error'] ?? '')
				. ' raw=' . substr((string) ($pay_res['raw'] ?? ''), 0, 600)
			);

			return;
		}
	}

	if (!empty($cfg['send_invoice_email'])) {
		$em_res = refrens_send_invoice_email($cfg, $token, $inv_id, $email, $billed_to['name']);
		if (empty($em_res['ok'])) {
			refrens_safe_log(
				'invoice email failed order_id=' . $order_id . ' inv=' . $inv_id . ' ' . (string) ($em_res['error'] ?? '')
				. ' raw=' . substr((string) ($em_res['raw'] ?? ''), 0, 600)
			);
		}
	}
}

/**
 * Record full payment on the invoice so Refrens shows it as paid.
 * @see https://www.refrens.com/api/docs/payment-updates/
 */
function refrens_add_payment_to_invoice(
	PDO $pdo,
	array $cfg,
	string $token,
	string $invoice_id,
	int $order_id,
	array $order,
	string $payment_date_iso,
	?float $amount_override = null
): array {
	$payment_row = payment_find_latest_by_order_id($pdo, $order_id);
	$ref_id = '';
	if (is_array($payment_row) && !empty($payment_row['provider_payment_id'])) {
		$ref_id = trim((string) $payment_row['provider_payment_id']);
	}
	if ($ref_id === '') {
		$ref_id = trim((string) ($order['order_number'] ?? ''));
	}
	if ($amount_override !== null && $amount_override >= 0) {
		$amount = round($amount_override, 2);
	} else {
		$total_paise = (int) ($order['total_paise'] ?? 0);
		$amount = round(max(0, $total_paise) / 100, 2);
	}
	$pay_method_order = strtolower(trim((string) ($order['payment_method'] ?? '')));
	if ($pay_method_order === 'cod') {
		$method = 'CASH';
		$notes = 'Pearlica COD order ' . (string) ($order['order_number'] ?? '');
	} else {
		$method = (string) ($cfg['payment_method'] ?? 'UPI');
		$notes = 'Pearlica web order ' . (string) ($order['order_number'] ?? '') . ' (Razorpay)';
	}
	$body = [
		'amount' => $amount,
		'paymentDate' => $payment_date_iso,
		'paymentMethod' => $method,
		'refId' => $ref_id,
		'notes' => $notes,
	];
	$path = '/businesses/' . rawurlencode($cfg['url_key']) . '/invoices/' . rawurlencode($invoice_id) . '/payments';

	return refrens_api_request('POST', $path, $body, $token);
}

/**
 * Send invoice PDF/link by email after payment is recorded (shows paid in the message).
 * @see https://help.refrens.com/en/article/how-to-trigger-emails-for-all-your-documents-via-api-1khpmic/
 */
function refrens_send_invoice_email(array $cfg, string $token, string $invoice_id, string $to_email, string $to_name): array
{
	$to_email = trim($to_email);
	$to_name = trim($to_name);
	if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
		return ['ok' => false, 'status' => 0, 'error' => 'Invalid recipient email'];
	}
	$payload = [
		'to' => [
			'email' => $to_email,
			'name' => $to_name !== '' ? $to_name : $to_email,
		],
	];
	$cc_raw = trim((string) ($cfg['invoice_cc_email'] ?? ''));
	if ($cc_raw !== '' && filter_var($cc_raw, FILTER_VALIDATE_EMAIL)
		&& strcasecmp($cc_raw, $to_email) !== 0) {
		$payload['cc'] = [
			[
				'email' => $cc_raw,
				'name' => (string) (($cfg['seller_name'] ?? '') !== '' ? $cfg['seller_name'] : 'Pearlica'),
			],
		];
	}
	$path = '/businesses/' . rawurlencode($cfg['url_key']) . '/invoices/' . rawurlencode($invoice_id) . '/email';

	return refrens_api_request('POST', $path, $payload, $token);
}

/**
 * When COD (or other unpaid) invoice should be marked paid — e.g. admin sets status to paid.
 * Creates the invoice if missing; otherwise records payment on the existing Refrens invoice.
 */
function refrens_mark_order_invoice_paid(PDO $pdo, int $order_id): void
{
	$cfg = refrens_config();
	if (!$cfg['enabled'] || !$cfg['configured']) {
		return;
	}
	$order = order_find_by_id($pdo, $order_id);
	if (!$order) {
		return;
	}
	$inv_id = '';
	if (refrens_orders_has_invoice_column($pdo)) {
		$inv_id = trim((string) ($order['refrens_invoice_id'] ?? ''));
	}
	if ($inv_id === '' || $inv_id === 'PENDING') {
		refrens_sync_invoice_for_order($pdo, $order_id, true);

		return;
	}
	$token = refrens_get_access_token();
	if ($token === null) {
		return;
	}
	$get = refrens_api_request(
		'GET',
		'/businesses/' . rawurlencode($cfg['url_key']) . '/invoices/' . rawurlencode($inv_id),
		null,
		$token
	);
	if (empty($get['ok']) || !is_array($get['data'] ?? null)) {
		refrens_safe_log('mark paid: get invoice failed order_id=' . $order_id . ' inv=' . $inv_id);

		return;
	}
	$data = $get['data'];
	$status = strtoupper((string) ($data['status'] ?? ''));
	if ($status === 'PAID') {
		return;
	}
	$amount = refrens_invoice_final_total_amount($data);
	$tz = new DateTimeZone('Asia/Kolkata');
	$pay_date = (new DateTimeImmutable('now', $tz))->format('c');
	$pay_res = refrens_add_payment_to_invoice($pdo, $cfg, $token, $inv_id, $order_id, $order, $pay_date, $amount);
	if (empty($pay_res['ok'])) {
		refrens_safe_log(
			'mark paid failed order_id=' . $order_id . ' inv=' . $inv_id . ' ' . (string) ($pay_res['error'] ?? '')
			. ' raw=' . substr((string) ($pay_res['raw'] ?? ''), 0, 600)
		);
	}
}

