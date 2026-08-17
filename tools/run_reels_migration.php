<?php
declare(strict_types=1);

/**
 * CLI helper to create instagram_reels (and add its video_path / poster_path
 * columns) on the current environment's database.
 * On production, run on the server (Hostinger SSH / hPanel terminal):
 *   php tools/run_reels_migration.php
 *
 * Auto-migration also runs on admin and homepage requests via instagram_reel_ensure_schema().
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/instagram_reel.php';

$pdo = $GLOBALS['pdo'];
if (!$pdo instanceof PDO) {
	fwrite(STDERR, "No database connection.\n");
	exit(1);
}

try {
	instagram_reel_ensure_schema($pdo);
	echo 'OK: instagram_reels table ready (video_path, poster_path, published_sort_order present)' . PHP_EOL;
	$reels = instagram_reel_list_all($pdo);
	echo 'OK: ' . count($reels) . ' reel(s) in database' . PHP_EOL;

	$without_video = 0;
	foreach ($reels as $reel) {
		if (trim((string) ($reel['video_path'] ?? '')) === '') {
			$without_video++;
		}
	}
	if ($without_video > 0) {
		echo 'NOTE: ' . $without_video . ' reel(s) have no uploaded video and will fall back to a '
			. 'click-to-play Instagram embed. Upload MP4s in admin for muted autoplay.' . PHP_EOL;
	}
} catch (Throwable $e) {
	fwrite(STDERR, 'ERR: ' . $e->getMessage() . PHP_EOL);
	exit(1);
}
