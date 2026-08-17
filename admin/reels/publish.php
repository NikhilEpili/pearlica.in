<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_permission('reels');
require_once ROOT . '/lib/instagram_reel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('reels/'));
}

if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('reels/'));
}

$pdo = $GLOBALS['pdo'];

try {
	instagram_reel_ensure_schema($pdo);
	$count = instagram_reel_publish($pdo);
	flash_set('success', $count . ' reel(s) pushed to the homepage.');
} catch (InvalidArgumentException $e) {
	flash_set('error', $e->getMessage());
} catch (Throwable $e) {
	error_log('Instagram reel publish failed: ' . $e->getMessage());
	flash_set('error', 'Could not push reels to the homepage. Please try again.');
}

redirect(admin_url('reels/'));
