<?php
/**
 * Shipping Policy — content aligned with policies/doc/04_Shipping_Policy.docx
 */
declare(strict_types=1);

$base_path = '../';
$meta_title_full = 'Shipping policy | Pearlica 925 silver jewellery India';
$meta_description = 'Read Pearlica’s shipping policy. Learn about delivery timelines, free shipping above ₹700, COD availability, order tracking, and more.';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../components/head.php'; ?>

<body class="homepage">

	<?php include __DIR__ . '/../components/navbar.php'; ?>

	<main>
		<section class="py-4 py-lg-5">
			<div class="container">
				<h4 class="section-title text-uppercase mb-4">Shipping policy</h4>
				<p class="lead text-secondary">Pearlica – 925 Sterling Silver Jewellery</p>
				<p class="text-secondary mb-4"><em>Last updated: 18 February 2026</em></p>
				<div class="policy-content">
					<p>At Pearlica, we are committed to ensuring your jewellery is delivered safely and within an efficient timeframe across India. Please review our shipping guidelines below.</p>

					<h2 class="h5 text-uppercase mt-4 mb-2">Shipping charges &amp; COD</h2>
					<ul>
						<li>Orders above ₹700 qualify for complimentary shipping across India.</li>
						<li>A standard shipping charge of ₹199 applies to orders below ₹700.</li>
						<li>Cash on Delivery (COD) is available for eligible orders. COD is not applicable on orders exceeding ₹10,000.</li>
						<li>All applicable taxes are included in the product price. No hidden charges at checkout.</li>
					</ul>

					<h2 class="h5 text-uppercase mt-4 mb-2">Order processing time</h2>
					<ul>
						<li>Orders are typically processed and dispatched within 2–3 working days (excluding Sundays and public holidays).</li>
						<li>Customised or made-to-order jewellery may require additional processing time, which will be communicated where applicable.</li>
						<li>All orders are dispatched from our Mumbai warehouse.</li>
					</ul>

					<h2 class="h5 text-uppercase mt-4 mb-2">Estimated delivery timelines</h2>
					<p>Delivery timelines begin after dispatch:</p>
					<ul>
						<li>Tier 1 cities: 1–3 working days</li>
						<li>Tier 2 cities: 3–5 working days</li>
						<li>Tier 3 cities: 3–7 working days</li>
					</ul>
					<p>Timelines are estimates and may vary due to location accessibility, weather conditions, courier delays, or peak seasonal demand.</p>

					<h2 class="h5 text-uppercase mt-4 mb-2">Order tracking</h2>
					<p>Once dispatched, tracking details will be shared via your registered email or mobile number. Each order is shipped to a single delivery address. For multiple addresses, please place separate orders.</p>

					<h2 class="h5 text-uppercase mt-4 mb-2">Address &amp; delivery responsibility</h2>
					<p>Customers are responsible for reviewing and confirming accurate delivery details before completing the purchase. Pearlica will not be responsible for delays or non-delivery resulting from incorrect address or contact information.</p>
					<p>Parcels that remain undelivered after multiple attempts or are refused by the recipient will be returned to our warehouse (RTO). Re-shipping charges may apply for such orders.</p>

					<h2 class="h5 text-uppercase mt-4 mb-2">Order verification</h2>
					<p>For security and fraud prevention, Pearlica may contact customers for order verification before dispatch. If verification is unsuccessful, the order may be delayed or cancelled at our discretion.</p>

					<h2 class="h5 text-uppercase mt-4 mb-2">International shipping</h2>
					<p>Currently, Pearlica ships within India only.</p>

					<h2 class="h5 text-uppercase mt-4 mb-2">Damaged parcel advisory</h2>
					<p>If you receive a parcel that appears tampered or damaged, please refuse delivery or record a clear unboxing video immediately. Contact us within 24 hours of receipt. Once verified, you may choose a full replacement or full refund.</p>

					<h2 class="h5 text-uppercase mt-4 mb-2">Need assistance?</h2>
					<p>Email: <a href="mailto:contact@pearlica.in">contact@pearlica.in</a><br>
						Phone: <a href="tel:+919699518449">+91 9699518449</a><br>
						Hours: Monday to Saturday, 10:00 AM – 6:00 PM</p>

					<?php include __DIR__ . '/includes/related-policies.php'; ?>
				</div>
			</div>
		</section>
	</main>

	<?php include __DIR__ . '/../components/footer.php'; ?>

	<script src="<?php echo htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8'); ?>assets/js/jquery.min.js"></script>
	<script src="<?php echo htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8'); ?>assets/js/plugins.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe"
		crossorigin="anonymous"></script>
	<?php include __DIR__ . '/../components/swiper_bundle_script.php'; ?>
	<script src="<?php echo htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8'); ?>assets/js/script.min.js"></script>
</body>

</html>
