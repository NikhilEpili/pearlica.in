(function () {
	var drawer = document.getElementById('cart-drawer');
	var overlay = drawer && drawer.querySelector('.cart-drawer-overlay');
	var panel = drawer && drawer.querySelector('.cart-drawer-panel');
	var replaceEl = document.getElementById('cart-drawer-replace');
	function getCartBasePath() {
		var form = document.getElementById('cart-update-form') || document.getElementById('checkout-cart-update-form');
		if (form && form.getAttribute('data-base-path')) {
			var p = form.getAttribute('data-base-path') || '';
			return p.length && p.charAt(p.length - 1) !== '/' ? p + '/' : p;
		}
		return '';
	}

	function openDrawer() {
		if (drawer) {
			drawer.classList.add('is-open');
			drawer.setAttribute('aria-hidden', 'false');
			document.body.style.overflow = 'hidden';
		}
	}

	function closeDrawer() {
		if (drawer) {
			drawer.classList.remove('is-open');
			drawer.setAttribute('aria-hidden', 'true');
			document.body.style.overflow = '';
		}
	}

	function refreshDrawerContent(callback) {
		if (!replaceEl) {
			if (callback) callback();
			return;
		}
		var xhr = new XMLHttpRequest();
		xhr.open('GET', getCartBasePath() + 'cart');
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.onload = function () {
			if (xhr.status === 200) {
				replaceEl.innerHTML = xhr.responseText;
			}
			if (callback) callback();
		};
		xhr.onerror = function () {
			if (callback) callback();
		};
		xhr.send();
	}

	document.body.addEventListener('click', function (e) {
		if (e.target.closest('.cart-drawer-trigger')) {
			e.preventDefault();
			openDrawer();
			return;
		}
		if (e.target === overlay || e.target.closest('.cart-drawer-close-btn') || e.target.classList.contains('cart-drawer-close')) {
			e.preventDefault();
			closeDrawer();
			return;
		}
		var removeLink = e.target.closest('.cart-drawer-remove[data-remove]');
		if (removeLink) {
			e.preventDefault();
			var id = removeLink.getAttribute('data-remove');
			if (!id) return;
			var xhr = new XMLHttpRequest();
			xhr.open('GET', getCartBasePath() + 'cart?remove=' + id);
			xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			xhr.onload = function () {
				if (xhr.status === 200 && replaceEl) {
					replaceEl.innerHTML = xhr.responseText;
					replaceEl.querySelector('.cart-drawer-empty');
					var isEmpty = replaceEl.querySelector('.cart-drawer-empty') !== null;
					if (isEmpty) {
						closeDrawer();
					}
				}
				var badgeEls = document.querySelectorAll('.nav-cart-badge');
				if (badgeEls.length) {
					badgeEls.forEach(function (el) {
						var count = 0;
						var totalEl = document.getElementById('cart-total-amount');
						if (replaceEl && xhr.responseText.indexOf('cart-drawer-empty') !== -1) {
							count = 0;
						} else if (replaceEl) {
							var inputs = replaceEl.querySelectorAll('.cart-qty-input');
							inputs.forEach(function (inp) { count += parseInt(inp.value, 10) || 0; });
						}
						el.textContent = count > 0 ? count : '';
						el.style.display = count > 0 ? '' : 'none';
					});
				}
				document.body.dispatchEvent(new CustomEvent('cart-updated', { detail: { removedId: id } }));
				var currentProductId = document.body.getAttribute('data-product-id');
				if (currentProductId && currentProductId === id) {
					window.location.reload();
				}
			};
			xhr.send();
		}
	});
})();
