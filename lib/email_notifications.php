<?php
/**
 * High-level transactional / admin notification emails (templates in /templates/email).
 */
declare(strict_types=1);

require_once __DIR__ . '/email_template.php';

function email_admin_recipient(): string
{
	return defined('ADMIN_NOTIFY_EMAIL') ? (string) ADMIN_NOTIFY_EMAIL : 'contact@pearlica.in';
}

function email_site_url(): string
{
	return defined('SITE_PUBLIC_URL') ? rtrim((string) SITE_PUBLIC_URL, '/') : 'https://pearlica.in';
}

function email_format_inr_paise(int $paise): string
{
	return '₹' . number_format(max(0, $paise) / 100, 2);
}

/**
 * @param array<int, array<string, mixed>> $items
 */
function email_order_items_table_html(array $items): string
{
	$rows = '';
	foreach ($items as $it) {
		$name = htmlspecialchars((string) ($it['product_name'] ?? ''), ENT_QUOTES, 'UTF-8');
		$sku = htmlspecialchars((string) ($it['sku'] ?? ''), ENT_QUOTES, 'UTF-8');
		$qty = (int) ($it['quantity'] ?? 0);
		$line = email_format_inr_paise((int) ($it['line_total_paise'] ?? 0));
		$rows .= '<tr>'
			. '<td style="padding:10px 12px;border-bottom:1px solid #e8e8e8;font-size:13px;color:#0a0a0a;">' . $name . '</td>'
			. '<td style="padding:10px 12px;border-bottom:1px solid #e8e8e8;font-size:12px;color:#666;">' . $sku . '</td>'
			. '<td style="padding:10px 12px;border-bottom:1px solid #e8e8e8;font-size:12px;color:#666;text-align:center;">' . $qty . '</td>'
			. '<td style="padding:10px 12px;border-bottom:1px solid #e8e8e8;font-size:13px;color:#0a0a0a;text-align:right;">' . $line . '</td>'
			. '</tr>';
	}
	return $rows;
}

function email_notify_admin_login(string $username, ?string $ip, ?string $user_agent): void
{
	$admin = email_admin_recipient();
	$when = date('D, d M Y H:i:s T');
	$html = email_template_render('admin_login_notification', [
		'username' => $username,
		'login_time' => $when,
		'ip_address' => $ip ?? '—',
		'user_agent' => $user_agent ?? '—',
		'site_url' => email_site_url(),
	]);
	if ($html !== '') {
		email_send_html($admin, 'Admin panel login — ' . $username, $html);
	}
}

function email_notify_admin_order_placed(PDO $pdo, int $order_id): void
{
	require_once __DIR__ . '/order.php';
	$order = order_find_by_id($pdo, $order_id);
	if (!$order) {
		return;
	}
	$items = order_items_list($pdo, $order_id);
	$method = (string) ($order['payment_method'] ?? '');
	$status = (string) ($order['status'] ?? '');
	$status_note = match ($status) {
		'paid' => 'Payment received.',
		'cod_pending' => 'Cash on delivery — awaiting fulfilment.',
		default => 'Customer is completing online checkout (pending payment).',
	};
	$addr = (string) ($order['shipping_address'] ?? '');
	$addr_html = nl2br(htmlspecialchars($addr, ENT_QUOTES, 'UTF-8'));
	$vars = [
		'order_number' => (string) ($order['order_number'] ?? ''),
		'order_id' => (string) $order_id,
		'payment_method' => $method,
		'order_status' => $status,
		'status_note' => $status_note,
		'customer_name' => (string) ($order['shipping_name'] ?? ''),
		'customer_email' => (string) ($order['shipping_email'] ?? ''),
		'customer_phone' => (string) ($order['shipping_phone'] ?? ''),
		'shipping_city' => (string) ($order['shipping_city'] ?? ''),
		'shipping_state' => (string) ($order['shipping_state'] ?? ''),
		'shipping_pincode' => (string) ($order['shipping_pincode'] ?? ''),
		'subtotal_formatted' => email_format_inr_paise((int) ($order['subtotal_paise'] ?? 0)),
		'shipping_formatted' => email_format_inr_paise((int) ($order['shipping_paise'] ?? 0)),
		'discount_formatted' => email_format_inr_paise((int) ($order['discount_paise'] ?? 0)),
		'total_formatted' => email_format_inr_paise((int) ($order['total_paise'] ?? 0)),
		'shipping_address_html' => $addr_html,
		'items_table_html' => email_order_items_table_html($items),
		'site_url' => email_site_url(),
	];
	$html = email_template_render('order_placed_admin', $vars);
	if ($html !== '') {
		$sub = 'New order ' . ($order['order_number'] ?? $order_id) . ' — ' . strtoupper($method);
		email_send_html(email_admin_recipient(), $sub, $html);
	}
}

