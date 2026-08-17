(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var phone = document.getElementById('phone');
		if (!phone) return;

		function sanitize(raw) {
			var digits = String(raw || '').replace(/\D/g, '');
			if (digits.indexOf('91') === 0 && digits.length > 10) {
				digits = digits.slice(2);
			}
			return digits.slice(0, 10);
		}

		function sync() {
			var next = sanitize(phone.value);
			if (phone.value !== next) {
				phone.value = next;
			}
		}

		phone.addEventListener('input', sync);
		phone.addEventListener('paste', function () {
			setTimeout(sync, 0);
		});

		var form = phone.closest('form');
		if (form) {
			form.addEventListener('submit', sync);
		}
	});
})();
