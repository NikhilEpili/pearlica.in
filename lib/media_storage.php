<?php
/**
 * Media roots for Hostinger: write outside public_html when pearlica-media exists
 * so git deploy cannot wipe uploads. Always probe both trees when reading.
 */
declare(strict_types=1);

/** @return list<string> */
function media_storage_bucket_names(): array
{
	return ['products', 'collections', 'banners', 'reels', 'blogs'];
}

function media_storage_sibling_root(): string
{
	$parent = dirname((string) BASE_PATH);
	return rtrim(str_replace('\\', '/', $parent), '/') . '/pearlica-media';
}

function media_storage_legacy_root(): string
{
	return rtrim(str_replace('\\', '/', (string) BASE_PATH), '/') . '/uploads';
}

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
 * Prefer pearlica-media (survives Hostinger git replace of public_html).
 * Fall back to public_html/uploads when the sibling folder does not exist.
 */
function media_storage_resolve_root(): string
{
	$sibling = media_storage_sibling_root();
	$legacy = media_storage_legacy_root();
	if (is_dir($sibling)) {
		return $sibling;
	}
	return $legacy;
}

/**
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
		if (!$file->isFile() || $file->getFilename() === '.gitkeep') {
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
 * Merge media both ways: pearlica-media ↔ public_html/uploads (and nested uploads mistake).
 *
 * @return array{ok:bool, dest:string, buckets:array<string, array{copied:int, skipped:int, errors:list<string>}>, sources:list<string>, error?:string}
 */
function media_storage_sync_trees(): array
{
	$sibling = media_storage_sibling_root();
	$legacy = media_storage_legacy_root();
	$pairs = [];

	if (!media_storage_ensure_buckets($sibling)) {
		return ['ok' => false, 'dest' => $sibling, 'buckets' => [], 'sources' => [], 'error' => 'Cannot create ' . $sibling . ' — create folder pearlica-media next to public_html in File Manager.'];
	}
	if (!media_storage_ensure_buckets($legacy)) {
		return ['ok' => false, 'dest' => $legacy, 'buckets' => [], 'sources' => [], 'error' => 'Cannot create ' . $legacy];
	}

	// Flatten pearlica-media/uploads/{bucket} → pearlica-media/{bucket}.
	$nested = $sibling . '/uploads';
	if (is_dir($nested)) {
		foreach (media_storage_bucket_names() as $bucket) {
			media_storage_copy_bucket($nested, $sibling, $bucket);
		}
	}

	$sources = [];
	foreach ([$legacy, $sibling] as $root) {
		if (is_dir($root)) {
			$sources[] = $root;
		}
	}

	$buckets = [];
	foreach (media_storage_bucket_names() as $bucket) {
		$merged = ['copied' => 0, 'skipped' => 0, 'errors' => []];
		// uploads → pearlica-media
		$a = media_storage_copy_bucket($legacy, $sibling, $bucket);
		// pearlica-media → uploads (so Apache can serve statically too)
		$b = media_storage_copy_bucket($sibling, $legacy, $bucket);
		$merged['copied'] = $a['copied'] + $b['copied'];
		$merged['skipped'] = $a['skipped'] + $b['skipped'];
		$merged['errors'] = array_merge($a['errors'], $b['errors']);
		$buckets[$bucket] = $merged;
	}

	return ['ok' => true, 'dest' => $sibling, 'buckets' => $buckets, 'sources' => $sources];
}

/** @deprecated use media_storage_sync_trees */
function media_storage_migrate_from_legacy(): array
{
	return media_storage_sync_trees();
}

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
