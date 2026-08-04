<?php
require_once dirname(__DIR__) . '/config.php';
require_permission('orders');
require_once ROOT . '/lib/order.php';
require_once ROOT . '/payment/shiprocket/shiprocket_init.php';

$pdo = $GLOBALS['pdo'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect(admin_url('orders/'));
}

if (!validate_csrf()) {
	flash_set('error', 'Invalid request.');
	redirect(admin_url('orders/'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
	flash_set('error', 'Invalid order.');
	redirect(admin_url('orders/'));
}

$order = order_find_by_id($pdo, $id);
if (!$order) {
	flash_set('error', 'Order not found.');
	redirect(admin_url('orders/'));
}

$res = shiprocket_sync_order($pdo, $id);
if (empty($res['ok'])) {
	flash_set('error', 'Shiprocket sync failed: ' . (string) ($res['error'] ?? 'unknown'));
	redirect(admin_url('orders/view.php?id=' . $id));
}

flash_set('success', 'Shiprocket synced.');
redirect(admin_url('orders/view.php?id=' . $id));
