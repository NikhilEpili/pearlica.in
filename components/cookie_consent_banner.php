<?php
/**
 * Cookie consent banner. Shown until user chooses OK or only necessary cookies.
 * JS sets pearlica_cookie_consent cookie and hides banner.
 */
$consent_base = isset($base_path) ? $base_path : (isset($footer_base) ? $footer_base : '');
?>
<div id="cookie-consent-banner" class="cookie-consent-banner" role="dialog" aria-label="Cookie consent" aria-live="polite" hidden>
	<div class="cookie-consent-inner">
		<p class="cookie-consent-text">We use cookies for cart, wishlist, and login.</p>
		<div class="cookie-consent-actions">
			<button type="button" id="cookie-consent-necessary" class="btn btn-sm text-uppercase cookie-consent-btn cookie-consent-btn--secondary">Only necessary</button>
			<button type="button" id="cookie-consent-accept" class="btn btn-sm text-uppercase cookie-consent-btn cookie-consent-btn--primary">OK</button>
		</div>
	</div>
</div>
