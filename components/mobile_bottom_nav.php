<?php
declare(strict_types=1);
/**
 * Fixed bottom navigation (phones only). Expects same context as navbar: $nav_base, $nav_cart_count, $nav_parent_categories, $nav_logged_in.
 */
if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}
$mb_base = isset($nav_base) ? (string) $nav_base : '';
$mb_cart = isset($nav_cart_count) ? (int) $nav_cart_count : 0;
$mb_cats = isset($nav_parent_categories) && is_array($nav_parent_categories) ? $nav_parent_categories : [];
$mb_logged_in = !empty($nav_logged_in);
$mb_trim = trim($mb_base, '/');
$mb_search_suggest = ($mb_trim === '' ? '' : '/' . $mb_trim) . '/search-suggest';
?>
<header class="mobile-top-logo-bar d-lg-none" role="banner">
	<a href="<?php echo h($mb_base); ?>index" class="mobile-top-logo-bar__link" aria-label="Pearlica home">
		<img src="<?php echo h($mb_base); ?>assets/images/black-branding.png" alt="Pearlica" class="mobile-top-logo-bar__img" width="140" height="40" loading="eager" decoding="async">
	</a>
</header>
<div class="mobile-bottom-dock d-lg-none" id="mobile-bottom-dock" role="navigation" aria-label="Main mobile">
	<a href="<?php echo h($mb_base); ?>index" class="mobile-bottom-dock__item" data-mobile-dock="home">
		<span class="mobile-bottom-dock__icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
		</span>
		<span class="mobile-bottom-dock__label">Home</span>
	</a>
	<a href="<?php echo h($mb_base); ?>products" class="mobile-bottom-dock__item" data-mobile-dock="shop">
		<span class="mobile-bottom-dock__icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
		</span>
		<span class="mobile-bottom-dock__label">Shop</span>
	</a>
	<a href="#" class="mobile-bottom-dock__item cart-drawer-trigger" data-mobile-dock="cart" aria-label="<?php echo $mb_cart > 0 ? 'Cart, ' . $mb_cart . ' items' : 'Cart'; ?>">
		<span class="mobile-bottom-dock__icon mobile-bottom-dock__icon--cart" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 -960 960 960" fill="currentColor"><path d="M236-102.21q-21-21.21-21-51T236.21-204q21.21-21 51-21T338-203.79q21 21.21 21 51T337.79-102q-21.21 21-51 21T236-102.21Zm400 0q-21-21.21-21-51T636.21-204q21.21-21 51-21T738-203.79q21 21.21 21 51T737.79-102q-21.21 21-51 21T636-102.21ZM235-741l110 228h288l125-228H235Zm-30-60h589.07q22.97 0 34.95 21 11.98 21-.02 42L694-495q-11 19-28.56 30.5T627-453H324l-56 104h491v60H277q-42 0-60.5-28t.5-63l64-118-152-322H51v-60h117l37 79Zm140 288h288-288Z"/></svg>
			<span class="nav-cart-badge mobile-bottom-dock__badge"<?php echo $mb_cart > 0 ? '' : ' style="display:none"'; ?> aria-hidden="true"><?php echo (int) $mb_cart; ?></span>
		</span>
		<span class="mobile-bottom-dock__label">Cart</span>
	</a>
	<button type="button" class="mobile-bottom-dock__item mobile-bottom-dock__item--btn" data-mobile-dock-open="search" aria-controls="mobile-search-sheet" aria-expanded="false" aria-label="Search products">
		<span class="mobile-bottom-dock__icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
		</span>
		<span class="mobile-bottom-dock__label">Search</span>
	</button>
	<button type="button" class="mobile-bottom-dock__item mobile-bottom-dock__item--btn" data-mobile-dock-open="more" aria-controls="mobile-more-sheet" aria-expanded="false" aria-label="More menu">
		<span class="mobile-bottom-dock__icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
		</span>
		<span class="mobile-bottom-dock__label">More</span>
	</button>
