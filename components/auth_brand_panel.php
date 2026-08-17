<?php
/**
 * Shared auth modal brand panel.
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
	<div class="login-brand-top">
		<img class="login-logo" src="<?php echo h($ui_login_logo); ?>"
			onerror="this.style.display='none';this.nextElementSibling.style.display='block';"
			alt="Pearlica">
		<p class="login-logo-fallback" style="display:none;">Pearlica</p>
		<p class="login-tagline">925 Silver Jewellery</p>
	</div>
	<div class="login-brand-bottom">
		<p class="login-welcome">Welcome to Pearlica</p>
		<p class="login-welcome-sub">Happy Shopping!</p>
	</div>
</div>
