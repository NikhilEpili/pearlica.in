<?php
/**
 * Cart stored in cookie. Read/write helpers for pearlica_cart cookie (JSON: product_id => quantity).
 */
declare(strict_types=1);

define('CART_COOKIE_NAME', 'pearlica_cart');
define('CART_COOKIE_DAYS', 30);

function cart_from_cookie(): array
{
	$raw = $_COOKIE[CART_COOKIE_NAME] ?? '';
	if ($raw === '') {
		return [];
	}
	$decoded = json_decode(stripcslashes($raw), true);
	if (!is_array($decoded)) {
		return [];
	}
	$out = [];
	foreach ($decoded as $id => $qty) {
		$id = (int) $id;
		$qty = (int) $qty;
		if ($id > 0 && $qty > 0) {
			$out[$id] = $qty;
		}
	}
	return $out;
}

function cart_to_cookie(array $cart): void
{
	$value = json_encode($cart);
	$expiry = time() + (CART_COOKIE_DAYS * 86400);
	setcookie(CART_COOKIE_NAME, $value, [
		'expires' => $expiry,
		'path' => '/',
		'secure' => false,
		'httponly' => false,
		'samesite' => 'Lax',
	]);
}

function cart_count_from_cookie(): int
{
	$cart = cart_from_cookie();
	return array_sum($cart);
}
