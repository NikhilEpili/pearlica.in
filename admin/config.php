<?php
define('ROOT', dirname(__DIR__));
if (!defined('APP_DEBUG')) {
	define('APP_DEBUG', false);
}
require_once ROOT . '/config/config.php';
require_once ROOT . '/config/db.php';

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
if (session_status() === PHP_SESSION_NONE) {
	session_start();
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
