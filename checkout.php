<?php
/**
 * Checkout: non-empty cart. Logged-in users use profile shipping; guests fill the delivery form (no OTP).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cart_cookie.php';
require_once __DIR__ . '/includes/store_base_path.php';
$base_path = store_base_path_from_request();
if (empty(cart_from_cookie())) {
	header('Location: ' . $base_path . 'cart', true, 302);
	exit;
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/product.php';
require_once __DIR__ . '/lib/store_user.php';
require_once __DIR__ . '/lib/store_user_address.php';
require_once __DIR__ . '/includes/cart_cookie.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/config/razorpay.php';
require_once __DIR__ . '/includes/store_base_path.php';
require_once __DIR__ . '/lib/promo_code.php';
require_once __DIR__ . '/includes/indian_states.php';
require_once __DIR__ . '/includes/checkout_shipping.php';
require_once __DIR__ . '/includes/checkout_guest.php';

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

$user_id = auth_cookie_user_id();
$is_guest = $user_id === null;

$cart = cart_from_cookie();
$pdo = pearlica_require_pdo();
$cart = cart_prune_unavailable($pdo, $cart);
if (empty($cart)) {
	header('Location: ' . $base_path . 'cart', true, 302);
	exit;
}
$user = null;
$checkout_address = null;
if (!$is_guest) {
	$user = store_user_find_by_id($pdo, (int) $user_id);
	if (!$user) {
		auth_cookie_clear();
		header('Location: ' . $base_path . 'checkout', true, 302);
		exit;
	}
	$checkout_address = store_user_address_resolve_for_checkout($pdo, (int) $user_id, $user);
	if ($checkout_address) {
		store_user_address_sync_profile($pdo, (int) $user_id, $checkout_address);
		$user = store_user_find_by_id($pdo, (int) $user_id) ?? $user;
	}
}
$checkout_states = indian_states_list();

$uploads_base = pearlica_uploads_products_base($base_path);
$cart_product_ids = array_keys($cart);
$cart_details = product_get_cart_details($pdo, $cart_product_ids);

$cart_total = 0;
$mrp_subtotal = 0.0;
$cart_items = [];
foreach ($cart as $product_id => $quantity) {
	$product_id = (int) $product_id;
	$detail = $cart_details[$product_id] ?? null;
	if (!$detail)
		continue;
	$mrp = (float) ($detail['mrp'] ?? 0);
	$discount = (float) ($detail['discount_percentage'] ?? 0);
	$unit_price = $discount > 0 ? $mrp * (1 - $discount / 100) : $mrp;
	$line_total = $unit_price * $quantity;
	$line_mrp_total = $mrp * $quantity;
	$cart_total += $line_total;
	$mrp_subtotal += $line_mrp_total;
	$cart_items[] = [
		'id' => $product_id,
		'name' => $detail['name'],
		'slug' => $detail['slug'],
		'sku' => $detail['sku'] ?? '',
		'category_name' => $detail['category_name'] ?? '',
		'image_url' => $detail['image_url'] ?? '',
		'quantity' => $quantity,
		'unit_price' => $unit_price,
		'line_total' => $line_total,
		'unit_mrp' => $mrp,
		'line_mrp_total' => $line_mrp_total,
		'discount_percentage' => $discount,
	];
}
$product_savings = max(0.0, $mrp_subtotal - $cart_total);

$checkout_prefill_promo_code = '';
$checkout_prefill_promo_id = 0;
$checkout_prefill_discount_paise = 0;
if (!empty($_SESSION['cart_promo']) && is_array($_SESSION['cart_promo'])) {
	$cc = trim((string) ($_SESSION['cart_promo']['code'] ?? ''));
	if ($cc !== '') {
		$sub_paise = (int) round($cart_total * 100);
		$val = promo_code_validate($pdo, $cc, $sub_paise);
		if (!empty($val['valid'])) {
			$checkout_prefill_promo_code = (string) $val['promo_code'];
			$checkout_prefill_promo_id = (int) $val['promo_id'];
			$checkout_prefill_discount_paise = (int) $val['discount_paise'];
			$_SESSION['cart_promo'] = [
				'code' => $checkout_prefill_promo_code,
				'promo_id' => $checkout_prefill_promo_id,
				'discount_paise' => $checkout_prefill_discount_paise,
			];
		} else {
			unset($_SESSION['cart_promo']);
		}
	}
}

$addr1 = $user ? trim((string) ($user['address'] ?? '')) : '';
$addr2 = $user ? trim((string) ($user['address_line_2'] ?? '')) : '';
$addr_full = $addr1 . ($addr2 !== '' ? "\n" . $addr2 : '');
$shipping_parts = $user ? array_filter([$user['city'] ?? '', $user['state'] ?? '', $user['pincode'] ?? '']) : [];
$shipping_line = trim($addr_full . ($shipping_parts !== [] ? "\n" . implode(', ', $shipping_parts) : ''));

$checkout_csrf_token = $_SESSION['checkout_csrf_token'] ?? '';
if (!is_string($checkout_csrf_token) || $checkout_csrf_token === '') {
	$checkout_csrf_token = bin2hex(random_bytes(32));
	$_SESSION['checkout_csrf_token'] = $checkout_csrf_token;
}

$razorpay_cfg = razorpay_config();
$razorpay_ready = $razorpay_cfg['key_id'] !== '' && $razorpay_cfg['key_secret'] !== '';

$subtotal_paise = (int) round($cart_total * 100);
$checkout_totals_initial = checkout_compute_totals_paise(
	$subtotal_paise,
	$checkout_prefill_discount_paise,
	true,
	0
);
$merch_net_paise = $checkout_totals_initial['merch_net_paise'];
$delivery_fee_paise = $checkout_totals_initial['delivery_fee_paise'];
$checkout_prepaid_discount_paise = $checkout_totals_initial['prepaid_discount_paise'];
$initial_grand_paise = $checkout_totals_initial['total_paise'];
$prepaid_discount_label = checkout_prepaid_discount_percent_label();
$cod_max_paise = (int) max(0, (int) CHECKOUT_COD_MAX_ORDER_RUPEES * 100);
$cod_eligible_initial = $initial_grand_paise > 0 && $initial_grand_paise <= $cod_max_paise;
$checkout_possible = $razorpay_ready || $cod_eligible_initial;
$checkout_registration_complete = !$is_guest && $user && store_user_registration_complete($user);
$checkout_shipping_complete = $is_guest
	? checkout_guest_is_complete()
	: ($user && store_user_shipping_complete($user, $pdo));
$guest_form = $is_guest ? checkout_guest_prefill_for_form() : [];
$guest_cod_otp_verified = $is_guest && checkout_guest_cod_otp_verified();

$cart_line_count = count($cart_items);
$product_discount_pct = $mrp_subtotal >= 0.01 ? (int) round($product_savings / $mrp_subtotal * 100) : 0;
$initial_savings_rupees = $product_savings + ($checkout_prefill_discount_paise / 100) + ($checkout_prepaid_discount_paise / 100);
$initial_pay_label = 'Pay ₹' . number_format($initial_grand_paise / 100, 0) . ' securely';
$product_savings_paise = (int) round($product_savings * 100);

$page_title = 'Checkout | Pearlica 925 Silver Jewellery';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/components/head.php'; ?>

<body class="checkout-page" data-base-path="<?php echo h($base_path); ?>"
	data-checkout-csrf="<?php echo h($checkout_csrf_token); ?>"
	data-subtotal-paise="<?php echo (int) $subtotal_paise; ?>"
	data-gift-packaging-paise="<?php echo (int) CHECKOUT_GIFT_PACKAGING_PAISE; ?>"
	data-free-shipping-min-paise="<?php echo (int) CHECKOUT_FREE_SHIPPING_MIN_MERCH_PAISE; ?>"
	data-shipping-fee-paise="<?php echo (int) CHECKOUT_SHIPPING_FEE_BELOW_MIN_PAISE; ?>"
	data-prepaid-discount-bps="<?php echo (int) CHECKOUT_PREPAID_DISCOUNT_BPS; ?>"
	data-cod-max-paise="<?php echo (int) $cod_max_paise; ?>"
	data-razorpay-ready="<?php echo $razorpay_ready ? '1' : '0'; ?>"
	data-is-guest="<?php echo $is_guest ? '1' : '0'; ?>"
	data-registration-complete="<?php echo $checkout_registration_complete ? '1' : '0'; ?>"
	data-shipping-complete="<?php echo $checkout_shipping_complete ? '1' : '0'; ?>"
	data-cod-otp-verified="<?php echo $guest_cod_otp_verified ? '1' : '0'; ?>"
	data-product-savings-paise="<?php echo (int) $product_savings_paise; ?>">
	<?php include __DIR__ . '/components/gtm_body.php'; ?>
	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main class="checkout-main">
		<section class="checkout-section" aria-labelledby="checkout-heading">
			<h1 class="visually-hidden" id="checkout-heading">Checkout</h1>
			<div class="checkout-page-content">
				<button type="button" class="checkout-mobile-summary-toggle" id="checkout-mobile-summary-toggle"
					aria-expanded="false" aria-controls="checkout-summary-panel">
					<span class="checkout-mobile-summary-toggle__label">Order summary (<?php echo (int) $cart_line_count; ?> item<?php echo $cart_line_count === 1 ? '' : 's'; ?>)</span>
					<span class="checkout-mobile-summary-toggle__chev" aria-hidden="true"></span>
					<span class="checkout-mobile-summary-toggle__total" id="checkout-mobile-bar-total">₹<?php echo number_format($initial_grand_paise / 100, 0); ?></span>
				</button>

				<div class="checkout-grid checkout-grid--v2">
					<div class="checkout-flow">
						<section class="checkout-step" aria-labelledby="checkout-step-contact">
							<header class="checkout-step-head" id="checkout-step-contact">
								<span class="checkout-step-num" aria-hidden="true">1</span>
								<span class="checkout-step-title">Contact</span>
							</header>
							<div class="checkout-step-card">
								<?php if ($is_guest): ?>
									<div class="pearlica-form-field">
										<label for="checkout-guest-phone" class="checkout-field-label">Mobile number</label>
										<div class="login-phone-field">
											<span class="login-phone-prefix" aria-hidden="true"><span class="login-flag">&#127470;&#127475;</span><span>+91</span></span>
											<input type="tel" id="checkout-guest-phone" class="login-phone-input" name="phone"
												inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="10-digit mobile" required
												value="<?php echo h($guest_form['phone'] ?? ''); ?>">
										</div>
										<p class="checkout-field-hint checkout-field-hint--success">Order updates on WhatsApp &amp; SMS</p>
									</div>
									<div class="pearlica-form-field">
										<label for="checkout-guest-email" class="checkout-field-label">Email</label>
										<input type="email" id="checkout-guest-email" class="login-phone-input pearlica-form-input checkout-input" name="email"
											autocomplete="email" maxlength="255" placeholder="For your invoice" required value="<?php echo h($guest_form['email'] ?? ''); ?>">
									</div>
								<?php else: ?>
									<div class="pearlica-form-field">
										<label class="checkout-field-label" for="checkout-signedin-phone">Mobile number</label>
										<input type="text" id="checkout-signedin-phone" class="checkout-input checkout-input--readonly" readonly
											value="<?php echo h(trim($user['phone'] ?? '') !== '' ? $user['phone'] : '—'); ?>">
									</div>
									<div class="pearlica-form-field">
										<label class="checkout-field-label" for="checkout-signedin-email">Email</label>
										<input type="text" id="checkout-signedin-email" class="checkout-input checkout-input--readonly" readonly
											value="<?php echo h(trim((string) ($user['email'] ?? '')) !== '' ? (string) $user['email'] : '—'); ?>">
									</div>
									<p class="checkout-field-hint checkout-field-hint--success">Order updates on WhatsApp &amp; SMS</p>
								<?php endif; ?>
							</div>
						</section>

						<section class="checkout-step" aria-labelledby="checkout-step-delivery">
							<header class="checkout-step-head checkout-step-head--split" id="checkout-step-delivery">
								<span class="checkout-step-head__main">
									<span class="checkout-step-num" aria-hidden="true">2</span>
									<span class="checkout-step-title">Delivery address</span>
								</span>
								<?php if (!$is_guest): ?>
									<span class="checkout-delivery-header-actions" id="checkout-delivery-actions">
										<button type="button" class="checkout-edit-link" id="checkout-change-address-btn">Change address</button>
										<a href="<?php echo h($base_path); ?>logout" class="checkout-edit-link ms-2">Sign out</a>
									</span>
								<?php endif; ?>
							</header>
							<div class="checkout-step-card checkout-delivery-card">
								<div id="checkout-delivery-body">
									<?php if ($is_guest): ?>
										<div id="checkout-guest-form" class="checkout-guest-form pearlica-profile-form">
											<div class="pearlica-form-field">
												<label for="checkout-guest-name" class="checkout-field-label">Full name</label>
												<input type="text" id="checkout-guest-name" class="login-phone-input pearlica-form-input checkout-input" name="full_name"
													autocomplete="name" maxlength="255" placeholder="Name of the person receiving the order" required value="<?php echo h($guest_form['full_name'] ?? ''); ?>">
											</div>
											<div class="checkout-address-row checkout-address-row--pin">
												<div class="pearlica-form-field checkout-address-row__pin">
													<label for="checkout-guest-pincode" class="checkout-field-label">Pincode</label>
													<input type="text" id="checkout-guest-pincode" class="login-phone-input pearlica-form-input checkout-input" name="pincode"
														inputmode="numeric" maxlength="6" placeholder="6-digit pincode" required value="<?php echo h($guest_form['pincode'] ?? ''); ?>">
												</div>
												<div class="pearlica-form-field checkout-address-row__city">
													<label for="checkout-guest-city" class="checkout-field-label">City</label>
													<input type="text" id="checkout-guest-city" class="login-phone-input pearlica-form-input checkout-input" name="city"
														maxlength="120" required value="<?php echo h($guest_form['city'] ?? ''); ?>">
												</div>
												<div class="pearlica-form-field checkout-address-row__state">
													<label for="checkout-guest-state" class="checkout-field-label">State</label>
													<select id="checkout-guest-state" class="login-phone-input pearlica-form-input pearlica-form-select checkout-input" name="state" required>
														<option value="">Select state</option>
														<?php foreach ($checkout_states as $st): ?>
															<option value="<?php echo h($st); ?>"<?php echo ($guest_form['state'] ?? '') === $st ? ' selected' : ''; ?>><?php echo h($st); ?></option>
														<?php endforeach; ?>
													</select>
												</div>
											</div>
											<p class="checkout-pin-eta" id="checkout-guest-pin-eta" hidden role="status"></p>
											<div class="pearlica-form-field">
												<label for="checkout-guest-line1" class="checkout-field-label">Flat / house no., building</label>
												<input type="text" id="checkout-guest-line1" class="login-phone-input pearlica-form-input checkout-input" name="address"
													autocomplete="address-line1" maxlength="255" placeholder="e.g. Flat 302, Sai Residency" required value="<?php echo h($guest_form['address'] ?? ''); ?>">
											</div>
											<div class="pearlica-form-field">
												<label for="checkout-guest-line2" class="checkout-field-label">Area, street, landmark</label>
												<input type="text" id="checkout-guest-line2" class="login-phone-input pearlica-form-input checkout-input" name="address_line_2"
													autocomplete="address-line2" maxlength="255" placeholder="e.g. Near Station Road" value="<?php echo h($guest_form['address_line_2'] ?? ''); ?>">
											</div>
											<p class="checkout-guest-form-error text-danger small" id="checkout-guest-form-error" hidden role="alert"></p>
										</div>
										<div class="checkout-cod-otp-block" id="checkout-cod-otp-block" hidden>
											<p class="checkout-field-label">Confirm mobile for COD</p>
											<p class="small text-secondary mb-2">Enter the code sent to <strong id="checkout-cod-phone-mask">your number</strong> to place a Cash on Delivery order.</p>
											<input type="text" id="checkout-cod-otp" class="login-otp-input checkout-cod-otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="0000">
											<button type="button" class="checkout-btn-secondary checkout-cod-otp-resend" id="checkout-cod-otp-resend">Resend code</button>
											<p class="checkout-cod-otp-error text-danger small" id="checkout-cod-otp-error" hidden role="alert"></p>
										</div>
									<?php else: ?>
										<div class="checkout-recipient-name"><?php echo h($user['full_name'] ?: ($user['email'] ?? '')); ?></div>
										<?php if ($shipping_line !== ''): ?>
											<div class="checkout-address-block" id="checkout-address-display"><?php echo nl2br(h($shipping_line)); ?></div>
										<?php else: ?>
											<div class="checkout-address-block" id="checkout-address-display">Your delivery address will be collected before payment.</div>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
						</section>

						<section class="checkout-step checkout-payment-methods" aria-labelledby="checkout-step-payment">
							<header class="checkout-step-head" id="checkout-step-payment">
								<span class="checkout-step-num" aria-hidden="true">3</span>
								<span class="checkout-step-title">Payment</span>
							</header>
							<input type="hidden" id="checkout-payment-method" name="payment_method" value="razorpay">
							<input type="checkbox" id="checkout-cod" class="checkout-cod-input-visually-hidden" autocomplete="off" tabindex="-1" aria-hidden="true">
							<div class="checkout-pay-options">
								<button type="button" class="checkout-pay-option checkout-pay-option--active" id="checkout-pay-online-btn" data-pay="razorpay">
									<span class="checkout-pay-option__radio" aria-hidden="true"></span>
									<span class="checkout-pay-option__body">
										<span class="checkout-pay-option__title-row">
											<span class="checkout-pay-option__title">Pay online</span>
											<?php if ($checkout_prepaid_discount_paise > 0): ?>
												<span class="checkout-pay-badge">Extra <?php echo h($prepaid_discount_label); ?>% off · Save ₹<?php echo number_format($checkout_prepaid_discount_paise / 100, 0); ?></span>
											<?php endif; ?>
										</span>
										<span class="checkout-pay-option__sub">UPI, cards, net banking — secured by Razorpay</span>
										<?php if (!$razorpay_ready): ?>
											<span class="checkout-pay-option__warn text-danger small" id="checkout-razorpay-down-msg">
												<?php if ($cod_eligible_initial): ?>
													Online payments are temporarily unavailable. You can still place your order with Cash on Delivery.
												<?php else: ?>
													Online payments are temporarily unavailable. Please try again later or contact us for help.
												<?php endif; ?>
											</span>
										<?php endif; ?>
									</span>
								</button>
								<div class="checkout-payment-methods__cod-wrap" id="checkout-cod-wrap">
									<button type="button" class="checkout-pay-option" id="checkout-pay-cod-btn" data-pay="cod">
										<span class="checkout-pay-option__radio" aria-hidden="true"></span>
										<span class="checkout-pay-option__body">
											<span class="checkout-pay-option__title">Cash on Delivery</span>
											<span class="checkout-pay-option__sub">Pay when your order arrives — orders up to ₹<?php echo number_format((int) CHECKOUT_COD_MAX_ORDER_RUPEES, 0); ?></span>
										</span>
									</button>
								</div>
							</div>
							<p class="checkout-payment-methods__note small text-secondary" id="checkout-cod-unavailable-note" hidden>Cash on Delivery is only available when your order total is ₹<?php echo number_format((int) CHECKOUT_COD_MAX_ORDER_RUPEES, 0); ?> or less.</p>

							<div class="checkout-trust-grid checkout-trust-grid--flow" aria-label="Why shop with Pearlica">
								<div class="checkout-trust-cell">Secure payment</div>
								<div class="checkout-trust-cell">925 Sterling Silver</div>
								<div class="checkout-trust-cell">7-day easy exchange</div>
								<div class="checkout-trust-cell">Pearlica Authenticity Certificate</div>
							</div>
						</section>
					</div>

					<aside class="checkout-summary" id="checkout-summary-panel" aria-label="Order summary">
						<div class="checkout-summary__card">
							<p class="checkout-summary__heading">Order summary · <?php echo (int) $cart_line_count; ?> item<?php echo $cart_line_count === 1 ? '' : 's'; ?></p>
							<div class="checkout-summary__scroll">
								<div class="checkout-order-card">
									<div id="checkout-cart-items">
									<?php foreach ($cart_items as $item): ?>
										<?php
										$img_src = !empty($item['image_url']) ? $uploads_base . '/' . $item['image_url'] : 'https://placehold.co/120x120/f0eeeb/888?text=Product';
										$product_url = $base_path . 'product?slug=' . rawurlencode($item['slug']);
										$pid = (int) $item['id'];
										$disc_pct = (float) ($item['discount_percentage'] ?? 0);
										?>
										<div class="checkout-order-item cart-item-card" data-product-id="<?php echo $pid; ?>">
											<div class="checkout-item-img-wrap">
												<a href="<?php echo h($product_url); ?>">
													<img src="<?php echo h($img_src); ?>" alt="<?php echo h($item['name']); ?>" loading="lazy">
												</a>
											</div>
											<div class="checkout-item-details">
												<a href="<?php echo h($product_url); ?>" class="checkout-item-name"><?php echo h($item['name']); ?></a>
												<div class="checkout-item-actions">
													<div class="cart-qty-stepper">
														<button type="button" class="cart-qty-btn cart-qty-minus" aria-label="Decrease quantity">−</button>
														<input type="number" id="checkout-qty-<?php echo $pid; ?>" class="cart-qty-input"
															min="1" max="99" value="<?php echo (int) $item['quantity']; ?>"
															data-product-id="<?php echo $pid; ?>"
															data-unit-price="<?php echo h((string) $item['unit_price']); ?>"
															aria-label="Quantity">
														<button type="button" class="cart-qty-btn cart-qty-plus" aria-label="Increase quantity">+</button>
													</div>
													<a href="<?php echo h($base_path); ?>cart?remove=<?php echo $pid; ?>" class="cart-remove-link checkout-item-remove">Remove</a>
												</div>
											</div>
											<div class="checkout-item-price-col">
												<span class="checkout-item-line-total cart-line-total<?php echo $disc_pct > 0 ? ' checkout-price--discount' : ''; ?>" data-product-id="<?php echo $pid; ?>">₹<?php echo number_format($item['line_total'], 0); ?></span>
												<?php if ($disc_pct > 0 && (float) ($item['line_mrp_total'] ?? 0) > 0): ?>
													<span class="checkout-item-was" aria-hidden="true">₹<?php echo number_format((float) $item['line_mrp_total'], 0); ?></span>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
									</div>
									<form id="checkout-cart-update-form" method="post" action="<?php echo h($base_path); ?>cart"
										class="cart-update-form checkout-cart-update-form"
										data-base-path="<?php echo h(rtrim($base_path, '/')); ?>">
										<input type="hidden" name="action" value="update">
										<?php foreach ($cart_items as $item): ?>
											<input type="hidden" name="cart[<?php echo (int) $item['id']; ?>]" id="cart-input-<?php echo (int) $item['id']; ?>"
												value="<?php echo (int) $item['quantity']; ?>">
										<?php endforeach; ?>
									</form>
								</div>

								<label class="checkout-gift-addon" for="checkout-gift-packaging">
									<input type="checkbox" id="checkout-gift-packaging" name="gift_packaging" value="1" autocomplete="off">
									<span>Add gift packaging (+₹<?php echo number_format(CHECKOUT_GIFT_PACKAGING_PAISE / 100, 0); ?>)</span>
								</label>
								<span id="checkout-gift-value" class="visually-hidden" aria-hidden="true">—</span>

								<?php $has_applied_promo = trim($checkout_prefill_promo_code) !== ''; ?>
								<div class="checkout-promo-wrap checkout-promo-wrap--summary">
									<div class="checkout-promo-applied" id="checkout-promo-applied-block"<?php echo $has_applied_promo ? '' : ' hidden'; ?>>
										<div class="checkout-applied-promo-row">
											<span class="checkout-applied-promo-code" id="checkout-display-promo-code"><?php echo h($checkout_prefill_promo_code); ?></span>
											<span class="checkout-applied-promo-save" id="checkout-display-promo-amount">−₹<?php echo number_format(max(0, $checkout_prefill_discount_paise) / 100, 0); ?></span>
											<button type="button" class="checkout-promo-remove-btn" id="checkout-promo-remove">Remove</button>
										</div>
									</div>
									<div class="checkout-promo-entry" id="checkout-promo-entry-block"<?php echo $has_applied_promo ? ' hidden' : ''; ?>>
										<div class="checkout-coupon-row">
											<input type="text" class="checkout-coupon-input" placeholder="Promo code"
												id="checkout-promo-code" value="" autocomplete="off" maxlength="40">
											<button type="button" class="checkout-coupon-btn" id="checkout-promo-apply">Apply</button>
										</div>
									</div>
								</div>

								<div class="checkout-total-rows">
									<?php if ($product_savings >= 0.01): ?>
										<div class="checkout-total-row checkout-total-row--muted">
											<span>MRP total</span>
											<span class="checkout-total-mrp">₹<?php echo number_format($mrp_subtotal, 0); ?></span>
										</div>
										<div class="checkout-total-row checkout-total-row--savings checkout-total-row--product-save">
											<span>Product discount<?php echo $product_discount_pct > 0 ? ' (' . (int) $product_discount_pct . '%)' : ''; ?></span>
											<span>−₹<?php echo number_format($product_savings, 0); ?></span>
										</div>
									<?php endif; ?>
									<div class="checkout-total-row checkout-total-row--emphasis">
										<span>Subtotal</span>
										<span>₹<?php echo number_format($cart_total, 0); ?></span>
									</div>
									<div class="checkout-total-row">
										<span>Shipping</span>
										<span id="checkout-shipping-display" class="<?php echo $delivery_fee_paise > 0 ? 'checkout-shipping-amount' : 'checkout-free-badge'; ?>"><?php echo $delivery_fee_paise > 0 ? '₹' . number_format($delivery_fee_paise / 100, 0) : 'FREE'; ?></span>
									</div>
									<div class="checkout-total-row checkout-total-row--savings" id="checkout-promo-total-row"<?php echo $checkout_prefill_discount_paise > 0 ? '' : ' hidden'; ?>>
										<span>Promo code</span>
										<span id="checkout-promo-value" class="<?php echo $checkout_prefill_discount_paise > 0 ? 'checkout-promo-value--active' : ''; ?>"><?php echo $checkout_prefill_discount_paise > 0 ? '−₹' . number_format($checkout_prefill_discount_paise / 100, 0) : '—'; ?></span>
									</div>
									<div class="checkout-total-row checkout-total-row--savings" id="checkout-prepaid-discount-row">
										<span>Prepaid discount (<?php echo h($prepaid_discount_label); ?>%)</span>
										<span id="checkout-prepaid-value" class="<?php echo $checkout_prepaid_discount_paise > 0 ? 'checkout-promo-value--active' : ''; ?>"><?php echo $checkout_prepaid_discount_paise > 0 ? '−₹' . number_format($checkout_prepaid_discount_paise / 100, 0) : '—'; ?></span>
									</div>
									<div class="checkout-total-row grand">
										<span>Order total</span>
										<span class="checkout-amount" id="checkout-order-total">₹<?php echo number_format($initial_grand_paise / 100, 0); ?></span>
									</div>
								</div>
							</div>

							<div class="checkout-summary__footer">
								<p class="checkout-savings-banner" id="checkout-savings-banner"<?php echo $initial_savings_rupees >= 1 ? '' : ' hidden'; ?>>
									You save ₹<?php echo number_format($initial_savings_rupees, 0); ?> on this order
								</p>
								<div class="checkout-mobile-pay-meta">
									<span class="checkout-mobile-pay-save" id="checkout-mobile-save"><?php echo $initial_savings_rupees >= 1 ? 'You save ₹' . number_format($initial_savings_rupees, 0) : ''; ?></span>
									<span class="checkout-mobile-pay-total">Total <strong id="checkout-mobile-footer-total">₹<?php echo number_format($initial_grand_paise / 100, 0); ?></strong></span>
								</div>
								<button type="button" class="checkout-btn-primary" id="checkout-place-order"
									data-csrf-token="<?= h($checkout_csrf_token) ?>" <?= $checkout_possible ? '' : 'disabled' ?>
									data-default-label="<?php echo h($initial_pay_label); ?>"
									aria-disabled="<?= $checkout_possible ? 'false' : 'true' ?>"><?php echo h($initial_pay_label); ?></button>
								<input type="hidden" id="checkout-applied-promo-code" value="<?php echo h($checkout_prefill_promo_code); ?>">
								<input type="hidden" id="checkout-applied-promo-id" value="<?php echo (int) $checkout_prefill_promo_id; ?>">
								<input type="hidden" id="checkout-applied-discount-paise" value="<?php echo (int) $checkout_prefill_discount_paise; ?>">

								<div class="checkout-trust-grid checkout-trust-grid--summary" aria-label="Why shop with Pearlica">
									<div class="checkout-trust-cell">Secure payment</div>
									<div class="checkout-trust-cell">925 Sterling Silver</div>
									<div class="checkout-trust-cell">7-day easy exchange</div>
									<div class="checkout-trust-cell">Pearlica Authenticity Certificate</div>
								</div>
								<p class="checkout-legal-note">By placing this order you agree to our <a href="<?php echo h($base_path); ?>policies/terms-conditions">Terms</a> and <a href="<?php echo h($base_path); ?>policies/return-exchange-policy">Return policy</a>.</p>
								<div class="checkout-secure-note visually-hidden" id="checkout-secure-note-block" hidden aria-hidden="true">SSL</div>
							</div>
						</div>
					</aside>
				</div>
			</div>
		</section>
	</main>
	<?php include __DIR__ . '/components/footer.php'; ?>
	<script src="<?php echo h(asset_url('assets/js/jquery.min.js', $base_path)); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		crossorigin="anonymous"></script>
	<script src="https://checkout.razorpay.com/v1/checkout.js?v=1"></script>
	<script src="<?php echo h(asset_url('assets/js/payment/checkout-payment.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/checkout-delivery-estimate.js', $base_path)); ?>"></script>
	<script src="<?php echo h(asset_url('assets/js/checkout-ui.js', $base_path)); ?>"></script>
</body>

</html>