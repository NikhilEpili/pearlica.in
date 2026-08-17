<?php
/**
 * Redirect to storefront with auth modal (password login / register).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_cookie.php';

$base_path = '';
$return_url = isset($_GET['return_url']) ? trim((string) $_GET['return_url']) : 'profile';
if ($return_url !== '' && strpos($return_url, '://') !== false) {
	$return_url = 'profile';
}

if (auth_cookie_user_id() !== null) {
	header('Location: ' . $base_path . ($return_url !== '' ? $return_url : 'profile'), true, 302);
	exit;
}

$dest = $base_path . 'index?auth=login&return_url=' . rawurlencode($return_url);
header('Location: ' . $dest, true, 302);
exit;
