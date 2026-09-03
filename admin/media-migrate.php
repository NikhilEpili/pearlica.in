<?php
/**
 * Sync media between public_html/uploads and pearlica-media (bidirectional).
 */
require_once __DIR__ . '/config.php';
require_super_admin();
require_once ROOT . '/lib/media_storage.php';
require_once ROOT . '/lib/media_serve.php';

$page_title = 'Restore media';
$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		$error = 'Invalid request token. Reload and try again.';
	} else {
		$result = media_storage_sync_trees();
		if (!$result['ok']) {
			$error = (string) ($result['error'] ?? 'Sync failed.');
			$result = null;
		}
	}
}

$sibling = media_storage_sibling_root();
$legacy = media_storage_legacy_root();
$durable = media_storage_is_durable();
$current_root = defined('MEDIA_STORAGE_ROOT') ? (string) MEDIA_STORAGE_ROOT : '';
$counts = [];
foreach (media_storage_bucket_names() as $bucket) {
	try {
		$counts[$bucket] = media_serve_count_files($bucket);
	} catch (Throwable $e) {
		$counts[$bucket] = -1;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Restore / sync media</h1>

	<div class="alert alert-danger">
		<strong>Why images 404:</strong> Hostinger git deploy wiped <code>public_html/uploads</code>.
		The database still has paths, so Admin and the homepage <em>reference</em> images that are gone from disk.
		Code cannot recreate those files — restore them once, then keep them in <code>pearlica-media</code>.
	</div>

	<div class="card mb-4">
		<div class="card-header">Do this in Hostinger File Manager (once)</div>
		<div class="card-body small">
			<ol class="mb-0">
				<li>Open <strong>Backups</strong> (or Files) and restore the latest backup of <code>public_html/uploads</code>.</li>
				<li>Confirm files exist under <code>public_html/uploads/products</code>, <code>banners</code>, <code>reels</code>.</li>
				<li>Ensure folder <code>pearlica-media</code> exists <strong>next to</strong> <code>public_html</code> (not inside it).</li>
				<li>Come back here and click <strong>Sync media both ways</strong>.</li>
				<li>Open the homepage on your phone — banners/products/reels should load.</li>
			</ol>
		</div>
	</div>

	<div class="card mb-4">
		<div class="card-body small">
			<p class="mb-1"><strong>Write root (new uploads):</strong> <code><?= h($current_root) ?></code>
				<?= $durable ? ' <span class="text-success">✓ durable (survives redeploy)</span>' : ' <span class="text-warning">inside public_html — create pearlica-media next to public_html</span>' ?>
			</p>
			<p class="mb-1"><strong>public_html/uploads:</strong> <code><?= h($legacy) ?></code></p>
			<p class="mb-1"><strong>pearlica-media:</strong> <code><?= h($sibling) ?></code> <?= is_dir($sibling) ? '✓' : '✗ missing' ?></p>
			<p class="mb-0"><strong>Files PHP can see now:</strong>
				products <?= (int) ($counts['products'] ?? 0) ?>,
				banners <?= (int) ($counts['banners'] ?? 0) ?>,
				reels <?= (int) ($counts['reels'] ?? 0) ?>,
				collections <?= (int) ($counts['collections'] ?? 0) ?>
			</p>
		</div>
	</div>

	<?php if ($error !== ''): ?>
		<div class="alert alert-danger"><?= h($error) ?></div>
	<?php endif; ?>

	<?php if ($result !== null): ?>
		<div class="alert alert-success">Sync finished. Check file counts above (refresh page) and test the homepage.</div>
		<table class="table table-sm">
			<thead><tr><th>Bucket</th><th>Copied</th><th>Skipped</th><th>Errors</th></tr></thead>
			<tbody>
			<?php foreach ($result['buckets'] as $bucket => $stats): ?>
				<tr>
					<td><?= h($bucket) ?></td>
					<td><?= (int) $stats['copied'] ?></td>
					<td><?= (int) $stats['skipped'] ?></td>
					<td><?= $stats['errors'] === [] ? '—' : h(implode('; ', array_slice($stats['errors'], 0, 3))) ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<form method="post" class="mb-3" onsubmit="return confirm('Sync media between pearlica-media and public_html/uploads?');">
		<?= csrf_field() ?>
		<button type="submit" class="btn btn-primary">Sync media both ways</button>
		<a href="<?= h(admin_url('media-health.php')) ?>" class="btn btn-secondary">Media health</a>
	</form>

	<p class="text-muted small mb-0">
		After restore: new Admin uploads go to <code>pearlica-media</code> (if that folder exists).
		<code>media.php</code> serves them at <code>/uploads/...</code> even when git redeploy empties <code>public_html/uploads</code>.
	</p>
</main>
<?php require __DIR__ . '/components/wrapper_end.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
