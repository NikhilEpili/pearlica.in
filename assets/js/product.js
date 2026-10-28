/**
 * Product detail page: gallery, add to cart state, sticky mobile CTA bar.
 */
document.addEventListener('DOMContentLoaded', function () {
	var galleryRoot = document.querySelector('[data-product-gallery]');
	if (galleryRoot) {
		var frame = galleryRoot.querySelector('[data-gallery-frame]');
		var viewport = galleryRoot.querySelector('[data-gallery-viewport]');
		var slides = galleryRoot.querySelectorAll('[data-gallery-slide]');
		var prevBtn = galleryRoot.querySelector('[data-gallery-prev]');
		var nextBtn = galleryRoot.querySelector('[data-gallery-next]');
		var fullscreenBtn = galleryRoot.querySelector('[data-gallery-fullscreen]');
		var slideCount = slides.length;

		if (viewport && slideCount > 0) {
			function slideWidth() {
				return viewport.clientWidth || 1;
			}

			function activeIndex() {
				var w = slideWidth();
				if (w <= 0) return 0;
				var i = Math.round(viewport.scrollLeft / w);
				if (i < 0) i = 0;
				if (i >= slideCount) i = slideCount - 1;
				return i;
			}

			function syncNav(i) {
				if (!prevBtn && !nextBtn) return;
				var atStart = i <= 0;
				var atEnd = i >= slideCount - 1;
				if (prevBtn) {
					prevBtn.disabled = atStart;
					prevBtn.setAttribute('aria-disabled', atStart ? 'true' : 'false');
				}
				if (nextBtn) {
					nextBtn.disabled = atEnd;
					nextBtn.setAttribute('aria-disabled', atEnd ? 'true' : 'false');
				}
			}

			function goTo(i) {
				i = Math.max(0, Math.min(slideCount - 1, i));
				var w = slideWidth();
				viewport.scrollTo({ left: i * w, behavior: 'smooth' });
				syncNav(i);
			}

			if (prevBtn) {
				prevBtn.addEventListener('click', function () {
					goTo(activeIndex() - 1);
				});
			}
			if (nextBtn) {
				nextBtn.addEventListener('click', function () {
					goTo(activeIndex() + 1);
				});
			}

			var rafScheduled = false;
			function onScrollTick() {
				syncNav(activeIndex());
			}
			viewport.addEventListener('scroll', function () {
				if (rafScheduled) return;
				rafScheduled = true;
				requestAnimationFrame(function () {
					rafScheduled = false;
					onScrollTick();
				});
			}, { passive: true });

			viewport.addEventListener('scrollend', onScrollTick);

			window.addEventListener('resize', function () {
				var i = activeIndex();
				var w = slideWidth();
				viewport.scrollLeft = i * w;
				onScrollTick();
			});

			viewport.addEventListener('keydown', function (e) {
				if (slideCount < 2) return;
				if (e.key === 'ArrowRight') {
					e.preventDefault();
					goTo(activeIndex() + 1);
				} else if (e.key === 'ArrowLeft') {
					e.preventDefault();
					goTo(activeIndex() - 1);
				}
			});

			onScrollTick();
		}

		var fsTarget = frame || viewport;
		if (fullscreenBtn && fsTarget) {
			if (typeof fsTarget.requestFullscreen === 'function') {
				fullscreenBtn.addEventListener('click', function () {
					fsTarget.requestFullscreen().catch(function () {});
				});
			} else {
				fullscreenBtn.classList.add('is-hidden');
			}
		}
	}

	var addToCartForm = document.getElementById('add-to-cart-form');
	var addToCartBtn = document.getElementById('add-to-cart-btn');
	if (addToCartForm && addToCartBtn) {
		addToCartForm.addEventListener('submit', function () {
			addToCartBtn.disabled = true;
			addToCartBtn.textContent = 'Added';
		});
	}

	var mainCtas = document.getElementById('product-main-ctas');
	var stickyCta = document.getElementById('product-sticky-cta');
	/* Sticky bar vs in-page CTAs: IntersectionObserver uses the full viewport, so the row still
	   "intersects" when it sits behind the dock + sticky bar. Use overlap with the usable area above that chrome. */
	if (mainCtas && stickyCta) {
		var ctaTarget = mainCtas.querySelector('.product-cta-btns') || mainCtas;
		var mqMobile = window.matchMedia('(max-width: 991.98px)');
		var stickySyncRaf = false;

		/* Only reserve the dock (+ padding). Do not subtract the sticky bar height: when the bar is
		   translated off-screen, that band is still visible, and subtracting dock+sticky made overlap
		   look "too small" so the duplicate bar stayed up even with in-page CTAs on screen. */
		function dockBottomReservePx() {
			var dock = document.getElementById('mobile-bottom-dock');
			var dh = 0;
			if (dock) {
				var r = dock.getBoundingClientRect();
				dh = r.height > 0 ? r.height : dock.offsetHeight;
			}
			if (!dh) {
				dh = 56;
			}
			return Math.ceil(dh + 12);
		}

		function viewportHeightPx() {
			var vv = window.visualViewport;
			if (vv && vv.height > 0) {
				return vv.height;
			}
			return window.innerHeight || document.documentElement.clientHeight || 0;
		}

		function mainCtasUsableOverlapPx() {
			var rect = ctaTarget.getBoundingClientRect();
			if (rect.height <= 0 || rect.width <= 0) {
				return 0;
			}
			var vh = viewportHeightPx();
			var usableBottom = Math.max(72, vh - dockBottomReservePx());
			return Math.min(rect.bottom, usableBottom) - Math.max(rect.top, 0);
		}

		function syncStickyCta() {
			stickySyncRaf = false;
			if (!mqMobile.matches) {
				stickyCta.classList.remove('is-visible');
				stickyCta.setAttribute('aria-hidden', 'true');
				return;
			}
			var overlap = mainCtasUsableOverlapPx();
			var inPageCtasReadable = overlap >= 10;
			var showSticky = !inPageCtasReadable;
			stickyCta.classList.toggle('is-visible', showSticky);
			stickyCta.setAttribute('aria-hidden', showSticky ? 'false' : 'true');
		}

		function scheduleSync() {
			if (stickySyncRaf) {
				return;
			}
			stickySyncRaf = true;
			requestAnimationFrame(syncStickyCta);
		}

		syncStickyCta();
		requestAnimationFrame(function () {
			requestAnimationFrame(syncStickyCta);
		});
		window.addEventListener('scroll', scheduleSync, { passive: true });
		document.addEventListener('scroll', scheduleSync, { passive: true, capture: true });
		window.addEventListener('resize', scheduleSync);
		var vv = window.visualViewport;
		if (vv) {
			vv.addEventListener('resize', scheduleSync);
			vv.addEventListener('scroll', scheduleSync);
		}
		window.addEventListener('orientationchange', scheduleSync);
		window.addEventListener('load', scheduleSync, { once: true });
		if (typeof mqMobile.addEventListener === 'function') {
			mqMobile.addEventListener('change', scheduleSync);
		} else if (typeof mqMobile.addListener === 'function') {
			mqMobile.addListener(scheduleSync);
		}
		if (typeof ResizeObserver !== 'undefined') {
			var ro = new ResizeObserver(scheduleSync);
			ro.observe(mainCtas);
			ro.observe(stickyCta);
			var dockEl = document.getElementById('mobile-bottom-dock');
			if (dockEl) {
				ro.observe(dockEl);
			}
		}
	}

	var reviewPicker = document.querySelector('[data-review-star-picker]');
	var reviewForm = document.getElementById('product-review-form');
	var ratingInput = document.getElementById('review-rating-input');
	if (reviewPicker && reviewForm && ratingInput) {
		var starBtns = reviewPicker.querySelectorAll('[data-star-value]');
		var ratingError = document.getElementById('review-rating-error');

		function hideRatingError() {
			if (ratingError) {
				ratingError.classList.add('d-none');
			}
		}

		function paintStars(upTo) {
			starBtns.forEach(function (b) {
				var v = parseInt(b.getAttribute('data-star-value'), 10);
				b.classList.toggle('is-lit', !isNaN(v) && v <= upTo);
			});
		}

		function syncFromInput() {
			var sel = parseInt(ratingInput.value, 10) || 0;
			paintStars(sel);
			starBtns.forEach(function (b) {
				var v = parseInt(b.getAttribute('data-star-value'), 10);
				b.setAttribute('aria-checked', !isNaN(v) && sel === v ? 'true' : 'false');
			});
		}

		starBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var n = parseInt(btn.getAttribute('data-star-value'), 10);
				if (!isNaN(n)) {
					ratingInput.value = String(n);
					hideRatingError();
					syncFromInput();
				}
			});
			btn.addEventListener('mouseenter', function () {
				var n = parseInt(btn.getAttribute('data-star-value'), 10);
				if (!isNaN(n)) {
					paintStars(n);
				}
			});
		});

		reviewPicker.addEventListener('mouseleave', function () {
			syncFromInput();
		});

		reviewForm.addEventListener('submit', function (e) {
			var r = parseInt(ratingInput.value, 10);
			if (isNaN(r) || r < 1 || r > 5) {
				e.preventDefault();
				if (ratingError) {
					ratingError.classList.remove('d-none');
					ratingError.focus();
				}
			}
		});

		syncFromInput();
	}

	var shareBtn = document.querySelector('.product-share-btn');
	if (shareBtn) {
		shareBtn.addEventListener('click', function () {
			var url = (shareBtn.getAttribute('data-share-url') || '').trim();
			if (!url) {
				url = window.location.href.split('#')[0];
			}
			var title = (shareBtn.getAttribute('data-share-title') || document.title || '').trim();
			if (navigator.share) {
				navigator.share({ title: title, url: url }).catch(function () {});
				return;
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(function () {
					var prev = shareBtn.getAttribute('aria-label');
					shareBtn.setAttribute('aria-label', 'Link copied');
					window.setTimeout(function () {
						shareBtn.setAttribute('aria-label', prev || 'Share this product');
					}, 2000);
				}).catch(function () {
					window.prompt('Copy product link:', url);
				});
				return;
			}
			window.prompt('Copy product link:', url);
		});
	}

	var sizeGuideOpen = document.querySelector('[data-size-guide-open]');
	var sizeGuideDialog = document.getElementById('size-guide-dialog');
	if (sizeGuideOpen && sizeGuideDialog && typeof sizeGuideDialog.showModal === 'function') {
		sizeGuideOpen.addEventListener('click', function () {
			sizeGuideDialog.showModal();
		});
		sizeGuideDialog.addEventListener('click', function (event) {
			if (event.target === sizeGuideDialog) {
				sizeGuideDialog.close();
			}
		});
		var sizeGuideClose = sizeGuideDialog.querySelector('[data-size-guide-close]');
		if (sizeGuideClose) {
			sizeGuideClose.addEventListener('click', function () {
				sizeGuideDialog.close();
			});
		}
	}
});
