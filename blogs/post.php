<?php
/**
 * Single blog article (admin-managed posts or external JSON feed fallback).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/store_base_path.php';
require_once __DIR__ . '/../lib/blog.php';

$base_path = store_base_path_from_request();
$slug = trim((string) ($_GET['slug'] ?? ''));
$feed_url = defined('BLOG_FEED_JSON_URL') ? (string) BLOG_FEED_JSON_URL : 'https://www.pearlica.in/blogs/rss';
$pdo = $GLOBALS['pdo'] ?? null;
$result = blog_storefront_post($pdo instanceof PDO ? $pdo : null, $slug, $feed_url, $base_path);

if (!$result['ok'] || !is_array($result['post'])) {
	http_response_code($result['error'] === 'Invalid article.' ? 400 : 404);
	$meta_title_full = 'Article not found | Pearlica Blog';
	$meta_description = 'This blog article could not be found.';
	$page_head_extra = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/blogs-post.css', $base_path), ENT_QUOTES, 'UTF-8') . '">';
	$error_message = (string) ($result['error'] ?? 'This article could not be found.');
} else {
	$post = $result['post'];
	$article_title = (string) ($post['title'] ?? 'Article');
	$meta_title = trim((string) ($post['meta_title'] ?? ''));
	$meta_description = trim((string) ($post['meta_description'] ?? ''));
	if ($meta_title === '') {
		$meta_title_full = $article_title . ' | Pearlica Blog';
	} else {
		$meta_title_full = $meta_title;
	}
	if ($meta_description === '') {
		$meta_description = (string) ($post['excerpt'] ?? 'Read this article on the Pearlica journal.');
	}
	$page_head_extra = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/blogs-post.css', $base_path), ENT_QUOTES, 'UTF-8') . '">';
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../components/head.php'; ?>

<body class="blogs-post-page">
	<?php include __DIR__ . '/../components/gtm_body.php'; ?>

	<?php include __DIR__ . '/../components/navbar.php'; ?>

	<main class="blogs-post-main">
		<section class="blogs-post-section">
			<p class="blogs-post-back-wrap px-3 px-md-0">
				<a class="blogs-post-back" href="<?php echo htmlspecialchars($base_path . 'blogs/', ENT_QUOTES, 'UTF-8'); ?>">← Back to journal</a>
			</p>

			<?php if (!$result['ok'] || !is_array($result['post'])): ?>
				<div class="blogs-post-header">
					<h1 class="blogs-post-title blogs-hero-title">Article not found</h1>
					<p class="blogs-hero-subtitle"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
				</div>
			<?php else: ?>
				<header class="blogs-post-header">
					<?php if (($post['date_label'] ?? '') !== ''): ?>
						<p class="blogs-post-meta blogs-card-meta"><?php echo htmlspecialchars((string) $post['date_label'], ENT_QUOTES, 'UTF-8'); ?></p>
					<?php endif; ?>
					<h1 class="blogs-post-title blogs-hero-title"><?php echo htmlspecialchars($article_title, ENT_QUOTES, 'UTF-8'); ?></h1>
					<?php if (($post['excerpt'] ?? '') !== ''): ?>
						<p class="blogs-hero-subtitle"><?php echo htmlspecialchars((string) $post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
					<?php endif; ?>
				</header>

				<?php if (($post['image'] ?? '') !== ''): ?>
					<div class="blogs-post-hero-wrap">
						<img class="blogs-post-hero" src="<?php echo htmlspecialchars((string) $post['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="eager" width="960" height="540">
					</div>
				<?php endif; ?>

				<div class="blogs-post-body px-3 px-md-0">
					<div class="blogs-post-prose">
						<?php echo $post['body_html']; ?>
					</div>
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
