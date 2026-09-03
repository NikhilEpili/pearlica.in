<?php
/**
 * Safe streaming of product / collection / banner uploads from disk.
 * Used by media.php when the web server cannot serve the file statically
 * (misplaced tree, rewrite quirks) but PHP can still read BASE_PATH/uploads.
 */

declare(strict_types=1);

/**
 * @return array{products: string, collections: string, banners: string, reels: string}
 */
function media_serve_bucket_roots(): array
{
	return [
		'products' => defined('UPLOAD_PATH') ? (string) UPLOAD_PATH : '',
		'collections' => defined('COLLECTION_UPLOAD_PATH') ? (string) COLLECTION_UPLOAD_PATH : '',
		'banners' => defined('BANNER_UPLOAD_PATH') ? (string) BANNER_UPLOAD_PATH : '',
		'reels' => defined('REEL_UPLOAD_PATH') ? (string) REEL_UPLOAD_PATH : '',
		'blogs' => defined('BLOG_UPLOAD_PATH') ? (string) BLOG_UPLOAD_PATH : '',
	];
}

/**
 * Ordered roots to probe for a bucket (durable MEDIA_STORAGE_ROOT first,
 * then legacy BASE_PATH/uploads, then DOCUMENT_ROOT/uploads).
 *
 * @return list<string>
 */
function media_serve_bucket_root_candidates(string $bucket): array
{
	$bucket = strtolower(trim($bucket));
	$roots = media_serve_bucket_roots();
	$primary = isset($roots[$bucket]) ? trim((string) $roots[$bucket]) : '';
	$candidates = [];
	$seen = [];

	$append = static function (string $path) use (&$candidates, &$seen): void {
		$path = rtrim(str_replace('\\', '/', $path), '/');
		if ($path === '') {
			return;
		}
		$real = realpath($path);
		$key = $real !== false ? $real : $path;
		if (isset($seen[$key])) {
			return;
		}
		$seen[$key] = true;
		$candidates[] = $real !== false ? $real : $path;
	};

	if ($primary !== '') {
		$append($primary);
	}

	if (function_exists('media_storage_legacy_root')) {
		$append(media_storage_legacy_root() . '/' . $bucket);
	} elseif (defined('BASE_PATH')) {
		$append(rtrim(str_replace('\\', '/', (string) BASE_PATH), '/') . '/uploads/' . $bucket);
	}

	$doc_root = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
	if ($doc_root !== '') {
		$append($doc_root . '/uploads/' . $bucket);
	}

	return $candidates;
}

function media_serve_normalize_rel(string $path, string $bucket = ''): string
{
	$path = str_replace('\\', '/', $path);
	$path = rawurldecode($path);
	$path = trim($path, '/');
	$path = preg_replace('#/+#', '/', $path) ?? $path;
	if ($path === '' || str_contains($path, "\0") || str_contains($path, '../') || str_starts_with($path, '../')) {
		return '';
	}
	if (!preg_match('#^[A-Za-z0-9._/-]+$#', $path)) {
		return '';
	}
	$bucket = strtolower(trim($bucket));
	if ($bucket === 'reels') {
		if (!preg_match('/\.(jpe?g|png|gif|webp|mp4|webm|mov)$/i', $path)) {
			return '';
		}
	} elseif (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $path)) {
		return '';
	}

	return $path;
}

/**
 * Resolve a readable image path under an allowed uploads bucket, or null.
 */
function media_serve_resolve(string $bucket, string $rel): ?string
{
	$bucket = strtolower(trim($bucket));
	$roots = media_serve_bucket_roots();
	if (!isset($roots[$bucket])) {
		return null;
	}
	$rel = media_serve_normalize_rel($rel, $bucket);
	if ($rel === '') {
		return null;
	}

	foreach (media_serve_bucket_root_candidates($bucket) as $base) {
		if (!is_dir($base)) {
			continue;
		}
		$baseReal = realpath($base);
		if ($baseReal === false) {
			continue;
		}

		$candidate = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
		if (!is_file($candidate)) {
			continue;
		}
		$fileReal = realpath($candidate);
		if ($fileReal === false) {
			continue;
		}
		$prefix = $baseReal . DIRECTORY_SEPARATOR;
		if (!str_starts_with($fileReal, $prefix) && $fileReal !== $baseReal) {
			continue;
		}

		return $fileReal;
	}

	return null;
}

/**
 * Stream an image file to the client. Returns false if not found / not allowed.
 */
function media_serve_stream(string $bucket, string $rel): bool
{
	$file = media_serve_resolve($bucket, $rel);
	if ($file === null) {
		return false;
	}

	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
	$mime = match ($ext) {
		'jpg', 'jpeg' => 'image/jpeg',
		'png' => 'image/png',
		'gif' => 'image/gif',
		'webp' => 'image/webp',
		'mp4' => 'video/mp4',
		'webm' => 'video/webm',
		'mov' => 'video/quicktime',
		default => 'application/octet-stream',
	};

	$size = @filesize($file);
	$mtime = @filemtime($file) ?: time();
	$etag = '"' . md5($file . '|' . $mtime . '|' . (string) $size) . '"';
	$max_age = ($bucket === 'banners' || $bucket === 'reels') ? 3600 : 604800;

	if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
		http_response_code(304);
		header('ETag: ' . $etag);
		header('Cache-Control: public, max-age=' . $max_age . ', must-revalidate');
		return true;
	}

	http_response_code(200);
	header('Content-Type: ' . $mime);
	header('Content-Length: ' . (string) (int) $size);
	header('ETag: ' . $etag);
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
	header('Cache-Control: public, max-age=' . $max_age . ', must-revalidate');
	header('X-Content-Type-Options: nosniff');

	$out = fopen($file, 'rb');
	if ($out === false) {
		return false;
	}
	fpassthru($out);
	fclose($out);

	return true;
}

/**
 * Count files under a bucket root (best-effort; skips .gitkeep).
 */
function media_serve_count_files(string $bucket): int
{
	$total = 0;
	$seen = [];
	foreach (media_serve_bucket_root_candidates($bucket) as $base) {
		if (!is_dir($base)) {
			continue;
		}
		$real = realpath($base);
		if ($real === false || isset($seen[$real])) {
			continue;
		}
		$seen[$real] = true;
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
		);
		foreach ($it as $file) {
			/** @var SplFileInfo $file */
			if (!$file->isFile()) {
				continue;
			}
			if ($file->getFilename() === '.gitkeep') {
				continue;
			}
			$total++;
		}
	}

	return $total;
}
