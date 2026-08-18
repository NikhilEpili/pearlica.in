/**
 * Mobile bottom dock: Search & More as cart-style off-canvas drawers, body scroll lock.
 */
document.addEventListener('DOMContentLoaded', function () {
	var dock = document.getElementById('mobile-bottom-dock');
	if (!dock) return;

	var searchDrawer = document.getElementById('mobile-search-sheet');
	var moreDrawer = document.getElementById('mobile-more-sheet');
	var mq = window.matchMedia('(max-width: 991.98px)');
	var lockedScrollY = 0;
	var pageLocked = false;

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

	function lockPageScroll() {
		if (pageLocked) {
			return;
		}
		pageLocked = true;
		lockedScrollY = window.scrollY || window.pageYOffset || 0;
		document.documentElement.classList.add('mobile-sheet-open');
		document.body.classList.add('mobile-sheet-open');
		document.body.style.position = 'fixed';
		document.body.style.top = '-' + lockedScrollY + 'px';
		document.body.style.left = '0';
		document.body.style.right = '0';
		document.body.style.width = '100%';
		document.body.style.overflow = 'hidden';
	}

	function unlockPageScroll() {
		if (!pageLocked) {
			return;
		}
		pageLocked = false;
		document.documentElement.classList.remove('mobile-sheet-open');
		document.body.classList.remove('mobile-sheet-open');
		document.body.style.position = '';
		document.body.style.top = '';
		document.body.style.left = '';
		document.body.style.right = '';
		document.body.style.width = '';
		document.body.style.overflow = '';
		window.scrollTo(0, lockedScrollY);
	}

	function syncBodyLock() {
		var searchOpen = searchDrawer && searchDrawer.classList.contains('is-open');
		var moreOpen = moreDrawer && moreDrawer.classList.contains('is-open');
		var cartOpen = document.body.classList.contains('cart-drawer-open');
		if (searchOpen || moreOpen || cartOpen) {
			lockPageScroll();
		} else {
			unlockPageScroll();
		}
	}

	function trapDrawerScroll(root) {
		if (!root) {
			return;
		}
		var inner = root.querySelector('.cart-drawer-panel-inner');
		root.addEventListener('wheel', function (e) {
			e.stopPropagation();
			if (!inner) {
				e.preventDefault();
				return;
			}
			if (!inner.contains(e.target) && e.target !== inner) {
				e.preventDefault();
				return;
			}
			var delta = e.deltaY;
			var atTop = inner.scrollTop <= 0 && delta < 0;
			var atBottom = inner.scrollTop + inner.clientHeight >= inner.scrollHeight - 1 && delta > 0;
			if (atTop || atBottom) {
				e.preventDefault();
			}
		}, { passive: false });

		root.addEventListener('touchmove', function (e) {
			e.stopPropagation();
			if (inner && (inner.contains(e.target) || e.target === inner)) {
				return;
			}
			e.preventDefault();
		}, { passive: false });
	}

	trapDrawerScroll(searchDrawer);
	trapDrawerScroll(moreDrawer);

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
