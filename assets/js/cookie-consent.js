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

	function showBanner() {
		var el = document.getElementById(BANNER_ID);
		if (el) {
			el.hidden = false;
		}
	}

	function hideBanner() {
		var el = document.getElementById(BANNER_ID);
		if (el) {
			el.hidden = true;
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		if (getCookie(COOKIE_NAME) === 'accepted') {
			hideBanner();
			return;
		}
		showBanner();

		var btn = document.getElementById('cookie-consent-accept');
		if (btn) {
			btn.addEventListener('click', function () {
				setCookie(COOKIE_NAME, 'accepted', COOKIE_DAYS);
				hideBanner();
			});
		}
	});
})();
