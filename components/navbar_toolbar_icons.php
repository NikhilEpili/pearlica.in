<?php
/**
 * Wishlist, cart, account icons.
 * Set $nav_icons_context to 'desktop' or 'mobile' before include.
 */
$nav_icons_context = isset($nav_icons_context) ? (string) $nav_icons_context : 'desktop';
$toolbar_modifier = $nav_icons_context === 'mobile' ? ' nav-icons-pearlica--offcanvas' : '';
?>
<ul class="nav-icons nav-icons-pearlica list-unstyled d-flex m-0 align-items-center<?php echo htmlspecialchars($toolbar_modifier, ENT_QUOTES, 'UTF-8'); ?>">
	<li class="nav-icons-item">
		<a href="<?php echo $nav_base; ?>wishlist" class="nav-icon-link nav-icon-link-pearlica position-relative" aria-label="Wishlist">
			<svg class="nav-icon-svg nav-icon-svg-pearlica nav-icon-heart" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true"><path d="m480-121-41-37q-105.77-97.12-174.88-167.56Q195-396 154-451.5T96.5-552Q80-597 80-643q0-90.15 60.5-150.58Q201-854 290-854q57 0 105.5 27t84.5 78q42-54 89-79.5T670-854q89 0 149.5 60.42Q880-733.15 880-643q0 46-16.5 91T806-451.5Q765-396 695.88-325.56 626.77-255.12 521-158l-41 37Zm0-79q101.24-93 166.62-159.5Q712-426 750.5-476t54-89.14q15.5-39.13 15.5-77.72 0-66.14-42-108.64T670.22-794q-51.52 0-95.37 31.5T504-674h-49q-26-56-69.85-88-43.85-32-95.37-32Q224-794 182-751.5t-42 108.82q0 38.68 15.5 78.18 15.5 39.5 54 90T314-358q66 66 166 158Zm0-297Z"/></svg>
			<?php if ($nav_wishlist_count > 0): ?><span class="nav-cart-badge"><?php echo (int) $nav_wishlist_count; ?></span><?php endif; ?>
		</a>
	</li>
	<li class="nav-icons-item">
		<a href="#" class="nav-icon-link nav-icon-link-pearlica position-relative cart-drawer-trigger" aria-label="<?php echo $nav_cart_count > 0 ? 'Cart, ' . (int) $nav_cart_count . ' item(s)' : 'Cart'; ?>">
			<svg class="nav-icon-svg nav-icon-svg-pearlica" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true"><path d="M236-102.21q-21-21.21-21-51T236.21-204q21.21-21 51-21T338-203.79q21 21.21 21 51T337.79-102q-21.21 21-51 21T236-102.21Zm400 0q-21-21.21-21-51T636.21-204q21.21-21 51-21T738-203.79q21 21.21 21 51T737.79-102q-21.21 21-51 21T636-102.21ZM235-741l110 228h288l125-228H235Zm-30-60h589.07q22.97 0 34.95 21 11.98 21-.02 42L694-495q-11 19-28.56 30.5T627-453H324l-56 104h491v60H277q-42 0-60.5-28t.5-63l64-118-152-322H51v-60h117l37 79Zm140 288h288-288Z"/></svg>
			<?php if ($nav_cart_count > 0): ?><span class="nav-cart-badge" aria-hidden="true"><?php echo (int) $nav_cart_count; ?></span><?php endif; ?>
		</a>
	</li>
	<li class="nav-icons-item nav-icons-item-user">
		<?php if ($nav_logged_in): ?>
			<?php if ($nav_icons_context === 'mobile'): ?>
		<a href="<?php echo $nav_base; ?>profile" class="nav-icon-link nav-icon-link-pearlica" aria-label="My profile">
			<svg class="nav-icon-svg nav-icon-svg-pearlica" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true"><path d="M222-255q63-44 125-67.5T480-346q71 0 133.5 23.5T739-255q44-54 62.5-109T820-480q0-145-97.5-242.5T480-820q-145 0-242.5 97.5T140-480q0 61 19 116t63 109Zm160.5-234.5Q343-529 343-587t39.5-97.5Q422-724 480-724t97.5 39.5Q617-645 617-587t-39.5 97.5Q538-450 480-450t-97.5-39.5ZM480-80q-82 0-155-31.5t-127.5-86Q143-252 111.5-325T80-480q0-83 31.5-155.5t86-127Q252-817 325-848.5T480-880q83 0 155.5 31.5t127 86q54.5 54.5 86 127T880-480q0 82-31.5 155t-86 127.5q-54.5 54.5-127 86T480-80Zm107.5-76Q640-172 691-212q-51-36-104-55t-107-19q-54 0-107 19t-104 55q51 40 103.5 56T480-140q55 0 107.5-16Zm-52-375.5Q557-553 557-587t-21.5-55.5Q514-664 480-664t-55.5 21.5Q403-621 403-587t21.5 55.5Q446-510 480-510t55.5-21.5ZM480-587Zm0 374Z"/></svg>
		</a>
			<?php else: ?>
		<div class="nav-user-dropdown">
			<button type="button" class="nav-icon-btn nav-icon-link-pearlica nav-dropdown-trigger" id="navUserDropdown" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
				<svg class="nav-icon-svg nav-icon-svg-pearlica" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true"><path d="M222-255q63-44 125-67.5T480-346q71 0 133.5 23.5T739-255q44-54 62.5-109T820-480q0-145-97.5-242.5T480-820q-145 0-242.5 97.5T140-480q0 61 19 116t63 109Zm160.5-234.5Q343-529 343-587t39.5-97.5Q422-724 480-724t97.5 39.5Q617-645 617-587t-39.5 97.5Q538-450 480-450t-97.5-39.5ZM480-80q-82 0-155-31.5t-127.5-86Q143-252 111.5-325T80-480q0-83 31.5-155.5t86-127Q252-817 325-848.5T480-880q83 0 155.5 31.5t127 86q54.5 54.5 86 127T880-480q0 82-31.5 155t-86 127.5q-54.5 54.5-127 86T480-80Zm107.5-76Q640-172 691-212q-51-36-104-55t-107-19q-54 0-107 19t-104 55q51 40 103.5 56T480-140q55 0 107.5-16Zm-52-375.5Q557-553 557-587t-21.5-55.5Q514-664 480-664t-55.5 21.5Q403-621 403-587t21.5 55.5Q446-510 480-510t55.5-21.5ZM480-587Zm0 374Z"/></svg>
			</button>
			<ul class="nav-dropdown-panel nav-dropdown-panel--user" role="menu" aria-labelledby="navUserDropdown">
				<li><a class="nav-dropdown-link" href="<?php echo $nav_base; ?>profile">My Profile</a></li>
				<li><a class="nav-dropdown-link" href="<?php echo $nav_base; ?>orders">My Orders</a></li>
				<li><a class="nav-dropdown-link" href="<?php echo $nav_base; ?>wishlist">Wishlist</a></li>
				<li><a class="nav-dropdown-link nav-dropdown-link--logout" href="<?php echo $nav_base; ?>logout">Logout</a></li>
			</ul>
		</div>
			<?php endif; ?>
		<?php else: ?>
		<a href="<?php echo $nav_base; ?>login" class="nav-icon-link nav-icon-link-pearlica" aria-label="Login">
			<svg class="nav-icon-svg nav-icon-svg-pearlica" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true"><path d="M222-255q63-44 125-67.5T480-346q71 0 133.5 23.5T739-255q44-54 62.5-109T820-480q0-145-97.5-242.5T480-820q-145 0-242.5 97.5T140-480q0 61 19 116t63 109Zm160.5-234.5Q343-529 343-587t39.5-97.5Q422-724 480-724t97.5 39.5Q617-645 617-587t-39.5 97.5Q538-450 480-450t-97.5-39.5ZM480-80q-82 0-155-31.5t-127.5-86Q143-252 111.5-325T80-480q0-83 31.5-155.5t86-127Q252-817 325-848.5T480-880q83 0 155.5 31.5t127 86q54.5 54.5 86 127T880-480q0 82-31.5 155t-86 127.5q-54.5 54.5-127 86T480-80Zm107.5-76Q640-172 691-212q-51-36-104-55t-107-19q-54 0-107 19t-104 55q51 40 103.5 56T480-140q55 0 107.5-16Zm-52-375.5Q557-553 557-587t-21.5-55.5Q514-664 480-664t-55.5 21.5Q403-621 403-587t21.5 55.5Q446-510 480-510t55.5-21.5ZM480-587Zm0 374Z"/></svg>
		</a>
		<?php endif; ?>
	</li>
</ul>
