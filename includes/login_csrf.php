<?php
/**
 * CSRF helpers for storefront login (full page + modal API).
 */
declare(strict_types=1);

if (!function_exists('login_csrf_token')) {
	function login_csrf_token(): string
	{
		if (session_status() === PHP_SESSION_NONE) {
			if (function_exists('store_session_start')) {
				store_session_start();
			} else {
				require_once __DIR__ . '/store_session.php';
			}
		}
		if (empty($_SESSION['login_csrf_token'])) {
			$_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
		}

		return (string) $_SESSION['login_csrf_token'];
	}
}

if (!function_exists('login_validate_csrf')) {
	function login_validate_csrf(): bool
	{
		if (session_status() === PHP_SESSION_NONE) {
			return false;
		}
		$token = (string) ($_POST['csrf_token'] ?? '');
		$expected = (string) ($_SESSION['login_csrf_token'] ?? '');

		return $token !== '' && $expected !== '' && hash_equals($expected, $token);
	}
}
