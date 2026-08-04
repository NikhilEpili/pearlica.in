<?php
require_once dirname(__DIR__) . '/config.php';
require_admin_login();
require_once ROOT . '/vendor/autoload.php';
require_once ROOT . '/lib/product.php';

$pdo = $GLOBALS['pdo'];
$id = (int) ($_GET['id'] ?? 0);
$format = strtolower(trim($_GET['format'] ?? 'png'));
if (!in_array($format, ['png', 'svg'], true)) {
	$format = 'png';
}

if ($id <= 0) {
	http_response_code(400);
	exit('Invalid product');
}

$product = product_find_by_id($pdo, $id);
if (!$product) {
	http_response_code(404);
	exit('Product not found');
}

$sku = trim((string) ($product['sku'] ?? ''));
if ($sku === '') {
	http_response_code(400);
	exit('Product has no SKU');
}

try {
	if ($format === 'svg') {
		$generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
		$data = $generator->getBarcode($sku, $generator::TYPE_CODE_128);
		header('Content-Type: image/svg+xml');
		header('Content-Disposition: inline; filename="barcode-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sku) . '.svg"');
		echo $data;
	} else {
		$generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
		$data = $generator->getBarcode($sku, $generator::TYPE_CODE_128, 2, 48);
		header('Content-Type: image/png');
		header('Content-Disposition: inline; filename="barcode-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sku) . '.png"');
		echo $data;
	}
} catch (Throwable $e) {
	http_response_code(500);
	exit('Barcode generation failed');
}
