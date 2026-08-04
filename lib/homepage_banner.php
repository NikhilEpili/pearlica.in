<?php
/**
 * Homepage banner slides (hero carousel + mid-page banner).
 */

function homepage_banner_placements(): array
{
	return ['hero', 'mid'];
}

/**
 * Create homepage_banners (and can_banners permission) if missing.
 * Safe to call repeatedly — uses IF NOT EXISTS / information_schema checks.
 */
function homepage_banner_ensure_schema(PDO $pdo): void
{
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS `homepage_banners` (
		  `id` INT AUTO_INCREMENT PRIMARY KEY,
		  `image_url` VARCHAR(512) NOT NULL,
		  `alt_text` VARCHAR(255) NOT NULL DEFAULT \'\',
		  `link_url` VARCHAR(512) NULL DEFAULT NULL,
		  `placement` VARCHAR(16) NOT NULL DEFAULT \'hero\',
		  `sort_order` INT NOT NULL DEFAULT 0,
		  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
		  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
		  INDEX `idx_homepage_banners_list` (`placement`, `is_active`, `sort_order`, `deleted_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
	);

	$stmt = $pdo->query(
		"SELECT COUNT(*) FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = 'admin_user_permissions'
		   AND COLUMN_NAME = 'can_banners'"
	);
	if ($stmt && (int) $stmt->fetchColumn() === 0) {
		$pdo->exec(
			'ALTER TABLE `admin_user_permissions`
			 ADD COLUMN `can_banners` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_orders`'
		);
	}
}

