<?php
/**
 * TeleSign callbacks: inbound SMS (MO), delivery / SMS Verify status posts.
 *
 * Register in the TeleSign portal (HTTPS, port 443):
 *   https://your-domain.com/telesign-sms-callback
 * Use the same URL for “Set Callback URL” on the sender ID and for
 * Settings → Inbound SMS callbacks if the portal asks for both.
 *
 * TeleSign sends JSON POST bodies; respond with HTTP 200 and a short body.
 */
declare(strict_types=1);

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'HEAD') {
	exit;
}
if ($method === 'GET') {
	echo 'OK';
	exit;
}
if ($method !== 'POST') {
	http_response_code(405);
	header('Allow: GET, HEAD, POST');
	echo 'Method Not Allowed';
	exit;
}

$raw = file_get_contents('php://input');
if (is_string($raw) && $raw !== '') {
	require_once __DIR__ . '/config/config.php';
	if (defined('APP_DEBUG') && APP_DEBUG) {
		$max = 4000;
		$log = strlen($raw) > $max ? substr($raw, 0, $max) . '…' : $raw;
		error_log('TeleSign callback: ' . $log);
	}
}

echo 'OK';
