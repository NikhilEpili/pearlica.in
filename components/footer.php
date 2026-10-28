<footer id="footer" class="footer-pearlica">
	<?php $footer_base = isset($base_path) ? $base_path : ''; ?>
	<div class="container">
		<div class="footer-grid py-5">
			<div class="footer-col footer-col-brand">
				<div class="footer-intro mb-4">
					<a href="<?php echo $footer_base ? $footer_base . 'index' : '.'; ?>">
						<img class="footer-logo img-fluid" src="<?php echo $footer_base; ?>assets/images/black-branding.png" alt="Pearlica">
					</a>
				</div>
				<p class="footer-tagline">
					Premium 925 sterling silver jewellery, crafted for everyday elegance, gifting, and life’s special moments. Shop with confidence.
				</p>
				<div class="footer-social">
					<a href="https://www.instagram.com/pearlicaofficial/" class="footer-social-btn" target="_blank" rel="noopener noreferrer" aria-label="Pearlica on Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
					<a href="https://www.facebook.com/profile.php?id=61588289825186&amp;ref=1" class="footer-social-btn" target="_blank" rel="noopener noreferrer" aria-label="Pearlica on Facebook"><i class="fab fa-facebook-f" aria-hidden="true"></i></a>
				</div>
			</div>
			<div class="footer-col">
				<h5 class="footer-heading section-label">Explore</h5>
				<ul class="footer-links list-unstyled">
					<li><a href="<?php echo $footer_base; ?>index" class="footer-link">Home</a></li>
					<li><a href="<?php echo $footer_base; ?>products" class="footer-link">Shop</a></li>
					<li><a href="<?php echo $footer_base; ?>about" class="footer-link">About</a></li>
					<li><a href="<?php echo $footer_base; ?>blogs" class="footer-link">Blog</a></li>
				</ul>
			</div>
			<div class="footer-col">
				<h5 class="footer-heading section-label">Customer care</h5>
				<ul class="footer-links list-unstyled">
					<li><a href="<?php echo $footer_base; ?>faqs" class="footer-link">FAQs</a></li>
					<li><a href="<?php echo $footer_base; ?>orders" class="footer-link">Track your order</a></li>
					<li><a href="<?php echo $footer_base; ?>contact" class="footer-link">Contact us</a></li>
					<li><a href="<?php echo $footer_base; ?>policies/care-guide" class="footer-link">Jewellery care guide</a></li>
				</ul>
			</div>
			<div class="footer-col">
				<h5 class="footer-heading section-label">Policies</h5>
				<ul class="footer-links list-unstyled">
					<li><a href="<?php echo $footer_base; ?>policies/return-exchange-policy" class="footer-link">Return, exchange, cancellation &amp; refund</a></li>
					<li><a href="<?php echo $footer_base; ?>policies/shipping-policy" class="footer-link">Shipping policy</a></li>
					<li><a href="<?php echo $footer_base; ?>policies/privacy-policy" class="footer-link">Privacy policy</a></li>
					<li><a href="<?php echo $footer_base; ?>policies/terms-conditions" class="footer-link">Terms &amp; conditions</a></li>
				</ul>
			</div>
			<div class="footer-col">
				<h5 class="footer-heading section-label">Contact</h5>
				<p class="footer-text">
					<a href="mailto:contact@pearlica.in" class="footer-link">contact@pearlica.in</a><br>
					<a href="tel:+919699518449" class="footer-link">+91 9699518449</a><br>
					<span class="footer-hours">Mon–Sat, 10:00 AM – 6:00 PM</span>
				</p>
			</div>
		</div>
	</div>
	<div class="footer-bottom">
		<div class="container">
			<div class="footer-bottom-inner">
				<span class="footer-copy">© <?php echo date('Y'); ?> Pearlica. All rights reserved.</span>
				<span class="footer-credit">Designed By MB-Creatives</span>
			</div>
		</div>
	</div>
</footer>
<?php include __DIR__ . '/ui_dialogs.php'; ?>
<?php include __DIR__ . '/cookie_consent_banner.php'; ?>
<?php
require_once __DIR__ . '/../includes/asset_url.php';
$footer_asset_base = htmlspecialchars($footer_base ?? '', ENT_QUOTES, 'UTF-8');
?>
<script src="<?php echo asset_url('assets/js/ui-dialog.js', $footer_asset_base); ?>"></script>
<script src="<?php echo asset_url('assets/js/navbar-search.js', $footer_asset_base); ?>"></script>
<script src="<?php echo asset_url('assets/js/mobile-bottom-nav.js', $footer_asset_base); ?>"></script>
<script src="<?php echo asset_url('assets/js/nav-dropdowns.js', $footer_asset_base); ?>"></script>
<script src="<?php echo asset_url('assets/js/cookie-consent.js', $footer_asset_base); ?>"></script>
<script src="<?php echo asset_url('assets/js/cart.js', $footer_asset_base); ?>"></script>
<script src="<?php echo asset_url('assets/js/cart-drawer.js', $footer_asset_base); ?>"></script>
<?php
if (is_file(__DIR__ . '/../lib/store_analytics.php')) {
	require_once __DIR__ . '/../lib/store_analytics.php';
	include __DIR__ . '/analytics_bootstrap.php';
}
?>
<script>
	(function () {
		var originalTitle = document.title;

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				document.title = '🥺 Come Back!';
			} else {
				document.title = originalTitle;
			}
		});
	})();
</script>
