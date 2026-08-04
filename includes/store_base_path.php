<?php
/**
 * URL path prefix for this app under the web server (e.g. "/" or "/pearlica.in/").
 * Always ends with "/" when non-empty. Used for assets and internal links so pages
 * under deep routes (e.g. /blogs/) still load /assets/... correctly.
 */
declare(strict_types=1);

function store_base_path_from_request(): string
{
	$docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	$path_only = parse_url((string) $req_uri, PHP_URL_PATH) ?: '/';
	$parts = array_values(array_filter(explode('/', $path_only)));

	// Prefer filesystem layout: app root is the parent of `includes/` (this file).
	// Works on Hostinger etc. when is_dir(DOCUMENT_ROOT . '/assets') fails (open_basedir, symlinks).
	$appRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
	if ($docRoot !== '' && $appRoot !== false) {
		$appNorm = rtrim(str_replace('\\', '/', $appRoot), '/');
		if ($appNorm !== '' && str_starts_with($appNorm, $docRoot)) {
			$rel = trim(substr($appNorm, strlen($docRoot)), '/');
			return $rel === '' ? '/' : '/' . $rel . '/';
		}
	}

	// Subdirectory install: first URL segment is a folder under docroot that contains assets/
	if ($docRoot !== '' && $parts !== []) {
		$first = (string) $parts[0];
		if ($first !== '' && is_dir($docRoot . '/' . $first . '/assets')) {
			return '/' . $first . '/';
		}
	}

	// Typical production: assets/ lives directly under DOCUMENT_ROOT
	if ($docRoot !== '' && is_dir($docRoot . '/assets')) {
		return '/';
	}

	// Fallback when DOCUMENT_ROOT is unset or paths are unusual (CLI, custom routing).
	$parts = array_values(array_filter(explode('/', $path_only)));
	if (count($parts) <= 1) {
		return '/';
	}
	$parents = $parts;
	array_pop($parents);
	return '/' . implode('/', $parents) . '/';
}
