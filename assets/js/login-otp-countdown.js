(function () {
	'use strict';

	var wrap = document.getElementById('otp-countdown-wrap');
	if (!wrap) return;
	var expiresAt = parseInt(wrap.getAttribute('data-expires-at'), 10);
	if (!expiresAt) return;

	var el = document.getElementById('otp-countdown');
	var expiredMsg = document.getElementById('otp-expired-msg');
	var form = document.getElementById('otp-form');

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function tick() {
		var now = Math.floor(Date.now() / 1000);
		var left = expiresAt - now;
		if (left <= 0) {
			if (el) el.textContent = '0:00';
			if (wrap) wrap.classList.add('d-none');
			if (expiredMsg) expiredMsg.classList.remove('d-none');
			if (form) form.classList.add('d-none');
			return;
		}
		var m = Math.floor(left / 60);
		var s = left % 60;
		if (el) el.textContent = pad(m) + ':' + pad(s);
		setTimeout(tick, 1000);
	}

	tick();
})();
