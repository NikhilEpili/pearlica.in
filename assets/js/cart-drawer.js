(function () {
	function initCartDrawer() {
		var drawer = document.getElementById('cart-drawer');
		var overlay = drawer && drawer.querySelector('.cart-drawer-overlay');
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
			if (!drawer) return;
			drawer.classList.add('is-open');
			drawer.setAttribute('aria-hidden', 'false');
			document.body.classList.add('cart-drawer-open');
			document.body.style.overflow = 'hidden';
		}

		function closeDrawer() {
			if (!drawer) return;
			drawer.classList.remove('is-open');
			drawer.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('cart-drawer-open');
			document.body.style.overflow = '';
		}

		try {
			var params = new URLSearchParams(window.location.search);
			if (params.get('open_cart') === '1') {
				openDrawer();
				params.delete('open_cart');
				var qs = params.toString();
				var next = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
				window.history.replaceState(null, '', next);
			}
		} catch (e) {
			// ignore
		}

		document.body.addEventListener('click', function (e) {
			if (e.target.closest('.cart-drawer-trigger')) {
				e.preventDefault();
				openDrawer();
				return;
			}
			var continueBtn = e.target.closest('.cart-drawer-close, a.cart-drawer-continue');
			if (e.target === overlay || e.target.closest('#cart-drawer .cart-drawer-close-btn') || continueBtn) {
				e.preventDefault();
				closeDrawer();
				if (continueBtn && continueBtn.tagName === 'A') {
					var href = continueBtn.getAttribute('href');
					if (href && href !== '#' && href !== '') {
						window.location.href = href;
					}
				}
				return;
			}
			var checkoutLink = e.target.closest('a.cart-drawer-btn-primary');
			if (checkoutLink && document.body.getAttribute('data-logged-in') !== '1') {
				e.preventDefault();
				closeDrawer();
				if (window.PearlicaUI && typeof PearlicaUI.openLogin === 'function') {
					PearlicaUI.openLogin({
						returnUrl: 'checkout',
						message: 'Sign in or create an account to complete your purchase.',
					});
				} else {
					window.location.href = checkoutLink.getAttribute('href') || (getCartBasePath() + 'login?return_url=checkout');
				}
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
						var isEmpty = replaceEl.querySelector('.cart-drawer-empty') !== null;
						if (isEmpty) {
							closeDrawer();
						}
					}
					var badgeEls = document.querySelectorAll('.nav-cart-badge');
					if (badgeEls.length) {
						badgeEls.forEach(function (el) {
							var count = 0;
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
	}

	if (document.body) {
		initCartDrawer();
	} else {
		document.addEventListener('DOMContentLoaded', initCartDrawer);
	}
})();
