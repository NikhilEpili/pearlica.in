<?php
/**
 * Poster frame for an Instagram reel, proxied and cached on disk.
 * Instagram's CDN is unreliable to hotlink, so the bytes are served from here.
 *
 * Usage: reel-thumbnail.php?shortcode=ABC123&type=reel
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/lib/instagram_reel.php';

$shortcode = trim((string) ($_GET['shortcode'] ?? ''));
$media_type = strtolower(trim((string) ($_GET['type'] ?? 'reel')));
if ($shortcode === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $shortcode)) {
	http_response_code(400);
	exit;
}
if ($media_type !== 'post') {
	$media_type = 'reel';
}

$cache_dir = instagram_reel_thumbnail_cache_dir();
$cache_file = $cache_dir . '/' . $shortcode . '.jpg';

if (is_file($cache_file) && filesize($cache_file) > 0) {
	header('Content-Type: image/jpeg');
	header('Content-Length: ' . filesize($cache_file));
	header('Cache-Control: public, max-age=604800');
	readfile($cache_file);
	exit;
}

$path = $media_type === 'post' ? 'p' : 'reel';
$permalink = 'https://www.instagram.com/' . $path . '/' . rawurlencode($shortcode) . '/';
$url = instagram_reel_thumbnail_url($permalink, $shortcode);

$bytes = $url !== '' ? instagram_reel_download_image($url) : '';
if ($bytes === '') {
	http_response_code(404);
	header('Cache-Control: no-store');
	exit;
}

if (!is_dir($cache_dir)) {
	@mkdir($cache_dir, 0755, true);
}
if (is_dir($cache_dir) && is_writable($cache_dir)) {
	file_put_contents($cache_file, $bytes);
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: public, max-age=604800');
echo $bytes;
