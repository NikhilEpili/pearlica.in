<?php
/**
 * FAQ accordion for About page. Two columns; only question visible until clicked.
 * Expects $base_path (optional). Links in answers use var(--black) + border-bottom (no blue).
 */
$faq_accordion_base = isset($base_path) ? $base_path : '';

$faqs_accordion = [
	['q' => 'Is Pearlica jewellery made of real 925 silver?', 'a' => 'Yes. All Pearlica pieces are crafted from certified 925 sterling silver, containing 92.5% pure silver blended with strengthening alloys for durability.'],
	['q' => 'Will 925 silver tarnish over time?', 'a' => 'Silver may naturally oxidize when exposed to moisture, perfumes, or air. With proper care and storage, your jewellery will maintain its shine for a long time.'],
	['q' => 'Does Pearlica provide an authenticity confirmation?', 'a' => 'Yes, every order includes confirmation of 925 sterling silver quality along with secure packaging.'],
	['q' => 'Do I pay before my order ships?', 'a' => 'Yes. Payment is collected securely online at checkout (UPI, cards, net banking). Your order is confirmed after successful payment.'],
	['q' => 'How long does delivery take?', 'a' => 'Orders are usually dispatched within 2–3 working days. Delivery timelines vary depending on your location within India.'],
	['q' => 'Can I exchange my jewellery?', 'a' => 'Yes. Your first exchange within 7 days of delivery is free with doorstep pickup, subject to our Return, exchange, cancellation & refund policy.'],
	['q' => 'Are earrings returnable?', 'a' => 'For hygiene reasons, earrings are not eligible for return or exchange unless received in damaged condition.'],
	['q' => 'How should I store my silver jewellery?', 'a' => 'Store your jewellery in a dry place, preferably in an airtight pouch. Avoid contact with water, perfumes, or harsh chemicals.'],
	['q' => 'Do you ship internationally?', 'a' => 'Currently, Pearlica delivers within India only.'],
	['q' => 'Is 925 silver suitable for daily wear?', 'a' => 'Yes, 925 sterling silver is durable for everyday use. However, removing jewellery before swimming, bathing, or gym activities is recommended.'],
	['q' => 'Will the jewellery cause skin irritation?', 'a' => '925 silver is generally skin-friendly and suitable for most people. Those with specific metal sensitivities should review material details before purchase.'],
	['q' => 'How do I know my ring size?', 'a' => 'You can refer to our size guide on the website. Measuring an existing ring is the easiest way to determine your correct size.'],
	['q' => 'Do you offer resizing services?', 'a' => 'Currently, resizing is not available for all products. Please review product details before purchasing.'],
	['q' => 'What payment methods do you accept?', 'a' => 'We accept UPI, debit and credit cards, and net banking through our checkout.'],
	['q' => 'Can I change my address after placing the order?', 'a' => 'Address changes may be possible before dispatch. Please contact us immediately after placing your order.'],
	['q' => 'What if my product arrives damaged?', 'a' => 'If you receive a damaged item, please record a clear unboxing video and contact us within 48 hours of delivery.'],
	['q' => 'Do you offer gift packaging?', 'a' => 'Yes, gift-ready packaging is available for select products at an additional charge. Please select the gift packaging option on the product page or contact us for confirmation before placing your order.'],
	['q' => 'How should I clean my 925 silver jewellery?', 'a' => 'Gently polish using a soft, dry cloth. Avoid harsh chemicals or abrasive materials.'],
	['q' => 'Is Pearlica jewellery 925 sterling silver?', 'a' => 'Yes. Our pieces are crafted to 925 sterling silver standards. Any extra product-specific details appear on the individual product page.'],
	['q' => 'Do you restock sold-out products?', 'a' => 'Some designs may be restocked depending on availability. Please contact us for restock updates.'],
	['q' => 'Can I cancel my order?', 'a' => 'You can cancel within 24 hours of placing the order, or any time before it is dispatched. Once shipped, cancellation is not possible — see our Return, exchange, cancellation & refund policy.'],
	['q' => 'Is Pearlica jewellery waterproof?', 'a' => 'Silver jewellery is not recommended for prolonged water exposure. Frequent contact with water may affect shine over time.'],
	['q' => 'Do you offer bulk or corporate gifting options?', 'a' => 'Yes, for bulk or corporate gifting inquiries, please contact us directly.'],
	['q' => 'How long will my silver jewellery last?', 'a' => 'With proper care and storage, 925 sterling silver jewellery can last for many years.'],
	['q' => 'How can I contact customer support?', 'a' => 'contact', 'a_html' => 'You can reach us at: <a href="mailto:contact@pearlica.in">contact@pearlica.in</a>, <a href="tel:+919699518449">+91 9699518449</a>, Monday – Saturday | 10 AM – 6 PM.'],
];
$faq_mid = (int) ceil(count($faqs_accordion) / 2);
$faqs_left = array_slice($faqs_accordion, 0, $faq_mid);
$faqs_right = array_slice($faqs_accordion, $faq_mid, null);
?>
<div class="about-faq-columns">
	<div class="about-faq-column" data-faq-column>
		<?php foreach ($faqs_left as $idx => $faq): ?>
		<div class="about-faq-item" data-faq-item>
			<button type="button" class="about-faq-question-row" data-faq-toggle aria-expanded="false">
				<span class="about-faq-question-text"><?php echo htmlspecialchars($faq['q'], ENT_QUOTES, 'UTF-8'); ?></span>
				<svg class="about-faq-toggle-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M7 2v10M2 7h10"/></svg>
			</button>
			<div class="about-faq-answer-wrap">
				<div class="about-faq-answer">
					<?php
					if (!empty($faq['a_html'])) {
						echo $faq['a_html'];
					} else {
						echo htmlspecialchars($faq['a'], ENT_QUOTES, 'UTF-8');
					}
					?>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<div class="about-faq-column" data-faq-column>
		<?php foreach ($faqs_right as $idx => $faq): ?>
		<div class="about-faq-item" data-faq-item>
			<button type="button" class="about-faq-question-row" data-faq-toggle aria-expanded="false">
				<span class="about-faq-question-text"><?php echo htmlspecialchars($faq['q'], ENT_QUOTES, 'UTF-8'); ?></span>
				<svg class="about-faq-toggle-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M7 2v10M2 7h10"/></svg>
			</button>
			<div class="about-faq-answer-wrap">
				<div class="about-faq-answer">
					<?php
					if (!empty($faq['a_html'])) {
						echo $faq['a_html'];
					} else {
						echo htmlspecialchars($faq['a'], ENT_QUOTES, 'UTF-8');
					}
					?>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</div>
