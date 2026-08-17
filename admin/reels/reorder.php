<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('reels');
require_once ROOT . '/lib/instagram_reel.php';

header('Content-Type: application/json');

$pdo = $GLOBALS['pdo'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'] ?? '')) {
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}

$order = $input['order'] ?? [];
if (!is_array($order)) {
	$order = [];
}

instagram_reel_ensure_schema($pdo);
instagram_reel_update_order($pdo, $order);
echo json_encode(['ok' => true]);
