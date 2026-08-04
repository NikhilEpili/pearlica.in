<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('products');
require_once ROOT . '/lib/product.php';

$pdo = $GLOBALS['pdo'];
$sku = trim($_GET['sku'] ?? '');

if ($sku === '') {
	flash_set('error', 'No SKU provided.');
	redirect(admin_url('products/'));
}

$product_id = product_find_id_by_sku($pdo, $sku);
if ($product_id === null) {
	flash_set('error', 'No product found for SKU: ' . h($sku));
	redirect(admin_url('products/'));
}

redirect(admin_url('products/edit.php?id=' . $product_id));
