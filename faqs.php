<?php
/**
 * Frequently asked questions.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/asset_url.php';
require_once __DIR__ . '/includes/store_base_path.php';

$base_path = store_base_path_from_request();
$meta_title_full = 'FAQs | Pearlica Customer Care';
$meta_description = 'Answers to common questions about Pearlica 925 silver jewellery — orders, shipping, returns, care, and more.';
$page_head_extra = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/about.css', $base_path), ENT_QUOTES, 'UTF-8') . '">';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/components/head.php'; ?>

<body class="about-page faqs-page">
	<?php include __DIR__ . '/components/gtm_body.php'; ?>

	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main class="about-animate">
		<section class="about-hero">
			<p class="about-hero-label">Customer care</p>
			<h1 class="about-hero-title">Frequently Asked Questions</h1>
			<p class="about-hero-subtitle">Quick answers about orders, shipping, returns, and our silver jewellery.</p>
		</section>

		<section class="about-section about-faq-section">
			<?php include __DIR__ . '/components/section_faqs_accordion.php'; ?>
		</section>

		<div class="about-cta-strip">
			<p class="about-cta-heading">Still need help?</p>
			<p class="about-cta-subtitle">Our team is happy to assist you.</p>
			<a href="<?php echo htmlspecialchars($base_path . 'contact', ENT_QUOTES, 'UTF-8'); ?>" class="about-cta-btn">Contact Us</a>
		</div>
	</main>

	<?php include __DIR__ . '/components/footer.php'; ?>

	<script src="<?php echo htmlspecialchars(asset_url('assets/js/jquery.min.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="<?php echo htmlspecialchars(asset_url('assets/js/plugins.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
	<?php include __DIR__ . '/components/swiper_bundle_script.php'; ?>
	<script src="<?php echo htmlspecialchars(asset_url('assets/js/script.min.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="<?php echo htmlspecialchars(asset_url('assets/js/about.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>

</html>
