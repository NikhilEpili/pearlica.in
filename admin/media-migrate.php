<?php
/**
 * One-time: copy public_html/uploads → sibling pearlica-media (outside git deploy).
 */
require_once __DIR__ . '/config.php';
require_super_admin();
require_once ROOT . '/lib/media_storage.php';

$page_title = 'Migrate media';
$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf()) {
		$error = 'Invalid request token. Reload and try again.';
	} else {
		$result = media_storage_migrate_from_legacy();
		if (!$result['ok']) {
			$error = (string) ($result['error'] ?? 'Migration failed.');
			$result = null;
		}
	}
}

$sibling = media_storage_sibling_root();
$legacy = media_storage_legacy_root();
$durable = media_storage_is_durable();
$current_root = defined('MEDIA_STORAGE_ROOT') ? (string) MEDIA_STORAGE_ROOT : '';
?>
<!DOCTYPE html>
<html lang="en">
<?php require dirname(__DIR__) . '/header.php'; ?>
<body class="admin-body">
<?php require dirname(__DIR__) . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Migrate media to durable storage</h1>
	<p class="text-muted small mb-3">
		Hostinger git deploy replaces files inside <code>public_html</code>. Media must live in
		<code>pearlica-media</code> next to <code>public_html</code>, not inside it.
	</p>

	<div class="card mb-4">
		<div class="card-body small">
			<p class="mb-1"><strong>Legacy (deploy tree):</strong> <code><?= h($legacy) ?></code></p>
			<p class="mb-1"><strong>Durable target:</strong> <code><?= h($sibling) ?></code></p>
			<p class="mb-0"><strong>Active write root:</strong> <code><?= h($current_root) ?></code>
				<?= $durable ? ' <span class="text-success">(durable)</span>' : ' <span class="text-danger">(still inside deploy tree)</span>' ?>
			</p>
		</div>
	</div>

	<?php if ($error !== ''): ?>
		<div class="alert alert-danger"><?= h($error) ?></div>
	<?php endif; ?>

	<?php if ($result !== null): ?>
		<div class="alert alert-success">
			<strong>Copy finished into</strong> <code><?= h($result['dest']) ?></code>.
			Open Media health next. New uploads write to the durable root when it exists.
		</div>
		<table class="table table-sm">
			<thead><tr><th>Bucket</th><th>Copied</th><th>Skipped (already present)</th><th>Errors</th></tr></thead>
			<tbody>
			<?php foreach ($result['buckets'] as $bucket => $stats): ?>
				<tr>
					<td><?= h($bucket) ?></td>
					<td><?= (int) $stats['copied'] ?></td>
					<td><?= (int) $stats['skipped'] ?></td>
					<td>
						<?php if ($stats['errors'] === []): ?>
							—
						<?php else: ?>
							<?= h(implode('; ', array_slice($stats['errors'], 0, 5))) ?>
							<?= count($stats['errors']) > 5 ? '…' : '' ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ($durable && $result === null && $error === ''): ?>
		<div class="alert alert-info">
			Durable storage is already active. You can still re-run the copy to pick up any files
			left only under the legacy <code>uploads/</code> folder.
		</div>
	<?php endif; ?>

	<form method="post" class="mb-4" onsubmit="return confirm('Copy all media from legacy uploads into pearlica-media?');">
		<?= csrf_field() ?>
		<button type="submit" class="btn btn-primary">Copy media to pearlica-media</button>
		<a href="<?= h(admin_url('media-health.php')) ?>" class="btn btn-secondary">Media health</a>
	</form>

	<div class="alert alert-warning small">
		<strong>After a successful copy:</strong> redeploys will no longer wipe images, as long as
		upload binaries stay out of git (repo only keeps <code>uploads/.htaccess</code> stubs).
		Public URLs stay <code>/uploads/...</code>; PHP streams from the durable folder when the
		static file under <code>public_html</code> is missing.
	</div>
</main>
<?php require dirname(__DIR__) . '/components/wrapper_end.php'; ?>
<?php require dirname(__DIR__) . '/footer.php'; ?>
</body>
</html>
