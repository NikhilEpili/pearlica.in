<?php
/**
 * Storefront PHP session — must run before any HTML output so auth CSRF tokens persist.
 */
declare(strict_types=1);

if (!function_exists('store_session_start')) {
	function store_session_start(): void
	{
		if (session_status() !== PHP_SESSION_NONE) {
			return;
		}

		ini_set('session.cookie_httponly', '1');
		ini_set('session.use_strict_mode', '1');

		$cookie_path = '/';
		if (!function_exists('store_base_path_from_request')) {
			require_once __DIR__ . '/store_base_path.php';
		}
		$base_path = store_base_path_from_request();
		if ($base_path !== '/' && $base_path !== '') {
			$cookie_path = rtrim($base_path, '/') ?: '/';
		}

		session_set_cookie_params([
			'lifetime' => 0,
			'path' => $cookie_path,
			'httponly' => true,
			'samesite' => 'Lax',
		]);
		session_start();
	}
}

store_session_start();
