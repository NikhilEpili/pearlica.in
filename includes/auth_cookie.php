<?php
/**
 * Storefront auth: remember user for AUTH_COOKIE_DAYS via signed cookie (user_id + timestamp + HMAC).
 */
declare(strict_types=1);

if (!defined('AUTH_COOKIE_NAME')) {
	require_once dirname(__DIR__) . '/config/config.php';
}

function auth_cookie_set(int $user_id): void
{
	$payload = (string) $user_id . '.' . time();
	$signature = hash_hmac('sha256', $payload, AUTH_SECRET_KEY);
	$value = base64_encode($payload . '.' . $signature);
	$expiry = time() + (AUTH_COOKIE_DAYS * 86400);
	setcookie(AUTH_COOKIE_NAME, $value, [
		'expires' => $expiry,
		'path' => '/',
		'secure' => false,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
}

function auth_cookie_clear(): void
{
	setcookie(AUTH_COOKIE_NAME, '', [
		'expires' => time() - 3600,
		'path' => '/',
		'secure' => false,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
}

function auth_cookie_user_id(): ?int
{
	$raw = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
	if ($raw === '') {
		return null;
	}
	$decoded = base64_decode($raw, true);
	if ($decoded === false) {
		return null;
	}
	$parts = explode('.', $decoded);
	if (count($parts) < 3) {
		return null;
	}
	$signature = array_pop($parts);
	$payload = implode('.', $parts);
	if (!hash_equals(hash_hmac('sha256', $payload, AUTH_SECRET_KEY), $signature)) {
		return null;
	}
	$id_part = $parts[0] ?? '';
	$issued_at = isset($parts[1]) ? (int) $parts[1] : 0;
	if (!ctype_digit($id_part) || $issued_at <= 0) {
		return null;
	}
	$max_age = AUTH_COOKIE_DAYS * 86400;
	if (time() - $issued_at > $max_age) {
		auth_cookie_clear();

		return null;
	}

	return (int) $id_part;
}
