<?php
/**
 * Shared shop helpers: product card image dimensions, price formatting, card context.
 */

if (!function_exists('pearlica_get_image_dimensions')) {
	/**
	 * @return array{0: int, 1: int}
	 */
	function pearlica_get_image_dimensions(string $relative_or_url, string $fallback_src): array
	{
		static $cache = [];

		$uploads_disk_dir = __DIR__ . '/../uploads/products';
		$cache_key = $relative_or_url !== '' ? ($uploads_disk_dir . '/' . ltrim($relative_or_url, '/')) : $fallback_src;
		if (isset($cache[$cache_key])) {
			return $cache[$cache_key];
		}

		$w = 0;
		$h = 0;

		if ($relative_or_url !== '') {
			$abs_path = $uploads_disk_dir . '/' . ltrim($relative_or_url, '/');
			if (is_file($abs_path)) {
				$info = @getimagesize($abs_path);
				if (is_array($info) && isset($info[0], $info[1])) {
					$w = (int) $info[0];
					$h = (int) $info[1];
				}
			}
		}

		if ($w <= 0 || $h <= 0) {
			if (preg_match('/placehold\.co\/(\d+)x(\d+)/i', $fallback_src, $m)) {
				$w = (int) $m[1];
				$h = (int) $m[2];
			}
		}

		if ($w <= 0 || $h <= 0) {
			$w = 4;
			$h = 5;
		}

		$cache[$cache_key] = [$w, $h];
		return $cache[$cache_key];
	}
}

if (!function_exists('format_price_parts')) {
	/**
	 * @return array{current: string, original: string|null}
	 */
	function format_price_parts($mrp, $discount_percentage = 0): array
	{
		if ($mrp === null || $mrp === '') {
			return ['current' => '', 'original' => null];
		}
		$mrp = (float) $mrp;
		$discount = (float) ($discount_percentage ?? 0);
		if ($discount > 0) {
			$sale = $mrp * (1 - $discount / 100);
			return ['current' => '₹' . number_format($sale, 0), 'original' => '₹' . number_format($mrp, 0)];
		}
		return ['current' => '₹' . number_format($mrp, 0), 'original' => null];
	}
}

/**
 * Variables for components/products_card.php (e.g. extract(..., EXTR_OVERWRITE)).
 *
 * @param array<string, mixed> $p
 * @param array<int, int> $wishlist_ids
 * @return array<string, mixed>
 */
function pearlica_product_card_vars(array $p, string $uploads_base, string $base_path, array $wishlist_ids, string $wishlist_return): array
{
	$img_src = !empty($p['image_url']) ? $uploads_base . '/' . $p['image_url'] : 'https://placehold.co/400x400?text=No+Image';
	[$img_w, $img_h] = pearlica_get_image_dimensions((string) ($p['image_url'] ?? ''), (string) $img_src);
	$img2_rel = trim((string) ($p['image_url_2'] ?? ''));
	$primary_rel = trim((string) ($p['image_url'] ?? ''));
	$img2_src = ($img2_rel !== '' && $img2_rel !== $primary_rel) ? $uploads_base . '/' . $img2_rel : '';
	$product_url = $base_path . 'product?slug=' . rawurlencode((string) ($p['slug'] ?? ''));
	$product_name = (string) ($p['name'] ?? '');
	$price_parts = format_price_parts($p['mrp'] ?? null, $p['discount_percentage'] ?? 0);
	$in_wishlist = in_array((int) ($p['id'] ?? 0), $wishlist_ids, true);

	return [
		'p' => $p,
		'img_src' => $img_src,
		'img2_src' => $img2_src,
		'img_w' => $img_w,
		'img_h' => $img_h,
		'product_url' => $product_url,
		'product_name' => $product_name,
		'price_parts' => $price_parts,
		'in_wishlist' => $in_wishlist,
		'base_path' => $base_path,
		'wishlist_return' => $wishlist_return,
	];
}
