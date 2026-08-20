<?php
/**
 * TeleSign SMS Verify API (REST). No external packages — curl only.
 *
 * Send OTP: POST /v1/verify/sms with phone_number, verify_code (we generate N digits),
 * and template ($$CODE$$ → code). Complete: GET /v1/verify/{reference_id}?verify_code=...
 */
declare(strict_types=1);

function telesign_config(): array
{
	$cid = trim((string) (defined('TELESIGN_CUSTOMER_ID') ? TELESIGN_CUSTOMER_ID : ''));
	$key = (string) (defined('TELESIGN_API_KEY') ? TELESIGN_API_KEY : '');

	return [
		'base' => rtrim((string) (defined('TELESIGN_REST_BASE') ? TELESIGN_REST_BASE : 'https://rest-ww.telesign.com'), '/'),
		'customer_id' => $cid,
		'api_key' => $key,
		'configured' => $cid !== '' && $key !== '',
	];
}

/**
 * Human-readable detail from TeleSign JSON (status + errors[]).
 */
function telesign_format_error_message(array $data): string
{
	$parts = [];
	if (!empty($data['status']['description']) && is_string($data['status']['description'])) {
		$parts[] = (string) $data['status']['description'];
	}
	$errs = $data['errors'] ?? [];
	if (is_array($errs)) {
		foreach ($errs as $e) {
			if (!is_array($e)) {
				continue;
			}
			$code = isset($e['code']) ? (int) $e['code'] : 0;
			$desc = isset($e['description']) ? trim((string) $e['description']) : '';
			if ($desc === '') {
				continue;
			}
			$parts[] = $desc;
			if ($code === -10033 || $code === 10033) {
				$parts[] = 'Trial accounts can usually only send OTP to the phone number you verified in the TeleSign portal.';
			}
			if (stripos($desc, 'Not Allowed Host') !== false || stripos($desc, 'not allowed host') !== false) {
				$parts[] = 'Add your server’s public outbound IP to TeleSign’s allowed list (Customer Portal → security / IP allowlist).';
			}
			if (stripos($desc, 'DLT') !== false || $code === 517) {
				$parts[] = 'For India (+91), set TELESIGN_DLT_TEMPLATE_ID and TELESIGN_DLT_ENTITY_ID in config/telesign.credentials.php if TeleSign requires them for your account.';
			}
		}
	}
	$msg = implode(' ', array_values(array_unique(array_filter($parts))));

	return $msg !== '' ? $msg : 'Request rejected by SMS provider.';
}

/**
 * True when POST /v1/verify/sms indicates the SMS was accepted for sending.
 */
function telesign_sms_send_status_ok(array $data): bool
{
	$errs = $data['errors'] ?? null;
	if (is_array($errs) && $errs !== []) {
		return false;
	}
	$sc = isset($data['status']['code']) ? (int) $data['status']['code'] : -1;
	if ($sc === 500) {
		return false;
	}
	if ($sc >= 501 && $sc <= 599) {
		return false;
	}
	if ($sc >= 207 && $sc <= 238) {
		return false;
	}

	return in_array($sc, [200, 203, 290, 292, 295], true);
}

/**
 * @return array{ok: bool, data?: array, reference_id?: string, error?: string, raw?: string, http_status?: int}
 */
function telesign_request(string $method, string $path, ?array $query = null, ?string $body = null): array
{
	$cfg = telesign_config();
	if (!$cfg['configured']) {
		return ['ok' => false, 'error' => 'SMS verification is not configured.'];
	}
	$url = $cfg['base'] . '/' . ltrim($path, '/');
	if ($query !== null && $query !== []) {
		$url .= '?' . http_build_query($query);
	}
	$auth = base64_encode($cfg['customer_id'] . ':' . $cfg['api_key']);
	$ch = curl_init($url);
	if ($ch === false) {
		return ['ok' => false, 'error' => 'Unable to start request'];
	}
	$headers = ['Authorization: Basic ' . $auth];
	$m = strtoupper($method);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 25);
	// Windows PHP often ships without a CA bundle; pin Mozilla CAs when available.
	$ca = '';
	if (defined('BASE_PATH')) {
		$ca = BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem';
	}
	if ($ca === '' || !is_file($ca)) {
		$iniCa = (string) ini_get('curl.cainfo');
		$ca = ($iniCa !== '' && is_file($iniCa)) ? $iniCa : '';
	}
	if ($ca !== '') {
		curl_setopt($ch, CURLOPT_CAINFO, $ca);
	}
	if ($m === 'GET') {
		curl_setopt($ch, CURLOPT_HTTPGET, true);
	} else {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $m);
		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			$headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
		}
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$res = curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err = curl_error($ch);
	curl_close($ch);
	if ($res === false) {
		return ['ok' => false, 'error' => $err !== '' ? $err : 'Request failed'];
	}
	$raw = (string) $res;
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return ['ok' => false, 'error' => 'TeleSign: invalid response', 'raw' => $raw, 'http_status' => $status];
	}
	if ($status >= 200 && $status < 300) {
		return ['ok' => true, 'data' => $decoded, 'raw' => $raw, 'http_status' => $status];
	}

	return [
		'ok' => false,
		'error' => 'TeleSign: ' . telesign_format_error_message($decoded),
		'data' => $decoded,
		'raw' => $raw,
		'http_status' => $status,
	];
}

