<?php
/**
 * Product card image(s) with optional second-image hover peek.
 * Used by components/products_card.php — expects $img_src, $img2_src, $img_w, $img_h, $product_url, $product_name.
 */
if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}
$product_name = (string) ($product_name ?? '');
$img_src = (string) ($img_src ?? '');
$img2_src = trim((string) ($img2_src ?? ''));
$product_url = (string) ($product_url ?? '');
$img_w = (int) ($img_w ?? 1);
$img_h = (int) ($img_h ?? 1);
$has_peek = $img2_src !== '' && $img2_src !== $img_src;
?>
<a href="<?php echo h($product_url); ?>" class="products-card-image-link">
	<span class="products-card-image-inner<?php echo $has_peek ? ' products-card-image-inner--peek' : ''; ?>">
		<?php if ($has_peek): ?>
			<span class="products-card-image-track">
		<?php endif; ?>
		<img src="<?php echo h($img_src); ?>" alt="<?php echo h($product_name); ?>" class="products-card-image" loading="lazy"
			width="<?php echo $img_w; ?>" height="<?php echo $img_h; ?>">
		<?php if ($has_peek): ?>
			<img src="<?php echo h($img2_src); ?>" alt="" class="products-card-image products-card-image--peek-second" loading="lazy"
				width="<?php echo $img_w; ?>" height="<?php echo $img_h; ?>">
			</span>
		<?php endif; ?>
	</span>
</a>
