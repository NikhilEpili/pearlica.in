<?php
/**
 * Storefront logout: clear auth cookie and redirect.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_cookie.php';

$base_path = '';
$return_url = isset($_GET['return_url']) ? trim((string) $_GET['return_url']) : 'index';
if (strpos($return_url, '://') !== false) $return_url = 'index';

auth_cookie_clear();
header('Location: ' . $base_path . $return_url, true, 302);
exit;
