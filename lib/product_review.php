<?php
/**
 * Storefront product reviews (table: product_reviews — see database/product_reviews.sql).
 */
declare(strict_types=1);

/**
 * Order must be past checkout; excludes pending payment, cancelled, refunded.
 *
 * @return list<string>
 */
function product_review_purchase_statuses(): array
{
	return ['paid', 'processing', 'shipped', 'delivered'];
}

function product_review_user_has_purchased(PDO $pdo, int $store_user_id, int $product_id): bool
{
	if ($store_user_id <= 0 || $product_id <= 0) {
		return false;
	}
	$statuses = product_review_purchase_statuses();
	$ph = implode(',', array_fill(0, count($statuses), '?'));
	$sql = "SELECT 1 FROM order_items oi
		INNER JOIN orders o ON o.id = oi.order_id
		WHERE o.store_user_id = ? AND oi.product_id = ?
		AND o.status IN ({$ph})
		LIMIT 1";
	$params = array_merge([$store_user_id, $product_id], $statuses);
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	return (bool) $stmt->fetchColumn();
}

function product_review_exists_for_user(PDO $pdo, int $store_user_id, int $product_id): bool
{
	$stmt = $pdo->prepare('SELECT 1 FROM product_reviews WHERE product_id = ? AND store_user_id = ? LIMIT 1');
	$stmt->execute([$product_id, $store_user_id]);
	return (bool) $stmt->fetchColumn();
}

function product_review_average_and_count(PDO $pdo, int $product_id): array
{
	$stmt = $pdo->prepare('SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM product_reviews WHERE product_id = ? AND is_approved = 1');
	$stmt->execute([$product_id]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
	$avg = isset($row['avg_r']) && $row['avg_r'] !== null ? (float) $row['avg_r'] : 0.0;
	$cnt = (int) ($row['cnt'] ?? 0);
	return ['average' => round($avg, 2), 'count' => $cnt];
}

/**
 * @return list<array<string,mixed>>
 */
function product_review_list_for_product(PDO $pdo, int $product_id, int $limit = 50): array
{
	$limit = max(1, min(100, $limit));
	$stmt = $pdo->prepare('SELECT id, rating, author_name, body, created_at FROM product_reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC LIMIT ' . (int) $limit);
	$stmt->execute([$product_id]);
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Verified buyer only; one review per store user per product. Sets store_user_id.
 */
function product_review_insert_verified_buyer(PDO $pdo, int $product_id, int $store_user_id, int $rating, string $author_name, string $body): bool
{
	if ($store_user_id <= 0 || $rating < 1 || $rating > 5) {
		return false;
	}
	if (!product_review_user_has_purchased($pdo, $store_user_id, $product_id)) {
		return false;
	}
	if (product_review_exists_for_user($pdo, $store_user_id, $product_id)) {
		return false;
	}
	$author_name = trim($author_name);
	if ($author_name === '') {
		$author_name = 'Customer';
	}
	if (strlen($author_name) > 120) {
		$author_name = substr($author_name, 0, 120);
	}
	$body = trim($body);
	if (strlen($body) > 5000) {
		$body = substr($body, 0, 5000);
	}
	$stmt = $pdo->prepare('INSERT INTO product_reviews (product_id, store_user_id, rating, author_name, body, is_approved) VALUES (?, ?, ?, ?, ?, 1)');
	return $stmt->execute([$product_id, $store_user_id, $rating, $author_name, $body !== '' ? $body : null]);
}

/**
 * Admin: all reviews for a product (any approval state).
 *
 * @return list<array<string, mixed>>
 */
function product_review_list_for_product_admin(PDO $pdo, int $product_id, int $limit = 200): array
{
	if ($product_id <= 0) {
		return [];
	}
	$limit = max(1, min(500, $limit));
	$stmt = $pdo->prepare(
		'SELECT id, product_id, store_user_id, rating, author_name, body, is_approved, created_at
		FROM product_reviews WHERE product_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit
	);
	$stmt->execute([$product_id]);
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array<string, mixed>|null
 */
function product_review_find_for_admin(PDO $pdo, int $review_id, int $product_id): ?array
{
	if ($review_id <= 0 || $product_id <= 0) {
		return null;
	}
	$stmt = $pdo->prepare(
		'SELECT id, product_id, store_user_id, rating, author_name, body, is_approved, created_at
		FROM product_reviews WHERE id = ? AND product_id = ? LIMIT 1'
	);
	$stmt->execute([$review_id, $product_id]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

/** Admin / curated review (store_user_id NULL); shows on storefront when approved. */
function product_review_admin_insert(PDO $pdo, int $product_id, int $rating, string $author_name, string $body, bool $is_approved): bool
{
	if ($product_id <= 0 || $rating < 1 || $rating > 5) {
		return false;
	}
	$author_name = trim($author_name);
	if ($author_name === '') {
		$author_name = 'Customer';
	}
	if (strlen($author_name) > 120) {
		$author_name = substr($author_name, 0, 120);
	}
	$body = trim($body);
	if (strlen($body) > 5000) {
		$body = substr($body, 0, 5000);
	}
	$stmt = $pdo->prepare(
		'INSERT INTO product_reviews (product_id, store_user_id, rating, author_name, body, is_approved) VALUES (?, NULL, ?, ?, ?, ?)'
	);
	return $stmt->execute([$product_id, $rating, $author_name, $body !== '' ? $body : null, $is_approved ? 1 : 0]);
}

function product_review_admin_update(PDO $pdo, int $review_id, int $product_id, int $rating, string $author_name, string $body, bool $is_approved): bool
{
	if ($review_id <= 0 || $product_id <= 0 || $rating < 1 || $rating > 5) {
		return false;
	}
	if (product_review_find_for_admin($pdo, $review_id, $product_id) === null) {
		return false;
	}
	$author_name = trim($author_name);
	if ($author_name === '') {
		$author_name = 'Customer';
	}
	if (strlen($author_name) > 120) {
		$author_name = substr($author_name, 0, 120);
	}
	$body = trim($body);
	if (strlen($body) > 5000) {
		$body = substr($body, 0, 5000);
	}
	$stmt = $pdo->prepare(
		'UPDATE product_reviews SET rating = ?, author_name = ?, body = ?, is_approved = ? WHERE id = ? AND product_id = ?'
	);
	return $stmt->execute([$rating, $author_name, $body !== '' ? $body : null, $is_approved ? 1 : 0, $review_id, $product_id]);
}

function product_review_admin_delete(PDO $pdo, int $review_id, int $product_id): bool
{
	if ($review_id <= 0 || $product_id <= 0) {
		return false;
	}
	$stmt = $pdo->prepare('DELETE FROM product_reviews WHERE id = ? AND product_id = ?');
	return $stmt->execute([$review_id, $product_id]);
}
