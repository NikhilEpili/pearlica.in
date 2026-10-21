<?php
/**
 * Guest checkout: delivery details in session (no auth cookie until optional sign-in).
 */
declare(strict_types=1);

require_once __DIR__ . '/store_profile_validation.php';

if (!function_exists('checkout_guest_session_key')) {
	function checkout_guest_session_key(): string
	{
		return 'checkout_guest';
	}
}

if (!function_exists('checkout_guest_get')) {
	/** @return array<string, mixed> */
	function checkout_guest_get(): array
	{
		$raw = $_SESSION[checkout_guest_session_key()] ?? null;
		if (!is_array($raw)) {
			return [];
		}

		return $raw;
	}
}

if (!function_exists('checkout_guest_is_complete')) {
	function checkout_guest_is_complete(?array $guest = null): bool
	{
		$guest = $guest ?? checkout_guest_get();
		$name = trim((string) ($guest['full_name'] ?? ''));
		$email = trim((string) ($guest['email'] ?? ''));
		$e164 = trim((string) ($guest['phone_e164'] ?? ''));
		$address = trim((string) ($guest['address'] ?? ''));
		$city = trim((string) ($guest['city'] ?? ''));
		$state = trim((string) ($guest['state'] ?? ''));
		$pincode = preg_replace('/\D+/', '', (string) ($guest['pincode'] ?? ''));
		if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}
		if ($e164 === '' || strlen(preg_replace('/\D+/', '', $e164)) < 10) {
			return false;
		}
		if ($address === '' || $city === '' || $state === '' || strlen($pincode) !== 6) {
			return false;
		}

		return true;
	}
}

if (!function_exists('checkout_guest_prefill_for_form')) {
	/** @return array<string, string> */
	function checkout_guest_prefill_for_form(): array
	{
		$g = checkout_guest_get();
		$phone = trim((string) ($g['phone_display'] ?? ''));
		if ($phone !== '' && preg_match('/^\+91(\d{10})$/', $phone, $m)) {
			$phone = $m[1];
		} elseif ($phone !== '') {
			$phone = preg_replace('/\D+/', '', $phone);
			if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
				$phone = substr($phone, 2);
			}
		}

		return [
			'full_name' => trim((string) ($g['full_name'] ?? '')),
			'email' => trim((string) ($g['email'] ?? '')),
			'phone' => $phone,
			'address' => trim((string) ($g['address'] ?? '')),
			'address_line_2' => trim((string) ($g['address_line_2'] ?? '')),
			'city' => trim((string) ($g['city'] ?? '')),
			'state' => trim((string) ($g['state'] ?? '')),
			'pincode' => preg_replace('/\D+/', '', (string) ($g['pincode'] ?? '')),
		];
	}
}

if (!function_exists('checkout_guest_validate_and_store')) {
	/**
	 * @return array{ok:bool,error?:string,data?:array<string,mixed>}
	 */
	function checkout_guest_validate_and_store(PDO $pdo, array $input): array
	{
		$errors = [];
		$full_name = trim((string) ($input['full_name'] ?? ''));
		$email = trim((string) ($input['email'] ?? ''));
		if ($full_name === '') {
			$errors[] = 'Please enter your full name.';
		}
		if ($email === '') {
			$errors[] = 'Please enter your email address.';
		} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errors[] = 'Please enter a valid email address.';
		}
		$phone_raw = trim((string) ($input['phone'] ?? ''));
		$e164 = store_user_phone_e164_digits($phone_raw);
		if ($e164 === null) {
			$errors[] = 'Please enter a valid 10-digit mobile number.';
		}
		$ship = store_profile_validate_shipping($input);
		if (!$ship['ok']) {
			$errors = array_merge($errors, $ship['errors']);
		}
		$except_user_id = 0;
		if ($e164 !== null) {
			$by_phone = store_user_find_by_phone_e164($pdo, $e164);
			if ($by_phone) {
				$except_user_id = (int) ($by_phone['id'] ?? 0);
			}
		}
		if ($email !== '' && store_user_email_taken($pdo, $email, $except_user_id)) {
			$errors[] = 'That email is already linked to another account.';
		}
		if ($errors !== []) {
			return ['ok' => false, 'error' => $errors[0]];
		}
		$data = $ship['data'];
		$stored = [
			'full_name' => $full_name,
			'email' => $email,
			'phone_e164' => (string) $e164,
			'phone_display' => store_user_phone_display((string) $e164),
			'address' => (string) $data['address'],
			'address_line_2' => (string) $data['address_line_2'],
			'city' => (string) $data['city'],
			'state' => (string) $data['state'],
			'pincode' => (string) $data['pincode'],
		];
		$prev = checkout_guest_get();
		if (
			isset($prev['phone_e164'])
			&& (string) $prev['phone_e164'] !== ''
			&& (string) $prev['phone_e164'] !== (string) $e164
		) {
			unset($_SESSION['checkout_cod_otp_verified_e164']);
		}
		$_SESSION[checkout_guest_session_key()] = $stored;

		return ['ok' => true, 'data' => $stored];
	}
}

if (!function_exists('checkout_guest_cod_otp_verified')) {
	function checkout_guest_cod_otp_verified(?array $guest = null): bool
	{
		$guest = $guest ?? checkout_guest_get();
		$e164 = trim((string) ($guest['phone_e164'] ?? ''));
		if ($e164 === '') {
			return false;
		}
		$verified = trim((string) ($_SESSION['checkout_cod_otp_verified_e164'] ?? ''));

		return $verified !== '' && hash_equals($verified, $e164);
	}
}