</div>

<div class="mobile-search-drawer d-lg-none" id="mobile-search-sheet" role="dialog" aria-modal="true" aria-labelledby="mobile-search-sheet-title" aria-hidden="true">
	<div class="mobile-search-drawer-overlay" data-mobile-dock-close tabindex="-1" aria-hidden="true"></div>
	<div class="mobile-search-drawer-panel">
		<div class="cart-drawer-header">
			<h2 class="cart-drawer-title" id="mobile-search-sheet-title">Search</h2>
			<button type="button" class="cart-drawer-close-btn" data-mobile-dock-close aria-label="Close search">&times;</button>
		</div>
		<div class="cart-drawer-panel-inner">
			<div class="site-search-wrap w-100 px-0 pb-0" data-site-search-wrap>
				<div class="site-search position-relative mx-auto" data-site-search data-search-url="<?php echo h($mb_search_suggest); ?>">
					<label class="visually-hidden" for="mobile-site-search-input">Search products</label>
					<input type="search" id="mobile-site-search-input" class="site-search-input form-control" name="q" autocomplete="off" placeholder="Rings, pendants, chains…" maxlength="80" data-site-search-input>
					<div class="site-search-dropdown list-group list-group-flush shadow-sm d-none" data-site-search-dropdown role="listbox" aria-label="Search suggestions"></div>
				</div>
			</div>
			<ul class="mobile-dock-chip-grid mobile-search-chip-grid" aria-label="Browse categories">
				<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>products">All products</a></li>
				<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>collections">Collections</a></li>
				<?php foreach ($mb_cats as $cat): ?>
					<li><a class="mobile-dock-chip" href="<?php echo h($mb_base . 'products?category=' . (int) ($cat['id'] ?? 0)); ?>"><?php echo h((string) ($cat['name'] ?? '')); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>

<div class="mobile-more-drawer d-lg-none" id="mobile-more-sheet" role="dialog" aria-modal="true" aria-labelledby="mobile-more-sheet-title" aria-hidden="true">
	<div class="mobile-more-drawer-overlay" data-mobile-dock-close tabindex="-1" aria-hidden="true"></div>
	<div class="mobile-more-drawer-panel">
		<div class="cart-drawer-header">
			<h2 class="cart-drawer-title" id="mobile-more-sheet-title">More</h2>
			<button type="button" class="cart-drawer-close-btn" data-mobile-dock-close aria-label="Close menu">&times;</button>
		</div>
		<div class="cart-drawer-panel-inner">
			<div class="mobile-more-section" aria-labelledby="mobile-more-categories-title">
				<p class="mobile-more-section__title" id="mobile-more-categories-title">Categories</p>
				<ul class="mobile-dock-chip-grid mobile-more-chip-grid" aria-label="Categories">
					<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>products">All products</a></li>
					<?php foreach ($mb_cats as $cat): ?>
						<li><a class="mobile-dock-chip" href="<?php echo h($mb_base . 'products?category=' . (int) ($cat['id'] ?? 0)); ?>"><?php echo h((string) ($cat['name'] ?? '')); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="mobile-more-section" aria-labelledby="mobile-more-options-title">
				<p class="mobile-more-section__title" id="mobile-more-options-title">More options</p>
				<ul class="mobile-dock-chip-grid mobile-more-chip-grid" aria-label="More options">
					<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>wishlist">Wishlist</a></li>
					<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>gifting">Gifting</a></li>
					<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>about">About</a></li>
					<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>blogs">Blog</a></li>
					<?php if ($mb_logged_in): ?>
						<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>profile">My profile</a></li>
						<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>orders">My orders</a></li>
						<li><a class="mobile-dock-chip mobile-dock-chip--quiet" href="<?php echo h($mb_base); ?>logout">Logout</a></li>
					<?php else: ?>
						<li><a class="mobile-dock-chip" href="<?php echo h($mb_base); ?>login">Login</a></li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</div>
</div>
