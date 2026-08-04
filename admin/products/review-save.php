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
if ($product_id <= 0 || !product_find_by_id($pdo, $product_id)) {
	flash_set('error', 'Invalid product.');
	redirect(admin_products_index_url_with_state($list_state));
}

$review_id = (int) ($_POST['review_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$author_name = (string) ($_POST['author_name'] ?? '');
$body = (string) ($_POST['body'] ?? '');
$is_approved = isset($_POST['is_approved']) && (string) $_POST['is_approved'] === '1';

if ($rating < 1 || $rating > 5) {
	flash_set('error', 'Rating must be between 1 and 5.');
	redirect(admin_products_edit_url($product_id, $list_state));
}

if ($review_id > 0) {
	$ok = product_review_admin_update($pdo, $review_id, $product_id, $rating, $author_name, $body, $is_approved);
	flash_set($ok ? 'success' : 'error', $ok ? 'Review updated.' : 'Could not update review.');
} else {
	$ok = product_review_admin_insert($pdo, $product_id, $rating, $author_name, $body, $is_approved);
	flash_set($ok ? 'success' : 'error', $ok ? 'Review added.' : 'Could not add review.');
}

redirect(admin_products_edit_url($product_id, $list_state));