function email_notify_admin_payment_success(PDO $pdo, int $order_id, string $provider_payment_id): void
{
	require_once __DIR__ . '/order.php';
	$order = order_find_by_id($pdo, $order_id);
	if (!$order) {
		return;
	}
	$html = email_template_render('payment_success_admin', [
		'order_number' => (string) ($order['order_number'] ?? ''),
		'order_id' => (string) $order_id,
		'customer_email' => (string) ($order['shipping_email'] ?? ''),
		'total_formatted' => email_format_inr_paise((int) ($order['total_paise'] ?? 0)),
		'provider_payment_id' => $provider_payment_id,
		'site_url' => email_site_url(),
	]);
	if ($html !== '') {
		email_send_html(
			email_admin_recipient(),
			'Payment received — Order ' . ($order['order_number'] ?? $order_id),
			$html
		);
	}
}

function email_notify_admin_payment_failed(
	PDO $pdo,
	int $order_id,
	string $provider_order_id,
	string $provider_payment_id,
	string $failure_detail
): void {
	require_once __DIR__ . '/order.php';
	$order = order_find_by_id($pdo, $order_id);
	$num = $order ? (string) ($order['order_number'] ?? '') : (string) $order_id;
	$html = email_template_render('payment_failed_admin', [
		'order_number' => $num,
		'order_id' => (string) $order_id,
		'provider_order_id' => $provider_order_id,
		'provider_payment_id' => $provider_payment_id,
		'failure_detail' => $failure_detail !== '' ? $failure_detail : 'No description from provider.',
		'customer_email' => $order ? (string) ($order['shipping_email'] ?? '') : '—',
		'total_formatted' => $order ? email_format_inr_paise((int) ($order['total_paise'] ?? 0)) : '—',
		'site_url' => email_site_url(),
	]);
	if ($html !== '') {
		email_send_html(email_admin_recipient(), 'Payment failed — Order ' . $num, $html);
	}
}

/**
 * @param array{full_name?: string, email?: string, phone?: string, order_id?: string, subject?: string, message?: string, submitted_on?: string} $data
 */
function email_notify_admin_contact(array $data, int $contact_id): bool
{
	$message_plain = (string) ($data['message'] ?? '');
	$message_html = nl2br(htmlspecialchars($message_plain, ENT_QUOTES, 'UTF-8'));
	$email = (string) ($data['email'] ?? '');
	$reply_href = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
		? 'mailto:' . rawurlencode($email)
		: 'mailto:contact@pearlica.in';
	$vars = [
		'full_name' => (string) ($data['full_name'] ?? ''),
		'email' => $email,
		'phone_display' => (string) ($data['phone'] ?? '') !== '' ? (string) ($data['phone'] ?? '') : '—',
		'order_display' => (string) ($data['order_id'] ?? '') !== '' ? (string) ($data['order_id'] ?? '') : '—',
		'subject_badge' => (string) ($data['subject'] ?? '') !== '' ? (string) ($data['subject'] ?? '') : '—',
		'message_html' => $message_html,
		'submitted_on' => (string) ($data['submitted_on'] ?? ''),
		'reply_href' => $reply_href,
		'contact_id' => (string) $contact_id,
		'site_url' => email_site_url(),
	];
	$html = email_template_render('contact_form_admin', $vars);
	if ($html === '') {
		return false;
	}
	$sub = 'New contact request — ' . ($data['subject'] ?? 'General') . ' (ID #' . $contact_id . ')';
	$headers_reply = $email !== '' ? $email : null;
	return email_send_html(email_admin_recipient(), $sub, $html, $headers_reply);
}

function email_customer_login_otp(string $email, string $code): bool
{
	$code = trim($code);
	if ($code === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return false;
	}
	$otp_safe = (strlen($code) === 6 && ctype_digit($code)) ? $code : htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
	$html = email_template_render('login_otp_customer', [
		'otp_code_html' => $otp_safe,
		'expiry_minutes' => (string) (defined('OTP_EXPIRY_MINUTES') ? OTP_EXPIRY_MINUTES : 10),
		'site_url' => email_site_url(),
	]);
	if ($html === '') {
		return false;
	}
	return email_send_html($email, 'Your Login Code — Pearlica', $html, 'contact@pearlica.in');
}
