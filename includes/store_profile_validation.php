<?php
/**
 * Shared validation for storefront profile registration and shipping.
 */
declare(strict_types=1);

require_once __DIR__ . '/indian_states.php';
require_once __DIR__ . '/../lib/store_user.php';

if (!function_exists('store_profile_normalize_birthday')) {
	/**
	 * @return string|null|false
	 */
	function store_profile_normalize_birthday(string $raw)
	{
		$raw = trim($raw);
		if ($raw === '') {
			return false;
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
}

if (!function_exists('store_profile_validate_registration')) {
	/**
	 * @return array{ok:bool,errors:array<int,string>,data:array<string,mixed>}
	 */
	function store_profile_validate_registration(PDO $pdo, int $user_id, array $input): array
	{
		$errors = [];
		$full_name = trim((string) ($input['full_name'] ?? ''));
		$email = trim((string) ($input['email'] ?? ''));
		$birthday_raw = trim((string) ($input['birthday'] ?? ''));
		$gender = strtolower(trim((string) ($input['gender'] ?? '')));

		if ($full_name === '') {
			$errors[] = 'Please enter your full name.';
		} elseif (function_exists('mb_strlen') ? mb_strlen($full_name) > 255 : strlen($full_name) > 255) {
			$errors[] = 'Full name is too long.';
		}

		if ($email === '') {
			$errors[] = 'Please enter your email address.';
		} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errors[] = 'Please enter a valid email address.';
		} elseif (store_user_email_taken($pdo, $email, $user_id)) {
			$errors[] = 'That email is already linked to another account.';
		}

		$birthday = store_profile_normalize_birthday($birthday_raw);
		if ($birthday === false) {
			$errors[] = 'Please enter a valid date of birth.';
			$birthday = null;
		}

		if ($gender === '') {
			$errors[] = 'Please select your gender.';
		} elseif (!in_array($gender, ['female', 'male', 'other'], true)) {
			$errors[] = 'Please select a valid gender.';
			$gender = '';
		}

		return [
			'ok' => $errors === [],
			'errors' => $errors,
			'data' => [
				'full_name' => $full_name,
				'email' => $email,
				'birthday' => $birthday,
				'gender' => $gender,
			],
		];
	}
}

if (!function_exists('store_profile_validate_shipping')) {
	/**
	 * @return array{ok:bool,errors:array<int,string>,data:array<string,mixed>}
	 */
	function store_profile_validate_shipping(array $input): array
	{
		$errors = [];
		$address = trim((string) ($input['address'] ?? ''));
		$address_line_2 = trim((string) ($input['address_line_2'] ?? ''));
		$city = trim((string) ($input['city'] ?? ''));
		$state = trim((string) ($input['state'] ?? ''));
		$pincode = preg_replace('/\D+/', '', (string) ($input['pincode'] ?? ''));

		if ($address === '') {
			$errors[] = 'Please enter address line 1.';
		}
		if ($city === '') {
			$errors[] = 'Please enter city.';
		}
		if ($state === '') {
			$errors[] = 'Please select state.';
		} elseif (!in_array($state, indian_states_list(), true)) {
			$errors[] = 'Please select a valid state.';
		}
		if (strlen($pincode) !== 6) {
			$errors[] = 'Please enter a valid 6-digit pincode.';
		}

		return [
			'ok' => $errors === [],
			'errors' => $errors,
			'data' => [
				'address' => $address,
				'address_line_2' => $address_line_2,
				'city' => $city,
				'state' => $state,
				'pincode' => $pincode,
			],
		];
	}
}

if (!function_exists('store_profile_validate_password')) {
	function store_profile_validate_password(string $password, string $confirm = ''): ?string
	{
		if ($password === '') {
			return 'Please enter a password.';
		}
		if (strlen($password) < 8) {
			return 'Password must be at least 8 characters.';
		}
		if ($confirm === '') {
			return 'Please confirm your password.';
		}
		if ($password !== $confirm) {
			return 'Passwords do not match.';
		}

		return null;
	}
}

if (!function_exists('store_profile_validate_new_registration')) {
	/**
	 * Registration details + phone (no password).
	 *
	 * @return array{ok:bool,errors:array<int,string>,data:array<string,mixed>}
	 */
	function store_profile_validate_new_registration(PDO $pdo, array $input): array
	{
		$result = store_profile_validate_registration($pdo, 0, $input);
		if (!$result['ok']) {
			return $result;
		}
		$phone_raw = trim((string) ($input['phone'] ?? ''));
		$e164 = store_user_phone_e164_digits($phone_raw);
		if ($e164 === null) {
			return ['ok' => false, 'errors' => ['Please enter a valid 10-digit mobile number.'], 'data' => []];
		}
		if (store_user_phone_taken($pdo, $e164)) {
			return ['ok' => false, 'errors' => ['This mobile number is already registered. Please sign in instead.'], 'data' => []];
		}
		$result['data']['phone_e164'] = $e164;
		$result['data']['phone_display'] = store_user_phone_display($e164);

		return $result;
	}
}

if (!function_exists('store_profile_validate_registration_with_password')) {
	/**
	 * @return array{ok:bool,errors:array<int,string>,data:array<string,mixed>}
	 */
	function store_profile_validate_registration_with_password(PDO $pdo, array $input): array
	{
		$result = store_profile_validate_registration($pdo, 0, $input);
		if (!$result['ok']) {
			return $result;
		}
		$phone_raw = trim((string) ($input['phone'] ?? ''));
		$e164 = store_user_phone_e164_digits($phone_raw);
		if ($e164 === null) {
			return ['ok' => false, 'errors' => ['Please enter a valid 10-digit mobile number.'], 'data' => []];
		}
		if (store_user_phone_taken($pdo, $e164)) {
			return ['ok' => false, 'errors' => ['This mobile number is already registered. Please sign in instead.'], 'data' => []];
		}
		$pw_err = store_profile_validate_password(
			(string) ($input['password'] ?? ''),
			(string) ($input['password_confirm'] ?? '')
		);
		if ($pw_err !== null) {
			return ['ok' => false, 'errors' => [$pw_err], 'data' => []];
		}
		$result['data']['phone_e164'] = $e164;
		$result['data']['phone_display'] = store_user_phone_display($e164);

		return $result;
	}
}
