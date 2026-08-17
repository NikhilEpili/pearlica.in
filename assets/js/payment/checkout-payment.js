/* Checkout: Razorpay (endpoints under payment/razorpay/). */
document.addEventListener('DOMContentLoaded', function () {
	var btn = document.getElementById('checkout-place-order');
	if (!btn) return;

	function uiToast(message, type) {
		if (window.PearlicaUI && PearlicaUI.toast) {
			PearlicaUI.toast(message, { type: type || 'info' });
		} else {
			window.alert(message);
		}
	}

	function uiAlert(message) {
		if (window.PearlicaUI && PearlicaUI.alert) {
			return PearlicaUI.alert(message, { type: 'error' });
		}
		window.alert(message);
		return Promise.resolve();
	}
	var basePath = (document.body && document.body.getAttribute('data-base-path')) ? document.body.getAttribute('data-base-path') : '';
	if (basePath && basePath[basePath.length - 1] !== '/') basePath += '/';

	/** Root-absolute path so fetch never resolves under /checkout/ (which caused 404 HTML after Razorpay). */
	function apiUrl(relPath) {
		relPath = String(relPath || '').replace(/^\//, '');
		var bp = basePath || '';
		if (bp && bp[bp.length - 1] !== '/') bp += '/';
		var combined = bp + relPath;
		if (combined.charAt(0) !== '/') combined = '/' + combined;
		return combined;
	}

	function isGuest() {
		return !document.body || document.body.getAttribute('data-logged-in') !== '1';
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
		});
	}

	/** Replace the guest "sign in" panel with the signed-in summary, no page reload. */
	function renderSignedInDelivery(profile) {
		var body = document.getElementById('checkout-delivery-body');
		if (!body || !profile) return;
		var name = String(profile.full_name || profile.email || '').trim();
		var contact = [profile.phone, profile.email].filter(function (part) {
			return String(part || '').trim() !== '';
		}).map(function (part) {
			return '<span>' + escapeHtml(part) + '</span>';
		}).join('');
		var address = [
			profile.address,
			profile.address_line_2,
			[profile.city, profile.state, profile.pincode].filter(function (part) {
				return String(part || '').trim() !== '';
			}).join(', '),
		].filter(function (part) {
			return String(part || '').trim() !== '';
		}).map(escapeHtml).join('<br>');
		body.innerHTML =
			'<div class="checkout-recipient-name">' + escapeHtml(name) + '</div>' +
			'<div class="checkout-contact-info">' + contact + '</div>' +
			'<div class="checkout-address-block" id="checkout-address-display">' +
			(address || 'Your delivery address will be collected before payment.') +
			'</div>';
		var actions = document.getElementById('checkout-delivery-actions');
		if (actions) {
			actions.innerHTML =
				'<a href="' + escapeHtml(apiUrl('profile')) + '" class="checkout-edit-link">Edit</a>' +
				'<a href="' + escapeHtml(apiUrl('logout')) + '" class="checkout-edit-link ms-2">Sign out</a>';
		}
	}

	/** Open the sign-in modal and run `resume` once the shopper is authenticated. */
	function requireSignIn(message, resume) {
		if (window.PearlicaUI && typeof PearlicaUI.openLogin === 'function') {
			PearlicaUI.openLogin({
				returnUrl: 'checkout',
				message: message || 'Sign in to complete your purchase securely.',
				onAuthenticated: function (auth) {
					if (auth && auth.profile) renderSignedInDelivery(auth.profile);
					if (typeof resume === 'function') resume(auth);
				},
			});
			return;
		}
		window.location.href = apiUrl('login?return_url=checkout');
	}

	var signInBtn = document.getElementById('checkout-signin-btn');
	if (signInBtn) {
		signInBtn.addEventListener('click', function () {
			requireSignIn('Sign in to complete your purchase securely.');
		});
	}

	var giftCb = document.getElementById('checkout-gift-packaging');
	var codCb = document.getElementById('checkout-cod');
	var codWrap = document.getElementById('checkout-cod-wrap');
	var codNote = document.getElementById('checkout-cod-unavailable-note');
	var onlineBlock = document.getElementById('checkout-online-pay-block');
	var payMethodHidden = document.getElementById('checkout-payment-method');
	var secureNote = document.getElementById('checkout-secure-note-block');
	var checkoutTotalPaise = 0;

	function giftPackagingPaise() {
		var n = parseInt(document.body.getAttribute('data-gift-packaging-paise') || '3000', 10);
		return n > 0 ? n : 3000;
	}

	function recalcCheckoutTotals() {
		var subtotalPaise = parseInt(document.body.getAttribute('data-subtotal-paise') || '0', 10);
		var discountPaise = 0;
		var appliedDiscount = document.getElementById('checkout-applied-discount-paise');
		if (appliedDiscount && appliedDiscount.value) {
			discountPaise = parseInt(appliedDiscount.value, 10) || 0;
		}
		var giftPaise = (giftCb && giftCb.checked) ? giftPackagingPaise() : 0;
		var freeMinPaise = parseInt(document.body.getAttribute('data-free-shipping-min-paise') || '70000', 10);
		var shipFeePaise = parseInt(document.body.getAttribute('data-shipping-fee-paise') || '19900', 10);

		function fmtInrFromPaise(paise, sign) {
			var v = (parseInt(paise || '0', 10) || 0) / 100;
			var s = Math.abs(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
			return (sign || '') + '₹' + s;
		}
		function fmtInrWholeFromPaise(paise) {
			var v = Math.round((parseInt(paise || '0', 10) || 0) / 100);
			return '₹' + v.toLocaleString('en-IN');
		}
		var netMerchPaise = Math.max(0, subtotalPaise - discountPaise);
		var deliveryPaise = netMerchPaise > 0 && netMerchPaise < freeMinPaise ? shipFeePaise : 0;
		var totalPaise = Math.max(0, netMerchPaise + deliveryPaise + giftPaise);

		var shipDisp = document.getElementById('checkout-shipping-display');
		if (shipDisp) {
			if (deliveryPaise > 0) {
				shipDisp.textContent = fmtInrWholeFromPaise(deliveryPaise);
				shipDisp.classList.remove('checkout-free-badge');
				shipDisp.classList.add('checkout-shipping-amount');
			} else {
				shipDisp.textContent = 'FREE';
				shipDisp.classList.add('checkout-free-badge');
				shipDisp.classList.remove('checkout-shipping-amount');
			}
		}

		var promoEl = document.getElementById('checkout-promo-value');
		if (promoEl) {
			promoEl.textContent = discountPaise > 0 ? fmtInrFromPaise(discountPaise, '−') : '—';
			if (discountPaise > 0) {
				promoEl.classList.add('checkout-promo-value--active');
			} else {
				promoEl.classList.remove('checkout-promo-value--active');
			}
		}
		var giftAmt = document.getElementById('checkout-gift-value');
		if (giftAmt) {
			giftAmt.textContent = giftPaise > 0 ? fmtInrFromPaise(giftPaise, '+') : '—';
		}
		var totalEl = document.getElementById('checkout-order-total');
		if (totalEl) totalEl.textContent = fmtInrWholeFromPaise(totalPaise);
		checkoutTotalPaise = totalPaise;
		syncCheckoutPayState();
	}

	function syncCheckoutPayState() {
		var codMax = parseInt(document.body.getAttribute('data-cod-max-paise') || '0', 10) || 0;
		var rpReady = document.body.getAttribute('data-razorpay-ready') === '1';
		var codAllowed = codMax > 0 && checkoutTotalPaise > 0 && checkoutTotalPaise <= codMax;
		if (codWrap) {
			if (codAllowed) {
				codWrap.removeAttribute('hidden');
			} else {
				codWrap.setAttribute('hidden', 'hidden');
			}
		}
		if (codNote) {
			if (!codAllowed && checkoutTotalPaise > 0) {
				codNote.removeAttribute('hidden');
			} else {
				codNote.setAttribute('hidden', 'hidden');
			}
		}
		if (!codAllowed && codCb && codCb.checked) {
			codCb.checked = false;
		}
		var codOn = !!(codCb && codCb.checked && codAllowed);
		if (payMethodHidden) {
			payMethodHidden.value = codOn ? 'cod' : 'razorpay';
		}
		if (onlineBlock) {
			if (codOn) {
				onlineBlock.setAttribute('hidden', 'hidden');
			} else {
				onlineBlock.removeAttribute('hidden');
			}
		}
		if (secureNote) {
			if (codOn) {
				secureNote.setAttribute('hidden', 'hidden');
			} else {
				secureNote.removeAttribute('hidden');
			}
		}
		var defaultLabel = btn.getAttribute('data-default-label') || 'Proceed';
		btn.textContent = codOn ? 'Place order' : defaultLabel;
		var canSubmit = codOn || rpReady;
		btn.disabled = !canSubmit;
		btn.setAttribute('aria-disabled', canSubmit ? 'false' : 'true');
	}

	if (giftCb) {
		giftCb.addEventListener('change', recalcCheckoutTotals);
	}
	if (codCb) {
		codCb.addEventListener('change', syncCheckoutPayState);
	}
	recalcCheckoutTotals();

	function selectedPaymentMethod() {
		var h = document.getElementById('checkout-payment-method');
		if (h && h.value) return String(h.value);
		return 'razorpay';
	}

	function parseJsonSafe(text) {
		if (!text || typeof text !== 'string') return null;
		var t = text.trim();
		if (t.length === 0) return null;
		try {
			return JSON.parse(t);
		} catch (e) {
			return null;
		}
	}

	function userFriendlyError(serverText) {
		if (!serverText || typeof serverText !== 'string') return 'Something went wrong. Please try again.';
		var s = serverText.trim();
		if (s.length > 200) s = s.substring(0, 200) + '…';
		if (/^\s*</.test(s) || s.indexOf('<!') !== -1) return 'Server returned an error. Please try again.';
		if (s.length > 80) return 'Something went wrong. Please try again.';
		return s;
	}

	function postForm(url, data) {
		var formData = new FormData();
		Object.keys(data).forEach(function (k) {
			var v = data[k];
			if (v !== undefined && v !== null) {
				formData.append(k, v);
			}
		});
		return fetch(apiUrl(url), { method: 'POST', body: formData, credentials: 'same-origin' })
			.then(function (r) {
				return r.text().then(function (text) {
					var parsed = parseJsonSafe(text);
					if (parsed !== null) {
						return { ok: r.ok, status: r.status, data: parsed };
					}
					return { ok: false, status: r.status, data: { error: userFriendlyError(text) } };
				});
			})
			.catch(function () {
				return { ok: false, status: 0, data: { error: 'Network error. Check your connection and try again.' } };
			});
	}

	function getAppliedPromoPayload(csrf) {
		var payload = { csrf_token: csrf };
		var promoCode = document.getElementById('checkout-applied-promo-code');
		var promoId = document.getElementById('checkout-applied-promo-id');
		var discountPaise = document.getElementById('checkout-applied-discount-paise');
		if (promoCode && promoCode.value && promoId && discountPaise) {
			payload.promo_code = promoCode.value;
			payload.promo_id = promoId.value || '';
			payload.discount_paise = discountPaise.value || '0';
		}
		payload.gift_packaging = (giftCb && giftCb.checked) ? '1' : '0';
		return payload;
	}

	function promoAppliedBlock() {
		return document.getElementById('checkout-promo-applied-block');
	}
	function promoEntryBlock() {
		return document.getElementById('checkout-promo-entry-block');
	}
	function setPromoUiApplied(showApplied, codeText, saveRupees) {
		var ab = promoAppliedBlock();
		var eb = promoEntryBlock();
		var codeEl = document.getElementById('checkout-display-promo-code');
		var amtEl = document.getElementById('checkout-display-promo-amount');
		if (showApplied) {
			if (ab) ab.removeAttribute('hidden');
			if (eb) eb.setAttribute('hidden', 'hidden');
			if (codeEl && codeText) codeEl.textContent = codeText;
			if (amtEl && saveRupees != null) {
				amtEl.textContent = '−₹' + Math.round(saveRupees).toLocaleString('en-IN');
			}
		} else {
			if (ab) ab.setAttribute('hidden', 'hidden');
			if (eb) eb.removeAttribute('hidden');
			if (codeEl) codeEl.textContent = '';
			if (amtEl) amtEl.textContent = '';
		}
	}

	var applyBtn = document.getElementById('checkout-promo-apply');
	var promoInput = document.getElementById('checkout-promo-code');
	if (applyBtn && promoInput && document.body) {
		applyBtn.addEventListener('click', function () {
			var code = (promoInput.value || '').trim();
			if (!code) {
				uiToast('Please enter a promo code', 'error');
				return;
			}
			var subtotalPaise = parseInt(document.body.getAttribute('data-subtotal-paise') || '0', 10);
			if (subtotalPaise <= 0) return;
			var csrf = btn.getAttribute('data-csrf-token') || '';
			applyBtn.disabled = true;
			applyBtn.textContent = 'Applying...';
			postForm('checkout-apply-promo', { code: code, subtotal_paise: subtotalPaise, csrf_token: csrf })
				.then(function (res) {
					var d = res && res.data ? res.data : {};
					if (res && res.ok && d.discount_paise !== undefined) {
						var discountPaise = parseInt(d.discount_paise, 10) || 0;
						var appliedCode = document.getElementById('checkout-applied-promo-code');
						var appliedId = document.getElementById('checkout-applied-promo-id');
						var appliedDiscount = document.getElementById('checkout-applied-discount-paise');
						var displayCode = d.promo_code || code;
						if (appliedCode) appliedCode.value = displayCode;
						if (appliedId) appliedId.value = (d.promo_id !== undefined) ? String(d.promo_id) : '';
						if (appliedDiscount) appliedDiscount.value = String(discountPaise);
						setPromoUiApplied(true, displayCode, discountPaise / 100);
						promoInput.value = '';
						recalcCheckoutTotals();
						uiToast(d.message || 'Discount applied', 'success');
					} else {
						uiToast(d.error || 'This promo code is not valid', 'error');
					}
				})
				.catch(function () {
					uiToast('Unable to apply promo code', 'error');
				})
				.finally(function () {
					applyBtn.disabled = false;
					applyBtn.textContent = 'Apply';
				});
		});
	}

	var removePromoBtn = document.getElementById('checkout-promo-remove');
	if (removePromoBtn && btn) {
		removePromoBtn.addEventListener('click', function () {
			var csrf = btn.getAttribute('data-csrf-token') || '';
			removePromoBtn.disabled = true;
			postForm('checkout-remove-promo', { csrf_token: csrf })
				.then(function (res) {
					var d = res && res.data ? res.data : {};
					if (res && res.ok) {
						var appliedCode = document.getElementById('checkout-applied-promo-code');
						var appliedId = document.getElementById('checkout-applied-promo-id');
						var appliedDiscount = document.getElementById('checkout-applied-discount-paise');
						if (appliedCode) appliedCode.value = '';
						if (appliedId) appliedId.value = '';
						if (appliedDiscount) appliedDiscount.value = '0';
						setPromoUiApplied(false);
						recalcCheckoutTotals();
					} else {
						uiToast(d.error || 'Could not remove promo code', 'error');
					}
				})
				.catch(function () {
					uiToast('Could not remove promo code', 'error');
				})
				.finally(function () {
					removePromoBtn.disabled = false;
				});
		});
	}

	btn.addEventListener('click', function () {
		function formatCheckoutAddress(addr) {
			if (!addr || typeof addr !== 'object') return '';
			var line1 = String(addr.address || '').trim();
			var line2 = String(addr.address_line_2 || '').trim();
			var cityState = [addr.city, addr.state, addr.pincode].filter(function (part) {
				return String(part || '').trim() !== '';
			}).join(', ');
			return [line1, line2, cityState].filter(function (part) { return part !== ''; }).join('\n');
		}

		function updateCheckoutAddressDisplay(d) {
			var el = document.getElementById('checkout-address-display');
			if (!el || !d || !d.address) return;
			var text = formatCheckoutAddress(d.address);
			if (!text) return;
			el.textContent = text;
			el.innerHTML = text.replace(/\n/g, '<br>');
			if (document.body) {
				document.body.setAttribute('data-shipping-complete', '1');
			}
		}

		function scrollToPaymentSection() {
			var target = document.querySelector('.checkout-payment-methods') || btn;
			if (target && typeof target.scrollIntoView === 'function') {
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}

		function proceedWithPayment() {
			var method = selectedPaymentMethod();
			var csrf = btn.getAttribute('data-csrf-token') || '';

			btn.disabled = true;
			btn.textContent = 'Please wait...';

			if (method === 'cod') {
				var codPayload = getAppliedPromoPayload(csrf);
				codPayload.payment_method = 'cod';
				postForm('checkout-place-cod', codPayload)
					.then(function (res) {
						var d = res && res.data ? res.data : {};
						if (res && res.ok && d.order_id) {
							window.location.href = apiUrl('order-success?order_id=' + encodeURIComponent(String(d.order_id)));
							return;
						}
						if (res && res.status === 401) {
							syncCheckoutPayState();
							requireSignIn(d.error || 'Please sign in to place your order.', startCheckout);
							return;
						}
						throw new Error(d.error || 'Unable to place your order');
					})
					.catch(function (e) {
						uiAlert(e.message || 'Unable to place your order');
					})
					.finally(function () {
						syncCheckoutPayState();
					});
				return;
			}

			if (method !== 'razorpay') {
				uiToast('Please choose a payment option.', 'error');
				syncCheckoutPayState();
				return;
			}

			var razorpayPayload = getAppliedPromoPayload(csrf);
			razorpayPayload.payment_method = 'razorpay';
			postForm('payment/razorpay/create-order', razorpayPayload)
				.then(function (res) {
					var d = res && res.data ? res.data : {};
					if (res && res.status === 401) {
						syncCheckoutPayState();
						requireSignIn(d.error || 'Please sign in to continue to payment.', startCheckout);
						return;
					}
					if (!res || !res.ok) {
						throw new Error(d.error || 'Unable to start payment');
					}
					var verifyNonce = d.verify_nonce ? String(d.verify_nonce) : '';
					var oid = String(d.razorpay_order_id || '').trim();
					var kid = String(d.key_id || '').trim();
					if (!kid || !oid || oid.indexOf('order_') !== 0) {
						throw new Error('Unable to start payment');
					}
					var pre = d.prefill && typeof d.prefill === 'object' ? d.prefill : {};
					var prefill = {};
					if (pre.name && String(pre.name).trim()) prefill.name = String(pre.name).trim();
					if (pre.email && String(pre.email).trim()) prefill.email = String(pre.email).trim();
					var cRaw = pre.contact ? String(pre.contact).replace(/\s/g, '') : '';
					if (/^\+91[6-9]\d{9}$/.test(cRaw)) {
						prefill.contact = cRaw;
					}
					var options = {
						key: kid,
						order_id: oid,
						amount: d.amount,
						currency: d.currency || 'INR',
						name: 'Pearlica',
						description: 'Pearlica order payment',
						handler: function (response) {
							btn.disabled = true;
							btn.textContent = 'Confirming payment…';
							var vbody = {
								csrf_token: csrf,
								razorpay_payment_id: response.razorpay_payment_id || '',
								razorpay_order_id: response.razorpay_order_id || '',
								razorpay_signature: response.razorpay_signature || ''
							};
							if (verifyNonce) vbody.verify_nonce = verifyNonce;
							postForm('payment/razorpay/verify', vbody).then(function (ver) {
								var v = ver && ver.data ? ver.data : {};
								if (ver && ver.ok && v.order_id) {
									window.location.href = apiUrl('order-success?order_id=' + encodeURIComponent(v.order_id));
									return;
								}
								throw new Error(v.error || 'Payment verification failed');
							}).catch(function (e) {
								uiAlert(e.message || 'Payment verification failed');
								syncCheckoutPayState();
							});
						},
						modal: {
							ondismiss: function () {
								syncCheckoutPayState();
							}
						},
						theme: { color: '#0a0a0a' }
					};
					if (Object.keys(prefill).length > 0) {
						options.prefill = prefill;
					}
					function openWhenReady(attempts) {
						if (typeof Razorpay === 'function') {
							try {
								(new Razorpay(options)).open();
							} catch (err) {
								uiAlert('Payment could not open. Please refresh the page and try again.');
							}
							return;
						}
						if (attempts > 80) {
							uiAlert('Payment could not load. Please refresh the page.');
							return;
						}
						setTimeout(function () { openWhenReady(attempts + 1); }, 40);
					}
					openWhenReady(0);
				})
				.catch(function (e) {
					uiAlert(e.message || 'Unable to start payment');
				})
				.finally(function () {
					syncCheckoutPayState();
				});
		}

		/**
		 * Gate every order attempt: sign in first, then confirm the delivery address,
		 * and only then hand off to COD or Razorpay.
		 */
		function startCheckout() {
			if (window.PearlicaUI && typeof PearlicaUI.ensureCheckoutReady === 'function') {
				PearlicaUI.ensureCheckoutReady({
					returnUrl: 'checkout',
					dismissable: true,
					onAuthenticated: function (auth) {
						if (auth && auth.profile) renderSignedInDelivery(auth.profile);
					},
					onReady: function (d) {
						updateCheckoutAddressDisplay(d);
						scrollToPaymentSection();
						proceedWithPayment();
					},
				});
				return;
			}

			if (isGuest()) {
				window.location.href = apiUrl('login?return_url=checkout');
				return;
			}

			proceedWithPayment();
		}

		startCheckout();
	});
});
