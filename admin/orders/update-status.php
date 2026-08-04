<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('orders');
require_once ROOT . '/lib/order.php';
require_once ROOT . '/lib/refrens.php';

$pdo = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('orders/'));
}

if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('orders/'));
}

$id = (int) ($_POST['id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? ''));
if ($id <= 0 || $status === '') {
	flash_set('error', 'Invalid data.');
	redirect(admin_url('orders/'));
}

$allowed = ['pending_payment', 'cod_pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
if (!in_array($status, $allowed, true)) {
	flash_set('error', 'Invalid status.');
	redirect(admin_url('orders/view.php?id=' . $id));
}

$order = order_find_by_id($pdo, $id);
if (!$order) {
	flash_set('error', 'Order not found.');
	redirect(admin_url('orders/'));
}

$prev_status = (string) ($order['status'] ?? '');
order_set_status($pdo, $id, $status);

if ($status === 'paid' && $prev_status !== 'paid') {
	try {
		refrens_mark_order_invoice_paid($pdo, $id);
	} catch (Throwable $e) {
		refrens_safe_log('admin update-status order_id=' . $id . ' ' . $e->getMessage());
	}
}

flash_set('success', 'Order status updated.');
redirect(admin_url('orders/view.php?id=' . $id));

