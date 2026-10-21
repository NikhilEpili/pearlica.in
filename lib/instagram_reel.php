<?php
/**
 * Homepage Instagram reels / posts (admin-managed links).
 */

function instagram_reel_ensure_schema(PDO $pdo): void
{
	static $done = false;
	if ($done) {
		return;
	}

	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS `instagram_reels` (
		  `id` INT AUTO_INCREMENT PRIMARY KEY,
		  `instagram_url` VARCHAR(512) NOT NULL,
		  `media_type` VARCHAR(8) NOT NULL DEFAULT \'reel\',
		  `shortcode` VARCHAR(64) NOT NULL DEFAULT \'\',
		  `product_id` BIGINT UNSIGNED NULL DEFAULT NULL,
		  `sort_order` INT NOT NULL DEFAULT 0,
		  `published_sort_order` INT NULL DEFAULT NULL,
		  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
		  `video_path` VARCHAR(255) NULL DEFAULT NULL,
		  `poster_path` VARCHAR(255) NULL DEFAULT NULL,
		  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
		  INDEX `idx_instagram_reels_list` (`is_active`, `sort_order`, `deleted_at`),
		  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
	);

	$exists = $pdo->query("SHOW TABLES LIKE 'instagram_reels'");
	if (!$exists || !$exists->fetch()) {
		throw RuntimeException('instagram_reels table could not be created.');
	}

	foreach (['video_path', 'poster_path'] as $column) {
		$stmt = $pdo->prepare(
			"SELECT COUNT(*) FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = 'instagram_reels'
			   AND COLUMN_NAME = ?"
		);
		$stmt->execute([$column]);
		if ((int) $stmt->fetchColumn() === 0) {
			$pdo->exec(
				'ALTER TABLE `instagram_reels`
				 ADD COLUMN `' . $column . '` VARCHAR(255) NULL DEFAULT NULL'
			);
		}
	}

	$stmt = $pdo->query(
		"SELECT COUNT(*) FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = 'instagram_reels'
		   AND COLUMN_NAME = 'published_sort_order'"
	);
	if ($stmt && (int) $stmt->fetchColumn() === 0) {
		$pdo->exec(
			'ALTER TABLE `instagram_reels`
			 ADD COLUMN `published_sort_order` INT NULL DEFAULT NULL AFTER `sort_order`'
		);
		$pdo->exec(
			'UPDATE `instagram_reels`
			 SET `published_sort_order` = `sort_order`
			 WHERE `is_active` = 1'
		);
	}

	// Uploaded reels do not need an Instagram link, so the URL must be nullable.
	try {
		$pdo->exec('ALTER TABLE `instagram_reels` MODIFY `instagram_url` VARCHAR(512) NULL DEFAULT NULL');
	} catch (Throwable $e) {
		// Older MySQL grants may block MODIFY; inserts still work with an empty string.
	}

	$stmt = $pdo->query(
		"SELECT COUNT(*) FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = 'admin_user_permissions'
		   AND COLUMN_NAME = 'can_reels'"
	);
	if ($stmt && (int) $stmt->fetchColumn() === 0) {
		try {
			$pdo->exec(
				'ALTER TABLE `admin_user_permissions`
				 ADD COLUMN `can_reels` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_faqs`'
			);
		} catch (Throwable $e) {
			try {
				$pdo->exec(
					'ALTER TABLE `admin_user_permissions`
					 ADD COLUMN `can_reels` TINYINT(1) NOT NULL DEFAULT 1'
				);
			} catch (Throwable $e2) {
				// Permission column optional — super admins can still manage reels.
			}
		}
	}

	$done = true;
}

/**
 * Parse Instagram reel or post URL.
 *
 * @return array{type: string, shortcode: string}|null
 */
function instagram_reel_parse_url(string $url): ?array
{
	$url = trim($url);
	if ($url === '') {
		return null;
	}
	if (!preg_match('#instagram\.com/(reel|reels|p)/([A-Za-z0-9_-]+)#i', $url, $m)) {
		return null;
	}
	$type = strtolower((string) $m[1]);
	if ($type === 'reels') {
		$type = 'reel';
	}
	return [
		'type' => $type === 'p' ? 'post' : 'reel',
		'shortcode' => (string) $m[2],
	];
}

function instagram_reel_normalize_url(string $url, ?array $parsed = null): string
{
	$parsed = $parsed ?? instagram_reel_parse_url($url);
	if (!$parsed) {
		return trim($url);
	}
	$path = $parsed['type'] === 'post' ? 'p' : 'reel';
	return 'https://www.instagram.com/' . $path . '/' . $parsed['shortcode'] . '/';
}

function instagram_reel_embed_url(string $shortcode, string $media_type = 'reel'): string
{
	$path = $media_type === 'post' ? 'p' : 'reel';
	return 'https://www.instagram.com/' . $path . '/' . rawurlencode($shortcode) . '/embed';
}

function instagram_reel_thumbnail_cache_dir(): string
{
	return dirname(__DIR__) . '/storage/cache/reel_thumbs';
}

/**
 * Best-effort thumbnail URL for reel/post side cards (og:image, cached on disk).
 */
function instagram_reel_thumbnail_url(string $permalink, string $shortcode): string
{
	$shortcode = trim($shortcode);
	if ($shortcode === '') {
		return '';
	}

	$cache_dir = instagram_reel_thumbnail_cache_dir();
	$cache_file = $cache_dir . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', $shortcode) . '.json';
	if (is_file($cache_file)) {
		$cached = json_decode((string) file_get_contents($cache_file), true);
		if (is_array($cached) && !empty($cached['url'])) {
			$expires = (int) ($cached['expires'] ?? 0);
			if ($expires === 0 || $expires > time()) {
				return (string) $cached['url'];
			}
		}
	}

	$url = instagram_reel_fetch_poster($permalink, $shortcode);

	if (!is_dir($cache_dir)) {
		@mkdir($cache_dir, 0755, true);
	}
	if (is_dir($cache_dir) && is_writable($cache_dir)) {
		// Cache misses too, briefly, so a blocked lookup is not retried on every render.
		file_put_contents($cache_file, json_encode([
			'url' => $url,
			'expires' => time() + ($url !== '' ? 7 * 86400 : 1800),
		], JSON_UNESCAPED_SLASHES));
	}

	return $url;
}

/**
 * Instagram serves the poster frame from several places depending on how the
 * request is fingerprinted, so try the public page and then the embed page.
 */
function instagram_reel_fetch_poster(string $permalink, string $shortcode): string
{
	$candidates = [];
	if ($permalink !== '') {
		$candidates[] = $permalink;
	}
	$candidates[] = 'https://www.instagram.com/reel/' . rawurlencode($shortcode) . '/embed/captioned/';
	$candidates[] = 'https://www.instagram.com/p/' . rawurlencode($shortcode) . '/embed/captioned/';

	foreach ($candidates as $candidate) {
		$html = instagram_reel_http_get($candidate);
		if ($html === '') {
			continue;
		}
		$url = instagram_reel_extract_poster($html);
		if ($url !== '') {
			return $url;
		}
	}

	return '';
}

function instagram_reel_http_get(string $url): string
{
	if (!function_exists('curl_init')) {
		return '';
	}

	$ch = curl_init($url);
	if ($ch === false) {
		return '';
	}

	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => 12,
		CURLOPT_CONNECTTIMEOUT => 8,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
		CURLOPT_HTTPHEADER => [
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: en-US,en;q=0.9',
		],
	]);

	$html = curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if (!is_string($html) || $html === '' || $status < 200 || $status >= 400) {
		return '';
	}

	return $html;
}

function instagram_reel_download_image(string $url): string
{
	if (!function_exists('curl_init') || stripos($url, 'http') !== 0) {
		return '';
	}

	$ch = curl_init($url);
	if ($ch === false) {
		return '';
	}

	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => 15,
		CURLOPT_CONNECTTIMEOUT => 8,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
		CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/jpeg,image/*;q=0.8'],
	]);

	$body = curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	curl_close($ch);

	if (!is_string($body) || $body === '' || $status < 200 || $status >= 400) {
		return '';
	}
	if (stripos($type, 'image/') !== 0) {
		return '';
	}

	return $body;
}

function instagram_reel_extract_poster(string $html): string
{
	$patterns = [
		'#<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)#i',
		'#<meta\s+content=["\']([^"\']+)["\']\s+property=["\']og:image["\']#i',
		'#"display_url"\s*:\s*"([^"]+)"#i',
		'#"thumbnail_src"\s*:\s*"([^"]+)"#i',
		'#class="EmbeddedMediaImage"[^>]*\ssrc="([^"]+)"#i',
	];

	foreach ($patterns as $pattern) {
		if (!preg_match($pattern, $html, $m)) {
			continue;
		}
		$url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
		$url = str_replace('\\/', '/', $url);
		$url = str_replace('\\u0026', '&', $url);
		if (stripos($url, 'http') === 0) {
			return $url;
		}
	}

	return '';
}

function instagram_reel_list_all(PDO $pdo): array
{
	$stmt = $pdo->query(
		'SELECT r.id, r.instagram_url, r.media_type, r.shortcode, r.product_id, r.sort_order, r.is_active,
		        r.video_path, r.poster_path, r.created_at, r.updated_at,
		        p.name AS product_name, p.slug AS product_slug, p.mrp AS product_mrp,
		        p.discount_percentage AS product_discount
		 FROM instagram_reels r
		 LEFT JOIN products p ON p.id = r.product_id AND p.deleted_at IS NULL
		 WHERE r.deleted_at IS NULL
		 ORDER BY r.sort_order ASC, r.id ASC'
	);
	return $stmt->fetchAll();
}

function instagram_reel_list_active(PDO $pdo): array
{
	$stmt = $pdo->query(
		'SELECT r.id, r.instagram_url, r.media_type, r.shortcode, r.product_id, r.sort_order,
		        r.video_path, r.poster_path,
		        p.name AS product_name, p.slug AS product_slug, p.mrp AS product_mrp,
		        p.discount_percentage AS product_discount
		 FROM instagram_reels r
		 LEFT JOIN products p ON p.id = r.product_id AND p.deleted_at IS NULL
		 WHERE r.is_active = 1
		 ORDER BY COALESCE(r.published_sort_order, r.sort_order) ASC, r.id ASC'
	);
	return $stmt->fetchAll();
}

function instagram_reel_count_active(PDO $pdo, ?int $exclude_id = null): int
{
	if ($exclude_id !== null && $exclude_id > 0) {
		$stmt = $pdo->prepare(
			'SELECT COUNT(*) FROM instagram_reels
			 WHERE is_active = 1 AND id != ?'
		);
		$stmt->execute([$exclude_id]);
		return (int) $stmt->fetchColumn();
	}

	$stmt = $pdo->query(
		'SELECT COUNT(*) FROM instagram_reels WHERE is_active = 1'
	);
	return (int) $stmt->fetchColumn();
}

function instagram_reel_count_library(PDO $pdo): int
{
	$stmt = $pdo->query(
		'SELECT COUNT(*) FROM instagram_reels WHERE deleted_at IS NULL'
	);
	return (int) $stmt->fetchColumn();
}

/**
 * Publish the current admin list atomically.
 *
 * Adding, deleting and reordering are drafts until this is called. The existing
 * homepage set remains unchanged when the draft count is even.
 *
 * @throws InvalidArgumentException
 */
function instagram_reel_publish(PDO $pdo): int
{
	$count = instagram_reel_count_library($pdo);
	if ($count < 1 || $count % 2 === 0) {
		throw new InvalidArgumentException(
			'Upload an odd number of reels before pushing to the homepage (1, 3, 5, 7…). '
			. 'The admin list currently has ' . $count . ' reel(s).'
		);
	}

	$deleted_media = $pdo->query(
		'SELECT video_path, poster_path
		 FROM instagram_reels
		 WHERE deleted_at IS NOT NULL'
	)->fetchAll();

	$pdo->beginTransaction();
	try {
		$pdo->exec(
			'UPDATE instagram_reels
			 SET is_active = 0, published_sort_order = NULL'
		);
		$pdo->exec(
			'UPDATE instagram_reels
			 SET is_active = 1, published_sort_order = sort_order
			 WHERE deleted_at IS NULL'
		);
		$pdo->commit();
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $e;
	}

	foreach ($deleted_media as $row) {
		instagram_reel_delete_media($row['video_path'] ?? null);
		instagram_reel_delete_media($row['poster_path'] ?? null);
	}

	return $count;
}

function instagram_reel_find_by_id(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare(
		'SELECT id, instagram_url, media_type, shortcode, product_id, sort_order, is_active,
		        video_path, poster_path
		 FROM instagram_reels
		 WHERE id = ? AND deleted_at IS NULL
		 LIMIT 1'
	);
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function instagram_reel_next_sort_order(PDO $pdo): int
{
	$stmt = $pdo->query(
		'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM instagram_reels WHERE deleted_at IS NULL'
	);
	return (int) $stmt->fetchColumn();
}

function instagram_reel_insert(
	PDO $pdo,
	string $instagram_url,
	?int $product_id = null,
	?int $sort_order = null,
	bool $is_active = false,
	?string $video_path = null,
	?string $poster_path = null
): int {
	$video_path = $video_path !== null && $video_path !== '' ? $video_path : null;
	$poster_path = $poster_path !== null && $poster_path !== '' ? $poster_path : null;
	$instagram_url = trim($instagram_url);

	$parsed = $instagram_url !== '' ? instagram_reel_parse_url($instagram_url) : null;
	if ($instagram_url !== '' && !$parsed) {
		throw new InvalidArgumentException('Invalid Instagram reel or post URL.');
	}
	if (!$parsed && $video_path === null) {
		throw new InvalidArgumentException('Upload a video, or paste an Instagram reel link.');
	}

	if ($sort_order === null) {
		$sort_order = instagram_reel_next_sort_order($pdo);
	}
	$product_id = $product_id !== null && $product_id > 0 ? $product_id : null;
	$stmt = $pdo->prepare(
		'INSERT INTO instagram_reels
		 (instagram_url, media_type, shortcode, product_id, sort_order, is_active, video_path, poster_path)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
	);
	$stmt->execute([
		$parsed ? instagram_reel_normalize_url($instagram_url, $parsed) : '',
		$parsed ? $parsed['type'] : 'reel',
		$parsed ? $parsed['shortcode'] : '',
		$product_id,
		(int) $sort_order,
		$is_active ? 1 : 0,
		$video_path,
		$poster_path,
	]);
	return (int) $pdo->lastInsertId();
}

function instagram_reel_update(
	PDO $pdo,
	int $id,
	string $instagram_url,
	?int $product_id,
	int $sort_order,
	bool $is_active,
	?string $video_path = null,
	?string $poster_path = null
): bool {
	$video_path = $video_path !== null && $video_path !== '' ? $video_path : null;
	$poster_path = $poster_path !== null && $poster_path !== '' ? $poster_path : null;
	$instagram_url = trim($instagram_url);

	$parsed = $instagram_url !== '' ? instagram_reel_parse_url($instagram_url) : null;
	if ($instagram_url !== '' && !$parsed) {
		throw new InvalidArgumentException('Invalid Instagram reel or post URL.');
	}
	if (!$parsed && $video_path === null) {
		throw new InvalidArgumentException('Upload a video, or paste an Instagram reel link.');
	}

	$product_id = $product_id !== null && $product_id > 0 ? $product_id : null;
	$stmt = $pdo->prepare(
		'UPDATE instagram_reels
		 SET instagram_url = ?, media_type = ?, shortcode = ?, product_id = ?, sort_order = ?,
		     is_active = ?, video_path = ?, poster_path = ?
		 WHERE id = ? AND deleted_at IS NULL'
	);
	return $stmt->execute([
		$parsed ? instagram_reel_normalize_url($instagram_url, $parsed) : '',
		$parsed ? $parsed['type'] : 'reel',
		$parsed ? $parsed['shortcode'] : '',
		$product_id,
		(int) $sort_order,
		$is_active ? 1 : 0,
		$video_path,
		$poster_path,
		$id,
	]);
}

function instagram_reel_soft_delete(PDO $pdo, int $id): bool
{
	$row = instagram_reel_find_by_id($pdo, $id);
	if (!$row) {
		return false;
	}
	$stmt = $pdo->prepare('UPDATE instagram_reels SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
	if (!$stmt->execute([$id])) {
		return false;
	}

	// A published reel remains available on the homepage until the next Push.
	// Draft-only reels can have their files removed immediately.
	if (empty($row['is_active'])) {
		instagram_reel_delete_media($row['video_path'] ?? null);
		instagram_reel_delete_media($row['poster_path'] ?? null);
	}
	return true;
}

function instagram_reel_update_order(PDO $pdo, array $ordered_ids): void
{
	$ordered_ids = array_values(array_filter(array_map('intval', $ordered_ids)));
	$stmt = $pdo->prepare(
		'UPDATE instagram_reels SET sort_order = ? WHERE id = ? AND deleted_at IS NULL'
	);
	foreach ($ordered_ids as $i => $id) {
		if ($id > 0) {
			$stmt->execute([(int) $i, $id]);
		}
	}
}

/** Storage root for uploaded reel videos and posters. */
function instagram_reel_upload_path(): string
{
	if (defined('REEL_UPLOAD_PATH')) {
		return (string) REEL_UPLOAD_PATH;
	}
	return dirname(__DIR__) . '/uploads/reels';
}

/**
 * Move an uploaded reel video or poster into uploads/reels and return its
 * relative path (e.g. "a1b2c3/video.mp4").
 *
 * @param array<string, mixed> $file One entry from $_FILES.
 * @param string $kind "video" or "poster"
 * @throws RuntimeException
 */
function instagram_reel_store_upload(array $file, string $kind): string
{
	$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
	if ($error === UPLOAD_ERR_NO_FILE) {
		return '';
	}
	if ($error !== UPLOAD_ERR_OK) {
		throw new RuntimeException(
			$error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
				? 'File is larger than the server upload limit.'
				: 'Upload failed (error ' . $error . ').'
		);
	}

	$tmp = (string) ($file['tmp_name'] ?? '');
	if ($tmp === '' || !is_uploaded_file($tmp)) {
		throw new RuntimeException('Invalid upload.');
	}

	$is_video = $kind === 'video';
	$max_size = $is_video
		? (defined('REEL_MAX_VIDEO_SIZE') ? REEL_MAX_VIDEO_SIZE : 32 * 1024 * 1024)
		: (defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 5 * 1024 * 1024);
	if ((int) ($file['size'] ?? 0) > $max_size) {
		throw new RuntimeException(
			$is_video
				? 'Video is too large (max ' . (int) round($max_size / 1048576) . 'MB).'
				: 'Poster image is too large (max ' . (int) round($max_size / 1048576) . 'MB).'
		);
	}

	$finfo = new finfo(FILEINFO_MIME_TYPE);
	$mime = (string) $finfo->file($tmp);
	$allowed = $is_video
		? (defined('ALLOWED_VIDEO_TYPES') ? ALLOWED_VIDEO_TYPES : ['video/mp4', 'video/webm'])
		: (defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg', 'image/png', 'image/webp']);
	if (!in_array($mime, $allowed, true)) {
		throw new RuntimeException(
			$is_video
				? 'Invalid video type. Use MP4, WebM, or MOV.'
				: 'Invalid image type. Use JPG, PNG, GIF, or WebP.'
		);
	}

	$extensions = [
		'video/mp4' => 'mp4',
		'video/webm' => 'webm',
		'video/quicktime' => 'mp4',
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/gif' => 'gif',
		'image/webp' => 'webp',
	];
	$ext = $extensions[$mime] ?? ($is_video ? 'mp4' : 'jpg');

	$base = instagram_reel_upload_path();
	if (!is_dir($base) && !@mkdir($base, 0755, true)) {
		throw new RuntimeException('Cannot create the uploads/reels folder.');
	}
	if (!is_writable($base)) {
		throw new RuntimeException('The uploads/reels folder is not writable.');
	}

	$folder = bin2hex(random_bytes(8));
	$dir = $base . '/' . $folder;
	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
		throw new RuntimeException('Cannot create the reel media folder.');
	}

	$name = ($is_video ? 'video.' : 'poster.') . $ext;
	if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
		throw new RuntimeException('Could not save the uploaded file.');
	}

	return $folder . '/' . $name;
}

/** Remove a stored reel asset and its folder when empty. */
function instagram_reel_delete_media(?string $relative): void
{
	$relative = trim((string) $relative);
	if ($relative === '' || str_contains($relative, '..')) {
		return;
	}

	$full = instagram_reel_upload_path() . '/' . ltrim(str_replace('\\', '/', $relative), '/');
	if (is_file($full)) {
		@unlink($full);
	}

	$dir = dirname($full);
	if (is_dir($dir) && $dir !== instagram_reel_upload_path()) {
		$entries = @scandir($dir);
		if (is_array($entries) && count(array_diff($entries, ['.', '..'])) === 0) {
			@rmdir($dir);
		}
	}
}

/** Public URL for a stored reel asset, cache-busted by file mtime. */
function instagram_reel_media_url(?string $relative, string $base_path = ''): string
{
	$relative = trim((string) $relative);
	if ($relative === '' || str_contains($relative, '..')) {
		return '';
	}

	$relative = ltrim(str_replace('\\', '/', $relative), '/');
	$base = function_exists('pearlica_uploads_reels_base')
		? pearlica_uploads_reels_base($base_path)
		: rtrim($base_path, '/') . '/uploads/reels';

	$url = rtrim($base, '/') . '/' . $relative;
	$full = null;
	if (function_exists('media_serve_resolve')) {
		$full = media_serve_resolve('reels', $relative);
	}
	if ($full === null) {
		$candidate = instagram_reel_upload_path() . '/' . $relative;
		if (is_file($candidate)) {
			$full = $candidate;
		}
	}
	if ($full !== null) {
		$mtime = @filemtime($full);
		if ($mtime) {
			$url .= '?v=' . (string) (int) $mtime;
		}
	}

	return $url;
}

/**
 * @return array{video_url: string, poster_url: string, embed_url: string, permalink: string, thumbnail_url: string, shortcode: string, media_type: string, product_name: string, product_url: string, price_parts: array{current: string, original: string|null}}
 */
function instagram_reel_slide_view(array $row, string $base_path = ''): array
{
	$media_type = (string) ($row['media_type'] ?? 'reel');
	$shortcode = (string) ($row['shortcode'] ?? '');
	$path = $media_type === 'post' ? 'p' : 'reel';
	$permalink = instagram_reel_normalize_url((string) ($row['instagram_url'] ?? ''));

	$video_rel = trim((string) ($row['video_path'] ?? ''));
	$poster_rel = trim((string) ($row['poster_path'] ?? ''));
	$video_on_disk = $video_rel !== '' && function_exists('media_serve_resolve')
		&& media_serve_resolve('reels', $video_rel) !== null;
	$poster_on_disk = $poster_rel !== '' && function_exists('media_serve_resolve')
		&& media_serve_resolve('reels', $poster_rel) !== null;

	// Only emit local media URLs when files exist — otherwise use Instagram embed.
	$video_url = $video_on_disk ? instagram_reel_media_url($video_rel, $base_path) : '';
	$poster_url = $poster_on_disk ? instagram_reel_media_url($poster_rel, $base_path) : '';

	// Only fall back to scraping Instagram when no poster was uploaded.
	$thumbnail_url = $poster_url;
	if ($thumbnail_url === '' && $shortcode !== '') {
		$thumbnail_url = $base_path . 'reel-thumbnail.php?shortcode=' . rawurlencode($shortcode)
			. '&type=' . rawurlencode($media_type === 'post' ? 'post' : 'reel');
	}

	$product_name = trim((string) ($row['product_name'] ?? ''));
	$product_url = '';
	$price_parts = ['current' => '', 'original' => null];
	if ($product_name !== '' && !empty($row['product_slug'])) {
		$product_url = $base_path . 'product?slug=' . rawurlencode((string) $row['product_slug']);
		if (function_exists('format_price_parts')) {
			$price_parts = format_price_parts($row['product_mrp'] ?? null, $row['product_discount'] ?? 0);
		}
	}

	return [
		'video_url' => $video_url,
		'poster_url' => $poster_url,
		'embed_url' => $shortcode !== '' ? instagram_reel_embed_url($shortcode, $media_type) : '',
		'permalink' => $permalink !== '' ? $permalink : ($shortcode !== '' ? 'https://www.instagram.com/' . $path . '/' . $shortcode . '/' : ''),
		'thumbnail_url' => $thumbnail_url,
		'shortcode' => $shortcode,
		'media_type' => $media_type,
		'product_name' => $product_name,
		'product_url' => $product_url,
		'price_parts' => $price_parts,
	];
}