function homepage_banner_list_all(PDO $pdo): array
{
	$stmt = $pdo->query(
		'SELECT id, image_url, alt_text, link_url, placement, sort_order, is_active, created_at, updated_at
		 FROM homepage_banners
		 WHERE deleted_at IS NULL
		 ORDER BY FIELD(placement, \'hero\', \'mid\'), sort_order ASC, id ASC'
	);
	return $stmt->fetchAll();
}

function homepage_banner_list_active(PDO $pdo, string $placement): array
{
	$placement = homepage_banner_normalize_placement($placement);
	$stmt = $pdo->prepare(
		'SELECT id, image_url, alt_text, link_url, placement, sort_order
		 FROM homepage_banners
		 WHERE deleted_at IS NULL AND is_active = 1 AND placement = ?
		 ORDER BY sort_order ASC, id ASC'
	);
	$stmt->execute([$placement]);
	return $stmt->fetchAll();
}

function homepage_banner_find_by_id(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare(
		'SELECT id, image_url, alt_text, link_url, placement, sort_order, is_active
		 FROM homepage_banners
		 WHERE id = ? AND deleted_at IS NULL
		 LIMIT 1'
	);
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function homepage_banner_normalize_placement(string $placement): string
{
	$placement = strtolower(trim($placement));
	return in_array($placement, homepage_banner_placements(), true) ? $placement : 'hero';
}

function homepage_banner_next_sort_order(PDO $pdo, string $placement): int
{
	$placement = homepage_banner_normalize_placement($placement);
	$stmt = $pdo->prepare(
		'SELECT COALESCE(MAX(sort_order), -1) + 1
		 FROM homepage_banners
		 WHERE deleted_at IS NULL AND placement = ?'
	);
	$stmt->execute([$placement]);
	return (int) $stmt->fetchColumn();
}

function homepage_banner_insert(
	PDO $pdo,
	string $image_url,
	string $placement = 'hero',
	string $alt_text = '',
	?string $link_url = null,
	?int $sort_order = null,
	bool $is_active = true
): int {
	$placement = homepage_banner_normalize_placement($placement);
	if ($sort_order === null) {
		$sort_order = homepage_banner_next_sort_order($pdo, $placement);
	}
	$link = $link_url !== null ? trim($link_url) : null;
	if ($link === '') {
		$link = null;
	}
	$stmt = $pdo->prepare(
		'INSERT INTO homepage_banners (image_url, alt_text, link_url, placement, sort_order, is_active)
		 VALUES (?, ?, ?, ?, ?, ?)'
	);
	$stmt->execute([
		$image_url,
		trim($alt_text),
		$link,
		$placement,
		(int) $sort_order,
		$is_active ? 1 : 0,
	]);
	return (int) $pdo->lastInsertId();
}

function homepage_banner_update_meta(
	PDO $pdo,
	int $id,
	string $alt_text,
	?string $link_url,
	string $placement,
	bool $is_active
): bool {
	$banner = homepage_banner_find_by_id($pdo, $id);
	if (!$banner) {
		return false;
	}
	$placement = homepage_banner_normalize_placement($placement);
	$link = $link_url !== null ? trim($link_url) : null;
	if ($link === '') {
		$link = null;
	}
	$old_placement = homepage_banner_normalize_placement((string) ($banner['placement'] ?? 'hero'));
	$sort_order = (int) ($banner['sort_order'] ?? 0);
	if ($old_placement !== $placement) {
		$sort_order = homepage_banner_next_sort_order($pdo, $placement);
	}
	$stmt = $pdo->prepare(
		'UPDATE homepage_banners
		 SET alt_text = ?, link_url = ?, placement = ?, sort_order = ?, is_active = ?
		 WHERE id = ? AND deleted_at IS NULL'
	);
	return $stmt->execute([
		trim($alt_text),
		$link,
		$placement,
		$sort_order,
		$is_active ? 1 : 0,
		$id,
	]);
}

function homepage_banner_soft_delete(PDO $pdo, int $id): bool
{
	$stmt = $pdo->prepare('UPDATE homepage_banners SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
	return $stmt->execute([$id]);
}

function homepage_banner_update_order(PDO $pdo, string $placement, array $id_order): void
{
	$placement = homepage_banner_normalize_placement($placement);
	$stmt = $pdo->prepare(
		'UPDATE homepage_banners SET sort_order = ? WHERE id = ? AND placement = ? AND deleted_at IS NULL'
	);
	foreach (array_values($id_order) as $i => $id) {
		$stmt->execute([(int) $i, (int) $id, $placement]);
	}
}

function homepage_banner_safe_rel_path(string $rel): string
{
	$rel = str_replace(['\\', "\0"], ['/', ''], $rel);
	$rel = trim($rel, '/');
	if ($rel === '' || str_contains($rel, '..')) {
		return '';
	}
	return $rel;
}

function homepage_banner_delete_disk(string $rel): void
{
	$rel = homepage_banner_safe_rel_path($rel);
	if ($rel === '' || !defined('BANNER_UPLOAD_PATH')) {
		return;
	}
	$base = realpath(BANNER_UPLOAD_PATH);
	if ($base === false) {
		return;
	}
	$full = BANNER_UPLOAD_PATH . '/' . $rel;
	$real = realpath($full);
	if ($real === false || !str_starts_with($real, $base)) {
		return;
	}
	if (is_file($real)) {
		@unlink($real);
	}
	$dir = dirname($real);
	if (is_dir($dir) && $dir !== $base) {
		$entries = @scandir($dir);
		if (is_array($entries) && count($entries) === 2) {
			@rmdir($dir);
		}
	}
}

function homepage_banner_public_src(string $rel, string $uploads_base): string
{
	$rel = homepage_banner_safe_rel_path($rel);
	if ($rel === '') {
		return '';
	}
	$parts = array_map('rawurlencode', explode('/', str_replace('\\', '/', $rel)));
	return rtrim($uploads_base, '/') . '/' . implode('/', $parts);
}

/** True when the banner file exists under BANNER_UPLOAD_PATH. */
function homepage_banner_file_exists(string $rel): bool
{
	$rel = homepage_banner_safe_rel_path($rel);
	if ($rel === '' || !defined('BANNER_UPLOAD_PATH')) {
		return false;
	}
	$full = BANNER_UPLOAD_PATH . '/' . str_replace(['\\', '..'], '', $rel);

	return is_file($full);
}

/**
 * Allow http(s) URLs or site-relative paths; otherwise return $fallback.
 */
function homepage_banner_safe_href(?string $link_url, string $fallback): string
{
	$link = trim((string) $link_url);
	if ($link === '') {
		return $fallback;
	}
	if (preg_match('#^https?://#i', $link)) {
		return $link;
	}
	if (isset($link[0]) && $link[0] === '/') {
		return $link;
	}
	// Relative storefront paths like "products" or "products?sort=newest"
	if (preg_match('#^[a-z0-9][a-z0-9/_?&=%.+-]*$#i', $link)) {
		return $link;
	}
	return $fallback;
}
