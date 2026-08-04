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
