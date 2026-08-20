<?php
/**
 * TeleSign OTP session helpers (register, forgot password, legacy OTP login).
 */
declare(strict_types=1);

if (!function_exists('login_telesign_rate_key')) {
	function login_telesign_rate_key(string $e164): string
	{
		return 'telesign_rate_ts_' . hash('sha256', $e164);
	}
}

if (!function_exists('login_telesign_can_send')) {
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
}

if (!function_exists('login_telesign_mark_sent')) {
	function login_telesign_mark_sent(string $e164): void
	{
		$_SESSION[login_telesign_rate_key($e164)] = time();
	}
}

if (!function_exists('login_phone_mask_last4')) {
	function login_phone_mask_last4(string $e164_digits): string
	{
		$d = preg_replace('/\D+/', '', $e164_digits);
		if (strlen($d) < 4) {
			return 'your number';
		}

		return '•••• ' . substr($d, -4);
	}
}

if (!function_exists('login_telesign_send_or_fail')) {
	/**
	 * @return array{ok:bool,error?:string,expires_at?:int,phone_mask?:string,otp_length?:int}
	 */
	function login_telesign_send_or_fail(string $e164, array $ts_cfg, int $login_otp_len): array
	{
		if (!$ts_cfg['configured']) {
			return ['ok' => false, 'error' => 'SMS verification is not configured yet. Please contact support.'];
		}
		$rate = login_telesign_can_send($e164);
		if (!$rate['allowed']) {
			$wait = (int) $rate['wait_seconds'];
			if ($wait >= 60) {
				$mins = (int) ceil($wait / 60);
				$msg = 'Please wait ' . $mins . ' minute(s) before requesting another code.';
			} else {
				$msg = 'Please wait ' . max(1, $wait) . ' second(s) before requesting another code.';
			}

			return ['ok' => false, 'error' => $msg, 'wait_seconds' => $wait];
		}
		$send = telesign_sms_verify_send($e164);
		if (empty($send['ok']) || empty($send['reference_id'])) {
			return ['ok' => false, 'error' => (string) ($send['error'] ?? 'Unable to send code. Please try again.')];
		}
		$_SESSION['telesign_ref_id'] = (string) $send['reference_id'];
		$_SESSION['telesign_phone_e164'] = $e164;
		login_telesign_mark_sent($e164);
		$sent_at = time();

		return [
			'ok' => true,
			'expires_at' => $sent_at + (OTP_EXPIRY_MINUTES * 60),
			'phone_mask' => login_phone_mask_last4($e164),
			'otp_length' => $login_otp_len,
		];
	}
}

if (!function_exists('login_telesign_verify_or_fail')) {
	/**
	 * @return array{ok:bool,error?:string,e164?:string}
	 */
	function login_telesign_verify_or_fail(string $otp, int $login_otp_len): array
	{
		$otp_digits = preg_replace('/\D+/', '', $otp);
		$ref = isset($_SESSION['telesign_ref_id']) ? trim((string) $_SESSION['telesign_ref_id']) : '';
		$sess_phone = isset($_SESSION['telesign_phone_e164']) ? trim((string) $_SESSION['telesign_phone_e164']) : '';
		if ($ref === '' || $sess_phone === '') {
			return ['ok' => false, 'error' => 'Session expired. Please request a new code.'];
		}
		if (strlen($otp_digits) !== $login_otp_len) {
			return ['ok' => false, 'error' => 'Please enter the ' . $login_otp_len . '-digit code from your SMS.'];
		}
		$chk = telesign_sms_verify_complete($ref, $otp_digits);
		if (empty($chk['verified'])) {
			return ['ok' => false, 'error' => (string) ($chk['error'] ?? 'Invalid or expired code. Please request a new one.')];
		}
		unset($_SESSION['telesign_ref_id'], $_SESSION['telesign_phone_e164']);

		return ['ok' => true, 'e164' => $sess_phone];
	}
}
