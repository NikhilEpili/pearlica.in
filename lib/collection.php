<?php

/**
 * Curated collections: many products per collection (junction table), soft-deleted headers.
 */

/**
 * Remove a stored cover file and its containing folder under COLLECTION_UPLOAD_PATH.
 */
function collection_delete_thumbnail_disk(?string $rel): void
{
	$rel = trim(str_replace(['\\', '..'], '', (string) $rel), '/ ');
	if ($rel === '' || !defined('COLLECTION_UPLOAD_PATH')) {
		return;
	}
	$full = COLLECTION_UPLOAD_PATH . '/' . $rel;
	if (!is_file($full)) {
		return;
	}
	$baseReal = @realpath(COLLECTION_UPLOAD_PATH);
	$fileReal = @realpath($full);
	$folder = dirname($full);
	$folderReal = @realpath($folder);
	if (!$baseReal || !$fileReal || !str_starts_with($fileReal, $baseReal)) {
		return;
	}
	@unlink($full);
	if ($folderReal && str_starts_with($folderReal, $baseReal)) {
		foreach (glob($folderReal . '/*') ?: [] as $f) {
			if (is_file($f)) {
				@unlink($f);
			}
		}
		@rmdir($folderReal);
		$grand = dirname($folderReal);
		if ($grand && str_starts_with($grand . DIRECTORY_SEPARATOR, $baseReal . DIRECTORY_SEPARATOR)) {
			$left = @scandir($grand);
			if (is_array($left) && count($left) === 2) {
				@rmdir($grand);
			}
		}
	}
}

