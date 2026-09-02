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

if (!empty($post['cover_image'])) {
	blog_delete_cover_disk((string) $post['cover_image']);
}
blog_set_cover_image($pdo, $post_id, null);
echo json_encode(['ok' => true]);
