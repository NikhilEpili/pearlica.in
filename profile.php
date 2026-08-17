<?php
/**
 * User profile: view and edit name, email, phone, address, city, state, pincode, birthday, gender.
 * Requires login. Uses session for CSRF.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/store_user.php';
require_once __DIR__ . '/includes/auth_cookie.php';
require_once __DIR__ . '/includes/indian_states.php';

if (!function_exists('h')) {
	function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

$base_path = '';
$user_id = auth_cookie_user_id();
if ($user_id === null) {
	header('Location: ' . $base_path . 'login?return_url=' . rawurlencode('profile'), true, 302);
	exit;
}

function profile_csrf_token(): string {
	if (empty($_SESSION['profile_csrf_token'])) {
		$_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['profile_csrf_token'];
}
function profile_validate_csrf(): bool {
	$token = $_POST['csrf_token'] ?? '';
	return hash_equals($_SESSION['profile_csrf_token'] ?? '', $token);
}

/**
 * Normalize/validate birthday (YYYY-MM-DD). Empty → null. Invalid → false.
 *
 * @return string|null|false
 */
function profile_normalize_birthday(string $raw)
{
	$raw = trim($raw);
	if ($raw === '') {
		return null;
	}
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
		return false;
	}
	$y = (int) $m[1];
	$mo = (int) $m[2];
	$d = (int) $m[3];
	if (!checkdate($mo, $d, $y)) {
		return false;
	}
	$year_now = (int) date('Y');
	if ($y < 1900 || $y > $year_now) {
		return false;
	}
	$ts = strtotime($raw . ' 00:00:00');
	if ($ts === false || $ts > time()) {
		return false;
	}
	return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

$pdo = $GLOBALS['pdo'];
$user = store_user_find_by_id($pdo, $user_id);
if (!$user) {
	auth_cookie_clear();
	header('Location: ' . $base_path . 'login?return_url=profile', true, 302);
	exit;
}

$states = indian_states_list();
$user_state = (string) ($user['state'] ?? '');
$phone_locked = trim((string) ($user['phone'] ?? '')) !== '';

