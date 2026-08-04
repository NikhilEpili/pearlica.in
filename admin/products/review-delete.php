<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('products');
require_once ROOT . '/lib/product.php';
require_once ROOT . '/lib/product_review.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('products/'));
}

if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('products/'));
}

$pdo = $GLOBALS['pdo'];
$list_state = admin_products_list_state_from_request();
$product_id = (int) ($_POST['product_id'] ?? 0);
$review_id = (int) ($_POST['review_id'] ?? 0);

if ($product_id <= 0 || $review_id <= 0 || !product_find_by_id($pdo, $product_id)) {
	flash_set('error', 'Invalid request.');
	redirect(admin_products_index_url_with_state($list_state));
}

$ok = product_review_admin_delete($pdo, $review_id, $product_id);
flash_set($ok ? 'success' : 'error', $ok ? 'Review deleted.' : 'Could not delete review.');
redirect(admin_products_edit_url($product_id, $list_state));
