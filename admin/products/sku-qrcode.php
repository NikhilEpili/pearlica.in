<?php
require_once dirname(__DIR__) . '/config.php';
require_admin_login();
require_once ROOT . '/vendor/autoload.php';
require_once ROOT . '/lib/product.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

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
		$result = Builder::create()
			->writer(new SvgWriter())
			->writerOptions([SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true])
			->data($sku)
			->size(200)
			->margin(10)
			->build();
		header('Content-Type: ' . $result->getMimeType());
		header('Content-Disposition: inline; filename="qrcode-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sku) . '.svg"');
		echo $result->getString();
	} else {
		$result = Builder::create()
			->writer(new PngWriter())
			->data($sku)
			->size(200)
			->margin(10)
			->build();
		header('Content-Type: ' . $result->getMimeType());
		header('Content-Disposition: inline; filename="qrcode-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sku) . '.png"');
		echo $result->getString();
	}
} catch (Throwable $e) {
	http_response_code(500);
	exit('QR code generation failed');
}