function collection_list_all(PDO $pdo): array
{
	$stmt = $pdo->query('SELECT c.id, c.name, c.slug, c.thumbnail_url, c.created_at,
		(SELECT COUNT(*) FROM collection_products cp WHERE cp.collection_id = c.id) AS product_count
		FROM collections c WHERE c.deleted_at IS NULL ORDER BY c.name ASC');
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Storefront: collections that have at least one active, in-catalog product.
 *
 * @return list<array{id:int,name:string,slug:string,thumbnail_url:?string,description:?string,active_product_count:int}>
 */
function collection_list_for_store(PDO $pdo): array
{
	$sql = 'SELECT c.id, c.name, c.slug, c.thumbnail_url, c.description,
		(SELECT COUNT(*) FROM collection_products cp
			INNER JOIN products p ON p.id = cp.product_id AND p.deleted_at IS NULL AND p.status = \'active\' AND COALESCE(p.stock_quantity, 0) > 0
			WHERE cp.collection_id = c.id) AS active_product_count
		FROM collections c
		WHERE c.deleted_at IS NULL
		HAVING active_product_count > 0
		ORDER BY c.name ASC';
	$stmt = $pdo->query($sql);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function collection_find_by_id(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare('SELECT id, name, slug, thumbnail_url, description, meta_title, meta_description, created_at
		FROM collections WHERE id = ? AND deleted_at IS NULL LIMIT 1');
	$stmt->execute([$id]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

function collection_find_by_slug_store(PDO $pdo, string $slug): ?array
{
	$slug = trim($slug);
	if ($slug === '') {
		return null;
	}
	$stmt = $pdo->prepare('SELECT id, name, slug, thumbnail_url, description, meta_title, meta_description
		FROM collections WHERE slug = ? AND deleted_at IS NULL LIMIT 1');
	$stmt->execute([$slug]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

function collection_slug_exists(PDO $pdo, string $slug, ?int $exclude_id = null): bool
{
	$sql = 'SELECT 1 FROM collections WHERE slug = ? AND deleted_at IS NULL';
	$params = [$slug];
	if ($exclude_id !== null) {
		$sql .= ' AND id != ?';
		$params[] = (int) $exclude_id;
	}
	$stmt = $pdo->prepare($sql . ' LIMIT 1');
	$stmt->execute($params);
	return (bool) $stmt->fetchColumn();
}

function collection_find_id_by_slug(PDO $pdo, string $slug): ?int
{
	$slug = trim($slug);
	if ($slug === '') {
		return null;
	}
	$stmt = $pdo->prepare('SELECT id FROM collections WHERE slug = ? AND deleted_at IS NULL LIMIT 1');
	$stmt->execute([$slug]);
	$id = $stmt->fetchColumn();

	return $id !== false ? (int) $id : null;
}

function collection_insert(PDO $pdo, array $data): string
{
	$stmt = $pdo->prepare('INSERT INTO collections (name, slug, description, meta_title, meta_description)
		VALUES (?, ?, ?, ?, ?)');
	$stmt->execute([
		$data['name'],
		$data['slug'],
		$data['description'] ?? null,
		$data['meta_title'] ?? null,
		$data['meta_description'] ?? null,
	]);

	return (string) $pdo->lastInsertId();
}

function collection_update(PDO $pdo, int $id, array $data): bool
{
	$stmt = $pdo->prepare('UPDATE collections SET name = ?, slug = ?, description = ?, meta_title = ?, meta_description = ?
		WHERE id = ? AND deleted_at IS NULL');
	return $stmt->execute([
		$data['name'],
		$data['slug'],
		$data['description'] ?? null,
		$data['meta_title'] ?? null,
		$data['meta_description'] ?? null,
		$id,
	]);
}

function collection_set_thumbnail_url(PDO $pdo, int $id, ?string $relative_path): bool
{
	$stmt = $pdo->prepare('UPDATE collections SET thumbnail_url = ? WHERE id = ? AND deleted_at IS NULL');

	return $stmt->execute([$relative_path !== null && $relative_path !== '' ? $relative_path : null, (int) $id]);
}

function collection_soft_delete(PDO $pdo, int $id): bool
{
	$id = (int) $id;
	$st = $pdo->prepare('SELECT thumbnail_url FROM collections WHERE id = ? AND deleted_at IS NULL LIMIT 1');
	$st->execute([$id]);
	$row = $st->fetch(PDO::FETCH_ASSOC);
	if (!empty($row['thumbnail_url'])) {
		collection_delete_thumbnail_disk((string) $row['thumbnail_url']);
	}
	$pdo->prepare('UPDATE collections SET thumbnail_url = NULL WHERE id = ?')->execute([$id]);
	$pdo->prepare('DELETE FROM collection_products WHERE collection_id = ?')->execute([$id]);
	$stmt = $pdo->prepare('UPDATE collections SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');

	return $stmt->execute([$id]);
}

/**
 * Replace all products in a collection; preserves order of $product_ids (first = lowest sort_order).
 *
 * @param int[] $product_ids
 */
function collection_replace_products(PDO $pdo, int $collection_id, array $product_ids): void
{
	$collection_id = (int) $collection_id;
	$pdo->prepare('DELETE FROM collection_products WHERE collection_id = ?')->execute([$collection_id]);
	$seen = [];
	$sort = 0;
	$ins = $pdo->prepare('INSERT INTO collection_products (collection_id, product_id, sort_order) VALUES (?, ?, ?)');
	foreach ($product_ids as $pid) {
		$pid = (int) $pid;
		if ($pid <= 0 || isset($seen[$pid])) {
			continue;
		}
		$chk = $pdo->prepare('SELECT 1 FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1');
		$chk->execute([$pid]);
		if (!$chk->fetchColumn()) {
			continue;
		}
		$seen[$pid] = true;
		$ins->execute([$collection_id, $pid, $sort]);
		$sort++;
	}
}

/**
 * @return int[]
 */
function collection_product_ids_ordered(PDO $pdo, int $collection_id): array
{
	$stmt = $pdo->prepare('SELECT product_id FROM collection_products WHERE collection_id = ? ORDER BY sort_order ASC, product_id ASC');
	$stmt->execute([(int) $collection_id]);
	$out = [];
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$out[] = (int) $row['product_id'];
	}

	return $out;
}

/**
 * Active storefront products in collection order (same card shape as product_list_store rows).
 */
function collection_products_for_store(PDO $pdo, int $collection_id): array
{
	$collection_id = (int) $collection_id;
	if ($collection_id <= 0) {
		return [];
	}
	$sql = "SELECT p.id, p.name, p.slug, p.mrp, p.discount_percentage, p.stock_quantity,
		c.name AS category_name,
		(SELECT pm.url FROM product_media pm WHERE pm.product_id = p.id AND pm.deleted_at IS NULL AND pm.media_type = 'image' ORDER BY pm.is_primary DESC, pm.sort_order, pm.id LIMIT 1) AS image_url,
		(SELECT pm.url FROM product_media pm WHERE pm.product_id = p.id AND pm.deleted_at IS NULL AND pm.media_type = 'image' ORDER BY pm.is_primary DESC, pm.sort_order, pm.id LIMIT 1 OFFSET 1) AS image_url_2
		FROM collection_products cp
		INNER JOIN products p ON p.id = cp.product_id AND p.deleted_at IS NULL AND p.status = 'active' AND COALESCE(p.stock_quantity, 0) > 0
		LEFT JOIN categories c ON c.id = p.category_id AND c.deleted_at IS NULL
		WHERE cp.collection_id = ?
		ORDER BY cp.sort_order ASC, cp.product_id ASC";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$collection_id]);

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
	if ($rows === []) {
		return [];
	}
	$by_id = [];
	foreach ($rows as $row) {
		$pid = (int) ($row['id'] ?? 0);
		if ($pid <= 0 || isset($by_id[$pid])) {
			continue;
		}
		$by_id[$pid] = $row;
	}
	return array_values($by_id);
}

/**
 * Admin product picker (checkbox list), optional name/SKU search.
 *
 * @return list<array{id:int,name:string,sku:?string,status:string}>
 */
function collection_list_products_for_picker(PDO $pdo, string $q = '', int $limit = 2500): array
{
	$q = trim($q);
	$limit = max(1, min(5000, $limit));
	$sql = 'SELECT id, name, sku, status FROM products WHERE deleted_at IS NULL';
	$params = [];
	if ($q !== '') {
		$sql .= ' AND (name LIKE ? OR sku LIKE ?)';
		$like = '%' . $q . '%';
		$params[] = $like;
		$params[] = $like;
	}
	$sql .= ' ORDER BY name ASC LIMIT ' . (int) $limit;
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
