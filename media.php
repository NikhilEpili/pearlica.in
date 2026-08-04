<?php
/**
 * Public media gateway for /uploads/{products|collections|banners}/...
 * Invoked by .htaccess when the static file is missing so PHP can still
 * stream from BASE_PATH (same tree admin uploads write to).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/media_serve.php';

$bucket = strtolower(trim((string) ($_GET['bucket'] ?? '')));
$path = (string) ($_GET['path'] ?? '');

if ($bucket === '' || $path === '') {
	http_response_code(404);
	header('Content-Type: text/plain; charset=UTF-8');
	echo 'Not found';
	exit;
}

if (!media_serve_stream($bucket, $path)) {
	http_response_code(404);
	header('Content-Type: text/plain; charset=UTF-8');
	header('Cache-Control: no-store');
	echo 'Not found';
	exit;
}
