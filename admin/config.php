<?php
define('ROOT', dirname(__DIR__));
if (!defined('APP_DEBUG')) {
	define('APP_DEBUG', false);
}
require_once ROOT . '/config/config.php';
require_once ROOT . '/config/db.php';

$pdo = $GLOBALS['pdo'] ?? null;
if ($pdo instanceof PDO) {
	require_once ROOT . '/lib/instagram_reel.php';
	try {
		instagram_reel_ensure_schema($pdo);
	} catch (Throwable $e) {
		error_log('Pearlica instagram_reels schema: ' . $e->getMessage());
	}
}

function h($s)
{
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect($url, $code = 302)
{
	header('Location: ' . $url, true, $code);
	exit;
}

function flash_set($key, $value)
{
	$_SESSION['_flash'][$key] = $value;
}

function flash_get($key)
{
	$v = $_SESSION['_flash'][$key] ?? null;
	unset($_SESSION['_flash'][$key]);
	return $v;
}

function admin_url($path = '')
{
	$path = ltrim($path, '/');
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
	return $base . '/admin' . ($path !== '' ? '/' . $path : '');
}

/** Base path to site root for asset URLs (no trailing slash; empty if at document root). */
function web_base()
{
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	return preg_match('#^(.*?)/admin#', $req_uri, $m) ? rtrim($m[1], '/') : '';
}

function require_admin_login()
{
	if (empty($_SESSION['admin_id'])) {
		redirect(admin_url('login.php'));
	}
}

function require_super_admin()
{
	require_admin_login();
	if (empty($_SESSION['admin_is_super_admin'])) {
		flash_set('error', 'Access denied. Super admin only.');
		redirect(admin_url('dashboard/'));
	}
}

function current_user_is_super_admin()
{
	return !empty($_SESSION['admin_is_super_admin']);
}

function current_user_permissions()
{
	return $_SESSION['admin_permissions'] ?? [];
}

function current_user_can($area)
{
	if (current_user_is_super_admin()) {
		return true;
	}
	$perms = current_user_permissions();
	return !empty($perms[$area]);
}

function require_permission($area)
{
	require_admin_login();
	if (!current_user_can($area)) {
		flash_set('error', 'Access denied.');
		redirect(admin_url('access-denied.php'));
	}
}

function csrf_token()
{
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

function csrf_field()
{
	return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function validate_csrf()
{
	$token = $_POST['csrf_token'] ?? '';
	return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/** Bytes for a php.ini shorthand size such as "8M" or "2G". */
function ini_size_to_bytes($value)
{
	$value = trim((string) $value);
	if ($value === '') {
		return 0;
	}
	$number = (int) $value;
	switch (strtolower(substr($value, -1))) {
		case 'g':
			return $number * 1024 * 1024 * 1024;
		case 'm':
			return $number * 1024 * 1024;
		case 'k':
			return $number * 1024;
		default:
			return $number;
	}
}

/**
 * True when the request body was larger than post_max_size. PHP then discards
 * $_POST and $_FILES entirely, which otherwise surfaces as a bogus CSRF failure.
 */
function post_exceeded_max_size()
{
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		return false;
	}
	if (!empty($_POST) || !empty($_FILES)) {
		return false;
	}
	$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
	$limit = ini_size_to_bytes(ini_get('post_max_size'));
	return $length > 0 && $limit > 0 && $length > $limit;
}

/** Human-readable explanation for an oversized upload. */
function post_max_size_error_message()
{
	return 'The upload was larger than this server allows ('
		. ini_get('post_max_size') . ' total, '
		. ini_get('upload_max_filesize') . ' per file). '
		. 'Use a smaller video, or raise upload_max_filesize and post_max_size in PHP settings.';
}

function slug_from_string($s)
{
	$s = preg_replace('/[^a-z0-9]+/i', '-', trim($s));
	return strtolower(trim($s, '-'));
}

function pagination_total_pages($total_items, $per_page)
{
	return (int) ceil(max(0, $total_items) / $per_page);
}

define('ADMIN_ITEMS_PER_PAGE', 20);

/**
 * Pagination / filters when returning to the products list from edit (GET or POST list_* hiddens).
 */
function admin_products_list_state_from_request(): array
{
	$page = (int) ($_POST['list_page'] ?? $_GET['page'] ?? 1);
	if ($page < 1) {
		$page = 1;
	}
	return [
		'page' => $page,
		'search' => trim((string) ($_POST['list_search'] ?? $_GET['search'] ?? '')),
		'category_id' => trim((string) ($_POST['list_category_id'] ?? $_GET['category_id'] ?? '')),
		'status' => trim((string) ($_POST['list_status'] ?? $_GET['status'] ?? '')),
	];
}

function admin_products_index_url_with_state(array $state): string
{
	$q = [];
	if ($state['page'] > 1) {
		$q['page'] = $state['page'];
	}
	if ($state['search'] !== '') {
		$q['search'] = $state['search'];
	}
	if ($state['category_id'] !== '') {
		$q['category_id'] = $state['category_id'];
	}
	if ($state['status'] !== '') {
		$q['status'] = $state['status'];
	}
	$suffix = $q === [] ? '' : ('?' . http_build_query($q));
	return admin_url('products/') . $suffix;
}

function admin_products_edit_url(int $productId, array $state): string
{
	$q = ['id' => $productId];
	if ($state['page'] > 1) {
		$q['page'] = $state['page'];
	}
	if ($state['search'] !== '') {
		$q['search'] = $state['search'];
	}
	if ($state['category_id'] !== '') {
		$q['category_id'] = $state['category_id'];
	}
	if ($state['status'] !== '') {
		$q['status'] = $state['status'];
	}
	return admin_url('products/edit.php?' . http_build_query($q));
}

function uploads_base_url() {
	if (function_exists('pearlica_uploads_products_base')) {
		$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
		$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
		$site_base = $base === '' ? '/' : rtrim($base, '/') . '/';
		return pearlica_uploads_products_base($site_base);
	}
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
	return rtrim($base, '/') . '/uploads/products';
}

/** Public URL prefix for collection cover images (admin previews + storefront). */
function uploads_collections_base_url() {
	if (function_exists('pearlica_uploads_collections_base')) {
		$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
		$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
		$site_base = $base === '' ? '/' : rtrim($base, '/') . '/';
		return pearlica_uploads_collections_base($site_base);
	}
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
	return rtrim($base, '/') . '/uploads/collections';
}

/** Public URL prefix for homepage banner images. */
function uploads_banners_base_url() {
	if (function_exists('pearlica_uploads_banners_base')) {
		$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
		$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
		$site_base = $base === '' ? '/' : rtrim($base, '/') . '/';
		return pearlica_uploads_banners_base($site_base);
	}
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
	return rtrim($base, '/') . '/uploads/banners';
}

/** Public URL prefix for influencer reel videos and posters. */
function uploads_reels_base_url() {
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
	if (function_exists('pearlica_uploads_reels_base')) {
		$site_base = $base === '' ? '/' : rtrim($base, '/') . '/';
		return pearlica_uploads_reels_base($site_base);
	}
	return rtrim($base, '/') . '/uploads/reels';
}

/** Public URL prefix for blog cover images. */
function uploads_blogs_base_url() {
	$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
	$base = preg_match('#^(.*?)/admin#', $req_uri, $m) ? $m[1] : '';
	if (function_exists('pearlica_uploads_blogs_base')) {
		$site_base = $base === '' ? '/' : rtrim($base, '/') . '/';
		return pearlica_uploads_blogs_base($site_base);
	}
	return rtrim($base, '/') . '/uploads/blogs';
}
