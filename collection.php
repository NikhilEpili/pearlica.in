<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/collection.php';
require_once __DIR__ . '/includes/wishlist_cookie.php';
require_once __DIR__ . '/includes/shop_product_helpers.php';
require_once __DIR__ . '/includes/store_base_path.php';

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$base_path = store_base_path_from_request();
$uploads_base = pearlica_uploads_products_base($base_path);

$pdo = $GLOBALS['pdo'];
$collection = null;
$products = [];
if ($slug !== '') {
	try {
		$collection = collection_find_by_slug_store($pdo, $slug);
		if ($collection) {
			$products = collection_products_for_store($pdo, (int) $collection['id']);
		}
	} catch (Throwable $e) {
		$collection = null;
		$products = [];
	}
}

if ($slug === '') {
	header('Location: ' . $base_path . 'collections', true, 302);
	exit;
}
if (!$collection) {
	http_response_code(404);
	$page_title = 'Collection not found | Pearlica';
	include __DIR__ . '/404.php';
	exit;
}

$wishlist_ids = array_keys(wishlist_from_cookie());
$wishlist_return = 'collection?slug=' . rawurlencode($slug);

$name = (string) ($collection['name'] ?? 'Collection');
$meta_title_full = !empty($collection['meta_title']) ? (string) $collection['meta_title'] : ($name . ' | Collections | Pearlica');
$meta_description = !empty($collection['meta_description']) ? (string) $collection['meta_description'] : trim((string) ($collection['description'] ?? ''));
if ($meta_description === '') {
	$meta_description = 'Shop the ' . $name . ' collection — 925 sterling silver jewellery from Pearlica.';
}
$meta_keywords = 'Pearlica, collection, ' . $name . ', 925 silver, jewellery India';
?>
<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h(asset_url('assets/css/products.css', $base_path)); ?>">
<link rel="stylesheet" href="<?php echo h(asset_url('assets/css/collections.css', $base_path)); ?>">

<body class="products-page collection-detail-page">

	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main class="products-main">
		<section id="collection-listing" class="products-section" aria-label="<?php echo h($name); ?>">
			<div class="products-page-content">
				<p class="products-label"><a href="<?php echo h($base_path . 'collections'); ?>" class="collections-back-link">Collections</a></p>
				<div class="products-heading-row">
					<div class="products-heading-bar">
						<div class="products-heading-text">
							<h1 class="products-title"><?php echo h($name); ?></h1>
							<?php if (!empty($collection['description'])): ?>
								<p class="collections-detail-desc text-muted"><?php echo nl2br(h((string) $collection['description'])); ?></p>
							<?php endif; ?>
							<p class="products-result-count products-result-count--below-title">
								<?php echo count($products) > 0 ? (int) count($products) . ' piece' . (count($products) === 1 ? '' : 's') : 'No pieces in this collection yet'; ?>
							</p>
						</div>
					</div>
				</div>

				<?php if (empty($products)): ?>
					<div class="products-empty">
						<p class="products-empty-text">This collection has no active products right now.</p>
						<a href="<?php echo h($base_path . 'collections'); ?>" class="products-btn-back">All collections</a>
						<a href="<?php echo h($base_path . 'products'); ?>" class="products-btn-back ms-2">Shop all</a>
					</div>
				<?php else: ?>
					<div class="products-layout">
						<div class="products-grid-wrap">
							<div class="products-grid" id="products-grid">
								<?php foreach ($products as $p): ?>
									<?php
									extract(pearlica_product_card_vars($p, $uploads_base, $base_path, $wishlist_ids, $wishlist_return), EXTR_OVERWRITE);
									include __DIR__ . '/components/products_card.php';
									?>
								<?php endforeach; ?>
							</div>
						</div>
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
	<script src="<?php echo h(asset_url('assets/js/wishlist.js', $base_path)); ?>"></script>
</body>

</html>
