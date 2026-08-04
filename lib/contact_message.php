<?php
/**
 * Contact form submissions storage. Uses prepared statements only.
 *
 * Table required: contact_messages (see sql/create_contact_messages_table.sql)
 */
declare(strict_types=1);

function contact_message_create(PDO $pdo, array $data): int
{
	$full_name = trim((string) ($data['full_name'] ?? ''));
	$email = trim((string) ($data['email'] ?? ''));
	$phone = trim((string) ($data['phone'] ?? ''));
	$order_id = trim((string) ($data['order_id'] ?? ''));
	$subject = trim((string) ($data['subject'] ?? ''));
	$message = trim((string) ($data['message'] ?? ''));
	$ip_address = trim((string) ($data['ip_address'] ?? ''));
	$user_agent = trim((string) ($data['user_agent'] ?? ''));

	$stmt = $pdo->prepare('INSERT INTO contact_messages (full_name, email, phone, order_id, subject, message, ip_address, user_agent, email_sent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)');
	$stmt->execute([$full_name, $email, $phone, $order_id, $subject, $message, $ip_address, $user_agent]);
	return (int) $pdo->lastInsertId();
}

function contact_message_mark_email_status(PDO $pdo, int $id, int $email_sent): void
{
	$email_sent = $email_sent ? 1 : 0;
	$pdo->prepare('UPDATE contact_messages SET email_sent = ?, email_sent_at = IF(?, NOW(), NULL) WHERE id = ?')->execute([$email_sent, $email_sent, $id]);
}

