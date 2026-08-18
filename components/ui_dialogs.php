<?php
/**
 * Global UI dialogs: toasts, alert modal, auth + address modals.
 */
declare(strict_types=1);

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

require_once __DIR__ . '/../includes/auth_cookie.php';
require_once __DIR__ . '/../includes/login_csrf.php';
require_once __DIR__ . '/../lib/telesign.php';
require_once __DIR__ . '/../lib/store_user.php';
require_once __DIR__ . '/../includes/indian_states.php';
if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
	require_once __DIR__ . '/../config/db.php';
}

$ui_base = isset($base_path) ? $base_path : (isset($footer_base) ? $footer_base : '');
$ui_logged_in = auth_cookie_user_id() !== null;
$ui_login_csrf = login_csrf_token();
$ui_ts_cfg = telesign_config();
$ui_otp_len = telesign_login_otp_digit_count();
$ui_login_logo = $ui_base . 'assets/images/white-logo_trans-bg.png';
$ui_states = indian_states_list();
$ui_registration_complete = '0';
$ui_shipping_complete = '0';
if ($ui_logged_in && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
	try {
		$ui_user_row = store_user_find_by_id($GLOBALS['pdo'], (int) auth_cookie_user_id());
		if ($ui_user_row) {
			$ui_registration_complete = store_user_registration_complete($ui_user_row) ? '1' : '0';
			$ui_shipping_complete = store_user_shipping_complete($ui_user_row, $GLOBALS['pdo']) ? '1' : '0';
		}
	} catch (Throwable $e) {
		error_log('Pearlica ui_dialogs profile flags: ' . $e->getMessage());
	}
}
?>
<div id="pearlica-toast-container" class="pearlica-toast-container" aria-live="polite" aria-atomic="true"></div>

<div id="pearlica-alert-modal" class="pearlica-modal" aria-hidden="true" role="dialog" aria-modal="true"
	aria-labelledby="pearlica-alert-title">
	<div class="pearlica-modal__overlay" data-pearlica-modal-close></div>
	<div class="pearlica-modal__panel pearlica-alert-panel">
		<p class="pearlica-alert-panel__kicker" id="pearlica-alert-kicker">Notice</p>
		<h2 class="pearlica-alert-panel__title" id="pearlica-alert-title"></h2>
		<p class="pearlica-alert-panel__message" id="pearlica-alert-message"></p>
		<div class="pearlica-alert-panel__actions">
			<button type="button" class="pearlica-alert-panel__btn" id="pearlica-alert-ok">OK</button>
		</div>
	</div>
</div>

