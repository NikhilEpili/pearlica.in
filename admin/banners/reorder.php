<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('banners');
require_once ROOT . '/lib/homepage_banner.php';

header('Content-Type: application/json');

$pdo = $GLOBALS['pdo'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'] ?? '')) {
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

$placement = homepage_banner_normalize_placement((string) ($input['placement'] ?? 'hero'));
$order = $input['order'] ?? [];
if (!is_array($order)) {
	$order = [];
}
$order = array_map('intval', array_values($order));
homepage_banner_update_order($pdo, $placement, $order);
echo json_encode(['ok' => true]);
