<?php
/**
 * Promo strip above the main navbar. Included from navbar.php.
 */
require_once __DIR__ . '/../lib/site_announcement_bar.php';

$announcement_bar = site_announcement_bar_settings_load();
if (empty($announcement_bar['enabled'])) {
	return;
}

$announcement_text = trim((string) ($announcement_bar['text'] ?? ''));
if ($announcement_text === '') {
	return;
}
?>
<section class="site-top-banner" role="status" aria-label="Store promotion">
	<p class="site-top-banner__text"><?= htmlspecialchars($announcement_text, ENT_QUOTES, 'UTF-8') ?></p>
</section>
