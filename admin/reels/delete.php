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

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
	echo json_encode(['ok' => false, 'error' => 'Invalid reel']);
	exit;
}

instagram_reel_ensure_schema($pdo);
try {
	if (!instagram_reel_soft_delete($pdo, $id)) {
		echo json_encode(['ok' => false, 'error' => 'Could not delete reel']);
		exit;
	}
} catch (InvalidArgumentException $e) {
	echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
	exit;
}

echo json_encode(['ok' => true]);
