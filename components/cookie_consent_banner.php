<?php
/**
 * Cookie consent banner. Shown until user accepts. Accept-only (necessary cookies).
 * JS sets pearlica_cookie_consent cookie and hides banner.
 */
$consent_base = isset($base_path) ? $base_path : (isset($footer_base) ? $footer_base : '');
?>
<div id="cookie-consent-banner" class="cookie-consent-banner" role="dialog" aria-label="Cookie consent" aria-live="polite" hidden>
	<div class="cookie-consent-inner">
		<p class="cookie-consent-text">
			We use necessary cookies to run our website (cart, wishlist, login). By continuing you accept our use of these cookies.
		</p>
		<button type="button" id="cookie-consent-accept" class="btn btn-sm text-uppercase cookie-consent-accept-btn">Accept</button>
	</div>
</div>
