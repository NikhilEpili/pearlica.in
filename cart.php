<?php
/**
 * Cart cookie handler: POST add/update, GET drawer fragment (AJAX), GET remove, or redirect.
 * There is no full cart page — use the cart drawer and checkout.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/product.php';
require_once __DIR__ . '/includes/cart_cookie.php';
require_once __DIR__ . '/includes/store_base_path.php';

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$base_path = store_base_path_from_request();
$cart = cart_from_cookie();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
	$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

	if ($action === 'add') {
		$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
		$quantity = isset($_POST['quantity']) ? max(1, (int) $_POST['quantity']) : 1;
		$return_url = isset($_POST['return_url']) ? trim((string) $_POST['return_url']) : '';
		if ($product_id > 0) {
			$pdo = $GLOBALS['pdo'];
			$product = product_find_by_id($pdo, $product_id);
			if ($product && (string) ($product['status'] ?? '') === 'active') {
				$stock = (int) ($product['stock_quantity'] ?? 0);
				if ($stock > 0) {
					$current = (int) ($cart[$product_id] ?? 0);
					$cart[$product_id] = min($stock, $current + $quantity);
					cart_to_cookie($cart);
				}
			}
		}
		if ($return_url !== '' && strpos($return_url, '://') === false) {
			$redirect = $base_path . ltrim($return_url, '/');
			if ($redirect !== '' && $redirect[0] !== '/' && strpos($redirect, '://') === false) {
				$redirect = '/' . $redirect;
			}
		} else {
			$redirect = $base_path . 'product?slug=';
			if ($product_id > 0 && isset($product) && $product) {
				$redirect .= rawurlencode((string) ($product['slug'] ?? ''));
			} else {
				$redirect = $base_path . 'products';
			}
		}
		header('Location: ' . $redirect, true, 302);
		exit;
	}

	if ($action === 'update') {
		$posted = isset($_POST['cart']) && is_array($_POST['cart']) ? $_POST['cart'] : [];
		$new_cart = [];
		foreach ($posted as $id => $qty) {
			$id = (int) $id;
			$qty = (int) $qty;
			if ($id > 0 && $qty > 0) {
				$new_cart[$id] = $qty;
			}
		}
		$pdo = $GLOBALS['pdo'];
		$details = $new_cart !== [] ? product_get_cart_details($pdo, array_keys($new_cart)) : [];
		$sanitized = [];
		foreach ($new_cart as $pid => $qty) {
			$d = $details[$pid] ?? null;
			if (!$d) {
				continue;
			}
			$stock = (int) ($d['stock_quantity'] ?? 0);
			if ($stock <= 0) {
				continue;
			}
			$sanitized[$pid] = min($stock, $qty);
		}
		$cart = $sanitized;
		cart_to_cookie($cart);
		// $_COOKIE is not updated until the next request — do not re-read here.
		if ($is_ajax) {
			header('Content-Type: application/json; charset=utf-8');
			$details = product_get_cart_details($pdo, array_keys($cart));
			$total_amount = 0;
			$line_totals = [];
			foreach ($cart as $pid => $qty) {
				$d = $details[$pid] ?? null;
				if (!$d) {
					continue;
				}
				$mrp = (float) ($d['mrp'] ?? 0);
				$disc = (float) ($d['discount_percentage'] ?? 0);
				$unit = $disc > 0 ? $mrp * (1 - $disc / 100) : $mrp;
				$line = $unit * $qty;
				$total_amount += $line;
				$line_totals[$pid] = round($line, 0);
			}
			echo json_encode([
				'total_items' => array_sum($cart),
				'total_amount' => round($total_amount, 0),
				'line_totals' => $line_totals,
			]);
			exit;
		}
		header('Location: ' . (empty($cart) ? $base_path . 'index' : $base_path . 'checkout'), true, 302);
		exit;
	}
}

if (isset($_GET['remove']) && $_GET['remove'] !== '') {
	$remove_id = (int) $_GET['remove'];
	if ($remove_id > 0) {
		unset($cart[$remove_id]);
		cart_to_cookie($cart);
	}
	// $_COOKIE still holds the previous cart this request — keep $cart as updated above.
	$is_ajax_remove = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
	if (!$is_ajax_remove) {
		header('Location: ' . (empty($cart) ? $base_path . 'index' : $base_path . 'checkout'), true, 302);
		exit;
	}
}

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $is_ajax) {
	require_once __DIR__ . '/includes/cart_for_drawer.php';
	header('Content-Type: text/html; charset=utf-8');
	include __DIR__ . '/components/cart_drawer_content.php';
	exit;
}

header('Location: ' . (empty($cart) ? $base_path . 'index' : $base_path . 'checkout'), true, 302);
exit;
