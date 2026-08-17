<?php
/**
 * Storefront logout: clear auth, cart, and session checkout state; redirect.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/includes/cart_cookie.php';

$base_path = '';
$return_url = isset($_GET['return_url']) ? trim((string) $_GET['return_url']) : 'index';
if (strpos($return_url, '://') !== false) $return_url = 'index';

auth_cookie_clear();
cart_cookie_clear();

unset($_SESSION['cart_promo']);

header('Location: ' . $base_path . $return_url, true, 302);
exit;
