<?php
/**
 * Load and render HTML email templates from /templates/email/*.html
 * Placeholders: {{key}} — values are HTML-escaped unless the key ends with _html.
 */
declare(strict_types=1);

function email_templates_root(): string
{
	$root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
	return $root . '/templates/email';
}

/**
 * @param array<string, string> $vars
 */
function email_template_render(string $name, array $vars): string
{
	$name = preg_replace('/[^a-z0-9_-]/i', '', $name) ?? '';
	if ($name === '') {
		return '';
	}
	$path = email_templates_root() . '/' . $name . '.html';
	if (!is_readable($path)) {
		return '';
	}
	$html = (string) file_get_contents($path);
	foreach ($vars as $key => $value) {
		if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/i', $key)) {
			continue;
		}
		$token = '{{' . $key . '}}';
		if (str_ends_with(strtolower($key), '_html')) {
			$html = str_replace($token, (string) $value, $html);
		} else {
			$html = str_replace($token, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $html);
		}
	}
	return $html;
}

function email_encode_subject(string $subject): string
{
	if (function_exists('mb_encode_mimeheader')) {
		return mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
	}
	return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

/**
 * Send multipart HTML email via PHP mail().
 */
function email_send_html(string $to, string $subject, string $html_body, ?string $reply_to = null): bool
{
	$to = trim($to);
	if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
		return false;
	}
	$from_name = defined('MAIL_FROM_NAME') ? (string) MAIL_FROM_NAME : 'Pearlica';
	$from_addr = defined('MAIL_FROM_ADDRESS') ? (string) MAIL_FROM_ADDRESS : 'noreply@pearlica.in';
	$from_line = $from_name . ' <' . $from_addr . '>';
	$headers = 'From: ' . $from_line . "\r\n"
		. 'MIME-Version: 1.0' . "\r\n"
		. 'Content-Type: text/html; charset=UTF-8' . "\r\n";
	if ($reply_to !== null && $reply_to !== '') {
		$headers .= 'Reply-To: ' . $reply_to . "\r\n";
	}
	$enc_subject = email_encode_subject($subject);
	return @mail($to, $enc_subject, $html_body, $headers);
}
