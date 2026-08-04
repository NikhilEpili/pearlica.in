/**
 * About page: FAQ accordion toggle. Adds/removes .active on data-faq-item.
 */
(function () {
	'use strict';

	function init() {
		var toggles = document.querySelectorAll('[data-faq-toggle]');
		toggles.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var item = this.closest('[data-faq-item]');
				if (!item) return;
				var expanded = item.classList.contains('active');
				item.classList.toggle('active');
				btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
