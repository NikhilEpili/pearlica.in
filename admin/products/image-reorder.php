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
$product_id = (int) ($input['product_id'] ?? $_POST['product_id'] ?? 0);
$order = $input['order'] ?? $_POST['order'] ?? [];
if ($product_id <= 0) {
	echo json_encode(['ok' => false, 'error' => 'Invalid product']);
	exit;
}
$product = product_find_by_id($pdo, $product_id);
if (!$product) {
	echo json_encode(['ok' => false, 'error' => 'Product not found']);
	exit;
}
if (!is_array($order)) {
	$order = [];
}
$order = array_map('intval', array_values($order));
product_media_update_order($pdo, $product_id, $order);
echo json_encode(['ok' => true]);
