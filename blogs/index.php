<?php
/**
 * Blog listing: cards from the public JSON feed (BLOG_FEED_JSON_URL).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/store_base_path.php';
require_once __DIR__ . '/../lib/blog.php';

$base_path = store_base_path_from_request();
$feed_url = defined('BLOG_FEED_JSON_URL') ? (string) BLOG_FEED_JSON_URL : 'https://www.pearlica.in/blogs/rss';
$pdo = $GLOBALS['pdo'] ?? null;
$feed = blog_storefront_feed($pdo instanceof PDO ? $pdo : null, $feed_url, $base_path);

$blogs_heading = 'Journal';
$blogs_meta_description = 'Stories, styling ideas, care tips, and studio updates from Pearlica — premium 925 sterling silver jewellery.';
$feed_desc = trim((string) ($feed['site_description'] ?? ''));
// Feed often returns the brand name alone; only use it when it looks like a real description.
$feed_desc_len = function_exists('mb_strlen') ? mb_strlen($feed_desc, 'UTF-8') : strlen($feed_desc);
if ($feed_desc !== '' && strcasecmp($feed_desc, 'Pearlica') !== 0 && $feed_desc_len >= 40) {
	$blogs_meta_description = $feed_desc;
}

$meta_title_full = 'Journal — Pearlica Blog | 925 Silver Jewellery';
$meta_description = $blogs_meta_description;
$page_head_extra = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/blogs-index.css', $base_path), ENT_QUOTES, 'UTF-8') . '">';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../components/head.php'; ?>

<body class="blogs-index-page">
	<?php include __DIR__ . '/../components/gtm_body.php'; ?>

	<?php include __DIR__ . '/../components/navbar.php'; ?>

	<main class="blogs-index-main">
		<section class="blogs-hero">
			<p class="blogs-hero-label">Blog</p>
			<h1 class="blogs-hero-title"><?php echo htmlspecialchars($blogs_heading, ENT_QUOTES, 'UTF-8'); ?></h1>
			<p class="blogs-hero-subtitle">Ideas, care tips, and behind-the-scenes from our studio.</p>
		</section>

		<section class="blogs-section">
			<?php if ($feed['error'] !== null): ?>
				<div class="blogs-alert blogs-alert--error" role="alert">
					<?php echo htmlspecialchars($feed['error'], ENT_QUOTES, 'UTF-8'); ?>
				</div>
			<?php elseif (count($feed['posts']) === 0): ?>
				<div class="blogs-empty">
					<p class="blogs-empty-title">No posts yet</p>
					<p class="blogs-empty-text">New articles will appear here when they are published.</p>
				</div>
			<?php else: ?>
				<div class="blogs-grid">
					<?php foreach ($feed['posts'] as $post): ?>
						<?php
						$card_href = ($post['slug'] ?? '') !== ''
							? $base_path . 'blogs/' . rawurlencode((string) $post['slug'])
							: $post['href'];
						$card_external = ($post['slug'] ?? '') === '';
						?>
						<article class="blogs-card">
							<a class="blogs-card-link"
								href="<?php echo htmlspecialchars($card_href, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $card_external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
								<?php if ($post['image'] !== ''): ?>
									<div class="blogs-card-image-wrap">
										<img class="blogs-card-image"
											src="<?php echo htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8'); ?>" alt=""
											loading="lazy" width="640" height="400">
									</div>
								<?php endif; ?>
								<div class="blogs-card-body">
									<?php if ($post['date_label'] !== ''): ?>
										<p class="blogs-card-meta">
											<?php echo htmlspecialchars($post['date_label'], ENT_QUOTES, 'UTF-8'); ?></p>
									<?php endif; ?>
									<h2 class="blogs-card-title">
										<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
									<?php if ($post['excerpt'] !== ''): ?>
										<p class="blogs-card-excerpt">
											<?php echo htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
									<?php endif; ?>
									<span class="blogs-card-cta">Read article</span>
								</div>
							</a>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	</main>

	<?php include __DIR__ . '/../components/footer.php'; ?>

	<script src="<?php echo htmlspecialchars(asset_url('assets/js/jquery.min.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="<?php echo htmlspecialchars(asset_url('assets/js/plugins.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe"
		crossorigin="anonymous"></script>
	<?php include __DIR__ . '/../components/swiper_bundle_script.php'; ?>
	<script src="<?php echo htmlspecialchars(asset_url('assets/js/script.min.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>

</html>