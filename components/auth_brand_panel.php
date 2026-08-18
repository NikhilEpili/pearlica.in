<?php
/**
 * Shared auth modal brand panel — centred logo + wordmark.
 * Expects: $ui_login_logo
 */
if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}
?>
<div class="login-card-brand">
	<div class="login-brand-lockup">
		<img class="login-logo" src="<?php echo h($ui_login_logo); ?>"
			onerror="this.style.display='none';var f=this.nextElementSibling;if(f){f.hidden=false;}"
			alt="Pearlica">
		<p class="login-logo-fallback" hidden>Pearlica</p>
		<span class="login-brand-rule" aria-hidden="true"></span>
		<p class="login-tagline">925 Silver Jewellery</p>
	</div>
</div>
