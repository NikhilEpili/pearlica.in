<?php
/**
 * Router for PHP built-in server (php -S). Mimics .htaccess clean URLs.
 * Usage: php -S localhost:8080 router.php
 */
declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . $uri;

// Serve existing files/directories as-is
if ($uri !== '/' && is_file($file)) {
	return false;
}

// Uploads: prefer on-disk file; else stream via media.php; local hosts may redirect to prod.
if (preg_match('#^/uploads/(products|collections|banners|reels|blogs)/(.+)$#', $uri, $m) && !is_file($file)) {
	$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
	$host = preg_replace('/:\d+$/', '', $host) ?? $host;
	$is_local = ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1'
		|| (bool) preg_match('/\.(local|test|localhost)$/', $host));
	if ($is_local) {
		header('Location: https://www.pearlica.in/uploads/' . $m[1] . '/' . $m[2], true, 302);
		return true;
	}
	$_GET['bucket'] = $m[1];
	$_GET['path'] = $m[2];
	require __DIR__ . '/media.php';
	return true;
}

// /blogs/{slug} -> blogs/post.php
if (preg_match('#^/blogs/(?!index$|rss$)([A-Za-z0-9][A-Za-z0-9_-]*)/?$#', $uri, $m)) {
	$_GET['slug'] = $m[1];
	require __DIR__ . '/blogs/post.php';
	return true;
}

// Strip trailing slash (except root)
$path = rtrim($uri, '/');
if ($path === '') {
	$path = '/index';
}

$phpFile = __DIR__ . $path . '.php';
if (is_file($phpFile)) {
	require $phpFile;
	return true;
}

// Directory index
if (is_dir(__DIR__ . $uri) && is_file(__DIR__ . $uri . '/index.php')) {
	require __DIR__ . $uri . '/index.php';
	return true;
}

http_response_code(404);
require __DIR__ . '/404.php';
return true;
