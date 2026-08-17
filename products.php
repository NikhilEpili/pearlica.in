<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/product.php';
require_once __DIR__ . '/lib/category.php';
require_once __DIR__ . '/includes/wishlist_cookie.php';

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path_only = parse_url($req_uri, PHP_URL_PATH);
$path_only = $path_only ?: '/';
$parts = array_values(array_filter(explode('/', $path_only)));
if (count($parts) <= 1) {
	$base_path = '';
} else {
	array_pop($parts);
	$base_path = '/' . implode('/', $parts) . '/';
}
$uploads_base = pearlica_uploads_products_base($base_path);

require_once __DIR__ . '/includes/shop_list_filters.php';
require_once __DIR__ . '/includes/shop_product_helpers.php';

$pdo = $GLOBALS['pdo'];
$per_page = (int) (ITEMS_PER_PAGE ?? 12);
$page = max(1, (int) ($_GET['page'] ?? 1));
$filter_state = products_ensure_random_seed(products_parse_filters_from_request());
$use_random_order = ($filter_state['sort'] === '' && $filter_state['q'] === '');

$list_filters = [
	'category_ids' => $filter_state['cat'],
	'gender' => $filter_state['gender'],
	'sort' => $filter_state['sort'],
	'min_price' => $filter_state['min_price'],
	'max_price' => $filter_state['max_price'],
	'silver_925' => $filter_state['silver'],
	'on_sale' => $filter_state['sale'],
	'in_stock' => $filter_state['stock'],
	'search' => $filter_state['q'],
	'metal_colors' => $filter_state['color'] ?? [],
	'random_order' => $use_random_order,
	'random_seed' => $use_random_order ? (int) $filter_state['rs'] : 0,
];

$result = product_list_store($pdo, $page, $per_page, $list_filters);
$products = $result['items'];
$total = $result['total'];
$total_pages = $total > 0 ? (int) ceil($total / $per_page) : 0;
$categories = category_list_for_store($pdo);
$wishlist_ids = array_keys(wishlist_from_cookie());

$store_parent_names = [];
$store_children_by_parent = [];
foreach ($categories as $cat_row) {
	$cat_id = (int) ($cat_row['id'] ?? 0);
	if ($cat_id <= 0) {
		continue;
	}
	$parent_id = (int) ($cat_row['parent_id'] ?? 0);
	if ($parent_id > 0) {
		if (!isset($store_children_by_parent[$parent_id])) {
			$store_children_by_parent[$parent_id] = [];
		}
		$store_children_by_parent[$parent_id][] = [
			'id' => $cat_id,
			'name' => (string) ($cat_row['name'] ?? ''),
		];
		continue;
	}
	$store_parent_names[$cat_id] = (string) ($cat_row['name'] ?? '');
}

$subfilter_parent_ids = [];
foreach ($store_children_by_parent as $parent_id => $child_rows) {
	if ($child_rows === []) {
		continue;
	}
	if (!category_filter_covers_parent($pdo, $filter_state['cat'], (int) $parent_id)) {
		continue;
	}
	$subfilter_parent_ids[] = (int) $parent_id;
}
usort($subfilter_parent_ids, static function (int $a, int $b) use ($store_parent_names): int {
	$name_a = strtolower($store_parent_names[$a] ?? '');
	$name_b = strtolower($store_parent_names[$b] ?? '');

	return $name_a <=> $name_b;
});

$listing_heading = 'All Products';
$cat_ids = $filter_state['cat'] ?? [];
if (!empty($cat_ids) && is_array($cat_ids)) {
	$id_to_name = [];
	foreach ($categories as $c) {
		$cid = (int) ($c['id'] ?? 0);
		if ($cid > 0) {
			$id_to_name[$cid] = (string) ($c['name'] ?? '');
		}
	}
	$names = [];
	foreach ($cat_ids as $cid) {
		$cid = (int) $cid;
		if ($cid <= 0) {
			continue;
		}
		if (isset($id_to_name[$cid]) && $id_to_name[$cid] !== '') {
			$names[] = $id_to_name[$cid];
			continue;
		}
		$row = category_find_by_id($pdo, $cid);
		if ($row && !empty($row['name'])) {
			$names[] = (string) $row['name'];
		}
	}
	if ($names !== []) {
		$listing_heading = count($names) === 1 ? $names[0] : implode(', ', $names);
	}
}

