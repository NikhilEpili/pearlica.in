<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('products');
require_once ROOT . '/lib/product.php';
require_once ROOT . '/lib/product_media.php';

header('Content-Type: application/json');

$pdo = $GLOBALS['pdo'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'] ?? '')) {
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}
$id = (int) ($input['id'] ?? $_POST['id'] ?? 0);
$product_id = (int) ($input['product_id'] ?? $_POST['product_id'] ?? 0);
if ($id <= 0 || $product_id <= 0) {
	echo json_encode(['ok' => false, 'error' => 'Invalid request']);
	exit;
}
$media = product_media_find_by_id($pdo, $id);
if (!$media || (int) $media['product_id'] !== $product_id) {
	echo json_encode(['ok' => false, 'error' => 'Not found']);
	exit;
}
product_media_soft_delete($pdo, $id);
$path = UPLOAD_PATH . '/' . $media['url'];
if (is_file($path)) {
	@unlink($path);
	$dir = dirname($path);
	if (is_dir($dir) && $dir !== UPLOAD_PATH && count(scandir($dir)) === 2) {
		@rmdir($dir);
	}
}
echo json_encode(['ok' => true]);
