<?php
if (!function_exists('cart_count_from_cookie')) {
	require_once __DIR__ . '/../includes/cart_cookie.php';
}
if (!function_exists('wishlist_from_cookie')) {
	require_once __DIR__ . '/../includes/wishlist_cookie.php';
}
if (!function_exists('auth_cookie_user_id')) {
	require_once __DIR__ . '/../includes/auth_cookie.php';
}
$nav_cart_count = cart_count_from_cookie();
$nav_wishlist_count = count(wishlist_from_cookie());
$nav_logged_in = auth_cookie_user_id() !== null;
$nav_base = isset($base_path) ? $base_path : '';
$base_path = $nav_base;
require_once __DIR__ . '/../includes/cart_for_drawer.php';
$nav_parent_categories = [];
$nav_store_collections = [];
if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
	require_once __DIR__ . '/../config/db.php';
}
$nav_pdo = isset($pdo) && $pdo instanceof PDO ? $pdo : ($GLOBALS['pdo'] ?? null);
if ($nav_pdo instanceof PDO) {
	if (!function_exists('category_list_parents_for_store')) {
		require_once __DIR__ . '/../lib/category.php';
	}
	$nav_parent_categories = category_list_parents_for_store($nav_pdo);
	if (!function_exists('collection_list_for_store')) {
		require_once __DIR__ . '/../lib/collection.php';
	}
	$nav_store_collections = collection_list_for_store($nav_pdo);
}
?>
<?php include __DIR__ . '/site_marquee.php'; ?>
<nav class="site-navbar site-navbar--desktop-bar navbar navbar-expand-lg navbar-pearlica align-items-center" role="navigation">
	<div class="container-fluid navbar-pearlica-inner">
		<div class="d-flex flex-wrap align-items-center justify-content-between w-100 gap-2 min-w-0 navbar-pearlica-toprow">
			<a class="navbar-brand navbar-brand-logo flex-shrink-0 order-1 order-lg-1" href="<?php echo $nav_base ? $nav_base . 'index' : '.'; ?>">
				<img class="navbar-logo-img" src="<?php echo $nav_base; ?>assets/images/black-branding.png" alt="Pearlica">
			</a>

			<div class="navbar-search-slot navbar-search-slot--desktop order-3 order-lg-2 min-w-0 px-0 d-none d-lg-flex align-items-center justify-content-center">
				<?php
				$navbar_search_input_id = 'navbar-desktop-search-input';
				$navbar_search_animated_placeholder = true;
				include __DIR__ . '/navbar_search.php';
				unset($navbar_search_input_id, $navbar_search_animated_placeholder, $navbar_placeholder_phrases);
				?>
			</div>

			<div class="d-none d-lg-flex flex-grow-1 justify-content-center align-items-center mx-2 min-w-0 order-lg-3">
				<?php
				$navbar_nav_ul_extra_classes = 'flex-row align-items-center justify-content-center flex-wrap gap-1 gap-md-4 mb-0';
				include __DIR__ . '/navbar_primary_nav_ul.php';
				?>
			</div>

			<div class="d-none d-lg-flex align-items-center justify-content-end flex-shrink-0 order-lg-4">
				<?php
				$nav_icons_context = 'desktop';
				include __DIR__ . '/navbar_toolbar_icons.php';
				?>
			</div>

			<button class="navbar-toggler navbar-toggler-pearlica d-lg-none flex-shrink-0 border-0 order-2 ms-auto" type="button" data-bs-toggle="offcanvas"
				data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Open menu">
				<span class="navbar-toggler-icon"></span>
			</button>
		</div>
	</div>

	<div class="offcanvas offcanvas-end offcanvas-navbar-mobile d-lg-none" tabindex="-1" id="offcanvasNavbar"
		aria-labelledby="offcanvasNavbarLabel">
		<div class="offcanvas-header border-bottom align-items-center">
			<h5 class="offcanvas-title offcanvas-title--navbar mb-0" id="offcanvasNavbarLabel">Menu</h5>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
		</div>
		<div class="offcanvas-body d-flex flex-column pt-3">
			<div class="navbar-offcanvas-toolbar mb-4">
				<?php
				$nav_icons_context = 'mobile';
				include __DIR__ . '/navbar_toolbar_icons.php';
				?>
			</div>
			<?php
			$navbar_nav_ul_extra_classes = 'flex-column align-items-stretch gap-0 w-100 offcanvas-nav-pearlica mb-0';
			include __DIR__ . '/navbar_primary_nav_ul.php';
			?>
			<?php if ($nav_logged_in): ?>
			<div class="offcanvas-account-links border-top mt-4 pt-3">
				<p class="navbar-offcanvas-label small mb-2">Account</p>
				<ul class="list-unstyled mb-0">
					<li><a class="nav-link nav-link-pearlica px-0 py-2" href="<?php echo $nav_base; ?>orders">My Orders</a></li>
					<li><a class="nav-link nav-link-pearlica px-0 py-2 text-muted" href="<?php echo $nav_base; ?>logout">Logout</a></li>
				</ul>
			</div>
			<?php endif; ?>
		</div>
	</div>
</nav>
<!-- Desktop search modal (with suggestions): centered, not full-screen -->
<div class="modal fade" id="siteSearchModal" tabindex="-1" aria-labelledby="siteSearchModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content border-0 shadow">
			<div class="modal-header border-bottom">
				<h5 class="modal-title modal-title--navbar-search mb-0" id="siteSearchModalLabel">Search</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body pt-3 pb-4">
				<?php include __DIR__ . '/navbar_search.php'; ?>
			</div>
		</div>
	</div>
</div>
<?php include __DIR__ . '/cart_drawer.php'; ?>
<?php include __DIR__ . '/mobile_bottom_nav.php'; ?>