$products_list_qs = products_filters_to_query($filter_state);
$wishlist_return = 'products' . ($products_list_qs !== '' ? '?' . $products_list_qs : '');

$start_item = $total > 0 ? ($page - 1) * $per_page + 1 : 0;
$end_item = min($page * $per_page, $total);

$page_title = 'Shop | Pearlica 925 Silver Jewellery';
if ($listing_heading !== 'All Products') {
	$meta_title_full = $listing_heading . ' | Pearlica 925 Silver Jewellery';
}
?>
<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h(asset_url('assets/css/products.css', $base_path)); ?>">

<body class="products-page">

	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main class="products-main">
		<section id="products-listing" class="products-section">
			<div class="products-filter-backdrop" id="products-filter-backdrop" aria-hidden="true"></div>
			<?php
			$price_slider_max = 200000;
			$cur_min = $filter_state['min_price'] !== null ? (int) $filter_state['min_price'] : 0;
			$cur_max = $filter_state['max_price'] !== null ? (int) $filter_state['max_price'] : $price_slider_max;
			$cur_max = min($price_slider_max, max($cur_min, $cur_max));
			$color_sel = $filter_state['color'] ?? [];
			?>
			<aside class="products-filter-drawer" id="products-filter-drawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="products-filter-drawer-title">
					<div class="products-filter-drawer__header">
						<div>
							<p class="products-sidebar-label mb-0" id="products-filter-drawer-title">Filters</p>
							<p class="products-filter-drawer__subtitle mb-0">Refine by price, colour, category, and more — apply when ready.</p>
						</div>
						<button type="button" class="products-filter-drawer__close" id="products-filter-close" aria-label="Close filters">&times;</button>
					</div>
					<div class="products-filter-drawer__main">
						<form class="products-filter-form" method="get" action="<?php echo h($base_path . 'products'); ?>" id="products-filter-form">
						<div class="products-filter-drawer__body">
							<div class="products-filter-grid">
								<div class="products-filter-grid__cell products-filter-grid__full">
									<div class="products-filter-group">
										<button type="button" class="products-filter-head" aria-expanded="<?php echo ($filter_state['min_price'] !== null || $filter_state['max_price'] !== null) ? 'true' : 'false'; ?>" data-filter="price">
											<span>By price</span>
											<span class="products-filter-toggle"><?php echo ($filter_state['min_price'] !== null || $filter_state['max_price'] !== null) ? '−' : '+'; ?></span>
										</button>
										<div class="products-filter-body <?php echo ($filter_state['min_price'] === null && $filter_state['max_price'] === null) ? 'products-filter-body--collapsed' : ''; ?>" id="filter-price">
											<p class="products-filter-hint">Uses sale price when a discount applies.</p>
											<div class="products-price-range-visual" data-price-max="<?php echo (int) $price_slider_max; ?>">
												<label class="products-price-range-label" for="products-price-range-min">Min <span class="products-price-range-val" data-range-min-display>₹<?php echo number_format($cur_min); ?></span></label>
												<input type="range" class="products-price-range" id="products-price-range-min" min="0" max="<?php echo (int) $price_slider_max; ?>" step="500" value="<?php echo (int) $cur_min; ?>" aria-label="Minimum price slider">
												<label class="products-price-range-label" for="products-price-range-max">Max <span class="products-price-range-val" data-range-max-display>₹<?php echo $filter_state['max_price'] !== null ? number_format($cur_max) : 'Any'; ?></span></label>
												<input type="range" class="products-price-range" id="products-price-range-max" min="0" max="<?php echo (int) $price_slider_max; ?>" step="500" value="<?php echo (int) $cur_max; ?>" aria-label="Maximum price slider">
											</div>
											<div class="products-filter-price-row">
												<input type="number" class="products-filter-input" id="products-min-price-input" name="min_price" placeholder="Min" min="0" step="100" aria-label="Minimum price"
													value="<?php echo $filter_state['min_price'] !== null ? h((string) (int) $filter_state['min_price']) : ''; ?>">
												<input type="number" class="products-filter-input" id="products-max-price-input" name="max_price" placeholder="Max" min="0" step="100" aria-label="Maximum price"
													value="<?php echo $filter_state['max_price'] !== null ? h((string) (int) $filter_state['max_price']) : ''; ?>">
											</div>
										</div>
									</div>
								</div>
								<div class="products-filter-grid__cell products-filter-grid__full">
									<div class="products-filter-divider products-filter-divider--in-grid"></div>
									<div class="products-filter-group">
										<button type="button" class="products-filter-head" aria-expanded="<?php echo $color_sel !== [] ? 'true' : 'false'; ?>" data-filter="color">
											<span>By color</span>
											<span class="products-filter-toggle"><?php echo $color_sel !== [] ? '−' : '+'; ?></span>
										</button>
										<div class="products-filter-body <?php echo $color_sel === [] ? 'products-filter-body--collapsed' : ''; ?>" id="filter-color">
											<label class="products-filter-check">
												<input type="checkbox" name="color[]" value="silver" <?php echo in_array('silver', $color_sel, true) ? 'checked' : ''; ?>>
												<span class="products-filter-checkbox"></span>
												<span class="products-filter-check-label">Silver</span>
											</label>
											<label class="products-filter-check">
												<input type="checkbox" name="color[]" value="rose_gold" <?php echo in_array('rose_gold', $color_sel, true) ? 'checked' : ''; ?>>
												<span class="products-filter-checkbox"></span>
												<span class="products-filter-check-label">Rose gold</span>
											</label>
										</div>
									</div>
								</div>
								<?php foreach ($subfilter_parent_ids as $subfilter_parent_id): ?>
									<?php
									$subfilter_children = $store_children_by_parent[$subfilter_parent_id] ?? [];
									$subfilter_parent_name = $store_parent_names[$subfilter_parent_id] ?? '';
									if ($subfilter_children === []) {
										continue;
									}
									if ($subfilter_parent_name === '') {
										$subfilter_parent_row = category_find_by_id($pdo, $subfilter_parent_id);
										$subfilter_parent_name = $subfilter_parent_row['name'] ?? 'Category';
									}
									?>
								<div class="products-filter-grid__cell products-filter-grid__full">
									<div class="products-filter-divider products-filter-divider--in-grid"></div>
									<div class="products-filter-group">
										<button type="button" class="products-filter-head" aria-expanded="true" data-filter="subcat-parent-<?php echo (int) $subfilter_parent_id; ?>">
											<span>By categories (<?php echo h((string) $subfilter_parent_name); ?>)</span>
											<span class="products-filter-toggle">−</span>
										</button>
										<div class="products-filter-body" id="filter-subcat-parent-<?php echo (int) $subfilter_parent_id; ?>">
											<?php foreach ($subfilter_children as $subfilter_child): ?>
												<label class="products-filter-check">
													<input type="checkbox" name="cat[]" value="<?php echo (int) ($subfilter_child['id'] ?? 0); ?>"
														<?php echo in_array((int) ($subfilter_child['id'] ?? 0), $filter_state['cat'], true) ? 'checked' : ''; ?>>
													<span class="products-filter-checkbox"></span>
													<span class="products-filter-check-label"><?php echo h((string) ($subfilter_child['name'] ?? '')); ?></span>
												</label>
											<?php endforeach; ?>
										</div>
									</div>
								</div>
								<?php endforeach; ?>
								<div class="products-filter-grid__cell products-filter-grid__full">
									<div class="products-filter-divider products-filter-divider--in-grid"></div>
									<div class="products-filter-group">
										<button type="button" class="products-filter-head" aria-expanded="true" data-filter="shopfor">
											<span>Shop for</span>
											<span class="products-filter-toggle">−</span>
										</button>
										<div class="products-filter-body" id="filter-shopfor">
											<label class="products-filter-check">
												<input type="radio" name="gender" value="all" <?php echo $filter_state['gender'] === '' ? 'checked' : ''; ?>>
												<span class="products-filter-checkbox products-filter-radio"></span>
												<span class="products-filter-check-label">All</span>
											</label>
											<label class="products-filter-check">
												<input type="radio" name="gender" value="women" <?php echo $filter_state['gender'] === 'women' ? 'checked' : ''; ?>>
												<span class="products-filter-checkbox products-filter-radio"></span>
												<span class="products-filter-check-label">Women’s</span>
											</label>
											<label class="products-filter-check">
												<input type="radio" name="gender" value="men" <?php echo $filter_state['gender'] === 'men' ? 'checked' : ''; ?>>
												<span class="products-filter-checkbox products-filter-radio"></span>
												<span class="products-filter-check-label">Men’s</span>
											</label>
										</div>
									</div>
								</div>
								<div class="products-filter-grid__cell products-filter-grid__full">
									<div class="products-filter-divider products-filter-divider--in-grid"></div>
									<div class="products-filter-group">
										<button type="button" class="products-filter-head" aria-expanded="<?php echo $filter_state['q'] !== '' ? 'true' : 'false'; ?>" data-filter="search">
											<span>Search</span>
											<span class="products-filter-toggle"><?php echo $filter_state['q'] !== '' ? '−' : '+'; ?></span>
										</button>
										<div class="products-filter-body <?php echo $filter_state['q'] === '' ? 'products-filter-body--collapsed' : ''; ?>" id="filter-search">
											<label class="visually-hidden" for="products-filter-q">Search products</label>
											<input type="search" id="products-filter-q" class="products-filter-input products-filter-input--full" name="q" placeholder="Product name or SKU" value="<?php echo h($filter_state['q']); ?>" autocomplete="off" maxlength="80" aria-label="Search products">
										</div>
									</div>
								</div>
								<div class="products-filter-grid__cell products-filter-grid__full">
									<div class="products-filter-divider products-filter-divider--in-grid"></div>
									<div class="products-filter-group">
										<button type="button" class="products-filter-head" aria-expanded="<?php echo ($filter_state['silver'] || $filter_state['sale'] || $filter_state['stock']) ? 'true' : 'false'; ?>" data-filter="more">
											<span>More</span>
											<span class="products-filter-toggle"><?php echo ($filter_state['silver'] || $filter_state['sale'] || $filter_state['stock']) ? '−' : '+'; ?></span>
										</button>
										<div class="products-filter-body <?php echo ($filter_state['silver'] || $filter_state['sale'] || $filter_state['stock']) ? '' : 'products-filter-body--collapsed'; ?>" id="filter-more">
											<label class="products-filter-check">
												<input type="checkbox" name="silver" value="1" <?php echo $filter_state['silver'] ? 'checked' : ''; ?>>
												<span class="products-filter-checkbox"></span>
												<span class="products-filter-check-label">925 / sterling silver</span>
											</label>
											<label class="products-filter-check">
												<input type="checkbox" name="sale" value="1" <?php echo $filter_state['sale'] ? 'checked' : ''; ?>>
												<span class="products-filter-checkbox"></span>
												<span class="products-filter-check-label">On sale</span>
											</label>
											<label class="products-filter-check">
												<input type="checkbox" name="stock" value="1" <?php echo $filter_state['stock'] ? 'checked' : ''; ?>>
												<span class="products-filter-checkbox"></span>
												<span class="products-filter-check-label">In stock only</span>
											</label>
										</div>
									</div>
								</div>
								<div class="products-filter-grid__cell products-filter-grid__full">
									<div class="products-filter-divider products-filter-divider--in-grid"></div>
									<div class="products-filter-group products-filter-group--sort">
										<p class="products-filter-sort-heading">Sort by</p>
										<label class="visually-hidden" for="products-filter-sort">Sort products</label>
										<div class="products-filter-sort-wrap">
											<select id="products-filter-sort" class="products-filter-sort-select" name="sort" aria-label="Sort products">
												<?php $sort_sel = $filter_state['sort']; ?>
												<option value="" <?php echo $sort_sel === '' ? 'selected' : ''; ?>>Featured</option>
												<option value="newest" <?php echo $sort_sel === 'newest' ? 'selected' : ''; ?>>Newest</option>
												<option value="price-asc" <?php echo $sort_sel === 'price-asc' ? 'selected' : ''; ?>>Price: Low to High</option>
												<option value="price-desc" <?php echo $sort_sel === 'price-desc' ? 'selected' : ''; ?>>Price: High to Low</option>
											</select>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="products-filter-actions">
							<button type="submit" class="products-filter-apply">Apply filters</button>
							<a href="<?php echo h($base_path); ?>products" class="products-filter-clear">Clear all</a>
						</div>
						</form>
					</div>
				</aside>

			<div class="products-page-content">
				<p class="products-label">Collection</p>
				<div class="products-heading-row">
					<div class="products-heading-bar">
						<div class="products-heading-text">
							<h1 class="products-title"><?php echo h($listing_heading); ?></h1>
							<p class="products-result-count products-result-count--below-title">
								<?php if ($total > 0): ?>
									Showing <?php echo $start_item; ?>–<?php echo $end_item; ?> of <?php echo $total; ?> pieces
								<?php else: ?>
									0 pieces
								<?php endif; ?>
							</p>
						</div>
						<div class="products-heading-actions">
							<button type="button" class="products-filter-open" id="products-filter-open"
								aria-label="Open filters" aria-controls="products-filter-drawer" aria-expanded="false">Filter</button>
							<?php if (!empty($products)): ?>
								<div class="products-grid-toggle d-none d-lg-flex align-items-stretch">
									<button type="button" class="products-grid-btn is-active" data-cols="4" aria-label="4 column grid">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="7" height="7" rx="0"/><rect x="14" y="3" width="7" height="7" rx="0"/><rect x="3" y="14" width="7" height="7" rx="0"/><rect x="14" y="14" width="7" height="7" rx="0"/></svg>
									</button>
									<button type="button" class="products-grid-btn" data-cols="2" aria-label="2 column grid">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="8" height="18" rx="0"/><rect x="13" y="3" width="8" height="18" rx="0"/></svg>
									</button>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<?php if (empty($products)): ?>
					<?php
					$has_active_filters = $filter_state['cat'] !== [] || $filter_state['gender'] !== '' || $filter_state['min_price'] !== null
						|| $filter_state['max_price'] !== null || $filter_state['silver'] || $filter_state['sale'] || $filter_state['stock']
						|| $filter_state['q'] !== '' || $filter_state['sort'] !== '' || (!empty($filter_state['color']));
					?>
					<div class="products-empty">
						<p class="products-empty-text">No products found.</p>
						<?php if ($has_active_filters): ?>
							<p class="products-empty-hint"><a href="<?php echo h($base_path); ?>products">Clear filters</a> or try different criteria.</p>
						<?php endif; ?>
						<a href="<?php echo h($base_path); ?>index" class="products-btn-back">Back to Home</a>
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

							<?php if ($total_pages > 1): ?>
								<nav class="products-pagination-wrap" aria-label="Products pagination">
									<ul class="products-pagination">
										<?php
										$pagination_qs = products_filters_to_query($filter_state);
										$pagination_prefix = $base_path . 'products' . ($pagination_qs === '' ? '?' : '?' . $pagination_qs . '&');
										?>
										<?php if ($page > 1): ?>
											<li>
												<a class="products-page-btn" href="<?php echo h($pagination_prefix); ?>page=<?php echo $page - 1; ?>">Previous</a>
											</li>
										<?php endif; ?>
										<?php for ($i = 1; $i <= $total_pages; $i++): ?>
											<li>
												<a class="products-page-btn <?php echo $i === $page ? 'is-active' : ''; ?>" href="<?php echo h($pagination_prefix); ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
											</li>
										<?php endfor; ?>
										<?php if ($page < $total_pages): ?>
											<li>
												<a class="products-page-btn" href="<?php echo h($pagination_prefix); ?>page=<?php echo $page + 1; ?>">Next</a>
											</li>
										<?php endif; ?>
									</ul>
								</nav>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>
	</main>

	<?php include __DIR__ . '/components/footer.php'; ?>

	<script src="<?php echo h(asset_url('assets/js/jquery.min.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/plugins.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/SmoothScroll.js', $base_path)); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe"
		crossorigin="anonymous"></script>
	<?php include __DIR__ . '/components/swiper_bundle_script.php'; ?>
	<script src="<?php echo h(asset_url('assets/js/script.min.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/wishlist.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/products.js', $base_path)); ?>"></script>
</body>

</html>
