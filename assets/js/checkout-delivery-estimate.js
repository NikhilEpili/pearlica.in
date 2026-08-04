/* Product page: compact PIN → short “Delivered in X–Y days”; PIN kept in field + localStorage. */
(function () {
	var STORAGE_KEY = 'pearlica_product_delivery_pin';

	document.addEventListener('DOMContentLoaded', function () {
		var pinCheck = document.getElementById('checkout-pin-check');
		var input = document.getElementById('checkout-delivery-pin');
		var btn = document.getElementById('checkout-delivery-check-btn');
		var result = document.getElementById('checkout-delivery-eta-result');
		if (!pinCheck || !input || !btn || !result) return;

		var body = document.body;
		var basePath = (body && body.getAttribute('data-base-path')) ? body.getAttribute('data-base-path') : '';
		if (basePath && basePath[basePath.length - 1] !== '/') basePath += '/';

		function apiUrl(relPath) {
			relPath = String(relPath || '').replace(/^\//, '');
			var bp = basePath || '';
			if (bp && bp[bp.length - 1] !== '/') bp += '/';
			var combined = bp + relPath;
			if (combined.charAt(0) !== '/') combined = '/' + combined;
			return combined;
		}

		function digitsOnly(s) {
			return String(s || '').replace(/\D/g, '');
		}

		function setLoading(loading) {
			btn.disabled = loading;
			btn.setAttribute('aria-busy', loading ? 'true' : 'false');
			btn.textContent = loading ? '…' : 'Check';
		}

		function renderMessage(text, muted) {
			var p = document.createElement('p');
			p.className = 'checkout-delivery-eta__text' + (muted ? ' checkout-delivery-eta__text--muted' : '');
			p.textContent = text;
			result.innerHTML = '';
			result.appendChild(p);
		}

		function formatEtaShort(d) {
			var min = d.min_days;
			var max = d.max_days;
			var hasMin = typeof min === 'number' && min > 0;
			var hasMax = typeof max === 'number' && max > 0;
			if (hasMin && hasMax) {
				if (min === max) {
					return 'Delivered in ~' + min + (min === 1 ? ' day' : ' days');
				}
				return 'Delivered in ~' + min + '–' + max + ' days';
			}
			if (d.ok) {
				return 'Delivery available to this PIN.';
			}
			return '';
		}

		function parseJsonSafe(text) {
			if (!text || typeof text !== 'string') return null;
			var t = text.trim();
			if (!t) return null;
			try {
				return JSON.parse(t);
			} catch (e) {
				return null;
			}
		}

		try {
			var saved = localStorage.getItem(STORAGE_KEY);
			if (saved && /^\d{6}$/.test(saved)) {
				input.value = saved;
			}
		} catch (e) {}

		function runCheck() {
			var pin = digitsOnly(input.value);
			input.value = pin;
			if (pin.length !== 6) {
				renderMessage('Enter a 6-digit PIN.', true);
				input.focus();
				return;
			}

			var csrf = body.getAttribute('data-checkout-csrf') || '';
			if (!csrf) {
				renderMessage('Sign in to check delivery.', true);
				return;
			}

			var fd = new FormData();
			fd.append('csrf_token', csrf);
			fd.append('pincode', pin);

			setLoading(true);
			fetch(apiUrl('checkout-delivery-estimate'), {
				method: 'POST',
				body: fd,
				credentials: 'same-origin',
			})
				.then(function (res) {
					return res.text().then(function (text) {
						return { status: res.status, data: parseJsonSafe(text), raw: text };
					});
				})
				.then(function (out) {
					var d = out.data;
					if (!d || typeof d !== 'object') {
						renderMessage('Something went wrong.', true);
						return;
					}
					if (out.status === 401) {
						renderMessage(d.error || 'Sign in to check delivery.', true);
						return;
					}
					if (out.status === 403 || out.status === 400) {
						renderMessage(d.error || 'Could not check this PIN.', true);
						return;
					}
					var shortLine = formatEtaShort(d);
					if (d.ok && shortLine) {
						input.value = pin;
						try {
							localStorage.setItem(STORAGE_KEY, pin);
						} catch (e) {}
						renderMessage(shortLine, false);
						return;
					}
					var msg = typeof d.message === 'string' ? d.message.trim() : '';
					if (!msg && typeof d.error === 'string') msg = d.error.trim();
					if (!msg) msg = 'No estimate right now.';
					renderMessage(msg, !d.ok);
					if (d.ok) {
						input.value = pin;
						try {
							localStorage.setItem(STORAGE_KEY, pin);
						} catch (e) {}
					}
				})
				.catch(function () {
					renderMessage('Network error.', true);
				})
				.finally(function () {
					setLoading(false);
				});
		}

		btn.addEventListener('click', runCheck);
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				runCheck();
			}
		});
		input.addEventListener('input', function () {
			var v = digitsOnly(input.value);
			if (v !== input.value) input.value = v;
		});
	});
})();
