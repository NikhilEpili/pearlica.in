<?php
/**
 * POST: product_id, slug, rating, body, csrf_token. Signed-in verified buyers only; name from profile.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/product_review.php';
require_once __DIR__ . '/lib/product.php';
require_once __DIR__ . '/lib/store_user.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/includes/store_base_path.php';

$base_path = store_base_path_from_request();

function product_review_redirect(string $base_path, string $slug, string $query_key): void
{
	header('Location: ' . $base_path . 'product?slug=' . rawurlencode($slug) . '&' . $query_key, true, 302);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . $base_path . 'products', true, 302);
	exit;
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
$expected = (string) ($_SESSION['product_review_csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $csrf)) {
	header('Location: ' . $base_path . 'products', true, 302);
	exit;
}

$product_id = (int) ($_POST['product_id'] ?? 0);
$slug = trim((string) ($_POST['slug'] ?? ''));
$rating = (int) ($_POST['rating'] ?? 0);
$body = trim((string) ($_POST['body'] ?? ''));

$pdo = $GLOBALS['pdo'];
$product = $product_id > 0 ? product_find_by_id($pdo, $product_id) : null;
if (!$product || (string) ($product['status'] ?? '') !== 'active' || $slug === '' || ($product['slug'] ?? '') !== $slug) {
	header('Location: ' . $base_path . 'products', true, 302);
	exit;
}

$user_id = auth_cookie_user_id();
if ($user_id === null) {
	product_review_redirect($base_path, $slug, 'review=auth');
}

if (!product_review_user_has_purchased($pdo, $user_id, $product_id)) {
	product_review_redirect($base_path, $slug, 'review=forbidden');
}

if (product_review_exists_for_user($pdo, $user_id, $product_id)) {
	product_review_redirect($base_path, $slug, 'review=duplicate');
}

$user = store_user_find_by_id($pdo, $user_id);
$author = '';
if ($user) {
	$author = trim((string) ($user['full_name'] ?? ''));
	if ($author === '') {
		$author = trim((string) ($user['email'] ?? ''));
	}
}
if ($author === '') {
	$author = 'Customer';
}

if ($rating < 1 || $rating > 5) {
	product_review_redirect($base_path, $slug, 'review=error');
}

$ok = product_review_insert_verified_buyer($pdo, $product_id, $user_id, $rating, $author, $body);
product_review_redirect($base_path, $slug, $ok ? 'review=thanks' : 'review=error');
