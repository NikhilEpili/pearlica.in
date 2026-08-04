<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('collections');
require_once ROOT . '/lib/collection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('collections/'));
}
if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('collections/'));
}
$pdo = $GLOBALS['pdo'];
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
	redirect(admin_url('collections/'));
}
$row = collection_find_by_id($pdo, $id);
if (!$row) {
	flash_set('error', 'Collection not found.');
	redirect(admin_url('collections/'));
}
collection_soft_delete($pdo, $id);
flash_set('success', 'Collection deleted.');
redirect(admin_url('collections/'));
