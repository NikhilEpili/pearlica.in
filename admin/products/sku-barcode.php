<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('products');
require_once ROOT . '/vendor/autoload.php';
require_once ROOT . '/lib/product.php';

if (function_exists('store_session_release_lock')) {
	store_session_release_lock();
}
while (ob_get_level() > 0) {
	ob_end_clean();
}

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

$safe_name = 'barcode-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sku);
$as_attachment = isset($_GET['download']) && (string) $_GET['download'] === '1';
$disposition = $as_attachment ? 'attachment' : 'inline';

try {
	if ($format === 'svg') {
		$generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
		$data = $generator->getBarcode($sku, $generator::TYPE_CODE_128);
		header('Content-Type: image/svg+xml; charset=UTF-8');
		header('Content-Disposition: ' . $disposition . '; filename="' . $safe_name . '.svg"');
		header('Cache-Control: private, max-age=3600');
		echo $data;
	} else {
		$generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
		$data = $generator->getBarcode($sku, $generator::TYPE_CODE_128, 2, 48);
		header('Content-Type: image/png');
		header('Content-Disposition: ' . $disposition . '; filename="' . $safe_name . '.png"');
		header('Content-Length: ' . (string) strlen($data));
		header('Cache-Control: private, max-age=3600');
		echo $data;
	}
} catch (Throwable $e) {
	http_response_code(500);
	exit('Barcode generation failed');
}
