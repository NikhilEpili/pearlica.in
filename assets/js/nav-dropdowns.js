/**
 * Nav dropdowns: on viewports where hover is not reliable (e.g. < 992px),
 * toggle .is-open on click so Categories, Collections, and user menu open/close on tap.
 */
(function () {
	'use strict';

	var MEDIA = window.matchMedia('(max-width: 991px)');

	function setup() {
		if (!MEDIA.matches) return;

		document.querySelectorAll('.nav-item-dropdown .nav-link, .nav-user-dropdown .nav-dropdown-trigger').forEach(function (trigger) {
			trigger.addEventListener('click', function (e) {
				var parent = trigger.closest('.nav-item-dropdown, .nav-user-dropdown');
				if (!parent) return;
				e.preventDefault();
				var isOpen = parent.classList.toggle('is-open');
				trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			});
		});

		document.addEventListener('click', function (e) {
			if (!MEDIA.matches) return;
			if (e.target.closest('.nav-item-dropdown, .nav-user-dropdown')) return;
			document.querySelectorAll('.nav-item-dropdown.is-open, .nav-user-dropdown.is-open').forEach(function (el) {
				el.classList.remove('is-open');
				var t = el.querySelector('.nav-link, .nav-dropdown-trigger');
				if (t) t.setAttribute('aria-expanded', 'false');
			});
		});
	}

	function setupOffcanvasNavClose() {
		var ocEl = document.getElementById('offcanvasNavbar');
		if (!ocEl) return;
		ocEl.addEventListener('click', function (e) {
			var a = e.target.closest('a');
			if (!a) return;
			var drop = a.closest('.nav-item-dropdown');
			if (drop) {
				var catTrigger = drop.querySelector(':scope > .nav-link');
				if (catTrigger && a === catTrigger) return;
			}
			if (typeof bootstrap === 'undefined' || !bootstrap.Offcanvas) return;
			var inst = bootstrap.Offcanvas.getInstance(ocEl);
			if (inst) inst.hide();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			setup();
			setupOffcanvasNavClose();
		});
	} else {
		setup();
		setupOffcanvasNavClose();
	}
})();
