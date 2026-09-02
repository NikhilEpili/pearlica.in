<?php
/**
 * About Us page. Luxury design system: hero, brand story, founder, values, CTA.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/asset_url.php';

$base_path = '';
$meta_title_full = 'About Pearlica – Premium 925 Silver Jewellery Brand in India';
$meta_description = 'Discover Pearlica: a modern Indian 925 silver jewellery brand. Our story, founder, and commitment to quality, craftsmanship, and accessible elegance.';
$page_head_extra = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/about.css', $base_path), ENT_QUOTES, 'UTF-8') . '">';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/components/head.php'; ?>

<body class="about-page">

	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main class="about-animate">
		<!-- Hero -->
		<section class="about-hero">
			<p class="about-hero-label">Our Story</p>
			<h1 class="about-hero-title">About Pearlica</h1>
			<p class="about-hero-subtitle">925 Sterling Silver — Crafted for Modern India</p>
		</section>

		<!-- Brand story -->
		<section class="about-section">
			<div class="about-story-text about-prose-wide">
				<p class="about-story-label">Who We Are</p>
				<p>Pearlica was born from over 12 years in the jewellery industry and a clear vision: the future of jewellery in India lies in premium sterling silver. We are a contemporary 925 silver brand built to make high-quality, beautifully crafted pieces affordable and meaningful for everyone.</p>
				<p>Our founder saw a shift: modern buyers wanted lightweight, elegant, versatile pieces with premium quality without the traditional price tag. We specialize in thoughtfully designed silver that blends modern innovation with timeless elegance for everyday wear and special moments.</p>
				<p>Quality is non-negotiable. From certified 925 sterling silver to meticulous finishing, every detail focuses on durability, comfort, and refined aesthetics. We believe jewellery should empower confidence and be accessible to all.</p>
			</div>
		</section>

		<!-- Founder -->
		<section class="about-section">
			<div class="about-founder-text about-prose-wide">
				<p class="about-founder-label">About The Founder</p>
				<h2 class="about-founder-name">Mahipal Rajpurohit</h2>
				<p class="about-founder-title">Jewellery Entrepreneur · 12+ Years Experience</p>
				<p>Mahipal (Jalam Singh) founded Pearlica after over 12 years of hands-on retail experience. Working closely with customers, designs, and market trends, he recognized a growing demand for high-quality silver jewellery that blends affordability with premium craftsmanship.</p>
				<p>With deep insight into customer preferences and operational excellence, he envisioned Pearlica as a trusted name in modern silver jewellery. Driven by strategic growth and a commitment to excellence, the brand continues to evolve as a contemporary silver destination in India.</p>
				<blockquote class="about-founder-blockquote">To build Pearlica into a leading Indian silver jewellery brand</blockquote>
			</div>
		</section>

		<!-- Values -->
		<section class="about-section">
			<p class="about-story-label">Our Pillars</p>
			<div class="about-values-grid">
				<div class="about-value-card">
					<span class="about-value-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
					</span>
					<h3 class="about-value-heading">Quality</h3>
					<p class="about-value-text">Premium materials and certified 925 silver in every piece.</p>
				</div>
				<div class="about-value-card">
					<span class="about-value-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none"><path d="M12 2L3 7v10l9 5 9-5V7l-9-5z"/><path d="M12 2v20M3 7l9 5 9-5M3 17l9 5 9-5"/></svg>
					</span>
					<h3 class="about-value-heading">Craftsmanship</h3>
					<p class="about-value-text">Meticulous finishing and design that lasts.</p>
				</div>
				<div class="about-value-card">
					<span class="about-value-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
					</span>
					<h3 class="about-value-heading">Accessibility</h3>
					<p class="about-value-text">Honest pricing so elegance is within reach.</p>
				</div>
				<div class="about-value-card">
					<span class="about-value-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
					</span>
					<h3 class="about-value-heading">Elegance</h3>
					<p class="about-value-text">Refined designs for everyday and occasion.</p>
				</div>
			</div>
		</section>

		<!-- CTA strip -->
		<div class="about-cta-strip">
			<p class="about-cta-heading">Have more questions?</p>
			<p class="about-cta-subtitle">We’re here to help. Get in touch with our team.</p>
			<a href="<?php echo htmlspecialchars($base_path . 'contact', ENT_QUOTES, 'UTF-8'); ?>" class="about-cta-btn">Contact Us</a>
		</div>
	</main>

	<?php include __DIR__ . '/components/footer.php'; ?>

	<script src="<?php echo htmlspecialchars(asset_url('assets/js/jquery.min.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="<?php echo htmlspecialchars(asset_url('assets/js/plugins.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
	<?php include __DIR__ . '/components/swiper_bundle_script.php'; ?>
	<script src="<?php echo htmlspecialchars(asset_url('assets/js/script.min.js', $base_path), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>

</html>