<div id="pearlica-login-modal" class="pearlica-modal" aria-hidden="true" role="dialog" aria-modal="true"
	aria-labelledby="pearlica-auth-title">
	<div class="pearlica-modal__overlay" data-pearlica-modal-close></div>
	<div class="pearlica-modal__panel pearlica-login-panel">
		<button type="button" class="pearlica-modal__close" data-pearlica-modal-close aria-label="Close">&times;</button>
		<div class="login-card">
			<?php include __DIR__ . '/auth_brand_panel.php'; ?>
			<div class="login-card-form">
				<p class="login-modal-context" id="pearlica-login-context" hidden></p>
				<div id="pearlica-auth-error" class="login-alert login-alert-danger" role="alert" hidden></div>

				<!-- Sign in -->
				<div class="pearlica-auth-step" id="pearlica-auth-step-login">
					<h1 class="login-form-title" id="pearlica-auth-title">Sign in</h1>
					<p class="login-form-subtitle">Enter your mobile number and password.</p>
					<form id="pearlica-auth-login-form" class="pearlica-profile-form" novalidate>
						<div class="pearlica-form-field">
							<label for="pearlica-auth-login-phone" class="pearlica-form-label">Mobile number</label>
							<div class="login-phone-field">
								<span class="login-phone-prefix" aria-hidden="true"><span class="login-flag">&#127470;&#127475;</span><span>+91</span></span>
								<input type="tel" id="pearlica-auth-login-phone" class="login-phone-input" inputmode="numeric"
									autocomplete="tel-national" maxlength="10" placeholder="10-digit mobile" required>
							</div>
						</div>
						<div class="pearlica-form-field">
							<label for="pearlica-auth-login-password" class="pearlica-form-label">Password</label>
							<input type="password" id="pearlica-auth-login-password" class="login-phone-input pearlica-form-input"
								autocomplete="current-password" minlength="8" required>
						</div>
						<div class="pearlica-auth-actions-row">
							<button type="button" class="pearlica-auth-link-btn" id="pearlica-auth-forgot-btn">Forgot password?</button>
						</div>
						<button type="submit" class="login-submit" id="pearlica-auth-login-submit">Sign in</button>
					</form>
					<p class="pearlica-auth-switch">New to Pearlica?
						<button type="button" class="pearlica-auth-link-btn" id="pearlica-auth-show-register">Create an account</button>
					</p>
				</div>

				<!-- Register -->
				<div class="pearlica-auth-step" id="pearlica-auth-step-register" hidden>
					<h1 class="login-form-title">Create your account</h1>
					<p class="login-form-subtitle">A few details, then a one-time code to verify your mobile.</p>
					<form id="pearlica-auth-register-form" class="pearlica-profile-form" novalidate>
						<div class="pearlica-form-field">
							<label for="pearlica-reg-full-name" class="pearlica-form-label">Full name *</label>
							<input type="text" id="pearlica-reg-full-name" class="login-phone-input pearlica-form-input" autocomplete="name" maxlength="255" required>
						</div>
						<div class="pearlica-form-field">
							<label for="pearlica-reg-email" class="pearlica-form-label">Email *</label>
							<input type="email" id="pearlica-reg-email" class="login-phone-input pearlica-form-input" autocomplete="email" maxlength="255" required>
						</div>
						<div class="pearlica-form-field">
							<label for="pearlica-reg-phone" class="pearlica-form-label">Mobile number *</label>
							<div class="login-phone-field">
								<span class="login-phone-prefix" aria-hidden="true"><span class="login-flag">&#127470;&#127475;</span><span>+91</span></span>
								<input type="tel" id="pearlica-reg-phone" class="login-phone-input" inputmode="numeric"
									autocomplete="tel-national" maxlength="10" placeholder="10-digit mobile" required>
							</div>
						</div>
						<div class="pearlica-form-row">
							<div class="pearlica-form-field">
								<label for="pearlica-reg-birthday" class="pearlica-form-label">Date of birth *</label>
								<input type="date" id="pearlica-reg-birthday" class="login-phone-input pearlica-form-input"
									max="<?php echo h(date('Y-m-d')); ?>" min="1900-01-01" required>
							</div>
							<div class="pearlica-form-field">
								<label for="pearlica-reg-gender" class="pearlica-form-label">Gender *</label>
								<select id="pearlica-reg-gender" class="login-phone-input pearlica-form-input pearlica-form-select" required>
									<option value="">Select</option>
									<option value="female">Female</option>
									<option value="male">Male</option>
									<option value="other">Other</option>
								</select>
							</div>
						</div>
						<div class="pearlica-form-field">
							<label for="pearlica-reg-password" class="pearlica-form-label">Password *</label>
							<input type="password" id="pearlica-reg-password" class="login-phone-input pearlica-form-input" autocomplete="new-password" minlength="8" required>
						</div>
						<div class="pearlica-form-field">
							<label for="pearlica-reg-password-confirm" class="pearlica-form-label">Confirm password *</label>
							<input type="password" id="pearlica-reg-password-confirm" class="login-phone-input pearlica-form-input" autocomplete="new-password" minlength="8" required>
						</div>
						<button type="submit" class="login-submit" id="pearlica-reg-submit" <?php echo $ui_ts_cfg['configured'] ? '' : 'disabled'; ?>>Send verification code</button>
					</form>
					<p class="pearlica-auth-switch">Already have an account?
						<button type="button" class="pearlica-auth-link-btn" id="pearlica-auth-show-login">Sign in</button>
					</p>
				</div>

				<!-- Register OTP -->
				<div class="pearlica-auth-step" id="pearlica-auth-step-register-verify" hidden>
					<h1 class="login-form-title">Verify your mobile</h1>
					<p class="login-form-subtitle">Enter the <?php echo (int) $ui_otp_len; ?>-digit code sent to <strong id="pearlica-reg-phone-mask">your number</strong>.</p>
					<p class="login-countdown" id="pearlica-reg-otp-countdown-wrap"><span>Code valid for </span><strong id="pearlica-reg-otp-countdown">10:00</strong></p>
					<form id="pearlica-auth-register-verify-form" novalidate>
						<input type="text" id="pearlica-reg-otp" class="login-otp-input" maxlength="<?php echo (int) $ui_otp_len; ?>"
							inputmode="numeric" autocomplete="one-time-code" placeholder="<?php echo str_repeat('0', (int) $ui_otp_len); ?>" required>
						<button type="submit" class="login-submit">Verify &amp; create account</button>
					</form>
					<p class="login-meta"><button type="button" class="pearlica-auth-link-btn" id="pearlica-reg-back">Edit details</button></p>
				</div>

				<!-- Forgot password -->
				<div class="pearlica-auth-step" id="pearlica-auth-step-forgot" hidden>
					<h1 class="login-form-title">Forgot password</h1>
					<p class="login-form-subtitle">Enter your registered mobile number. We&rsquo;ll send a verification code.</p>
					<form id="pearlica-auth-forgot-form" class="pearlica-profile-form" novalidate>
						<div class="login-phone-field">
							<span class="login-phone-prefix" aria-hidden="true"><span class="login-flag">&#127470;&#127475;</span><span>+91</span></span>
							<input type="tel" id="pearlica-forgot-phone" class="login-phone-input" inputmode="numeric" maxlength="10" required>
						</div>
						<button type="submit" class="login-submit">Send code</button>
					</form>
					<p class="login-meta"><button type="button" class="pearlica-auth-link-btn" id="pearlica-forgot-back">Back to sign in</button></p>
				</div>

				<!-- Reset password -->
				<div class="pearlica-auth-step" id="pearlica-auth-step-forgot-reset" hidden>
					<h1 class="login-form-title">Set new password</h1>
					<p class="login-form-subtitle">Code sent to <strong id="pearlica-forgot-phone-mask">your number</strong>.</p>
					<form id="pearlica-auth-forgot-reset-form" class="pearlica-profile-form" novalidate>
						<input type="text" id="pearlica-forgot-otp" class="login-otp-input" maxlength="<?php echo (int) $ui_otp_len; ?>"
							inputmode="numeric" placeholder="<?php echo str_repeat('0', (int) $ui_otp_len); ?>" required>
						<div class="pearlica-form-field">
							<label for="pearlica-forgot-password" class="pearlica-form-label">New password</label>
							<input type="password" id="pearlica-forgot-password" class="login-phone-input pearlica-form-input" minlength="8" required>
						</div>
						<div class="pearlica-form-field">
							<label for="pearlica-forgot-password-confirm" class="pearlica-form-label">Confirm password</label>
							<input type="password" id="pearlica-forgot-password-confirm" class="login-phone-input pearlica-form-input" minlength="8" required>
						</div>
						<button type="submit" class="login-submit">Update password</button>
					</form>
				</div>
				<p class="pearlica-auth-signout" id="pearlica-auth-signout-wrap" <?php echo $ui_logged_in ? '' : 'hidden'; ?>>
					<a href="<?php echo h($ui_base); ?>logout" class="pearlica-auth-link-btn">Sign out</a>
				</p>
			</div>
		</div>
	</div>
