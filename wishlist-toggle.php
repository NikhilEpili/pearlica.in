<?php
/**
 * Toggle product in wishlist cookie. GET: product_id, return_url (optional).
 * Redirects to return_url or Referer. If Accept: application/json, returns JSON.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/wishlist_cookie.php';

$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
$return_url = isset($_GET['return_url']) ? trim((string) $_GET['return_url']) : '';
$want_json = !empty($_SERVER['HTTP_ACCEPT']) && (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($product_id <= 0) {
	if ($want_json) {
		header('Content-Type: application/json');
		echo json_encode(['ok' => false, 'in_wishlist' => false]);
		exit;
	}
	$return_url = $return_url !== '' ? $return_url : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index');
	header('Location: ' . $return_url, true, 302);
	exit;
}

$in_wishlist = wishlist_toggle($product_id);

if ($want_json) {
	header('Content-Type: application/json');
	echo json_encode(['ok' => true, 'in_wishlist' => $in_wishlist]);
	exit;
}

if ($return_url !== '') {
	header('Location: ' . $return_url, true, 302);
	exit;
}
if (!empty($_SERVER['HTTP_REFERER'])) {
	header('Location: ' . $_SERVER['HTTP_REFERER'], true, 302);
	exit;
}
header('Location: index', true, 302);
exit;