$success = '';
$errors = [];
$form = [
	'full_name' => (string) ($user['full_name'] ?? ''),
	'email' => trim((string) ($user['email'] ?? '')),
	'phone' => (string) ($user['phone'] ?? ''),
	'address' => (string) ($user['address'] ?? ''),
	'address_line_2' => (string) ($user['address_line_2'] ?? ''),
	'city' => (string) ($user['city'] ?? ''),
	'state' => $user_state,
	'pincode' => (string) ($user['pincode'] ?? ''),
	'birthday' => (string) ($user['birthday'] ?? ''),
	'gender' => (string) ($user['gender'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
	if (!profile_validate_csrf()) {
		$errors[] = 'Invalid request. Please try again.';
	} else {
		$full_name = trim((string) ($_POST['full_name'] ?? ''));
		$email = trim((string) ($_POST['email'] ?? ''));
		$address = trim((string) ($_POST['address'] ?? ''));
		$address_line_2 = trim((string) ($_POST['address_line_2'] ?? ''));
		$city = trim((string) ($_POST['city'] ?? ''));
		$state = trim((string) ($_POST['state'] ?? ''));
		$pincode = trim((string) ($_POST['pincode'] ?? ''));
		$birthday_raw = trim((string) ($_POST['birthday'] ?? ''));
		$gender = strtolower(trim((string) ($_POST['gender'] ?? '')));

		$form = [
			'full_name' => $full_name,
			'email' => $email,
			'phone' => $phone_locked ? (string) ($user['phone'] ?? '') : trim((string) ($_POST['phone'] ?? '')),
			'address' => $address,
			'address_line_2' => $address_line_2,
			'city' => $city,
			'state' => $state,
			'pincode' => $pincode,
			'birthday' => $birthday_raw,
			'gender' => $gender,
		];

		if ($full_name === '') {
			$errors[] = 'Please enter your full name.';
		} elseif (function_exists('mb_strlen') ? mb_strlen($full_name) > 255 : strlen($full_name) > 255) {
			$errors[] = 'Full name is too long.';
		}

		if ($email !== '') {
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$errors[] = 'Please enter a valid email address.';
			} elseif (store_user_email_taken($pdo, $email, $user_id)) {
				$errors[] = 'That email is already linked to another account.';
			}
		}

		if ($gender !== '' && !in_array($gender, ['female', 'male', 'other'], true)) {
			$errors[] = 'Please select a valid gender.';
			$form['gender'] = '';
			$gender = '';
		}

		$birthday = profile_normalize_birthday($birthday_raw);
		if ($birthday === false) {
			$errors[] = 'Please enter a valid date of birth.';
			$birthday = null;
		}

		$pincode_digits = preg_replace('/\D+/', '', $pincode);
		if ($pincode !== '' && (strlen((string) $pincode_digits) !== 6)) {
			$errors[] = 'Please enter a valid 6-digit pincode.';
		} else {
			$pincode = (string) $pincode_digits;
			$form['pincode'] = $pincode;
		}

		if ($errors === []) {
			$update = [
				'full_name' => $full_name,
				'email' => $email,
				'address' => $address,
				'address_line_2' => $address_line_2,
				'city' => $city,
				'state' => $state,
				'pincode' => $pincode,
				'birthday' => $birthday,
				'gender' => $gender,
			];
			// Phone is the login identity — do not overwrite from the form when already set.
			if (!$phone_locked) {
				$update['phone'] = trim((string) ($_POST['phone'] ?? ''));
			}
			try {
				store_user_update($pdo, $user_id, $update);
				$user = store_user_find_by_id($pdo, $user_id);
				$user_state = (string) ($user['state'] ?? '');
				$phone_locked = trim((string) ($user['phone'] ?? '')) !== '';
				$form = [
					'full_name' => (string) ($user['full_name'] ?? ''),
					'email' => trim((string) ($user['email'] ?? '')),
					'phone' => (string) ($user['phone'] ?? ''),
					'address' => (string) ($user['address'] ?? ''),
					'address_line_2' => (string) ($user['address_line_2'] ?? ''),
					'city' => (string) ($user['city'] ?? ''),
					'state' => $user_state,
					'pincode' => (string) ($user['pincode'] ?? ''),
					'birthday' => (string) ($user['birthday'] ?? ''),
					'gender' => (string) ($user['gender'] ?? ''),
				];
				$success = 'Profile updated successfully.';
			} catch (Throwable $e) {
				$errors[] = 'Could not save your profile. Please try again.';
			}
		}
	}
}

$page_title = 'My Profile | Pearlica 925 Silver Jewellery';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/components/head.php'; ?>
<link rel="stylesheet" href="<?php echo h($base_path); ?>assets/css/profile.css">
<body class="profile-page">
	<?php include __DIR__ . '/components/navbar.php'; ?>
	<main class="profile-main">
		<div class="profile-wrap page-content-constrained">
			<div class="profile-content">
				<p class="profile-content-label">Account</p>
				<h1 class="profile-content-title">My Profile</h1>
				<?php if ($success !== ''): ?>
					<div class="profile-alert profile-alert-success"><?php echo h($success); ?></div>
				<?php endif; ?>
				<?php foreach ($errors as $e): ?>
					<div class="profile-alert profile-alert-danger"><?php echo h($e); ?></div>
				<?php endforeach; ?>
				<form method="post" action="" class="profile-form" novalidate>
					<input type="hidden" name="csrf_token" value="<?php echo h(profile_csrf_token()); ?>">
					<input type="hidden" name="action" value="update">
					<section class="profile-form-block profile-fade-up">
						<div class="profile-form-grid profile-form-grid-2">
							<div class="profile-field">
								<label for="full_name" class="profile-field-label">Full Name</label>
								<input type="text" id="full_name" name="full_name" class="profile-input" value="<?php echo h($form['full_name']); ?>" placeholder="Full name" autocomplete="name" maxlength="255" required>
							</div>
							<div class="profile-field">
								<label for="phone" class="profile-field-label">Phone</label>
								<input type="tel" id="phone" name="phone" class="profile-input<?php echo $phone_locked ? ' profile-input-disabled' : ''; ?>" value="<?php echo h($form['phone']); ?>" placeholder="Phone" autocomplete="tel" <?php echo $phone_locked ? 'readonly' : ''; ?>>
								<?php if ($phone_locked): ?>
									<span class="profile-field-note">Phone is used to sign in and cannot be changed here.</span>
								<?php endif; ?>
							</div>
							<div class="profile-field">
								<label for="birthday" class="profile-field-label">Birthday</label>
								<input type="date" id="birthday" name="birthday" class="profile-input profile-input-date" value="<?php echo h($form['birthday']); ?>" title="Date of birth" max="<?php echo h(date('Y-m-d')); ?>" min="1900-01-01" autocomplete="bday">
							</div>
							<div class="profile-field">
								<label for="gender" class="profile-field-label">Gender</label>
								<div class="select-wrap profile-select-wrap">
									<select id="gender" name="gender" class="profile-select">
										<option value="">Select</option>
										<option value="female" <?php echo $form['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
										<option value="male" <?php echo $form['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
										<option value="other" <?php echo $form['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
									</select>
								</div>
							</div>
						</div>
						<div class="profile-field profile-field-full">
							<label for="email" class="profile-field-label">Email</label>
							<input type="email" id="email" name="email" class="profile-input" value="<?php echo h($form['email']); ?>" placeholder="Email address" autocomplete="email" maxlength="255">
							<span class="profile-field-note"><?php echo $form['email'] === '' ? 'Add your email for order updates and receipts.' : 'Used for order updates and receipts.'; ?></span>
						</div>
					</section>
					<section class="profile-form-block profile-fade-up">
						<div class="profile-form-fields">
							<div class="profile-field profile-field-full">
								<label for="address" class="profile-field-label">Address Line 1</label>
								<input type="text" id="address" name="address" class="profile-input" value="<?php echo h($form['address']); ?>" placeholder="Street, building, area" autocomplete="address-line1">
							</div>
							<div class="profile-field profile-field-full">
								<label for="address_line_2" class="profile-field-label">Address Line 2</label>
								<input type="text" id="address_line_2" name="address_line_2" class="profile-input" value="<?php echo h($form['address_line_2']); ?>" placeholder="Landmark, floor, optional" autocomplete="address-line2">
							</div>
							<div class="profile-form-grid profile-form-grid-3">
								<div class="profile-field">
									<label for="city" class="profile-field-label">City</label>
									<input type="text" id="city" name="city" class="profile-input" value="<?php echo h($form['city']); ?>" placeholder="City" autocomplete="address-level2">
								</div>
								<div class="profile-field">
									<label for="state" class="profile-field-label">State</label>
									<div class="select-wrap profile-select-wrap">
										<select id="state" name="state" class="profile-select" autocomplete="address-level1">
											<option value="">Select state</option>
											<?php foreach ($states as $s): ?>
												<option value="<?php echo h($s); ?>" <?php echo $form['state'] === $s ? 'selected' : ''; ?>><?php echo h($s); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<div class="profile-field profile-field-pincode">
									<label for="pincode" class="profile-field-label">Pincode</label>
									<input type="text" id="pincode" name="pincode" class="profile-input" value="<?php echo h($form['pincode']); ?>" placeholder="Pincode" inputmode="numeric" maxlength="6" autocomplete="postal-code">
								</div>
							</div>
						</div>
					</section>
					<div class="profile-form-actions profile-fade-up">
						<button type="submit" class="profile-btn-save">Save profile</button>
					</div>
				</form>
			</div>
		</div>
	</main>
	<?php include __DIR__ . '/components/footer.php'; ?>
	<script src="<?php echo h($base_path); ?>assets/js/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
	<script src="<?php echo h($base_path); ?>assets/js/profile.js"></script>
</body>
</html>
