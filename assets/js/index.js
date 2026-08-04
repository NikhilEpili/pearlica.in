/**
 * Homepage: mark sections when they enter view (optional hooks / a11y). Layout no longer depends on this — see index.css.
 */
(function () {
	'use strict';

	var body = document.body;
	if (!body || !body.classList.contains('homepage')) return;

	var sections = document.querySelectorAll('.homepage .section-reveal');
	if (!sections.length) return;

	if (typeof IntersectionObserver === 'undefined') {
		sections.forEach(function (el) {
			el.classList.add('is-visible');
		});
		return;
	}

	var observer = new IntersectionObserver(
		function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
				}
			});
		},
		{ rootMargin: '0px 0px 5% 0px', threshold: 0.01 }
	);

	sections.forEach(function (el) {
		observer.observe(el);
	});
})();
