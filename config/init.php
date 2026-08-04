<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
if (!empty($_SESSION['created_at']) && (time() - (int) $_SESSION['created_at'] > SESSION_LIFETIME)) {
	session_unset();
	session_destroy();
	session_start();
}
if (empty($_SESSION['created_at'])) {
	$_SESSION['created_at'] = time();
}
