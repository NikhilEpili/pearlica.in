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

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
	echo json_encode(['ok' => false, 'error' => 'Invalid banner']);
	exit;
}

$banner = homepage_banner_find_by_id($pdo, $id);
if (!$banner) {
	echo json_encode(['ok' => false, 'error' => 'Not found']);
	exit;
}

homepage_banner_soft_delete($pdo, $id);
homepage_banner_delete_disk((string) ($banner['image_url'] ?? ''));
echo json_encode(['ok' => true]);
