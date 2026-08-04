/**
 * Shop collection page: filter accordions, grid toggle, toolbar sort (e.g. gifting), category checkboxes.
 */
(function () {
	'use strict';

	function initFilters() {
		var sidebar = document.querySelector('.products-filter-drawer') || document.querySelector('.products-sidebar');
		if (!sidebar) return;

		sidebar.addEventListener('click', function (e) {
			var btn = e.target.closest('.products-filter-head');
			if (!btn) return;

			e.preventDefault();
			var filterId = btn.getAttribute('data-filter');
			if (!filterId) return;

			var body = document.getElementById('filter-' + filterId);
			var toggleEl = btn.querySelector('.products-filter-toggle');
			if (!body || !toggleEl) return;

			var isCollapsed = body.classList.contains('products-filter-body--collapsed');

			if (isCollapsed) {
				body.classList.remove('products-filter-body--collapsed');
				toggleEl.textContent = '\u2212'; /* − */
				btn.setAttribute('aria-expanded', 'true');
			} else {
				body.classList.add('products-filter-body--collapsed');
				toggleEl.textContent = '+';
				btn.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function initSortToolbar() {
		document.querySelectorAll('#products-sort, #products-filter-sort').forEach(function (select) {
			select.addEventListener('change', function () {
				var url = new URL(window.location.href);
				if (select.value) {
					url.searchParams.set('sort', select.value);
				} else {
					url.searchParams.delete('sort');
				}
				url.searchParams.set('page', '1');
				window.location.href = url.toString();
			});
		});
	}

	function initFilterDrawer() {
		var section = document.getElementById('products-listing');
		var drawer = document.getElementById('products-filter-drawer');
		var openBtn = document.getElementById('products-filter-open');
		var closeBtn = document.getElementById('products-filter-close');
		var backdrop = document.getElementById('products-filter-backdrop');
		if (!section || !drawer || !openBtn) return;

		function open() {
			section.classList.add('is-filter-open');
			document.body.classList.add('products-filter-drawer-open');
			drawer.setAttribute('aria-hidden', 'false');
			openBtn.setAttribute('aria-expanded', 'true');
			if (backdrop) {
				backdrop.classList.add('is-visible');
				backdrop.setAttribute('aria-hidden', 'false');
			}
		}
		function close() {
			section.classList.remove('is-filter-open');
			document.body.classList.remove('products-filter-drawer-open');
			drawer.setAttribute('aria-hidden', 'true');
			openBtn.setAttribute('aria-expanded', 'false');
			if (backdrop) {
				backdrop.classList.remove('is-visible');
				backdrop.setAttribute('aria-hidden', 'true');
			}
		}

		openBtn.addEventListener('click', function (e) {
			e.preventDefault();
			open();
		});
		if (closeBtn) closeBtn.addEventListener('click', close);
		if (backdrop) backdrop.addEventListener('click', close);
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && section.classList.contains('is-filter-open')) close();
		});
	}

	function initGridToggle() {
		var grid = document.getElementById('products-grid');
		var wrap = document.querySelector('.products-grid-toggle');
		if (!grid || !wrap) return;

		wrap.addEventListener('click', function (e) {
			var btn = e.target.closest('.products-grid-btn');
			if (!btn) return;

			var cols = btn.getAttribute('data-cols');
			if (!cols) return;

			wrap.querySelectorAll('.products-grid-btn').forEach(function (b) {
				b.classList.remove('is-active');
			});
			btn.classList.add('is-active');
			grid.classList.toggle('cols-2', cols === '2');
		});
	}

	function formatRupee(n) {
		return '₹' + Math.round(n).toLocaleString('en-IN');
	}

	function initPriceRangeSliders() {
		var wrap = document.querySelector('.products-price-range-visual');
		if (!wrap) return;
		var cap = parseInt(wrap.getAttribute('data-price-max') || '200000', 10) || 200000;
		var rMin = document.getElementById('products-price-range-min');
		var rMax = document.getElementById('products-price-range-max');
		var iMin = document.getElementById('products-min-price-input');
		var iMax = document.getElementById('products-max-price-input');
		var dMin = wrap.querySelector('[data-range-min-display]');
		var dMax = wrap.querySelector('[data-range-max-display]');
		if (!rMin || !rMax || !iMin || !iMax) return;

		function clamp(v, lo, hi) {
			return Math.max(lo, Math.min(hi, v));
		}

		function syncDisplays(vMin, vMax) {
			if (dMin) dMin.textContent = formatRupee(vMin);
			if (dMax) {
				dMax.textContent = vMax >= cap ? 'Any' : formatRupee(vMax);
			}
		}

		function fromSliders() {
			var a = parseInt(rMin.value, 10) || 0;
			var b = parseInt(rMax.value, 10) || cap;
			if (a > b) {
				if (document.activeElement === rMin) {
					rMin.value = String(b);
					a = b;
				} else {
					rMax.value = String(a);
					b = a;
				}
			}
			iMin.value = a > 0 ? String(a) : '';
			if (b >= cap) {
				iMax.value = '';
			} else {
				iMax.value = String(b);
			}
			syncDisplays(a, b);
		}

		function fromInputs() {
			var a = parseInt(iMin.value, 10);
			var b = parseInt(iMax.value, 10);
			if (isNaN(a) || a < 0) a = 0;
			a = clamp(a, 0, cap);
			if (isNaN(b) || b < 0) {
				rMin.value = String(a);
				rMax.value = String(cap);
				syncDisplays(a, cap);
				return;
			}
			b = clamp(b, 0, cap);
			if (b < a) b = a;
			rMin.value = String(a);
			rMax.value = String(b);
			syncDisplays(a, b);
		}

		rMin.addEventListener('input', fromSliders);
		rMax.addEventListener('input', fromSliders);
		iMin.addEventListener('change', fromInputs);
		iMax.addEventListener('change', fromInputs);
		fromInputs();
	}

	function init() {
		initFilters();
		initFilterDrawer();
		initSortToolbar();
		initGridToggle();
		initPriceRangeSliders();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
