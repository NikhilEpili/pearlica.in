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

$safe_name = 'qrcode-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sku);
$as_attachment = isset($_GET['download']) && (string) $_GET['download'] === '1';
$disposition = $as_attachment ? 'attachment' : 'inline';

try {
	if ($format === 'svg') {
		$result = Builder::create()
			->writer(new SvgWriter())
			->writerOptions([SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true])
			->data($sku)
			->size(200)
			->margin(10)
			->build();
		$body = $result->getString();
		header('Content-Type: ' . $result->getMimeType());
		header('Content-Disposition: ' . $disposition . '; filename="' . $safe_name . '.svg"');
		header('Cache-Control: private, max-age=3600');
		echo $body;
	} else {
		$result = Builder::create()
			->writer(new PngWriter())
			->data($sku)
			->size(200)
			->margin(10)
			->build();
		$body = $result->getString();
		header('Content-Type: ' . $result->getMimeType());
		header('Content-Disposition: ' . $disposition . '; filename="' . $safe_name . '.png"');
		header('Content-Length: ' . (string) strlen($body));
		header('Cache-Control: private, max-age=3600');
		echo $body;
	}
} catch (Throwable $e) {
	http_response_code(500);
	exit('QR code generation failed');
}
