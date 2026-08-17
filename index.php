<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/product.php';
require_once __DIR__ . '/lib/category.php';
require_once __DIR__ . '/includes/wishlist_cookie.php';
require_once __DIR__ . '/includes/shop_product_helpers.php';
require_once __DIR__ . '/lib/instagram_reel.php';

$base_path = '';

// Note: the old `coming-soon.php` gate was removed.

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$pdo = $GLOBALS['pdo'];
$uploads_base = pearlica_uploads_products_base($base_path ?? '/');

$new_arrivals_filters = ['sort' => 'newest'];
$days = (int) (INDEX_NEW_ARRIVALS_DAYS ?? 0);
if ($days > 0) {
	$new_arrivals_filters['added_within_days'] = $days;
}
$new_arrivals_max = max(1, (int) (INDEX_NEW_ARRIVALS_MAX ?? 500));
$new_arrivals_res = product_list_store($pdo, 1, $new_arrivals_max, $new_arrivals_filters);
$new_arrivals_products = $new_arrivals_res['items'];
if ($new_arrivals_products === [] && $days > 0) {
	$new_arrivals_res = product_list_store($pdo, 1, $new_arrivals_max, ['sort' => 'newest']);
	$new_arrivals_products = $new_arrivals_res['items'];
}

$wishlist_ids = array_keys(wishlist_from_cookie());
$wishlist_return = 'index';

$influencer_reels = [];
try {
	instagram_reel_ensure_schema($pdo);
	$influencer_reels = instagram_reel_list_active($pdo);
} catch (Throwable $e) {
	$influencer_reels = [];
}

$index_price_category_tiles = [
	[
		'label' => 'Under Rs. 1999',
		'img' => $base_path . 'assets/images/price-categories/1.png',
		'url' => $base_path . 'products?max_price=1999',
	],
	[
		'label' => 'Under Rs. 2999',
		'img' => $base_path . 'assets/images/price-categories/2.png',
		'url' => $base_path . 'products?max_price=2999',
	],
	[
		'label' => 'Under Rs. 4999',
		'img' => $base_path . 'assets/images/price-categories/3.png',
		'url' => $base_path . 'products?max_price=4999',
	],
	[
		'label' => 'Under Rs. 9999',
		'img' => $base_path . 'assets/images/price-categories/4.png',
		'url' => $base_path . 'products?max_price=9999',
	],
];
// Prefer exact category slugs so substring traps like "ring"→Earrings / "pendant"→Necklace & Pendants cannot fire.
$index_special_category_defs = [
	['file' => 'bracelets.png', 'legacy_file' => 'Bracelets.png', 'label' => 'Bracelets', 'slugs' => ['bracelets'], 'needles' => ['bracelets', 'bracelet']],
	['file' => 'earrings.png', 'legacy_file' => 'Earrings.png', 'label' => 'Earrings', 'slugs' => ['earrings'], 'needles' => ['earrings', 'earring']],
	['file' => 'jwellery sets.png', 'legacy_file' => 'Jwellery Sets.png', 'label' => 'Jewellery Sets', 'slugs' => ['jewellery-sets'], 'needles' => ['jewellery sets', 'jewelry sets', 'jwellery sets']],
	['file' => 'kada.png', 'legacy_file' => 'Kada.png', 'label' => 'Kada', 'slugs' => ['kada'], 'needles' => ['kada']],
	['file' => 'necklace.png', 'legacy_file' => 'Necklace.png', 'label' => 'Necklace', 'slugs' => ['necklace-pendants'], 'needles' => ['necklace & pendants', 'necklace']],
	['file' => 'pearls.png', 'legacy_file' => 'Pearls.png', 'label' => 'Pearls', 'slugs' => ['pearl-collection'], 'needles' => ['pearl collection', 'pearls']],
	['file' => 'pendant-sets.png', 'label' => 'Pendant Sets', 'slugs' => ['pendant-sets'], 'needles' => ['pendant sets', 'pendant set']],
	['file' => 'rings.png', 'legacy_file' => 'Rings.png', 'label' => 'Rings', 'slugs' => ['rings'], 'needles' => ['rings']],
];
// Match against all categories (not only those with active stock) so drafted Jewellery Sets still links correctly.
$index_match_categories = category_list_all($pdo);
$index_special_category_tiles = [];
foreach ($index_special_category_defs as $def) {
	$shop_url = $base_path . 'products';
	$matched_id = 0;
	$slug_targets = array_map('strtolower', $def['slugs'] ?? []);
	$best_score = -1;

	foreach ($index_match_categories as $c) {
		$cid = (int) ($c['id'] ?? 0);
		if ($cid <= 0) {
			continue;
		}
		$cslug = strtolower(trim((string) ($c['slug'] ?? '')));
		$cname = strtolower(trim((string) ($c['name'] ?? '')));
		$is_parent = empty($c['parent_id']) || (int) $c['parent_id'] === 0;
		$score = -1;

		if ($cslug !== '' && in_array($cslug, $slug_targets, true)) {
			$score = 1000 + ($is_parent ? 50 : 0);
		} else {
			foreach ($def['needles'] as $needle) {
				$nd = strtolower(trim((string) $needle));
				if ($nd === '') {
					continue;
				}
				if ($cname === $nd) {
					$score = max($score, 900 + ($is_parent ? 50 : 0));
					continue;
				}
				// Whole-word / phrase match only (avoids "ring" inside "earrings", "pendant" inside "necklace & pendants").
				$pattern = '/(?:^|[\s&\-\/])' . preg_quote($nd, '/') . '(?:$|[\s&\-\/])/u';
				if (preg_match($pattern, ' ' . $cname . ' ')) {
					$score = max($score, 500 + strlen($nd) + ($is_parent ? 50 : 0));
				}
			}
		}

		if ($score > $best_score) {
			$best_score = $score;
			$matched_id = $cid;
		}
	}

	if ($matched_id > 0) {
		$shop_url = $base_path . 'products?category=' . $matched_id;
	}

	$file_name = (string) ($def['file'] ?? '');
	$disk_path = __DIR__ . '/assets/images/special-categories/' . $file_name;
	if ($file_name !== '' && !is_file($disk_path)) {
		$legacy = trim((string) ($def['legacy_file'] ?? ''));
		if ($legacy !== '' && is_file(__DIR__ . '/assets/images/special-categories/' . $legacy)) {
			$file_name = $legacy;
		}
	}
	$index_special_category_tiles[] = [
		'label' => $def['label'],
		'img' => $base_path . 'assets/images/special-categories/' . rawurlencode($file_name),
		'url' => $shop_url,
	];
}

