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
		'SELECT id, image_url, alt_text, link_url, placement, sort_order, updated_at
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

function homepage_banner_update_image(PDO $pdo, int $id, string $image_url): bool
{
	$banner = homepage_banner_find_by_id($pdo, $id);
	if (!$banner) {
		return false;
	}
	$image_url = homepage_banner_safe_rel_path($image_url);
	if ($image_url === '') {
		return false;
	}
	$stmt = $pdo->prepare(
		'UPDATE homepage_banners SET image_url = ? WHERE id = ? AND deleted_at IS NULL'
	);
	return $stmt->execute([$image_url, $id]);
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

/** Absolute filesystem path for a banner rel path, or null. */
function homepage_banner_disk_path(string $rel): ?string
{
	$rel = homepage_banner_safe_rel_path($rel);
	if ($rel === '' || !defined('BANNER_UPLOAD_PATH') || BANNER_UPLOAD_PATH === '') {
		return null;
	}
	$base = realpath(BANNER_UPLOAD_PATH);
	if ($base === false) {
		return null;
	}
	$candidate = BANNER_UPLOAD_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
	if (!is_file($candidate)) {
		return null;
	}
	$real = realpath($candidate);
	if ($real === false) {
		return null;
	}
	$prefix = $base . DIRECTORY_SEPARATOR;
	if (!str_starts_with($real, $prefix) && $real !== $base) {
		return null;
	}

	return $real;
}

/** True when the banner file exists under BANNER_UPLOAD_PATH. */
function homepage_banner_file_exists(string $rel): bool
{
	return homepage_banner_disk_path($rel) !== null;
}

/** mtime for cache-busting, or 0 when the file is not local. */
function homepage_banner_file_mtime(string $rel): int
{
	$path = homepage_banner_disk_path($rel);
	if ($path === null) {
		return 0;
	}
	$mtime = @filemtime($path);

	return $mtime !== false ? (int) $mtime : 0;
}

/**
 * Whether a DB banner row should be rendered on the storefront.
 * - Local/dev: always allow (missing files may 302 to production via .htaccess).
 * - Production: only when the file is present under BANNER_UPLOAD_PATH (media.php uses the same tree).
 */
function homepage_banner_is_displayable(string $rel): bool
{
	$rel = homepage_banner_safe_rel_path($rel);
	if ($rel === '') {
		return false;
	}
	if (homepage_banner_file_exists($rel)) {
		return true;
	}
	if (defined('BANNER_MEDIA_PUBLIC_BASE') && BANNER_MEDIA_PUBLIC_BASE !== '') {
		return true;
	}
	if (function_exists('pearlica_is_local_dev_host') && pearlica_is_local_dev_host()) {
		return true;
	}

	return false;
}

/**
 * Public URL with ?v=… cache-buster (file mtime, DB updated_at, and banner id).
 */
function homepage_banner_public_src_versioned(
	string $rel,
	string $uploads_base,
	?int $banner_id = null,
	?string $updated_at = null
): string {
	$src = homepage_banner_public_src($rel, $uploads_base);
	if ($src === '') {
		return '';
	}
	$version = homepage_banner_file_mtime($rel);
	if ($updated_at !== null && $updated_at !== '') {
		$db_ts = strtotime($updated_at);
		if ($db_ts !== false && $db_ts > $version) {
			$version = $db_ts;
		}
	}
	if ($banner_id !== null && $banner_id > 0) {
		$version = max($version, $banner_id);
	}
	if ($version > 0) {
		$src .= (str_contains($src, '?') ? '&' : '?') . 'v=' . $version;
	}

	return $src;
}

/**
 * Hydrate active banner rows for the storefront: build src/alt/link from DB rows.
 * Trusts active DB rows (do not silently drop them — missing files should not
 * revert the homepage to hardcoded fallback assets).
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array{id: int, image_url: string, src: string, alt_text: string, link_url: ?string}>
 */
function homepage_banner_hydrate_for_display(array $rows, string $uploads_base, string $default_alt, string $link_fallback = ''): array
{
	$ready = [];
	foreach ($rows as $row) {
		$rel = homepage_banner_safe_rel_path((string) ($row['image_url'] ?? ''));
		if ($rel === '') {
			continue;
		}
		$id = (int) ($row['id'] ?? 0);
		$updated_at = isset($row['updated_at']) ? (string) $row['updated_at'] : null;
		$src = homepage_banner_public_src_versioned($rel, $uploads_base, $id > 0 ? $id : null, $updated_at);
		if ($src === '') {
			continue;
		}
		$alt = trim((string) ($row['alt_text'] ?? ''));
		$link = homepage_banner_safe_href($row['link_url'] ?? null, $link_fallback);
		$ready[] = [
			'id' => $id,
			'image_url' => $rel,
			'src' => $src,
			'alt_text' => $alt !== '' ? $alt : $default_alt,
			'link_url' => $link !== '' ? $link : null,
		];
	}

	return $ready;
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
