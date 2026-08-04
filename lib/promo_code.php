<?php
/**
 * Promo code validation and discount calculation.
 * Table: promo_codes (see migrations/create_promo_codes_table.sql)
 */
declare(strict_types=1);

/**
 * Find active promo by code (case-insensitive).
 */
function promo_code_find_by_code(PDO $pdo, string $code): ?array
{
	$code = trim($code);
	if ($code === '') {
		return null;
	}
	$stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE is_active = 1 AND UPPER(TRIM(code)) = UPPER(?) LIMIT 1');
	$stmt->execute([$code]);
	$row = $stmt->fetch();
	return $row ? $row : null;
}

/**
 * Validate promo for a given subtotal (paise). Returns [ 'valid' => bool, 'discount_paise' => int, 'message' => string ].
 */
function promo_code_validate(PDO $pdo, string $code, int $subtotal_paise): array
{
	$promo = promo_code_find_by_code($pdo, $code);
	if (!$promo) {
		return ['valid' => false, 'discount_paise' => 0, 'message' => 'Invalid or inactive code.'];
	}

	$now = date('Y-m-d H:i:s');
	if ($promo['valid_from'] !== null && $promo['valid_from'] !== '' && (string) $promo['valid_from'] > $now) {
		return ['valid' => false, 'discount_paise' => 0, 'message' => 'This code is not yet valid.'];
	}
	if ($promo['valid_until'] !== null && $promo['valid_until'] !== '' && (string) $promo['valid_until'] < $now) {
		return ['valid' => false, 'discount_paise' => 0, 'message' => 'This code has expired.'];
	}

	$min = (int) ($promo['min_order_paise'] ?? 0);
	if ($min > 0 && $subtotal_paise < $min) {
		return ['valid' => false, 'discount_paise' => 0, 'message' => 'Minimum order amount not met.'];

	}

	$limit = isset($promo['usage_limit']) && $promo['usage_limit'] !== null ? (int) $promo['usage_limit'] : null;
	$used = (int) ($promo['used_count'] ?? 0);
	if ($limit !== null && $used >= $limit) {
		return ['valid' => false, 'discount_paise' => 0, 'message' => 'This code has reached its usage limit.'];
	}

	$type = (string) ($promo['discount_type'] ?? 'percent');
	$value = (float) ($promo['discount_value'] ?? 0);
	$discount_paise = 0;

	if ($type === 'percent') {
		$value = min(100, max(0, $value));
		$discount_paise = (int) round($subtotal_paise * $value / 100);
	} else {
		$discount_paise = (int) min($value, $subtotal_paise);
	}

	$discount_paise = max(0, $discount_paise);

	return [
		'valid' => true,
		'discount_paise' => $discount_paise,
		'message' => 'Code applied.',
		'promo_id' => (int) $promo['id'],
		'promo_code' => (string) $promo['code'],
	];
}

/**
 * Increment used_count for a promo. Call after order is placed.
 */
function promo_code_increment_used(PDO $pdo, int $promo_id): void
{
	$pdo->prepare('UPDATE promo_codes SET used_count = used_count + 1, updated_at = NOW() WHERE id = ?')->execute([$promo_id]);
}

function promo_code_list_all(PDO $pdo): array
{
	$stmt = $pdo->query('SELECT * FROM promo_codes ORDER BY id DESC');
	return $stmt ? $stmt->fetchAll() : [];
}

function promo_code_find_by_id(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE id = ? LIMIT 1');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ? $row : null;
}

function promo_code_create(PDO $pdo, array $data): int
{
	$stmt = $pdo->prepare('INSERT INTO promo_codes (code, description, discount_type, discount_value, min_order_paise, valid_from, valid_until, usage_limit, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
	$stmt->execute([
		trim((string) ($data['code'] ?? '')),
		trim((string) ($data['description'] ?? '')),
		(string) ($data['discount_type'] ?? 'percent'),
		(float) ($data['discount_value'] ?? 0),
		(int) ($data['min_order_paise'] ?? 0),
		$data['valid_from'] ?? null,
		$data['valid_until'] ?? null,
		isset($data['usage_limit']) && $data['usage_limit'] !== '' ? (int) $data['usage_limit'] : null,
		!empty($data['is_active']) ? 1 : 0,
	]);
	return (int) $pdo->lastInsertId();
}

function promo_code_update(PDO $pdo, int $id, array $data): void
{
	$stmt = $pdo->prepare('UPDATE promo_codes SET code = ?, description = ?, discount_type = ?, discount_value = ?, min_order_paise = ?, valid_from = ?, valid_until = ?, usage_limit = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
	$stmt->execute([
		trim((string) ($data['code'] ?? '')),
		trim((string) ($data['description'] ?? '')),
		(string) ($data['discount_type'] ?? 'percent'),
		(float) ($data['discount_value'] ?? 0),
		(int) ($data['min_order_paise'] ?? 0),
		$data['valid_from'] ?? null,
		$data['valid_until'] ?? null,
		isset($data['usage_limit']) && $data['usage_limit'] !== '' ? (int) $data['usage_limit'] : null,
		!empty($data['is_active']) ? 1 : 0,
		$id,
	]);
}

function promo_code_delete(PDO $pdo, int $id): void
{
	$pdo->prepare('DELETE FROM promo_codes WHERE id = ?')->execute([$id]);
}
