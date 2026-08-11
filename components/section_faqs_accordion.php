<?php
/**
 * FAQ accordion for About page. Two columns; only question visible until clicked.
 * Loads from DB (admin-editable). Falls back to built-in defaults if DB unavailable.
 */
$faq_accordion_base = isset($base_path) ? $base_path : '';

if (!function_exists('faq_for_accordion')) {
	require_once __DIR__ . '/../lib/faq.php';
}

$faqs_accordion = [];
try {
	$pdo_faq = $GLOBALS['pdo'] ?? null;
	if ($pdo_faq instanceof PDO) {
		faq_ensure_schema($pdo_faq);
		$faqs_accordion = faq_for_accordion($pdo_faq);
	}
} catch (Throwable $e) {
	$faqs_accordion = [];
}
if ($faqs_accordion === []) {
	$faqs_accordion = faq_fallback_accordion();
}

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
