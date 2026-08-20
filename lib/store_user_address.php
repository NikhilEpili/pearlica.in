<?php
/**
 * Saved delivery addresses for storefront users.
 */
declare(strict_types=1);

require_once __DIR__ . '/store_user.php';

function store_user_id_column_sql_type(PDO $pdo): string
{
	$col = $pdo->query("SHOW COLUMNS FROM `store_users` LIKE 'id'")->fetch();
	$type = strtolower((string) ($col['Type'] ?? 'int'));
	return str_contains($type, 'unsigned') ? 'INT UNSIGNED' : 'INT';
}

function store_user_address_ensure_schema(PDO $pdo): void
{
	static $done = false;
	if ($done) {
		return;
	}

	store_user_ensure_schema($pdo);
	$id_type = store_user_id_column_sql_type($pdo);
	$sql = 'CREATE TABLE IF NOT EXISTS `store_user_addresses` (
		  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  `store_user_id` ' . $id_type . ' NOT NULL,
		  `label` VARCHAR(80) NULL DEFAULT NULL,
		  `full_name` VARCHAR(255) NOT NULL DEFAULT \'\',
		  `phone` VARCHAR(100) NULL DEFAULT NULL,
		  `address` VARCHAR(255) NOT NULL,
		  `address_line_2` VARCHAR(255) NULL DEFAULT NULL,
		  `city` VARCHAR(120) NOT NULL,
		  `state` VARCHAR(120) NOT NULL,
		  `pincode` VARCHAR(20) NOT NULL,
		  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
		  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  KEY `idx_store_user_addresses_user` (`store_user_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

	try {
		$pdo->exec($sql);
	} catch (Throwable $e) {
		error_log('Pearlica store_user_addresses create: ' . $e->getMessage());
	}

	$exists = $pdo->query("SHOW TABLES LIKE 'store_user_addresses'");
	if (!$exists || !$exists->fetch()) {
		$done = true;
		return;
	}

	$fk = $pdo->query(
		"SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = 'store_user_addresses'
		   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
		   AND CONSTRAINT_NAME = 'fk_store_user_addresses_user'"
	);
	if ($fk && !$fk->fetch()) {
		try {
			$pdo->exec(
				'ALTER TABLE `store_user_addresses`
				 ADD CONSTRAINT `fk_store_user_addresses_user`
				 FOREIGN KEY (`store_user_id`) REFERENCES `store_users` (`id`) ON DELETE CASCADE'
			);
		} catch (Throwable $e) {
			error_log('Pearlica store_user_addresses fk: ' . $e->getMessage());
		}
	}

	$done = true;
}

function store_user_addresses_available(PDO $pdo): bool
{
	static $available = null;
	if ($available !== null) {
		return $available;
	}
	try {
		store_user_address_ensure_schema($pdo);
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

/**
 * If the profile has a complete shipping address but no saved-address rows yet,
 * create one so checkout can list/select it instead of forcing a blank form.
 */
function store_user_address_ensure_from_profile(PDO $pdo, array $user): ?array
{
	$user_id = (int) ($user['id'] ?? 0);
	if ($user_id <= 0 || !store_user_addresses_available($pdo)) {
		return null;
	}
	$existing = store_user_address_get_default($pdo, $user_id);
	if ($existing) {
		return $existing;
	}

	$address = trim((string) ($user['address'] ?? ''));
	$city = trim((string) ($user['city'] ?? ''));
	$state = trim((string) ($user['state'] ?? ''));
	$pincode = preg_replace('/\D+/', '', (string) ($user['pincode'] ?? ''));
	if ($address === '' || $city === '' || $state === '' || strlen((string) $pincode) !== 6) {
		return null;
	}

	try {
		$address_id = store_user_address_create($pdo, $user_id, [
			'label' => 'Home',
			'full_name' => trim((string) ($user['full_name'] ?? '')),
			'phone' => trim((string) ($user['phone'] ?? '')),
			'address' => $address,
			'address_line_2' => trim((string) ($user['address_line_2'] ?? '')),
			'city' => $city,
			'state' => $state,
			'pincode' => $pincode,
		], true);

		return store_user_address_find($pdo, $user_id, $address_id);
	} catch (Throwable $e) {
		error_log('Pearlica ensure address from profile: ' . $e->getMessage());

		return null;
	}
}

/**
 * Prefer the session checkout selection, else default/profile-backed saved address.
 * Also seeds $_SESSION['checkout_address_id'] when a saved address is available.
 */
function store_user_address_resolve_for_checkout(PDO $pdo, int $user_id, array $user): ?array
{
	store_user_address_ensure_from_profile($pdo, $user);
	$selected_id = isset($_SESSION['checkout_address_id']) ? (int) $_SESSION['checkout_address_id'] : 0;
	$row = null;
	if ($selected_id > 0) {
		$row = store_user_address_find($pdo, $user_id, $selected_id);
	}
	if (!$row) {
		$row = store_user_address_get_default($pdo, $user_id);
	}
	if ($row) {
		$_SESSION['checkout_address_id'] = (int) $row['id'];
	}

	return $row;
}
