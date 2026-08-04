<?php
/**
 * One-shot: create homepage_banners (+ can_banners) on this database.
 * Visit while logged into admin, then delete this file.
 */
require_once dirname(__DIR__) . '/config.php';
require_permission('banners');

$pdo = $GLOBALS['pdo'];
$ok = false;
$error = '';

try {
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS `homepage_banners` (
		  `id` INT AUTO_INCREMENT PRIMARY KEY,
		  `image_url` VARCHAR(512) NOT NULL,
		  `alt_text` VARCHAR(255) NOT NULL DEFAULT \'\',
		  `link_url` VARCHAR(512) NULL DEFAULT NULL,
		  `placement` VARCHAR(16) NOT NULL DEFAULT \'hero\',
		  `sort_order` INT NOT NULL DEFAULT 0,
		  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
		  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
		  INDEX `idx_homepage_banners_list` (`placement`, `is_active`, `sort_order`, `deleted_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
	);

	$stmt = $pdo->query(
		"SELECT COUNT(*) FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = 'admin_user_permissions'
		   AND COLUMN_NAME = 'can_banners'"
	);
	if ($stmt && (int) $stmt->fetchColumn() === 0) {
		$pdo->exec(
			'ALTER TABLE `admin_user_permissions`
			 ADD COLUMN `can_banners` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_orders`'
		);
	}

	$pdo->query('SELECT 1 FROM homepage_banners LIMIT 1');
	$ok = true;
} catch (Throwable $e) {
	$error = $e->getMessage();
}

if ($ok) {
	flash_set('success', 'Homepage banners table is ready. You can delete admin/banners/install-table.php.');
	redirect(admin_url('banners/'));
}

$page_title = 'Install banners table';
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Install banners table</h1>
	<div class="alert alert-danger">
		Could not create the table: <?= h($error) ?>
	</div>
	<p><a href="<?= h(admin_url('banners/')) ?>">Back to banners</a></p>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
</body>
</html>
