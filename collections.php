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
							$count = (int) ($c['active_product_count'] ?? 0);
							$thumb_rel = trim(str_replace(['\\', '..'], '', (string) ($c['thumbnail_url'] ?? '')), '/ ');
							$thumb_src = '';
							if ($thumb_rel !== '') {
								$thumb_src = pearlica_uploads_collections_base($base_path) . '/' . implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $thumb_rel))));
							}
							?>
							<a href="<?php echo h($url); ?>" class="collections-card">
								<div class="collections-card-media" aria-hidden="true">
									<?php if ($thumb_src !== ''): ?>
										<img src="<?php echo h($thumb_src); ?>" alt="" class="collections-card-img" width="1080" height="1440" loading="lazy">
									<?php endif; ?>
								</div>
								<h2 class="collections-card-title"><?php echo h((string) ($c['name'] ?? 'Collection')); ?></h2>
								<p class="collections-card-meta"><?php echo (int) $count; ?> piece<?php echo $count === 1 ? '' : 's'; ?></p>
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
