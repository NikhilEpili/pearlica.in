<?php
/**
 * Checkout: non-empty cart. Logged-in users use profile shipping; guests fill the delivery form (no OTP).
 */
declare(strict_types=1);

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

$base_path = store_base_path_from_request();
$user_id = auth_cookie_user_id();
$is_guest = $user_id === null;

$cart = cart_from_cookie();
if (empty($cart)) {
	header('Location: ' . $base_path . 'index', true, 302);
	exit;
}

$pdo = $GLOBALS['pdo'];
$cart = cart_prune_unavailable($pdo, $cart);
if (empty($cart)) {
	header('Location: ' . $base_path . 'index', true, 302);
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
$merch_net_paise = checkout_merchandise_net_paise($subtotal_paise, $checkout_prefill_discount_paise);
$delivery_fee_paise = checkout_delivery_fee_paise($merch_net_paise);
$initial_grand_paise = (int) max(0, $merch_net_paise + $delivery_fee_paise);
$cod_max_paise = (int) max(0, (int) CHECKOUT_COD_MAX_ORDER_RUPEES * 100);
$cod_eligible_initial = $initial_grand_paise > 0 && $initial_grand_paise <= $cod_max_paise;
$checkout_possible = $razorpay_ready || $cod_eligible_initial;
$checkout_registration_complete = !$is_guest && $user && store_user_registration_complete($user);
$checkout_shipping_complete = $is_guest
	? checkout_guest_is_complete()
	: ($user && store_user_shipping_complete($user, $pdo));
$guest_form = $is_guest ? checkout_guest_prefill_for_form() : [];
$guest_cod_otp_verified = $is_guest && checkout_guest_cod_otp_verified();

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
	data-cod-max-paise="<?php echo (int) $cod_max_paise; ?>"
	data-razorpay-ready="<?php echo $razorpay_ready ? '1' : '0'; ?>"
	data-is-guest="<?php echo $is_guest ? '1' : '0'; ?>"
	data-registration-complete="<?php echo $checkout_registration_complete ? '1' : '0'; ?>"
	data-shipping-complete="<?php echo $checkout_shipping_complete ? '1' : '0'; ?>"
	data-cod-otp-verified="<?php echo $guest_cod_otp_verified ? '1' : '0'; ?>">
	<?php include __DIR__ . '/components/gtm_body.php'; ?>
	<?php include __DIR__ . '/components/navbar.php'; ?>

	<main class="checkout-main">
		<section class="checkout-section" aria-labelledby="checkout-heading">
			<div class="checkout-page-content">
				<p class="checkout-kicker">Secure checkout</p>
				<h1 class="checkout-heading" id="checkout-heading">Your order</h1>
				<div class="checkout-grid">
				<!-- Left: order summary + promo (takes remaining space) -->
				<div class="checkout-left">
					<p class="checkout-section-label">Items</p>
					<div class="checkout-order-card">
						<div id="checkout-cart-items">
						<?php foreach ($cart_items as $item): ?>
							<?php
							$img_src = !empty($item['image_url']) ? $uploads_base . '/' . $item['image_url'] : 'https://placehold.co/120x120/f0eeeb/888?text=Product';
							$product_url = $base_path . 'product?slug=' . rawurlencode($item['slug']);
							$item_meta = trim((string) ($item['category_name'] ?? '')) !== '' ? $item['category_name'] : '925 Sterling Silver';
							$pid = (int) $item['id'];
							$disc_pct = (float) ($item['discount_percentage'] ?? 0);
							?>
							<div class="checkout-order-item cart-item-card" data-product-id="<?php echo $pid; ?>">
								<div class="checkout-item-img-wrap">
									<a href="<?php echo h($product_url); ?>">
										<img src="<?php echo h($img_src); ?>" alt="<?php echo h($item['name']); ?>"
											loading="lazy">
									</a>
								</div>
								<div class="checkout-item-details">
									<a href="<?php echo h($product_url); ?>"
										class="checkout-item-name"><?php echo h($item['name']); ?></a>
									<div class="checkout-item-meta">
										<?php echo h($item_meta); ?><?php if ($disc_pct > 0): ?>
											· <?php endif; ?>
										<?php if ($disc_pct > 0): ?>
											<span class="checkout-item-off-badge"><?php echo (int) $disc_pct; ?>% off</span>
										<?php endif; ?>
									</div>
									<div class="checkout-item-unit-row">
										<span class="checkout-item-unit-sale<?php echo $disc_pct > 0 ? ' checkout-price--discount' : ''; ?>">₹<?php echo number_format((float) $item['unit_price'], 0); ?></span>
										<?php if ($disc_pct > 0 && (float) ($item['unit_mrp'] ?? 0) > 0): ?>
											<span class="checkout-item-unit-mrp">₹<?php echo number_format((float) $item['unit_mrp'], 0); ?></span>
										<?php endif; ?>
									</div>
									<div class="checkout-item-actions">
										<label class="checkout-qty-label" for="checkout-qty-<?php echo $pid; ?>">Qty</label>
										<div class="cart-qty-stepper">
											<button type="button" class="cart-qty-btn cart-qty-minus" aria-label="Decrease quantity">−</button>
											<input type="number" id="checkout-qty-<?php echo $pid; ?>" class="cart-qty-input"
												min="1" max="99" value="<?php echo (int) $item['quantity']; ?>"
												data-product-id="<?php echo $pid; ?>"
												data-unit-price="<?php echo h((string) $item['unit_price']); ?>">
											<button type="button" class="cart-qty-btn cart-qty-plus" aria-label="Increase quantity">+</button>
										</div>
										<a href="<?php echo h($base_path); ?>cart?remove=<?php echo $pid; ?>"
											class="cart-remove-link checkout-item-remove">Remove</a>
									</div>
								</div>
								<div class="checkout-item-price-col">
									<?php if ($disc_pct > 0 && (float) ($item['line_mrp_total'] ?? 0) > 0): ?>
										<span class="checkout-item-was"
											aria-hidden="true">₹<?php echo number_format((float) $item['line_mrp_total'], 0); ?></span>
									<?php endif; ?>
									<span class="checkout-item-line-total cart-line-total<?php echo $disc_pct > 0 ? ' checkout-price--discount' : ''; ?>" data-product-id="<?php echo $pid; ?>">₹<?php echo number_format($item['line_total'], 0); ?></span>
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
						<div class="checkout-order-divider"></div>
						<div class="checkout-trust-strip">
							<svg class="checkout-shield" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
								aria-hidden="true">
								<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
							</svg>
							<span>Authentic 925 sterling silver.</span>
						</div>
					</div>

					<div class="checkout-promo-wrap">
						<?php
						$has_applied_promo = trim($checkout_prefill_promo_code) !== '';
						?>
						<div class="checkout-promo-applied" id="checkout-promo-applied-block"<?php echo $has_applied_promo ? '' : ' hidden'; ?>>
							<p class="checkout-section-label">Applied promo</p>
							<ul class="checkout-applied-promo-list" role="list">
								<li class="checkout-applied-promo-item">
									<div class="checkout-applied-promo-row">
										<span class="checkout-applied-promo-code" id="checkout-display-promo-code"><?php echo h($checkout_prefill_promo_code); ?></span>
										<span class="checkout-applied-promo-save" id="checkout-display-promo-amount">−₹<?php echo number_format(max(0, $checkout_prefill_discount_paise) / 100, 0); ?></span>
										<button type="button" class="checkout-promo-remove-btn" id="checkout-promo-remove">Remove</button>
									</div>
								</li>
							</ul>
							<p class="checkout-promo-limit-note">One promo code per order. Remove it to apply a different code.</p>
						</div>
						<div class="checkout-promo-entry" id="checkout-promo-entry-block"<?php echo $has_applied_promo ? ' hidden' : ''; ?>>
							<p class="checkout-section-label">Promo code</p>
							<div class="checkout-coupon-row">
								<input type="text" class="checkout-coupon-input" placeholder="Enter code"
									id="checkout-promo-code" value=""
									autocomplete="off" maxlength="40">
								<button type="button" class="checkout-coupon-btn" id="checkout-promo-apply">Apply</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Right: sticky column — delivery, totals, buttons (matches cart summary width) -->
				<div class="checkout-panel">
					<div class="checkout-panel-section-head">
						<p class="checkout-section-label checkout-panel-section-label">Delivery details</p>
						<?php if (!$is_guest): ?>
							<span class="checkout-delivery-header-actions" id="checkout-delivery-actions">
								<button type="button" class="checkout-edit-link" id="checkout-change-address-btn">Change address</button>
								<a href="<?php echo h($base_path); ?>logout" class="checkout-edit-link ms-2">Sign out</a>
							</span>
						<?php endif; ?>
					</div>
					<div class="checkout-delivery-card">
						<div id="checkout-delivery-body">
							<?php if ($is_guest): ?>
								<p class="small text-secondary mb-3">Enter where we should deliver your order. No account required.</p>
								<form id="checkout-guest-form" class="checkout-guest-form pearlica-profile-form" novalidate autocomplete="on">
									<div class="pearlica-form-field">
										<label for="checkout-guest-name" class="pearlica-form-label">Full name</label>
										<input type="text" id="checkout-guest-name" class="login-phone-input pearlica-form-input" name="full_name"
											autocomplete="name" maxlength="255" required value="<?php echo h($guest_form['full_name'] ?? ''); ?>">
									</div>
									<div class="pearlica-form-field">
										<label for="checkout-guest-email" class="pearlica-form-label">Email</label>
										<input type="email" id="checkout-guest-email" class="login-phone-input pearlica-form-input" name="email"
											autocomplete="email" maxlength="255" required value="<?php echo h($guest_form['email'] ?? ''); ?>">
									</div>
									<div class="pearlica-form-field">
										<label for="checkout-guest-phone" class="pearlica-form-label">Mobile number</label>
										<div class="login-phone-field">
											<span class="login-phone-prefix" aria-hidden="true"><span class="login-flag">&#127470;&#127475;</span><span>+91</span></span>
											<input type="tel" id="checkout-guest-phone" class="login-phone-input" name="phone"
												inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="10-digit mobile" required
												value="<?php echo h($guest_form['phone'] ?? ''); ?>">
										</div>
									</div>
									<div class="pearlica-form-field">
										<label for="checkout-guest-line1" class="pearlica-form-label">Address line 1</label>
										<input type="text" id="checkout-guest-line1" class="login-phone-input pearlica-form-input" name="address"
											autocomplete="address-line1" maxlength="255" required value="<?php echo h($guest_form['address'] ?? ''); ?>">
									</div>
									<div class="pearlica-form-field">
										<label for="checkout-guest-line2" class="pearlica-form-label">Address line 2 <span class="pearlica-optional">(optional)</span></label>
										<input type="text" id="checkout-guest-line2" class="login-phone-input pearlica-form-input" name="address_line_2"
											autocomplete="address-line2" maxlength="255" value="<?php echo h($guest_form['address_line_2'] ?? ''); ?>">
									</div>
									<div class="pearlica-form-row">
										<div class="pearlica-form-field">
											<label for="checkout-guest-city" class="pearlica-form-label">City</label>
											<input type="text" id="checkout-guest-city" class="login-phone-input pearlica-form-input" name="city"
												maxlength="120" required value="<?php echo h($guest_form['city'] ?? ''); ?>">
										</div>
										<div class="pearlica-form-field">
											<label for="checkout-guest-state" class="pearlica-form-label">State</label>
											<select id="checkout-guest-state" class="login-phone-input pearlica-form-input pearlica-form-select" name="state" required>
												<option value="">Select state</option>
												<?php foreach ($checkout_states as $st): ?>
													<option value="<?php echo h($st); ?>"<?php echo ($guest_form['state'] ?? '') === $st ? ' selected' : ''; ?>><?php echo h($st); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
									</div>
									<div class="pearlica-form-field">
										<label for="checkout-guest-pincode" class="pearlica-form-label">Pincode</label>
										<input type="text" id="checkout-guest-pincode" class="login-phone-input pearlica-form-input" name="pincode"
											inputmode="numeric" maxlength="6" required value="<?php echo h($guest_form['pincode'] ?? ''); ?>">
									</div>
									<p class="checkout-guest-form-error text-danger small" id="checkout-guest-form-error" hidden role="alert"></p>
								</form>
								<div class="checkout-cod-otp-block" id="checkout-cod-otp-block" hidden>
									<p class="checkout-section-label">Confirm mobile for COD</p>
									<p class="small text-secondary mb-2">Enter the code sent to <strong id="checkout-cod-phone-mask">your number</strong> to place a Cash on Delivery order.</p>
									<input type="text" id="checkout-cod-otp" class="login-otp-input checkout-cod-otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="0000">
									<button type="button" class="checkout-btn-secondary checkout-cod-otp-resend" id="checkout-cod-otp-resend">Resend code</button>
									<p class="checkout-cod-otp-error text-danger small" id="checkout-cod-otp-error" hidden role="alert"></p>
								</div>
							<?php else: ?>
								<div class="checkout-recipient-name"><?php echo h($user['full_name'] ?: ($user['email'] ?? '')); ?>
								</div>
								<div class="checkout-contact-info">
									<?php if (trim($user['phone'] ?? '') !== ''): ?>
										<span><?php echo h($user['phone']); ?></span>
									<?php endif; ?>
									<?php if (trim((string) ($user['email'] ?? '')) !== ''): ?>
										<span><?php echo h((string) $user['email']); ?></span>
									<?php endif; ?>
								</div>
								<?php if ($shipping_line !== ''): ?>
									<div class="checkout-address-block" id="checkout-address-display"><?php echo nl2br(h($shipping_line)); ?></div>
								<?php else: ?>
									<div class="checkout-address-block" id="checkout-address-display">Your delivery address will be collected before payment.</div>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>

					<div class="checkout-total-card">
						<div class="checkout-total-rows">
							<?php if ($product_savings >= 0.01): ?>
								<div class="checkout-total-row checkout-total-row--muted">
									<span>MRP total</span>
									<span class="checkout-total-mrp">₹<?php echo number_format($mrp_subtotal, 0); ?></span>
								</div>
								<div
									class="checkout-total-row checkout-total-row--savings checkout-total-row--product-save">
									<span>Product discount</span>
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
							<div class="checkout-total-row checkout-total-row--gift">
								<label class="checkout-gift-label" for="checkout-gift-packaging">
									<input type="checkbox" id="checkout-gift-packaging" name="gift_packaging" value="1"
										autocomplete="off">
									<span class="checkout-gift-text">Gift packaging</span>
									<span
										class="checkout-gift-hint">(+₹<?php echo number_format(CHECKOUT_GIFT_PACKAGING_PAISE / 100, 0); ?>)</span>
								</label>
								<span id="checkout-gift-value" class="checkout-gift-amount">—</span>
							</div>
							<div class="checkout-total-row checkout-total-row--savings">
								<span>Promo code</span>
								<span id="checkout-promo-value" class="<?php echo $checkout_prefill_discount_paise > 0 ? 'checkout-promo-value--active' : ''; ?>"><?php echo $checkout_prefill_discount_paise > 0 ? '−₹' . number_format($checkout_prefill_discount_paise / 100, 0) : '—'; ?></span>
							</div>
							<div class="checkout-total-row grand">
								<span>Order Total</span>
								<span class="checkout-amount"
									id="checkout-order-total">₹<?php echo number_format($initial_grand_paise / 100, 0); ?></span>
							</div>
						</div>
						<ul class="checkout-shipping-policy small text-secondary mb-3 ps-3">
							<li class="mb-1"><strong>Shipping charges &amp; COD</strong></li>
							<li class="mb-1">A standard shipping charge of ₹<?php echo number_format((int) CHECKOUT_SHIPPING_FEE_BELOW_MIN_PAISE / 100, 0); ?> applies to orders below ₹<?php echo number_format((int) CHECKOUT_FREE_SHIPPING_MIN_MERCH_PAISE / 100, 0); ?>.</li>
							<li class="mb-0">Cash on Delivery (COD) is available for eligible orders. COD is not applicable on orders exceeding ₹<?php echo number_format((int) CHECKOUT_COD_MAX_ORDER_RUPEES, 0); ?>.</li>
						</ul>
						<div class="checkout-payment-methods">
							<p class="checkout-section-label checkout-payment-methods__title">Payment</p>
							<input type="hidden" id="checkout-payment-method" name="payment_method" value="razorpay">
							<div class="checkout-payment-methods__cod-wrap mb-2" id="checkout-cod-wrap">
								<div class="checkout-payment-methods__row">
									<label class="checkout-payment-methods__label" for="checkout-cod">
										<input type="checkbox" id="checkout-cod" autocomplete="off">
										<span>Cash on Delivery — pay when your order arrives (orders up to ₹<?php echo number_format((int) CHECKOUT_COD_MAX_ORDER_RUPEES, 0); ?> only).</span>
									</label>
								</div>
							</div>
							<p class="checkout-payment-methods__note small text-secondary mb-2" id="checkout-cod-unavailable-note" hidden>Cash on Delivery is only available when your order total is ₹<?php echo number_format((int) CHECKOUT_COD_MAX_ORDER_RUPEES, 0); ?> or less.</p>
							<div id="checkout-online-pay-block">
								<p class="checkout-payment-methods__note small text-secondary mb-0">Pay securely online via
									Razorpay (UPI, cards, net banking).</p>
								<?php if (!$razorpay_ready): ?>
									<div class="text-danger small mt-2" id="checkout-razorpay-down-msg">
										<?php if ($cod_eligible_initial): ?>
											Online payments are temporarily unavailable. You can still place your order with Cash on Delivery.
										<?php else: ?>
											Online payments are temporarily unavailable. Please try again later or contact us for help.
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<div class="checkout-payment-icons" role="list" aria-label="Accepted payment methods">
									<span class="checkout-pay-icon" role="listitem">
										<img src="<?php echo h($base_path); ?>assets/images/payments/visa.svg" width="44" height="18" class="checkout-pay-logo" alt="Visa" loading="lazy" decoding="async">
									</span>
									<span class="checkout-pay-icon" role="listitem">
										<img src="<?php echo h($base_path); ?>assets/images/payments/mastercard.svg" width="40" height="22" class="checkout-pay-logo" alt="Mastercard" loading="lazy" decoding="async">
									</span>
									<span class="checkout-pay-icon" role="listitem">
										<img src="<?php echo h($base_path); ?>assets/images/payments/upi.png" width="28" height="28" class="checkout-pay-logo checkout-pay-logo--square" alt="UPI" loading="lazy" decoding="async">
									</span>
									<span class="checkout-pay-icon" role="listitem">
										<img src="<?php echo h($base_path); ?>assets/images/payments/googlepay.png" width="72" height="22" class="checkout-pay-logo checkout-pay-logo--wide" alt="Google Pay" loading="lazy" decoding="async">
									</span>
								</div>
							</div>
						</div>
						<button type="button" class="checkout-btn-primary" id="checkout-place-order"
							data-csrf-token="<?= h($checkout_csrf_token) ?>" <?= $checkout_possible ? '' : 'disabled' ?>
							data-default-label="Proceed"
							aria-disabled="<?= $checkout_possible ? 'false' : 'true' ?>">Proceed</button>
						<input type="hidden" id="checkout-applied-promo-code"
							value="<?php echo h($checkout_prefill_promo_code); ?>">
						<input type="hidden" id="checkout-applied-promo-id"
							value="<?php echo (int) $checkout_prefill_promo_id; ?>">
						<input type="hidden" id="checkout-applied-discount-paise"
							value="<?php echo (int) $checkout_prefill_discount_paise; ?>">
						<a href="<?php echo h($base_path); ?>products" class="checkout-btn-secondary">Continue shopping</a>
						<div class="checkout-secure-note" id="checkout-secure-note-block">
							<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<rect x="3" y="11" width="18" height="11" />
								<path d="M7 11V7a5 5 0 0 1 10 0v4" />
							</svg>
							<span>SSL</span>
						</div>
					</div>
				</div>
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
</body>

</html>