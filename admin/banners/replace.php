<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('banners');
require_once ROOT . '/lib/homepage_banner.php';
require_once ROOT . '/lib/banner_image.php';

header('Content-Type: application/json');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

$pdo = $GLOBALS['pdo'];
$id = (int) ($_POST['id'] ?? 0);
$banner = homepage_banner_find_by_id($pdo, $id);
if (!$banner) {
	echo json_encode(['ok' => false, 'error' => 'Banner not found']);
	exit;
}

if (!defined('BANNER_UPLOAD_PATH')) {
	echo json_encode(['ok' => false, 'error' => 'Banner upload path not configured']);
	exit;
}
$base_upload = BANNER_UPLOAD_PATH;
if (!is_dir($base_upload) && !@mkdir($base_upload, 0755, true)) {
	echo json_encode(['ok' => false, 'error' => 'Cannot create banners uploads folder.']);
	exit;
}
if (!is_writable($base_upload)) {
	echo json_encode(['ok' => false, 'error' => 'Banners uploads folder is not writable.']);
	exit;
}

$file = $_FILES['image'] ?? null;
if (!$file || !is_array($file)) {
	echo json_encode(['ok' => false, 'error' => 'No image selected']);
	exit;
}

$validated = banner_image_validate_upload($file);
if (empty($validated['ok'])) {
	echo json_encode(['ok' => false, 'error' => (string) ($validated['error'] ?? 'Invalid upload')]);
	exit;
}
$mime = (string) $validated['mime'];
$ext = (string) $validated['ext'];

$folder_name = bin2hex(random_bytes(8));
$image_name = 'image.' . $ext;
$rel_path = $folder_name . '/' . $image_name;
$image_dir = $base_upload . '/' . $folder_name;
if (!is_dir($image_dir) && !@mkdir($image_dir, 0755, true)) {
	echo json_encode(['ok' => false, 'error' => 'Cannot create image folder.']);
	exit;
}
$dest = $image_dir . '/' . $image_name;
$stored = banner_image_store_from_upload($file['tmp_name'], $dest, $mime);
if (empty($stored['ok'])) {
	@rmdir($image_dir);
	echo json_encode(['ok' => false, 'error' => (string) ($stored['error'] ?? 'Upload failed.')]);
	exit;
}

$old_rel = (string) ($banner['image_url'] ?? '');
if (!homepage_banner_update_image($pdo, $id, $rel_path)) {
	@unlink($dest);
	@rmdir($image_dir);
	echo json_encode(['ok' => false, 'error' => 'Could not update banner image.']);
	exit;
}
homepage_banner_delete_disk($old_rel);

$banner = homepage_banner_find_by_id($pdo, $id);
$uploads_base = uploads_banners_base_url();
$updated_at = null;
try {
	$stmt = $pdo->prepare('SELECT updated_at FROM homepage_banners WHERE id = ? LIMIT 1');
	$stmt->execute([$id]);
	$updated_at = (string) ($stmt->fetchColumn() ?: '');
} catch (Throwable $e) {
	$updated_at = null;
}

echo json_encode([
	'ok' => true,
	'banner' => [
		'id' => $id,
		'image_url' => $rel_path,
		'src' => homepage_banner_public_src_versioned($rel_path, $uploads_base, $id, $updated_at !== '' ? $updated_at : null),
		'alt_text' => (string) ($banner['alt_text'] ?? ''),
		'link_url' => (string) ($banner['link_url'] ?? ''),
		'placement' => (string) ($banner['placement'] ?? 'hero'),
		'sort_order' => (int) ($banner['sort_order'] ?? 0),
		'is_active' => !empty($banner['is_active']),
	],
]);
