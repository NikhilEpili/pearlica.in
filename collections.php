<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/collection.php';
require_once __DIR__ . '/includes/store_base_path.php';

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$base_path = store_base_path_from_request();

$pdo = $GLOBALS['pdo'];
$collections = [];
try {
	$collections = collection_list_for_store($pdo);
} catch (Throwable $e) {
	$collections = [];
}

if (!function_exists('collection_cover_dimensions')) {
	/**
	 * @return array{width:int,height:int}
	 */
	function collection_cover_dimensions(string $rel): array
	{
		$fallback_w = defined('COLLECTION_COVER_WIDTH') ? (int) COLLECTION_COVER_WIDTH : 1080;
		$fallback_h = defined('COLLECTION_COVER_HEIGHT') ? (int) COLLECTION_COVER_HEIGHT : 1440;
		$rel = trim(str_replace(['\\', '..'], '', $rel), '/ ');
		if ($rel === '' || !defined('COLLECTION_UPLOAD_PATH')) {
			return ['width' => $fallback_w, 'height' => $fallback_h];
		}
		$path = COLLECTION_UPLOAD_PATH . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
		$info = is_file($path) ? @getimagesize($path) : false;
		if ($info === false) {
			return ['width' => $fallback_w, 'height' => $fallback_h];
		}

		return [
			'width' => max(1, (int) ($info[0] ?? $fallback_w)),
			'height' => max(1, (int) ($info[1] ?? $fallback_h)),
		];
	}
}

$page_title = 'Collections | Pearlica 925 Silver Jewellery';
$meta_title_full = $page_title;
$meta_description = 'Shop curated jewellery collections from Pearlica — 925 sterling silver pieces grouped by theme and edit.';
$meta_keywords = 'Pearlica, collections, curated jewellery, 925 silver, sterling silver India';
?>
<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h(asset_url('assets/css/products.css', $base_path)); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('assets/css/collections.css', $base_path)); ?>">

<body class="products-page collections-page">
	<?php include __DIR__ . '/components/gtm_body.php'; ?>

	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main class="products-main collections-main">
		<section class="products-section collections-index-section" aria-label="Collections">
			<div class="products-page-content">
				<p class="products-label">Pearlica</p>
				<h1 class="products-title">Collections</h1>
				<p class="collections-intro text-muted">Curated sets of our silver jewellery. Open a collection to shop the pieces inside.</p>

				<?php if (empty($collections)): ?>
					<p class="collections-empty text-muted">No collections are live yet. Check back soon.</p>
				<?php else: ?>
					<div class="collections-grid">
						<?php foreach ($collections as $c):
							$slug = (string) ($c['slug'] ?? '');
							$url = $base_path . 'collection?slug=' . rawurlencode($slug);
							$desc = trim((string) ($c['description'] ?? ''));
							$excerpt = $desc !== '' ? (mb_strlen($desc) > 160 ? mb_substr($desc, 0, 157) . '…' : $desc) : '';
							$thumb_rel = trim(str_replace(['\\', '..'], '', (string) ($c['thumbnail_url'] ?? '')), '/ ');
							$thumb_src = '';
							$thumb_w = defined('COLLECTION_COVER_WIDTH') ? (int) COLLECTION_COVER_WIDTH : 1080;
							$thumb_h = defined('COLLECTION_COVER_HEIGHT') ? (int) COLLECTION_COVER_HEIGHT : 1440;
							if ($thumb_rel !== '') {
								$thumb_src = pearlica_uploads_collections_base($base_path) . '/' . implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $thumb_rel))));
								$dims = collection_cover_dimensions($thumb_rel);
								$thumb_w = (int) $dims['width'];
								$thumb_h = (int) $dims['height'];
							}
							?>
							<a href="<?php echo h($url); ?>" class="collections-card">
								<div class="collections-card-media" aria-hidden="true">
									<?php if ($thumb_src !== ''): ?>
										<img src="<?php echo h($thumb_src); ?>" alt="" class="collections-card-img"
											width="<?php echo $thumb_w; ?>" height="<?php echo $thumb_h; ?>"
											srcset="<?php echo h($thumb_src); ?> <?php echo $thumb_w; ?>w"
											sizes="(min-width: 1200px) 200px, (min-width: 768px) 180px, 45vw" loading="lazy" decoding="async">
									<?php endif; ?>
								</div>
								<h2 class="collections-card-title"><?php echo h((string) ($c['name'] ?? 'Collection')); ?></h2>
								<?php if ($excerpt !== ''): ?>
									<p class="collections-card-desc"><?php echo h($excerpt); ?></p>
								<?php endif; ?>
								<span class="collections-card-cta">View collection</span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
	</main>

	<?php include __DIR__ . '/components/footer.php'; ?>

	<script src="<?php echo h(asset_url('assets/js/jquery.min.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/plugins.js', $base_path)); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe"
		crossorigin="anonymous"></script>
	<?php include __DIR__ . '/components/swiper_bundle_script.php'; ?>
	<script src="<?php echo h(asset_url('assets/js/script.min.js', $base_path)); ?>"></script>
</body>

</html>
