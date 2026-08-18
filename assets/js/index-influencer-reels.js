/**
 * Homepage — Pearlica Picks circular reel carousel.
 *
 * Slides are reel 1..N in admin sort order. centerIndex 0 means reel 1 is centred.
 * With 5 reels the initial left-to-right order is 4, 5, 1, 2, 3.
 * Next moves the centre forward (1 -> 2 -> 3 ...), previous moves it back, both wrapping.
 *
 * The centred reel plays muted and looping. Uploaded videos autoplay; Instagram
 * embeds cannot, so those slides fall back to a cropped iframe the visitor clicks.
 */
(function () {
	'use strict';

	/**
	 * Instagram will not lay its embed out below ~326px, so the iframe is rendered
	 * at EMBED_WIDTH and scaled down. Raise EMBED_HEADER to pull the video up, or
	 * OVERSCAN to zoom in, if any Instagram chrome remains visible.
	 */
	var EMBED_WIDTH = 400;
	var EMBED_HEADER = 58;
	var EMBED_HEIGHT = 1100;
	var OVERSCAN = 1.06;
	var TRANSITION_MS = 560;

	var section = document.querySelector('.index-influencers');
	var track = section && section.querySelector('.index-influencers-track');
	if (!section || !track) {
		return;
	}

	var slides = Array.prototype.slice.call(track.querySelectorAll('.index-influencers-slide'));
	var total = slides.length;
	if (total < 1) {
		return;
	}

	var maxRadius = parseInt(section.getAttribute('data-max-radius') || '2', 10);
	var prevBtn = section.querySelector('.index-influencers-prev');
	var nextBtn = section.querySelector('.index-influencers-next');
	var centerIndex = 0;
	var animating = false;

	function mod(n, m) {
		return ((n % m) + m) % m;
	}

	/** Shortest signed distance from the centre to a slide on the ring. */
	function slotOffset(slideIndex) {
		var diff = slideIndex - centerIndex;
		while (diff > total / 2) {
			diff -= total;
		}
		while (diff < -total / 2) {
			diff += total;
		}
		return diff;
	}

	function slotGap() {
		var w = window.innerWidth;
		if (w < 576) {
			return 74;
		}
		if (w < 768) {
			return 92;
		}
		if (w < 992) {
			return 118;
		}
		return 138;
	}

	function scaleForOffset(absOffset) {
		if (absOffset === 0) {
			return 1;
		}
		return absOffset === 1 ? 0.84 : 0.7;
	}

	function playVideo(slide) {
		var video = slide.querySelector('.index-influencers-video');
		if (!video) {
			return;
		}
		if (!video.getAttribute('src')) {
			var src = video.getAttribute('data-video-src');
			if (src) {
				video.setAttribute('src', src);
			}
		}
		video.muted = true;
		var attempt = video.play();
		if (attempt && typeof attempt.catch === 'function') {
			// Autoplay can still be refused; the poster stays visible in that case.
			attempt.catch(function () {});
		}
	}

	function pauseVideo(slide) {
		var video = slide.querySelector('.index-influencers-video');
		if (!video) {
			return;
		}
		if (!video.paused) {
			video.pause();
		}
		video.currentTime = 0;
	}

	function loadEmbed(slide) {
		var iframe = slide.querySelector('.index-influencers-iframe');
		if (iframe && !iframe.getAttribute('src')) {
			var src = iframe.getAttribute('data-embed-src');
			if (src) {
				iframe.setAttribute('src', src);
			}
		}
	}

	function unloadEmbed(slide) {
		var iframe = slide.querySelector('.index-influencers-iframe');
		if (iframe && iframe.getAttribute('src')) {
			iframe.removeAttribute('src');
		}
	}

	/**
	 * Scale the embed to the card and pull it up so the video area starts at the
	 * card's top edge. Anchoring to the top of the video (rather than its centre)
	 * keeps the profile header clipped even if Instagram renders it shorter than 9:16.
	 */
	function fitEmbed(slide) {
		var card = slide.querySelector('.index-influencers-reel');
		var embed = slide.querySelector('.index-influencers-embed');
		if (!card || !embed) {
			return;
		}

		var cardWidth = card.offsetWidth;
		if (!cardWidth) {
			return;
		}

		var scale = (cardWidth / EMBED_WIDTH) * OVERSCAN;
		embed.style.width = EMBED_WIDTH + 'px';
		embed.style.height = EMBED_HEIGHT + 'px';
		embed.style.transform =
			'translate(' + ((cardWidth - EMBED_WIDTH * scale) / 2) + 'px, ' +
			(-EMBED_HEADER * scale) + 'px) scale(' + scale + ')';
	}

	function applyLayout() {
		var gap = slotGap();

		slides.forEach(function (slide) {
			var index = parseInt(slide.getAttribute('data-reel-index') || '0', 10);
			var offset = slotOffset(index);
			var distance = Math.abs(offset);
			var isCentre = offset === 0;
			var isHidden = distance > maxRadius;

			slide.classList.toggle('is-center', isCentre);
			slide.classList.toggle('is-hidden', isHidden);
			slide.style.setProperty('--slide-distance', String(Math.min(distance, maxRadius)));
			slide.style.transform =
				'translateX(calc(-50% + ' + offset * gap + 'px)) scale(' + scaleForOffset(distance) + ')';
			slide.style.zIndex = String(20 - distance);
			slide.style.opacity = isHidden ? '0' : '1';
			slide.setAttribute('aria-hidden', isHidden ? 'true' : 'false');

			fitEmbed(slide);

			if (isCentre) {
				playVideo(slide);
				loadEmbed(slide);
			} else {
				pauseVideo(slide);
				// A side card only needs the embed when it has no poster to show.
				if (!isHidden && slide.getAttribute('data-thumb') === 'failed') {
					loadEmbed(slide);
				} else {
					unloadEmbed(slide);
				}
			}
		});

		section.setAttribute('data-center-reel', String(centerIndex + 1));
	}

	function goTo(nextCentre) {
		if (animating || total < 2) {
			return;
		}
		nextCentre = mod(nextCentre, total);
		if (nextCentre === centerIndex) {
			return;
		}

		animating = true;
		centerIndex = nextCentre;
		applyLayout();
		window.setTimeout(function () {
			animating = false;
		}, TRANSITION_MS);
	}

	slides.forEach(function (slide) {
		var thumb = slide.querySelector('.index-influencers-thumb');
		if (thumb) {
			thumb.addEventListener('error', function () {
				slide.setAttribute('data-thumb', 'failed');
				applyLayout();
			});
		}

		slide.addEventListener('click', function (e) {
			if (e.target.closest('a')) {
				return;
			}
			var index = parseInt(slide.getAttribute('data-reel-index') || '0', 10);
			if (index !== centerIndex && !slide.classList.contains('is-hidden')) {
				goTo(index);
			}
		});
	});

	if (prevBtn) {
		prevBtn.addEventListener('click', function () {
			goTo(centerIndex - 1);
		});
	}
	if (nextBtn) {
		nextBtn.addEventListener('click', function () {
			goTo(centerIndex + 1);
		});
	}

	var touchStartX = 0;
	var touchStartY = 0;
	track.addEventListener('touchstart', function (e) {
		if (e.touches && e.touches[0]) {
			touchStartX = e.touches[0].clientX;
			touchStartY = e.touches[0].clientY;
		}
	}, { passive: true });

	track.addEventListener('touchend', function (e) {
		if (!e.changedTouches || !e.changedTouches[0]) {
			return;
		}
		var dx = e.changedTouches[0].clientX - touchStartX;
		var dy = e.changedTouches[0].clientY - touchStartY;
		if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) {
			return;
		}
		goTo(dx < 0 ? centerIndex + 1 : centerIndex - 1);
	}, { passive: true });

	var resizeTimer;
	window.addEventListener('resize', function () {
		window.clearTimeout(resizeTimer);
		resizeTimer = window.setTimeout(applyLayout, 120);
	});

	// Pause playback while the carousel is off screen.
	if ('IntersectionObserver' in window) {
		new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				var centre = track.querySelector('.index-influencers-slide.is-center');
				if (!centre) {
					return;
				}
				if (entry.isIntersecting) {
					playVideo(centre);
				} else {
					pauseVideo(centre);
				}
			});
		}, { threshold: 0.2 }).observe(section);
	}

	applyLayout();
})();
