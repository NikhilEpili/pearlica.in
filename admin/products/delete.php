<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('products');
require_once ROOT . '/lib/product.php';

$pdo = $GLOBALS['pdo'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(admin_url('products/'));
}
if (!validate_csrf()) {
    flash_set('error', 'Invalid request.');
    redirect(admin_url('products/'));
}
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect(admin_url('products/'));
}
$product = product_find_by_id($pdo, $id);
if (!$product) {
    flash_set('error', 'Product not found.');
    redirect(admin_url('products/'));
}
product_soft_delete($pdo, $id);
flash_set('success', 'Product deleted.');
redirect(admin_url('products/'));
