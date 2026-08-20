<?php
/**
 * Storefront user (store_users) and OTP (login_otps) helpers. No auth cookie logic here.
 */
declare(strict_types=1);

/**
 * Idempotent: create store_users if missing and add columns the app inserts on register.
 * Hostinger/MariaDB in STRICT mode rejects INSERTs that omit NOT NULL columns with no default
 * (production `address` is TEXT NOT NULL). Safe to call on every auth request.
 */
function store_user_ensure_schema(PDO $pdo): void
{
	static $done = false;
	if ($done) {
		return;
	}

	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS `store_users` (
		  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  `email` VARCHAR(255) NULL DEFAULT NULL,
		  `full_name` VARCHAR(255) NOT NULL DEFAULT \'\',
		  `phone` VARCHAR(100) NULL DEFAULT NULL,
		  `password_hash` VARCHAR(255) NULL DEFAULT NULL,
		  `address` TEXT NULL,
		  `address_line_2` VARCHAR(255) NULL DEFAULT NULL,
		  `city` VARCHAR(255) NULL DEFAULT NULL,
		  `state` VARCHAR(255) NULL DEFAULT NULL,
		  `pincode` VARCHAR(20) NULL DEFAULT NULL,
		  `birthday` DATE NULL DEFAULT NULL,
		  `gender` VARCHAR(50) NULL DEFAULT NULL,
		  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  UNIQUE KEY `uk_email` (`email`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
	);

	$existing = [];
	$stmt = $pdo->query(
		"SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE, COLUMN_DEFAULT
		 FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_users'"
	);
	if ($stmt) {
		foreach ($stmt as $row) {
			$existing[strtolower((string) $row['COLUMN_NAME'])] = $row;
		}
	}

	$add = [
		'password_hash' => 'VARCHAR(255) NULL DEFAULT NULL',
		'address' => 'TEXT NULL',
		'address_line_2' => 'VARCHAR(255) NULL DEFAULT NULL',
		'city' => 'VARCHAR(255) NULL DEFAULT NULL',
		'state' => 'VARCHAR(255) NULL DEFAULT NULL',
		'pincode' => 'VARCHAR(20) NULL DEFAULT NULL',
		'birthday' => 'DATE NULL DEFAULT NULL',
		'gender' => 'VARCHAR(50) NULL DEFAULT NULL',
		'phone' => 'VARCHAR(100) NULL DEFAULT NULL',
		'full_name' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
		'email' => 'VARCHAR(255) NULL DEFAULT NULL',
	];
	foreach ($add as $column => $definition) {
		if (!isset($existing[$column])) {
			try {
				$pdo->exec('ALTER TABLE `store_users` ADD COLUMN `' . $column . '` ' . $definition);
			} catch (Throwable $e) {
				error_log('Pearlica store_users add ' . $column . ': ' . $e->getMessage());
			}
		}
	}

	// TEXT NOT NULL with no default breaks account creation on MariaDB STRICT.
	$address = $existing['address'] ?? null;
	if (is_array($address) && strtoupper((string) $address['IS_NULLABLE']) === 'NO') {
		try {
			$pdo->exec('ALTER TABLE `store_users` MODIFY `address` TEXT NULL');
		} catch (Throwable $e) {
			error_log('Pearlica store_users modify address: ' . $e->getMessage());
		}
	}

	try {
		$pdo->exec("UPDATE `store_users` SET `email` = NULL WHERE `email` = ''");
	} catch (Throwable $e) {
		error_log('Pearlica store_users empty email cleanup: ' . $e->getMessage());
	}

	$done = true;
}

function store_user_find_by_id(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare('SELECT * FROM store_users WHERE id = ? LIMIT 1');
	$stmt->execute([$id]);
	$row = $stmt->fetch();
	if (!$row)
		return null;
	$row['address_line_2'] = $row['address_line_2'] ?? '';
	return $row;
}

function store_user_find_by_email(PDO $pdo, string $email): ?array
{
	$stmt = $pdo->prepare('SELECT * FROM store_users WHERE email = ? LIMIT 1');
	$stmt->execute([$email]);
	$row = $stmt->fetch();
	if (!$row)
		return null;
	$row['address_line_2'] = $row['address_line_2'] ?? '';
	return $row;
}

function store_user_create(PDO $pdo, string $email): int
{
	store_user_ensure_schema($pdo);
	$email = trim($email);
	$stmt = $pdo->prepare(
		'INSERT INTO store_users (email, full_name, phone, address, address_line_2, city, state, pincode)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
	);
	$stmt->execute([$email === '' ? null : $email, '', '', '', '', '', '', '']);
	return (int) $pdo->lastInsertId();
}

function store_user_update(PDO $pdo, int $id, array $data): void
{
	$allowed = ['email', 'full_name', 'phone', 'address', 'address_line_2', 'city', 'state', 'pincode', 'birthday', 'gender', 'password_hash'];
	$set = [];
	$params = [];
	foreach ($allowed as $k) {
		if (!array_key_exists($k, $data)) {
			continue;
		}
		$v = $data[$k];
		if ($k === 'birthday') {
			$v = ($v === '' || $v === null) ? null : $v;
			$set[] = 'birthday = ?';
			$params[] = $v;
		} elseif ($k === 'password_hash') {
			$set[] = 'password_hash = ?';
			$params[] = (string) $v;
		} elseif ($k === 'email') {
			// Empty → NULL so UNIQUE(email) still allows multiple users without email.
			$email = trim((string) ($v ?? ''));
			$set[] = 'email = ?';
			$params[] = ($email === '') ? null : $email;
		} else {
			$set[] = $k . ' = ?';
			$params[] = (string) $v;
		}
	}
	if ($set === []) {
		return;
	}
	$params[] = $id;
	$sql = 'UPDATE store_users SET ' . implode(', ', $set) . ' WHERE id = ?';
	$pdo->prepare($sql)->execute($params);
}

/**
 * True when another store_users row already owns this email.
 */
function store_user_email_taken(PDO $pdo, string $email, int $except_user_id = 0): bool
{
	$email = trim($email);
	if ($email === '') {
		return false;
	}
	$stmt = $pdo->prepare('SELECT id FROM store_users WHERE email = ? AND id <> ? LIMIT 1');
	$stmt->execute([$email, $except_user_id]);
	return (bool) $stmt->fetchColumn();
}

function store_user_ensure_exists(PDO $pdo, string $email): int
{
	$user = store_user_find_by_email($pdo, $email);
	if ($user) {
		return (int) $user['id'];
	}
	store_user_create($pdo, $email);
	return (int) $pdo->lastInsertId();
}

/**
 * Normalize Indian mobile to E.164 digits (no +), e.g. 919876543210.
 */
function store_user_phone_e164_digits(string $input): ?string
{
	$d = preg_replace('/\D+/', '', $input);
	if ($d === '') {
		return null;
	}
	if (strlen($d) === 10 && $d[0] >= '6' && $d[0] <= '9') {
		return '91' . $d;
	}
	if (strlen($d) === 12 && str_starts_with($d, '91')) {
		return $d;
	}
	if (strlen($d) === 11 && $d[0] === '0') {
		$rest = substr($d, 1);
		if (strlen($rest) === 10 && $rest[0] >= '6' && $rest[0] <= '9') {
			return '91' . $rest;
		}
	}

	return null;
}

function store_user_find_by_phone_e164(PDO $pdo, string $e164_digits): ?array
{
	$e164_digits = preg_replace('/\D+/', '', $e164_digits);
	$ten = '';
	if (strlen($e164_digits) === 12 && str_starts_with($e164_digits, '91')) {
		$ten = substr($e164_digits, 2);
	} elseif (strlen($e164_digits) === 10 && $e164_digits[0] >= '6' && $e164_digits[0] <= '9') {
		$ten = $e164_digits;
	}
	if ($ten === '' || strlen($ten) !== 10) {
		return null;
	}
	$variants = [$ten, '+91' . $ten, '91' . $ten];
	$placeholders = implode(',', array_fill(0, count($variants), '?'));
	$stmt = $pdo->prepare("SELECT * FROM store_users WHERE phone IN ($placeholders) ORDER BY id DESC LIMIT 1");
	$stmt->execute($variants);
	$row = $stmt->fetch();
	if (!$row) {
		return null;
	}
	$row['address_line_2'] = $row['address_line_2'] ?? '';

	return $row;
}

function store_user_ensure_exists_by_phone(PDO $pdo, string $e164_digits): int
{
	$user = store_user_find_by_phone_e164($pdo, $e164_digits);
	if ($user) {
		return (int) $user['id'];
	}
	$e164_digits = preg_replace('/\D+/', '', $e164_digits);
	$ten = '';
	if (strlen($e164_digits) === 12 && str_starts_with($e164_digits, '91')) {
		$ten = substr($e164_digits, 2);
	} elseif (strlen($e164_digits) === 10) {
		$ten = $e164_digits;
	}
	$display = $ten !== '' ? '+91' . $ten : '';
	store_user_ensure_schema($pdo);
	$stmt = $pdo->prepare(
		'INSERT INTO store_users (email, full_name, phone, address, address_line_2, city, state, pincode)
		 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)'
	);
	$stmt->execute(['', $display, '', '', '', '', '']);

	return (int) $pdo->lastInsertId();
}

