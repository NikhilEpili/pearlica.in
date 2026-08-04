<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('banners');
require_once ROOT . '/lib/homepage_banner.php';

$pdo = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('banners/'));
}
if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('banners/'));
}

$id = (int) ($_POST['id'] ?? 0);
$banner = homepage_banner_find_by_id($pdo, $id);
if (!$banner) {
	flash_set('error', 'Banner not found.');
	redirect(admin_url('banners/'));
}

$ok = homepage_banner_update_meta(
	$pdo,
	$id,
	(string) ($_POST['alt_text'] ?? ''),
	(string) ($_POST['link_url'] ?? ''),
	(string) ($_POST['placement'] ?? 'hero'),
	!empty($_POST['is_active'])
);

flash_set($ok ? 'success' : 'error', $ok ? 'Banner updated.' : 'Could not update banner.');
redirect(admin_url('banners/#' . homepage_banner_normalize_placement((string) ($_POST['placement'] ?? $banner['placement']))));
