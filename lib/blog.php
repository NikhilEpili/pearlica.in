<?php
/**
 * Blog posts (admin CRUD + JSON feed for storefront).
 */

require_once __DIR__ . '/../includes/blog_feed_single.php';

function blog_feed_site_description(): string
{
	return 'Stories, styling ideas, care tips, and studio updates from Pearlica — premium 925 sterling silver jewellery.';
}

function blog_feed_site_base(): string
{
	$base = defined('SITE_PUBLIC_URL') ? rtrim((string) SITE_PUBLIC_URL, '/') : 'https://pearlica.in';
	return $base . '/blogs/';
}

function blog_ensure_schema(PDO $pdo): void
{
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS `blog_posts` (
		  `id` INT AUTO_INCREMENT PRIMARY KEY,
		  `title` VARCHAR(255) NOT NULL,
		  `slug` VARCHAR(128) NOT NULL,
		  `excerpt` TEXT NULL DEFAULT NULL,
		  `body_html` MEDIUMTEXT NOT NULL,
		  `cover_image` VARCHAR(512) NULL DEFAULT NULL,
		  `meta_title` VARCHAR(255) NOT NULL DEFAULT \'\',
		  `meta_description` VARCHAR(512) NOT NULL DEFAULT \'\',
		  `published_at` DATETIME NULL DEFAULT NULL,
		  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
		  `sort_order` INT NOT NULL DEFAULT 0,
		  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
		  UNIQUE KEY `uq_blog_posts_slug` (`slug`),
		  INDEX `idx_blog_posts_list` (`is_published`, `published_at`, `deleted_at`, `sort_order`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
	);

	$stmt = $pdo->query(
		"SELECT COUNT(*) FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = 'admin_user_permissions'
		   AND COLUMN_NAME = 'can_blogs'"
	);
	if ($stmt && (int) $stmt->fetchColumn() === 0) {
		try {
			$pdo->exec(
				'ALTER TABLE `admin_user_permissions`
				 ADD COLUMN `can_blogs` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_policies`'
			);
		} catch (Throwable $e) {
			try {
				$pdo->exec(
					'ALTER TABLE `admin_user_permissions`
					 ADD COLUMN `can_blogs` TINYINT(1) NOT NULL DEFAULT 1'
				);
			} catch (Throwable $e2) {
				// Permission column optional — super admins can still manage blogs.
			}
		}
	}
}

function blog_upload_dir(): string
{
	return defined('BLOG_UPLOAD_PATH') ? (string) BLOG_UPLOAD_PATH : '';
}

function blog_cover_public_url(?string $relative_path, string $base_path = ''): string
{
	$relative_path = trim((string) $relative_path);
	if ($relative_path === '') {
		return '';
	}
	if (preg_match('#^https?://#i', $relative_path)) {
		return $relative_path;
	}
	if (str_starts_with($relative_path, '/')) {
		return $relative_path;
	}
	if (!function_exists('pearlica_uploads_blogs_base')) {
		require_once __DIR__ . '/../includes/uploads_url.php';
	}
	$uploads_base = pearlica_uploads_blogs_base($base_path);
	return rtrim($uploads_base, '/') . '/' . ltrim(str_replace('\\', '/', $relative_path), '/');
}

function blog_delete_cover_disk(?string $relative_path): void
{
	$relative_path = trim((string) $relative_path);
	if ($relative_path === '' || preg_match('#^https?://#i', $relative_path)) {
		return;
	}
	$base = blog_upload_dir();
	if ($base === '') {
		return;
	}
	$path = $base . '/' . str_replace('\\', '/', ltrim($relative_path, '/'));
	if (is_file($path)) {
		@unlink($path);
	}
	$dir = dirname($path);
	while ($dir !== $base && str_starts_with($dir, $base) && is_dir($dir)) {
		$entries = @scandir($dir);
		if (!is_array($entries) || count(array_diff($entries, ['.', '..'])) > 0) {
			break;
		}
		@rmdir($dir);
		$dir = dirname($dir);
	}
}

function blog_list_all(PDO $pdo): array
{
	$stmt = $pdo->query(
		'SELECT id, title, slug, excerpt, cover_image, meta_title, meta_description,
		        published_at, is_published, sort_order, created_at, updated_at
		 FROM blog_posts
		 WHERE deleted_at IS NULL
		 ORDER BY COALESCE(published_at, created_at) DESC, sort_order ASC, id DESC'
	);
	return $stmt->fetchAll();
}

function blog_list_published(PDO $pdo): array
{
	$stmt = $pdo->query(
		'SELECT id, title, slug, excerpt, body_html, cover_image, meta_title, meta_description,
		        published_at, is_published, sort_order, created_at, updated_at
		 FROM blog_posts
		 WHERE deleted_at IS NULL AND is_published = 1
		 ORDER BY COALESCE(published_at, created_at) DESC, sort_order ASC, id DESC'
	);
	return $stmt->fetchAll();
}

function blog_find_by_id(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare(
		'SELECT id, title, slug, excerpt, body_html, cover_image, meta_title, meta_description,
		        published_at, is_published, sort_order, created_at, updated_at
		 FROM blog_posts
		 WHERE id = ? AND deleted_at IS NULL
		 LIMIT 1'
	);
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function blog_find_by_slug(PDO $pdo, string $slug): ?array
{
	$slug = trim($slug);
	if ($slug === '') {
		return null;
	}
	$stmt = $pdo->prepare(
		'SELECT id, title, slug, excerpt, body_html, cover_image, meta_title, meta_description,
		        published_at, is_published, sort_order, created_at, updated_at
		 FROM blog_posts
		 WHERE slug = ? AND deleted_at IS NULL AND is_published = 1
		 LIMIT 1'
	);
	$stmt->execute([$slug]);
	$row = $stmt->fetch();
	return $row ?: null;
}

function blog_slug_exists(PDO $pdo, string $slug, ?int $exclude_id = null): bool
{
	$sql = 'SELECT 1 FROM blog_posts WHERE slug = ? AND deleted_at IS NULL';
	$params = [$slug];
	if ($exclude_id !== null) {
		$sql .= ' AND id != ?';
		$params[] = (int) $exclude_id;
	}
	$stmt = $pdo->prepare($sql . ' LIMIT 1');
	$stmt->execute($params);
	return (bool) $stmt->fetch();
}

function blog_next_sort_order(PDO $pdo): int
{
	$stmt = $pdo->query(
		'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM blog_posts WHERE deleted_at IS NULL'
	);
	return (int) $stmt->fetchColumn();
}

function blog_normalize_slug(string $slug, string $fallback_title = ''): string
{
	$slug = strtolower(trim($slug));
	$slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
	$slug = trim($slug, '-');
	if ($slug === '' && $fallback_title !== '') {
		if (!function_exists('slug_from_string')) {
			$slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $fallback_title) ?? '', '-'));
		} else {
			$slug = slug_from_string($fallback_title);
		}
	}
	return $slug;
}

function blog_validate_slug(string $slug): ?string
{
	if ($slug === '') {
		return 'Slug is required.';
	}
	if (!preg_match('#^[a-z0-9][a-z0-9_-]*$#', $slug)) {
		return 'Slug may only contain lowercase letters, numbers, hyphens, and underscores.';
	}
	return null;
}

function blog_sanitize_body_html(string $html): string
{
	return pearlica_blog_feed_sanitize_body_html($html);
}

function blog_parse_published_at(?string $raw): ?string
{
	$raw = trim((string) $raw);
	if ($raw === '') {
		return null;
	}
	$dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw);
	if ($dt instanceof DateTimeImmutable) {
		return $dt->format('Y-m-d H:i:s');
	}
	try {
		return (new DateTimeImmutable($raw))->format('Y-m-d H:i:s');
	} catch (Throwable $e) {
		return null;
	}
}

function blog_format_published_at_input(?string $db_value): string
{
	$db_value = trim((string) $db_value);
	if ($db_value === '') {
		return '';
	}
	try {
		return (new DateTimeImmutable($db_value))->format('Y-m-d\TH:i');
	} catch (Throwable $e) {
		return '';
	}
}

function blog_insert(
	PDO $pdo,
	string $title,
	string $slug,
	string $excerpt,
	string $body_html,
	?string $cover_image,
	string $meta_title,
	string $meta_description,
	?string $published_at,
	bool $is_published,
	?int $sort_order = null
): int {
	if ($sort_order === null) {
		$sort_order = blog_next_sort_order($pdo);
	}
	if ($is_published && $published_at === null) {
		$published_at = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
	}
	$stmt = $pdo->prepare(
		'INSERT INTO blog_posts
		 (title, slug, excerpt, body_html, cover_image, meta_title, meta_description, published_at, is_published, sort_order)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
	);
	$stmt->execute([
		trim($title),
		$slug,
		trim($excerpt) !== '' ? trim($excerpt) : null,
		blog_sanitize_body_html($body_html),
		trim((string) $cover_image) !== '' ? trim((string) $cover_image) : null,
		trim($meta_title),
		trim($meta_description),
		$published_at,
		$is_published ? 1 : 0,
		(int) $sort_order,
	]);
	return (int) $pdo->lastInsertId();
}

function blog_update(
	PDO $pdo,
	int $id,
	string $title,
	string $slug,
	string $excerpt,
	string $body_html,
	?string $cover_image,
	string $meta_title,
	string $meta_description,
	?string $published_at,
	bool $is_published,
	int $sort_order
): bool {
	if ($is_published && $published_at === null) {
		$published_at = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
	}
	$stmt = $pdo->prepare(
		'UPDATE blog_posts
		 SET title = ?, slug = ?, excerpt = ?, body_html = ?, cover_image = ?,
		     meta_title = ?, meta_description = ?, published_at = ?, is_published = ?, sort_order = ?
		 WHERE id = ? AND deleted_at IS NULL'
	);
	return $stmt->execute([
		trim($title),
		$slug,
		trim($excerpt) !== '' ? trim($excerpt) : null,
		blog_sanitize_body_html($body_html),
		trim((string) $cover_image) !== '' ? trim((string) $cover_image) : null,
		trim($meta_title),
		trim($meta_description),
		$published_at,
		$is_published ? 1 : 0,
		(int) $sort_order,
		$id,
	]);
}

function blog_set_cover_image(PDO $pdo, int $id, ?string $relative_path): bool
{
	$stmt = $pdo->prepare('UPDATE blog_posts SET cover_image = ? WHERE id = ? AND deleted_at IS NULL');
	return $stmt->execute([
		$relative_path !== null && trim($relative_path) !== '' ? trim($relative_path) : null,
		$id,
	]);
}

function blog_soft_delete(PDO $pdo, int $id): bool
{
	$row = blog_find_by_id($pdo, $id);
	if (!$row) {
		return false;
	}
	if (!empty($row['cover_image'])) {
		blog_delete_cover_disk((string) $row['cover_image']);
	}
	$stmt = $pdo->prepare('UPDATE blog_posts SET deleted_at = NOW(), cover_image = NULL WHERE id = ? AND deleted_at IS NULL');
	return $stmt->execute([$id]);
}

/**
 * @return array<string, mixed>
 */
function blog_row_to_feed_post(array $row, string $site_base, string $base_path = ''): array
{
	$slug = (string) ($row['slug'] ?? '');
	$published_at = (string) ($row['published_at'] ?? $row['created_at'] ?? '');
	$cover = blog_cover_public_url(isset($row['cover_image']) ? (string) $row['cover_image'] : '', $base_path);
	return [
		'title' => (string) ($row['title'] ?? ''),
		'slug' => $slug,
		'url' => rtrim($site_base, '/') . '/' . rawurlencode($slug),
		'excerpt' => (string) ($row['excerpt'] ?? ''),
		'description' => (string) ($row['excerpt'] ?? ''),
		'published_at' => $published_at,
		'image' => $cover,
		'cover_image' => $cover,
		'html' => (string) ($row['body_html'] ?? ''),
		'meta_title' => (string) ($row['meta_title'] ?? ''),
		'meta_description' => (string) ($row['meta_description'] ?? ''),
	];
}

/**
 * @return array{site: array<string, string>, generated_at: string, posts: list<array<string, mixed>>}
 */
function blog_feed_payload(PDO $pdo, string $base_path = ''): array
{
	$site_base = blog_feed_site_base();
	$posts = [];
	foreach (blog_list_published($pdo) as $row) {
		$posts[] = blog_row_to_feed_post($row, $site_base, $base_path);
	}
	return [
		'site' => [
			'title' => 'Pearlica',
			'base_url' => $site_base,
			'description' => blog_feed_site_description(),
		],
		'generated_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
		'posts' => $posts,
	];
}

/**
 * @return array{
 *   ok: bool,
 *   error: string|null,
 *   site_title: string,
 *   site_base: string,
 *   site_description: string,
 *   generated_at: string|null,
 *   posts: list<array{title: string, href: string, excerpt: string, date_label: string, image: string, slug: string}>
 * }
 */
function blog_storefront_feed(?PDO $pdo, string $fallback_feed_url, string $base_path = ''): array
{
	if ($pdo instanceof PDO) {
		try {
			blog_ensure_schema($pdo);
			$payload = blog_feed_payload($pdo, $base_path);
			require_once __DIR__ . '/../includes/blog_feed.php';
			$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if (is_string($json)) {
				$data = json_decode($json, true);
				if (is_array($data)) {
					return pearlica_blog_feed_from_array($data);
				}
			}
		} catch (Throwable $e) {
			// Fall back to HTTP feed below.
		}
	}
	require_once __DIR__ . '/../includes/blog_feed.php';
	return pearlica_blog_feed_load($fallback_feed_url);
}

/**
 * @return array{
 *   ok: bool,
 *   error: string|null,
 *   site_title: string,
 *   site_base: string,
 *   post: array{
 *     title: string,
 *     slug: string,
 *     href: string,
 *     excerpt: string,
 *     date_label: string,
 *     image: string,
 *     body_html: string,
 *     meta_title: string,
 *     meta_description: string
 *   }|null
 * }
 */
function blog_storefront_post(?PDO $pdo, string $slug, string $fallback_feed_url, string $base_path = ''): array
{
	$want = trim(str_replace('\\', '/', $slug), '/');
	if ($pdo instanceof PDO) {
		try {
			blog_ensure_schema($pdo);
			$row = blog_find_by_slug($pdo, $want);
			if ($row) {
				$site_base = blog_feed_site_base();
				$feed_post = blog_row_to_feed_post($row, $site_base, $base_path);
				require_once __DIR__ . '/../includes/blog_feed.php';
				$norm = pearlica_blog_feed_normalize_post($feed_post, $site_base);
				if ($norm !== null) {
					$body_html = pearlica_blog_feed_row_body_html($feed_post);
					if ($body_html === '' && $norm['excerpt'] !== '') {
						$body_html = '<p class="blogs-post-lead">' . htmlspecialchars($norm['excerpt'], ENT_QUOTES, 'UTF-8') . '</p>';
					}
					return [
						'ok' => true,
						'error' => null,
						'site_title' => 'Pearlica',
						'site_base' => $site_base,
						'post' => [
							'title' => $norm['title'],
							'slug' => (string) ($row['slug'] ?? $want),
							'href' => $norm['href'],
							'excerpt' => $norm['excerpt'],
							'date_label' => $norm['date_label'],
							'image' => $norm['image'],
							'body_html' => $body_html,
							'meta_title' => (string) ($row['meta_title'] ?? ''),
							'meta_description' => (string) ($row['meta_description'] ?? ''),
						],
					];
				}
			}
		} catch (Throwable $e) {
			// Fall back to HTTP feed below.
		}
	}
	require_once __DIR__ . '/../includes/blog_feed.php';
	$result = pearlica_blog_feed_find_post_by_slug($fallback_feed_url, $want);
	if ($result['ok'] && is_array($result['post'])) {
		$result['post']['meta_title'] = '';
		$result['post']['meta_description'] = '';
	}
	return $result;
}
