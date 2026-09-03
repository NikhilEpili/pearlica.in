<?php
/**
 * Super-admin diagnostic: DOCUMENT_ROOT vs BASE_PATH uploads, sample file checks.
 */
require_once __DIR__ . '/config.php';
require_super_admin();
require_once ROOT . '/lib/media_serve.php';
require_once ROOT . '/lib/media_storage.php';
require_once ROOT . '/lib/homepage_banner.php';
require_once ROOT . '/lib/instagram_reel.php';

$pdo = $GLOBALS['pdo'];
$page_title = 'Media health';

$doc_root = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
$base_path = defined('BASE_PATH') ? (string) BASE_PATH : '';
$media_root = defined('MEDIA_STORAGE_ROOT') ? (string) MEDIA_STORAGE_ROOT : '';
$upload_products = defined('UPLOAD_PATH') ? (string) UPLOAD_PATH : '';
$upload_collections = defined('COLLECTION_UPLOAD_PATH') ? (string) COLLECTION_UPLOAD_PATH : '';
$upload_banners = defined('BANNER_UPLOAD_PATH') ? (string) BANNER_UPLOAD_PATH : '';
$upload_reels = defined('REEL_UPLOAD_PATH') ? (string) REEL_UPLOAD_PATH : '';
$media_durable = media_storage_is_durable();
$media_sibling = media_storage_sibling_root();
$media_legacy = media_storage_legacy_root();

$doc_uploads = $doc_root !== '' ? rtrim($doc_root, '/\\') . DIRECTORY_SEPARATOR . 'uploads' : '';
$base_uploads = $media_legacy;

$same_tree = false;
if ($doc_uploads !== '' && $base_uploads !== '') {
	$a = realpath($doc_uploads);
	$b = realpath($base_uploads);
	$same_tree = ($a !== false && $b !== false && $a === $b);
}

$counts = [
	'products' => 0,
	'collections' => 0,
	'banners' => 0,
	'reels' => 0,
];
foreach (array_keys($counts) as $bucket) {
	try {
		$counts[$bucket] = media_serve_count_files($bucket);
	} catch (Throwable $e) {
		$counts[$bucket] = -1;
	}
}

$homepage_missing = [];
try {
	homepage_banner_ensure_schema($pdo);
	foreach (homepage_banner_list_active($pdo, 'hero') as $row) {
		$rel = str_replace('\\', '/', (string) ($row['image_url'] ?? ''));
		if ($rel === '') {
			continue;
		}
		$normalized = media_serve_normalize_rel($rel, 'banners');
		$disk = media_serve_resolve('banners', $rel);
		$homepage_missing[] = [
			'label' => 'Homepage banner #' . (int) ($row['id'] ?? 0),
			'bucket' => 'banners',
			'rel' => $rel,
			'normalized' => $normalized,
			'on_disk' => $disk !== null,
			'disk_path' => $disk ?? '',
			'probed' => media_serve_bucket_root_candidates('banners'),
		];
	}
} catch (Throwable $e) {
	// ignore
}

try {
	instagram_reel_ensure_schema($pdo);
	foreach (instagram_reel_list_active($pdo) as $row) {
		$rid = (int) ($row['id'] ?? 0);
		foreach (['video_path' => 'video', 'poster_path' => 'poster'] as $col => $kind) {
			$rel = str_replace('\\', '/', (string) ($row[$col] ?? ''));
			if ($rel === '') {
				continue;
			}
			$normalized = media_serve_normalize_rel($rel, 'reels');
			$disk = media_serve_resolve('reels', $rel);
			$homepage_missing[] = [
				'label' => 'Active reel #' . $rid . ' (' . $kind . ')',
				'bucket' => 'reels',
				'rel' => $rel,
				'normalized' => $normalized,
				'on_disk' => $disk !== null,
				'disk_path' => $disk ?? '',
				'probed' => media_serve_bucket_root_candidates('reels'),
			];
		}
	}
} catch (Throwable $e) {
	// ignore
}

