/**
 * Mobile bottom dock: Search & More as cart-style off-canvas drawers, body scroll lock.
 */
document.addEventListener('DOMContentLoaded', function () {
	var dock = document.getElementById('mobile-bottom-dock');
	if (!dock) return;

	var searchDrawer = document.getElementById('mobile-search-sheet');
	var moreDrawer = document.getElementById('mobile-more-sheet');
	var mq = window.matchMedia('(max-width: 991.98px)');

	function setActiveDock() {
		if (!mq.matches) return;
		var path = window.location.pathname || '';
		dock.querySelectorAll('.mobile-bottom-dock__item').forEach(function (el) {
			el.classList.remove('mobile-bottom-dock__item--active');
		});
		var homeEl = dock.querySelector('[data-mobile-dock="home"]');
		var shopEl = dock.querySelector('[data-mobile-dock="shop"]');
		var cartEl = dock.querySelector('[data-mobile-dock="cart"]');
		var isCart = /\/checkout$/.test(path) || path.endsWith('/checkout');
		var isShop = path.indexOf('/products') !== -1 || (path.indexOf('/product') !== -1 && path.indexOf('/products') === -1);
		if (isCart) {
			if (cartEl) cartEl.classList.add('mobile-bottom-dock__item--active');
		} else if (isShop) {
			if (shopEl) shopEl.classList.add('mobile-bottom-dock__item--active');
		} else if (homeEl) {
			homeEl.classList.add('mobile-bottom-dock__item--active');
		}
	}

	setActiveDock();
	window.addEventListener('popstate', setActiveDock);

	var openBtnSearch = dock.querySelector('[data-mobile-dock-open="search"]');
	var openBtnMore = dock.querySelector('[data-mobile-dock-open="more"]');

	function syncBodyLock() {
		var searchOpen = searchDrawer && searchDrawer.classList.contains('is-open');
		var moreOpen = moreDrawer && moreDrawer.classList.contains('is-open');
		var cartOpen = document.body.classList.contains('cart-drawer-open');
		document.body.style.overflow = searchOpen || moreOpen || cartOpen ? 'hidden' : '';
	}

	function openSearchDrawer(triggerBtn) {
		if (!searchDrawer) return;
		searchDrawer.classList.add('is-open');
		searchDrawer.setAttribute('aria-hidden', 'false');
		if (triggerBtn) {
			triggerBtn.setAttribute('aria-expanded', 'true');
		}
		syncBodyLock();
		window.setTimeout(function () {
			var inp = searchDrawer.querySelector('[data-site-search-input]');
			if (inp) inp.focus();
		}, 320);
	}

	function closeSheets() {
		if (searchDrawer) {
			searchDrawer.classList.remove('is-open');
			searchDrawer.setAttribute('aria-hidden', 'true');
		}
		if (moreDrawer) {
			moreDrawer.classList.remove('is-open');
			moreDrawer.setAttribute('aria-hidden', 'true');
		}
		if (openBtnSearch) openBtnSearch.setAttribute('aria-expanded', 'false');
		if (openBtnMore) openBtnMore.setAttribute('aria-expanded', 'false');
		syncBodyLock();
	}

	mq.addEventListener('change', function () {
		if (!mq.matches) {
			closeSheets();
		}
		setActiveDock();
	});

	if (openBtnSearch) {
		openBtnSearch.addEventListener('click', function () {
			closeSheets();
			openSearchDrawer(openBtnSearch);
		});
	}
	if (openBtnMore) {
		openBtnMore.addEventListener('click', function () {
			closeSheets();
			if (!moreDrawer) return;
			moreDrawer.classList.add('is-open');
			moreDrawer.setAttribute('aria-hidden', 'false');
			openBtnMore.setAttribute('aria-expanded', 'true');
			syncBodyLock();
		});
	}

	document.querySelectorAll('[data-mobile-dock-close]').forEach(function (el) {
		el.addEventListener('click', function () {
			closeSheets();
		});
	});

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		var searchOpen = searchDrawer && searchDrawer.classList.contains('is-open');
		var moreOpen = moreDrawer && moreDrawer.classList.contains('is-open');
		if (searchOpen || moreOpen) closeSheets();
	});

	function closeSheetOnChipNav(root) {
		if (!root) return;
		root.querySelectorAll('a.mobile-dock-chip').forEach(function (a) {
			a.addEventListener('click', function () {
				closeSheets();
			});
		});
	}
	closeSheetOnChipNav(searchDrawer);
	closeSheetOnChipNav(moreDrawer);
});
