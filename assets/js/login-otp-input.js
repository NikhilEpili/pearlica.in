(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var otp = document.getElementById('otp');
		if (!otp) return;

		function maxOtpLen() {
			var m = parseInt(otp.getAttribute('maxlength') || '4', 10);
			if (isNaN(m) || m < 4) return 4;
			if (m > 10) return 10;
			return m;
		}

		function sanitizeOtp(raw) {
			return String(raw || '').replace(/\D/g, '').slice(0, maxOtpLen());
		}

		function sync() {
			var next = sanitizeOtp(otp.value);
			if (otp.value !== next) otp.value = next;
		}

		otp.addEventListener('input', sync);
		otp.addEventListener('paste', function () {
			setTimeout(sync, 0);
		});

		var form = document.getElementById('otp-form');
		if (form) {
			form.addEventListener('submit', function () {
				sync();
			});
		}
	});
})();

