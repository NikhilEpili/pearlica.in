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
try {
	homepage_banner_ensure_schema($pdo);
} catch (Throwable $e) {
	echo json_encode(['ok' => false, 'error' => 'Could not prepare banners table. Run sql/add_homepage_banners.sql if needed.']);
	exit;
}
$placement = homepage_banner_normalize_placement((string) ($_POST['placement'] ?? 'hero'));
$alt_text = trim((string) ($_POST['alt_text'] ?? ''));
$link_url = trim((string) ($_POST['link_url'] ?? ''));

if (!defined('BANNER_UPLOAD_PATH')) {
	echo json_encode(['ok' => false, 'error' => 'Banner upload path not configured']);
	exit;
}
$base_upload = BANNER_UPLOAD_PATH;
if (!is_dir($base_upload)) {
	if (!@mkdir($base_upload, 0755, true)) {
		echo json_encode(['ok' => false, 'error' => 'Cannot create banners uploads folder.']);
		exit;
	}
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

try {
	$id = homepage_banner_insert(
		$pdo,
		$rel_path,
		$placement,
		$alt_text !== '' ? $alt_text : 'Homepage banner',
		$link_url !== '' ? $link_url : null
	);
} catch (Throwable $e) {
	@unlink($dest);
	@rmdir($image_dir);
	echo json_encode(['ok' => false, 'error' => 'Could not save banner. Refresh Banners to auto-create the table, or run sql/add_homepage_banners.sql.']);
	exit;
}

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
		'id' => (int) $id,
		'image_url' => $rel_path,
		'src' => homepage_banner_public_src_versioned($rel_path, $uploads_base, (int) $id, $updated_at !== '' ? $updated_at : null),
		'alt_text' => (string) ($banner['alt_text'] ?? ''),
		'link_url' => (string) ($banner['link_url'] ?? ''),
		'placement' => (string) ($banner['placement'] ?? $placement),
		'sort_order' => (int) ($banner['sort_order'] ?? 0),
		'is_active' => !empty($banner['is_active']),
	],
]);
