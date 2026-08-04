<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('products');
require_once ROOT . '/lib/product.php';
require_once ROOT . '/lib/product_media.php';

header('Content-Type: application/json');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}
$pdo = $GLOBALS['pdo'];
$product_id = (int) ($_POST['product_id'] ?? 0);
if ($product_id <= 0) {
	echo json_encode(['ok' => false, 'error' => 'Invalid product']);
	exit;
}
$product = product_find_by_id($pdo, $product_id);
if (!$product) {
	echo json_encode(['ok' => false, 'error' => 'Product not found']);
	exit;
}

$files = $_FILES['images'] ?? null;
if (!$files || !is_array($files['name'])) {
	$files = ['name' => [$_FILES['image']['name'] ?? ''], 'type' => [$_FILES['image']['type'] ?? ''], 'tmp_name' => [$_FILES['image']['tmp_name'] ?? ''], 'error' => [$_FILES['image']['error'] ?? 4], 'size' => [$_FILES['image']['size'] ?? 0]];
}

if (!defined('UPLOAD_PATH')) {
	echo json_encode(['ok' => false, 'error' => 'Upload path not configured']);
	exit;
}
$base_upload = UPLOAD_PATH;
if (!is_dir($base_upload)) {
	if (!@mkdir($base_upload, 0755, true)) {
		echo json_encode(['ok' => false, 'error' => 'Cannot create uploads folder. Check permissions.']);
		exit;
	}
}
if (!is_writable($base_upload)) {
	echo json_encode(['ok' => false, 'error' => 'Uploads folder is not writable.']);
	exit;
}

$uploaded = [];
$max_size = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : (5 * 1024 * 1024);
$allowed = defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

for ($i = 0; $i < count($files['name']); $i++) {
	$err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
	if ($err === UPLOAD_ERR_NO_FILE || empty($files['tmp_name'][$i])) continue;
	if ($err !== UPLOAD_ERR_OK) {
		$msg = $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'File too large' : 'Upload error ' . $err;
		echo json_encode(['ok' => false, 'error' => $msg]);
		exit;
	}
	if (!is_uploaded_file($files['tmp_name'][$i])) continue;
	if (($files['size'][$i] ?? 0) > $max_size) {
		echo json_encode(['ok' => false, 'error' => 'File too large']);
		exit;
	}
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	$mime = $finfo->file($files['tmp_name'][$i]);
	if (!in_array($mime, $allowed, true)) {
		echo json_encode(['ok' => false, 'error' => 'Invalid file type']);
		exit;
	}
	$ext = match ($mime) {
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/gif' => 'gif',
		'image/webp' => 'webp',
		default => 'jpg',
	};
	$folder_name = bin2hex(random_bytes(8));
	$image_name = 'image.' . $ext;
	$rel_path = $product_id . '/' . $folder_name . '/' . $image_name;
	$image_dir = $base_upload . '/' . $product_id . '/' . $folder_name;
	if (!is_dir($image_dir)) {
		if (!@mkdir($image_dir, 0755, true)) {
			echo json_encode(['ok' => false, 'error' => 'Cannot create image folder. Check permissions.']);
			exit;
		}
	}
	$dest = $image_dir . '/' . $image_name;
	if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
		$err = defined('APP_DEBUG') && APP_DEBUG ? ' (path: ' . $dest . ')' : '';
		echo json_encode(['ok' => false, 'error' => 'Upload failed. Check folder permissions.' . $err]);
		exit;
	}
	$existing = product_media_list_by_product($pdo, $product_id);
	$sort_order = empty($existing) ? 0 : max(array_column($existing, 'sort_order')) + 1;
	$is_primary = empty($existing) ? 1 : 0;
	$media_id = product_media_insert($pdo, $product_id, 'image', $rel_path, $sort_order, $is_primary);
	$uploaded[] = ['id' => (int) $media_id, 'url' => $rel_path, 'sort_order' => $sort_order, 'is_primary' => (bool) $is_primary];
}

echo json_encode(['ok' => true, 'images' => $uploaded]);
