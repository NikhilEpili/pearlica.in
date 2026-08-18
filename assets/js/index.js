/**
 * Homepage: mark sections when they enter view (optional hooks / a11y). Layout no longer depends on this — see index.css.
 * Keep the hero carousel cycling after late image decode (common on mobile).
 */
(function () {
	'use strict';

	var body = document.body;
	if (!body || !body.classList.contains('homepage')) return;

	var sections = document.querySelectorAll('.homepage .section-reveal');
	if (sections.length) {
		if (typeof IntersectionObserver === 'undefined') {
			sections.forEach(function (el) {
				el.classList.add('is-visible');
			});
		} else {
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
		}
	}

	var carouselEl = document.getElementById('index-hero-carousel');
	if (!carouselEl) {
		return;
	}

	var started = false;

	function ensureCarouselCycles() {
		if (started) {
			return;
		}
		started = true;
		void carouselEl.offsetHeight;
		if (typeof bootstrap === 'undefined' || !bootstrap.Carousel) {
			return;
		}
		try {
			bootstrap.Carousel.getOrCreateInstance(carouselEl, {
				interval: 4500,
				pause: false,
				touch: true,
				ride: false
			}).cycle();
		} catch (e) {
			/* ignore */
		}
	}

	var imgs = carouselEl.querySelectorAll('.index-hero-banner-img');
	var pending = 0;
	imgs.forEach(function (img) {
		if (img.complete && img.naturalWidth > 0) {
			return;
		}
		pending += 1;
		var done = function () {
			pending -= 1;
			if (pending <= 0) {
				ensureCarouselCycles();
			}
		};
		img.addEventListener('load', done, { once: true });
		img.addEventListener('error', done, { once: true });
	});

	if (pending === 0) {
		ensureCarouselCycles();
	} else {
		setTimeout(ensureCarouselCycles, 8000);
	}
})();
