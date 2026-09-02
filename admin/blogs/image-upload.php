<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('blogs');
require_once ROOT . '/lib/blog.php';

header('Content-Type: application/json');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

$pdo = $GLOBALS['pdo'];
blog_ensure_schema($pdo);
$post_id = (int) ($_POST['post_id'] ?? 0);
if ($post_id <= 0) {
	echo json_encode(['ok' => false, 'error' => 'Invalid post']);
	exit;
}
$post = blog_find_by_id($pdo, $post_id);
if (!$post) {
	echo json_encode(['ok' => false, 'error' => 'Post not found']);
	exit;
}

$base_upload = blog_upload_dir();
if ($base_upload === '') {
	echo json_encode(['ok' => false, 'error' => 'Blog upload path not configured']);
	exit;
}
if (!is_dir($base_upload)) {
	if (!@mkdir($base_upload, 0755, true)) {
		echo json_encode(['ok' => false, 'error' => 'Cannot create blogs uploads folder.']);
		exit;
	}
}
if (!is_writable($base_upload)) {
	echo json_encode(['ok' => false, 'error' => 'Blogs uploads folder is not writable.']);
	exit;
}

$file = $_FILES['image'] ?? null;
if (!$file || !is_uploaded_file($file['tmp_name'] ?? '')) {
	echo json_encode(['ok' => false, 'error' => 'No image uploaded']);
	exit;
}
$err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err !== UPLOAD_ERR_OK) {
	$msg = $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'File too large' : 'Upload error ' . $err;
	echo json_encode(['ok' => false, 'error' => $msg]);
	exit;
}

$max_size = defined('BLOG_MAX_UPLOAD_SIZE') ? BLOG_MAX_UPLOAD_SIZE : (12 * 1024 * 1024);
if (($file['size'] ?? 0) > $max_size) {
	$max_mb = (int) round($max_size / (1024 * 1024));
	echo json_encode(['ok' => false, 'error' => 'File too large (max ' . $max_mb . 'MB)']);
	exit;
}

$allowed = defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
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

if (!empty($post['cover_image'])) {
	blog_delete_cover_disk((string) $post['cover_image']);
}

$folder_name = bin2hex(random_bytes(8));
$image_name = 'cover.' . $ext;
$rel_path = $post_id . '/' . $folder_name . '/' . $image_name;
$image_dir = $base_upload . '/' . $post_id . '/' . $folder_name;
if (!is_dir($image_dir) && !@mkdir($image_dir, 0755, true)) {
	echo json_encode(['ok' => false, 'error' => 'Cannot create image folder.']);
	exit;
}
$dest = $image_dir . '/' . $image_name;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
	echo json_encode(['ok' => false, 'error' => 'Upload failed.']);
	exit;
}

blog_set_cover_image($pdo, $post_id, $rel_path);
$public_url = blog_cover_public_url($rel_path, rtrim(web_base(), '/') . '/');
echo json_encode(['ok' => true, 'path' => $rel_path, 'url' => $public_url]);