/**
 * OTP digit count for TeleSign verify_code (clamped between 4 and 8).
 */
function telesign_login_otp_digit_count(): int
{
	$n = defined('TELESIGN_LOGIN_OTP_DIGITS') ? (int) TELESIGN_LOGIN_OTP_DIGITS : 4;
	if ($n < 4) {
		$n = 4;
	}
	if ($n > 8) {
		$n = 8;
	}

	return $n;
}

/**
 * SMS template for login OTP; must include $$CODE$$.
 */
function telesign_login_otp_sms_template(): string
{
	$tpl = defined('TELESIGN_LOGIN_OTP_SMS_TEMPLATE') ? trim((string) TELESIGN_LOGIN_OTP_SMS_TEMPLATE) : '';
	if ($tpl === '' || strpos($tpl, '$$CODE$$') === false) {
		$tpl = '$$CODE$$ is your Pearlica sign-in code. Valid 10 min. Do not share with anyone.';
	}

	return $tpl;
}

/**
 * @return array{ok: bool, reference_id?: string, error?: string}
 */
function telesign_sms_verify_send(string $phone_e164_digits): array
{
	$phone_e164_digits = preg_replace('/\D+/', '', $phone_e164_digits);
	if ($phone_e164_digits === '' || strlen($phone_e164_digits) < 10) {
		return ['ok' => false, 'error' => 'Invalid phone number'];
	}

	$len = telesign_login_otp_digit_count();
	$max = (10 ** $len) - 1;
	$code = str_pad((string) random_int(0, $max), $len, '0', STR_PAD_LEFT);

	$params = [
		'phone_number' => $phone_e164_digits,
		'verify_code' => $code,
	];

	$dlt_tid = defined('TELESIGN_DLT_TEMPLATE_ID') ? trim((string) TELESIGN_DLT_TEMPLATE_ID) : '';
	$dlt_eid = defined('TELESIGN_DLT_ENTITY_ID') ? trim((string) TELESIGN_DLT_ENTITY_ID) : '';

	// Only attach a custom template when India DLT IDs are configured.
	// An unregistered custom template is a common reason SMS is accepted by the API
	// but never delivered on the first attempt.
	if ($dlt_tid !== '' && $dlt_eid !== '') {
		$params['template'] = telesign_login_otp_sms_template();
		$params['dlt_template_id'] = $dlt_tid;
		$params['dlt_entity_id'] = $dlt_eid;
	}

	$body = http_build_query($params);
	$res = telesign_request('POST', '/v1/verify/sms', null, $body);
	$data = is_array($res['data'] ?? null) ? $res['data'] : null;
	if (!$res['ok']) {
		return ['ok' => false, 'error' => (string) ($res['error'] ?? 'Could not send SMS')];
	}
	if ($data === null || !telesign_sms_send_status_ok($data)) {
		return ['ok' => false, 'error' => 'TeleSign: ' . telesign_format_error_message($data ?? [])];
	}
	$ref = isset($data['reference_id']) ? trim((string) $data['reference_id']) : '';
	if ($ref === '') {
		return ['ok' => false, 'error' => 'TeleSign: No reference_id in response.'];
	}

	return ['ok' => true, 'reference_id' => $ref];
}

/**
 * @return array{ok: bool, verified?: bool, error?: string}
 */
function telesign_sms_verify_complete(string $reference_id, string $verify_code): array
{
	$reference_id = trim($reference_id);
	$verify_code = preg_replace('/\D+/', '', $verify_code);
	$want_len = telesign_login_otp_digit_count();
	if ($reference_id === '' || strlen($verify_code) !== $want_len) {
		return ['ok' => false, 'verified' => false, 'error' => 'Invalid request'];
	}
	$res = telesign_request('GET', '/v1/verify/' . rawurlencode($reference_id), ['verify_code' => $verify_code]);
	if (empty($res['ok']) || !is_array($res['data'] ?? null)) {
		return ['ok' => true, 'verified' => false, 'error' => (string) ($res['error'] ?? 'Verification failed')];
	}
	$data = $res['data'];
	$code_state = (string) ($data['verify']['code_state'] ?? '');
	$verified = $code_state === 'VALID';

	return ['ok' => true, 'verified' => $verified, 'error' => $verified ? null : 'Invalid or expired code'];
}