function store_user_phone_display(string $e164_digits): string
{
	$d = preg_replace('/\D+/', '', $e164_digits);
	if (strlen($d) === 12 && str_starts_with($d, '91')) {
		return '+91' . substr($d, 2);
	}
	if (strlen($d) === 10) {
		return '+91' . $d;
	}

	return $d !== '' ? ('+' . $d) : '';
}

function store_user_phone_taken(PDO $pdo, string $e164_digits, int $except_user_id = 0): bool
{
	$user = store_user_find_by_phone_e164($pdo, $e164_digits);
	if (!$user) {
		return false;
	}

	return (int) ($user['id'] ?? 0) !== $except_user_id;
}

function store_user_has_password(array $user): bool
{
	return trim((string) ($user['password_hash'] ?? '')) !== '';
}

function store_user_verify_password(array $user, string $password): bool
{
	$hash = (string) ($user['password_hash'] ?? '');
	if ($hash === '' || $password === '') {
		return false;
	}

	return password_verify($password, $hash);
}

function store_user_set_password(PDO $pdo, int $user_id, string $password): void
{
	store_user_update($pdo, $user_id, [
		'password_hash' => password_hash($password, PASSWORD_DEFAULT),
	]);
}

/**
 * @param array{full_name:string,email:string,phone_display:string,birthday:?string,gender:string} $profile
 */
