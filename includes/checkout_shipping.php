<?php

/**
 * Checkout delivery fee from merchandise value after promo (excludes shipping itself).
 * Aligns with policies/shipping-policy.php (complimentary shipping at or above ₹700).
 */

function checkout_merchandise_net_paise(int $subtotal_paise, int $discount_paise): int
{
	return (int) max(0, $subtotal_paise - $discount_paise);
}

function checkout_delivery_fee_paise(int $merchandise_after_discount_paise): int
{
	$min_free = defined('CHECKOUT_FREE_SHIPPING_MIN_MERCH_PAISE') ? (int) CHECKOUT_FREE_SHIPPING_MIN_MERCH_PAISE : 70000;
	$fee = defined('CHECKOUT_SHIPPING_FEE_BELOW_MIN_PAISE') ? (int) CHECKOUT_SHIPPING_FEE_BELOW_MIN_PAISE : 19900;
	if ($merchandise_after_discount_paise <= 0) {
		return 0;
	}

	return $merchandise_after_discount_paise < $min_free ? $fee : 0;
}

/** 5% (default) off merchandise after promo — Razorpay / prepaid only. */
function checkout_prepaid_discount_paise(int $merchandise_after_promo_paise): int
{
	if ($merchandise_after_promo_paise <= 0) {
		return 0;
	}
	$bps = defined('CHECKOUT_PREPAID_DISCOUNT_BPS') ? (int) CHECKOUT_PREPAID_DISCOUNT_BPS : 500;
	if ($bps <= 0) {
		return 0;
	}

	return (int) round($merchandise_after_promo_paise * $bps / 10000);
}

function checkout_prepaid_discount_percent_label(): string
{
	$bps = defined('CHECKOUT_PREPAID_DISCOUNT_BPS') ? (int) CHECKOUT_PREPAID_DISCOUNT_BPS : 500;
	if ($bps <= 0) {
		return '0';
	}
	$whole = intdiv($bps, 100);
	$frac = $bps % 100;
	if ($frac === 0) {
		return (string) $whole;
	}

	return rtrim(rtrim(number_format($bps / 100, 2, '.', ''), '0'), '.');
}

/**
 * @return array{
 *   merch_net_paise: int,
 *   promo_discount_paise: int,
 *   prepaid_discount_paise: int,
 *   discount_paise: int,
 *   delivery_fee_paise: int,
 *   shipping_paise: int,
 *   total_paise: int
 * }
 */
function checkout_compute_totals_paise(
	int $subtotal_paise,
	int $promo_discount_paise,
	bool $apply_prepaid_discount,
	int $gift_packaging_paise = 0
): array {
	$promo_discount_paise = max(0, $promo_discount_paise);
	$gift_packaging_paise = max(0, $gift_packaging_paise);
	$merch_net_paise = checkout_merchandise_net_paise($subtotal_paise, $promo_discount_paise);
	$prepaid_discount_paise = $apply_prepaid_discount
		? checkout_prepaid_discount_paise($merch_net_paise)
		: 0;
	$delivery_fee_paise = checkout_delivery_fee_paise($merch_net_paise);
	$shipping_paise = $delivery_fee_paise + $gift_packaging_paise;
	$discount_paise = $promo_discount_paise + $prepaid_discount_paise;
	$total_paise = (int) max(0, $subtotal_paise + $shipping_paise - $discount_paise);

	return [
		'merch_net_paise' => $merch_net_paise,
		'promo_discount_paise' => $promo_discount_paise,
		'prepaid_discount_paise' => $prepaid_discount_paise,
		'discount_paise' => $discount_paise,
		'delivery_fee_paise' => $delivery_fee_paise,
		'shipping_paise' => $shipping_paise,
		'total_paise' => $total_paise,
	];
}
