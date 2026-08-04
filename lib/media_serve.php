<?php
/**
 * Safe streaming of product / collection / banner uploads from disk.
 * Used by media.php when the web server cannot serve the file statically
 * (misplaced tree, rewrite quirks) but PHP can still read BASE_PATH/uploads.
 */

declare(strict_types=1);

/**
 * @return array{products: string, collections: string, banners: string}
 */
function media_serve_bucket_roots(): array
{
	return [
		'products' => defined('UPLOAD_PATH') ? (string) UPLOAD_PATH : '',
		'collections' => defined('COLLECTION_UPLOAD_PATH') ? (string) COLLECTION_UPLOAD_PATH : '',
		'banners' => defined('BANNER_UPLOAD_PATH') ? (string) BANNER_UPLOAD_PATH : '',
	];
}

function media_serve_normalize_rel(string $path): string
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
	if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $path)) {
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
	if (!isset($roots[$bucket]) || $roots[$bucket] === '') {
		return null;
	}
	$rel = media_serve_normalize_rel($rel);
	if ($rel === '') {
		return null;
	}

	$base = $roots[$bucket];
	if (!is_dir($base)) {
		return null;
	}
	$baseReal = realpath($base);
	if ($baseReal === false) {
		return null;
	}

	$candidate = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
	if (!is_file($candidate)) {
		return null;
	}
	$fileReal = realpath($candidate);
	if ($fileReal === false) {
		return null;
	}
	$prefix = $baseReal . DIRECTORY_SEPARATOR;
	if (!str_starts_with($fileReal, $prefix) && $fileReal !== $baseReal) {
		return null;
	}

	return $fileReal;
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
		default => 'application/octet-stream',
	};

	$size = @filesize($file);
	$mtime = @filemtime($file) ?: time();
	$etag = '"' . md5($file . '|' . $mtime . '|' . (string) $size) . '"';

	if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
		http_response_code(304);
		header('ETag: ' . $etag);
		header('Cache-Control: public, max-age=604800');
		return true;
	}

	http_response_code(200);
	header('Content-Type: ' . $mime);
	header('Content-Length: ' . (string) (int) $size);
	header('ETag: ' . $etag);
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
	header('Cache-Control: public, max-age=604800');
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
	$roots = media_serve_bucket_roots();
	if (!isset($roots[$bucket]) || $roots[$bucket] === '' || !is_dir($roots[$bucket])) {
		return 0;
	}
	$count = 0;
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($roots[$bucket], FilesystemIterator::SKIP_DOTS)
	);
	foreach ($it as $file) {
		/** @var SplFileInfo $file */
		if (!$file->isFile()) {
			continue;
		}
		if ($file->getFilename() === '.gitkeep') {
			continue;
		}
		$count++;
	}

	return $count;
}
