<?php
/**
 * GET q= — JSON suggestions for site search (products + categories).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/product.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$pdo = $GLOBALS['pdo'];
$suggestions = product_search_suggestions($pdo, $q, 12);

echo json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
