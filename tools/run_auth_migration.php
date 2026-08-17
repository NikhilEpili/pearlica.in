<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/store_user.php';
require_once __DIR__ . '/../lib/store_user_address.php';

$pdo = $GLOBALS['pdo'];
if (!$pdo instanceof PDO) {
	fwrite(STDERR, "No database connection.\n");
	exit(1);
}

try {
	store_user_ensure_schema($pdo);
	echo "OK: store_users schema\n";
	store_user_address_ensure_schema($pdo);
	echo "OK: store_user_addresses schema\n";
} catch (Throwable $e) {
	echo 'ERR: ' . $e->getMessage() . PHP_EOL;
	exit(1);
}
