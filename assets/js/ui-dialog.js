/**
 * Pearlica storefront UI: toasts, alerts, password auth, addresses.
 */
(function (global) {
	'use strict';

	var toastContainer, alertModal, loginModal, addressModal;
	var alertResolve = null;
	var loginReturnUrl = 'profile';
	var loginOnAuthenticated = null;
	var addressOnSaved = null;
	var addressDismissable = true;
	var otpTimer = null;
	var resendTimer = null;
	var savedAddresses = [];
	var selectedAddressId = 0;

	function basePath() {
		var bp = document.body && document.body.getAttribute('data-base-path');
		if (!bp) return '/';
		return bp.charAt(bp.length - 1) === '/' ? bp : bp + '/';
	}

	function apiUrl(rel) {
		rel = String(rel || '').replace(/^\//, '');
		var combined = basePath() + rel;
		return combined.charAt(0) === '/' ? combined : '/' + combined;
	}

	function csrfToken() {
		return (document.body && document.body.getAttribute('data-login-csrf')) || '';
	}

	function otpLength() {
		var n = parseInt(document.body.getAttribute('data-login-otp-length') || '4', 10);
		return isNaN(n) || n < 4 ? 4 : n;
	}

	function isLoggedIn() {
		return document.body && document.body.getAttribute('data-logged-in') === '1';
	}

	function isRegistrationComplete() {
		return document.body && document.body.getAttribute('data-registration-complete') === '1';
	}

	function isShippingComplete() {
		return document.body && document.body.getAttribute('data-shipping-complete') === '1';
	}

	function setProfileFlags(reg, ship) {
		if (!document.body) return;
		document.body.setAttribute('data-registration-complete', reg ? '1' : '0');
		document.body.setAttribute('data-shipping-complete', ship ? '1' : '0');
	}

	function setLoggedInUI(loggedIn) {
		if (document.body) {
			document.body.setAttribute('data-logged-in', loggedIn ? '1' : '0');
			document.body.setAttribute('data-is-guest', loggedIn ? '0' : '1');
		}
		['pearlica-auth-signout-wrap', 'pearlica-address-signout-wrap'].forEach(function (id) {
			var el = document.getElementById(id);
			if (el) el.hidden = !loggedIn;
		});
	}

	function refreshProfileStatus() {
		if (!isLoggedIn()) return Promise.resolve(null);
		return postProfile('status', {}).then(function (d) {
			if (d && d.ok) {
				setProfileFlags(!!d.registration_complete, !!d.shipping_complete);
			}
			return d;
		});
	}

	function lockBody(on) {
		document.body.classList.toggle('pearlica-modal-open', !!on);
	}

	function openModal(el) {
		if (!el) return;
		el.classList.add('is-open');
		el.setAttribute('aria-hidden', 'false');
		lockBody(true);
	}

	function closeModal(el) {
		if (!el) return;
		el.classList.remove('is-open');
		el.setAttribute('aria-hidden', 'true');
		if (!document.querySelector('.pearlica-modal.is-open')) lockBody(false);
	}

	function sanitizePhone(raw) {
		var d = String(raw || '').replace(/\D/g, '');
		if (d.indexOf('91') === 0 && d.length > 10) d = d.slice(2);
		return d.slice(0, 10);
	}

	function sanitizeOtp(raw) {
		return String(raw || '').replace(/\D/g, '').slice(0, otpLength());
	}

	function sanitizePincode(raw) {
		return String(raw || '').replace(/\D/g, '').slice(0, 6);
	}

	function toast(message, opts) {
		opts = opts || {};
		if (!toastContainer) toastContainer = document.getElementById('pearlica-toast-container');
		if (!toastContainer || !message) return;
		var type = opts.type || 'info';
		var el = document.createElement('div');
		el.className = 'pearlica-toast pearlica-toast--' + type;
		el.innerHTML = '<span class="pearlica-toast__body"></span><button type="button" class="pearlica-toast__close">&times;</button>';
		el.querySelector('.pearlica-toast__body').textContent = String(message);
		toastContainer.appendChild(el);
		function dismiss() {
			el.classList.add('is-leaving');
			setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 280);
		}
		el.querySelector('.pearlica-toast__close').addEventListener('click', dismiss);
		requestAnimationFrame(function () { el.classList.add('is-visible'); });
		setTimeout(dismiss, type === 'error' ? 5000 : 3500);
	}

	function alertDialog(message, opts) {
		opts = opts || {};
		if (!alertModal) alertModal = document.getElementById('pearlica-alert-modal');
		if (!alertModal) { global.alert(message); return Promise.resolve(); }
		document.getElementById('pearlica-alert-title').textContent = opts.title || 'Notice';
		document.getElementById('pearlica-alert-message').textContent = String(message || '');
		openModal(alertModal);
		return new Promise(function (resolve) { alertResolve = resolve; });
	}

	function showAuthError(msg) {
		var box = document.getElementById('pearlica-auth-error');
		if (!box) return;
		if (!msg) { box.hidden = true; box.textContent = ''; return; }
		box.hidden = false;
		box.textContent = String(msg);
	}

	function showAddressError(msg) {
		var box = document.getElementById('pearlica-address-error');
		if (!box) return;
		if (!msg) { box.hidden = true; box.textContent = ''; return; }
		box.hidden = false;
		box.textContent = String(msg);
	}

	function showAuthStep(step) {
		['login', 'register', 'register-verify', 'forgot', 'forgot-reset'].forEach(function (s) {
			var el = document.getElementById('pearlica-auth-step-' + s);
			if (!el) return;
			var active = s === step;
			el.hidden = !active;
			// Disable fields in hidden steps so password managers only see the active form.
			el.querySelectorAll('input, select, textarea, button').forEach(function (ctrl) {
				if (ctrl.hasAttribute('data-keep-enabled')) return;
				ctrl.disabled = !active;
			});
		});
		showAuthError('');
		if (step === 'forgot-reset') {
			var fp = document.getElementById('pearlica-forgot-password');
			var fpc = document.getElementById('pearlica-forgot-password-confirm');
			if (fp) fp.value = '';
			if (fpc) fpc.value = '';
		}
		var modalPanel = document.querySelector('#pearlica-login-modal .pearlica-login-panel');
		if (modalPanel) modalPanel.scrollTop = 0;
	}

	function fieldValue(id) {
		var el = document.getElementById(id);
		return el ? String(el.value == null ? '' : el.value) : '';
	}

	function validateForgotResetFields() {
		var otp = sanitizeOtp(fieldValue('pearlica-forgot-otp'));
		var password = fieldValue('pearlica-forgot-password');
		var passwordConfirm = fieldValue('pearlica-forgot-password-confirm');
		var otpLen = parseInt((document.getElementById('pearlica-forgot-otp') || {}).getAttribute('maxlength') || '4', 10) || 4;
		if (!otp || otp.length !== otpLen) {
			return { error: 'Please enter the ' + otpLen + '-digit code from your SMS.', fieldId: 'pearlica-forgot-otp' };
		}
		if (!password) {
			return { error: 'Please enter a password.', fieldId: 'pearlica-forgot-password' };
		}
		if (password.length < 8) {
			return { error: 'Password must be at least 8 characters.', fieldId: 'pearlica-forgot-password' };
		}
		if (!passwordConfirm) {
			return { error: 'Please confirm your password.', fieldId: 'pearlica-forgot-password-confirm' };
		}
		if (password !== passwordConfirm) {
			return { error: 'Passwords do not match.', fieldId: 'pearlica-forgot-password-confirm' };
		}
		return { error: null, fieldId: null, otp: otp, password: password, password_confirm: passwordConfirm };
	}

	function postAuth(action, data) {
		var fd = new FormData();
		fd.append('csrf_token', csrfToken());
		fd.append('action', action);
		fd.append('return_url', loginReturnUrl);
		Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(apiUrl('login-api'), { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected response.' }; }); })
			.catch(function () { return { ok: false, error: 'Network error. Check your connection and try again.' }; });
	}

	function postProfile(action, data) {
		var fd = new FormData();
		fd.append('csrf_token', csrfToken());
		fd.append('action', action);
		Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(apiUrl('profile-api'), { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected response.' }; }); })
			.catch(function () { return { ok: false, error: 'Network error. Check your connection and try again.' }; });
	}

	function startCountdown(expiresAt, labelId, wrapId) {
		if (otpTimer) clearTimeout(otpTimer);
		var el = document.getElementById(labelId);
		var wrap = wrapId ? document.getElementById(wrapId) : null;
		function tick() {
			var left = (expiresAt || 0) - Math.floor(Date.now() / 1000);
			if (left <= 0) {
				if (el) el.textContent = '0:00';
				return;
			}
			var m = Math.floor(left / 60);
			var s = left % 60;
			if (el) el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
			otpTimer = setTimeout(tick, 1000);
		}
		if (wrap) wrap.hidden = false;
		tick();
	}

	function formatMmSs(totalSeconds) {
		var left = Math.max(0, totalSeconds | 0);
		var m = Math.floor(left / 60);
		var s = left % 60;
		return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
	}

	function startForgotResendCountdown(resendAt) {
		if (resendTimer) clearTimeout(resendTimer);
		var waitEl = document.getElementById('pearlica-forgot-resend-wait');
		var countEl = document.getElementById('pearlica-forgot-resend-countdown');
		var btn = document.getElementById('pearlica-forgot-resend-btn');
		function showReady() {
			if (waitEl) waitEl.hidden = true;
			if (btn) {
				btn.hidden = false;
				btn.disabled = false;
			}
		}
		function tick() {
			var left = (resendAt || 0) - Math.floor(Date.now() / 1000);
			if (left <= 0) {
				showReady();
				return;
			}
			if (waitEl) waitEl.hidden = false;
			if (btn) {
				btn.hidden = true;
				btn.disabled = true;
			}
			if (countEl) countEl.textContent = formatMmSs(left);
			resendTimer = setTimeout(tick, 1000);
		}
		tick();
	}

	function applyForgotOtpSent(d) {
		document.getElementById('pearlica-forgot-phone-mask').textContent = d.phone_mask || 'your number';
		showAuthStep('forgot-reset');
		startCountdown(d.expires_at, 'pearlica-forgot-otp-countdown', 'pearlica-forgot-otp-countdown-wrap');
		var resendAt = d.resend_at || (Math.floor(Date.now() / 1000) + 60);
		startForgotResendCountdown(resendAt);
	}

	function applyAuthResponse(d) {
		if (!d) return;
		setProfileFlags(!!d.registration_complete, !!d.shipping_complete);
	}

	function finishLogin(d) {
		applyAuthResponse(d);
		setLoggedInUI(true);
		toast(d.message || 'Signed in successfully.', { type: 'success' });
		var resume = loginOnAuthenticated;
		loginOnAuthenticated = null;
		closeLogin();
		if (resume) {
			resume(d);
			return;
		}
		if (loginReturnUrl === 'checkout' && !d.shipping_complete) {
			openAddress({
				message: 'Add or choose a delivery address before payment.',
				onSaved: function () {
					window.location.href = apiUrl('checkout');
				},
			});
			return;
		}
		if (d.redirect) {
			window.location.href = d.redirect.charAt(0) === '/' ? d.redirect : apiUrl(d.redirect);
		} else {
			window.location.reload();
		}
	}

	function prefillRegisterPhone(fromLogin) {
		var loginPhone = document.getElementById('pearlica-auth-login-phone');
		var regPhone = document.getElementById('pearlica-reg-phone');
		if (!regPhone) return;
		var p = sanitizePhone(fromLogin || (loginPhone ? loginPhone.value : ''));
		if (p) regPhone.value = p;
	}

	function registerFieldOrder() {
		return [
			'pearlica-reg-full-name',
			'pearlica-reg-email',
			'pearlica-reg-phone',
			'pearlica-reg-birthday',
			'pearlica-reg-gender',
			'pearlica-reg-password',
			'pearlica-reg-password-confirm',
		];
	}

	function getRegisterValues() {
		return {
			full_name: (document.getElementById('pearlica-reg-full-name') || {}).value || '',
			email: (document.getElementById('pearlica-reg-email') || {}).value || '',
			phone: sanitizePhone((document.getElementById('pearlica-reg-phone') || {}).value || ''),
			birthday: (document.getElementById('pearlica-reg-birthday') || {}).value || '',
			gender: (document.getElementById('pearlica-reg-gender') || {}).value || '',
			password: (document.getElementById('pearlica-reg-password') || {}).value || '',
			password_confirm: (document.getElementById('pearlica-reg-password-confirm') || {}).value || '',
		};
	}

	function isValidRegisterBirthday(raw) {
		if (!raw || !/^\d{4}-\d{2}-\d{2}$/.test(raw)) return false;
		var parts = raw.split('-');
		var y = parseInt(parts[0], 10);
		var mo = parseInt(parts[1], 10);
		var d = parseInt(parts[2], 10);
		if (y < 1900 || y > new Date().getFullYear()) return false;
		var dt = new Date(y, mo - 1, d);
		if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return false;
		var today = new Date();
		today.setHours(0, 0, 0, 0);
		return dt <= today;
	}

	function validateRegisterField(fieldId, values) {
		values = values || getRegisterValues();
		switch (fieldId) {
			case 'pearlica-reg-full-name':
				if (!String(values.full_name).trim()) return 'Please enter your full name.';
				break;
			case 'pearlica-reg-email':
				if (!String(values.email).trim()) return 'Please enter your email address.';
				if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(values.email).trim())) {
					return 'Please enter a valid email address.';
				}
				break;
			case 'pearlica-reg-phone':
				if (values.phone.length !== 10) return 'Please enter a valid 10-digit mobile number.';
				break;
			case 'pearlica-reg-birthday':
				if (!isValidRegisterBirthday(values.birthday)) return 'Please enter a valid date of birth.';
				break;
			case 'pearlica-reg-gender':
				if (!values.gender) return 'Please select your gender.';
				break;
			case 'pearlica-reg-password':
				if (!values.password) return 'Please enter a password.';
				if (values.password.length < 8) return 'Password must be at least 8 characters.';
				break;
			case 'pearlica-reg-password-confirm':
				if (!values.password_confirm) return 'Please confirm your password.';
				if (values.password !== values.password_confirm) return 'Passwords do not match.';
				break;
			default:
				break;
		}
		return null;
	}

	function validateRegisterForm() {
		var values = getRegisterValues();
		var order = registerFieldOrder();
		for (var i = 0; i < order.length; i++) {
			var err = validateRegisterField(order[i], values);
			if (err) return { error: err, fieldId: order[i] };
		}
		return { error: null, fieldId: null };
	}

	function focusRegisterField(fieldId) {
		var el = document.getElementById(fieldId);
		if (!el) return;
		el.focus();
		if (typeof el.select === 'function' && el.type !== 'date') {
			el.select();
		}
	}

	function submitRegisterForm() {
		var validation = validateRegisterForm();
		if (validation.error) {
			showAuthError(validation.error);
			if (validation.fieldId) focusRegisterField(validation.fieldId);
			return;
		}
		showAuthError('');
		var values = getRegisterValues();
		postAuth('register_send_otp', values).then(function (d) {
			if (d && d.ok) {
				document.getElementById('pearlica-reg-phone-mask').textContent = d.phone_mask || 'your number';
				startCountdown(d.expires_at, 'pearlica-reg-otp-countdown', 'pearlica-reg-otp-countdown-wrap');
				showAuthStep('register-verify');
				return;
			}
			showAuthError((d && d.error) || 'Could not start registration.');
		});
	}

	function handleRegisterEnter(e) {
		if (e.key !== 'Enter') return;
		var target = e.target;
		if (!target || target.tagName === 'BUTTON' || target.tagName === 'TEXTAREA') return;
		var form = document.getElementById('pearlica-auth-register-form');
		if (!form || !form.contains(target)) return;
		e.preventDefault();

		var fieldId = target.id;
		var order = registerFieldOrder();
		var idx = order.indexOf(fieldId);
		if (idx === -1) return;

		var err = validateRegisterField(fieldId);
		if (err) {
			showAuthError(err);
			return;
		}
		showAuthError('');

		if (idx < order.length - 1) {
			focusRegisterField(order[idx + 1]);
			return;
		}
		submitRegisterForm();
	}

	function openLogin(opts) {
		opts = opts || {};
		loginReturnUrl = opts.returnUrl || 'profile';
		loginOnAuthenticated = typeof opts.onAuthenticated === 'function' ? opts.onAuthenticated : null;
		if (!loginModal) loginModal = document.getElementById('pearlica-login-modal');
		if (!loginModal) {
			window.location.href = apiUrl('login?return_url=' + encodeURIComponent(loginReturnUrl));
			return Promise.resolve(false);
		}
		if (isLoggedIn()) {
			var resume = loginOnAuthenticated;
			loginOnAuthenticated = null;
			if (resume) {
				resume(null);
			} else if (opts.returnUrl) {
				window.location.href = apiUrl(opts.returnUrl);
			}
			return Promise.resolve(true);
		}
		showAuthStep('login');
		var ctx = document.getElementById('pearlica-login-context');
		if (ctx) {
			if (opts.message) { ctx.textContent = opts.message; ctx.hidden = false; }
			else { ctx.hidden = true; }
		}
		if (opts.phone) {
			var lp = document.getElementById('pearlica-auth-login-phone');
			if (lp) lp.value = sanitizePhone(opts.phone);
		}
		openModal(loginModal);
		setTimeout(function () {
			var el = document.getElementById('pearlica-auth-login-phone');
			if (el) el.focus();
		}, 100);
		return Promise.resolve(true);
	}

	function closeLogin() {
		if (!loginModal) loginModal = document.getElementById('pearlica-login-modal');
		closeModal(loginModal);
		showAuthStep('login');
		loginOnAuthenticated = null;
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
		});
	}

	function renderSavedAddresses() {
		var wrap = document.getElementById('pearlica-saved-addresses-wrap');
		var list = document.getElementById('pearlica-saved-addresses-list');
		var form = document.getElementById('pearlica-address-form');
		if (!wrap || !list) return;
		if (!savedAddresses.length) {
			wrap.hidden = true;
			if (form) form.hidden = false;
			return;
		}
		wrap.hidden = false;
		list.innerHTML = '';
		savedAddresses.forEach(function (addr) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'pearlica-saved-address-card' + (selectedAddressId === addr.id ? ' is-selected' : '');
			btn.innerHTML =
				'<div class="pearlica-saved-address-card__label">' + escapeHtml(addr.label || 'Address') + (addr.is_default ? ' · Default' : '') + '</div>' +
				'<div class="pearlica-saved-address-card__line">' +
				escapeHtml(addr.address) + (addr.address_line_2 ? ', ' + escapeHtml(addr.address_line_2) : '') + '<br>' +
				escapeHtml(addr.city) + ', ' + escapeHtml(addr.state) + ' ' + escapeHtml(addr.pincode) +
				'</div>';
			btn.addEventListener('click', function () {
				selectedAddressId = addr.id;
				renderSavedAddresses();
				postProfile('checkout_select_address', { address_id: String(addr.id) }).then(function (d) {
					if (d && d.ok) {
						setProfileFlags(isRegistrationComplete(), true);
						toast('Address selected.', { type: 'success' });
						var onSaved = addressOnSaved;
						closeAddress();
						if (onSaved) onSaved(d);
					} else if (!addressSessionExpired(d)) {
						showAddressError((d && d.error) || 'Could not select address.');
					}
				});
			});
			list.appendChild(btn);
		});
	}

	function resetAddressForm() {
		['pearlica-addr-label', 'pearlica-addr-line1', 'pearlica-addr-line2', 'pearlica-addr-city', 'pearlica-addr-pincode'].forEach(function (id) {
			var el = document.getElementById(id);
			if (el) el.value = '';
		});
		var state = document.getElementById('pearlica-addr-state');
		if (state) state.value = '';
	}

	function openAddress(opts) {
		opts = opts || {};
		if (!addressModal) addressModal = document.getElementById('pearlica-address-modal');
		if (!addressModal) return Promise.resolve(false);
		addressOnSaved = typeof opts.onSaved === 'function' ? opts.onSaved : null;
		addressDismissable = opts.dismissable !== false;
		var ctx = document.getElementById('pearlica-address-context');
		if (ctx) {
			ctx.textContent = opts.message || 'Choose or add a delivery address before payment.';
		}
		showAddressError('');
		selectedAddressId = opts.selectedAddressId ? (opts.selectedAddressId | 0) : 0;
		savedAddresses = [];
		resetAddressForm();
		renderSavedAddresses();
		var form = document.getElementById('pearlica-address-form');
		if (form) form.hidden = false;
		openModal(addressModal);
		postProfile('list_addresses', {}).then(function (d) {
			if (d && d.ok && d.addresses) {
				savedAddresses = d.addresses;
				if (!selectedAddressId && d.checkout_address_id) {
					selectedAddressId = d.checkout_address_id | 0;
				}
				renderSavedAddresses();
				if (savedAddresses.length) {
					if (form) form.hidden = true;
				}
			}
		});
		return Promise.resolve(true);
	}

	/** Session dropped mid-checkout: bounce back to sign in and replay the address step. */
	function addressSessionExpired(d) {
		if (!d || d.ok || !/sign in|session expired/i.test(String(d.error || ''))) return false;
		var resume = addressOnSaved;
		closeAddress();
		setLoggedInUI(false);
		openLogin({
			returnUrl: 'checkout',
			message: 'Your session expired. Please sign in again to place your order.',
			onAuthenticated: function () {
				openAddress({
					message: 'Confirm where we should deliver this order.',
					onSaved: resume,
				});
			},
		});
		return true;
	}

	function closeAddress() {
		if (!addressModal) addressModal = document.getElementById('pearlica-address-modal');
		closeModal(addressModal);
		showAddressError('');
		addressOnSaved = null;
	}

	function ensureCheckoutReady(opts) {
		opts = opts || {};
		var returnUrl = opts.returnUrl || 'checkout';

		function requireSignIn(message) {
			return openLogin({
				returnUrl: returnUrl,
				message: message || 'Sign in or create an account to complete your purchase.',
				onAuthenticated: function (auth) {
					if (typeof opts.onAuthenticated === 'function') opts.onAuthenticated(auth);
					ensureCheckoutReady(opts);
				},
			});
		}

		if (!isLoggedIn()) {
			return requireSignIn();
		}
		return refreshProfileStatus().then(function (status) {
			if (!status || !status.ok) {
				setLoggedInUI(false);
				return requireSignIn('Your session expired. Please sign in again to place your order.');
			}
			if (!status.registration_complete) {
				toast('Please complete your profile (name, email, date of birth, gender) before checkout.', { type: 'error' });
				window.location.href = apiUrl('profile');
				return false;
			}
			if (status.shipping_complete && opts.skipAddressIfComplete !== false) {
				if (typeof opts.onReady === 'function') opts.onReady(status);
				return true;
			}
			return openAddress({
				message: status.shipping_complete
					? 'Choose a delivery address or add a new one.'
					: 'Add a delivery address to continue.',
				dismissable: opts.dismissable !== false,
				selectedAddressId: status.checkout_address_id || 0,
				onSaved: opts.onReady,
			});
		});
	}

	function bindAuth() {
		showAuthStep('login');

		document.querySelectorAll('#pearlica-auth-login-phone, #pearlica-reg-phone, #pearlica-forgot-phone').forEach(function (inp) {
			inp.addEventListener('input', function () {
				var n = sanitizePhone(inp.value);
				if (inp.value !== n) inp.value = n;
			});
		});

		var loginForm = document.getElementById('pearlica-auth-login-form');
		if (loginForm) {
			loginForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var phone = sanitizePhone(document.getElementById('pearlica-auth-login-phone').value);
				var password = document.getElementById('pearlica-auth-login-password').value;
				postAuth('login', { phone: phone, password: password }).then(function (d) {
					if (d && d.ok) {
						setLoggedInUI(true);
						finishLogin(d);
						return;
					}
					showAuthError((d && d.error) || 'Sign in failed.');
				});
			});
		}

		var regForm = document.getElementById('pearlica-auth-register-form');
		if (regForm) {
			regForm.addEventListener('submit', function (e) {
				e.preventDefault();
				submitRegisterForm();
			});
			regForm.addEventListener('keydown', handleRegisterEnter);
		}

		var regVerifyForm = document.getElementById('pearlica-auth-register-verify-form');
		if (regVerifyForm) {
			regVerifyForm.addEventListener('submit', function (e) {
				e.preventDefault();
				postAuth('register_verify', { otp: sanitizeOtp(document.getElementById('pearlica-reg-otp').value) }).then(function (d) {
					if (d && d.ok) {
						setLoggedInUI(true);
						finishLogin(d);
						return;
					}
					showAuthError((d && d.error) || 'Verification failed.');
				});
			});
		}

		var forgotForm = document.getElementById('pearlica-auth-forgot-form');
		if (forgotForm) {
			forgotForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var phone = sanitizePhone(document.getElementById('pearlica-forgot-phone').value);
				postAuth('forgot_send_otp', { phone: phone }).then(function (d) {
					if (d && d.ok) {
						applyForgotOtpSent(d);
						return;
					}
					showAuthError((d && d.error) || 'Could not send code.');
				});
			});
		}

		var forgotResetForm = document.getElementById('pearlica-auth-forgot-reset-form');
		if (forgotResetForm) {
			forgotResetForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var validation = validateForgotResetFields();
				if (validation.error) {
					showAuthError(validation.error);
					var focusEl = document.getElementById(validation.fieldId);
					if (focusEl) focusEl.focus();
					return;
				}
				showAuthError('');
				postAuth('forgot_reset', {
					otp: validation.otp,
					password: validation.password,
					password_confirm: validation.password_confirm,
				}).then(function (d) {
					if (d && d.ok) {
						setLoggedInUI(true);
						finishLogin(d);
						return;
					}
					if (d && d.step === 'forgot') {
						showAuthStep('forgot');
					}
					showAuthError((d && d.error) || 'Could not reset password.');
				});
			});
		}

		var forgotResendBtn = document.getElementById('pearlica-forgot-resend-btn');
		if (forgotResendBtn) {
			forgotResendBtn.addEventListener('click', function () {
				forgotResendBtn.disabled = true;
				postAuth('forgot_resend_otp', {}).then(function (d) {
					if (d && d.ok) {
						showAuthError('');
						var otpInput = document.getElementById('pearlica-forgot-otp');
						if (otpInput) otpInput.value = '';
						applyForgotOtpSent(d);
						toast('A new code has been sent.', { type: 'success' });
						return;
					}
					forgotResendBtn.disabled = false;
					if (d && d.wait_seconds) {
						startForgotResendCountdown(Math.floor(Date.now() / 1000) + (d.wait_seconds | 0));
					}
					if (d && d.step === 'forgot') {
						showAuthStep('forgot');
					}
					showAuthError((d && d.error) || 'Could not resend code.');
				});
			});
		}

		var showReg = document.getElementById('pearlica-auth-show-register');
		if (showReg) showReg.addEventListener('click', function () {
			prefillRegisterPhone();
			showAuthStep('register');
		});
		var showLogin = document.getElementById('pearlica-auth-show-login');
		if (showLogin) showLogin.addEventListener('click', function () { showAuthStep('login'); });
		var forgotBtn = document.getElementById('pearlica-auth-forgot-btn');
		if (forgotBtn) forgotBtn.addEventListener('click', function () {
			var p = sanitizePhone(document.getElementById('pearlica-auth-login-phone').value);
			var fp = document.getElementById('pearlica-forgot-phone');
			if (fp && p) fp.value = p;
			showAuthStep('forgot');
		});
		var forgotBack = document.getElementById('pearlica-forgot-back');
		if (forgotBack) forgotBack.addEventListener('click', function () { showAuthStep('login'); });
		var forgotResetBack = document.getElementById('pearlica-forgot-reset-back');
		if (forgotResetBack) forgotResetBack.addEventListener('click', function () { showAuthStep('forgot'); });
		var regBack = document.getElementById('pearlica-reg-back');
		if (regBack) regBack.addEventListener('click', function () { showAuthStep('register'); });

		var addAddrBtn = document.getElementById('pearlica-add-address-btn');
		if (addAddrBtn) addAddrBtn.addEventListener('click', function () {
			var form = document.getElementById('pearlica-address-form');
			if (form) form.hidden = false;
		});

		var addrForm = document.getElementById('pearlica-address-form');
		if (addrForm) {
			addrForm.addEventListener('submit', function (e) {
				e.preventDefault();
				postProfile('checkout_save_address', {
					label: document.getElementById('pearlica-addr-label').value,
					address: document.getElementById('pearlica-addr-line1').value,
					address_line_2: document.getElementById('pearlica-addr-line2').value,
					city: document.getElementById('pearlica-addr-city').value,
					state: document.getElementById('pearlica-addr-state').value,
					pincode: sanitizePincode(document.getElementById('pearlica-addr-pincode').value),
				}).then(function (d) {
					if (d && d.ok) {
						setProfileFlags(isRegistrationComplete(), !!d.shipping_complete);
						toast(d.message || 'Address saved.', { type: 'success' });
						var onSaved = addressOnSaved;
						closeAddress();
						if (onSaved) {
							onSaved(d);
						} else {
							window.location.reload();
						}
						return;
					}
					if (!addressSessionExpired(d)) {
						showAddressError((d && d.error) || 'Could not save address.');
					}
				});
			});
		}

		document.getElementById('pearlica-addr-pincode') && document.getElementById('pearlica-addr-pincode').addEventListener('input', function (e) {
			e.target.value = sanitizePincode(e.target.value);
		});
	}

	function bindModals() {
		alertModal = document.getElementById('pearlica-alert-modal');
		loginModal = document.getElementById('pearlica-login-modal');
		addressModal = document.getElementById('pearlica-address-modal');

		document.addEventListener('click', function (e) {
			if (e.target.closest('[data-pearlica-modal-close]')) {
				closeLogin();
			}
			if (addressDismissable && e.target.closest('[data-pearlica-address-dismiss]')) {
				closeAddress();
			}
			if (e.target.id === 'pearlica-alert-ok') {
				closeModal(alertModal);
				if (alertResolve) { alertResolve(); alertResolve = null; }
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') return;
			if (loginModal && loginModal.classList.contains('is-open')) closeLogin();
			else if (addressModal && addressModal.classList.contains('is-open') && addressDismissable) closeAddress();
		});

		bindAuth();

		var params = new URLSearchParams(window.location.search);
		if (params.get('auth') === 'login') {
			openLogin({ returnUrl: params.get('return_url') || 'profile' });
		}
	}

	global.PearlicaUI = {
		toast: toast,
		alert: alertDialog,
		openLogin: openLogin,
		closeLogin: closeLogin,
		openAddress: openAddress,
		closeAddress: closeAddress,
		ensureCheckoutReady: ensureCheckoutReady,
		isLoggedIn: isLoggedIn,
		isRegistrationComplete: isRegistrationComplete,
		isShippingComplete: isShippingComplete,
	};

	document.addEventListener('DOMContentLoaded', bindModals);
})(window);