$homepage_missing_count = 0;
foreach ($homepage_missing as $item) {
	if (!$item['on_disk']) {
		$homepage_missing_count++;
	}
}

$sample_urls = [];
try {
	$stmt = $pdo->query(
		"SELECT pm.url
		 FROM product_media pm
		 INNER JOIN products p ON p.id = pm.product_id AND p.deleted_at IS NULL
		 WHERE pm.deleted_at IS NULL AND pm.media_type = 'image' AND pm.url IS NOT NULL AND pm.url != ''
		 ORDER BY pm.is_primary DESC, pm.id DESC
		 LIMIT 8"
	);
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$rel = str_replace('\\', '/', (string) ($row['url'] ?? ''));
		$disk = media_serve_resolve('products', $rel);
		$public = '/uploads/products/' . ltrim($rel, '/');
		$sample_urls[] = [
			'bucket' => 'products',
			'rel' => $rel,
			'public' => $public,
			'on_disk' => $disk !== null,
			'disk_path' => $disk ?? '',
		];
	}
} catch (Throwable $e) {
	// ignore
}

try {
	$stmt = $pdo->query(
		"SELECT thumbnail_url AS url FROM collections
		 WHERE deleted_at IS NULL AND thumbnail_url IS NOT NULL AND thumbnail_url != ''
		 LIMIT 5"
	);
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$rel = str_replace('\\', '/', (string) ($row['url'] ?? ''));
		$disk = media_serve_resolve('collections', $rel);
		$sample_urls[] = [
			'bucket' => 'collections',
			'rel' => $rel,
			'public' => '/uploads/collections/' . ltrim($rel, '/'),
			'on_disk' => $disk !== null,
			'disk_path' => $disk ?? '',
		];
	}
} catch (Throwable $e) {
	// ignore
}

try {
	$stmt = $pdo->query(
		"SELECT image_url AS url FROM homepage_banners
		 WHERE image_url IS NOT NULL AND image_url != ''
		 ORDER BY id DESC LIMIT 5"
	);
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$rel = str_replace('\\', '/', (string) ($row['url'] ?? ''));
		$disk = media_serve_resolve('banners', $rel);
		$sample_urls[] = [
			'bucket' => 'banners',
			'rel' => $rel,
			'public' => '/uploads/banners/' . ltrim($rel, '/'),
			'on_disk' => $disk !== null,
			'disk_path' => $disk ?? '',
		];
	}
} catch (Throwable $e) {
	// ignore
}

$missing = 0;
$present = 0;
foreach ($sample_urls as $s) {
	if ($s['on_disk']) {
		$present++;
	} else {
		$missing++;
	}
}

