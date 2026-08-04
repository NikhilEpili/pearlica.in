<?php
/**
 * Shared “Related policies” nav. Expects $base_path (e.g. '../').
 */
if (!isset($base_path)) {
	$base_path = '../';
}
$bp = htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8');
?>
<nav class="mt-5 pt-4 border-top" aria-label="Related policies">
	<p class="small text-uppercase text-secondary mb-2">Related policies</p>
	<ul class="list-unstyled d-flex flex-wrap gap-2 small">
		<li><a href="<?= $bp; ?>policies/return-exchange-policy">Return, exchange, cancellation &amp; refund</a></li>
		<li><a href="<?= $bp; ?>policies/shipping-policy">Shipping policy</a></li>
		<li><a href="<?= $bp; ?>policies/pearlica-care">Pearlica Care Promise</a></li>
		<li><a href="<?= $bp; ?>policies/care-guide">Jewellery care guide</a></li>
		<li><a href="<?= $bp; ?>policies/privacy-policy">Privacy policy</a></li>
		<li><a href="<?= $bp; ?>policies/terms-conditions">Terms &amp; conditions</a></li>
	</ul>
</nav>
