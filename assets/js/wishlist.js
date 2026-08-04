(function () {
	'use strict';

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.btn-wishlist');
		if (!btn) return;
		e.preventDefault();
		var href = btn.getAttribute('href');
		if (!href) return;
		var url = href + (href.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
		var icon = btn.querySelector('.fa-heart');
		var wasActive = btn.classList.contains('active');

		fetch(url, {
			method: 'GET',
			headers: { 'Accept': 'application/json' },
			credentials: 'same-origin'
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data && data.ok) {
					if (data.in_wishlist) {
						btn.classList.add('active');
						btn.setAttribute('aria-label', 'Remove from wishlist');
						if (icon) { icon.classList.remove('far'); icon.classList.add('fas'); }
					} else {
						btn.classList.remove('active');
						btn.setAttribute('aria-label', 'Add to wishlist');
						if (icon) { icon.classList.remove('fas'); icon.classList.add('far'); }
					}
				}
			})
			.catch(function () {
				window.location.href = href;
			});
	});
})();
