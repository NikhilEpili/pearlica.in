<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('collections');
require_once ROOT . '/lib/collection.php';

header('Content-Type: application/json');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}
$pdo = $GLOBALS['pdo'];
$collection_id = (int) ($_POST['collection_id'] ?? 0);
if ($collection_id <= 0) {
	echo json_encode(['ok' => false, 'error' => 'Invalid collection']);
	exit;
}
$collection = collection_find_by_id($pdo, $collection_id);
if (!$collection) {
	echo json_encode(['ok' => false, 'error' => 'Collection not found']);
	exit;
}

if (!defined('COLLECTION_UPLOAD_PATH')) {
	echo json_encode(['ok' => false, 'error' => 'Collection upload path not configured']);
	exit;
}
$base_upload = COLLECTION_UPLOAD_PATH;
if (!is_dir($base_upload)) {
	if (!@mkdir($base_upload, 0755, true)) {
		echo json_encode(['ok' => false, 'error' => 'Cannot create collections uploads folder.']);
		exit;
	}
}
if (!is_writable($base_upload)) {
	echo json_encode(['ok' => false, 'error' => 'Collections uploads folder is not writable.']);
	exit;
}

$files = $_FILES['images'] ?? null;
if (!$files || !is_array($files['name'])) {
	$files = ['name' => [$_FILES['image']['name'] ?? ''], 'type' => [$_FILES['image']['type'] ?? ''], 'tmp_name' => [$_FILES['image']['tmp_name'] ?? ''], 'error' => [$_FILES['image']['error'] ?? 4], 'size' => [$_FILES['image']['size'] ?? 0]];
}

$max_size = defined('COLLECTION_MAX_UPLOAD_SIZE') ? COLLECTION_MAX_UPLOAD_SIZE : (12 * 1024 * 1024);
$allowed = defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

$uploaded = [];
for ($i = 0; $i < count($files['name']); $i++) {
	$err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
	if ($err === UPLOAD_ERR_NO_FILE || empty($files['tmp_name'][$i])) {
		continue;
	}
	if ($err !== UPLOAD_ERR_OK) {
		$msg = $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'File too large' : 'Upload error ' . $err;
		echo json_encode(['ok' => false, 'error' => $msg]);
		exit;
	}
	if (!is_uploaded_file($files['tmp_name'][$i])) {
		continue;
	}
	if (($files['size'][$i] ?? 0) > $max_size) {
		$max_mb = (int) round($max_size / (1024 * 1024));
		echo json_encode(['ok' => false, 'error' => 'File too large (max ' . $max_mb . 'MB)']);
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

	if (!empty($collection['thumbnail_url'])) {
		collection_delete_thumbnail_disk((string) $collection['thumbnail_url']);
	}

	$folder_name = bin2hex(random_bytes(8));
	$image_name = 'image.' . $ext;
	$rel_path = $collection_id . '/' . $folder_name . '/' . $image_name;
	$image_dir = $base_upload . '/' . $collection_id . '/' . $folder_name;
	if (!is_dir($image_dir)) {
		if (!@mkdir($image_dir, 0755, true)) {
			echo json_encode(['ok' => false, 'error' => 'Cannot create image folder.']);
			exit;
		}
	}
	$dest = $image_dir . '/' . $image_name;
	if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
		echo json_encode(['ok' => false, 'error' => 'Upload failed.']);
		exit;
	}
	collection_set_thumbnail_url($pdo, $collection_id, $rel_path);
	$uploaded[] = ['id' => 0, 'url' => $rel_path, 'sort_order' => 0, 'is_primary' => true];
	break;
}

echo json_encode(['ok' => true, 'images' => $uploaded]);
