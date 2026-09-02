<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('blogs');
require_once ROOT . '/lib/blog.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('blogs/'));
}
if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('blogs/'));
}

$pdo = $GLOBALS['pdo'];
blog_ensure_schema($pdo);
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0 || !blog_find_by_id($pdo, $id)) {
	flash_set('error', 'Blog post not found.');
	redirect(admin_url('blogs/'));
}

blog_soft_delete($pdo, $id);
flash_set('success', 'Blog post deleted.');
redirect(admin_url('blogs/'));
