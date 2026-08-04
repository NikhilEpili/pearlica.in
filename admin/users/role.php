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
$make_super = isset($_POST['make_super']) ? (int) $_POST['make_super'] : 0;

if ($id <= 0) {
	redirect(admin_url('users/'));
}

$user = admin_user_find_by_id($pdo, $id);
if (!$user) {
	flash_set('error', 'User not found.');
	redirect(admin_url('users/'));
}

// Prevent removing the last super admin
if ($make_super === 0 && !empty($user['is_super_admin'])) {
	$stmt = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE is_super_admin = 1 AND deleted_at IS NULL");
	$total_supers = (int) $stmt->fetchColumn();
	if ($total_supers <= 1) {
		flash_set('error', 'Cannot remove the last super admin.');
		redirect(admin_url('users/'));
	}
}

$stmt = $pdo->prepare('UPDATE admin_users SET is_super_admin = ? WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$make_super ? 1 : 0, $id]);

flash_set('success', 'Permissions updated for ' . h($user['username']) . '.');
redirect(admin_url('users/'));

