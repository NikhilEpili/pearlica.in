<?php
/**
 * Public URL bases for product/collection media.
 * When the local uploads tree is empty (typical git clone — images are gitignored),
 * fall back to production so storefront/admin previews still show images.
 * Never use that fallback on the live host — missing files must 404, not self-redirect.
 */
declare(strict_types=1);

if (!function_exists('pearlica_upload_dir_has_media')) {
	function pearlica_upload_dir_has_media(string $dir): bool
	{
		if (!is_dir($dir)) {
			return false;
		}
		$entries = @scandir($dir);
		if (!is_array($entries)) {
			return false;
		}
		foreach ($entries as $e) {
			if ($e === '.' || $e === '..' || $e === '.gitkeep') {
				continue;
			}
			return true;
		}
		return false;
	}
}

if (!function_exists('pearlica_is_local_dev_host')) {
	/** True for localhost / *.local / *.test so production never gets the remote media fallback. */
	function pearlica_is_local_dev_host(): bool
	{
		$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
		$host = preg_replace('/:\d+$/', '', $host) ?? $host;
		if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
			return true;
		}
		return (bool) preg_match('/\.(local|test|localhost)$/', $host);
	}
}

if (!function_exists('pearlica_uploads_products_base')) {
	/**
	 * @param string $base_path Storefront URL prefix (usually ends with "/").
	 */
	function pearlica_uploads_products_base(string $base_path = ''): string
	{
		if (defined('PRODUCT_MEDIA_PUBLIC_BASE') && PRODUCT_MEDIA_PUBLIC_BASE !== '') {
			return rtrim((string) PRODUCT_MEDIA_PUBLIC_BASE, '/');
		}
		$prefix = rtrim($base_path, '/');
		return ($prefix === '' ? '' : $prefix) . '/uploads/products';
	}
}

if (!function_exists('pearlica_uploads_collections_base')) {
	/**
	 * @param string $base_path Storefront URL prefix (usually ends with "/").
	 */
	function pearlica_uploads_collections_base(string $base_path = ''): string
	{
		if (defined('COLLECTION_MEDIA_PUBLIC_BASE') && COLLECTION_MEDIA_PUBLIC_BASE !== '') {
			return rtrim((string) COLLECTION_MEDIA_PUBLIC_BASE, '/');
		}
		$prefix = rtrim($base_path, '/');
		return ($prefix === '' ? '' : $prefix) . '/uploads/collections';
	}
}

if (!function_exists('pearlica_uploads_banners_base')) {
	/**
	 * @param string $base_path Storefront URL prefix (usually ends with "/").
	 */
	function pearlica_uploads_banners_base(string $base_path = ''): string
	{
		if (defined('BANNER_MEDIA_PUBLIC_BASE') && BANNER_MEDIA_PUBLIC_BASE !== '') {
			return rtrim((string) BANNER_MEDIA_PUBLIC_BASE, '/');
		}
		$prefix = rtrim($base_path, '/');
		return ($prefix === '' ? '' : $prefix) . '/uploads/banners';
	}
}

if (!function_exists('pearlica_uploads_reels_base')) {
	/**
	 * @param string $base_path Storefront URL prefix (usually ends with "/").
	 */
	function pearlica_uploads_reels_base(string $base_path = ''): string
	{
		if (defined('REEL_MEDIA_PUBLIC_BASE') && REEL_MEDIA_PUBLIC_BASE !== '') {
			return rtrim((string) REEL_MEDIA_PUBLIC_BASE, '/');
		}
		$prefix = rtrim($base_path, '/');
		return ($prefix === '' ? '' : $prefix) . '/uploads/reels';
	}
}

if (!function_exists('pearlica_uploads_blogs_base')) {
	/**
	 * @param string $base_path Storefront URL prefix (usually ends with "/").
	 */
	function pearlica_uploads_blogs_base(string $base_path = ''): string
	{
		if (defined('BLOG_MEDIA_PUBLIC_BASE') && BLOG_MEDIA_PUBLIC_BASE !== '') {
			return rtrim((string) BLOG_MEDIA_PUBLIC_BASE, '/');
		}
		$prefix = rtrim($base_path, '/');
		return ($prefix === '' ? '' : $prefix) . '/uploads/blogs';
	}
}
