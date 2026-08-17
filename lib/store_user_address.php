<?php
/**
 * Saved delivery addresses for storefront users.
 */
declare(strict_types=1);

require_once __DIR__ . '/store_user.php';

function store_user_addresses_available(PDO $pdo): bool
{
	static $available = null;
	if ($available !== null) {
		return $available;
	}
	try {
		$pdo->query('SELECT 1 FROM store_user_addresses LIMIT 1');
		$available = true;
	} catch (PDOException $e) {
		$available = false;
	}

	return $available;
}

function store_user_address_row_normalize(array $row): array
{
	$row['address_line_2'] = $row['address_line_2'] ?? '';
	$row['label'] = $row['label'] ?? '';
	$row['is_default'] = !empty($row['is_default']);

	return $row;
}

function store_user_address_list(PDO $pdo, int $user_id): array
{
	if (!store_user_addresses_available($pdo)) {
		return [];
	}
	$stmt = $pdo->prepare('SELECT * FROM store_user_addresses WHERE store_user_id = ? ORDER BY is_default DESC, id DESC');
	$stmt->execute([$user_id]);
	$rows = $stmt->fetchAll();
	if (!is_array($rows)) {
		return [];
	}

	return array_map('store_user_address_row_normalize', $rows);
}

function store_user_address_find(PDO $pdo, int $user_id, int $address_id): ?array
{
	if (!store_user_addresses_available($pdo)) {
		return null;
	}
	$stmt = $pdo->prepare('SELECT * FROM store_user_addresses WHERE id = ? AND store_user_id = ? LIMIT 1');
	$stmt->execute([$address_id, $user_id]);
	$row = $stmt->fetch();
	if (!$row) {
		return null;
	}

	return store_user_address_row_normalize($row);
}

function store_user_address_for_client(array $row): array
{
	return [
		'id' => (int) ($row['id'] ?? 0),
		'label' => (string) ($row['label'] ?? ''),
		'full_name' => (string) ($row['full_name'] ?? ''),
		'phone' => (string) ($row['phone'] ?? ''),
		'address' => (string) ($row['address'] ?? ''),
		'address_line_2' => (string) ($row['address_line_2'] ?? ''),
		'city' => (string) ($row['city'] ?? ''),
		'state' => (string) ($row['state'] ?? ''),
		'pincode' => (string) ($row['pincode'] ?? ''),
		'is_default' => !empty($row['is_default']),
	];
}

function store_user_address_count(PDO $pdo, int $user_id): int
{
	if (!store_user_addresses_available($pdo)) {
		return 0;
	}
	$stmt = $pdo->prepare('SELECT COUNT(*) FROM store_user_addresses WHERE store_user_id = ?');
	$stmt->execute([$user_id]);

	return (int) $stmt->fetchColumn();
}

function store_user_address_create(PDO $pdo, int $user_id, array $data, bool $set_default = false): int
{
	if (!store_user_addresses_available($pdo)) {
		throw new RuntimeException('Saved addresses are not available. Please run the store auth migration.');
	}
	$is_first = store_user_address_count($pdo, $user_id) === 0;
	$make_default = $set_default || $is_first;
	if ($make_default) {
		$pdo->prepare('UPDATE store_user_addresses SET is_default = 0 WHERE store_user_id = ?')->execute([$user_id]);
	}
	$stmt = $pdo->prepare(
		'INSERT INTO store_user_addresses (store_user_id, label, full_name, phone, address, address_line_2, city, state, pincode, is_default)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
	);
	$stmt->execute([
		$user_id,
		trim((string) ($data['label'] ?? '')) ?: null,
		trim((string) ($data['full_name'] ?? '')),
		trim((string) ($data['phone'] ?? '')),
		trim((string) ($data['address'] ?? '')),
		trim((string) ($data['address_line_2'] ?? '')),
		trim((string) ($data['city'] ?? '')),
		trim((string) ($data['state'] ?? '')),
		preg_replace('/\D+/', '', (string) ($data['pincode'] ?? '')),
		$make_default ? 1 : 0,
	]);

	return (int) $pdo->lastInsertId();
}

function store_user_address_sync_profile(PDO $pdo, int $user_id, array $address_row): void
{
	store_user_update($pdo, $user_id, [
		'address' => (string) ($address_row['address'] ?? ''),
		'address_line_2' => (string) ($address_row['address_line_2'] ?? ''),
		'city' => (string) ($address_row['city'] ?? ''),
		'state' => (string) ($address_row['state'] ?? ''),
		'pincode' => (string) ($address_row['pincode'] ?? ''),
	]);
}

function store_user_address_get_default(PDO $pdo, int $user_id): ?array
{
	if (!store_user_addresses_available($pdo)) {
		return null;
	}
	$stmt = $pdo->prepare('SELECT * FROM store_user_addresses WHERE store_user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1');
	$stmt->execute([$user_id]);
	$row = $stmt->fetch();
	if (!$row) {
		return null;
	}

	return store_user_address_row_normalize($row);
}

function store_user_has_saved_address(PDO $pdo, int $user_id): bool
{
	return store_user_address_count($pdo, $user_id) > 0;
}
