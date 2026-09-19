<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('products');
require_once ROOT . '/lib/product.php';

$pdo = $GLOBALS['pdo'];
$search = trim($_GET['search'] ?? '');
$category_id = trim($_GET['category_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$format = strtolower(trim($_GET['format'] ?? 'csv'));
$split_by_category = !empty($_GET['split']);

if (!in_array($format, ['csv', 'excel'], true)) {
	$format = 'csv';
}

$rows = product_list_for_export($pdo, $search, $category_id, $status);
$site_public_url = defined('SITE_PUBLIC_URL') ? (string) SITE_PUBLIC_URL : '';
if ($site_public_url === '') {
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$site_public_url = $scheme . '://' . $host;
}

$headers = [
	'Product Name',
	'Category',
	'Product Description',
	'Specification',
	'Additional Information',
	'Styling Tips',
	'Material',
	'Purity',
	'MRP',
	'Website Selling Price',
	'SKU',
	'Weight',
	'Stock',
	'Image Links',
];

$row_to_cells = static function (array $row) use ($site_public_url): array {
	return [
		(string) ($row['name'] ?? ''),
		(string) ($row['category_name'] ?? ''),
		product_export_plain_text($row['description'] ?? ''),
		product_export_plain_text($row['specification'] ?? ''),
		product_export_plain_text($row['additional_information'] ?? ''),
		product_export_plain_text($row['styling_tips'] ?? ''),
		(string) ($row['metal_type'] ?? ''),
		(string) ($row['purity'] ?? ''),
		($row['mrp'] ?? '') !== '' && $row['mrp'] !== null ? number_format((float) $row['mrp'], 2, '.', '') : '',
		product_export_selling_price($row['mrp'] ?? null, $row['discount_percentage'] ?? 0),
		(string) ($row['sku'] ?? ''),
		($row['weight_grams'] ?? '') !== '' && $row['weight_grams'] !== null ? (string) $row['weight_grams'] : '',
		(string) ($row['stock_quantity'] ?? '0'),
		product_export_image_urls((string) ($row['image_paths'] ?? ''), $site_public_url),
	];
};

$build_csv = static function (array $data_rows) use ($headers, $row_to_cells): string {
	$buffer = fopen('php://temp', 'r+');
	if ($buffer === false) {
		return '';
	}
	fputcsv($buffer, $headers);
	foreach ($data_rows as $row) {
		fputcsv($buffer, $row_to_cells($row));
	}
	rewind($buffer);
	$content = stream_get_contents($buffer);
	fclose($buffer);

	return "\xEF\xBB\xBF" . ($content === false ? '' : $content);
};

$date_suffix = date('Y-m-d');

if ($split_by_category) {
	if (!class_exists('ZipArchive')) {
		http_response_code(500);
		exit('ZIP export is not available on this server.');
	}

	$by_category = [];
	foreach ($rows as $row) {
		$cat = trim((string) ($row['category_name'] ?? ''));
		if ($cat === '') {
			$cat = 'Uncategorized';
		}
		$by_category[$cat][] = $row;
	}
	ksort($by_category, SORT_NATURAL | SORT_FLAG_CASE);

	$tmp = tempnam(sys_get_temp_dir(), 'pearlica-products-');
	if ($tmp === false) {
		http_response_code(500);
		exit('Could not create export file.');
	}

	$zip = new ZipArchive();
	if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
		@unlink($tmp);
		http_response_code(500);
		exit('Could not create ZIP archive.');
	}

	foreach ($by_category as $cat_name => $cat_rows) {
		$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $cat_name) ?? 'category');
		$slug = trim($slug, '-') ?: 'category';
		$zip->addFromString($slug . '.csv', $build_csv($cat_rows));
	}
	$zip->close();

	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename="products-by-category-' . $date_suffix . '.zip"');
	header('Content-Length: ' . (string) filesize($tmp));
	readfile($tmp);
	@unlink($tmp);
	exit;
}

$csv = $build_csv($rows);

if ($format === 'excel') {
	header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
	header('Content-Disposition: attachment; filename="products-' . $date_suffix . '.xls"');
} else {
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="products-' . $date_suffix . '.csv"');
}

echo $csv;
exit;
