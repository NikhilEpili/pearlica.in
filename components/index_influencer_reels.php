<?php
/**
 * Homepage — "Your Favourite Influencers" reel carousel.
 * Expects: $influencer_reels (list from instagram_reel_list_active), $base_path.
 */
declare(strict_types=1);

$reels = isset($influencer_reels) && is_array($influencer_reels) ? $influencer_reels : [];
if ($reels === []) {
	return;
}

require_once __DIR__ . '/../lib/instagram_reel.php';
require_once __DIR__ . '/../includes/shop_product_helpers.php';

$slides = [];
foreach ($reels as $row) {
	$slide = instagram_reel_slide_view($row, $base_path ?? '');
	if ($slide['video_url'] === '' && $slide['shortcode'] === '') {
		continue;
	}
	$slides[] = $slide;
}
if ($slides === []) {
	return;
}

$slide_count = count($slides);
if ($slide_count % 2 === 0) {
	return;
}

$max_side_radius = $slide_count === 1 ? 0 : ($slide_count === 3 ? 1 : 2);
?>
<section
	class="index-influencers section-reveal"
	aria-label="Your Favourite Influencers"
	data-reel-count="<?= (int) $slide_count ?>"
	data-max-radius="<?= (int) $max_side_radius ?>">
	<h2 class="index-influencers-title">Your Favourite Influencers</h2>
	<div class="index-influencers-carousel">
		<button type="button" class="index-influencers-nav index-influencers-prev" aria-label="Previous reel"<?= $slide_count < 2 ? ' hidden' : '' ?>>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M15 18l-6-6 6-6"/>
			</svg>
		</button>
		<div class="index-influencers-stage">
			<div class="index-influencers-track">
				<?php foreach ($slides as $i => $slide): ?>
					<?php $has_video = $slide['video_url'] !== ''; ?>
					<div
						class="index-influencers-slide<?= $has_video ? ' has-video' : ' has-embed' ?>"
						data-reel-index="<?= (int) $i ?>"
						data-reel-number="<?= (int) ($i + 1) ?>"
						data-shortcode="<?= h($slide['shortcode']); ?>">
						<div class="index-influencers-reel">
							<div class="index-influencers-media">
								<?php if ($slide['thumbnail_url'] !== ''): ?>
									<img
										class="index-influencers-thumb"
										src="<?= h($slide['thumbnail_url']); ?>"
										alt=""
										loading="lazy"
										decoding="async">
								<?php endif; ?>

								<?php if ($has_video): ?>
									<video
										class="index-influencers-video"
										data-video-src="<?= h($slide['video_url']); ?>"
										<?php if ($slide['poster_url'] !== ''): ?>poster="<?= h($slide['poster_url']); ?>"<?php endif; ?>
										muted
										loop
										playsinline
										preload="none"
										disablepictureinpicture
										aria-label="Influencer reel"></video>
								<?php else: ?>
									<div class="index-influencers-embed">
										<iframe
											class="index-influencers-iframe"
											data-embed-src="<?= h($slide['embed_url']); ?>"
											title="Instagram reel"
											loading="lazy"
											scrolling="no"
											frameborder="0"
											allow="autoplay; encrypted-media; picture-in-picture"
											allowfullscreen
											referrerpolicy="strict-origin-when-cross-origin"></iframe>
									</div>
								<?php endif; ?>

								<?php if ($slide['permalink'] !== ''): ?>
									<a
										class="index-influencers-open"
										href="<?= h($slide['permalink']); ?>"
										target="_blank"
										rel="noopener noreferrer"
										aria-label="Open this reel on Instagram">
										<i class="fab fa-instagram" aria-hidden="true"></i>
									</a>
								<?php endif; ?>
							</div>
						</div>
						<?php if ($slide['product_name'] !== ''): ?>
							<div class="index-influencers-caption">
								<?php if ($slide['product_url'] !== ''): ?>
									<p class="index-influencers-product-name">
										<a href="<?= h($slide['product_url']); ?>"><?= h($slide['product_name']); ?></a>
									</p>
								<?php else: ?>
									<p class="index-influencers-product-name"><?= h($slide['product_name']); ?></p>
								<?php endif; ?>
								<?php if ($slide['price_parts']['current'] !== ''): ?>
									<p class="index-influencers-price">
										<span><?= h($slide['price_parts']['current']); ?></span>
										<?php if (!empty($slide['price_parts']['original'])): ?>
											<span class="index-influencers-price-original"><?= h($slide['price_parts']['original']); ?></span>
										<?php endif; ?>
									</p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<button type="button" class="index-influencers-nav index-influencers-next" aria-label="Next reel"<?= $slide_count < 2 ? ' hidden' : '' ?>>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M9 18l6-6-6-6"/>
			</svg>
		</button>
	</div>
</section>
