<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/product.php';
require_once __DIR__ . '/includes/wishlist_cookie.php';
require_once __DIR__ . '/includes/shop_list_filters.php';
require_once __DIR__ . '/includes/shop_product_helpers.php';

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
	'random_order' => $use_random_order,
	'random_seed' => $use_random_order ? (int) $filter_state['rs'] : 0,
	'is_gifting' => true,
];

$result = product_list_store($pdo, $page, $per_page, $list_filters);
$products = $result['items'];
$total = $result['total'];
$total_pages = $total > 0 ? (int) ceil($total / $per_page) : 0;
$wishlist_ids = array_keys(wishlist_from_cookie());

$gifting_list_qs = products_filters_to_query($filter_state);
$wishlist_return = 'gifting' . ($gifting_list_qs !== '' ? '?' . $gifting_list_qs : '');

$start_item = $total > 0 ? ($page - 1) * $per_page + 1 : 0;
$end_item = min($page * $per_page, $total);

$meta_title_full = 'Gifting | Pearlica 925 Silver Jewellery';
$meta_description = 'Curated 925 sterling silver jewellery perfect for gifting — rings, necklaces, earrings and more. Gift-ready quality from Pearlica.';
$meta_keywords = 'Pearlica, gifting, gift jewellery, 925 sterling silver, silver gifts, jewellery gifts India';
?>
<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h(asset_url('assets/css/products.css', $base_path)); ?>">

<body class="products-page gifting-page">

	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main class="products-main">
		<section id="gifting-listing" class="products-section">
			<div class="products-page-content">
				<p class="products-label">Shop</p>
				<div class="products-heading-row">
					<div class="products-heading-left">
						<h1 class="products-title">Gifting</h1>
					</div>
				</div>
				<p class="text-muted small mb-4" style="max-width: 36rem;">Pieces marked for gifting in the admin catalog appear here. Use search and sort to browse.</p>

				<form class="row g-2 align-items-end mb-4" method="get" action="<?php echo h($base_path . 'gifting'); ?>">
					<?php if ($filter_state['sort'] !== ''): ?>
						<input type="hidden" name="sort" value="<?php echo h($filter_state['sort']); ?>">
					<?php endif; ?>
					<?php if ($filter_state['silver']): ?>
						<input type="hidden" name="silver" value="1">
					<?php endif; ?>
					<?php if ($filter_state['sale']): ?>
						<input type="hidden" name="sale" value="1">
					<?php endif; ?>
					<?php if ($filter_state['stock']): ?>
						<input type="hidden" name="stock" value="1">
					<?php endif; ?>
					<div class="col-md-8">
						<label class="visually-hidden" for="gifting-q">Search gifting</label>
						<input type="search" class="form-control" id="gifting-q" name="q" placeholder="Search gifting collection…" value="<?php echo h($filter_state['q']); ?>" maxlength="80" autocomplete="off">
					</div>
					<div class="col-md-4">
						<button type="submit" class="btn btn-dark w-100 rounded-0">Search</button>
					</div>
				</form>

				<?php if (empty($products)): ?>
					<div class="products-empty">
						<p class="products-empty-text">No gifting products yet.</p>
						<p class="products-empty-hint">Mark products as gifting in the admin panel, or <a href="<?php echo h($base_path); ?>products">browse the full shop</a>.</p>
						<a href="<?php echo h($base_path); ?>index" class="products-btn-back">Back to Home</a>
					</div>
				<?php else: ?>
					<div class="products-toolbar products-toolbar--ruled">
						<span class="products-result-count">Showing <?php echo $start_item; ?>–<?php echo $end_item; ?> of <?php echo $total; ?> pieces</span>
						<div class="products-toolbar-right">
							<div class="products-sort-wrap select-wrap">
								<select id="products-sort" class="products-sort-select" aria-label="Sort by">
									<?php $sort_sel = $filter_state['sort']; ?>
									<option value="" <?php echo $sort_sel === '' ? 'selected' : ''; ?>>Sort By: Featured</option>
									<option value="newest" <?php echo $sort_sel === 'newest' ? 'selected' : ''; ?>>Newest</option>
									<option value="price-asc" <?php echo $sort_sel === 'price-asc' ? 'selected' : ''; ?>>Price: Low to High</option>
									<option value="price-desc" <?php echo $sort_sel === 'price-desc' ? 'selected' : ''; ?>>Price: High to Low</option>
								</select>
							</div>
							<div class="products-grid-toggle d-none d-lg-flex align-items-stretch">
								<button type="button" class="products-grid-btn is-active" data-cols="4" aria-label="4 column grid">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="7" height="7" rx="0"/><rect x="14" y="3" width="7" height="7" rx="0"/><rect x="3" y="14" width="7" height="7" rx="0"/><rect x="14" y="14" width="7" height="7" rx="0"/></svg>
								</button>
								<button type="button" class="products-grid-btn" data-cols="2" aria-label="2 column grid">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="8" height="18" rx="0"/><rect x="13" y="3" width="8" height="18" rx="0"/></svg>
								</button>
							</div>
						</div>
					</div>

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
								<nav class="products-pagination-wrap" aria-label="Gifting pagination">
									<ul class="products-pagination">
										<?php
										$pagination_qs = products_filters_to_query($filter_state);
										$pagination_prefix = $base_path . 'gifting' . ($pagination_qs === '' ? '?' : '?' . $pagination_qs . '&');
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
