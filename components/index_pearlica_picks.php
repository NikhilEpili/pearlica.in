<?php
/**
 * Pearlica Picks — optional YouTube embeds (INDEX_HOME_PICK_VIDEO_IDS) or Instagram CTA.
 * Expects: $base_path, $index_pick_video_ids (list of non-empty 11-char-ish YouTube IDs).
 */
declare(strict_types=1);
$pick_ids = isset($index_pick_video_ids) && is_array($index_pick_video_ids) ? $index_pick_video_ids : [];
$pick_ids = array_values(array_filter(array_map(static function ($id) {
	$id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $id);
	return strlen($id) >= 6 ? $id : '';
}, $pick_ids)));
?>
<section class="index-picks section-reveal" aria-label="Pearlica Picks">
	<div class="index-picks-inner">
		<div class="index-picks-head">
			<h2 class="index-picks-title">Pearlica Picks</h2>
			<p class="index-picks-sub">Short clips — styling, close-ups, and new drops.</p>
		</div>
		<?php if ($pick_ids !== []): ?>
			<div class="index-picks-grid">
				<?php foreach ($pick_ids as $vid): ?>
					<div class="index-picks-cell">
						<div class="index-picks-embed ratio ratio-16x9">
							<iframe
								src="https://www.youtube-nocookie.com/embed/<?= h($vid); ?>?rel=0"
								title="Pearlica video"
								loading="lazy"
								allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
								referrerpolicy="strict-origin-when-cross-origin"
								allowfullscreen></iframe>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="index-picks-fallback">
				<p class="index-picks-fallback-text mb-3">See reels and styling videos on our Instagram.</p>
				<a href="https://www.instagram.com/pearlicaofficial/" class="index-picks-ig-btn" target="_blank" rel="noopener noreferrer">Open Instagram</a>
			</div>
		<?php endif; ?>
	</div>
</section>
