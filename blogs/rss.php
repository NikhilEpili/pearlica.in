<?php
/**
 * Public JSON blog feed from admin-managed posts.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/blog.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=60, must-revalidate');

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
	http_response_code(503);
	echo json_encode(['error' => 'Database unavailable'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

try {
	blog_ensure_schema($pdo);
	$payload = blog_feed_payload($pdo);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['error' => 'Could not load blog feed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
