<?php
/**
 * Cart drawer fragment — line items use the same markup/classes as checkout for identical UI.
 * Expects: $cart_items, $cart_total, $base_path, $uploads_base
 */
if (!function_exists('h')) {
	function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
$drawer_base = isset($base_path) ? $base_path : '';
$drawer_uploads = isset($uploads_base) ? $uploads_base : (function_exists('pearlica_uploads_products_base') ? pearlica_uploads_products_base($drawer_base === '' ? '/' : $drawer_base) : ($drawer_base . 'uploads/products'));
$drawer_items = isset($cart_items) ? $cart_items : [];
$drawer_total = isset($cart_total) ? (float) $cart_total : 0;
?>
<div id="cart-drawer-content">
	<?php if (empty($drawer_items)): ?>
		<div class="cart-drawer-empty">
			<p class="cart-drawer-empty-text">Your cart is empty.</p>
			<button type="button" class="cart-drawer-btn-ghost cart-drawer-close">Continue Shopping</button>
		</div>
	<?php else: ?>
		<div class="checkout-order-card cart-drawer-order-card">
			<?php foreach ($drawer_items as $item): ?>
				<?php
				$img_src = !empty($item['image_url']) ? $drawer_uploads . '/' . $item['image_url'] : 'https://placehold.co/120x120/f0eeeb/888?text=Product';
				$product_url = $drawer_base . 'product?slug=' . rawurlencode($item['slug']);
				$pid = (int) $item['id'];
				$disc_pct = (float) ($item['discount_percentage'] ?? $item['discount'] ?? 0);
				$item_meta = trim((string) ($item['category_name'] ?? '')) !== '' ? $item['category_name'] : '925 Sterling Silver';
				?>
				<div class="checkout-order-item cart-item-card" data-product-id="<?php echo $pid; ?>">
					<div class="checkout-item-img-wrap">
						<a href="<?php echo h($product_url); ?>">
							<img src="<?php echo h($img_src); ?>" alt="<?php echo h($item['name']); ?>" loading="lazy">
						</a>
					</div>
					<div class="checkout-item-details">
						<a href="<?php echo h($product_url); ?>" class="checkout-item-name"><?php echo h($item['name']); ?></a>
						<div class="checkout-item-meta">
							<?php echo h($item_meta); ?><?php if ($disc_pct > 0): ?> · <?php endif; ?>
							<?php if ($disc_pct > 0): ?>
								<span class="checkout-item-off-badge"><?php echo (int) $disc_pct; ?>% off</span>
							<?php endif; ?>
						</div>
						<div class="cart-drawer-item-controls">
						<div class="checkout-item-unit-row">
							<span class="checkout-item-unit-sale<?php echo $disc_pct > 0 ? ' checkout-price--discount' : ''; ?>">₹<?php echo number_format((float) $item['unit_price'], 0); ?></span>
							<?php if ($disc_pct > 0 && (float) ($item['unit_mrp'] ?? $item['mrp'] ?? 0) > 0): ?>
								<span class="checkout-item-unit-mrp">₹<?php echo number_format((float) ($item['unit_mrp'] ?? $item['mrp']), 0); ?></span>
							<?php endif; ?>
						</div>
						<div class="checkout-item-actions">
							<label class="checkout-qty-label visually-hidden" for="cart-drawer-qty-<?php echo $pid; ?>">Qty</label>
							<div class="cart-qty-stepper">
								<button type="button" class="cart-qty-btn cart-qty-minus" aria-label="Decrease quantity">−</button>
								<input type="number" id="cart-drawer-qty-<?php echo $pid; ?>" class="cart-qty-input" min="1" max="99"
									value="<?php echo (int) $item['quantity']; ?>"
									data-product-id="<?php echo $pid; ?>"
									data-unit-price="<?php echo h((string) $item['unit_price']); ?>">
								<button type="button" class="cart-qty-btn cart-qty-plus" aria-label="Increase quantity">+</button>
							</div>
							<a href="<?php echo h($drawer_base); ?>cart?remove=<?php echo $pid; ?>"
								class="cart-drawer-remove cart-remove-link checkout-item-remove" data-remove="<?php echo $pid; ?>">Remove</a>
						</div>
						</div>
					</div>
					<div class="checkout-item-price-col">
						<?php if ($disc_pct > 0 && (float) ($item['line_mrp_total'] ?? 0) > 0): ?>
							<span class="checkout-item-was" aria-hidden="true">₹<?php echo number_format((float) $item['line_mrp_total'], 0); ?></span>
						<?php endif; ?>
						<span class="checkout-item-line-total cart-line-total<?php echo $disc_pct > 0 ? ' checkout-price--discount' : ''; ?>" data-product-id="<?php echo $pid; ?>">₹<?php echo number_format($item['line_total'], 0); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<form id="cart-update-form" method="post" action="<?php echo h($drawer_base); ?>cart" class="cart-update-form" data-base-path="<?php echo h(rtrim($drawer_base, '/')); ?>">
			<input type="hidden" name="action" value="update">
			<?php foreach ($drawer_items as $item): ?>
				<input type="hidden" name="cart[<?php echo (int) $item['id']; ?>]" id="cart-input-<?php echo (int) $item['id']; ?>" value="<?php echo (int) $item['quantity']; ?>">
			<?php endforeach; ?>
		</form>
		<div class="cart-drawer-summary">
			<div class="cart-drawer-summary-total">
				<span class="cart-drawer-summary-label">Total</span>
				<span class="cart-drawer-summary-amount" id="cart-total-amount">₹<?php echo number_format($drawer_total, 0); ?></span>
			</div>
			<a href="<?php echo h($drawer_base); ?>checkout" class="cart-drawer-btn-primary">Checkout</a>
			<button type="button" class="cart-drawer-btn-ghost cart-drawer-close">Continue Shopping</button>
		</div>
	<?php endif; ?>
</div>
