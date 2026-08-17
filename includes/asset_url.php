<?php
/**
 * Versioned URLs for local static assets.
 *
 * The host serves js/css with `Cache-Control: public, max-age=604800`, so a plain
 * path keeps returning visitors on the pre-deploy file for up to a week. Appending
 * the file's mtime makes every deploy a new URL without touching server config.
 */
declare(strict_types=1);

if (!function_exists('asset_url')) {
	function asset_url(string $relative_path, string $base_path = ''): string
	{
		$relative_path = ltrim($relative_path, '/');
		$url = $base_path . $relative_path;
		$root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
		$file = $root . '/' . $relative_path;
		$mtime = is_file($file) ? @filemtime($file) : false;

		return $mtime === false ? $url : $url . '?v=' . $mtime;
	}
}
