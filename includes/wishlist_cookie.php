<?php
/**
 * Wishlist stored in cookie, same pattern as cart: pearlica_wishlist cookie is JSON object (product_id => 1).
 */
declare(strict_types=1);

define('WISHLIST_COOKIE_NAME', 'pearlica_wishlist');
define('WISHLIST_COOKIE_DAYS', 365);

function wishlist_from_cookie(): array
{
	$raw = $_COOKIE[WISHLIST_COOKIE_NAME] ?? '';
	if ($raw === '') {
		return [];
	}
	$decoded = json_decode(stripcslashes($raw), true);
	if (!is_array($decoded)) {
		return [];
	}
	$out = [];
	// New format: { "12": 1, "5": 1 } (same style as cart)
	foreach ($decoded as $id => $qty) {
		if (is_int($id) || is_string($id)) {
			$id = (int) $id;
			if ($id > 0) {
				$out[$id] = 1;
			}
		}
	}
	// Backward compat: old format was [12, 5]
	if (empty($out) && array_keys($decoded) === range(0, count($decoded) - 1)) {
		foreach ($decoded as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$out[$id] = 1;
			}
		}
	}
	return $out;
}

function wishlist_to_cookie(array $items): void
{
	$items = array_map('intval', array_keys($items));
	$items = array_unique(array_filter($items, function ($id) { return $id > 0; }));
	$assoc = [];
	foreach ($items as $id) {
		$assoc[$id] = 1;
	}
	$value = json_encode($assoc);
	$expiry = time() + (WISHLIST_COOKIE_DAYS * 86400);
	setcookie(WISHLIST_COOKIE_NAME, $value, [
		'expires' => $expiry,
		'path' => '/',
		'secure' => false,
		'httponly' => false,
		'samesite' => 'Lax',
	]);
}

function wishlist_has(int $product_id): bool
{
	$wishlist = wishlist_from_cookie();
	return isset($wishlist[$product_id]);
}

function wishlist_toggle(int $product_id): bool
{
	$wishlist = wishlist_from_cookie();
	if (isset($wishlist[$product_id])) {
		unset($wishlist[$product_id]);
	} else {
		$wishlist[$product_id] = 1;
	}
	wishlist_to_cookie($wishlist);
	return isset($wishlist[$product_id]);
}
