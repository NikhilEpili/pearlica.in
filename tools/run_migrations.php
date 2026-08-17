<?php
declare(strict_types=1);

/**
 * Apply every incremental migration this project needs, safely and repeatedly.
 *
 * On production (Hostinger SSH / hPanel terminal):
 *   php tools/run_migrations.php
 *
 * Runs the lib/*_ensure_schema() helpers first (they are written to be
 * idempotent), then each sql/*.sql file statement by statement. Errors that
 * simply mean "this was already applied" are reported as SKIP, not failures.
 *
 * sql/schema.sql is deliberately never executed here: it DROPs every table and
 * is only for building an empty database from scratch.
 */

require_once __DIR__ . '/../config/db.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
	fwrite(STDERR, "No database connection. Check config/db.php credentials for this environment.\n");
	exit(1);
}

/** Errors that mean the change is already in place. */
function migration_error_is_benign(Throwable $e): bool
{
	$message = $e->getMessage();
	$benign = [
		'Duplicate column name',
		'Duplicate key name',
		'already exists',
		'Duplicate entry',
		"Can't DROP",
		'check that column/key exists',
	];
	foreach ($benign as $needle) {
		if (stripos($message, $needle) !== false) {
			return true;
		}
	}
	return false;
}

/** Split a .sql file into statements, ignoring comments and blank lines. */
function migration_split_statements(string $sql): array
{
	$lines = preg_split('/\R/', $sql) ?: [];
	$clean = [];
	foreach ($lines as $line) {
		$trimmed = ltrim($line);
		if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
			continue;
		}
		$clean[] = $line;
	}

	$statements = [];
	foreach (explode(';', implode("\n", $clean)) as $chunk) {
		$chunk = trim($chunk);
		if ($chunk !== '') {
			$statements[] = $chunk;
		}
	}
	return $statements;
}

$applied = 0;
$skipped = 0;
$failed = 0;

echo "== Schema helpers ==" . PHP_EOL;

$helpers = [
	'store_users' => [__DIR__ . '/../lib/store_user.php', 'store_user_ensure_schema'],
	'store_user_addresses' => [__DIR__ . '/../lib/store_user_address.php', 'store_user_address_ensure_schema'],
	'homepage_banners' => [__DIR__ . '/../lib/homepage_banner.php', 'homepage_banner_ensure_schema'],
	'faqs' => [__DIR__ . '/../lib/faq.php', 'faq_ensure_schema'],
	'site_policies' => [__DIR__ . '/../lib/site_policy.php', 'site_policy_ensure_schema'],
	'instagram_reels' => [__DIR__ . '/../lib/instagram_reel.php', 'instagram_reel_ensure_schema'],
];

foreach ($helpers as $label => [$file, $function]) {
	if (!is_file($file)) {
		echo 'SKIP ' . $label . ': ' . basename($file) . ' not found' . PHP_EOL;
		$skipped++;
		continue;
	}
	require_once $file;
	if (!function_exists($function)) {
		echo 'SKIP ' . $label . ': ' . $function . '() not defined' . PHP_EOL;
		$skipped++;
		continue;
	}
	try {
		$function($pdo);
		echo 'OK   ' . $label . PHP_EOL;
		$applied++;
	} catch (Throwable $e) {
		if (migration_error_is_benign($e)) {
			echo 'SKIP ' . $label . ': ' . $e->getMessage() . PHP_EOL;
			$skipped++;
		} else {
			echo 'FAIL ' . $label . ': ' . $e->getMessage() . PHP_EOL;
			$failed++;
		}
	}
}

echo PHP_EOL . '== SQL files ==' . PHP_EOL;

$sql_dir = __DIR__ . '/../sql';
$files = glob($sql_dir . '/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
	$name = basename($file);
	if ($name === 'schema.sql') {
		echo 'SKIP ' . $name . ': destructive full rebuild, never run on an existing database' . PHP_EOL;
		$skipped++;
		continue;
	}

	$sql = (string) file_get_contents($file);
	$statements = migration_split_statements($sql);
	if ($statements === []) {
		echo 'SKIP ' . $name . ': nothing to run' . PHP_EOL;
		$skipped++;
		continue;
	}

	foreach ($statements as $statement) {
		$preview = substr((string) preg_replace('/\s+/', ' ', $statement), 0, 64);
		try {
			$pdo->exec($statement);
			echo 'OK   ' . $name . ': ' . $preview . PHP_EOL;
			$applied++;
		} catch (Throwable $e) {
			if (migration_error_is_benign($e)) {
				echo 'SKIP ' . $name . ': ' . $preview . ' (already applied)' . PHP_EOL;
				$skipped++;
			} else {
				echo 'FAIL ' . $name . ': ' . $preview . ' -> ' . $e->getMessage() . PHP_EOL;
				$failed++;
			}
		}
	}
}

echo PHP_EOL . '== Upload settings ==' . PHP_EOL;

$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');
$reel_max_mb = 32;
echo 'upload_max_filesize = ' . $upload_max . PHP_EOL;
echo 'post_max_size       = ' . $post_max . PHP_EOL;

function migration_ini_bytes(string $value): int
{
	$value = trim($value);
	$unit = strtolower(substr($value, -1));
	$number = (int) $value;
	return match ($unit) {
		'g' => $number * 1024 * 1024 * 1024,
		'm' => $number * 1024 * 1024,
		'k' => $number * 1024,
		default => $number,
	};
}

if (migration_ini_bytes((string) $upload_max) < $reel_max_mb * 1024 * 1024
	|| migration_ini_bytes((string) $post_max) < $reel_max_mb * 1024 * 1024) {
	echo 'WARN Reel video uploads need roughly ' . $reel_max_mb . 'MB. Raise upload_max_filesize and '
		. 'post_max_size (see .user.ini in the project root, or hPanel > PHP Configuration).' . PHP_EOL;
}

echo PHP_EOL . '== Uploads folders ==' . PHP_EOL;

foreach (['products', 'collections', 'banners', 'reels'] as $folder) {
	$path = __DIR__ . '/../uploads/' . $folder;
	if (!is_dir($path) && !@mkdir($path, 0755, true)) {
		echo 'FAIL uploads/' . $folder . ': could not create' . PHP_EOL;
		$failed++;
		continue;
	}
	echo (is_writable($path) ? 'OK   ' : 'WARN ') . 'uploads/' . $folder
		. (is_writable($path) ? '' : ' is not writable') . PHP_EOL;
}

echo PHP_EOL . '== store_users columns ==' . PHP_EOL;
try {
	$cols = $pdo->query('SHOW COLUMNS FROM store_users');
	if ($cols) {
		foreach ($cols as $col) {
			$default = $col['Default'] === null ? 'NULL' : (string) $col['Default'];
			echo sprintf(
				"%-18s %-24s null=%-3s default=%s\n",
				$col['Field'],
				$col['Type'],
				$col['Null'],
				$default
			);
		}
	}
} catch (Throwable $e) {
	echo 'FAIL describe store_users: ' . $e->getMessage() . PHP_EOL;
	$failed++;
}

echo PHP_EOL . 'Done. applied=' . $applied . ' skipped=' . $skipped . ' failed=' . $failed . PHP_EOL;
exit($failed > 0 ? 1 : 0);