$index_pick_video_ids = array_values(array_filter(array_map('trim', explode(',', (string) (defined('INDEX_HOME_PICK_VIDEO_IDS') ? INDEX_HOME_PICK_VIDEO_IDS : '')))));

$view_all_url = 'products?sort=newest';

require_once __DIR__ . '/lib/homepage_banner.php';
$banners_upload_base = pearlica_uploads_banners_base($base_path ?? '/');
$hero_fallback_src = $base_path . 'assets/images/banner_0.png';
$mid_fallback_src = $base_path . 'assets/images/banner_1.png';
$hero_banners = [];
$hero_rows = [];
try {
	if ($pdo) {
		homepage_banner_ensure_schema($pdo);
		$hero_rows = homepage_banner_list_active($pdo, 'hero');
	}
} catch (Throwable $e) {
	$hero_rows = [];
}

// Only use hardcoded fallback assets when no active banners exist in the DB.
// Do not revert to defaults when admin banners exist but were filtered/skipped.
$hero_banners = homepage_banner_hydrate_for_display(
	$hero_rows,
	$banners_upload_base,
	'Pearlica homepage banner'
);
if ($hero_rows === []) {
	$hero_banners = [
		[
			'id' => 0,
			'image_url' => '',
			'src' => $hero_fallback_src,
			'alt_text' => 'Pearlica launch offer banner — upto 50% off',
			'link_url' => null,
		],
		[
			'id' => 0,
			'image_url' => '',
			'src' => $mid_fallback_src,
			'alt_text' => 'Pearlica pearl jewelry banner — elegant 925 silver collection',
			'link_url' => null,
		],
	];
}

?>
<!DOCTYPE html>
<html lang="en">

<?php include 'components/head.php'; ?>
<link rel="stylesheet" href="<?php echo asset_url('assets/css/products.css'); ?>">
<link rel="stylesheet" href="<?php echo asset_url('assets/css/index.css'); ?>">

