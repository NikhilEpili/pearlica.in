<?php
/**
 * Store product grid card (shop, gifting, homepage New Arrivals).
 * Set variables via pearlica_product_card_vars() then extract(..., EXTR_OVERWRITE) before include.
 *
 * @var array<string, mixed> $p
 * @var string $img_src
 * @var string $img2_src
 * @var int $img_w
 * @var int $img_h
 * @var string $product_url
 * @var string $product_name
 * @var array{current: string, original: string|null} $price_parts
 * @var bool $in_wishlist
 * @var string $base_path
 * @var string $wishlist_return
 */
?>
<article class="products-card fade-up-products">
	<div class="products-card-inner">
		<div class="products-card-image-wrap">
			<?php if ((float) ($p['discount_percentage'] ?? 0) > 0): ?>
				<span class="products-card-discount-badge"><?php echo (int) round((float) $p['discount_percentage']); ?>% OFF</span>
			<?php endif; ?>
			<?php include __DIR__ . '/products_card_peek_images.php'; ?>
			<a href="<?php echo h($base_path); ?>wishlist-toggle?product_id=<?php echo (int) $p['id']; ?>&return_url=<?php echo rawurlencode($base_path . $wishlist_return); ?>" class="products-card-wishlist btn-wishlist <?php echo $in_wishlist ? 'active' : ''; ?>" data-product-id="<?php echo (int) $p['id']; ?>" aria-label="<?php echo $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
				<svg class="products-card-wishlist-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
			</a>
			<a href="<?php echo h($product_url); ?>" class="products-card-addstrip">Add to Cart</a>
		</div>
		<div class="products-card-info">
			<?php if (!empty($p['category_name'])): ?>
				<p class="products-card-category"><?php echo h($p['category_name']); ?></p>
			<?php endif; ?>
			<h2 class="products-card-name">
				<a href="<?php echo h($product_url); ?>"><?php echo h($p['name'] ?? ''); ?></a>
			</h2>
			<div class="products-card-price-row">
				<span class="products-card-price"><?php echo $price_parts['current']; ?></span>
				<?php if (!empty($price_parts['original'])): ?>
					<span class="products-card-price-original"><?php echo $price_parts['original']; ?></span>
				<?php endif; ?>
				<?php include __DIR__ . '/product_tax_incl_note.php'; ?>
			</div>
			<?php
			$sq = (int) ($p['stock_quantity'] ?? -1);
			if ($sq === 1):
				?>
				<p class="products-card-stock-limited">Limited Pieces Available</p>
			<?php endif; ?>
		</div>
	</div>
</article>
