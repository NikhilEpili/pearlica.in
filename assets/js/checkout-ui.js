/* Checkout layout: mobile order-summary toggle */
(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var toggle = document.getElementById('checkout-mobile-summary-toggle');
		var panel = document.getElementById('checkout-summary-panel');
		if (!toggle || !panel) return;

		toggle.addEventListener('click', function () {
			var open = panel.classList.toggle('checkout-summary--mobile-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	});
})();
