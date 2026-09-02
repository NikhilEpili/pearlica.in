/**
 * Nav dropdowns: on viewports where hover is not reliable (e.g. < 992px),
 * toggle .is-open on click so Categories, Collections, and user menu open/close on tap.
 */
(function () {
	'use strict';

	var MEDIA = window.matchMedia('(max-width: 991px)');

	function isMobileNav() {
		return MEDIA.matches;
	}

	function closeAllDropdowns(except) {
		document.querySelectorAll('.nav-item-dropdown.is-open, .nav-user-dropdown.is-open').forEach(function (el) {
			if (except && el === except) {
				return;
			}
			el.classList.remove('is-open');
			var t = el.querySelector('.nav-link, .nav-dropdown-trigger');
			if (t) {
				t.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function onTriggerClick(e) {
		if (!isMobileNav()) {
			return;
		}
		var trigger = e.currentTarget;
		var parent = trigger.closest('.nav-item-dropdown, .nav-user-dropdown');
		if (!parent) {
			return;
		}
		e.preventDefault();
		var willOpen = !parent.classList.contains('is-open');
		closeAllDropdowns(willOpen ? parent : null);
		if (willOpen) {
			parent.classList.add('is-open');
			trigger.setAttribute('aria-expanded', 'true');
		} else {
			parent.classList.remove('is-open');
			trigger.setAttribute('aria-expanded', 'false');
		}
	}

	function bindTriggers() {
		document.querySelectorAll('.nav-item-dropdown .nav-link, .nav-user-dropdown .nav-dropdown-trigger').forEach(function (trigger) {
			if (trigger.dataset.navDropdownBound === '1') {
				return;
			}
			trigger.dataset.navDropdownBound = '1';
			trigger.addEventListener('click', onTriggerClick);
		});
	}

	function setupOutsideClick() {
		if (document.documentElement.dataset.navDropdownOutsideBound === '1') {
			return;
		}
		document.documentElement.dataset.navDropdownOutsideBound = '1';
		document.addEventListener('click', function (e) {
			if (!isMobileNav()) {
				return;
			}
			if (e.target.closest('.nav-item-dropdown, .nav-user-dropdown')) {
				return;
			}
			closeAllDropdowns(null);
		});
	}

	function setupOffcanvasNavClose() {
		var ocEl = document.getElementById('offcanvasNavbar');
		if (!ocEl || ocEl.dataset.navDropdownBound === '1') {
			return;
		}
		ocEl.dataset.navDropdownBound = '1';
		ocEl.addEventListener('click', function (e) {
			var a = e.target.closest('a');
			if (!a) {
				return;
			}
			var drop = a.closest('.nav-item-dropdown');
			if (drop) {
				var dropTrigger = drop.querySelector(':scope > .nav-link');
				if (dropTrigger && a === dropTrigger) {
					return;
				}
			}
			if (typeof bootstrap === 'undefined' || !bootstrap.Offcanvas) {
				return;
			}
			var inst = bootstrap.Offcanvas.getInstance(ocEl);
			if (inst) {
				inst.hide();
			}
		});
		ocEl.addEventListener('hidden.bs.offcanvas', function () {
			closeAllDropdowns(null);
		});
	}

	function init() {
		bindTriggers();
		setupOutsideClick();
		setupOffcanvasNavClose();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