</div>

<div id="pearlica-address-modal" class="pearlica-modal" aria-hidden="true" role="dialog" aria-modal="true">
	<div class="pearlica-modal__overlay" data-pearlica-address-dismiss></div>
	<div class="pearlica-modal__panel pearlica-login-panel pearlica-address-panel-wide">
		<button type="button" class="pearlica-modal__close" data-pearlica-address-dismiss aria-label="Close">&times;</button>
		<div class="login-card login-card--address">
			<?php include __DIR__ . '/auth_brand_panel.php'; ?>
			<div class="login-card-form">
				<p class="login-modal-context" id="pearlica-address-context">Choose or add a delivery address before payment.</p>
				<div id="pearlica-address-error" class="login-alert login-alert-danger" role="alert" hidden></div>
				<h1 class="login-form-title">Delivery address</h1>

				<div id="pearlica-saved-addresses-wrap" class="pearlica-saved-addresses" hidden>
					<p class="pearlica-form-label">Saved addresses</p>
					<div id="pearlica-saved-addresses-list" class="pearlica-saved-addresses-list"></div>
					<button type="button" class="pearlica-auth-link-btn pearlica-add-address-toggle" id="pearlica-add-address-btn">+ Add new address</button>
				</div>

				<form id="pearlica-address-form" class="pearlica-profile-form" novalidate>
					<div class="pearlica-form-field">
						<label for="pearlica-addr-label" class="pearlica-form-label">Label <span class="pearlica-form-optional">(optional)</span></label>
						<input type="text" id="pearlica-addr-label" class="login-phone-input pearlica-form-input" placeholder="Home, Office…" maxlength="80">
					</div>
					<div class="pearlica-form-field">
						<label for="pearlica-addr-line1" class="pearlica-form-label">Address line 1 *</label>
						<input type="text" id="pearlica-addr-line1" class="login-phone-input pearlica-form-input" autocomplete="address-line1" maxlength="255" required>
					</div>
					<div class="pearlica-form-field">
						<label for="pearlica-addr-line2" class="pearlica-form-label">Address line 2 <span class="pearlica-form-optional">(optional)</span></label>
						<input type="text" id="pearlica-addr-line2" class="login-phone-input pearlica-form-input" autocomplete="address-line2" maxlength="255">
					</div>
					<div class="pearlica-form-row pearlica-form-row--3">
						<div class="pearlica-form-field">
							<label for="pearlica-addr-city" class="pearlica-form-label">City *</label>
							<input type="text" id="pearlica-addr-city" class="login-phone-input pearlica-form-input" maxlength="120" required>
						</div>
						<div class="pearlica-form-field">
							<label for="pearlica-addr-state" class="pearlica-form-label">State *</label>
							<select id="pearlica-addr-state" class="login-phone-input pearlica-form-input pearlica-form-select" required>
								<option value="">Select</option>
								<?php foreach ($ui_states as $st): ?>
									<option value="<?php echo h($st); ?>"><?php echo h($st); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pearlica-form-field">
							<label for="pearlica-addr-pincode" class="pearlica-form-label">Pincode *</label>
							<input type="text" id="pearlica-addr-pincode" class="login-phone-input pearlica-form-input" inputmode="numeric" maxlength="6" required>
						</div>
					</div>
					<label class="login-checkbox-row">
						<input type="checkbox" id="pearlica-addr-save" value="1" checked>
						<span class="login-checkbox-label">Save this address for next time</span>
					</label>
					<button type="submit" class="login-submit" id="pearlica-address-submit">Use this address &amp; proceed</button>
				</form>
				<p class="pearlica-auth-signout" id="pearlica-address-signout-wrap" <?php echo $ui_logged_in ? '' : 'hidden'; ?>>
					<a href="<?php echo h($ui_base); ?>logout" class="pearlica-auth-link-btn">Sign out</a>
				</p>
			</div>
		</div>
	</div>
</div>

<script>
document.body.setAttribute('data-logged-in', <?php echo $ui_logged_in ? "'1'" : "'0'"; ?>);
document.body.setAttribute('data-base-path', <?php echo json_encode($ui_base); ?>);
document.body.setAttribute('data-login-csrf', <?php echo json_encode($ui_login_csrf); ?>);
document.body.setAttribute('data-login-otp-length', <?php echo (int) $ui_otp_len; ?>);
document.body.setAttribute('data-registration-complete', <?php echo json_encode($ui_registration_complete); ?>);
document.body.setAttribute('data-shipping-complete', <?php echo json_encode($ui_shipping_complete); ?>);
document.body.setAttribute('data-sms-configured', <?php echo $ui_ts_cfg['configured'] ? "'1'" : "'0'"; ?>);
</script>
