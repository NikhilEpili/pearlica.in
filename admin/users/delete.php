<?php
require_once dirname(__DIR__) . '/config.php';
require_super_admin();
require_once ROOT . '/lib/admin_user.php';

$pdo = $GLOBALS['pdo'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('users/'));
}
if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('users/'));
}
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
	redirect(admin_url('users/'));
}
if ($id === (int) ($_SESSION['admin_id'] ?? 0)) {
	flash_set('error', 'You cannot delete your own account.');
	redirect(admin_url('users/'));
}
$user = admin_user_find_by_id($pdo, $id);
if (!$user) {
	flash_set('error', 'User not found.');
	redirect(admin_url('users/'));
}
admin_user_soft_delete($pdo, $id);
flash_set('success', 'User deleted.');
redirect(admin_url('users/'));
