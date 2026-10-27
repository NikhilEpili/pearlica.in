(function () {
	'use strict';

	var BANNER_ID = 'cookie-consent-banner';
	var COOKIE_NAME = 'pearlica_cookie_consent';
	var COOKIE_DAYS = 365;

	function getCookie(name) {
		var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function setCookie(name, value, days) {
		var d = new Date();
		d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
		document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
	}

	function hasConsent() {
		var v = getCookie(COOKIE_NAME);
		return v === 'accepted' || v === 'necessary';
	}

	function showBanner() {
		var el = document.getElementById(BANNER_ID);
		if (el) {
			el.hidden = false;
		}
		document.documentElement.classList.add('cookie-consent-open');
	}

	function hideBanner() {
		var el = document.getElementById(BANNER_ID);
		if (el) {
			el.hidden = true;
		}
		document.documentElement.classList.remove('cookie-consent-open');
	}

	function bindConsentButton(id, value) {
		var btn = document.getElementById(id);
		if (!btn) {
			return;
		}
		btn.addEventListener('click', function () {
			setCookie(COOKIE_NAME, value, COOKIE_DAYS);
			hideBanner();
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		if (hasConsent()) {
			hideBanner();
			return;
		}
		showBanner();
		bindConsentButton('cookie-consent-accept', 'accepted');
		bindConsentButton('cookie-consent-necessary', 'necessary');
	});
})();