$probe_rel = $sample_urls[0]['rel'] ?? '';
$probe_bucket = $sample_urls[0]['bucket'] ?? 'products';
$media_probe = '';
if ($probe_rel !== '') {
	$media_probe = rtrim(web_base(), '/') . '/media.php?bucket=' . rawurlencode($probe_bucket) . '&path=' . rawurlencode($probe_rel);
}
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/header.php'; ?>
<body class="admin-body">
<?php require __DIR__ . '/components/wrapper_start.php'; ?>
<main class="container-fluid p-4 admin-content">
	<h1 class="page-title">Media health</h1>
	<p class="text-muted small">Compares PHP upload paths with the web document root and checks whether DB media paths exist on disk.</p>

	<?php if (!$media_durable): ?>
		<div class="alert alert-danger">
			<strong>Media is still inside the git deploy tree.</strong>
			Every Hostinger redeploy can wipe or roll back images.
			<a class="alert-link" href="<?= h(admin_url('media-migrate.php')) ?>">Migrate media to pearlica-media</a>
			(sibling of <code>public_html</code>), then confirm this page shows durable storage.
		</div>
	<?php else: ?>
		<div class="alert alert-success">
			<strong>Durable media storage is active.</strong>
			Writes go to <code><?= h($media_root) ?></code> (outside the deploy tree). Redeploys will not replace these files.
		</div>
	<?php endif; ?>

	<?php if ($homepage_missing_count > 0): ?>
		<div class="alert alert-danger">
			<strong><?= (int) $homepage_missing_count ?> homepage media file(s) are missing on disk.</strong>
			Run <a class="alert-link" href="<?= h(admin_url('media-migrate.php')) ?>">media migrate</a> again after this deploy
			(it now flattens <code>pearlica-media/uploads/…</code> and also reads <code>public_html/uploads</code>).
			If migrate copies 0 banners/reels, restore those folders from a Hostinger backup or re-upload in Admin.
		</div>
	<?php elseif ($missing > 0 && $present === 0): ?>
		<div class="alert alert-danger">
			<strong>Root cause:</strong> PHP cannot find image files under the media roots.
			The database has paths, but those files are missing from disk.
			<a class="alert-link" href="<?= h(admin_url('media-migrate.php')) ?>">Migrate</a>, restore from backup, or re-upload via Admin.
		</div>
	<?php elseif ($missing > 0): ?>
		<div class="alert alert-warning">Some sampled media files are missing on disk. Restore or re-upload those products/banners.</div>
	<?php elseif (!$media_durable && !$same_tree): ?>
		<div class="alert alert-warning">
			<strong>Document root and legacy uploads differ.</strong>
			Deploy the updated <code>.htaccess</code> + <code>media.php</code> so missing static files are streamed by PHP.
		</div>
	<?php elseif ($media_durable || $same_tree): ?>
		<div class="alert alert-success">Sampled media files are present on disk. If the storefront still 404s, purge Hostinger cache.</div>
	<?php endif; ?>

	<div class="card mb-4">
		<div class="card-header">Paths</div>
		<div class="card-body small">
			<table class="table table-sm mb-0">
				<tr><th>DOCUMENT_ROOT</th><td><code><?= h($doc_root) ?></code></td></tr>
				<tr><th>BASE_PATH</th><td><code><?= h($base_path) ?></code></td></tr>
				<tr><th>MEDIA_STORAGE_ROOT</th><td><code><?= h($media_root) ?></code> <?= $media_durable ? '✓ durable' : '✗ inside deploy tree' ?></td></tr>
				<tr><th>Durable sibling target</th><td><code><?= h($media_sibling) ?></code> <?= is_dir($media_sibling) ? '✓ dir' : '✗ not created yet' ?></td></tr>
				<tr><th>Legacy uploads</th><td><code><?= h($media_legacy) ?></code> <?= is_dir($media_legacy) ? '✓ dir' : '✗ missing' ?></td></tr>
				<tr><th>UPLOAD_PATH (products)</th><td><code><?= h($upload_products) ?></code> <?= is_dir($upload_products) ? '✓ dir' : '✗ missing' ?></td></tr>
				<tr><th>COLLECTION_UPLOAD_PATH</th><td><code><?= h($upload_collections) ?></code> <?= is_dir($upload_collections) ? '✓ dir' : '✗ missing' ?></td></tr>
				<tr><th>BANNER_UPLOAD_PATH</th><td><code><?= h($upload_banners) ?></code> <?= is_dir($upload_banners) ? '✓ dir' : '✗ missing' ?></td></tr>
				<tr><th>REEL_UPLOAD_PATH</th><td><code><?= h($upload_reels) ?></code> <?= is_dir($upload_reels) ? '✓ dir' : '✗ missing' ?></td></tr>
				<tr><th>Docroot uploads</th><td><code><?= h($doc_uploads) ?></code> <?= ($doc_uploads !== '' && is_dir($doc_uploads)) ? '✓ dir' : '✗ missing' ?></td></tr>
				<tr><th>Docroot == legacy uploads?</th><td><?= $same_tree ? 'Yes' : 'No — media.php fallback required for static misses' ?></td></tr>
			</table>
			<p class="mt-3 mb-0"><a href="<?= h(admin_url('media-migrate.php')) ?>" class="btn btn-sm btn-outline-primary">Migrate media</a></p>
		</div>
	</div>

	<div class="card mb-4">
		<div class="card-header">Files on disk (via PHP)</div>
		<div class="card-body">
			<ul class="mb-0">
				<li>products: <strong><?= (int) $counts['products'] ?></strong></li>
				<li>collections: <strong><?= (int) $counts['collections'] ?></strong></li>
				<li>banners: <strong><?= (int) $counts['banners'] ?></strong></li>
				<li>reels: <strong><?= (int) $counts['reels'] ?></strong></li>
			</ul>
			<?php if ($counts['products'] === 0): ?>
				<p class="text-danger small mt-2 mb-0">Products upload folder is empty from PHP’s perspective. File Manager may be showing a different folder than <code><?= h($upload_products) ?></code>.</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ($homepage_missing !== []): ?>
	<div class="card mb-4">
		<div class="card-header">Homepage media (active banners + reels)</div>
		<div class="card-body p-0">
			<table class="table table-sm mb-0">
				<thead><tr><th>Item</th><th>DB path</th><th>Normalized</th><th>On disk?</th><th>Resolved path</th></tr></thead>
				<tbody>
				<?php foreach ($homepage_missing as $item): ?>
					<tr class="<?= $item['on_disk'] ? '' : 'table-danger' ?>">
						<td><?= h($item['label']) ?></td>
						<td><code><?= h($item['bucket'] . '/' . $item['rel']) ?></code></td>
						<td><code><?= h((string) ($item['normalized'] ?? '')) ?></code></td>
						<td><?= $item['on_disk'] ? '✓' : '✗ missing' ?></td>
						<td class="small"><?= $item['on_disk'] ? '<code>' . h((string) ($item['disk_path'] ?? '')) . '</code>' : '—' ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$first_missing = null;
			foreach ($homepage_missing as $item) {
				if (!$item['on_disk']) {
					$first_missing = $item;
					break;
				}
			}
			?>
			<?php if ($first_missing !== null && !empty($first_missing['probed'])): ?>
				<div class="p-3 small border-top">
					<strong>Probe roots for <?= h((string) $first_missing['bucket']) ?>:</strong>
					<ul class="mb-0 mt-1">
						<?php foreach ($first_missing['probed'] as $root): ?>
							<li><code><?= h($root) ?></code> <?= is_dir($root) ? '✓' : '✗' ?></li>
						<?php endforeach; ?>
					</ul>
					<p class="text-muted mb-0 mt-2">
						Expected file layout: <code>pearlica-media/<?= h((string) $first_missing['bucket']) ?>/<?= h((string) ($first_missing['normalized'] !== '' ? $first_missing['normalized'] : $first_missing['rel'])) ?></code>
						— not nested under an extra <code>uploads/</code> folder.
					</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="card mb-4">
		<div class="card-header">Sample DB paths vs disk</div>
		<div class="card-body p-0">
			<table class="table table-sm mb-0">
				<thead><tr><th>Bucket</th><th>Relative path</th><th>On disk?</th><th>Public URL</th></tr></thead>
				<tbody>
				<?php foreach ($sample_urls as $s): ?>
					<tr>
						<td><?= h($s['bucket']) ?></td>
						<td><code><?= h($s['rel']) ?></code></td>
						<td><?= $s['on_disk'] ? '✓' : '✗ missing' ?></td>
						<td><a href="<?= h($s['public']) ?>" target="_blank" rel="noopener"><?= h($s['public']) ?></a></td>
					</tr>
				<?php endforeach; ?>
				<?php if ($sample_urls === []): ?>
					<tr><td colspan="4" class="text-muted">No media rows found in the database.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php if ($media_probe !== ''): ?>
	<p class="small">PHP gateway probe: <a href="<?= h($media_probe) ?>" target="_blank" rel="noopener"><?= h($media_probe) ?></a></p>
	<?php endif; ?>
	<a href="<?= h(admin_url('dashboard/')) ?>" class="btn btn-secondary">Back</a>
</main>
<?php require __DIR__ . '/components/wrapper_end.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
