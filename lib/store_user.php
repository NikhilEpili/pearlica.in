<?php
/**
 * Storefront user (store_users) and OTP (login_otps) helpers. No auth cookie logic here.
 */
declare(strict_types=1);

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
	$stmt = $pdo->prepare('INSERT INTO store_users (email, full_name) VALUES (?, ?)');
	$stmt->execute([$email, '']);
	return (int) $pdo->lastInsertId();
}

function store_user_update(PDO $pdo, int $id, array $data): void
{
	$allowed = ['email', 'full_name', 'phone', 'address', 'address_line_2', 'city', 'state', 'pincode', 'birthday', 'gender'];
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
	$stmt = $pdo->prepare('INSERT INTO store_users (email, full_name, phone) VALUES (NULL, ?, ?)');
	$stmt->execute(['', $display]);

	return (int) $pdo->lastInsertId();
}

define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);
define('OTP_RATE_LIMIT_MINUTES', 2);

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