if (!function_exists('checkout_guest_mark_cod_otp_verified')) {
	function checkout_guest_mark_cod_otp_verified(string $e164): void
	{
		$_SESSION['checkout_cod_otp_verified_e164'] = preg_replace('/\D+/', '', $e164);
	}
}

if (!function_exists('store_user_upsert_guest_checkout')) {
	function store_user_upsert_guest_checkout(PDO $pdo, array $guest): int
	{
		store_user_ensure_schema($pdo);
		$e164 = (string) ($guest['phone_e164'] ?? '');
		$existing = store_user_find_by_phone_e164($pdo, $e164);
		$patch = [
			'full_name' => trim((string) ($guest['full_name'] ?? '')),
			'email' => trim((string) ($guest['email'] ?? '')),
			'phone' => trim((string) ($guest['phone_display'] ?? '')),
			'address' => trim((string) ($guest['address'] ?? '')),
			'address_line_2' => trim((string) ($guest['address_line_2'] ?? '')),
			'city' => trim((string) ($guest['city'] ?? '')),
			'state' => trim((string) ($guest['state'] ?? '')),
			'pincode' => trim((string) ($guest['pincode'] ?? '')),
		];
		if ($existing) {
			$id = (int) $existing['id'];
			store_user_update($pdo, $id, $patch);

			return $id;
		}

		$id = store_user_create_registered($pdo, [
			'full_name' => $patch['full_name'],
			'email' => $patch['email'],
			'phone_display' => $patch['phone'],
			'birthday' => null,
			'gender' => '',
		]);
		store_user_update($pdo, $id, [
			'address' => $patch['address'],
			'address_line_2' => $patch['address_line_2'],
			'city' => $patch['city'],
			'state' => $patch['state'],
			'pincode' => $patch['pincode'],
		]);

		return $id;
	}
}

if (!function_exists('checkout_resolve_order_customer')) {
	/**
	 * Logged-in user or guest session → store user id + shipping snapshot for order create.
	 *
	 * @return array{
	 *   ok:bool,
	 *   error?:string,
	 *   http?:int,
	 *   store_user_id?:int,
	 *   shipping_name?:string,
	 *   shipping_email?:string,
	 *   shipping_phone?:string,
	 *   shipping_address_1?:string,
	 *   shipping_address_2?:string,
	 *   shipping_city?:string,
	 *   shipping_state?:string,
	 *   shipping_pincode?:string
	 * }
	 */
	function checkout_resolve_order_customer(PDO $pdo, ?int $auth_user_id): array
	{
		if ($auth_user_id !== null && $auth_user_id > 0) {
			$user = store_user_find_by_id($pdo, $auth_user_id);
			if (!$user) {
				return ['ok' => false, 'error' => 'Session expired. Please sign in again.', 'http' => 401];
			}
			if (!function_exists('store_user_address_resolve_for_checkout')) {
				require_once __DIR__ . '/../lib/store_user_address.php';
			}
			$address_row = store_user_address_resolve_for_checkout($pdo, $auth_user_id, $user);
			if ($address_row) {
				store_user_address_sync_profile($pdo, $auth_user_id, $address_row);
				$user = store_user_find_by_id($pdo, $auth_user_id) ?? $user;
			}
			$name = trim((string) ($user['full_name'] ?? ''));
			$email = trim((string) ($user['email'] ?? ''));
			$phone = trim((string) ($user['phone'] ?? ''));
			$a1 = trim((string) ($user['address'] ?? ''));
			$a2 = trim((string) ($user['address_line_2'] ?? ''));
			$city = trim((string) ($user['city'] ?? ''));
			$state = trim((string) ($user['state'] ?? ''));
			$pin = trim((string) ($user['pincode'] ?? ''));
			if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				return ['ok' => false, 'error' => 'Invalid email on your profile.', 'http' => 400];
			}
			if ($name === '') {
				$name = $email !== '' ? $email : 'Customer';
			}
			if ($a1 === '' || $city === '' || $state === '' || $pin === '') {
				return ['ok' => false, 'error' => 'Please choose or add a delivery address before placing your order.', 'http' => 400];
			}

			return [
				'ok' => true,
				'store_user_id' => $auth_user_id,
				'shipping_name' => $name,
				'shipping_email' => $email,
				'shipping_phone' => $phone,
				'shipping_address_1' => $a1,
				'shipping_address_2' => $a2,
				'shipping_city' => $city,
				'shipping_state' => $state,
				'shipping_pincode' => $pin,
			];
		}

		$guest = checkout_guest_get();
		if (!checkout_guest_is_complete($guest)) {
			return ['ok' => false, 'error' => 'Please fill in your delivery details to continue.', 'http' => 400];
		}
		try {
			$user_id = store_user_upsert_guest_checkout($pdo, $guest);
		} catch (Throwable $e) {
			error_log('Pearlica guest checkout upsert: ' . $e->getMessage());

			return ['ok' => false, 'error' => 'Could not save your details. Please try again.', 'http' => 500];
		}
		$name = trim((string) ($guest['full_name'] ?? ''));
		$email = trim((string) ($guest['email'] ?? ''));

		return [
			'ok' => true,
			'store_user_id' => $user_id,
			'shipping_name' => $name !== '' ? $name : 'Customer',
			'shipping_email' => $email,
			'shipping_phone' => trim((string) ($guest['phone_display'] ?? '')),
			'shipping_address_1' => trim((string) ($guest['address'] ?? '')),
			'shipping_address_2' => trim((string) ($guest['address_line_2'] ?? '')),
			'shipping_city' => trim((string) ($guest['city'] ?? '')),
			'shipping_state' => trim((string) ($guest['state'] ?? '')),
			'shipping_pincode' => trim((string) ($guest['pincode'] ?? '')),
		];
	}
}
