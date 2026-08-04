<?php
/**
 * Storefront login: TeleSign SMS OTP to mobile. Step 1 enter phone, step 2 enter OTP.
 * Creates store_users row on first successful verification. CSRF via session.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/store_user.php';
require_once __DIR__ . '/lib/telesign.php';
require_once __DIR__ . '/includes/auth_cookie.php';

if (!function_exists('h')) {
	function h($s)
	{
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}
}

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$base_path = '';
$return_url = isset($_GET['return_url']) ? trim((string) $_GET['return_url']) : 'profile';
if ($return_url !== '' && strpos($return_url, '://') !== false) {
	$return_url = 'profile';
}

if (auth_cookie_user_id() !== null) {
	header('Location: ' . $base_path . ($return_url !== '' ? $return_url : 'profile'), true, 302);
	exit;
}

function login_csrf_token(): string
{
	if (empty($_SESSION['login_csrf_token'])) {
		$_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
	}

	return $_SESSION['login_csrf_token'];
}

function login_validate_csrf(): bool
{
	$token = $_POST['csrf_token'] ?? '';

	return hash_equals($_SESSION['login_csrf_token'] ?? '', $token);
}

function login_telesign_rate_key(string $e164): string
{
	return 'telesign_rate_ts_' . hash('sha256', $e164);
}

function login_telesign_can_send(string $e164): array
{
	$key = login_telesign_rate_key($e164);
	$last = isset($_SESSION[$key]) ? (int) $_SESSION[$key] : 0;
	$elapsed = time() - $last;
	$cooldown = OTP_RATE_LIMIT_MINUTES * 60;
	if ($last > 0 && $elapsed < $cooldown) {
		return ['allowed' => false, 'wait_seconds' => $cooldown - $elapsed];
	}

	return ['allowed' => true, 'wait_seconds' => 0];
}

function login_telesign_mark_sent(string $e164): void
{
	$_SESSION[login_telesign_rate_key($e164)] = time();
}

function login_phone_mask_last4(string $e164_digits): string
{
	$d = preg_replace('/\D+/', '', $e164_digits);
	if (strlen($d) < 4) {
		return 'your number';
	}

	return '•••• ' . substr($d, -4);
}

$step = isset($_GET['step']) ? trim((string) $_GET['step']) : 'phone';
if ($step !== 'verify') {
	$step = 'phone';
}

$sent_at = isset($_GET['sent_at']) ? (int) $_GET['sent_at'] : 0;
if ($sent_at <= 0) {
	$sent_at = time();
}
$expires_at_ts = $sent_at + (OTP_EXPIRY_MINUTES * 60);
$error = '';
$pdo = $GLOBALS['pdo'];
$ts_cfg = telesign_config();
$login_otp_len = telesign_login_otp_digit_count();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!login_validate_csrf()) {
		$error = 'Invalid request. Please try again.';
	} elseif (isset($_POST['action']) && $_POST['action'] === 'request_otp') {
		$phone_raw = trim((string) ($_POST['phone'] ?? ''));
		$e164 = store_user_phone_e164_digits($phone_raw);
		if ($e164 === null) {
			$error = 'Please enter a valid Indian mobile number (10 digits).';
		} elseif (!$ts_cfg['configured']) {
			$error = 'SMS login is not configured yet. Please contact support.';
		} else {
			$rate = login_telesign_can_send($e164);
			if (!$rate['allowed']) {
				$wait = (int) $rate['wait_seconds'];
				$mins = (int) ceil($wait / 60);
				$error = 'Please wait ' . $mins . ' minute(s) before requesting another code.';
			} else {
				$send = telesign_sms_verify_send($e164);
				if (!empty($send['ok']) && !empty($send['reference_id'])) {
					$_SESSION['telesign_ref_id'] = (string) $send['reference_id'];
					$_SESSION['telesign_phone_e164'] = $e164;
					login_telesign_mark_sent($e164);
					$sent_at = time();
					header('Location: ' . $base_path . 'login?step=verify&return_url=' . rawurlencode($return_url) . '&sent_at=' . $sent_at, true, 302);
					exit;
				}
				$error = (string) ($send['error'] ?? 'Unable to send code. Please try again.');
			}
		}
	} elseif (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
		$otp = trim((string) ($_POST['otp'] ?? ''));
		$otp_digits = preg_replace('/\D+/', '', $otp);
		$ref = isset($_SESSION['telesign_ref_id']) ? trim((string) $_SESSION['telesign_ref_id']) : '';
		$sess_phone = isset($_SESSION['telesign_phone_e164']) ? trim((string) $_SESSION['telesign_phone_e164']) : '';
		if ($ref === '' || $sess_phone === '') {
			$error = 'Session expired. Please request a new code.';
			$step = 'phone';
		} elseif (strlen($otp_digits) !== $login_otp_len) {
			$error = 'Please enter the ' . $login_otp_len . '-digit code from your SMS.';
			$step = 'verify';
		} else {
			$chk = telesign_sms_verify_complete($ref, $otp_digits);
			if (empty($chk['verified'])) {
				$error = (string) ($chk['error'] ?? 'Invalid or expired code. Please request a new one.');
				$step = 'verify';
			} else {
				$user_id = store_user_ensure_exists_by_phone($pdo, $sess_phone);
				unset($_SESSION['telesign_ref_id'], $_SESSION['telesign_phone_e164']);
				session_regenerate_id(true);
				auth_cookie_set($user_id);
				header('Location: ' . $base_path . ($return_url !== '' ? $return_url : 'profile'), true, 302);
				exit;
			}
		}
	}
}

if ($step === 'verify' && (empty($_SESSION['telesign_ref_id']) || empty($_SESSION['telesign_phone_e164']))) {
	header('Location: ' . $base_path . 'login?return_url=' . rawurlencode($return_url), true, 302);
	exit;
}

$phone_mask = '';
if ($step === 'verify' && !empty($_SESSION['telesign_phone_e164'])) {
	$phone_mask = login_phone_mask_last4((string) $_SESSION['telesign_phone_e164']);
}

$page_title = 'Login | Pearlica 925 Silver Jewellery';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/components/head.php'; ?>

<body class="homepage">
	<?php include __DIR__ . '/components/navbar.php'; ?>
	<main>
		<section class="py-5 page-content-constrained">
			<div>
				<div style="height: 80vh;" class="row justify-content-center align-items-center">
					<div class="col-md-5">
						<?php if ($error !== ''): ?>
							<div class="alert alert-danger"><?php echo h($error); ?></div>
						<?php endif; ?>
						<?php if ($step === 'verify'): ?>
							<p class="text-secondary mb-2">We sent a <?php echo (int) $login_otp_len; ?>-digit code to <strong><?php echo h($phone_mask); ?></strong>.
								Enter it below.</p>
							<p class="mb-3 small text-secondary" id="otp-countdown-wrap"
								data-expires-at="<?php echo (int) $expires_at_ts; ?>">
								<span id="otp-countdown-label">Code valid for </span><strong
									id="otp-countdown">10:00</strong>
							</p>
							<p class="mb-3 small text-danger d-none" id="otp-expired-msg">Code expired. <a
									href="<?php echo h($base_path); ?>login?return_url=<?php echo rawurlencode($return_url); ?>">Request
									a new code</a></p>
							<form method="post" action="" id="otp-form">
								<input type="hidden" name="csrf_token" value="<?php echo h(login_csrf_token()); ?>">
								<input type="hidden" name="action" value="verify_otp">
								<div class="mb-3">
									<label for="otp" class="form-label">Verification code</label>
									<input type="text" id="otp" name="otp" class="form-control" maxlength="<?php echo (int) $login_otp_len; ?>"
										inputmode="numeric" pattern="[0-9]{<?php echo (int) $login_otp_len; ?>}" placeholder="<?php echo str_repeat('0', (int) $login_otp_len); ?>"
										autocomplete="one-time-code" autocapitalize="off" spellcheck="false" required>
								</div>
								<button type="submit" class="btn btn-dark text-uppercase w-100" id="otp-submit-btn">Verify
									and log in</button>
							</form>
							<p class="mt-3 small text-secondary">
								<a
									href="<?php echo h($base_path); ?>login?return_url=<?php echo rawurlencode($return_url); ?>">Use
									a different number?</a>
							</p>
							<script src="<?php echo h($base_path); ?>assets/js/login-otp-countdown.js"></script>
							<script src="<?php echo h($base_path); ?>assets/js/login-otp-input.js"></script>
						<?php else: ?>
							<p class="text-secondary mb-4">Enter your mobile number and we’ll send you a one-time code via
								SMS to log in. No password needed.</p>
							<?php if (!$ts_cfg['configured']): ?>
								<div class="alert alert-warning small">SMS login is not configured on this server.</div>
							<?php endif; ?>
							<form method="post" action="">
								<input type="hidden" name="csrf_token" value="<?php echo h(login_csrf_token()); ?>">
								<input type="hidden" name="action" value="request_otp">
								<div class="mb-3">
									<label for="phone" class="form-label">Mobile number</label>
									<input type="tel" id="phone" name="phone" class="form-control"
										value="<?php echo h($_POST['phone'] ?? ''); ?>"
										placeholder="9876543210 or +91 9876543210" inputmode="tel" autocomplete="tel"
										required>
								</div>
								<button type="submit" class="btn btn-dark text-uppercase w-100" <?php echo $ts_cfg['configured'] ? '' : 'disabled'; ?>>Send SMS
									code</button>
							</form>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>
	</main>
	<?php include __DIR__ . '/components/footer.php'; ?>
	<script src="<?php echo h($base_path); ?>assets/js/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"
		crossorigin="anonymous"></script>
</body>

</html>
