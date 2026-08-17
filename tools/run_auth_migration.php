<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$pdo = $GLOBALS['pdo'];
if (!$pdo instanceof PDO) {
	fwrite(STDERR, "No database connection.\n");
	exit(1);
}

$statements = [
	"ALTER TABLE `store_users` ADD COLUMN `password_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `phone`",
	<<<'SQL'
CREATE TABLE IF NOT EXISTS `store_user_addresses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `store_user_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(80) NULL DEFAULT NULL,
  `full_name` VARCHAR(255) NOT NULL DEFAULT '',
  `phone` VARCHAR(100) NULL DEFAULT NULL,
  `address` VARCHAR(255) NOT NULL,
  `address_line_2` VARCHAR(255) NULL DEFAULT NULL,
  `city` VARCHAR(120) NOT NULL,
  `state` VARCHAR(120) NOT NULL,
  `pincode` VARCHAR(20) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_store_user_addresses_user` (`store_user_id`),
  CONSTRAINT `fk_store_user_addresses_user` FOREIGN KEY (`store_user_id`) REFERENCES `store_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

foreach ($statements as $stmt) {
	try {
		$pdo->exec($stmt);
		echo 'OK: ' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 72) . PHP_EOL;
	} catch (Throwable $e) {
		echo 'ERR: ' . $e->getMessage() . PHP_EOL;
	}
}
