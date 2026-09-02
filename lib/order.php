<?php
/**
 * Orders and payments helpers (storefront + admin).
 *
 * Tables required:
 * - orders, order_items, payments (see sql/create_orders_tables.sql)
 */
declare(strict_types=1);

function order_generate_number(): string
{
	// Example: PRL-20260318-5F2A9C
	$date = date('Ymd');
	$rand = strtoupper(bin2hex(random_bytes(3)));
	return 'PRL-' . $date . '-' . $rand;
}

function order_create(PDO $pdo, array $order, array $items): int
{
	$order_number = order_generate_number();
	$promo_id = isset($order['promo_code_id']) && (int) $order['promo_code_id'] > 0 ? (int) $order['promo_code_id'] : null;
	$store_user_id = null;
	if (array_key_exists('store_user_id', $order) && $order['store_user_id'] !== null && $order['store_user_id'] !== '') {
		$store_user_id = (int) $order['store_user_id'];
	}
	$base = [
		$order_number,
		$store_user_id,
		(string) ($order['status'] ?? 'pending_payment'),
		(string) ($order['payment_method'] ?? 'razorpay'),
		(int) ($order['subtotal_paise'] ?? 0),
		(int) ($order['shipping_paise'] ?? 0),
		(int) ($order['discount_paise'] ?? 0),
		(int) ($order['total_paise'] ?? 0),
		(string) ($order['shipping_name'] ?? ''),
		(string) ($order['shipping_phone'] ?? ''),
		(string) ($order['shipping_email'] ?? ''),
		(string) ($order['shipping_address'] ?? ''),
		(string) ($order['shipping_city'] ?? ''),
		(string) ($order['shipping_state'] ?? ''),
		(string) ($order['shipping_pincode'] ?? ''),
	];
	$order_id = null;
	if ($promo_id !== null) {
		try {
			$stmt = $pdo->prepare('INSERT INTO orders (order_number, store_user_id, status, payment_method, promo_code_id, subtotal_paise, shipping_paise, discount_paise, total_paise, shipping_name, shipping_phone, shipping_email, shipping_address, shipping_city, shipping_state, shipping_pincode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
			$stmt->execute(array_merge(array_slice($base, 0, 4), [$promo_id], array_slice($base, 4)));
			$order_id = (int) $pdo->lastInsertId();
		} catch (PDOException $e) {
			$order_id = null;
		}
	}
	if ($order_id === null) {
		$stmt = $pdo->prepare('INSERT INTO orders (order_number, store_user_id, status, payment_method, subtotal_paise, shipping_paise, discount_paise, total_paise, shipping_name, shipping_phone, shipping_email, shipping_address, shipping_city, shipping_state, shipping_pincode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute($base);
		$order_id = (int) $pdo->lastInsertId();
	}

	$item_stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, sku, product_name, unit_price_paise, quantity, line_total_paise) VALUES (?, ?, ?, ?, ?, ?, ?)');
	foreach ($items as $it) {
		$item_stmt->execute([
			$order_id,
			isset($it['product_id']) ? (int) $it['product_id'] : null,
			(string) ($it['sku'] ?? ''),
			(string) ($it['product_name'] ?? ''),
			(int) ($it['unit_price_paise'] ?? 0),
			(int) ($it['quantity'] ?? 1),
			(int) ($it['line_total_paise'] ?? 0),
		]);
	}
	return $order_id;
}

function order_find_by_id(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ? $row : null;
}

function order_find_by_number(PDO $pdo, string $order_number): ?array
{
	$stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
	$stmt->execute([$order_number]);
	$row = $stmt->fetch();
	return $row ? $row : null;
}

function order_items_list(PDO $pdo, int $order_id): array
{
	$stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
	$stmt->execute([$order_id]);
	return $stmt->fetchAll();
}

function payment_create(PDO $pdo, int $order_id, array $data): int
{
	$stmt = $pdo->prepare('INSERT INTO payments (order_id, provider, status, amount_paise, currency, provider_order_id) VALUES (?, ?, ?, ?, ?, ?)');
	$stmt->execute([
		$order_id,
		(string) ($data['provider'] ?? 'razorpay'),
		(string) ($data['status'] ?? 'created'),
		(int) ($data['amount_paise'] ?? 0),
		(string) ($data['currency'] ?? 'INR'),
		(string) ($data['provider_order_id'] ?? ''),
	]);
	return (int) $pdo->lastInsertId();
}

/**
 * Update a payment row by primary key (avoids WHERE provider=? failing on BINARY/charset mismatches → 0 rows updated).
 */
function payment_update_by_id(PDO $pdo, int $payment_id, string $provider_order_id, ?string $provider_payment_id, ?string $signature, string $status): void
{
	$stmt = $pdo->prepare("UPDATE payments SET provider_order_id = ?, provider_payment_id = ?, provider_signature = ?, status = ?, updated_at = NOW(), paid_at = IF(? = 1, NOW(), paid_at) WHERE id = ?");
	$is_paid = $status === 'paid' ? 1 : 0;
	$stmt->execute([
		$provider_order_id,
		$provider_payment_id,
		$signature,
		$status,
		$is_paid,
		$payment_id,
	]);
}

function payment_find_by_provider_order_id(PDO $pdo, string $provider_order_id): ?array
{
	$stmt = $pdo->prepare('SELECT * FROM payments WHERE provider_order_id = ? ORDER BY id DESC LIMIT 1');
	$stmt->execute([$provider_order_id]);
	$row = $stmt->fetch();
	return $row ? $row : null;
}

function payment_find_latest_by_order_id(PDO $pdo, int $order_id): ?array
{
	$stmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1');
	$stmt->execute([$order_id]);
	$row = $stmt->fetch();
	return $row ? $row : null;
}

function order_mark_paid(PDO $pdo, int $order_id): void
{
	$pdo->prepare('UPDATE orders SET status = ?, paid_at = NOW(), updated_at = NOW() WHERE id = ?')->execute(['paid', $order_id]);
}

function order_set_status(PDO $pdo, int $order_id, string $status): void
{
	$pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW(), cancelled_at = IF(? = 'cancelled', NOW(), cancelled_at) WHERE id = ?")->execute([$status, $status, $order_id]);
}

function order_list_admin(PDO $pdo, array $filters, int $page, int $per_page): array
{
	$where = [];
	$params = [];
	if (!empty($filters['status'])) {
		$where[] = 'o.status = ?';
		$params[] = (string) $filters['status'];
	}
	if (!empty($filters['payment_method'])) {
		$where[] = 'o.payment_method = ?';
		$params[] = (string) $filters['payment_method'];
	}
	$sql_where = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

	try {
		$count_stmt = $pdo->prepare('SELECT COUNT(*) FROM orders o ' . $sql_where);
		$count_stmt->execute($params);
		$total = (int) $count_stmt->fetchColumn();

		$offset = max(0, ($page - 1) * $per_page);
		$list_sql = 'SELECT o.* FROM orders o ' . $sql_where . ' ORDER BY o.id DESC LIMIT ' . (int) $per_page . ' OFFSET ' . (int) $offset;
		$list_stmt = $pdo->prepare($list_sql);
		$list_stmt->execute($params);

		return [
			'total' => $total,
			'items' => $list_stmt->fetchAll(),
		];
	} catch (PDOException $e) {
		$msg = $e->getMessage();
		$is_missing = strpos($msg, 'Base table or view not found') !== false || strpos($msg, "doesn't exist") !== false;
		return [
			'total' => 0,
			'items' => [],
			'db_missing' => $is_missing,
			'db_error' => $is_missing ? 'Orders tables not found. Run sql/create_orders_tables.sql' : 'Database error.',
		];
	}
}

/**
 * Orders for a logged-in storefront user (newest first).
 *
 * @return array{total: int, items: array<int, array>, db_missing?: bool, db_error?: string|null}
 */
function order_list_for_store_user(PDO $pdo, int $store_user_id, int $page, int $per_page): array
{
	$store_user_id = max(0, $store_user_id);
	$per_page = max(1, min(50, $per_page));
	$page = max(1, $page);

	try {
		$count_stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE store_user_id = ?');
		$count_stmt->execute([$store_user_id]);
		$total = (int) $count_stmt->fetchColumn();

		$offset = (int) max(0, ($page - 1) * $per_page);
		$lim = (int) $per_page;
		$list_stmt = $pdo->prepare(
			"SELECT id, order_number, status, payment_method, total_paise, created_at
			FROM orders WHERE store_user_id = ? ORDER BY id DESC LIMIT {$lim} OFFSET {$offset}"
		);
		$list_stmt->execute([$store_user_id]);

		return [
			'total' => $total,
			'items' => $list_stmt->fetchAll(),
		];
	} catch (PDOException $e) {
		$msg = $e->getMessage();
		$is_missing = strpos($msg, 'Base table or view not found') !== false || strpos($msg, "doesn't exist") !== false;

		return [
			'total' => 0,
			'items' => [],
			'db_missing' => $is_missing,
			'db_error' => $is_missing ? 'Orders are not set up yet.' : 'Could not load orders.',
		];
	}
}

function shiprocket_order_find_by_order_id(PDO $pdo, int $order_id): ?array
{
	try {
		$stmt = $pdo->prepare('SELECT * FROM shiprocket_orders WHERE order_id = ? LIMIT 1');
		$stmt->execute([$order_id]);
		$row = $stmt->fetch();
		return $row ? $row : null;
	} catch (PDOException $e) {
		$msg = $e->getMessage();
		$is_missing = strpos($msg, 'Base table or view not found') !== false || strpos($msg, "doesn't exist") !== false;
		if ($is_missing) {
			return null;
		}
		throw $e;
	}
}

/** Max order IDs remembered in session after checkout (same-session success page access). */
const ORDER_SUCCESS_ACCESS_SESSION_MAX = 40;

function order_success_grant(int $order_id): void
{
	if ($order_id <= 0) {
		return;
	}
	if (!isset($_SESSION['order_success_access']) || !is_array($_SESSION['order_success_access'])) {
		$_SESSION['order_success_access'] = [];
	}
	$_SESSION['order_success_access'][$order_id] = time();
	$keys = array_keys($_SESSION['order_success_access']);
	if (count($keys) > ORDER_SUCCESS_ACCESS_SESSION_MAX) {
		foreach (array_slice($keys, 0, -ORDER_SUCCESS_ACCESS_SESSION_MAX) as $old_id) {
			unset($_SESSION['order_success_access'][$old_id]);
		}
	}
}

function order_success_can_view(array $order, ?int $store_user_id): bool
{
	$order_id = (int) ($order['id'] ?? 0);
	if ($order_id <= 0) {
		return false;
	}

	$grants = $_SESSION['order_success_access'] ?? [];
	if (is_array($grants) && array_key_exists($order_id, $grants)) {
		return true;
	}

	if ($store_user_id !== null && $store_user_id > 0) {
		return (int) ($order['store_user_id'] ?? 0) === $store_user_id;
	}

	return false;
}

function shiprocket_order_upsert(PDO $pdo, int $order_id, array $data): void
{
	try {
		$current = shiprocket_order_find_by_order_id($pdo, $order_id);
		$shiprocket_order_id = (string) ($data['shiprocket_order_id'] ?? ($current['shiprocket_order_id'] ?? ''));
		$shipment_id = (string) ($data['shipment_id'] ?? ($current['shipment_id'] ?? ''));
		$awb_code = (string) ($data['awb_code'] ?? ($current['awb_code'] ?? ''));
		$status = (string) ($data['status'] ?? ($current['status'] ?? 'pending'));
		$request_payload = isset($data['request_payload']) ? (string) $data['request_payload'] : ($current['request_payload'] ?? null);
		$response_payload = isset($data['response_payload']) ? (string) $data['response_payload'] : ($current['response_payload'] ?? null);

		if ($current) {
			$stmt = $pdo->prepare("UPDATE shiprocket_orders SET shiprocket_order_id = ?, shipment_id = ?, awb_code = ?, status = ?, request_payload = ?, response_payload = ?, updated_at = NOW() WHERE order_id = ?");
			$stmt->execute([$shiprocket_order_id, $shipment_id, $awb_code, $status, $request_payload, $response_payload, $order_id]);
			return;
		}

		$stmt = $pdo->prepare("INSERT INTO shiprocket_orders (order_id, shiprocket_order_id, shipment_id, awb_code, status, request_payload, response_payload) VALUES (?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$order_id, $shiprocket_order_id, $shipment_id, $awb_code, $status, $request_payload, $response_payload]);
	} catch (PDOException $e) {
		$msg = $e->getMessage();
		$is_missing = strpos($msg, 'Base table or view not found') !== false || strpos($msg, "doesn't exist") !== false;
		if ($is_missing) {
			return;
		}
		throw $e;
	}
}