function store_user_create_registered(PDO $pdo, array $profile, string $password): int
{
	store_user_ensure_schema($pdo);
	$email = trim((string) ($profile['email'] ?? ''));
	$stmt = $pdo->prepare(
		'INSERT INTO store_users (email, full_name, phone, birthday, gender, password_hash, address, address_line_2, city, state, pincode)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
	);
	$stmt->execute([
		$email === '' ? null : $email,
		trim((string) ($profile['full_name'] ?? '')),
		trim((string) ($profile['phone_display'] ?? '')),
		$profile['birthday'] ?? null,
		trim((string) ($profile['gender'] ?? '')),
		password_hash($password, PASSWORD_DEFAULT),
		'',
		'',
		'',
		'',
		'',
	]);

	return (int) $pdo->lastInsertId();
}

/**
 * Profile fields required after OTP login (address collected separately at checkout).
 */
function store_user_registration_complete(array $user): bool
{
	$name = trim((string) ($user['full_name'] ?? ''));
	$email = trim((string) ($user['email'] ?? ''));
	$birthday = trim((string) ($user['birthday'] ?? ''));
	$gender = strtolower(trim((string) ($user['gender'] ?? '')));
	if ($name === '') {
		return false;
	}
	if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return false;
	}
	if ($birthday === '') {
		return false;
	}
	if (!in_array($gender, ['female', 'male', 'other'], true)) {
		return false;
	}

	return true;
}

/**
 * Shipping address required before placing an order.
 */
