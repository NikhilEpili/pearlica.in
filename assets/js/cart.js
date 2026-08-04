(function () {
	var basePath = '';

	function getForm() {
		return document.getElementById('checkout-cart-update-form') || document.getElementById('cart-update-form');
	}
	function getTotalEl() {
		return document.getElementById('cart-total-amount');
	}
	function getBasePath() {
		if (basePath !== undefined && basePath !== '') return basePath;
		var form = getForm();
		basePath = (form && form.getAttribute('data-base-path')) || '';
		if (basePath.length && basePath.charAt(basePath.length - 1) !== '/') basePath += '/';
		return basePath;
	}

	function collectCart() {
		var cart = {};
		var root = document.getElementById('checkout-cart-items');
		var inputs = root
			? root.querySelectorAll('.cart-qty-input')
			: document.querySelectorAll('.cart-qty-input');
		inputs.forEach(function (input) {
			var id = input.getAttribute('data-product-id');
			var qty = parseInt(input.value, 10);
			if (id && !isNaN(qty) && qty >= 0) cart[id] = qty;
		});
		return cart;
	}

	function sendUpdate(cart, done) {
		var formData = new FormData();
		formData.append('action', 'update');
		Object.keys(cart).forEach(function (id) {
			formData.append('cart[' + id + ']', cart[id]);
		});
		var xhr = new XMLHttpRequest();
		xhr.open('POST', getBasePath() + 'cart');
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.onload = function () {
			if (xhr.status !== 200) return;
			try {
				var data = JSON.parse(xhr.responseText);
				done(null, data);
			} catch (e) {
				done(e);
			}
		};
		xhr.onerror = function () { done(new Error('Network error')); };
		xhr.send(formData);
	}

	function updateUI(data) {
		var lineTotals = data.line_totals || {};
		if (data.line_totals) {
			document.querySelectorAll('.cart-item-card').forEach(function (card) {
				var id = card.getAttribute('data-product-id');
				if (lineTotals[id] != null) {
					var el = card.querySelector('.cart-line-total[data-product-id="' + id + '"]');
					if (el) el.textContent = '₹' + Number(lineTotals[id]).toLocaleString('en-IN');
				} else {
					card.remove();
				}
			});
		}
		var totalEl = getTotalEl();
		if (totalEl && data.total_amount != null) {
			totalEl.textContent = '₹' + Number(data.total_amount).toLocaleString('en-IN');
		}
		var badgeEls = document.querySelectorAll('.nav-cart-badge');
		if (data.total_items != null && badgeEls.length) {
			badgeEls.forEach(function (el) {
				el.textContent = data.total_items;
				el.style.display = data.total_items > 0 ? '' : 'none';
			});
		}
		if (data.total_items === 0) {
			location.reload();
		}
	}

	var debounceTimer;
	function onQtyChange() {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(function () {
			var cart = collectCart();
			var keys = Object.keys(cart);
			var hasPositive = keys.some(function (id) {
				return (parseInt(cart[id], 10) || 0) > 0;
			});
			// Empty object → nothing to POST. All zero qty → still POST so the server clears the cookie.
			if (keys.length === 0) return;
			if (!hasPositive) {
				cart = {};
			}
			sendUpdate(cart, function (err, data) {
				if (err || !data) return;
				if (document.body.classList.contains('checkout-page')) {
					window.location.reload();
					return;
				}
				updateUI(data);
			});
		}, 400);
	}

	document.body.addEventListener('change', function (e) {
		if (!e.target.matches('.cart-qty-input')) return;
		onQtyChange();
	});
	document.body.addEventListener('input', function (e) {
		if (!e.target.matches('.cart-qty-input')) return;
		onQtyChange();
	});

	document.body.addEventListener('click', function (e) {
		var stepper = e.target.closest('.cart-qty-stepper');
		if (!stepper) return;
		var input = stepper.querySelector('.cart-qty-input');
		if (!input) return;
		var min = parseInt(input.getAttribute('min'), 10) || 1;
		var max = parseInt(input.getAttribute('max'), 10) || 99;
		if (e.target.classList.contains('cart-qty-minus')) {
			e.preventDefault();
			var val = parseInt(input.value, 10) || min;
			if (val > min) {
				input.value = val - 1;
				input.dispatchEvent(new Event('change', { bubbles: true }));
			}
		} else if (e.target.classList.contains('cart-qty-plus')) {
			e.preventDefault();
			var val = parseInt(input.value, 10) || min;
			if (val < max) {
				input.value = val + 1;
				input.dispatchEvent(new Event('change', { bubbles: true }));
			}
		}
	});
})();
