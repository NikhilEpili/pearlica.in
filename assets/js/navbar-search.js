/**
 * Navbar search: fetch suggestions, keyboard nav, navigate to product or filtered shop.
 */
document.addEventListener('DOMContentLoaded', function () {
	function initAnimatedSearchPlaceholder(root, input) {
		var field = root.querySelector('.site-search-field');
		var ph = root.querySelector('[data-search-animated-ph]');
		if (!field || !ph) return;
		var raw = root.getAttribute('data-placeholder-phrases');
		var phrases = [];
		try {
			phrases = raw ? JSON.parse(raw) : [];
		} catch (err) {
			phrases = [];
		}
		if (!phrases.length) return;
		var idx = 0;
		function syncPlaceholderVisibility() {
			var hide = document.activeElement === input || (input.value && input.value.trim().length > 0);
			field.classList.toggle('is-placeholder-hidden', hide);
		}
		input.addEventListener('focus', syncPlaceholderVisibility);
		input.addEventListener('blur', syncPlaceholderVisibility);
		input.addEventListener('input', syncPlaceholderVisibility);
		syncPlaceholderVisibility();
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			ph.textContent = phrases[0];
			return;
		}
		window.setInterval(function () {
			if (field.classList.contains('is-placeholder-hidden')) return;
			ph.classList.add('is-switching');
			window.setTimeout(function () {
				idx = (idx + 1) % phrases.length;
				ph.textContent = phrases[idx];
				ph.classList.remove('is-switching');
			}, 200);
		}, 3200);
	}

	document.querySelectorAll('[data-site-search]').forEach(function (root) {
		initSiteSearchRoot(root);
	});

	function initSiteSearchRoot(root) {
	var input = root.querySelector('[data-site-search-input]');
	var dropdown = root.querySelector('[data-site-search-dropdown]');
	var urlAttr = root.getAttribute('data-search-url') || '';
	var productsOnly = root.hasAttribute('data-search-products-only');
	var minChars = parseInt(root.getAttribute('data-search-min-chars') || '2', 10);
	if (!Number.isFinite(minChars) || minChars < 1) minChars = 2;
	if (!input || !dropdown) return;

	var debounceTimer = null;
	var items = [];
	var activeIndex = -1;

	function hide() {
		dropdown.classList.add('d-none');
		dropdown.innerHTML = '';
		items = [];
		activeIndex = -1;
	}

	function basePathPrefix() {
		var p = window.location.pathname || '/';
		var parts = p.split('/').filter(Boolean);
		if (parts.length <= 1) return '/';
		parts.pop();
		return '/' + parts.join('/') + '/';
	}

	function resolveHref(rel) {
		if (!rel || rel.indexOf('://') !== -1) return rel;
		var bp = basePathPrefix();
		if (bp === '/') return '/' + rel.replace(/^\//, '');
		return bp.replace(/\/?$/, '/') + rel.replace(/^\//, '');
	}

	function queryMeetsMinChars(q) {
		return (q || '').trim().length >= minChars;
	}

	function render(list) {
		dropdown.innerHTML = '';
		items = [];
		if (productsOnly && list) {
			list = list.filter(function (s) {
				return (s.type || '') === 'product';
			});
		}
		if (!list || !list.length) {
			hide();
			return;
		}
		list.forEach(function (s, i) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'list-group-item list-group-item-action text-start border-0 border-bottom';
			btn.setAttribute('role', 'option');
			btn.dataset.index = String(i);
			if (productsOnly) {
				btn.innerHTML = '<span>' + escapeHtml(s.label || '') + '</span>';
			} else {
				var t = s.type || '';
				var type = t === 'category' ? 'Category' : t === 'search' ? 'Popular search' : 'Product';
				btn.innerHTML = '<span class="site-search-hint d-block mb-1">' + type + '</span><span>' + escapeHtml(s.label || '') + '</span>';
			}
			btn.addEventListener('click', function () {
				window.location.href = resolveHref(s.url || '');
			});
			dropdown.appendChild(btn);
			items.push(btn);
		});
		dropdown.classList.remove('d-none');
		activeIndex = -1;
	}

	function escapeHtml(str) {
		var d = document.createElement('div');
		d.textContent = str;
		return d.innerHTML;
	}

	function suggestEndpoint() {
		if (urlAttr && urlAttr.length && urlAttr.indexOf('search-suggest') !== -1) {
			return urlAttr.charAt(0) === '/' ? urlAttr : '/' + urlAttr.replace(/^\//, '');
		}
		var base = basePathPrefix();
		if (base.charAt(base.length - 1) !== '/') base += '/';
		return base + 'search-suggest';
	}

	function fetchSuggestions(q) {
		q = (q || '').trim();
		if (productsOnly && !queryMeetsMinChars(q)) {
			hide();
			return;
		}
		var u = suggestEndpoint() + '?q=' + encodeURIComponent(q);
		if (productsOnly) u += '&mode=products';
		fetch(u, { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var sug = data && data.suggestions ? data.suggestions : [];
				render(sug);
			})
			.catch(function () {
				hide();
			});
	}

	function scheduleFetch() {
		var q = (input.value || '').trim();
		if (productsOnly && !queryMeetsMinChars(q)) {
			hide();
			return;
		}
		if (debounceTimer) clearTimeout(debounceTimer);
		debounceTimer = setTimeout(function () {
			fetchSuggestions(q);
		}, 200);
	}

	function highlight(idx) {
		items.forEach(function (el, j) {
			el.classList.toggle('active', j === idx);
		});
	}

	input.addEventListener('focus', function () {
		if (productsOnly && !queryMeetsMinChars(input.value || '')) {
			hide();
			return;
		}
		scheduleFetch();
	});

	input.addEventListener('input', function () {
		scheduleFetch();
	});

	input.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			hide();
			return;
		}
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			if (!items.length) return;
			activeIndex = Math.min(activeIndex + 1, items.length - 1);
			highlight(activeIndex);
			return;
		}
		if (e.key === 'ArrowUp') {
			e.preventDefault();
			if (!items.length) return;
			activeIndex = Math.max(activeIndex - 1, 0);
			highlight(activeIndex);
			return;
		}
		if (e.key === 'Enter') {
			if (activeIndex >= 0 && items[activeIndex]) {
				e.preventDefault();
				items[activeIndex].click();
				return;
			}
			var q = (input.value || '').trim();
			if (q !== '') {
				e.preventDefault();
				window.location.href = resolveHref('products?q=' + encodeURIComponent(q));
			}
		}
	});

	document.addEventListener('click', function (e) {
		if (!root.contains(e.target)) hide();
	});

	if (root.hasAttribute('data-placeholder-phrases')) {
		initAnimatedSearchPlaceholder(root, input);
	}
	}
});
