<?php
/**
 * Cart drawer: overlay + slide-out panel. Include after navbar.
 * Expects $cart_items, $cart_total, $base_path, $uploads_base (set by cart_for_drawer.php or page).
 */
$drawer_base = isset($base_path) ? $base_path : '';
?>
<div id="cart-drawer" class="cart-drawer" aria-hidden="true">
	<div class="cart-drawer-overlay" aria-label="Close cart"></div>
	<div class="cart-drawer-panel">
		<div class="cart-drawer-header">
			<h2 class="cart-drawer-title">Your Cart</h2>
			<button type="button" class="cart-drawer-close-btn" aria-label="Close cart">&times;</button>
		</div>
		<div class="cart-drawer-panel-inner" id="cart-drawer-replace">
			<?php include __DIR__ . '/cart_drawer_content.php'; ?>
		</div>
	</div>
</div>
