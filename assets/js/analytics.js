/**
 * Pearlica storefront analytics: Meta Pixel + GA4 with SKU content_id.
 */
(function (global) {
	'use strict';

	var GA4_ID = 'G-FCQW9SZ8VF';

	function metaParams(payload) {
		var p = {
			currency: payload.currency || 'INR',
			value: payload.value,
			content_ids: payload.content_ids || [],
			content_type: 'product',
		};
		if (payload.contents && payload.contents.length) {
			p.contents = payload.contents;
		}
		if (payload.num_items) {
			p.num_items = payload.num_items;
		}
		if (payload.transaction_id) {
			p.order_id = payload.transaction_id;
		}
		return p;
	}

	function gaItems(items) {
		return (items || []).map(function (item) {
			return {
				item_id: item.item_id,
				item_name: item.item_name || '',
				quantity: item.quantity || 1,
				price: item.price || 0,
			};
		});
	}

	function trackMeta(eventName, payload, eventId) {
		if (typeof global.fbq !== 'function') {
			return;
		}
		var options = eventId ? { eventID: eventId } : undefined;
		if (options) {
			global.fbq('track', eventName, metaParams(payload), options);
		} else {
			global.fbq('track', eventName, metaParams(payload));
		}
	}

	function trackGa(eventName, payload) {
		if (typeof global.gtag !== 'function') {
			return;
		}
		var gaPayload = {
			currency: payload.currency || 'INR',
			value: payload.value,
			items: gaItems(payload.ga_items),
		};
		if (payload.transaction_id) {
			gaPayload.transaction_id = payload.transaction_id;
		}
		global.gtag('event', eventName, gaPayload);
	}

	function trackViewContent(payload) {
		trackMeta('ViewContent', payload);
		trackGa('view_item', payload);
	}

	function trackAddToCart(payload) {
		trackMeta('AddToCart', payload);
		trackGa('add_to_cart', payload);
	}

	function trackInitiateCheckout(payload) {
		trackMeta('InitiateCheckout', payload);
		trackGa('begin_checkout', payload);
	}

	function trackPurchase(payload) {
		if (!payload || !payload.event_id) {
			return;
		}
		trackMeta('Purchase', payload, payload.event_id);
		trackGa('purchase', payload);
	}

	function flushBootstrap(data) {
		if (!data || typeof data !== 'object') {
			return;
		}
		if (data.view_content) {
			trackViewContent(data.view_content);
		}
		if (data.initiate_checkout) {
			trackInitiateCheckout(data.initiate_checkout);
		}
		if (data.purchase) {
			trackPurchase(data.purchase);
		}
		var queue = data.queue;
		if (Array.isArray(queue)) {
			queue.forEach(function (row) {
				if (!row || !row.event) {
					return;
				}
				if (row.event === 'AddToCart') {
					trackAddToCart(row.payload || {});
				}
			});
		}
	}

	function readBootstrapNode() {
		var node = document.getElementById('pearlica-analytics-bootstrap');
		if (!node || !node.textContent) {
			return null;
		}
		try {
			return JSON.parse(node.textContent);
		} catch (e) {
			return null;
		}
	}

	global.pearlicaAnalytics = {
		trackViewContent: trackViewContent,
		trackAddToCart: trackAddToCart,
		trackInitiateCheckout: trackInitiateCheckout,
		trackPurchase: trackPurchase,
		flushBootstrap: flushBootstrap,
	};

	document.addEventListener('DOMContentLoaded', function () {
		flushBootstrap(readBootstrapNode());
	});
})(window);