<body class="homepage">

	<?php include 'components/navbar.php'; ?>

	<!--
		Home sequence (below global promo + header from navbar):
		1) Banner (hero carousel)  2) Shop by price  3) Shop by category
		4) New arrivals  5) Influencer reels  6) Pearlica Picks (videos)  7) Footer
	-->
	<section class="index-hero-banners" aria-label="Pearlica featured banners">
		<div id="index-hero-carousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4500" data-bs-pause="false" data-bs-touch="true">
			<div class="carousel-inner">
				<?php foreach ($hero_banners as $hi => $hb): ?>
					<?php
					$slide_src = (string) ($hb['src'] ?? '');
					$slide_alt = (string) ($hb['alt_text'] ?? 'Pearlica homepage banner');
					$slide_link = trim((string) ($hb['link_url'] ?? ''));
					?>
					<div class="carousel-item<?= $hi === 0 ? ' active' : '' ?>">
						<?php if ($slide_link !== ''): ?>
							<a href="<?= h($slide_link) ?>" class="d-block index-hero-banner-link">
								<img class="index-hero-banner-img w-100 d-block" src="<?= h($slide_src); ?>"
									alt="<?= h($slide_alt) ?>" width="1280" height="600"
									sizes="100vw" decoding="async"<?= $hi === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
							</a>
						<?php else: ?>
							<img class="index-hero-banner-img w-100 d-block" src="<?= h($slide_src); ?>"
								alt="<?= h($slide_alt) ?>" width="1280" height="600"
								sizes="100vw" decoding="async"<?= $hi === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if (count($hero_banners) > 1): ?>
				<button class="carousel-control-prev" type="button" data-bs-target="#index-hero-carousel" data-bs-slide="prev" aria-label="Previous banner">
					<span class="carousel-control-prev-icon" aria-hidden="true"></span>
				</button>
				<button class="carousel-control-next" type="button" data-bs-target="#index-hero-carousel" data-bs-slide="next" aria-label="Next banner">
					<span class="carousel-control-next-icon" aria-hidden="true"></span>
				</button>
				<div class="carousel-indicators">
					<?php foreach ($hero_banners as $hi => $_): ?>
						<button type="button" data-bs-target="#index-hero-carousel" data-bs-slide-to="<?= (int) $hi ?>"<?= $hi === 0 ? ' class="active" aria-current="true"' : '' ?> aria-label="Show banner <?= (int) ($hi + 1) ?>"></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="index-price-categories section-reveal" aria-label="Shop by price">
		<div class="index-price-categories-inner">
			<h2 class="index-price-categories-title">Shop by Price</h2>
			<div class="index-price-categories-grid">
				<?php foreach ($index_price_category_tiles as $tile): ?>
					<a href="<?= h($tile['url']) ?>" class="index-price-cat-tile">
						<img src="<?= h($tile['img']) ?>" alt="<?= h($tile['label']) ?>" class="index-price-cat-img"
							loading="lazy" decoding="async" width="819" height="461">
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="index-special-categories section-reveal" aria-label="Shop by categories">
		<div class="index-special-categories-inner">
			<h2 class="index-special-categories-title">Shop By Category</h2>
			<div class="index-special-categories-grid">
				<?php foreach ($index_special_category_tiles as $tile): ?>
					<a href="<?= h($tile['url']) ?>" class="index-special-cat-tile">
						<div class="index-special-cat-img-wrap">
							<img src="<?= h($tile['img']) ?>" alt="<?= h($tile['label']) ?> — shop collection"
								class="index-special-cat-img" loading="lazy" decoding="async"
								width="600" height="600">
							<span class="index-special-cat-gradient" aria-hidden="true"></span>
							<span class="index-special-cat-copy">
								<span class="index-special-cat-title"><?= h($tile['label']) ?></span>
								<span class="index-special-cat-cta">Shop now <span class="index-special-cat-arrow"
										aria-hidden="true">↗</span></span>
							</span>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="index-products section-reveal" aria-label="New Arrivals">
		<div class="index-products-head">
			<h2 class="index-products-title">New Arrivals</h2>
			<a href="<?= h($base_path . $view_all_url) ?>" class="index-products-viewall">View All</a>
		</div>
		<div class="products-grid-wrap">
			<?php if (empty($new_arrivals_products)): ?>
				<p class="index-products-empty" style="font-family: var(--font-body); color: var(--text-muted); margin: 0;">
					No products yet. <a href="<?= h($base_path); ?>products" style="color: inherit;">View all</a>.</p>
			<?php else: ?>
				<div class="products-grid">
					<?php foreach ($new_arrivals_products as $p): ?>
						<?php
						extract(pearlica_product_card_vars($p, $uploads_base, $base_path, $wishlist_ids, $wishlist_return), EXTR_OVERWRITE);
						include __DIR__ . '/components/products_card.php';
						?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php include __DIR__ . '/components/index_influencer_reels.php'; ?>

	<?php include __DIR__ . '/components/index_pearlica_picks.php'; ?>

	<?php include 'components/footer.php'; ?>

	<script src="<?php echo asset_url('assets/js/jquery.min.js'); ?>"></script>
	<script src="<?php echo asset_url('assets/js/plugins.js'); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe"
		crossorigin="anonymous"></script>
	<?php include __DIR__ . '/components/swiper_bundle_script.php'; ?>
	<script src="<?php echo asset_url('assets/js/script.min.js'); ?>"></script>
	<script src="<?php echo asset_url('assets/js/wishlist.js'); ?>"></script>
	<script src="<?php echo asset_url('assets/js/index.js'); ?>"></script>
	<?php if (!empty($influencer_reels)): ?>
	<script src="<?php echo asset_url('assets/js/index-influencer-reels.js'); ?>"></script>
	<?php endif; ?>
</body>

</html>