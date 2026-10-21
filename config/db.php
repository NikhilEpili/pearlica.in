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

$pdo = null;
$last_db_error = '';
$max_attempts = $is_local ? 1 : 3;
for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        // Force consistent connection collation to avoid "Illegal mix of collations"
        // when comparing strings across tables/columns with different utf8mb4 collations.
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
        break;
    } catch (PDOException $e) {
        $last_db_error = $e->getMessage();
        $pdo = null;
        if ($attempt < $max_attempts) {
            usleep(150000 * $attempt);
        }
    }
}

if ($pdo instanceof PDO) {
    $GLOBALS['pdo'] = $pdo;
} else {
    $GLOBALS['pdo'] = null;
    error_log('Pearlica DB connection failed after ' . $max_attempts . ' attempt(s): ' . $last_db_error);
}

if (!function_exists('pearlica_pdo')) {
    function pearlica_pdo(): ?PDO
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        return $pdo instanceof PDO ? $pdo : null;
    }
}

if (!function_exists('pearlica_require_pdo')) {
    function pearlica_require_pdo(): PDO
    {
        $pdo = pearlica_pdo();
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        require_once dirname(__DIR__) . '/includes/pearlica_http.php';
        pearlica_service_unavailable('Pearlica DB unavailable for request ' . ($_SERVER['REQUEST_URI'] ?? ''));
    }
}
