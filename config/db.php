<?php
$db_charset = 'utf8mb4';

$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
// Strip port so localhost:8888 still counts as local (PHP built-in server).
$host_no_port = preg_replace('/:\d+$/', '', $host) ?? $host;
$is_local = in_array($host_no_port, ['localhost', '127.0.0.1'], true)
    || (str_ends_with($host_no_port, '.local') || str_ends_with($host_no_port, '.test'));

if ($is_local) {
    // Use TCP loopback — on macOS/XAMPP, "localhost" often resolves to a Unix socket path
    // that PHP cannot find (HY000/2002), which whitescreens pages that include the DB.
    $db_host = '127.0.0.1';
    $db_name = 'pearlica.in';
    $db_user = 'root';
    $db_pass = '';
} else {
    $db_host = 'localhost';
    $db_name = 'u257760413_pearlica';
    $db_user = 'u257760413_admin';
    $db_pass = 'Pe@rL1cA#2026!Dev';
}

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    // Force consistent connection collation to avoid "Illegal mix of collations"
    // when comparing strings across tables/columns with different utf8mb4 collations.
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    $pdo = null;
    $GLOBALS['pdo'] = null;
    error_log('Pearlica DB connection failed: ' . $e->getMessage());
}