function store_user_shipping_complete(array $user, ?PDO $pdo = null): bool
{
	if ($pdo instanceof PDO) {
		if (!function_exists('store_user_has_saved_address')) {
			require_once __DIR__ . '/store_user_address.php';
		}
		if (store_user_has_saved_address($pdo, (int) ($user['id'] ?? 0))) {
			return true;
		}
	}
	$address = trim((string) ($user['address'] ?? ''));
	$city = trim((string) ($user['city'] ?? ''));
	$state = trim((string) ($user['state'] ?? ''));
	$pincode = preg_replace('/\D+/', '', (string) ($user['pincode'] ?? ''));
	if ($address === '' || $city === '' || $state === '') {
		return false;
	}

	return strlen($pincode) === 6;
}

/**
 * Safe profile payload for modal forms / client prefill.
 */
function store_user_profile_for_client(array $user, ?PDO $pdo = null): array
{
	return [
		'full_name' => (string) ($user['full_name'] ?? ''),
		'email' => trim((string) ($user['email'] ?? '')),
		'phone' => (string) ($user['phone'] ?? ''),
		'birthday' => (string) ($user['birthday'] ?? ''),
		'gender' => (string) ($user['gender'] ?? ''),
		'address' => (string) ($user['address'] ?? ''),
		'address_line_2' => (string) ($user['address_line_2'] ?? ''),
		'city' => (string) ($user['city'] ?? ''),
		'state' => (string) ($user['state'] ?? ''),
		'pincode' => (string) ($user['pincode'] ?? ''),
		'registration_complete' => store_user_registration_complete($user),
		'shipping_complete' => store_user_shipping_complete($user, $pdo),
	];
}

define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);
define('OTP_RATE_LIMIT_MINUTES', 1);

/**
 * Check if a new OTP can be requested for this email. Returns ['allowed' => bool, 'wait_seconds' => int (0 if allowed)].
 */
function login_otp_can_request(PDO $pdo, string $email): array
{
	$email = trim($email);
	if ($email === '') {
		return ['allowed' => false, 'wait_seconds' => 0];
	}
	$stmt = $pdo->prepare('SELECT UNIX_TIMESTAMP(created_at) AS created_ts FROM login_otps WHERE email = ? ORDER BY id DESC LIMIT 1');
	$stmt->execute([$email]);
	$row = $stmt->fetch();
	if (!$row || !isset($row['created_ts'])) {
		return ['allowed' => true, 'wait_seconds' => 0];
	}
	$elapsed = time() - (int) $row['created_ts'];
	$cooldown = OTP_RATE_LIMIT_MINUTES * 60;
	if ($elapsed >= $cooldown) {
		return ['allowed' => true, 'wait_seconds' => 0];
	}
	return ['allowed' => false, 'wait_seconds' => $cooldown - $elapsed];
}

function login_otp_create(PDO $pdo, string $email): ?string
{
	$email = trim($email);
	$check = login_otp_can_request($pdo, $email);
	if (!$check['allowed']) {
		return null;
	}
	$pdo->prepare('DELETE FROM login_otps WHERE email = ?')->execute([$email]);
	$code = '';
	for ($i = 0; $i < OTP_LENGTH; $i++) {
		$code .= (string) random_int(0, 9);
	}
	$stmt = $pdo->prepare('INSERT INTO login_otps (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))');
	$stmt->execute([$email, $code, OTP_EXPIRY_MINUTES]);
	return $code;
}

function login_otp_verify(PDO $pdo, string $email, string $code): bool
{
	$email = trim($email);
	$code = trim($code);
	if ($code === '')
		return false;
	$stmt = $pdo->prepare('SELECT id FROM login_otps WHERE email = ? AND code = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
	$stmt->execute([$email, $code]);
	$row = $stmt->fetch();
	if (!$row)
		return false;
	$del = $pdo->prepare('DELETE FROM login_otps WHERE email = ?');
	$del->execute([$email]);
	return true;
}

function store_user_send_otp_email(string $email, string $code): bool
{
	require_once __DIR__ . '/email_notifications.php';
	return email_customer_login_otp($email, $code);
}
