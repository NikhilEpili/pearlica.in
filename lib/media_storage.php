<?php
/**
 * Durable media root: keep uploads outside the Hostinger git deploy tree
 * so redeploys never wipe admin-uploaded product/banner/reel files.
 */
declare(strict_types=1);

/** @return list<string> */
function media_storage_bucket_names(): array
{
	return ['products', 'collections', 'banners', 'reels', 'blogs'];
}

/**
 * Sibling folder next to public_html (Hostinger-safe). Never under BASE_PATH.
 */
function media_storage_sibling_root(): string
{
	$parent = dirname((string) BASE_PATH);
	return rtrim(str_replace('\\', '/', $parent), '/') . '/pearlica-media';
}

function media_storage_legacy_root(): string
{
	return rtrim(str_replace('\\', '/', (string) BASE_PATH), '/') . '/uploads';
}

/**
 * True when MEDIA_STORAGE_ROOT is outside the git-deployed app tree.
 */
function media_storage_is_durable(): bool
{
	if (!defined('MEDIA_STORAGE_ROOT') || !defined('BASE_PATH')) {
		return false;
	}
	$root = rtrim(str_replace('\\', '/', (string) MEDIA_STORAGE_ROOT), '/');
	$legacy = media_storage_legacy_root();
	$base = rtrim(str_replace('\\', '/', (string) BASE_PATH), '/');
	if ($root === '' || $root === $legacy) {
		return false;
	}
	return !str_starts_with($root, $base . '/');
}

/**
 * Create bucket dirs under the current media root. Returns false if root cannot be created.
 */
function media_storage_ensure_buckets(?string $root = null): bool
{
	$root = $root ?? (defined('MEDIA_STORAGE_ROOT') ? (string) MEDIA_STORAGE_ROOT : '');
	$root = rtrim(str_replace('\\', '/', $root), '/');
	if ($root === '') {
		return false;
	}
	if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) {
		return false;
	}
	foreach (media_storage_bucket_names() as $bucket) {
		$dir = $root . '/' . $bucket;
		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
			return false;
		}
	}
	return true;
}

/**
 * Resolve where new uploads should be written.
 * Prefers an existing sibling pearlica-media/; on production, creates it when possible.
 */
function media_storage_resolve_root(): string
{
	$sibling = media_storage_sibling_root();
	$legacy = media_storage_legacy_root();

	if (is_dir($sibling)) {
		return $sibling;
	}

	if (function_exists('pearlica_is_local_dev_host') && pearlica_is_local_dev_host()) {
		return $legacy;
	}

	$parent = dirname((string) BASE_PATH);
	if (is_dir($parent) && is_writable($parent)) {
		if (@mkdir($sibling, 0755, true) || is_dir($sibling)) {
			return $sibling;
		}
	}

	return $legacy;
}

/**
 * Recursively copy media files from $src_root/$bucket into $dest_root/$bucket.
 * Skips .gitkeep; does not delete destination extras.
 *
 * @return array{copied:int, skipped:int, errors:list<string>}
 */
function media_storage_copy_bucket(string $src_root, string $dest_root, string $bucket): array
{
	$copied = 0;
	$skipped = 0;
	$errors = [];
	$src = rtrim(str_replace('\\', '/', $src_root), '/') . '/' . $bucket;
	$dest = rtrim(str_replace('\\', '/', $dest_root), '/') . '/' . $bucket;

	if (!is_dir($src)) {
		return ['copied' => 0, 'skipped' => 0, 'errors' => []];
	}
	if (!is_dir($dest) && !@mkdir($dest, 0755, true) && !is_dir($dest)) {
		return ['copied' => 0, 'skipped' => 0, 'errors' => ['Cannot create ' . $dest]];
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		/** @var SplFileInfo $file */
		if (!$file->isFile()) {
			continue;
		}
		if ($file->getFilename() === '.gitkeep') {
			continue;
		}
		$full = str_replace('\\', '/', $file->getPathname());
		$rel = substr($full, strlen($src) + 1);
		if ($rel === false || $rel === '') {
			continue;
		}
		$target = $dest . '/' . $rel;
		$target_dir = dirname($target);
		if (!is_dir($target_dir) && !@mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
			$errors[] = 'Cannot create dir for ' . $rel;
			continue;
		}
		if (is_file($target) && @filesize($target) === @filesize($full)) {
			$skipped++;
			continue;
		}
		if (!@copy($full, $target)) {
			$errors[] = 'Copy failed: ' . $rel;
			continue;
		}
		$copied++;
	}

	return ['copied' => $copied, 'skipped' => $skipped, 'errors' => $errors];
}

/**
 * Migrate all buckets from legacy BASE_PATH/uploads into durable sibling root.
 *
 * @return array{ok:bool, dest:string, buckets:array<string, array{copied:int, skipped:int, errors:list<string>}>, error?:string}
 */
function media_storage_migrate_from_legacy(): array
{
	$dest = media_storage_sibling_root();
	$src = media_storage_legacy_root();
	if ($dest === $src) {
		return ['ok' => false, 'dest' => $dest, 'buckets' => [], 'error' => 'Destination equals legacy uploads path.'];
	}
	if (!media_storage_ensure_buckets($dest)) {
		return ['ok' => false, 'dest' => $dest, 'buckets' => [], 'error' => 'Cannot create durable media folders at ' . $dest];
	}

	$buckets = [];
	foreach (media_storage_bucket_names() as $bucket) {
		$buckets[$bucket] = media_storage_copy_bucket($src, $dest, $bucket);
	}

	return ['ok' => true, 'dest' => $dest, 'buckets' => $buckets];
}

/**
 * Public media URL with mtime cache-bust when the file is readable.
 */
function media_storage_public_url(string $uploads_base, string $rel, string $bucket = 'products'): string
{
	$rel = str_replace('\\', '/', trim($rel));
	$rel = ltrim($rel, '/');
	$base = rtrim($uploads_base, '/');
	if ($rel === '') {
		return $base;
	}
	$url = $base . '/' . $rel;
	if (!function_exists('media_serve_resolve')) {
		return $url;
	}
	$file = media_serve_resolve($bucket, $rel);
	if ($file === null) {
		return $url;
	}
	$mtime = @filemtime($file);
	if (!$mtime) {
		return $url;
	}

	return $url . '?v=' . (string) (int) $mtime;
}
